<?php

namespace App\Services;

use App\Models\ActivityMonitorClosure;
use App\Models\Greasing;
use App\Models\ManualActivity;
use App\Models\PMSchedule;
use App\Models\User;
use App\Support\Activities\ActiveActivity;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Single source of truth for "what activity does this PIC currently have
 * open?" across every supported activity source. The rule the whole app
 * relies on: a PIC may only ever have ONE active activity at a time.
 *
 * Everything here is DERIVED from existing records/columns — there is no
 * activity table:
 *
 *   PM               pm_schedules.start_time (the column START PM writes) with
 *                    actual_date = today, status not finished
 *   Greasing         greasings.start_time (timestamp), started today, status not finished
 *   Oil Audit        users.oil_audit_started_at (daily marker, today)
 *   Oil Audit Action users.oil_audit_action_started_at (daily marker, today)
 *   Manual Activity  manual_activities.started_at (owned by the user, today)
 *
 * "Started today" is enforced at SQL level against the source's real START
 * field, never inferred from actual_date/action_date/updated_at/audited_at
 * alone.
 *
 * Day rollover is also derived: an activity whose start date is not "today"
 * is simply not returned here. No source module status is ever changed.
 *
 * "Finish" (the Activity Control Panel) is monitoring-only: it writes a row
 * to activity_monitor_closures. Such an activity is still returned by
 * {@see forToday()} (so "Started Today" lists it as FINISHED) but is marked
 * $endedAt, so {@see currentFor()} skips it and it disappears from /monitor.
 * The source module (pm_schedules / greasings / users) is never touched.
 *
 * Technical limitation: PM and Greasing store the PIC as a name string
 * (`pic` column) with no user_id relation, so those two sources are matched
 * by `$user->name`. Oil Audit / Oil Audit Action / Manual use the user id.
 * Unifying the PIC model is out of scope (a repo-wide change).
 */
class ActiveActivityResolver
{
    /**
     * Every activity the PIC started TODAY, newest start first. Includes
     * activities finished from the control panel (marked $endedAt) so
     * "Started Today" can list them; use {@see currentFor()} for the one
     * that is actually active.
     *
     * @return Collection<int, ActiveActivity>
     */
    public function forToday(User $user): Collection
    {
        $closures = $this->closuresFor($user);

        return collect([
            ...$this->pmActivities($user, $closures),
            ...$this->greasingActivities($user, $closures),
            ...$this->oilAuditActivity($user, $closures),
            ...$this->oilAuditActionActivity($user, $closures),
            ...$this->manualActivities($user, $closures),
        ])
            ->sortByDesc(fn (ActiveActivity $a) => $a->startedAt->getTimestamp())
            ->values();
    }

    /**
     * The PIC's single current active activity, or null. An activity that
     * was finished on the monitor is skipped.
     */
    public function currentFor(User $user): ?ActiveActivity
    {
        return $this->forToday($user)
            ->reject(fn (ActiveActivity $a) => $a->isFinished())
            ->first();
    }

    /**
     * Today's monitor closures for this PIC, keyed "SOURCE:source_key".
     *
     * @return Collection<string, CarbonInterface>
     */
    private function closuresFor(User $user): Collection
    {
        return ActivityMonitorClosure::query()
            ->where('pic_user_id', $user->id)
            ->whereDate('business_date', today())
            ->get()
            ->mapWithKeys(fn (ActivityMonitorClosure $c) => [
                ActivityMonitorClosure::keyFor($c->source, $c->source_key) => $c->closed_at,
            ]);
    }

    /**
     * @param  Collection<string, CarbonInterface>  $closures
     * @return array<int, ActiveActivity>
     */
    private function pmActivities(User $user, Collection $closures): array
    {
        if (blank($user->name)) {
            return [];
        }

        return PMSchedule::query()
            ->activeActivity()                       // start_time set, status not finished
            ->where('pic', $user->name)
            ->whereNotNull('actual_date')            // a real start date, not just start_time
            ->whereDate('actual_date', today())      // started today (app timezone)
            ->get()
            ->map(function (PMSchedule $pm) use ($user, $closures): ?ActiveActivity {
                $startedAt = $pm->startedAt();

                if (! $startedAt || ! $startedAt->isToday()) {
                    return null;
                }

                return new ActiveActivity(
                    source: 'PM',
                    label: 'PM',
                    picName: $user->name,
                    startedAt: $startedAt,
                    machineNumber: $pm->machine_number,
                    recordId: $pm->id,
                    endedAt: $closures->get(ActivityMonitorClosure::keyFor('PM', $pm->id)),
                );
            })
            ->filter()
            ->all();
    }

    /**
     * @param  Collection<string, CarbonInterface>  $closures
     * @return array<int, ActiveActivity>
     */
    private function greasingActivities(User $user, Collection $closures): array
    {
        if (blank($user->name)) {
            return [];
        }

        return Greasing::query()
            ->activeActivity()                       // start_time set, status not finished
            ->where('pic', $user->name)
            ->whereDate('start_time', today())       // started today (app timezone)
            ->with('group:id,name')
            ->get()
            ->map(function (Greasing $greasing) use ($user, $closures): ?ActiveActivity {
                $startedAt = $greasing->start_time;

                if (! $startedAt || ! $startedAt->isToday()) {
                    return null;
                }

                // Greasing is group-level, not machine-level.
                return new ActiveActivity(
                    source: 'GREASING',
                    label: 'Greasing',
                    picName: $user->name,
                    startedAt: $startedAt,
                    machineNumber: null,
                    recordId: $greasing->id,
                    groupName: $greasing->group?->name,
                    endedAt: $closures->get(ActivityMonitorClosure::keyFor('GREASING', $greasing->id)),
                );
            })
            ->filter()
            ->all();
    }

    /**
     * @param  Collection<string, CarbonInterface>  $closures
     * @return array<int, ActiveActivity>
     */
    private function oilAuditActivity(User $user, Collection $closures): array
    {
        if (! $user->hasStartedOilAuditToday()) {
            return [];
        }

        return [new ActiveActivity(
            source: 'OIL_AUDIT',
            label: 'Oil Audit',
            picName: $user->name,
            startedAt: $user->oil_audit_started_at,
            recordId: null,
            endedAt: $closures->get(ActivityMonitorClosure::keyFor('OIL_AUDIT', $user->id)),
        )];
    }

    /**
     * @param  Collection<string, CarbonInterface>  $closures
     * @return array<int, ActiveActivity>
     */
    private function oilAuditActionActivity(User $user, Collection $closures): array
    {
        if (! $user->hasStartedOilAuditActionToday()) {
            return [];
        }

        return [new ActiveActivity(
            source: 'OIL_AUDIT_ACTION',
            label: 'Oil Audit Action',
            picName: $user->name,
            startedAt: $user->oil_audit_action_started_at,
            recordId: null,
            endedAt: $closures->get(ActivityMonitorClosure::keyFor('OIL_AUDIT_ACTION', $user->id)),
        )];
    }

    /**
     * @param  Collection<string, CarbonInterface>  $closures
     * @return array<int, ActiveActivity>
     */
    private function manualActivities(User $user, Collection $closures): array
    {
        return ManualActivity::query()
            ->startedOnFor($user->id)
            ->get()
            ->map(fn (ManualActivity $manual) => new ActiveActivity(
                source: 'MANUAL',
                label: 'Manual Activity',
                picName: $user->name,
                startedAt: $manual->started_at,
                title: $manual->name,
                machineNumber: $manual->machine_number,
                recordId: $manual->id,
                endedAt: $closures->get(ActivityMonitorClosure::keyFor('MANUAL', $manual->id)),
            ))
            ->all();
    }
}
