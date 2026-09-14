<?php

namespace App\Services;

use App\Models\PMChecklist;
use App\Models\PMSchedule;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * PM checklist save — extracted verbatim from
 * PMScheduleController::saveChecklist() / updatePMStatus() /
 * validatePMCompleted() so the business logic can later be reused by an
 * offline sync replay without duplicating the rule.
 *
 * The existing Fill PM UX saves measurements/problems/spareparts (see
 * PMScheduleSaveService) and the checklist as two separate steps/requests.
 * This extraction intentionally preserves that behavior rather than
 * merging them into a single transaction.
 *
 * Matches the original method's exact (non-atomic) shape: only the
 * checklist delete/recreate runs inside DB::transaction(); the
 * actual_date-from-last-session update and the status recalculation run
 * afterwards, same as before.
 */
class PMChecklistSaveService
{
    /**
     * Fields required before a PM checklist may be saved. Read-only check,
     * safe to reuse for offline validation too.
     *
     * @return array<int, string>
     */
    public function completionErrors(PMSchedule $pmSchedule): array
    {
        $errors = [];

        if ($pmSchedule->requiresOilChange() && blank($pmSchedule->oil_change)) {
            $errors[] = 'Oil Change';
        }

        if (blank($pmSchedule->greasing)) {
            $errors[] = 'Greasing';
        }

        if (blank($pmSchedule->wo_zsbp)) {
            $errors[] = 'WO ZSBP';
        }

        if (blank($pmSchedule->remarks)) {
            $errors[] = 'Remarks';
        }

        if (! $pmSchedule->problems()->exists()) {
            $errors[] = 'Problem';
        }

        if (! $pmSchedule->measurements()->exists()) {
            $errors[] = 'Measurement';
        }

        if (! $pmSchedule->spareparts()->exists()) {
            $errors[] = 'Sparepart';
        }

        return $errors;
    }

    /**
     * @param  array<int, array<string, mixed>>  $checklists
     */
    public function save(PMSchedule $pmSchedule, array $checklists): void
    {
        DB::transaction(function () use ($checklists, $pmSchedule) {

            // hapus checklist lama jika ada
            PMChecklist::where(
                'pm_schedule_id',
                $pmSchedule->id
            )->delete();

            foreach ($checklists as $item) {

                PMChecklist::create([

                    'pm_schedule_id' => $pmSchedule->id,

                    'machine_checklist_id' => $item['machine_checklist_id'],

                    'clean' => $item['clean'] ?? 'NO',

                    'lubrication' => $item['lubrication'] ?? 'NO',

                    'replace' => $item['replace'] ?? 'NO',

                    'check' => $item['check'] ?? 'NO',

                    'remarks' => $item['remarks'] ?? null,

                ]);

            }

        });

        // If there are work sessions, set completion date to last session's date when checklist is saved
        $lastSessionDate = $pmSchedule->workSessions()->latest('actual_date')->value('actual_date');
        if ($lastSessionDate) {
            $pmSchedule->update([
                'actual_date' => $lastSessionDate,
            ]);
        }

        $pmSchedule->refresh();

        $this->updateStatus($pmSchedule);
    }

    public function updateStatus(PMSchedule $pmSchedule): void
    {

        // belum ada PM
        if (! $pmSchedule->actual_date) {

            if (now()->greaterThan($pmSchedule->due_date)) {

                $pmSchedule->update([
                    'status' => 'MISSED',
                ]);

            } else {

                $pmSchedule->update([
                    'status' => 'OPEN',
                ]);

            }

            return;
        }

        // cek apakah checklist sudah disimpan
        $hasChecklist = PMChecklist::where(
            'pm_schedule_id',
            $pmSchedule->id
        )->exists();

        if (! $hasChecklist) {

            $pmSchedule->update([
                'status' => 'IN_PROGRESS',
            ]);

            return;

        }

        // checklist sudah ada
        if (
            Carbon::parse($pmSchedule->actual_date)
                ->greaterThan(
                    Carbon::parse($pmSchedule->due_date)
                )
        ) {

            $pmSchedule->update([
                'status' => 'FINISHED',
            ]);

        } else {

            $pmSchedule->update([
                'status' => 'FINISHED_ON_TIME',
            ]);

        }

    }
}
