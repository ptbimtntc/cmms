<?php

namespace App\Services;

use Illuminate\Support\Collection;

/**
 * Scoring for the Kedisiplinan PIC report: each PicDisciplineEntry counts as
 * 1 point when ACHIEVED (on time) and 0 when BREACH (late); PENDING entries
 * (not yet due) are excluded from both the count and the percentage so an
 * in-progress quarter doesn't look artificially bad.
 */
class PicDisciplineKpiCalculator
{
    public static function fromCounts(int $achieved, int $breach): array
    {
        $total = $achieved + $breach;

        return [
            'achieved' => $achieved,
            'breach' => $breach,
            'total' => $total,
            'percentage' => $total > 0 ? round($achieved / $total * 100, 2) : 0.0,
        ];
    }

    /**
     * @param  iterable<int, string>  $statusCounts  e.g. ['ACHIEVED' => 9, 'BREACH' => 2]
     */
    public static function fromStatusCounts(iterable $statusCounts): array
    {
        $counts = collect($statusCounts);

        return self::fromCounts(
            (int) ($counts->get('ACHIEVED') ?? 0),
            (int) ($counts->get('BREACH') ?? 0),
        );
    }

    /**
     * Achieved/Breach/% per PIC per item type — the "Excel 9A 2B 82%"-style
     * row in the report matrix.
     *
     * @param  Collection<int, \App\Models\PicDisciplineEntry>  $entries
     * @return array<int, array<int, array>> keyed by [pic_user_id][item_type_id]
     */
    public static function perItemByPic(Collection $entries): array
    {
        return $entries
            ->groupBy('pic_user_id')
            ->map(fn (Collection $picEntries) => $picEntries
                ->groupBy('item_type_id')
                ->map(fn (Collection $itemEntries) => self::fromStatusCounts($itemEntries->countBy('status')))
                ->all())
            ->all();
    }

    /**
     * Overall Achieved/Breach/% per PIC across every item type — the
     * "Average" column in the report matrix.
     *
     * @param  Collection<int, \App\Models\PicDisciplineEntry>  $entries
     * @return array<int, array>
     */
    public static function overallByPic(Collection $entries): array
    {
        return $entries
            ->groupBy('pic_user_id')
            ->map(fn (Collection $picEntries) => self::fromStatusCounts($picEntries->countBy('status')))
            ->all();
    }
}
