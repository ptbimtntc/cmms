<?php

namespace App\Http\Controllers;

use App\Models\Greasing;
use App\Models\Machine;
use App\Models\OilAudit;
use App\Models\PMProblem;
use App\Models\PMSchedule;
use App\Models\PMSparepart;
use App\Models\User;
use App\Services\GreasingKpiCalculator;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class DashboardController extends Controller
{
    /**
     * Areas actually used by this application (Machine/PMSchedule.area is a
     * free string column, but every import/UI in the app only ever writes
     * WWD or BUL — see Machine/PMSchedule migrations and Group::inferredArea()).
     */
    private const AREAS = ['WWD', 'BUL'];

    /**
     * Fixed company-wide PM completion target used to draw the reference
     * line on the trend chart. This is a display constant only — it does
     * not change how completion % itself is calculated.
     */
    private const PM_TARGET_PERCENT = 96;

    /**
     * Oil Audit is a WWD-only module (see OilAuditController::AUDIT_AREA /
     * AUDIT_MACHINE_TYPES and its route middleware) — BUL roles never had
     * access to it, so the dashboard must not show it to them either.
     */
    private const OIL_AUDIT_AREA = 'WWD';

    private const OIL_AUDIT_MACHINE_TYPES = ['NDE', 'NDB'];

    public function index(Request $request)
    {
        $user = $request->user();
        $now = Carbon::now();

        $year = (int) $request->input('year', $now->year);
        $month = $request->filled('month') ? (int) $request->input('month') : null;
        // Global area filter (top of page). Only ADMIN can override — every
        // other role is already fixed to one area/pic by applyScopeTo().
        $area = $user->isAdmin() && in_array($request->input('area'), self::AREAS, true)
            ? $request->input('area')
            : null;

        // --- KPI row + progress card (always "current month", live) ---
        $monthKpi = $this->monthKpi($user, $now, $area);
        $overdue = $this->scoped($user, $area)->where('status', 'MISSED')->count();

        // --- PM Completion Trend (Jan-Dec of the selected year) ---
        $trend = $this->completionTrend($user, $year, $area);

        // --- PM Status Breakdown (selected year / optional month, area = global filter) ---
        $statusBreakdown = $this->statusBreakdown($user, $year, $month, $area);
        $statusLegend = $this->statusLegend($statusBreakdown);
        $statusSubtitle = collect([
            $year,
            $month ? Carbon::create(null, $month, 1)->format('F') : 'All Months',
            $area ?: ($user->isAdmin() ? 'All Areas' : null),
        ])->filter()->implode(' · ');

        // --- Completion by Area (current month) ---
        $areaProgress = $this->completionByArea($user, $now, $area);

        // --- Top 10 Sparepart Usage (selected year) ---
        $topSpareparts = $this->topSparepartUsage($user, $year, $area);

        // --- Top 10 Problem Categories (selected year) ---
        $topProblems = $this->topProblemCategories($user, $year, $area);

        // --- Today's PM Schedule (plan_date = today, not due_date) ---
        $todaySchedules = $this->todaySchedule($user, $now, $area);

        // --- Year options for every "year" filter on the page ---
        $years = $this->availableYears($user, $year);

        // --- Greasing (current month snapshot, same KPI formula as Greasing Report) ---
        $greasing = $this->greasingSummary($user, $now);

        // --- Oil Audit (WWD-only module; null hides the section entirely) ---
        $oilAudit = $this->oilAuditSummary($user, $area);

        return view('dashboard', [
            'monthKpi' => $monthKpi,
            'overdue' => $overdue,
            'trend' => $trend,
            'statusBreakdown' => $statusBreakdown,
            'statusLegend' => $statusLegend,
            'statusSubtitle' => $statusSubtitle,
            'areaProgress' => $areaProgress,
            'topSpareparts' => $topSpareparts,
            'topProblems' => $topProblems,
            'todaySchedules' => $todaySchedules,
            'years' => $years,
            'selectedYear' => $year,
            'selectedMonth' => $month,
            'selectedArea' => $area,
            'isAdmin' => $user->isAdmin(),
            'pmTargetPercent' => self::PM_TARGET_PERCENT,
            'greasing' => $greasing,
            'oilAudit' => $oilAudit,
        ]);
    }

    /**
     * Applies the exact same area/PIC visibility rule used by
     * PMScheduleController::index(), so the dashboard never shows a role
     * data it isn't allowed to see. $area additionally applies the
     * ADMIN-only global area filter at the top of the page, if selected.
     */
    private function scoped(User $user, ?string $area = null): Builder
    {
        return $this->applyScopeTo(PMSchedule::query(), $user, $area);
    }

    /**
     * Target = all PM planned this month, Actual = finished this month,
     * Remaining = Target - Actual, Completion % = Actual / Target * 100.
     */
    private function monthKpi(User $user, Carbon $now, ?string $area): array
    {
        $counts = $this->scoped($user, $area)
            ->whereYear('plan_date', $now->year)
            ->whereMonth('plan_date', $now->month)
            ->select('status')
            ->selectRaw('count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $target = (int) $counts->sum();
        $actual = (int) ($counts->get('FINISHED', 0) + $counts->get('FINISHED_ON_TIME', 0));

        return [
            'target' => $target,
            'actual' => $actual,
            'remaining' => max($target - $actual, 0),
            'completion_percent' => $target > 0 ? round($actual / $target * 100) : 0,
        ];
    }

    /**
     * Groups the year's schedules by month in PHP (same technique as
     * GreasingReportController::index()'s monthlyTrend) instead of a
     * database-specific MONTH() function, so this works identically on
     * MySQL (production) and SQLite (tests).
     */
    private function completionTrend(User $user, int $year, ?string $area): Collection
    {
        $yearRecords = $this->scoped($user, $area)
            ->whereYear('plan_date', $year)
            ->get(['plan_date', 'status']);

        return collect(range(1, 12))->map(function (int $m) use ($yearRecords) {
            $forMonth = $yearRecords->filter(
                fn ($pm) => Carbon::parse($pm->plan_date)->month === $m
            )->countBy('status');

            $target = (int) $forMonth->sum();
            $actual = (int) ($forMonth->get('FINISHED', 0) + $forMonth->get('FINISHED_ON_TIME', 0));

            return [
                'month' => $m,
                'label' => Carbon::create(null, $m, 1)->format('M'),
                'target' => $target,
                'actual' => $actual,
                'completion_percent' => $target > 0 ? round($actual / $target * 100) : 0,
            ];
        });
    }

    private function statusBreakdown(User $user, int $year, ?int $month, ?string $area): array
    {
        $query = $this->scoped($user, $area)->whereYear('plan_date', $year);

        if ($month) {
            $query->whereMonth('plan_date', $month);
        }

        $counts = $query
            ->select('status')
            ->selectRaw('count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $statuses = ['OPEN', 'IN_PROGRESS', 'FINISHED', 'FINISHED_ON_TIME', 'MISSED'];

        return collect($statuses)
            ->mapWithKeys(fn (string $status) => [$status => (int) $counts->get($status, 0)])
            ->all();
    }

    /**
     * Turns the raw status => count map into the label/color/percent rows
     * the donut's legend renders, so the chart is legible even when a
     * single status makes up 100% of the segment.
     */
    private function statusLegend(array $statusBreakdown): array
    {
        $meta = [
            'OPEN' => ['label' => 'Open', 'color' => '#f59e0b'],
            'IN_PROGRESS' => ['label' => 'In Progress', 'color' => '#94a3b8'],
            'FINISHED' => ['label' => 'Finished', 'color' => '#8b5cf6'],
            'FINISHED_ON_TIME' => ['label' => 'Finished On Time', 'color' => '#10b981'],
            'MISSED' => ['label' => 'Missed', 'color' => '#f43f5e'],
        ];

        $total = array_sum($statusBreakdown);

        return collect($meta)->map(function (array $info, string $status) use ($statusBreakdown, $total) {
            $value = $statusBreakdown[$status] ?? 0;

            return [
                'label' => $info['label'],
                'color' => $info['color'],
                'value' => $value,
                'percent' => $total > 0 ? round($value / $total * 100) : 0,
            ];
        })->values()->all();
    }

    private function completionByArea(User $user, Carbon $now, ?string $area): Collection
    {
        $counts = $this->scoped($user, $area)
            ->whereYear('plan_date', $now->year)
            ->whereMonth('plan_date', $now->month)
            ->whereIn('area', self::AREAS)
            ->select('area', 'status')
            ->selectRaw('count(*) as total')
            ->groupBy('area', 'status')
            ->get()
            ->groupBy('area');

        return collect($this->visibleAreas($user, $area))
            ->map(function (string $area) use ($counts) {
                $forArea = collect($counts->get($area, []))->pluck('total', 'status');
                $total = (int) $forArea->sum();
                $actual = (int) ($forArea->get('FINISHED', 0) + $forArea->get('FINISHED_ON_TIME', 0));

                return [
                    'name' => $area,
                    'percent' => $total > 0 ? round($actual / $total * 100) : 0,
                ];
            })
            ->values();
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
     * Which areas the current view should show: role permission narrowed
     * further by the optional ADMIN-only global area filter.
     */
    private function visibleAreas(User $user, ?string $area): array
    {
        $allowed = collect(self::AREAS)->filter(fn (string $a) => $this->userAreaMatches($user, $a))->values();

        if ($area && $allowed->contains($area)) {
            return [$area];
        }

        return $allowed->all();
    }

    private function topSparepartUsage(User $user, int $year, ?string $area, int $limit = 10): Collection
    {
        $rows = PMSparepart::query()
            ->whereHas('pmSchedule', function (Builder $q) use ($user, $year, $area) {
                $this->applyScopeTo($q, $user, $area)->whereYear('plan_date', $year);
            })
            ->join('spareparts', 'spareparts.id', '=', 'pm_spareparts.sparepart_id')
            ->select('spareparts.description as label')
            ->selectRaw('SUM(pm_spareparts.qty) as total_qty')
            ->groupBy('spareparts.id', 'spareparts.description')
            ->orderByDesc('total_qty')
            ->limit($limit)
            ->get();

        $max = (float) ($rows->max('total_qty') ?: 0);

        return $rows->map(fn ($row) => [
            'label' => $row->label ?: '-',
            'value' => (int) $row->total_qty,
            'percent' => $max > 0 ? round(((float) $row->total_qty / $max) * 100) : 0,
        ]);
    }

    private function topProblemCategories(User $user, int $year, ?string $area, int $limit = 10): Collection
    {
        $rows = PMProblem::query()
            ->whereHas('pmSchedule', function (Builder $q) use ($user, $year, $area) {
                $this->applyScopeTo($q, $user, $area)->whereYear('plan_date', $year);
            })
            ->join('machine_problems', 'machine_problems.id', '=', 'pm_problems.machine_problem_id')
            ->whereNotNull('machine_problems.category')
            ->select('machine_problems.category as label')
            ->selectRaw('count(*) as total')
            ->groupBy('machine_problems.category')
            ->orderByDesc('total')
            ->limit($limit)
            ->get();

        $max = (float) ($rows->max('total') ?: 0);

        return $rows->map(fn ($row) => [
            'label' => $row->label ?: '-',
            'value' => (int) $row->total,
            'percent' => $max > 0 ? round(((float) $row->total / $max) * 100) : 0,
        ]);
    }

    /**
     * "Today's PM Schedule" means what's planned for today — plan_date,
     * not due_date (due_date is the +14 day deadline, a different concept).
     */
    private function todaySchedule(User $user, Carbon $now, ?string $area): Collection
    {
        return $this->scoped($user, $area)
            ->whereDate('plan_date', $now->toDateString())
            ->orderBy('area')
            ->get(['machine_number', 'area', 'pic', 'due_date', 'status'])
            ->map(fn ($pm) => [
                'machine' => $pm->machine_number,
                'area' => $pm->area,
                'pic' => $pm->pic ? Str::title(strtolower($pm->pic)) : '-',
                'due_date' => Carbon::parse($pm->due_date)->format('Y-m-d'),
                'status' => match ($pm->status) {
                    'FINISHED', 'FINISHED_ON_TIME' => 'Completed',
                    'MISSED' => 'Overdue',
                    default => 'Pending',
                },
            ]);
    }

    /**
     * Uses MIN/MAX (portable across MySQL and SQLite) instead of a
     * database-specific YEAR() extraction, same technique as
     * GreasingReportController::index()'s year filter options.
     */
    private function availableYears(User $user, int $selectedYear): array
    {
        $minPlanDate = $this->scoped($user)->min('plan_date');
        $maxPlanDate = $this->scoped($user)->max('plan_date');

        $years = $minPlanDate && $maxPlanDate
            ? range((int) Carbon::parse($maxPlanDate)->format('Y'), (int) Carbon::parse($minPlanDate)->format('Y'))
            : [];

        if (! in_array($selectedYear, $years, true)) {
            $years[] = $selectedYear;
        }

        rsort($years);

        return $years;
    }

    /**
     * Current-month Greasing KPI, using the exact same formula/visibility
     * rule as GreasingReportController::index() (GreasingKpiCalculator +
     * PIC-name scoping) so the dashboard can never disagree with the
     * Greasing Report page. Koordinator/Admin see all groups, same as the
     * report — Greasing has no area-based scoping today.
     */
    private function greasingSummary(User $user, Carbon $now): array
    {
        $statusCounts = Greasing::query()
            ->whereYear('plan_date', $now->year)
            ->whereMonth('plan_date', $now->month)
            ->when($user->isPic(), fn ($q) => $q->where('pic', $user->name))
            ->select('status')
            ->selectRaw('count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return GreasingKpiCalculator::fromStatusCounts($statusCounts);
    }

    /**
     * Oil Audit summary using the same 3 metrics and query shape as
     * OilAuditController::report()'s $summary (today / pending follow-up /
     * unresolved critical). Returns null for roles that have no access to
     * the Oil Audit module at all (BUL), so the dashboard section is
     * hidden rather than shown empty.
     */
    private function oilAuditSummary(User $user, ?string $area): ?array
    {
        if (! in_array($user->role, [User::ROLE_ADMIN, User::ROLE_KOORDINATOR_WWD, User::ROLE_PIC_WWD], true)) {
            return null;
        }

        // Oil Audit is WWD-only — if the global filter is explicitly set to
        // BUL, there is nothing relevant to show.
        if ($area === 'BUL') {
            return null;
        }

        $base = fn () => OilAudit::where('area', self::OIL_AUDIT_AREA)
            ->whereIn('machine_type', self::OIL_AUDIT_MACHINE_TYPES);

        return [
            'today' => $base()->whereDate('audited_at', today())->count(),
            'pending' => $base()->requiringFollowUp()->whereDoesntHave('followUp')->count(),
            'critical' => $base()->where('condition', 'KRITIS')->whereDoesntHave('followUp')->count(),
            'machines_audited' => $base()->distinct('machine_id')->count('machine_id'),
            'total_machines' => Machine::where('area', self::OIL_AUDIT_AREA)
                ->whereIn('machine_type', self::OIL_AUDIT_MACHINE_TYPES)
                ->count(),
        ];
    }

    /**
     * Same role scope as scoped(), but applied to an already-built query
     * (used when scoping through a whereHas() on a related model). $area is
     * the ADMIN-only global area filter — every other role is already
     * fixed to one area/pic and ignores it.
     */
    private function applyScopeTo(Builder $query, User $user, ?string $area = null): Builder
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
}
