<?php

namespace App\Http\Controllers;

use App\Models\PMSchedule;
use App\Models\PMSparepart;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class CostReportController extends Controller
{
    private const AREAS = ['WWD', 'BUL'];

    public function index(Request $request)
    {
        $user = $request->user();

        $year = (int) $request->input('year', now()->year);
        $month = $request->filled('month') ? (int) $request->input('month') : null;
        $area = $user->isAdmin() && in_array($request->input('area'), self::AREAS, true)
            ? $request->input('area')
            : null;
        $machine = $request->input('machine') ?: null;
        $machineType = $request->input('machine_type') ?: null;

        $query = $this->filteredQuery($user, $area, $year, $month, $machine, $machineType);

        // --- Summary — same filtered scope (year/month/area/machine/type)
        // as the table below it. ---
        $totalCost = (float) ((clone $query)->selectRaw('COALESCE(SUM(pm_spareparts.qty * spareparts.price), 0) as total')->value('total') ?? 0);

        // Sparepart cost currently equals total maintenance cost: sparepart
        // usage (pm_spareparts) is the ONLY maintenance-cost-bearing data
        // source in this system today — Greasing and Oil Audit record no
        // cost/price anywhere. Both are shown so the report is honest about
        // this rather than silently only showing one number.
        $sparepartCost = $totalCost;

        $topMachineRow = (clone $query)
            ->select('pm_schedules.machine_number as label')
            ->selectRaw('SUM(pm_spareparts.qty * spareparts.price) as cost')
            ->groupBy('pm_schedules.machine_number')
            ->orderByDesc('cost')
            ->first();

        // Cost per Area — naturally reflects whatever area(s) remain after
        // the active Area filter (one row if Area=WWD/BUL is selected, both
        // rows if ALL), never a separate/independent query.
        $costByAreaRows = (clone $query)
            ->select('pm_schedules.area as area')
            ->selectRaw('SUM(pm_spareparts.qty * spareparts.price) as cost')
            ->groupBy('pm_schedules.area')
            ->get()
            ->keyBy('area');

        $summary = [
            'total_cost' => $totalCost,
            'sparepart_cost' => $sparepartCost,
            'top_machine' => $topMachineRow ? ['label' => $topMachineRow->label, 'cost' => (float) $topMachineRow->cost] : null,
            'cost_by_area' => collect(self::AREAS)->mapWithKeys(
                fn (string $a) => [$a => (float) ($costByAreaRows->get($a)->cost ?? 0)]
            ),
        ];

        // --- Monthly Cost Trend (Jan-Dec of the selected year) — 12 small
        // SQL aggregate queries (SUM only), never a bulk fetch-then-sum-in-
        // PHP over every transaction, regardless of how many rows exist. ---
        $chartScope = $this->filteredQuery($user, $area, $year, null, $machine, $machineType);
        $monthlyTrend = collect(range(1, 12))->map(function (int $m) use ($chartScope) {
            $cost = (float) ((clone $chartScope)
                ->whereMonth('pm_schedules.actual_date', $m)
                ->selectRaw('COALESCE(SUM(pm_spareparts.qty * spareparts.price), 0) as total')
                ->value('total') ?? 0);

            return [
                'month' => $m,
                'label' => Carbon::create(null, $m, 1)->format('M'),
                'cost' => $cost,
            ];
        });
        $maxMonthlyCost = (float) ($monthlyTrend->max('cost') ?: 0);

        // --- Detail table ---
        $usages = (clone $query)
            ->select([
                'pm_spareparts.id as id',
                'pm_schedules.actual_date as actual_date',
                'pm_schedules.area as area',
                'pm_schedules.machine_number as machine_number',
                'pm_schedules.machine_type as machine_type',
                'pm_schedules.order_number as order_number',
                'pm_spareparts.qty as qty',
                'spareparts.price as price',
            ])
            ->orderByDesc('pm_schedules.actual_date')
            ->paginate(20)
            ->withQueryString();

        // --- Year options ---
        $yearScope = $this->applyScopeTo(PMSchedule::query(), $user, $area);
        $minDate = (clone $yearScope)->min('actual_date');
        $maxDate = (clone $yearScope)->max('actual_date');
        $years = $minDate && $maxDate
            ? range((int) Carbon::parse($maxDate)->format('Y'), (int) Carbon::parse($minDate)->format('Y'))
            : [];
        if (! in_array($year, $years, true)) {
            $years[] = $year;
        }
        rsort($years);

        // --- Machine/Machine Type filter dropdown options — scoped by
        // role/area visibility and the active year/month, but never by the
        // other cross-filters. ---
        $optionsScope = $this->filteredQuery($user, $area, $year, $month, null, null);
        $machines = (clone $optionsScope)->select('pm_schedules.machine_number')->distinct()->orderBy('pm_schedules.machine_number')->pluck('machine_number');
        $machineTypes = (clone $optionsScope)->select('pm_schedules.machine_type')->distinct()->orderBy('pm_schedules.machine_type')->pluck('machine_type');

        return view('reports.cost.index', [
            'summary' => $summary,
            'monthlyTrend' => $monthlyTrend,
            'maxMonthlyCost' => $maxMonthlyCost,
            'usages' => $usages,
            'years' => $years,
            'machines' => $machines,
            'machineTypes' => $machineTypes,
            'areas' => self::AREAS,
            'isAdmin' => $user->isAdmin(),
            'selectedYear' => $year,
            'selectedMonth' => $month,
            'selectedArea' => $area,
            'selectedMachine' => $machine,
            'selectedMachineType' => $machineType,
        ]);
    }

    /**
     * Sparepart usage (pm_spareparts) is the ONLY maintenance-cost source
     * in this system — Greasing and Oil Audit have no price/cost column
     * anywhere. Cost = qty * price, the exact existing formula from
     * MachineHistoryController::detail(), PMScheduleController::checklist(),
     * and SparepartReportController — not a new formula.
     */
    private function baseQuery(User $user, ?string $area): Builder
    {
        $query = PMSparepart::query()
            ->join('pm_schedules', 'pm_schedules.id', '=', 'pm_spareparts.pm_schedule_id')
            ->join('spareparts', 'spareparts.id', '=', 'pm_spareparts.sparepart_id');

        return $this->applyScopeTo($query, $user, $area);
    }

    private function applyFilters(Builder $query, ?int $year, ?int $month, ?string $machine, ?string $machineType): Builder
    {
        if ($year) {
            $query->whereYear('pm_schedules.actual_date', $year);
        }

        if ($month) {
            $query->whereMonth('pm_schedules.actual_date', $month);
        }

        if ($machine) {
            $query->where('pm_schedules.machine_number', $machine);
        }

        if ($machineType) {
            $query->where('pm_schedules.machine_type', $machineType);
        }

        return $query;
    }

    private function filteredQuery(
        User $user,
        ?string $area,
        ?int $year,
        ?int $month,
        ?string $machine,
        ?string $machineType
    ): Builder {
        return $this->applyFilters($this->baseQuery($user, $area), $year, $month, $machine, $machineType);
    }

    /**
     * Identical role/area/PIC visibility rule to
     * SparepartReportController/PMReportController.
     */
    private function applyScopeTo(Builder $query, User $user, ?string $area): Builder
    {
        switch ($user->role) {
            case User::ROLE_KOORDINATOR_WWD:
                $query->where('pm_schedules.area', 'WWD');
                break;
            case User::ROLE_KOORDINATOR_BUL:
                $query->where('pm_schedules.area', 'BUL');
                break;
            case User::ROLE_PIC_WWD:
            case User::ROLE_PIC_BUL:
                $query->where('pm_schedules.pic', $user->name);
                break;
            default:
                if ($area) {
                    $query->where('pm_schedules.area', $area);
                }
                break;
        }

        return $query;
    }
}
