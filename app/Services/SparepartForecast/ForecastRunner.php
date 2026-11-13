<?php

namespace App\Services\SparepartForecast;

use App\Models\AiForecastEvaluation;
use App\Models\AiForecastMachine;
use App\Models\AiForecastRecommendation;
use App\Models\AiForecastResult;
use App\Models\AiForecastRun;
use App\Models\Machine;
use App\Models\PMSchedule;
use App\Models\PMSparepart;
use App\Models\User;
use App\Services\SparepartPlanningService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Loads READ-ONLY history (pm_spareparts via pm_schedules via machines), runs
 * ForecastEngine and persists everything into the ai_forecast_* tables.
 *
 * Source rules:
 *  - Only pm_spareparts is a usage source. mandatory_spareparts is never read.
 *  - Rows injected from the warehouse (source = WAREHOUSE) are not used, and
 *    a PM that contains any is excluded as a whole (mixed provenance), so
 *    injection never leaks into the history or the PM denominator.
 *  - Only finished PMs (PMSchedule::DONE_STATUSES) are history; their date is
 *    the completion date used by the existing Sparepart Planning.
 */
class ForecastRunner
{
    public function __construct(private ForecastEngine $engine = new ForecastEngine()) {}

    /** @return array{0: Carbon, 1: Carbon} */
    public static function period(string $type, \DateTimeInterface $anchor): array
    {
        $anchor = Carbon::instance($anchor);

        return $type === 'WEEK'
            ? [$anchor->copy()->startOfWeek()->startOfDay(), $anchor->copy()->endOfWeek()->startOfDay()]
            : [$anchor->copy()->startOfMonth()->startOfDay(), $anchor->copy()->endOfMonth()->startOfDay()];
    }

    public function run(User $user, string $periodType, \DateTimeInterface $anchor, ?string $area = null, ?string $machineType = null): AiForecastRun
    {
        [$from, $to] = self::period($periodType, $anchor);

        $run = AiForecastRun::create([
            'user_id' => $user->id,
            'status' => AiForecastRun::RUNNING,
            'algorithm_version' => ForecastEngine::VERSION,
            'period_type' => $periodType,
            'period_start' => $from,
            'period_end' => $to,
            'scope_area' => $area,
            'scope_machine_type' => $machineType,
            'parameters' => ForecastEngine::parameters(),
            'started_at' => now(),
        ]);

        try {
            DB::transaction(fn () => $this->execute($run, $from, $to, $area, $machineType));
        } catch (Throwable $e) {
            $run->update(['status' => AiForecastRun::FAILED, 'error' => mb_substr($e->getMessage(), 0, 1000), 'finished_at' => now()]);
        }

        return $run->fresh();
    }

    private function execute(AiForecastRun $run, \DateTimeInterface $from, \DateTimeInterface $to, ?string $area, ?string $machineType): void
    {
        $machines = Machine::query()
            ->where('status', 'ACTIVE')
            ->when($area, fn ($q) => $q->where('area', $area))
            ->when($machineType, fn ($q) => $q->where('machine_type', $machineType))
            ->get(['id', 'machine_number', 'machine_type', 'area'])
            ->keyBy('id');

        $load = $this->loadHistory($machines->keys()->all());
        $upcoming = $this->upcomingCounts($machines->keys()->all(), $from, $to);

        $quality = [
            'machines_in_scope' => $machines->count(),
            'machines_without_history' => 0,
            'machines_insufficient' => 0,
            'machines_sufficient' => 0,
            'finished_pm_total' => $load['finished_total'],
            'finished_pm_without_date' => $load['without_date'],
            'finished_pm_excluded_warehouse' => $load['excluded_warehouse'],
            'finished_pm_used' => 0,
            'finished_pm_without_usage' => 0,
            'usage_rows' => $load['usage_rows'],
            'distinct_spareparts' => count($load['parts']),
            'machines_without_upcoming_pm' => 0,
        ];

        $partMeta = $load['parts'];
        $resultRows = [];
        $now = now();
        $aggregate = [];

        foreach ($machines as $id => $machine) {
            $history = $load['history'][$id] ?? [];
            $upc = $upcoming[$id] ?? 0;
            $forecast = $this->engine->forecastMachine($history, $upc);
            $excluded = $load['excluded_by_machine'][$id] ?? 0;

            $quality['finished_pm_used'] += count($history);
            $quality['finished_pm_without_usage'] += count($history) - $forecast['pm_with_usage'];

            if ($forecast['status'] === 'SUFFICIENT') {
                $quality['machines_sufficient']++;
                if ($upc === 0) {
                    $quality['machines_without_upcoming_pm']++;
                }
            } else {
                $quality['machines_insufficient']++;
                if ($history === []) {
                    $quality['machines_without_history']++;
                }
            }

            AiForecastMachine::create([
                'run_id' => $run->id,
                'machine_id' => $id,
                'machine_number' => $machine->machine_number,
                'machine_type' => $machine->machine_type,
                'area' => $machine->area,
                'status' => $forecast['status'],
                'finished_pm_count' => $forecast['pm_count'],
                'pm_with_usage_count' => $forecast['pm_with_usage'],
                'excluded_pm_count' => $excluded,
                'upcoming_pm_count' => $upc,
                'first_pm_date' => $history[0]['date'] ?? null,
                'last_pm_date' => $history ? end($history)['date'] : null,
                'confidence' => $forecast['confidence'],
                'note' => $forecast['note'] ?? ($upc === 0 ? 'Tidak ada PM terjadwal pada periode ini; kebutuhan 0.' : null),
            ]);

            foreach ($forecast['items'] as $partId => $item) {
                $meta = $partMeta[$partId];
                $resultRows[] = [
                    'run_id' => $run->id,
                    'machine_id' => $id,
                    'machine_number' => $machine->machine_number,
                    'machine_type' => $machine->machine_type,
                    'area' => $machine->area,
                    'sparepart_id' => $partId,
                    'material_number' => $meta['material_number'],
                    'description' => $meta['description'],
                    'unit' => $meta['unit'],
                    'pm_considered' => $item['pm_considered'],
                    'usage_pm_count' => $item['usage_pm_count'],
                    'total_qty' => $item['total_qty'],
                    'avg_qty' => $item['avg_qty'],
                    'mean_interval_days' => $item['mean_interval_days'],
                    'last_used_date' => $item['last_used_date'],
                    'trend' => $item['trend'],
                    'probability' => $item['probability'],
                    'upcoming_pm_count' => $item['upcoming_pm_count'],
                    'expected_qty' => $item['expected_qty'],
                    'recommended_qty' => $item['recommended_qty'],
                    'level' => $item['level'],
                    'confidence' => $item['confidence'],
                    'basis' => json_encode($item['basis']),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                $agg = &$aggregate[$partId];
                $agg['recommended'] = ($agg['recommended'] ?? 0) + $item['recommended_qty'];
                $agg['machines'] = ($agg['machines'] ?? 0) + ($item['recommended_qty'] > 0 ? 1 : 0);
                $agg['increasing'] = ($agg['increasing'] ?? 0) + ($item['trend'] === 'INCREASING' ? 1 : 0);
                unset($agg);
            }
        }

        foreach (array_chunk($resultRows, 500) as $chunk) {
            AiForecastResult::insert($chunk);
        }

        $evaluation = $this->engine->evaluate($load['history']);
        AiForecastEvaluation::create(['run_id' => $run->id] + $evaluation);

        $this->recommendations($run, $quality, $aggregate, $partMeta, $evaluation);

        $dates = collect($load['history'])->flatten(1)->pluck('date');
        $run->update([
            'status' => AiForecastRun::COMPLETED,
            'history_from' => $dates->min(),
            'history_to' => $dates->max(),
            'data_fingerprint' => $load['fingerprint'],
            'summary' => ['quality' => $quality, 'result_rows' => count($resultRows)],
            'finished_at' => now(),
        ]);
    }

    /**
     * @param  list<int>  $machineIds
     * @return array{history: array<int, list<array>>, parts: array<int, array>, finished_total: int, without_date: int, excluded_warehouse: int, excluded_by_machine: array<int,int>, usage_rows: int, fingerprint: string}
     */
    private function loadHistory(array $machineIds): array
    {
        $history = [];
        $parts = [];
        $excludedByMachine = [];
        $finishedTotal = $withoutDate = $excluded = $usageRows = 0;
        $hash = hash_init('sha256');

        foreach (array_chunk($machineIds, 500) as $chunk) {
            $pms = PMSchedule::query()
                ->whereIn('machine_id', $chunk)
                ->whereIn('status', PMSchedule::DONE_STATUSES)
                ->selectRaw('pm_schedules.id, pm_schedules.machine_id, '.SparepartPlanningService::COMPLETION_DATE_SQL.' as pm_date')
                ->get();

            $finishedTotal += $pms->count();
            $pmIds = $pms->pluck('id')->all();

            $warehousePms = PMSparepart::whereIn('pm_schedule_id', $pmIds)->where('source', PMSparepart::SOURCE_WAREHOUSE)
                ->distinct()->pluck('pm_schedule_id')->flip();

            $usage = PMSparepart::query()
                ->join('spareparts', 'spareparts.id', '=', 'pm_spareparts.sparepart_id')
                ->whereIn('pm_spareparts.pm_schedule_id', $pmIds)
                ->where(fn ($q) => $q->whereNull('pm_spareparts.source')->orWhere('pm_spareparts.source', '!=', PMSparepart::SOURCE_WAREHOUSE))
                ->orderBy('pm_spareparts.id')
                ->get(['pm_spareparts.id', 'pm_spareparts.pm_schedule_id', 'pm_spareparts.sparepart_id', 'pm_spareparts.qty',
                    'spareparts.material_number', 'spareparts.description', 'spareparts.unit'])
                ->groupBy('pm_schedule_id');

            foreach ($pms->sortBy([['pm_date', 'asc'], ['id', 'asc']]) as $pm) {
                if ($pm->pm_date === null) {
                    $withoutDate++;

                    continue;
                }
                if ($warehousePms->has($pm->id)) {
                    $excluded++;
                    $excludedByMachine[$pm->machine_id] = ($excludedByMachine[$pm->machine_id] ?? 0) + 1;

                    continue;
                }

                $lines = [];
                foreach ($usage->get($pm->id, collect()) as $u) {
                    // Repeated rows for one part on one PM add up.
                    $lines[$u->sparepart_id] = ($lines[$u->sparepart_id] ?? 0) + (int) $u->qty;
                    $parts[$u->sparepart_id] = [
                        'material_number' => $u->material_number,
                        'description' => $u->description,
                        'unit' => $u->unit,
                    ];
                    $usageRows++;
                    hash_update($hash, "{$u->id}:{$pm->id}:{$u->sparepart_id}:{$u->qty};");
                }
                hash_update($hash, "pm{$pm->id}@{$pm->pm_date};");

                $history[$pm->machine_id][] = ['pm_id' => $pm->id, 'date' => (string) $pm->pm_date, 'usage' => $lines];
            }
        }

        return [
            'history' => $history,
            'parts' => $parts,
            'finished_total' => $finishedTotal,
            'without_date' => $withoutDate,
            'excluded_warehouse' => $excluded,
            'excluded_by_machine' => $excludedByMachine,
            'usage_rows' => $usageRows,
            'fingerprint' => hash_final($hash),
        ];
    }

    /** @return array<int,int> machine_id => scheduled, not-yet-finished PMs planned in the period */
    private function upcomingCounts(array $machineIds, \DateTimeInterface $from, \DateTimeInterface $to): array
    {
        $counts = [];
        foreach (array_chunk($machineIds, 500) as $chunk) {
            $rows = PMSchedule::query()
                ->whereIn('machine_id', $chunk)
                ->whereIn('status', SparepartPlanningService::PLANNED_STATUSES)
                ->whereBetween('plan_date', [$from->format('Y-m-d'), $to->format('Y-m-d')])
                ->selectRaw('machine_id, COUNT(*) as c')->groupBy('machine_id')->pluck('c', 'machine_id');
            foreach ($rows as $id => $c) {
                $counts[$id] = (int) $c;
            }
        }

        return $counts;
    }

    private function recommendations(AiForecastRun $run, array $q, array $aggregate, array $partMeta, array $evaluation): void
    {
        $rows = [];
        $add = fn (string $type, string $severity, string $message, ?int $partId = null) => $rows[] = [
            'run_id' => $run->id, 'source' => 'ENGINE', 'type' => $type, 'severity' => $severity,
            'sparepart_id' => $partId, 'machine_id' => null, 'message' => $message,
            'created_at' => now(), 'updated_at' => now(),
        ];

        uasort($aggregate, fn ($a, $b) => $b['recommended'] <=> $a['recommended']);
        foreach ($aggregate as $partId => $agg) {
            if ($agg['recommended'] > 0) {
                $m = $partMeta[$partId];
                $add('PREPARE', 'INFO', "Siapkan {$agg['recommended']} {$m['unit']} {$m['material_number']} ({$m['description']}) untuk {$agg['machines']} mesin pada periode ini.", $partId);
            }
            if ($agg['increasing'] > 0) {
                $m = $partMeta[$partId];
                $add('TREND', 'INFO', "Penggunaan {$m['material_number']} cenderung meningkat pada {$agg['increasing']} mesin.", $partId);
            }
        }

        if ($q['machines_insufficient'] > 0) {
            $add('DATA_QUALITY', 'WARNING', "{$q['machines_insufficient']} dari {$q['machines_in_scope']} mesin berstatus Insufficient Historical Data dan tidak diberi prediksi.");
        }
        if ($q['machines_without_upcoming_pm'] > 0) {
            $add('DATA_QUALITY', 'INFO', "{$q['machines_without_upcoming_pm']} mesin yang histori-nya cukup tidak punya PM terjadwal pada periode ini, sehingga kebutuhannya 0.");
        }
        if ($q['finished_pm_excluded_warehouse'] > 0) {
            $add('DATA_QUALITY', 'INFO', "{$q['finished_pm_excluded_warehouse']} PM selesai tidak dipakai karena memuat data Sparepart Injection (tidak dicampur dengan histori PIC).");
        }
        if ($evaluation['status'] !== AiForecastEvaluation::AVAILABLE) {
            $add('EVALUATION', 'WARNING', 'Accuracy Not Available: Insufficient Testing Data. The forecast accuracy cannot be measured yet.');
        }

        AiForecastRecommendation::insert($rows);
    }
}
