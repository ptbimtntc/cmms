<?php

namespace App\Services;

use App\Models\Machine;
use App\Models\PMProblem;
use App\Models\PMSchedule;
use App\Models\PMSparepart;
use App\Models\Sparepart;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

/**
 * Single source of truth for the public, no-login PM figures shown on the
 * landing page and the guest dashboard preview. Both pages must read the
 * same numbers for the same period, so the query lives here once.
 *
 * Every figure is scoped to the running month only (bulan berjalan), keyed
 * off PMSchedule.plan_date, matching DashboardController::monthKpi(). The
 * cumulative totals (machines, PM records, spareparts) are all-time counts,
 * cached separately because they change far less often.
 */
class PublicPmOverview
{
    /**
     * This month's figures: planned, overdue (MISSED), problems recorded,
     * sparepart quantity used, and the on-time completion ratio from
     * PMReportKpiCalculator. Cached 10 minutes; on query failure returns
     * null so the caller can render an honest error state instead of
     * zeroes.
     */
    public static function monthly(): ?array
    {
        try {
            return Cache::remember('landing.pm-overview.monthly', 600, function () {
                $now = Carbon::now();

                $thisMonth = fn (Builder $query) => $query
                    ->whereYear('plan_date', $now->year)
                    ->whereMonth('plan_date', $now->month);

                $statusCounts = PMSchedule::query()
                    ->tap($thisMonth)
                    ->selectRaw('status, count(*) as total')
                    ->groupBy('status')
                    ->pluck('total', 'status');

                $kpi = PMReportKpiCalculator::fromStatusCounts($statusCounts);

                $problems = PMProblem::query()
                    ->whereHas('pmSchedule', $thisMonth)
                    ->count();

                $sparepartUsage = (int) PMSparepart::query()
                    ->whereHas('pmSchedule', $thisMonth)
                    ->sum('qty');

                return [
                    'period_label' => $now->translatedFormat('F Y'),
                    'planned' => $kpi['total'],
                    'overdue' => $kpi['missed'],
                    'problems' => $problems,
                    'sparepart_usage' => $sparepartUsage,
                    'closing_percent' => $kpi['closing_percent'],
                ];
            });
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    /**
     * All-time totals: machines managed, PM records with a real actual_date
     * (i.e. PMs that actually happened, not just scheduled), and spareparts
     * in the master list. Same cache key and TTL the old statistics.blade.php
     * section used, so this is a lossless move, not a new query.
     */
    public static function cumulative(): ?array
    {
        try {
            return Cache::remember('landing.overview', 600, fn () => [
                'machines' => Machine::count(),
                'pm' => PMSchedule::whereNotNull('actual_date')->count(),
                'spareparts' => Sparepart::count(),
            ]);
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }
}
