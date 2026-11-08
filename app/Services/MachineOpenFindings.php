<?php

namespace App\Services;

use App\Http\Controllers\SmallPmController;
use App\Models\GreasingFinding;
use App\Models\Machine;
use App\Models\OilAudit;
use App\Models\PMSchedule;
use App\Models\PredictiveWorkOrder;
use App\Models\SmallPmFinding;
use App\Models\SmallPmRow;
use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

/**
 * Findings still open on the machine of a PM, gathered from the modules
 * that record them (Oil Audit, Predictive, Greasing, Small PM blower
 * filter), so the PIC can handle them during the PM. Read-only.
 *
 * Each source is queried on its own: if one fails it is reported and
 * flagged, and Fill PM still opens.
 *
 * @phpstan-type Item array{title: string, detail: ?string, date: ?string, url: ?string}
 * @phpstan-type Rows array{items: list<Item>, total: int, more_url: ?string}
 * @phpstan-type Source array{label: string, error: bool, items: list<Item>, total: int, more_url: ?string}
 */
class MachineOpenFindings
{
    /** Rows shown per source; the rest are reached through the source's own page. */
    public const LIMIT = 10;

    private const MODULE_ROLES = [User::ROLE_ADMIN, User::ROLE_KOORDINATOR, User::ROLE_PIC, User::ROLE_SUPERVISOR];

    /**
     * @return array<string, Source>
     */
    public function forSchedule(PMSchedule $pm, User $user): array
    {
        // Older PM rows may only carry machine_number.
        $machineId = $pm->machine_id ?? Machine::where('machine_number', $pm->machine_number)->value('id');

        $sources = [
            'oil' => ['Oil Audit', fn () => $this->oil($pm, $machineId, $user)],
            'predictive' => ['Predictive', fn () => $this->predictive($pm, $machineId, $user)],
            'greasing' => ['Greasing', fn () => $this->greasing($machineId, $user)],
            'blower' => ['Blower Panel', fn () => $this->blower($pm, $machineId, $user)],
        ];

        $result = [];

        foreach ($sources as $key => [$label, $source]) {
            $result[$key] = $this->collect($label, $machineId === null ? fn () => $this->none() : $source);
        }

        return $result;
    }

    /**
     * @param  Closure(): Rows  $source
     * @return Source
     */
    private function collect(string $label, Closure $source): array
    {
        try {
            return ['label' => $label, 'error' => false] + $source();
        } catch (Throwable $e) {
            report($e);

            return ['label' => $label, 'error' => true] + $this->none();
        }
    }

    /** @return Rows */
    private function none(): array
    {
        return ['items' => [], 'total' => 0, 'more_url' => null];
    }

    /** @return Rows */
    private function oil(PMSchedule $pm, int $machineId, User $user): array
    {
        $query = OilAudit::query()
            ->where(fn (Builder $q) => $this->forMachine($q, $machineId, $pm->machine_number))
            ->requiringFollowUp()
            ->whereDoesntHave('followUp');

        $url = $user->hasRole(self::MODULE_ROLES) && $user->hasArea(OilAudit::AREA)
            ? route('oil-audits.history', $pm->machine_number)
            : null;

        return $this->result($query, 'audited_at', fn (OilAudit $a) => [
            'title' => $a->conditionLabel(),
            'detail' => null,
            'date' => $a->audited_at?->format('d-m-Y'),
            'url' => $url,
        ], $url);
    }

    /** @return Rows */
    private function predictive(PMSchedule $pm, int $machineId, User $user): array
    {
        $query = PredictiveWorkOrder::query()
            ->where(fn (Builder $q) => $this->forMachine($q, $machineId, $pm->machine_number))
            ->where('status', PredictiveWorkOrder::STATUS_OPEN);

        return $this->result($query, 'wo_date', fn (PredictiveWorkOrder $wo) => [
            'title' => $wo->categoryLabel().' · '.$wo->order_number,
            'detail' => $wo->problem_description,
            'date' => $wo->due_date ? 'Due '.$wo->due_date->format('d-m-Y') : null,
            'url' => $user->hasRole(self::MODULE_ROLES) && $wo->isVisibleTo($user) ? route('predictive.show', $wo) : null,
        ]);
    }

    /** @return Rows */
    private function greasing(int $machineId, User $user): array
    {
        $query = GreasingFinding::query()
            ->with('greasing')
            ->where('machine_id', $machineId)
            ->where('status', 'OPEN');

        return $this->result($query, 'created_at', fn (GreasingFinding $f) => [
            'title' => $f->finding ?: 'Greasing finding',
            'detail' => $f->finding_area ? 'Area: '.$f->finding_area : null,
            'date' => $f->created_at?->format('d-m-Y'),
            'url' => $f->greasing && $user->hasRole(self::MODULE_ROLES) && $f->greasing->isAccessibleBy($user)
                ? route('greasings.execute', $f->greasing)
                : null,
        ]);
    }

    /** @return Rows */
    private function blower(PMSchedule $pm, int $machineId, User $user): array
    {
        $query = SmallPmFinding::query()
            ->with('schedule.row:id,name')
            ->where('machine_id', $machineId)
            ->where('status', 'OPEN')
            ->whereHas('schedule', fn (Builder $q) => $q->where('task_type', SmallPmRow::TASK_FILTER_PANEL));

        $url = $user->hasRole(self::MODULE_ROLES)
            ? route('small-pm.index', ['tab' => SmallPmController::FINDINGS_TAB, 'search' => $pm->machine_number, 'status' => ['OPEN']])
            : null;

        return $this->result($query, 'created_at', fn (SmallPmFinding $f) => [
            'title' => 'Blower filter dead',
            'detail' => $f->schedule?->row?->name,
            'date' => $f->created_at?->format('d-m-Y'),
            'url' => $url,
        ], $url);
    }

    /**
     * Matches by machine_id, plus rows whose machine_id was never filled but
     * whose machine_number matches (Oil Audit and Predictive keep both).
     *
     * @param  Builder<OilAudit>|Builder<PredictiveWorkOrder>  $query
     */
    private function forMachine(Builder $query, int $machineId, string $machineNumber): void
    {
        $query->where('machine_id', $machineId)
            ->orWhere(fn (Builder $q) => $q->whereNull('machine_id')->where('machine_number', $machineNumber));
    }

    /**
     * Oldest first: what has been open longest comes first.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @param  Closure(TModel): Item  $map
     * @return Rows
     */
    private function result(Builder $query, string $dateColumn, Closure $map, ?string $moreUrl = null): array
    {
        $total = (clone $query)->count();

        $items = $query->orderBy($dateColumn)->orderBy('id')->limit(self::LIMIT)->get()->map($map)->values()->all();

        return ['items' => $items, 'total' => $total, 'more_url' => $total > self::LIMIT ? $moreUrl : null];
    }
}
