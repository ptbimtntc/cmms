<?php

namespace App\Http\Controllers;

use App\Models\PMSchedule;
use App\Models\PMSparepart;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class SparepartReportController extends Controller
{
    private const AREAS = ['WWD', 'BUL'];

    private const STATUSES = ['ACTIVE', 'INACTIVE'];

    public function index(Request $request)
    {
        $user = $request->user();

        $year = $request->filled('year') ? (int) $request->input('year') : null;
        $month = $request->filled('month') ? (int) $request->input('month') : null;
        $area = $user->isAdmin() && in_array($request->input('area'), self::AREAS, true)
            ? $request->input('area')
            : null;
        $machine = $request->input('machine') ?: null;
        $machineType = $request->input('machine_type') ?: null;
        $segment = $request->input('segment') ?: null;
        $status = in_array($request->input('status'), self::STATUSES, true) ? $request->input('status') : null;
        $search = trim((string) $request->input('search', ''));

        $query = $this->filteredQuery($user, $area, $year, $month, $machine, $machineType, $segment, $status, $search);

        // --- Summary — same filtered scope as the table/top-analysis below. ---
        $summaryRow = (clone $query)
            ->selectRaw('COUNT(*) as total_transactions')
            ->selectRaw('COALESCE(SUM(pm_spareparts.qty), 0) as total_quantity')
            ->selectRaw('COALESCE(SUM(pm_spareparts.qty * spareparts.price), 0) as total_cost')
            ->selectRaw('COUNT(DISTINCT spareparts.id) as unique_materials')
            ->first();

        $summary = [
            'total_transactions' => (int) ($summaryRow->total_transactions ?? 0),
            'total_quantity' => (int) ($summaryRow->total_quantity ?? 0),
            'total_cost' => (float) ($summaryRow->total_cost ?? 0),
            'unique_materials' => (int) ($summaryRow->unique_materials ?? 0),
        ];

        // --- Top 10 Most Used (by quantity) and Top 10 Highest Cost —
        // both computed from the exact same filtered scope. ---
        $topUsage = (clone $query)
            ->select('spareparts.material_number', 'spareparts.description')
            ->selectRaw('SUM(pm_spareparts.qty) as total_qty')
            ->groupBy('spareparts.id', 'spareparts.material_number', 'spareparts.description')
            ->orderByDesc('total_qty')
            ->limit(10)
            ->get();

        $topCost = (clone $query)
            ->select('spareparts.material_number', 'spareparts.description')
            ->selectRaw('SUM(pm_spareparts.qty * spareparts.price) as total_cost')
            ->groupBy('spareparts.id', 'spareparts.material_number', 'spareparts.description')
            ->orderByDesc('total_cost')
            ->limit(10)
            ->get();

        // --- Detail table ---
        $usages = (clone $query)
            ->select([
                'pm_spareparts.id as id',
                'pm_spareparts.qty as qty',
                'pm_schedules.actual_date as actual_date',
                'pm_schedules.area as area',
                'pm_schedules.machine_number as machine_number',
                'pm_schedules.order_number as order_number',
                'spareparts.material_number as material_number',
                'spareparts.description as description',
                'spareparts.unit as unit',
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
        if ($year && ! in_array($year, $years, true)) {
            $years[] = $year;
        }
        rsort($years);

        // --- Machine/Machine Type/Segment filter dropdown options —
        // scoped by role/area visibility and the active year/month, but
        // never by the other cross-filters, so narrowing one never hides
        // the choices available in another. ---
        $optionsScope = $this->filteredQuery($user, $area, $year, $month, null, null, null, null, '');
        $machines = (clone $optionsScope)->select('pm_schedules.machine_number')->distinct()->orderBy('pm_schedules.machine_number')->pluck('machine_number');
        $machineTypes = (clone $optionsScope)->select('pm_schedules.machine_type')->distinct()->orderBy('pm_schedules.machine_type')->pluck('machine_type');
        $segments = (clone $optionsScope)->select('spareparts.segment')->whereNotNull('spareparts.segment')->distinct()->orderBy('spareparts.segment')->pluck('segment');

        return view('reports.sparepart.index', [
            'summary' => $summary,
            'topUsage' => $topUsage,
            'topCost' => $topCost,
            'usages' => $usages,
            'years' => $years,
            'machines' => $machines,
            'machineTypes' => $machineTypes,
            'segments' => $segments,
            'statuses' => self::STATUSES,
            'areas' => self::AREAS,
            'isAdmin' => $user->isAdmin(),
            'selectedYear' => $year,
            'selectedMonth' => $month,
            'selectedArea' => $area,
            'selectedMachine' => $machine,
            'selectedMachineType' => $machineType,
            'selectedSegment' => $segment,
            'selectedStatus' => $status,
            'search' => $search,
            // Forecasting/Predictive Maintenance is not implemented yet.
            // The underlying query already supports grouping usage by
            // machine_number + material_number + actual_date (see
            // baseQuery()/filteredQuery() below) — a future forecasting
            // feature can group this same join by those three dimensions
            // without any schema or query-shape changes. No key is passed
            // here; the view's placeholder section renders no data.
        ]);
    }

    /**
     * Sparepart usage has no access-control concept of its own — it is
     * purely a child of PMSchedule via pm_schedule_id, so authorizing by
     * the parent PMSchedule's area/pic (identical rule to PMReportController
     * and DashboardController) is correct and sufficient.
     */
    private function baseQuery(User $user, ?string $area): Builder
    {
        $query = PMSparepart::query()
            ->join('pm_schedules', 'pm_schedules.id', '=', 'pm_spareparts.pm_schedule_id')
            ->join('spareparts', 'spareparts.id', '=', 'pm_spareparts.sparepart_id');

        return $this->applyScopeTo($query, $user, $area);
    }

    private function applyFilters(
        Builder $query,
        ?int $year,
        ?int $month,
        ?string $machine,
        ?string $machineType,
        ?string $segment,
        ?string $status,
        string $search
    ): Builder {
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

        if ($segment) {
            $query->where('spareparts.segment', $segment);
        }

        if ($status) {
            $query->where('spareparts.status', $status);
        }

        if ($search !== '') {
            $query->where(function (Builder $q) use ($search) {
                $q->where('spareparts.material_number', 'like', "%{$search}%")
                    ->orWhere('spareparts.description', 'like', "%{$search}%")
                    ->orWhere('pm_schedules.order_number', 'like', "%{$search}%");
            });
        }

        return $query;
    }

    private function filteredQuery(
        User $user,
        ?string $area,
        ?int $year,
        ?int $month,
        ?string $machine,
        ?string $machineType,
        ?string $segment,
        ?string $status,
        string $search
    ): Builder {
        return $this->applyFilters($this->baseQuery($user, $area), $year, $month, $machine, $machineType, $segment, $status, $search);
    }

    /**
     * Identical role/area/PIC visibility rule to PMReportController and
     * DashboardController — kept as its own copy here rather than a
     * cross-controller refactor, matching this codebase's established
     * convention of each report controller holding its own copy.
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
