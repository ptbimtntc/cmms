<?php

namespace App\Services;

use App\Models\MandatorySparepart;
use App\Models\PMSchedule;
use App\Models\PMSparepart;
use App\Models\Sparepart;
use App\Models\User;
use App\Support\AreaAuthorizationScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Sparepart Planning — the two independent calculations behind the
 * "Estimasi Kebutuhan" tab. They are never summed into one number:
 *
 *  - Plan   = count of not-yet-finished PM schedules planned (plan_date) in
 *             the period × mandatory qty per machine type. Configuration.
 *  - Actual = qty stored in pm_spareparts for FINISHED PMs whose completion
 *             date falls in the period. Real transactions; the mandatory
 *             configuration is never consulted.
 */
class SparepartPlanningService
{
    /**
     * Statuses that mean "work still has to be done". An explicit whitelist
     * (rather than "anything not done") so a future status is never counted
     * as demand by accident. MISSED is an overdue PM that was never
     * started/completed, so it still needs its parts when it is performed.
     */
    public const PLANNED_STATUSES = ['OPEN', 'IN_PROGRESS', 'MISSED'];

    /**
     * Completion date of a finished PM: the last work session's date
     * (multi-day PMs), falling back to pm_schedules.actual_date for legacy
     * rows that have no work sessions.
     */
    public const COMPLETION_DATE_SQL = '(COALESCE((SELECT MAX(ws.actual_date) FROM pm_work_sessions ws WHERE ws.pm_schedule_id = pm_schedules.id), pm_schedules.actual_date))';

    /**
     * @return array{plan: Collection, actual: Collection, recap: Collection}
     */
    public function calculate(User $user, int $year, ?int $month, ?string $machineType, ?string $area): array
    {
        [$from, $to] = $this->period($year, $month);

        $plan = $this->plan($user, $from, $to, $machineType, $area);
        $actual = $this->actual($user, $from, $to, $machineType, $area);

        return [
            'plan' => $plan,
            'actual' => $actual,
            'recap' => $this->recap($plan, $actual),
        ];
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    public function period(int $year, ?int $month): array
    {
        $from = Carbon::create($year, $month ?: 1, 1)->startOfDay();
        $to = $month ? $from->copy()->endOfMonth() : $from->copy()->endOfYear();

        return [$from->startOfDay(), $to->startOfDay()];
    }

    /**
     * Rows keyed by sparepart id: material_number, description, unit,
     * total, pm_count and a per-machine-type breakdown.
     */
    public function plan(User $user, Carbon $from, Carbon $to, ?string $machineType, ?string $area): Collection
    {
        $query = PMSchedule::query()
            ->whereIn('status', self::PLANNED_STATUSES)
            ->whereBetween('plan_date', [$from->toDateString(), $to->toDateString()]);

        $this->scopeSchedules($query, $user, $machineType, $area);

        $pmCounts = $query
            ->selectRaw('machine_type, COUNT(*) as pm_count')
            ->groupBy('machine_type')
            ->pluck('pm_count', 'machine_type');

        if ($pmCounts->isEmpty()) {
            return collect();
        }

        $rows = collect();

        MandatorySparepart::query()
            ->with('sparepart')
            ->whereIn('machine_type', $pmCounts->keys())
            ->get()
            ->each(function (MandatorySparepart $config) use ($pmCounts, $rows) {
                $pmCount = (int) $pmCounts[$config->machine_type];
                $total = $pmCount * $config->qty;

                $row = $rows->get($config->sparepart_id) ?? $this->emptyRow($config->sparepart);
                $row['total'] += $total;
                $row['pm_count'] += $pmCount;
                $row['breakdown'][] = [
                    'machine_type' => $config->machine_type,
                    'pm_count' => $pmCount,
                    'qty_per_pm' => $config->qty,
                    'total' => $total,
                ];

                $rows->put($config->sparepart_id, $row);
            });

        return $this->sorted($rows);
    }

    public function actual(User $user, Carbon $from, Carbon $to, ?string $machineType, ?string $area): Collection
    {
        $query = PMSparepart::query()
            ->join('pm_schedules', 'pm_schedules.id', '=', 'pm_spareparts.pm_schedule_id')
            ->join('spareparts', 'spareparts.id', '=', 'pm_spareparts.sparepart_id')
            ->whereIn('pm_schedules.status', PMSchedule::DONE_STATUSES)
            ->whereRaw(self::COMPLETION_DATE_SQL.' BETWEEN ? AND ?', [$from->toDateString(), $to->toDateString()]);

        $this->scopeSchedules($query, $user, $machineType, $area, 'pm_schedules.');

        // One pass over pm_spareparts only (work sessions are reached via a
        // scalar subquery, never joined), so a multi-day PM cannot multiply
        // its quantity.
        $usages = $query
            ->selectRaw('spareparts.id as sparepart_id, spareparts.material_number, spareparts.description, spareparts.unit, pm_schedules.machine_type as machine_type')
            ->selectRaw('SUM(pm_spareparts.qty) as total_qty, COUNT(DISTINCT pm_schedules.id) as pm_count')
            ->groupBy('spareparts.id', 'spareparts.material_number', 'spareparts.description', 'spareparts.unit', 'pm_schedules.machine_type')
            ->get();

        $rows = collect();

        foreach ($usages as $usage) {
            $row = $rows->get($usage->sparepart_id) ?? [
                'sparepart_id' => (int) $usage->sparepart_id,
                'material_number' => $usage->material_number,
                'description' => $usage->description,
                'unit' => $usage->unit,
                'total' => 0,
                'pm_count' => 0,
                'breakdown' => [],
            ];

            $row['total'] += (int) $usage->total_qty;
            // A PM belongs to exactly one machine type, so summing the
            // per-type distinct PM counts never double counts a PM.
            $row['pm_count'] += (int) $usage->pm_count;
            $row['breakdown'][] = [
                'machine_type' => $usage->machine_type,
                'pm_count' => (int) $usage->pm_count,
                'total' => (int) $usage->total_qty,
            ];

            $rows->put($usage->sparepart_id, $row);
        }

        return $this->sorted($rows);
    }

    /**
     * Side-by-side per sparepart. Difference = plan − actual (positive means
     * actual usage is below the planned need).
     */
    public function recap(Collection $plan, Collection $actual): Collection
    {
        return $plan->keys()->merge($actual->keys())->unique()
            ->map(function ($id) use ($plan, $actual) {
                $source = $plan->get($id) ?? $actual->get($id);
                $planned = (int) ($plan->get($id)['total'] ?? 0);
                $used = (int) ($actual->get($id)['total'] ?? 0);

                return [
                    'sparepart_id' => $id,
                    'material_number' => $source['material_number'],
                    'description' => $source['description'],
                    'unit' => $source['unit'],
                    'plan' => $planned,
                    'actual' => $used,
                    'difference' => $planned - $used,
                ];
            })
            ->sortBy('material_number', SORT_NATURAL)
            ->values();
    }

    /**
     * Number of distinct machines with a not-yet-finished PM schedule
     * (same PLANNED_STATUSES as the plan calculation) planned in the period.
     * A machine with several schedules in the period counts once.
     */
    public function machineCount(User $user, Carbon $from, Carbon $to, string $machineType, ?string $area): int
    {
        $query = PMSchedule::query()
            ->whereIn('status', self::PLANNED_STATUSES)
            ->whereBetween('plan_date', [$from->toDateString(), $to->toDateString()]);

        $this->scopeSchedules($query, $user, $machineType, $area);

        return (int) $query->distinct()->count('machine_id');
    }

    /**
     * Sparepart Order Estimation. Stateless: everything the user edited
     * arrives in $state and is re-applied, so nothing is stored.
     *
     * $state keys: machines_final (raw), qty[id] (order qty shown), orig[id]
     * (the calculated qty that was shown when the user last submitted — a qty
     * differing from it is a manual correction and is preserved), manual[id]
     * (extra spareparts, qty each), reset ('qty' drops qty corrections).
     *
     * @return array{machines_system: int, machines_final: int, rows: Collection, errors: list<string>}
     */
    public function estimateOrder(User $user, int $year, ?int $month, string $machineType, ?string $area, array $state): array
    {
        [$from, $to] = $this->period($year, $month);
        $errors = [];

        $machinesSystem = $this->machineCount($user, $from, $to, $machineType, $area);
        $machinesFinal = $machinesSystem;
        $rawMachines = $state['machines_final'] ?? null;

        if ($rawMachines !== null && $rawMachines !== '') {
            if ($this->isQty($rawMachines)) {
                $machinesFinal = (int) $rawMachines;
            } else {
                $errors[] = 'Final Machine Count must be a whole number of 0 or more; the system value was used.';
            }
        }

        $reset = ($state['reset'] ?? null) === 'qty';
        $rows = collect();

        $configs = MandatorySparepart::with('sparepart')->where('machine_type', $machineType)->get();

        foreach ($configs as $config) {
            $id = $config->sparepart_id;
            $auto = $machinesFinal * $config->qty;
            $order = $auto;
            $overridden = false;
            $submitted = $state['qty'][$id] ?? null;

            if (! $reset && $submitted !== null && $submitted !== '') {
                if (! $this->isQty($submitted)) {
                    $errors[] = "Qty order {$config->sparepart->material_number} tidak valid (harus bilangan bulat 0 atau lebih); nilai hitungan dipakai.";
                } elseif ((int) $submitted !== (int) ($state['orig'][$id] ?? $auto)) {
                    $order = (int) $submitted;
                    $overridden = $order !== $auto;
                }
            }

            $rows->push([
                'source' => 'Mandatory',
                'sparepart_id' => $id,
                'material_number' => $config->sparepart->material_number,
                'description' => $config->sparepart->description,
                'unit' => $config->sparepart->unit,
                'per_machine' => $config->qty,
                'system_qty' => $machinesSystem * $config->qty,
                'auto_qty' => $auto,
                'order_qty' => $order,
                'overridden' => $overridden,
            ]);
        }

        $manual = collect($state['manual'] ?? [])->reject(fn ($q, $id) => $configs->contains('sparepart_id', (int) $id));
        $parts = Sparepart::whereIn('id', $manual->keys())->get()->keyBy('id');

        foreach ($manual as $id => $qty) {
            $part = $parts->get((int) $id);

            if (! $part) {
                continue;
            }

            if (! $this->isQty($qty)) {
                $errors[] = "Qty {$part->material_number} tidak valid (harus bilangan bulat 0 atau lebih); diset 0.";
                $qty = 0;
            }

            $rows->push([
                'source' => 'Manual',
                'sparepart_id' => $part->id,
                'material_number' => $part->material_number,
                'description' => $part->description,
                'unit' => $part->unit,
                'per_machine' => null,
                'system_qty' => null,
                'auto_qty' => null,
                'order_qty' => (int) $qty,
                'overridden' => false,
            ]);
        }

        return [
            'machines_system' => $machinesSystem,
            'machines_final' => $machinesFinal,
            'rows' => $rows->values(),
            'errors' => $errors,
        ];
    }

    /**
     * Actual usage history, shown only as a reference for picking extra
     * spareparts: the 12 months before the selected period, finished PMs only
     * (see actual()). Never merged into the order quantities.
     */
    public function usageReference(User $user, int $year, ?int $month, string $machineType, ?string $area): array
    {
        [$periodFrom] = $this->period($year, $month);
        $to = $periodFrom->copy()->subDay();
        $from = $periodFrom->copy()->subYear();

        return [
            'from' => $from,
            'to' => $to,
            'rows' => $this->actual($user, $from, $to, $machineType, $area),
        ];
    }

    private function isQty(mixed $value): bool
    {
        return is_int($value) || (is_string($value) && ctype_digit($value) && strlen($value) <= 9);
    }

    private function scopeSchedules(Builder $query, User $user, ?string $machineType, ?string $area, string $prefix = ''): void
    {
        AreaAuthorizationScope::apply($query, $user, $prefix.'area', $prefix.'pic', $area);

        if ($machineType) {
            $query->where($prefix.'machine_type', $machineType);
        }
    }

    private function emptyRow($sparepart): array
    {
        return [
            'sparepart_id' => $sparepart->id,
            'material_number' => $sparepart->material_number,
            'description' => $sparepart->description,
            'unit' => $sparepart->unit,
            'total' => 0,
            'pm_count' => 0,
            'breakdown' => [],
        ];
    }

    private function sorted(Collection $rows): Collection
    {
        return $rows->sortBy('material_number', SORT_NATURAL)->values();
    }
}
