<?php

namespace App\Services;

class GreasingKpiCalculator
{
    /**
     * Single source of truth for the Closing % and Completion % formulas.
     * Every KPI card, chart, and table on the Greasing Report must go
     * through this method so they never drift out of sync.
     *
     * Closing %    = (FINISH ON TIME + FINISH) / Total * 100
     * Completion % = (FINISH ON TIME * 1 + FINISH * 0.5) / Total * 100
     */
    public static function fromCounts(int $finishOnTime, int $finish, int $open): array
    {
        $total = $finishOnTime + $finish + $open;

        return [
            'total' => $total,
            'finish_on_time' => $finishOnTime,
            'finish' => $finish,
            'open' => $open,
            'closing_percent' => $total > 0
                ? round((($finishOnTime + $finish) / $total) * 100, 2)
                : 0.0,
            'completion_percent' => $total > 0
                ? round((($finishOnTime * 1 + $finish * 0.5) / $total) * 100, 2)
                : 0.0,
        ];
    }

    /**
     * Convenience wrapper around a status => count map, e.g. the result of
     * Greasing::query()->groupBy('status')->pluck('total', 'status').
     */
    public static function fromStatusCounts(iterable $statusCounts): array
    {
        $counts = collect($statusCounts);

        return self::fromCounts(
            (int) ($counts->get('FINISH ON TIME') ?? 0),
            (int) ($counts->get('FINISH') ?? 0),
            (int) ($counts->get('OPEN') ?? 0)
        );
    }
}
