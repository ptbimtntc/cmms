<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\Machine;
use App\Models\PMSchedule;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class MachineReportController extends Controller
{
    private const AREAS = ['WWD', 'BUL'];

    /**
     * "Gearbox machine" has no dedicated column/flag anywhere in the schema
     * (Machine/MachineChecklist/MachineProblem all lack one) — this list is
     * reused from two independent, pre-existing, data-backed signals rather
     * than an invented string match:
     *   1. The machine_problems catalog only has Mainshaft/Innershaft
     *      categories for machine_type NDE (WWD) and BF/BFM (BUL) — SHX has
     *      neither category at all.
     *   2. OilAuditController::AUDIT_MACHINE_TYPES (an independent,
     *      business-rule-driven WWD gearbox/shaft-auditing scope) is
     *      exactly ['NDE', 'NDB'] — also excluding SHX.
     * Both agree: for WWD, NDE (+NDB, per the Oil Audit precedent, though no
     * real NDB machines exist yet) are gearbox machines; SHX is not.
     */
    private const GEARBOX_MACHINE_TYPES = ['NDE', 'NDB'];

    public function index(Request $request)
    {
        $user = $request->user();

        $area = $user->isAdmin() && in_array($request->input('area'), self::AREAS, true)
            ? $request->input('area')
            : null;
        $machineType = $request->input('machine_type') ?: null;
        $status = $request->input('status') ?: null;
        $groupId = $request->filled('group_id') ? (int) $request->input('group_id') : null;
        $search = trim((string) $request->input('search', ''));

        $query = $this->filteredQuery($user, $area, $machineType, $status, $groupId, $search);

        // --- Summary — same filtered scope as the table below it. ---
        $totalMachine = (clone $query)->count();
        $activeMachine = (clone $query)->where('machines.status', 'ACTIVE')->count();
        $inactiveMachine = (clone $query)->where('machines.status', '!=', 'ACTIVE')->count();

        // --- Gearbox Machines — WWD only. Hidden (not just zeroed) when
        // the effective visible area is BUL, per the WWD-only business
        // rule this metric represents. ---
        $resolvedArea = $this->resolvedArea($user, $area);
        $showGearboxMetric = $resolvedArea !== 'BUL';
        $gearboxCount = $showGearboxMetric
            ? (clone $query)->where('machines.area', 'WWD')->whereIn('machines.machine_type', self::GEARBOX_MACHINE_TYPES)->count()
            : 0;

        $summary = [
            'total_machine' => $totalMachine,
            'active_machine' => $activeMachine,
            'inactive_machine' => $inactiveMachine,
        ];

        // --- Machine table: Last PM / PM Count via an efficient grouped
        // subquery join (no N+1 per row). Next PM is then just arithmetic
        // in PHP against the already-loaded Last PM + the machine's own
        // pm_cycle_value/unit — identical formula to
        // MachineHistoryController::show(), not a new one. ---
        $machines = (clone $query)
            ->select([
                'machines.id',
                'machines.machine_number',
                'machines.machine_type',
                'machines.area',
                'machines.status',
                'machines.pm_cycle_value',
                'machines.pm_cycle_unit',
                'groups.name as group_name',
                'pm_stats.pm_count',
                'pm_stats.last_pm',
            ])
            ->orderBy('machines.machine_number')
            ->paginate(20)
            ->withQueryString();

        $machines->getCollection()->transform(function ($row) {
            $row->next_pm = $this->calculateNextPm($row->last_pm, $row->pm_cycle_value, $row->pm_cycle_unit);

            return $row;
        });

        // --- Filter dropdown options — scoped by role/area visibility
        // only, never by the other cross-filters, so narrowing one never
        // hides the choices available in another. ---
        $optionsScope = $this->applyScopeTo(Machine::query(), $user, $area);
        $machineTypes = (clone $optionsScope)->whereNotNull('machine_type')->distinct()->orderBy('machine_type')->pluck('machine_type');
        $statuses = (clone $optionsScope)->whereNotNull('status')->distinct()->orderBy('status')->pluck('status');
        $groupIds = (clone $optionsScope)->whereNotNull('group_id')->distinct()->pluck('group_id');
        $groups = Group::whereIn('id', $groupIds)->orderBy('name')->get(['id', 'name']);

        return view('reports.machine.index', [
            'summary' => $summary,
            'showGearboxMetric' => $showGearboxMetric,
            'gearboxCount' => $gearboxCount,
            'machines' => $machines,
            'machineTypes' => $machineTypes,
            'statuses' => $statuses,
            'groups' => $groups,
            'areas' => self::AREAS,
            'isAdmin' => $user->isAdmin(),
            'selectedArea' => $area,
            'selectedMachineType' => $machineType,
            'selectedStatus' => $status,
            'selectedGroupId' => $groupId,
            'search' => $search,
        ]);
    }

    private function calculateNextPm(?string $lastPm, ?int $cycleValue, ?string $cycleUnit): ?Carbon
    {
        if (! $lastPm || ! $cycleValue || ! $cycleUnit) {
            return null;
        }

        $nextPm = Carbon::parse($lastPm);

        match (strtolower($cycleUnit)) {
            'day' => $nextPm->addDays($cycleValue),
            'week' => $nextPm->addWeeks($cycleValue),
            'month' => $nextPm->addMonths($cycleValue),
            default => null,
        };

        return $nextPm;
    }

    private function applyFilters(
        Builder $query,
        ?string $machineType,
        ?string $status,
        ?int $groupId,
        string $search
    ): Builder {
        if ($machineType) {
            $query->where('machines.machine_type', $machineType);
        }

        if ($status) {
            $query->where('machines.status', $status);
        }

        if ($groupId) {
            $query->where('machines.group_id', $groupId);
        }

        if ($search !== '') {
            $query->where(function (Builder $q) use ($search) {
                $q->where('machines.machine_number', 'like', "%{$search}%")
                    ->orWhere('machines.machine_type', 'like', "%{$search}%");
            });
        }

        return $query;
    }

    private function filteredQuery(
        User $user,
        ?string $area,
        ?string $machineType,
        ?string $status,
        ?int $groupId,
        string $search
    ): Builder {
        $query = Machine::query()
            ->leftJoin('groups', 'groups.id', '=', 'machines.group_id')
            ->leftJoinSub(
                PMSchedule::query()->selectRaw('machine_number, COUNT(*) as pm_count, MAX(actual_date) as last_pm')->groupBy('machine_number'),
                'pm_stats',
                'pm_stats.machine_number',
                '=',
                'machines.machine_number'
            );

        $this->applyScopeTo($query, $user, $area);

        return $this->applyFilters($query, $machineType, $status, $groupId, $search);
    }

    /**
     * Machine has no PIC-ownership concept of its own (unlike PMSchedule/
     * Greasing) — so unlike PMReportController/GreasingReportController,
     * BOTH Koordinator and PIC roles are scoped purely by area here,
     * matching DashboardController::userAreaMatches()'s treatment of
     * WWD-tied vs BUL-tied roles for area-based visibility.
     */
    private function applyScopeTo(Builder $query, User $user, ?string $area): Builder
    {
        switch ($user->role) {
            case User::ROLE_KOORDINATOR_WWD:
            case User::ROLE_PIC_WWD:
                $query->where('machines.area', 'WWD');
                break;
            case User::ROLE_KOORDINATOR_BUL:
            case User::ROLE_PIC_BUL:
                $query->where('machines.area', 'BUL');
                break;
            default:
                if ($area) {
                    $query->where('machines.area', $area);
                }
                break;
        }

        return $query;
    }

    /**
     * The area a non-admin role is fixed to, or the admin's active $area
     * selection (null = ALL). Used only to decide whether the WWD-only
     * Gearbox metric should be shown at all.
     */
    private function resolvedArea(User $user, ?string $area): ?string
    {
        return match ($user->role) {
            User::ROLE_KOORDINATOR_WWD, User::ROLE_PIC_WWD => 'WWD',
            User::ROLE_KOORDINATOR_BUL, User::ROLE_PIC_BUL => 'BUL',
            default => $area,
        };
    }
}
