<?php

namespace App\Http\Controllers;

use App\Models\Greasing;
use App\Models\GreasingFinding;
use App\Models\Group;
use App\Models\User;
use App\Services\GreasingKpiCalculator;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class GreasingReportController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $periodType = $request->input('period_type') === 'yearly' ? 'yearly' : 'monthly';
        $year = (int) $request->input('year', now()->year);
        // Month is multi-select (checkbox-dropdown), only meaningful in
        // 'monthly' period type: arrives as an array, but a plain single
        // value (old bookmarked link) still works via the (array) cast.
        // Empty selection means "every month of the year" — there is no
        // forced default to the current month anymore.
        //
        // Named $selectedMonths (not $months) on purpose: further down,
        // $months is reused for the Jan-Dec dropdown OPTIONS list — reusing
        // the same name here would get this value silently overwritten by
        // that assignment (this happened once already, in
        // MachineReportController, before it was caught and fixed).
        $selectedMonths = collect((array) $request->input('month', []))
            ->map(fn ($m) => (int) $m)
            ->filter(fn ($m) => $m >= 1 && $m <= 12)
            ->values()
            ->all();

        // Area filter is ADMIN-only — every other role has no established
        // per-area scoping for Greasing (see applyVisibility()).
        $area = $user->isAdmin() && in_array($request->input('area'), ['WWD', 'BUL'], true)
            ? $request->input('area')
            : null;

        $groupId = $request->filled('group_id') ? (int) $request->input('group_id') : null;
        $cycle = $request->input('cycle') ?: null;
        $pic = $user->isPic() ? null : ($request->input('pic') ?: null);
        // Status is multi-select (checkbox-dropdown): arrives as an array,
        // but a plain single value (old bookmarked link) still works via
        // the (array) cast.
        $statuses = array_values(array_intersect((array) $request->input('status', []), Greasing::STATUSES));
        $search = trim((string) $request->input('search', ''));
        $isAdmin = $user->isAdmin();
        $isPic = $user->isPic();

        // --- KPI (single source of truth: GreasingKpiCalculator) ---
        $statusCounts = $this->filteredQuery($user, $periodType, $year, $selectedMonths, $area, $groupId, $cycle, $pic, $statuses, $search)
            ->select('status')
            ->selectRaw('count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $kpi = GreasingKpiCalculator::fromStatusCounts($statusCounts);

        // --- Yearly Jan-Dec trend (chart) — every active filter also
        // narrows the trend, not just the KPI/table. ---
        $monthlyTrend = null;

        if ($periodType === 'yearly') {
            $yearRecords = $this->filteredQuery($user, 'yearly', $year, $selectedMonths, $area, $groupId, $cycle, $pic, $statuses, $search)
                ->get(['plan_date', 'status']);

            $monthlyTrend = collect(range(1, 12))->map(function (int $m) use ($yearRecords) {
                $counts = $yearRecords
                    ->filter(fn ($g) => $g->plan_date->month === $m)
                    ->countBy('status');

                return array_merge(
                    GreasingKpiCalculator::fromStatusCounts($counts),
                    [
                        'month' => $m,
                        'label' => Carbon::create(null, $m, 1)->format('M'),
                    ]
                );
            });
        }

        // --- Greasing Report table ---
        $greasings = $this->filteredQuery($user, $periodType, $year, $selectedMonths, $area, $groupId, $cycle, $pic, $statuses, $search)
            ->with('group')
            ->withCount('findings')
            ->orderBy('plan_date')
            ->paginate(15, ['*'], 'greasing_page')
            ->withQueryString();

        // --- Finding Report table (from greasing_findings, same filtered scope) ---
        $findings = GreasingFinding::query()
            ->whereHas('greasing', function (Builder $query) use ($user, $periodType, $year, $selectedMonths, $area, $groupId, $cycle, $pic, $statuses, $search) {
                $this->applyFilters(
                    $this->applyVisibility($query, $user, $area),
                    $periodType,
                    $year,
                    $selectedMonths,
                    $groupId,
                    $cycle,
                    $pic,
                    $statuses,
                    $search
                );
            })
            ->with('greasing.group')
            ->orderByDesc('id')
            ->paginate(15, ['*'], 'finding_page')
            ->withQueryString();

        // --- Year filter options (respect the same role/area visibility) ---
        $yearScope = Greasing::query()->visibleToUser($user);
        $minPlanDate = (clone $yearScope)->min('plan_date');
        $maxPlanDate = (clone $yearScope)->max('plan_date');

        $years = $minPlanDate
            ? range((int) Carbon::parse($maxPlanDate)->format('Y'), (int) Carbon::parse($minPlanDate)->format('Y'))
            : [];

        if (! in_array($year, $years, true)) {
            $years[] = $year;
        }
        rsort($years);

        $months = collect(range(1, 12))->mapWithKeys(
            fn (int $m) => [$m => Carbon::create(null, $m, 1)->format('F')]
        );

        // --- Group/Cycle/PIC filter dropdown options — scoped by role/area
        // visibility AND the active period (a cycle/group/pic that only
        // occurs outside the selected year/month must not leak into the
        // dropdown), but never by the other cross-filters (group/cycle/pic/
        // status/search), so narrowing one of those never hides the choices
        // available in another. ---
        $optionsScope = $this->applyFilters(
            $this->applyVisibility(Greasing::query(), $user, $area),
            $periodType,
            $year,
            $selectedMonths,
            null,
            null,
            null,
            [],
            ''
        );

        $groupIds = (clone $optionsScope)->whereNotNull('group_id')->distinct()->pluck('group_id');
        $groups = Group::whereIn('id', $groupIds)->orderBy('name')->get(['id', 'name']);
        $cycles = (clone $optionsScope)->whereNotNull('cycle')->distinct()->orderBy('cycle')->pluck('cycle');
        $pics = (clone $optionsScope)->whereNotNull('pic')->distinct()->orderBy('pic')->pluck('pic');

        return view('reports.greasing.index', compact(
            'kpi',
            'monthlyTrend',
            'greasings',
            'findings',
            'periodType',
            'year',
            'selectedMonths',
            'years',
            'months',
            'area',
            'groups',
            'cycles',
            'pics',
            'groupId',
            'cycle',
            'pic',
            'statuses',
            'search',
            'isAdmin',
            'isPic',
        ));
    }

    /**
     * Role/area visibility scope. Per-area authorization now mirrors
     * PMScheduleController: a WWD role (koordinator/PIC) only ever sees
     * WWD-group schedules, a BUL role only BUL-group ones, and PIC roles
     * are additionally restricted to schedules assigned to them by name.
     * ADMIN is unrestricted except for the ADMIN-only $area filter. The
     * area a schedule belongs to is derived from its Group's name, since
     * Greasing has no area column (see Greasing::scopeVisibleToUser()).
     */
    private function applyVisibility(Builder $query, User $user, ?string $area): Builder
    {
        $query->visibleToUser($user);

        if ($area) {
            $query->whereHas('group', fn ($q) => $q->whereRaw('UPPER(name) LIKE ?', ['%'.$area.'%']));
        }

        return $query;
    }

    /**
     * Every active filter (period/group/cycle/pic/status/search), applied
     * on top of whatever visibility scope the caller already built. Search
     * matches Order Number OR Group name, combined with every other filter
     * via AND.
     */
    private function applyFilters(
        Builder $query,
        string $periodType,
        int $year,
        array $months,
        ?int $groupId,
        ?string $cycle,
        ?string $pic,
        array $statuses,
        string $search
    ): Builder {
        $query->whereYear('plan_date', $year);

        if ($periodType === 'monthly' && ! empty($months)) {
            $query->where(function (Builder $q) use ($months) {
                foreach ($months as $m) {
                    $q->orWhereMonth('plan_date', $m);
                }
            });
        }

        if ($groupId) {
            $query->where('group_id', $groupId);
        }

        if ($cycle) {
            $query->where('cycle', $cycle);
        }

        if ($pic) {
            $query->where('pic', $pic);
        }

        if (! empty($statuses)) {
            $query->whereIn('status', $statuses);
        }

        if ($search !== '') {
            $query->where(function (Builder $q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                    ->orWhereHas('group', fn (Builder $g) => $g->where('name', 'like', "%{$search}%"));
            });
        }

        return $query;
    }

    private function filteredQuery(
        User $user,
        string $periodType,
        int $year,
        array $months,
        ?string $area,
        ?int $groupId,
        ?string $cycle,
        ?string $pic,
        array $statuses,
        string $search
    ): Builder {
        $query = $this->applyVisibility(Greasing::query(), $user, $area);

        return $this->applyFilters($query, $periodType, $year, $months, $groupId, $cycle, $pic, $statuses, $search);
    }
}
