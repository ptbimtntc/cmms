<?php

namespace App\Http\Controllers\Concerns;

use App\Models\ActivityMonitorClosure;
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
 *     The check is skipped, {@see confirmedStartTime()} explicitly closes
 *     the previous activity (an activity_monitor_closures row, same
 *     mechanism the control panel's "Finish" uses) and the new activity is
 *     started normally. No source module status is changed: closing an
 *     activity is NOT completing work.
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
     * On END & START: explicitly closes the activity being ended (so it
     * cannot silently resurface as "active" again later — see below — the
     * way relying on "newest start wins" alone allowed), then guarantees
     * the new activity really becomes the current one: if the PIC
     * hand-picked a start time earlier than the activity they are ending,
     * fall back to now() so Today's Activity reads the new activity as
     * active.
     *
     * Without this, the previous activity's own record was left completely
     * untouched — correct for the module data, but ALSO untouched in the
     * monitor, so it only stopped being "current" because its start
     * timestamp was older. If the NEW activity was later finished from the
     * control panel, {@see ActiveActivityResolver::currentFor()} would then
     * fall back to that old, never-actually-closed activity and show it as
     * active again — e.g. Oil Audit -> END & START into Oil Audit Action ->
     * admin finishes Oil Audit Action -> Oil Audit incorrectly reappears.
     * Writing a real closure here (identical to the control panel's
     * "Finish") makes END & START end the old activity for good.
     */
    protected function confirmedStartTime(User $user, CarbonInterface $requested): CarbonInterface
    {
        $current = $this->activityConflictFor($user);

        if ($current) {
            ActivityMonitorClosure::updateOrCreate(
                [
                    'source' => $current->source,
                    'source_key' => (string) ($current->recordId ?? $user->id),
                    'business_date' => today(),
                ],
                [
                    'pic_user_id' => $user->id,
                    'closed_by_user_id' => $user->id,
                    'closed_at' => now(),
                ]
            );
        }

        return $current && $requested->lessThan($current->startedAt)
            ? Carbon::now()
            : $requested;
    }
}
