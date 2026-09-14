<?php

namespace App\Services;

use App\Http\Controllers\Concerns\HandlesActivityConflict;
use App\Models\PMSchedule;
use App\Models\User;
use App\Support\Activities\ActiveActivity;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

/**
 * PM_START — extracted verbatim from PMScheduleController::start() so the
 * business logic can be reused by both the existing online controller and
 * the offline sync handler without duplicating the rule (see
 * docs/tasks FreeDOMS Phase 1, Task 2 section 7).
 *
 * The exact order of checks from the original method is preserved:
 * validate started_at -> already finished? -> already started (idempotent
 * no-op)? -> one-active-activity-per-PIC conflict -> persist. Callers
 * (online controller, sync handler) only translate the returned outcome
 * into their own response shape (redirect+flash vs. JSON) — no business
 * rule is re-implemented at the call site.
 */
class PMStartService
{
    use HandlesActivityConflict;

    public const OUTCOME_ALREADY_FINISHED = 'already_finished';

    public const OUTCOME_ALREADY_STARTED = 'already_started';

    public const OUTCOME_ACTIVITY_CONFLICT = 'activity_conflict';

    public const OUTCOME_STARTED = 'started';

    /**
     * @return array{
     *     outcome: string,
     *     pm_schedule: PMSchedule,
     *     started_at?: CarbonInterface,
     *     requested_started_at?: CarbonInterface,
     *     conflict?: ActiveActivity,
     * }
     */
    public function start(PMSchedule $pmSchedule, User $user, ?string $startedAtRaw, bool $confirmEndStart): array
    {
        if (in_array($pmSchedule->status, PMSchedule::DONE_STATUSES, true)) {
            return ['outcome' => self::OUTCOME_ALREADY_FINISHED, 'pm_schedule' => $pmSchedule];
        }

        // Idempotent: keep the original start time instead of overwriting.
        if (filled($pmSchedule->start_time)) {
            return ['outcome' => self::OUTCOME_ALREADY_STARTED, 'pm_schedule' => $pmSchedule];
        }

        $validated = Validator::make(
            ['started_at' => $startedAtRaw],
            ['started_at' => ['required', 'date']]
        )->validate();

        $startedAt = Carbon::parse($validated['started_at']);

        // One active activity per PIC — checked across every activity source
        // (PM, Greasing, Oil Audit, Oil Audit Action). Unless the PIC has
        // already confirmed END & START, bounce back with the confirmation
        // payload instead of starting.
        if (! $confirmEndStart) {
            $current = $this->activityConflictFor($user);

            if ($current) {
                return [
                    'outcome' => self::OUTCOME_ACTIVITY_CONFLICT,
                    'pm_schedule' => $pmSchedule,
                    'requested_started_at' => $startedAt,
                    'conflict' => $current,
                ];
            }
        } else {
            $startedAt = $this->confirmedStartTime($user, $startedAt);
        }

        $pmSchedule->update([
            'actual_date' => $startedAt->toDateString(),
            'start_time' => $startedAt->format('H:i'),
            'status' => $pmSchedule->status === 'OPEN'
                ? 'IN_PROGRESS'
                : $pmSchedule->status,
        ]);

        return [
            'outcome' => self::OUTCOME_STARTED,
            'pm_schedule' => $pmSchedule,
            'started_at' => $startedAt,
        ];
    }
}
