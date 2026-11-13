<?php

namespace App\Imports;

use App\Models\Area;
use App\Models\PredictiveFindingOption;
use App\Models\PredictiveProblemOption;
use App\Models\PredictiveWorkOrder;
use App\Models\User;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Loads the Problem > Finding master from a file. Columns: Category, Area,
 * Problem, Finding (Finding may be empty to create just the Problem). Rows are
 * independent and nothing existing is changed or reactivated.
 *
 * Success = something new was created; Skipped = it all existed already;
 * Failed  = invalid category/area/length, or an area the importer may not manage.
 */
class PredictiveOptionImport implements ToCollection, WithHeadingRow
{
    public const MAX_DETAIL_ROWS = 200;

    private const REQUIRED_HEADINGS = ['category', 'area', 'problem'];

    public int $success = 0;

    public int $failed = 0;

    public int $skipped = 0;

    /** @var list<array{row: int, label: string, result: string, reason: string}> */
    public array $details = [];

    public int $hiddenDetails = 0;

    public ?string $fatalError = null;

    /** @var array<string, PredictiveProblemOption> */
    private array $problems = [];

    /** @param  Collection<int, string>  $allowedAreas  Area names the importer may manage. */
    public function __construct(private readonly User $user, private readonly Collection $allowedAreas) {}

    public function collection(Collection $rows): void
    {
        $first = $rows->first();
        $missing = array_diff(self::REQUIRED_HEADINGS, $first ? array_keys($first->toArray()) : []);

        if ($missing !== []) {
            $this->fatalError = 'Required column not found: '.implode(', ', $missing).'. Use these headers: Category, Area, Problem, Finding.';

            return;
        }

        foreach ($rows as $index => $row) {
            $values = $row->toArray();

            if (collect($values)->every(fn ($v) => trim((string) $v) === '')) {
                continue;
            }

            $this->processRow($index + 2, $values);
        }
    }

    /** @param  array<string, mixed>  $values */
    private function processRow(int $line, array $values): void
    {
        $problemName = trim((string) ($values['problem'] ?? ''));
        $findingName = trim((string) ($values['finding'] ?? ''));
        $label = $findingName === '' ? $problemName : "{$problemName} > {$findingName}";

        $category = strtoupper((string) preg_replace('/[\s\-]+/', '_', trim((string) ($values['category'] ?? ''))));
        $category = $category === 'AIRLEAKED' ? PredictiveWorkOrder::CATEGORY_AIR_LEAKED : $category;

        if (! isset(PredictiveWorkOrder::CATEGORIES[$category])) {
            $this->record($line, $label, 'failed', 'Category must be VIBRATION or AIR_LEAKED');

            return;
        }

        $areaInput = trim((string) ($values['area'] ?? ''));
        $area = Area::matching($areaInput);
        if ($area === null) {
            $this->record($line, $label, 'failed', "Area {$areaInput} tidak ditemukan atau tidak aktif");

            return;
        }

        if (! $this->allowedAreas->contains($area)) {
            $this->record($line, $label, 'failed', "Area {$area} di luar area Anda");

            return;
        }

        if ($problemName === '' || mb_strlen($problemName) > 255 || mb_strlen($findingName) > 255) {
            $this->record($line, $label, 'failed', 'Problem is required; Problem and Finding are at most 255 characters');

            return;
        }

        $created = false;

        $problem = $this->problem($category, $area, $problemName);
        if ($problem === null) {
            $problem = PredictiveProblemOption::create(['category' => $category, 'area' => $area, 'name' => $problemName]);
            $this->problems["{$category}|{$area}|".PredictiveProblemOption::normalize($problemName)] = $problem;
            $created = true;
        }

        if ($findingName !== '') {
            $exists = $problem->findings()->pluck('name')
                ->contains(fn ($n) => PredictiveProblemOption::normalize($n) === PredictiveProblemOption::normalize($findingName));

            if (! $exists) {
                PredictiveFindingOption::create(['problem_option_id' => $problem->id, 'name' => $findingName]);
                $created = true;
            }
        }

        if ($created) {
            $this->success++;
        } else {
            $this->record($line, $label, 'skipped', 'Already in the master; left unchanged');
        }
    }

    private function problem(string $category, string $area, string $name): ?PredictiveProblemOption
    {
        $key = "{$category}|{$area}|".PredictiveProblemOption::normalize($name);

        return $this->problems[$key] ??= PredictiveProblemOption::query()->forScope($category, $area)->get()
            ->first(fn ($p) => PredictiveProblemOption::normalize($p->name) === PredictiveProblemOption::normalize($name));
    }

    private function record(int $line, string $label, string $result, string $reason): void
    {
        $result === 'failed' ? $this->failed++ : $this->skipped++;

        if (count($this->details) >= self::MAX_DETAIL_ROWS) {
            $this->hiddenDetails++;

            return;
        }

        $this->details[] = ['row' => $line, 'label' => $label, 'result' => $result, 'reason' => $reason];
    }
}
