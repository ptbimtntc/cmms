<?php

namespace App\Http\Controllers\Concerns;

use App\Models\User;
use App\Services\ActiveActivityResolver;
use App\Support\Activities\ActiveActivity;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Shared "one active activity per PIC" guard for every Start Activity
 * endpoint (PM, Greasing, Oil Audit, Oil Audit Action).
 *
 * Flow:
 *  1. Start endpoint validates `started_at` as usual.
 *  2. Unless the request carries `confirm_end_start=1`, it asks
 *     {@see activityConflictFor()} whether the PIC already has an active
 *     activity. If so it flashes {@see activityConflictPayload()} as
 *     `activity_conflict` and redirects back — the shared
 *     `partials.activity-conflict-modal` renders the CANCEL / END & START
 *     confirmation.
 *  3. END & START re-POSTs the same `started_at` with `confirm_end_start=1`.
 *     The check is skipped and the new activity is started normally; the
 *     previous one simply stops being "active" because the new start is
 *     later (newest start wins — see ActiveActivityResolver). No source
 *     module status is changed: closing an activity is NOT completing work.
 */
trait HandlesActivityConflict
{
    protected function activityConflictFor(User $user): ?ActiveActivity
    {
        return app(ActiveActivityResolver::class)->currentFor($user);
    }

    /**
     * @return array<string, mixed>
     */
    protected function activityConflictPayload(ActiveActivity $current, string $action, CarbonInterface $startedAt): array
    {
        return [
            'pic' => $current->picName,
            'current' => $current->label,
            'machine' => $current->machineNumber,
            'started_at' => $current->startedAt->format('d M Y H:i'),
            'action' => $action,
            'resume_started_at' => $startedAt->format('Y-m-d\TH:i'),
        ];
    }

    /**
     * On END & START, guarantee the new activity really becomes the current
     * one: if the PIC hand-picked a start time earlier than the activity
     * they are ending, fall back to now() so "newest start wins" holds and
     * Today's Activity reads the new activity as active.
     */
    protected function confirmedStartTime(User $user, CarbonInterface $requested): CarbonInterface
    {
        $current = $this->activityConflictFor($user);

        return $current && $requested->lessThan($current->startedAt)
            ? Carbon::now()
            : $requested;
    }
}
