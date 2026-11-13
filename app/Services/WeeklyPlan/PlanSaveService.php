<?php

namespace App\Services\WeeklyPlan;

use App\Models\PMSchedule;
use App\Models\User;
use App\Models\WeeklyPmPlan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Replaces a plan's placed machines with the arrangement sent by the board.
 * Order inside each day follows the order of the payload.
 */
class PlanSaveService
{
    public function __construct(
        private readonly PlanBoardService $board,
        private readonly PicSyncService $picSync,
    ) {}

    /**
     * @param  list<array{pm_schedule_id: int, day: string, pic?: ?string, construction_note?: ?string}>  $rows
     * @return int PM Schedule PICs given back because their machine left the plan
     */
    public function save(WeeklyPmPlan $plan, array $rows, User $user): int
    {
        $days = array_map(fn ($d) => $d->toDateString(), $plan->workDays());
        $pics = $this->board->picNames($plan->area);
        $ids = array_map(fn ($r) => (int) $r['pm_schedule_id'], $rows);

        if (count($ids) !== count(array_unique($ids))) {
            throw ValidationException::withMessages(['items' => 'A PM Schedule may be planned only once in a week.']);
        }

        $schedules = PMSchedule::query()
            ->whereIn('id', $ids)
            ->where('area', $plan->area)
            ->get()
            ->keyBy('id');

        foreach ($rows as $i => $row) {
            if (! $schedules->has((int) $row['pm_schedule_id'])) {
                throw ValidationException::withMessages(["items.$i.pm_schedule_id" => 'PM Schedule not found in area '.$plan->area.'.']);
            }

            if (! in_array($row['day'], $days, true)) {
                throw ValidationException::withMessages(["items.$i.day" => 'The day must be Monday to Friday of this week.']);
            }

            if (filled($row['pic'] ?? null) && ! in_array($row['pic'], $pics, true)) {
                throw ValidationException::withMessages(["items.$i.pic" => $row['pic'].' bukan PIC area '.$plan->area.'.']);
            }
        }

        DB::transaction(function () use ($plan, $rows, $schedules, $user) {
            $plan->items()->delete();
            $orderPerDay = [];

            foreach ($rows as $row) {
                $schedule = $schedules[(int) $row['pm_schedule_id']];
                $orderPerDay[$row['day']] = ($orderPerDay[$row['day']] ?? 0) + 1;

                $plan->items()->create([
                    'pm_schedule_id' => $schedule->id,
                    'machine_id' => $schedule->machine_id,
                    'machine_number' => $schedule->machine_number,
                    'machine_type' => $schedule->machine_type,
                    'day' => $row['day'],
                    'pic' => filled($row['pic'] ?? null) ? $row['pic'] : null,
                    'construction_note' => filled($row['construction_note'] ?? null) ? trim($row['construction_note']) : null,
                    'sort_order' => $orderPerDay[$row['day']],
                ]);
            }

            $plan->update(['updated_by' => $user->id]);
        });

        $plan->unsetRelation('items');

        // Machines taken out of the plan give their PM Schedule PIC back now.
        $released = $this->picSync->releaseRemoved($plan);
        $plan->unsetRelation('items');

        return $released;
    }
}
