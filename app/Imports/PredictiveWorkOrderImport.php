<?php

namespace App\Imports;

use App\Models\Machine;
use App\Models\PredictiveWorkOrder;
use App\Models\User;
use App\Services\Predictive\PredictiveWorkOrderService;
use DateTime;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

/**
 * Imports Predictive WOs for one category. Rows are independent: each valid
 * row is inserted on its own, an invalid or duplicate row never stops the
 * rest, and an existing WO is never touched (no update path exists here).
 *
 * Failed  = the row is invalid (missing/invalid field, unknown machine, or a
 *           machine outside the importer's area).
 * Skipped = the Order Number already exists, in the database or earlier in
 *           the same file.
 */
class PredictiveWorkOrderImport implements ToCollection, WithHeadingRow
{
    public const MAX_DETAIL_ROWS = 200;

    private const REQUIRED_HEADINGS = ['order_number', 'machine_number', 'date', 'problem_description'];

    private const DATE_FORMATS = ['Y-m-d', 'd-m-Y', 'd/m/Y', 'd-m-y', 'd/m/y'];

    public int $success = 0;

    public int $failed = 0;

    public int $skipped = 0;

    /** @var list<array{row: int, order_number: string, result: string, reason: string}> */
    public array $details = [];

    public int $hiddenDetails = 0;

    public ?string $fatalError = null;

    /** @var array<string, ?Machine> */
    private array $machines = [];

    public function __construct(
        private readonly string $category,
        private readonly User $user,
        private readonly PredictiveWorkOrderService $service,
    ) {}

    public function collection(Collection $rows): void
    {
        $first = $rows->first();
        $headings = $first ? array_keys($first->toArray()) : [];
        $missing = array_diff(self::REQUIRED_HEADINGS, $headings);

        if ($missing !== []) {
            $this->fatalError = 'Required column not found: '.implode(', ', $missing)
                .'. Use these headers: Order Number, Machine Number, Date, Problem Description.';

            return;
        }

        foreach ($rows as $index => $row) {
            $values = $row->toArray();

            if ($this->isBlank($values)) {
                continue;
            }

            // +2: heading row is row 1 in the spreadsheet.
            $this->processRow($index + 2, $values);
        }
    }

    /** @param  array<string, mixed>  $values */
    private function processRow(int $line, array $values): void
    {
        $order = trim((string) ($values['order_number'] ?? ''));
        $machineNumber = trim((string) ($values['machine_number'] ?? ''));
        $woDate = $this->parseDate($values['date'] ?? null);
        $problem = trim((string) ($values['problem_description'] ?? ''));

        $errors = [];
        if ($order === '') {
            $errors[] = 'Order Number is empty';
        } elseif (mb_strlen($order) > 100) {
            $errors[] = 'Order Number is longer than 100 characters';
        }
        if ($machineNumber === '') {
            $errors[] = 'Machine Number is empty';
        }
        if (trim((string) ($values['date'] ?? '')) === '') {
            $errors[] = 'Date is empty';
        } elseif ($woDate === null) {
            $errors[] = 'Date format not recognised (use YYYY-MM-DD or DD-MM-YYYY)';
        }
        if ($problem === '') {
            $errors[] = 'Problem Description is empty';
        } elseif (mb_strlen($problem) > 2000) {
            $errors[] = 'Problem Description is longer than 2000 characters';
        }

        if ($errors !== []) {
            $this->record($line, $order, 'failed', implode('; ', $errors));

            return;
        }

        if (PredictiveWorkOrder::where('order_number', $order)->exists()) {
            $this->record($line, $order, 'skipped', 'Order Number already exists; the existing WO was left unchanged');

            return;
        }

        $machine = $this->machine($machineNumber);
        if (! $machine) {
            $this->record($line, $order, 'failed', "Machine Number {$machineNumber} tidak ditemukan di Machine Master");

            return;
        }

        if (! $this->user->isAdmin() && $this->user->area?->name !== $machine->area) {
            $this->record($line, $order, 'failed', "Mesin {$machineNumber} berada di area {$machine->area}, di luar area Anda");

            return;
        }

        try {
            $this->service->create($this->category, $machine, [
                'order_number' => $order,
                'wo_date' => $woDate,
                'problem_description' => $problem,
                // Due Date is never read from the file: always the WO date + 14 days.
                'due_date' => PredictiveWorkOrder::defaultDueDate($woDate),
            ], $this->user);
        } catch (UniqueConstraintViolationException) {
            $this->record($line, $order, 'skipped', 'Order Number already exists; the existing WO was left unchanged');

            return;
        }

        $this->success++;
    }

    private function record(int $line, string $order, string $result, string $reason): void
    {
        $result === 'failed' ? $this->failed++ : $this->skipped++;

        if (count($this->details) >= self::MAX_DETAIL_ROWS) {
            $this->hiddenDetails++;

            return;
        }

        $this->details[] = ['row' => $line, 'order_number' => $order, 'result' => $result, 'reason' => $reason];
    }

    private function machine(string $number): ?Machine
    {
        return $this->machines[$number] ??= Machine::where('machine_number', $number)->first();
    }

    /** @param  array<string, mixed>  $values */
    private function isBlank(array $values): bool
    {
        foreach ($values as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function parseDate(mixed $value): ?string
    {
        if ($value instanceof DateTime) {
            return $value->format('Y-m-d');
        }

        if (is_int($value) || is_float($value) || (is_string($value) && is_numeric(trim($value)) && (float) $value > 20000 && (float) $value < 80000)) {
            try {
                return ExcelDate::excelToDateTimeObject((float) $value)->format('Y-m-d');
            } catch (\Throwable) {
                return null;
            }
        }

        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }

        foreach (self::DATE_FORMATS as $format) {
            $date = DateTime::createFromFormat('!'.$format, $text);
            $errors = DateTime::getLastErrors();

            if ($date && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
                return $date->format('Y-m-d');
            }
        }

        return null;
    }
}
