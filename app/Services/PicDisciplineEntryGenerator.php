<?php

namespace App\Services;

use App\Models\PicDisciplineEntry;
use App\Models\PicDisciplineItemType;
use App\Models\PMChecklist;
use App\Models\PMSchedule;
use App\Models\User;
use Carbon\Carbon;

/**
 * Creates the Kedisiplinan PIC administration entries (one per active
 * PicDisciplineItemType) the moment a PM Schedule's actual_date is set —
 * called from PMChecklistSaveService::save() right after updateStatus().
 * Idempotent: re-saving the same PM's checklist never duplicates entries,
 * it only (re)computes the auto FILL_PM entry so a correction to actual_date
 * is reflected.
 */
class PicDisciplineEntryGenerator
{
    public function generateFor(PMSchedule $pmSchedule): void
    {
        if (! $pmSchedule->actual_date) {
            return;
        }

        $actualDate = Carbon::parse($pmSchedule->actual_date);

        $picUserId = User::query()
            ->where('role', User::ROLE_PIC)
            ->where('name', $pmSchedule->pic)
            ->value('id');

        foreach (PicDisciplineItemType::where('is_active', true)->get() as $itemType) {
            $entry = PicDisciplineEntry::firstOrNew([
                'pm_schedule_id' => $pmSchedule->id,
                'item_type_id' => $itemType->id,
            ]);

            $entry->pic_user_id = $picUserId;
            $entry->due_date = $actualDate->clone()->addDays($itemType->grace_days);

            if ($itemType->is_auto) {
                $entry->source = PicDisciplineEntry::SOURCE_AUTO;
                $entry->completed_date = PMChecklist::where('pm_schedule_id', $pmSchedule->id)
                    ->oldest('created_at')
                    ->value('created_at');
            } elseif (! $entry->exists) {
                $entry->source = PicDisciplineEntry::SOURCE_MANUAL;
            }

            // Re-run for every entry (new or existing) so a shifted due_date
            // (actual_date corrected on a re-save) keeps a manual entry's
            // Pending/Breach verdict in sync, same as the auto one.
            $entry->recomputeStatus();

            $entry->save();
        }
    }
}
