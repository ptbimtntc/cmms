<?php

namespace App\Services;

use App\Models\PMSchedule;
use App\Models\PMSparepart;
use App\Models\Sparepart;
use App\Models\SparepartInjectionImport;
use App\Models\SparepartInjectionRow;
use App\Models\User;
use App\Support\AreaAuthorizationScope;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Sparepart Injection: applies warehouse issue lines (Month, SAP Number,
 * Qty, Order Number) to the EXISTING PM Sparepart Usage (pm_spareparts).
 *
 * Per row, in this order (first match decides the outcome):
 *  1. invalid format (Month name, Qty, ...)  -> FAILED
 *  2. Order Number / SAP Number not matched  -> FAILED (never guessed)
 *  3. usage for that PM+sparepart exists     -> SKIPPED (never overwritten;
 *     this is also what makes re-uploading a file a no-op)
 *  4. otherwise Qty of all remaining rows with the same Order+SAP is summed
 *     into ONE pm_spareparts row (marked source=WAREHOUSE) -> PROCESSED
 *
 * Month is informational only (the month the warehouse issue happened; the
 * year is the upload year). There is deliberately no per-row duplicate key.
 *
 * Everything runs in one DB transaction; a failure leaves nothing behind.
 * No price is involved: cost keeps being derived from spareparts.price by
 * the existing reports.
 */
class SparepartInjectionService
{
    public const HEADERS = ['month', 'sap number', 'qty', 'order number'];

    private const MAX_ROWS = 10000;

    /** Month name (Indonesian / English, full or abbreviated) => month number. */
    private const MONTHS = [
        'januari' => 1, 'january' => 1, 'jan' => 1,
        'februari' => 2, 'february' => 2, 'feb' => 2, 'pebruari' => 2,
        'maret' => 3, 'march' => 3, 'mar' => 3,
        'april' => 4, 'apr' => 4,
        'mei' => 5, 'may' => 5,
        'juni' => 6, 'june' => 6, 'jun' => 6,
        'juli' => 7, 'july' => 7, 'jul' => 7,
        'agustus' => 8, 'august' => 8, 'agu' => 8, 'agt' => 8, 'aug' => 8,
        'september' => 9, 'sep' => 9, 'sept' => 9,
        'oktober' => 10, 'october' => 10, 'okt' => 10, 'oct' => 10,
        'november' => 11, 'nov' => 11, 'nopember' => 11,
        'desember' => 12, 'december' => 12, 'des' => 12, 'dec' => 12,
    ];

    /**
     * @param  array<int, array<int, mixed>>  $sheet  raw sheet rows, first row = header
     *
     * @throws InvalidArgumentException when the header/file is unusable
     */
    public function import(User $user, string $filename, array $sheet): SparepartInjectionImport
    {
        $sheet = array_values($sheet);
        $columns = $this->mapHeader($sheet[0] ?? []);
        $lines = [];

        foreach (array_slice($sheet, 1, null, true) as $index => $cells) {
            $record = [
                'row_number' => $index + 1,
                'raw_month' => $this->text($cells[$columns['month']] ?? null),
                'raw_sap' => $this->text($cells[$columns['sap number']] ?? null),
                'raw_qty' => $this->text($cells[$columns['qty']] ?? null),
                'raw_order' => $this->text($cells[$columns['order number']] ?? null),
                'cell_qty' => $cells[$columns['qty']] ?? null,
            ];

            if ($record['raw_month'] === '' && $record['raw_sap'] === '' && $record['raw_qty'] === '' && $record['raw_order'] === '') {
                continue; // blank spreadsheet line
            }

            $lines[] = $record;
        }

        if ($lines === []) {
            throw new InvalidArgumentException('The file contains no data rows.');
        }

        if (count($lines) > self::MAX_ROWS) {
            throw new InvalidArgumentException('Maksimal '.self::MAX_ROWS.' baris per file.');
        }

        return DB::transaction(fn () => $this->process($user, $filename, $lines));
    }

    private function process(User $user, string $filename, array $lines): SparepartInjectionImport
    {
        $import = SparepartInjectionImport::create([
            'user_id' => $user->id,
            'area' => $user->isAdmin() ? null : $user->area?->name,
            'filename' => mb_substr($filename, 0, 250),
            'total_rows' => count($lines),
        ]);

        // 1. Parse / validate format.
        foreach ($lines as &$line) {
            $line['errors'] = [];
            $line['order'] = $this->normalizeId($line['raw_order']);
            $line['sap'] = $this->normalizeId($line['raw_sap']);
            $line['month'] = $this->parseMonth($line['raw_month']);
            $line['qty'] = $this->parseQty($line['cell_qty'], $line['raw_qty']);

            if ($line['month'] === null) {
                $line['errors'][] = 'Month must be a month name (for example Januari / January / Jan)';
            }
            if ($line['sap'] === '') {
                $line['errors'][] = 'SAP Number is empty';
            }
            if ($line['order'] === '') {
                $line['errors'][] = 'Order Number is empty';
            }
            if ($line['qty'] === null) {
                $line['errors'][] = 'Qty must be a positive whole number';
            }

            $line['status'] = $line['errors'] ? SparepartInjectionRow::FAILED : null;
            $line['reason'] = $line['errors'] ? implode('; ', $line['errors']) : null;
            $line['pm_id'] = $line['sparepart_id'] = null;
        }
        unset($line);

        $valid = collect($lines)->filter(fn ($l) => $l['status'] === null);

        // 2. Match Order Number -> PM Schedule, SAP Number -> Sparepart.
        $pmsByOrder = $this->loadSchedules($user, $valid->pluck('order')->unique()->all());
        $partsBySap = Sparepart::whereIn('material_number', $valid->pluck('sap')->unique()->all())->get()->keyBy('material_number');

        foreach ($lines as &$line) {
            if ($line['status'] !== null) {
                continue;
            }

            $pms = $pmsByOrder->get($this->orderKey($line['order']), collect());
            $part = $partsBySap->get($line['sap']);

            if ($pms->isEmpty()) {
                $line['status'] = SparepartInjectionRow::FAILED;
                $line['reason'] = 'Order Number not found on any PM Schedule';
            } elseif ($pms->count() > 1) {
                $line['status'] = SparepartInjectionRow::FAILED;
                $line['reason'] = 'Order Number matches more than one PM Schedule';
            } elseif (! $part) {
                $line['status'] = SparepartInjectionRow::FAILED;
                $line['reason'] = 'SAP Number does not match any sparepart Material Number';
            } else {
                $line['pm_id'] = $pms->first()->id;
                $line['sparepart_id'] = $part->id;
            }
        }
        unset($line);

        // 3/4. Group the remaining rows per PM + sparepart.
        $groups = [];
        foreach ($lines as $i => $line) {
            if ($line['status'] === null) {
                $groups[$line['pm_id'].'-'.$line['sparepart_id']][] = $i;
            }
        }

        $existing = PMSparepart::whereIn('pm_schedule_id', collect($groups)->map(fn ($g) => $lines[$g[0]]['pm_id'])->unique()->all())
            ->get()
            ->mapWithKeys(fn ($u) => [$u->pm_schedule_id.'-'.$u->sparepart_id => true]);

        $usageAdded = 0;

        foreach ($groups as $groupKey => $indexes) {
            if ($existing->has($groupKey)) {
                foreach ($indexes as $i) {
                    $lines[$i]['status'] = SparepartInjectionRow::SKIPPED;
                    $lines[$i]['reason'] = 'Sparepart Usage already exists on this PM Schedule (left as it was)';
                }

                continue;
            }

            $first = $lines[$indexes[0]];
            $total = array_sum(array_map(fn ($i) => $lines[$i]['qty'], $indexes));

            PMSparepart::create([
                'pm_schedule_id' => $first['pm_id'],
                'sparepart_id' => $first['sparepart_id'],
                'qty' => $total,
                'source' => PMSparepart::SOURCE_WAREHOUSE,
                'injection_import_id' => $import->id,
            ]);
            $usageAdded++;

            foreach ($indexes as $i) {
                $lines[$i]['status'] = SparepartInjectionRow::PROCESSED;
                $lines[$i]['reason'] = count($indexes) > 1
                    ? "Qty dijumlahkan dengan {$this->plural(count($indexes) - 1)} lain (total {$total})"
                    : 'Sparepart Usage ditambahkan';
            }
        }

        // Persist the per-row audit trail.
        $now = now();
        $records = array_map(fn ($l) => [
            'import_id' => $import->id,
            'row_number' => $l['row_number'],
            'month' => mb_substr($l['month'] ?? $l['raw_month'], 0, 250),
            'order_number' => mb_substr($l['raw_order'], 0, 250),
            'sap_number' => mb_substr($l['raw_sap'], 0, 250),
            'qty' => mb_substr($l['raw_qty'], 0, 250),
            'status' => $l['status'],
            'reason' => $l['reason'],
            'pm_schedule_id' => $l['pm_id'],
            'sparepart_id' => $l['sparepart_id'],
            'created_at' => $now,
            'updated_at' => $now,
        ], $lines);

        foreach (array_chunk($records, 500) as $chunk) {
            SparepartInjectionRow::insert($chunk);
        }

        $statuses = array_count_values(array_column($lines, 'status'));
        $import->update([
            'processed_rows' => $statuses[SparepartInjectionRow::PROCESSED] ?? 0,
            'usage_added' => $usageAdded,
            'skipped_rows' => $statuses[SparepartInjectionRow::SKIPPED] ?? 0,
            'failed_rows' => $statuses[SparepartInjectionRow::FAILED] ?? 0,
        ]);

        return $import;
    }

    /**
     * Schedules visible to the importing user, keyed by normalized order
     * number. A KOORDINATOR can only inject into their own area; an order of
     * another area is reported as "not found" so nothing leaks.
     *
     * @return Collection<string, Collection<int, PMSchedule>>
     */
    private function loadSchedules(User $user, array $orders): Collection
    {
        if ($orders === []) {
            return collect();
        }

        $schedules = collect();

        foreach (array_chunk($orders, 500) as $chunk) {
            $query = PMSchedule::query()->whereIn('order_number', $chunk);
            AreaAuthorizationScope::apply($query, $user, 'area');
            $schedules = $schedules->concat($query->get(['id', 'order_number', 'area']));
        }

        return $schedules->groupBy(fn ($pm) => $this->orderKey($this->normalizeId((string) $pm->order_number)));
    }

    /** @return array<string, int> */
    private function mapHeader(array $header): array
    {
        $found = [];
        foreach ($header as $index => $cell) {
            $name = strtolower(trim(preg_replace('/\s+/', ' ', (string) $cell)));
            if (in_array($name, self::HEADERS, true) && ! isset($found[$name])) {
                $found[$name] = $index;
            }
        }

        $missing = array_diff(self::HEADERS, array_keys($found));
        if ($missing) {
            throw new InvalidArgumentException('Wrong headers. Required columns: Month, SAP Number, Qty, Order Number. Missing: '.implode(', ', $missing).'.');
        }

        return $found;
    }

    private function text(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_float($value) && floor($value) === $value && abs($value) < 1e15) {
            return (string) (int) $value;
        }

        return trim((string) $value);
    }

    /** Trim and collapse whitespace; whole-number floats lose the ".0". */
    private function normalizeId(string $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', $value));
    }

    private function orderKey(string $normalizedOrder): string
    {
        return mb_strtoupper($normalizedOrder);
    }

    /**
     * Month name only; the year is the upload year. Returns "Y-m" or null.
     * Numbers and dates are rejected rather than guessed.
     */
    private function parseMonth(string $raw): ?string
    {
        $key = mb_strtolower(trim($raw), 'UTF-8');
        $month = self::MONTHS[rtrim($key, '.')] ?? null;

        return $month ? sprintf('%d-%02d', now()->year, $month) : null;
    }

    private function parseQty(mixed $cell, string $raw): ?int
    {
        $value = is_int($cell) || is_float($cell) ? $cell : $raw;

        if (is_string($value) && preg_match('/^\d+(\.0+)?$/', $value)) {
            $value = (float) $value;
        }

        if (! is_int($value) && ! (is_float($value) && floor($value) === $value)) {
            return null;
        }

        $value = (int) $value;

        return $value >= 1 && $value <= 1000000 ? $value : null;
    }

    private function plural(int $n): string
    {
        return $n.' transaksi';
    }
}
