<?php

namespace App\Http\Controllers;

use App\Models\Greasing;
use App\Models\GreasingFinding;
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
        $month = (int) $request->input('month', now()->month);

        if ($month < 1 || $month > 12) {
            $month = now()->month;
        }

        // Area filter is ADMIN-only — every other role has no established
        // per-area scoping for Greasing (matches the dashboard's pattern).
        $area = $user->isAdmin() && in_array($request->input('area'), ['WWD', 'BUL'], true)
            ? $request->input('area')
            : null;

        // --- KPI (single source of truth: GreasingKpiCalculator) ---
        $statusCounts = $this->scopePeriod(Greasing::query(), $user, $periodType, $year, $month, $area)
            ->select('status')
            ->selectRaw('count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $kpi = GreasingKpiCalculator::fromStatusCounts($statusCounts);

        // --- Yearly Jan-Dec trend (chart) ---
        $monthlyTrend = null;

        if ($periodType === 'yearly') {
            $yearRecords = $this->scopePeriod(Greasing::query(), $user, 'yearly', $year, $month, $area)
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
        $greasings = $this->scopePeriod(Greasing::query(), $user, $periodType, $year, $month, $area)
            ->with('group')
            ->withCount('findings')
            ->orderBy('plan_date')
            ->paginate(15, ['*'], 'greasing_page')
            ->withQueryString();

        // --- Finding Report table (from greasing_findings, same period/scope) ---
        $findings = GreasingFinding::query()
            ->whereHas('greasing', function (Builder $query) use ($user, $periodType, $year, $month, $area) {
                $this->scopePeriod($query, $user, $periodType, $year, $month, $area);
            })
            ->with('greasing.group')
            ->orderByDesc('id')
            ->paginate(15, ['*'], 'finding_page')
            ->withQueryString();

        // --- Year filter options ---
        $yearScope = Greasing::query()->when($user->isPic(), fn ($q) => $q->where('pic', $user->name));
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

        return view('greasing-report.index', compact(
            'kpi',
            'monthlyTrend',
            'greasings',
            'findings',
            'periodType',
            'year',
            'month',
            'years',
            'months',
            'area'
        ));
    }

    /**
     * Applies the same Monthly/Yearly period filter (and PIC visibility
     * scope) to a Greasing query builder. Used by the KPI, the chart, the
     * Greasing table, and the Finding table so all four always agree on
     * what "the selected period" means. $area is the ADMIN-only filter;
     * Greasing has no area column, so it matches via the same group-name
     * convention Group::inferredArea() already uses (e.g. "WWD 1" => WWD).
     */
    private function scopePeriod(Builder $query, User $user, string $periodType, int $year, int $month, ?string $area = null): Builder
    {
        $query->whereYear('plan_date', $year);

        if ($periodType === 'monthly') {
            $query->whereMonth('plan_date', $month);
        }

        if ($user->isPic()) {
            $query->where('pic', $user->name);
        }

        if ($area) {
            $query->whereHas('group', fn ($q) => $q->whereRaw('UPPER(name) LIKE ?', ['%'.$area.'%']));
        }

        return $query;
    }
}
