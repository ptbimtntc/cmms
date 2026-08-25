<?php

namespace App\Http\Controllers;

use App\Models\PMProblem;
use App\Models\PMSchedule;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class ProblemReportController extends Controller
{
    private const AREAS = ['WWD', 'BUL'];

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
        $category = $request->input('category') ?: null;
        $search = trim((string) $request->input('search', ''));

        $query = $this->filteredQuery($user, $area, $year, $month, $machine, $machineType, $category, $search);

        // --- Summary — same filtered scope as the table/chart below it.
        // Deliberately no Open/Completed counts: PM Problems have no such
        // status concept (only a severity rating — see the detail table). ---
        $totalProblems = (clone $query)->count();

        $topCategoryRow = (clone $query)
            ->select('machine_problems.category as label')
            ->selectRaw('COUNT(*) as total')
            ->whereNotNull('machine_problems.category')
            ->groupBy('machine_problems.category')
            ->orderByDesc('total')
            ->first();

        $topMachineRow = (clone $query)
            ->select('pm_schedules.machine_number as label')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('pm_schedules.machine_number')
            ->orderByDesc('total')
            ->first();

        $summary = [
            'total_problems' => $totalProblems,
            'top_category' => $topCategoryRow ? ['label' => $topCategoryRow->label, 'count' => (int) $topCategoryRow->total] : null,
            'top_machine' => $topMachineRow ? ['label' => $topMachineRow->label, 'count' => (int) $topMachineRow->total] : null,
        ];

        // --- Chart: Top Problem Categories (horizontal bar) — same
        // grouping formula as DashboardController::topProblemCategories(),
        // just filtered dynamically instead of only by year. ---
        $topCategoryRows = (clone $query)
            ->select('machine_problems.category as label')
            ->selectRaw('COUNT(*) as total')
            ->whereNotNull('machine_problems.category')
            ->groupBy('machine_problems.category')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        $maxCategoryTotal = (float) ($topCategoryRows->max('total') ?: 0);
        $topCategories = $topCategoryRows->map(fn ($row) => [
            'label' => $row->label,
            'value' => (int) $row->total,
            'percent' => $maxCategoryTotal > 0 ? round(((float) $row->total / $maxCategoryTotal) * 100) : 0,
        ]);

        // --- Repeated Problem: same machine + same catalog problem
        // recorded more than once within the filtered scope. This is the
        // "problem frequency per machine" / "repeated problem" analysis
        // from the forecasting foundation — real data, no prediction. ---
        $repeatedProblems = (clone $query)
            ->select(
                'pm_schedules.machine_number as machine_number',
                'machine_problems.problem as problem',
                'machine_problems.category as category'
            )
            ->selectRaw('COUNT(*) as occurrences')
            ->groupBy('pm_schedules.machine_number', 'machine_problems.id', 'machine_problems.problem', 'machine_problems.category')
            ->having('occurrences', '>', 1)
            ->orderByDesc('occurrences')
            ->limit(10)
            ->get();

        // --- Detail table ---
        $problems = (clone $query)
            ->select([
                'pm_problems.id as id',
                'pm_schedules.actual_date as actual_date',
                'pm_schedules.area as area',
                'pm_schedules.machine_number as machine_number',
                'pm_schedules.machine_type as machine_type',
                'pm_schedules.order_number as order_number',
                'pm_schedules.pic as pic',
                'machine_problems.problem as problem',
                'machine_problems.category as category',
                'pm_problems.severity as severity',
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

        // --- Machine/Machine Type/Category filter dropdown options —
        // scoped by role/area visibility and the active year/month, but
        // never by the other cross-filters, so narrowing one never hides
        // the choices available in another. ---
        $optionsScope = $this->filteredQuery($user, $area, $year, $month, null, null, null, '');
        $machines = (clone $optionsScope)->select('pm_schedules.machine_number')->distinct()->orderBy('pm_schedules.machine_number')->pluck('machine_number');
        $machineTypes = (clone $optionsScope)->select('pm_schedules.machine_type')->distinct()->orderBy('pm_schedules.machine_type')->pluck('machine_type');
        $categories = (clone $optionsScope)->select('machine_problems.category')->whereNotNull('machine_problems.category')->distinct()->orderBy('machine_problems.category')->pluck('category');

        return view('reports.problem.index', [
            'summary' => $summary,
            'topCategories' => $topCategories,
            'repeatedProblems' => $repeatedProblems,
            'problems' => $problems,
            'years' => $years,
            'machines' => $machines,
            'machineTypes' => $machineTypes,
            'categories' => $categories,
            'areas' => self::AREAS,
            'isAdmin' => $user->isAdmin(),
            'selectedYear' => $year,
            'selectedMonth' => $month,
            'selectedArea' => $area,
            'selectedMachine' => $machine,
            'selectedMachineType' => $machineType,
            'selectedCategory' => $category,
            'search' => $search,
            // Forecasting/Predictive Maintenance is not implemented yet —
            // no fake prediction, no hardcoded values. The base query above
            // already joins pm_problems -> pm_schedules -> machine_problems
            // and is filterable/groupable by machine, date, problem,
            // category, order number, area, and machine type, so a future
            // "Machine X next PM: likely problem" feature can reuse this
            // exact join without any schema changes.
        ]);
    }

    private function applyFilters(
        Builder $query,
        ?int $year,
        ?int $month,
        ?string $machine,
        ?string $machineType,
        ?string $category,
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

        if ($category) {
            $query->where('machine_problems.category', $category);
        }

        if ($search !== '') {
            $query->where(function (Builder $q) use ($search) {
                $q->where('pm_schedules.machine_number', 'like', "%{$search}%")
                    ->orWhere('machine_problems.problem', 'like', "%{$search}%")
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
        ?string $category,
        string $search
    ): Builder {
        $query = PMProblem::query()
            ->join('pm_schedules', 'pm_schedules.id', '=', 'pm_problems.pm_schedule_id')
            ->join('machine_problems', 'machine_problems.id', '=', 'pm_problems.machine_problem_id');

        $this->applyScopeTo($query, $user, $area);

        return $this->applyFilters($query, $year, $month, $machine, $machineType, $category, $search);
    }

    /**
     * Identical role/area/PIC visibility rule to PMReportController —
     * PM Problems are a child of PMSchedule via pm_schedule_id, so
     * authorizing by the parent's area/pic is correct and sufficient.
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
