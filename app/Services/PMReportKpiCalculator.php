<?php

namespace App\Services;

class PMReportKpiCalculator
{
    /**
     * Single source of truth for the PM Report summary KPIs. Every summary
     * card and any future PM Report chart must go through this method so
     * they never drift out of sync.
     *
     * Closing %    = (FINISHED + FINISHED_ON_TIME) / Total * 100
     *   — the PM completion formula already established by
     *   DashboardController::monthKpi()/completionTrend(): a closed PM
     *   counts the same whether it finished on time or late.
     *
     * Completion % = (FINISHED_ON_TIME * 1 + FINISHED * 0.5) / Total * 100
     *   — an on-time-weighted variant for the PM Report: a late completion
     *   only earns half credit. MISSED never counts toward either
     *   numerator, but is still part of Total.
     */
    public static function fromCounts(int $open, int $inProgress, int $finished, int $finishedOnTime, int $missed): array
    {
        $total = $open + $inProgress + $finished + $finishedOnTime + $missed;

        return [
            'total' => $total,
            'open' => $open,
            'in_progress' => $inProgress,
            'finished' => $finished,
            'finished_on_time' => $finishedOnTime,
            'missed' => $missed,
            'closing_percent' => $total > 0
                ? round(($finished + $finishedOnTime) / $total * 100, 2)
                : 0.0,
            'completion_percent' => $total > 0
                ? round((($finishedOnTime * 1) + ($finished * 0.5)) / $total * 100, 2)
                : 0.0,
        ];
    }

    /**
     * Convenience wrapper around a status => count map, e.g. the result of
     * PMSchedule::query()->groupBy('status')->pluck('total', 'status').
     */
    public static function fromStatusCounts(iterable $statusCounts): array
    {
        $counts = collect($statusCounts);

        return self::fromCounts(
            (int) ($counts->get('OPEN') ?? 0),
            (int) ($counts->get('IN_PROGRESS') ?? 0),
            (int) ($counts->get('FINISHED') ?? 0),
            (int) ($counts->get('FINISHED_ON_TIME') ?? 0),
            (int) ($counts->get('MISSED') ?? 0)
        );
    }
}
