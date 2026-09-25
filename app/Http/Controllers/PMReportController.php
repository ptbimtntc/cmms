<?php

namespace App\Http\Controllers;

use App\Models\PMSchedule;
use App\Models\User;
use App\Services\PMReportKpiCalculator;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class PMReportController extends Controller
{
    /**
     * Areas actually used by this application (PMSchedule.area is a free
     * string column, but every import/UI only ever writes WWD or BUL — see
     * DashboardController::AREAS).
     */
    private const AREAS = ['WWD', 'BUL'];

    private const STATUSES = ['OPEN', 'IN_PROGRESS', 'FINISHED', 'FINISHED_ON_TIME', 'MISSED'];

    public function index(Request $request)
    {
        $user = $request->user();

        $year = $request->filled('year') ? (int) $request->input('year') : null;

        // Month and Status are multi-select (checkbox-dropdown): both arrive
        // as arrays, but a plain single value (old bookmarked link) still
        // works via the (array) cast.
        $months = collect((array) $request->input('month', []))
            ->map(fn ($m) => (int) $m)
            ->filter(fn ($m) => $m >= 1 && $m <= 12)
            ->values()
            ->all();

        // Area filter is ADMIN-only — every other role is already fixed to
        // one area/pic by applyScopeTo() (same convention as the dashboard
        // and Greasing Report).
        $area = $user->isAdmin() && in_array($request->input('area'), self::AREAS, true)
            ? $request->input('area')
            : null;
        $machineType = $request->input('machine_type') ?: null;
        $machine = $request->input('machine') ?: null;
        $pic = $request->input('pic') ?: null;
        $statuses = array_values(array_intersect((array) $request->input('status', []), self::STATUSES));
        $search = trim((string) $request->input('search', ''));

        $query = $this->filteredQuery($user, $area, $year, $months, $machineType, $machine, $pic, $statuses, $search);

        // Summary must always reflect the exact same filtered/scoped query
        // as the table below it, so the two can never disagree.
        $statusCounts = (clone $query)
            ->select('status')
            ->selectRaw('count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $summary = PMReportKpiCalculator::fromStatusCounts($statusCounts);

        // Jan-Sep trend charts always plot the active Year filter; "All
        // Years" has no single year to bucket by, so it falls back to the
        // current year rather than showing an ambiguous mix.
        $trendYear = $year ?? Carbon::now()->year;
        $trend = $this->monthlyTrend($user, $area, $machineType, $machine, $pic, $statuses, $search, $trendYear);

        $schedules = $query
            ->orderByDesc('plan_date')
            ->orderBy('machine_number')
            ->paginate(20)
            ->withQueryString();

        // Filter dropdown options are scoped by role/area visibility only —
        // never by the currently active filters — so narrowing one filter
        // never hides the choices available in another (same convention as
        // PMScheduleController::index()).
        $optionsScope = $this->scoped($user, $area);

        return view('reports.pm.index', [
            'summary' => $summary,
            'trend' => $trend,
            'trendYear' => $trendYear,
            'schedules' => $schedules,
            'years' => $this->availableYears($user, $year),
            'machineTypes' => (clone $optionsScope)->whereNotNull('machine_type')->distinct()->orderBy('machine_type')->pluck('machine_type'),
            'machines' => (clone $optionsScope)->whereNotNull('machine_number')->distinct()->orderBy('machine_number')->pluck('machine_number'),
            'pics' => (clone $optionsScope)->whereNotNull('pic')->distinct()->orderBy('pic')->pluck('pic'),
            'statuses' => self::STATUSES,
            'areas' => $this->visibleAreas($user, $area),
            'isAdmin' => $user->isAdmin(),
            'selectedYear' => $year,
            'selectedMonths' => $months,
            'selectedArea' => $area,
            'selectedMachineType' => $machineType,
            'selectedMachine' => $machine,
            'selectedPic' => $pic,
            'selectedStatuses' => $statuses,
            'search' => $search,
            // Forecasting/Predictive Maintenance is not implemented yet —
            // deliberately no key is passed here. When it is built, add a
            // 'forecast' => ... array here (e.g. per-machine predicted
            // problem/sparepart/confidence) and the view's Forecasting
            // section already has the placeholder ready to receive it.
        ]);
    }

    /**
     * Applies every active filter (year/month/machine type/machine/pic/
     * status/search) on top of the role+area authorization scope. Search
     * matches Machine Number OR Order Number, combined with every other
     * filter via AND — e.g. Area=WWD + Year=2026 + Search=12345678 only
     * ever returns PM schedules matching all three at once.
     */
    private function filteredQuery(
        User $user,
        ?string $area,
        ?int $year,
        array $months,
        ?string $machineType,
        ?string $machine,
        ?string $pic,
        array $statuses,
        string $search
    ): Builder {
        $query = $this->scoped($user, $area);

        if ($year) {
            $query->whereYear('plan_date', $year);
        }

        if (! empty($months)) {
            $query->where(function (Builder $q) use ($months) {
                foreach ($months as $m) {
                    $q->orWhereMonth('plan_date', $m);
                }
            });
        }

        return $this->applyNonTemporalFilters($query, $machineType, $machine, $pic, $statuses, $search);
    }

    /**
     * Machine type/machine/pic/status/search — every filter that narrows
     * WHICH schedules count, as opposed to WHEN (year/month). Split out so
     * the monthly trend chart (monthlyTrend()) can honor these same filters
     * while defining its own month axis, independent of the year/month
     * filter above it.
     */
    private function applyNonTemporalFilters(
        Builder $query,
        ?string $machineType,
        ?string $machine,
        ?string $pic,
        array $statuses,
        string $search
    ): Builder {
        if ($machineType) {
            $query->where('machine_type', $machineType);
        }

        if ($machine) {
            $query->where('machine_number', $machine);
        }

        if ($pic) {
            $query->where('pic', $pic);
        }

        if (! empty($statuses)) {
            $query->whereIn('status', $statuses);
        }

        if ($search !== '') {
            $query->where(function (Builder $q) use ($search) {
                $q->where('machine_number', 'like', "%{$search}%")
                    ->orWhere('order_number', 'like', "%{$search}%");
            });
        }

        return $query;
    }

    /**
     * Jan–Sep completion/closing % trend for the PM Report's summary
     * charts. Honors every filter except year/month (those define the
     * chart's own axis): Area/Machine Type/Machine/PIC/Status/Search still
     * narrow which schedules count, same as the summary cards and table.
     * One query for the whole year, bucketed by month in PHP — same
     * approach as DashboardController::completionTrend(), portable across
     * MySQL/SQLite and avoiding 9 separate round trips.
     */
    private function monthlyTrend(
        User $user,
        ?string $area,
        ?string $machineType,
        ?string $machine,
        ?string $pic,
        array $statuses,
        string $search,
        int $year
    ): array {
        $yearRecords = $this->applyNonTemporalFilters(
            $this->scoped($user, $area),
            $machineType,
            $machine,
            $pic,
            $statuses,
            $search
        )
            ->whereYear('plan_date', $year)
            ->get(['plan_date', 'status']);

        return collect(range(1, 9))->map(function (int $m) use ($yearRecords) {
            $statusCounts = $yearRecords
                ->filter(fn ($pm) => Carbon::parse($pm->plan_date)->month === $m)
                ->countBy('status');

            $kpi = PMReportKpiCalculator::fromStatusCounts($statusCounts);

            return [
                'month' => $m,
                'label' => Carbon::create(null, $m, 1)->format('M'),
                'has_data' => $kpi['total'] > 0,
                'closing_percent' => $kpi['closing_percent'],
                'completion_percent' => $kpi['completion_percent'],
            ];
        })->values()->all();
    }

    private function scoped(User $user, ?string $area): Builder
    {
        return $this->applyScopeTo(PMSchedule::query(), $user, $area);
    }

    /**
     * Same role/area/PIC visibility rule as
     * DashboardController::applyScopeTo() (that method is private on a
     * different controller, and PMScheduleController::index() already
     * implements this same rule a third time as an inline switch — kept as
     * a separate copy here rather than a cross-controller refactor).
     *
     * ADMIN            -> ALL, or the given $area if provided
     * KOORDINATOR WWD  -> fixed to WWD, $area ignored
     * KOORDINATOR BUL  -> fixed to BUL, $area ignored
     * PIC WWD / PIC BUL -> fixed to their own name, $area ignored
     */
    private function applyScopeTo(Builder $query, User $user, ?string $area): Builder
    {
        switch ($user->role) {
            case User::ROLE_KOORDINATOR_WWD:
                $query->where('area', 'WWD');
                break;
            case User::ROLE_KOORDINATOR_BUL:
                $query->where('area', 'BUL');
                break;
            case User::ROLE_PIC_WWD:
            case User::ROLE_PIC_BUL:
                $query->where('pic', $user->name);
                break;
            default:
                if ($area) {
                    $query->where('area', $area);
                }
                break;
        }

        return $query;
    }

    private function userAreaMatches(User $user, string $area): bool
    {
        return match ($user->role) {
            User::ROLE_KOORDINATOR_WWD, User::ROLE_PIC_WWD => $area === 'WWD',
            User::ROLE_KOORDINATOR_BUL, User::ROLE_PIC_BUL => $area === 'BUL',
            default => true,
        };
    }

    /**
     * Which areas the Area filter dropdown should offer: role permission
     * narrowed further by the optional ADMIN-only active area filter.
     */
    private function visibleAreas(User $user, ?string $area): array
    {
        $allowed = collect(self::AREAS)->filter(fn (string $a) => $this->userAreaMatches($user, $a))->values();

        if ($area && $allowed->contains($area)) {
            return [$area];
        }

        return $allowed->all();
    }

    /**
     * MIN/MAX-based year range (portable across MySQL and SQLite), same
     * technique as DashboardController::availableYears().
     */
    private function availableYears(User $user, ?int $selectedYear): array
    {
        $scope = $this->scoped($user, null);
        $minPlanDate = (clone $scope)->min('plan_date');
        $maxPlanDate = (clone $scope)->max('plan_date');

        $years = $minPlanDate && $maxPlanDate
            ? range((int) Carbon::parse($maxPlanDate)->format('Y'), (int) Carbon::parse($minPlanDate)->format('Y'))
            : [];

        if ($selectedYear && ! in_array($selectedYear, $years, true)) {
            $years[] = $selectedYear;
        }

        rsort($years);

        return $years;
    }
}
