<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesActivityConflict;
use App\Models\ActivityMonitorClosure;
use App\Models\ManualActivity;
use App\Models\PicAvailability;
use App\Models\User;
use App\Services\ActiveActivityResolver;
use App\Support\Activities\ActiveActivity;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Activity Control Panel.
 *
 * For ADMIN / KOORDINATOR it shows every activity started today across ALL
 * of their PICs and ALL sources (PM, Greasing, Oil Audit, Oil Audit Action,
 * Manual) — read from ActiveActivityResolver, area-scoped. A plain PIC sees
 * only their own.
 *
 * Actions (ADMIN / KOORDINATOR, area-scoped, server-side authorized):
 *  - Finish — available on EVERY activity. It only writes an
 *    activity_monitor_closures row so the activity leaves /monitor; it never
 *    changes the source module's status / dates / completion.
 *  - Edit — Manual Activity only (its own name / location / start time).
 *  - PM / Greasing / Oil Audit / Oil Audit Action also get a deep-link to
 *    the module's own screen for the real work.
 */
class TodayActivityController extends Controller
{
    use HandlesActivityConflict;

    public function index(Request $request): View
    {
        $user = $request->user();
        $canManage = $user->isAdmin() || $user->isKoordinator();
        $resolver = app(ActiveActivityResolver::class);

        $pics = $canManage
            ? $this->assignablePics($user)
            : collect([$user]);

        $inactiveByUser = PicAvailability::query()
            ->whereIn('user_id', $pics->pluck('id'))
            ->whereDate('date', today())
            ->get()
            ->keyBy('user_id');

        $rows = collect();
        $picStatuses = collect();

        foreach ($pics as $pic) {
            $inactive = $inactiveByUser->get($pic->id);
            $current = $inactive ? null : $resolver->currentFor($pic);

            if (! $inactive) {
                foreach ($resolver->forToday($pic) as $activity) {
                    $rows->push([
                        'pic' => $pic,
                        'activity' => $activity,
                        'isActive' => $activity->sameAs($current),
                        'moduleLink' => $this->moduleLinkFor($activity),
                        'monitorKey' => (string) ($activity->recordId ?? $pic->id),
                    ]);
                }
            }

            $picStatuses->push([
                'pic' => $pic,
                'status' => $inactive ? 'INACTIVE' : ($current ? 'ACTIVE' : 'NOT STARTED'),
                'availability' => $inactive,
            ]);
        }

        $rows = $rows
            ->sortBy(fn (array $r) => [$r['isActive'] ? 0 : 1, -$r['activity']->startedAt->getTimestamp()])
            ->values();

        return view('today-activity.index', [
            'rows' => $rows,
            'activeRows' => $rows->where('isActive', true)->values(),
            'picStatuses' => $picStatuses,
            'canManage' => $canManage,
            'assignablePics' => $canManage ? $pics : collect(),
            'inactiveReasons' => PicAvailability::REASONS,
        ]);
    }

    /**
     * Start a manual activity for a PIC. ADMIN / KOORDINATOR only; a
     * KOORDINATOR may only target PICs in their own area. Runs the same
     * one-active-activity-per-PIC conflict check as every other Start
     * endpoint — checked against the TARGET pic, not the actor.
     */
    public function storeManual(Request $request): RedirectResponse
    {
        $actor = $request->user();

        abort_unless($actor->isAdmin() || $actor->isKoordinator(), 403);

        $validated = $request->validate([
            'user_id' => ['required', 'integer'],
            'name' => ['required', 'string', 'max:255'],
            'machine_number' => ['nullable', 'string', 'max:255'],
            'started_at' => ['required', 'date'],
        ]);

        $target = $this->authorizedTarget($actor, (int) $validated['user_id']);

        $machineNumber = trim((string) ($validated['machine_number'] ?? '')) ?: null;
        $startedAt = Carbon::parse($validated['started_at']);

        if (! $request->boolean('confirm_end_start')) {
            $current = $this->activityConflictFor($target);

            if ($current) {
                return back()->with('activity_conflict', array_merge(
                    $this->activityConflictPayload($current, route('today-activity.manual.store'), $startedAt),
                    ['extra_fields' => [
                        'user_id' => $target->id,
                        'name' => $validated['name'],
                        'machine_number' => $machineNumber ?? '',
                    ]],
                ))->withInput();
            }
        } else {
            $startedAt = $this->confirmedStartTime($target, $startedAt);
        }

        ManualActivity::create([
            'user_id' => $target->id,
            'name' => $validated['name'],
            'machine_number' => $machineNumber,
            'started_at' => $startedAt,
        ]);

        return back()->with('success', 'Manual activity "'.$validated['name'].'" started for '.$target->name.'.');
    }

    /**
     * Edit a manual activity (name / location / start time). ADMIN /
     * KOORDINATOR only, and only for a PIC in their scope.
     */
    public function updateManual(Request $request, ManualActivity $manualActivity): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor->isAdmin() || $actor->isKoordinator(), 403);
        $this->authorizedTarget($actor, $manualActivity->user_id);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'machine_number' => ['nullable', 'string', 'max:255'],
            'started_at' => ['required', 'date'],
        ]);

        $manualActivity->update([
            'name' => $validated['name'],
            'machine_number' => trim((string) ($validated['machine_number'] ?? '')) ?: null,
            'started_at' => Carbon::parse($validated['started_at']),
        ]);

        return back()->with('success', 'Manual activity updated.');
    }

    /**
     * Finish ANY activity — monitoring only. Writes an
     * activity_monitor_closures row so the activity leaves /monitor. It
     * never touches pm_schedules / greasings / users / manual_activities:
     * the module's status, dates and completion are unchanged.
     */
    public function finishActivity(Request $request): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor->isAdmin() || $actor->isKoordinator(), 403);

        $validated = $request->validate([
            'source' => ['required', Rule::in(['PM', 'GREASING', 'OIL_AUDIT', 'OIL_AUDIT_ACTION', 'MANUAL'])],
            'source_key' => ['required', 'string', 'max:255'],
            'pic_user_id' => ['required', 'integer'],
        ]);

        $target = $this->authorizedTarget($actor, (int) $validated['pic_user_id']);

        // Only close something that is genuinely one of this PIC's activities today.
        $resolver = app(ActiveActivityResolver::class);
        $match = $resolver->forToday($target)->first(
            fn (ActiveActivity $a) => $a->source === $validated['source']
                && (string) ($a->recordId ?? $target->id) === $validated['source_key']
        );

        if ($match === null) {
            return back()->with('warning', 'That activity is no longer active.');
        }
        if ($match->isFinished()) {
            return back()->with('warning', 'This activity is already finished.');
        }

        // updateOrCreate, not create: Oil Audit / Oil Audit Action have no
        // per-instance row, so source_key is just the PIC's id — if the PIC
        // was finished earlier today and later restarted the same source, a
        // row for (source, source_key, business_date) already exists (the
        // unique index enforces one closure per source per PIC per day).
        // Bumping closed_at to now() here is exactly what ActiveActivity::
        // isFinished() needs to correctly treat THIS later instance as
        // finished too, instead of colliding on insert.
        ActivityMonitorClosure::updateOrCreate(
            [
                'source' => $validated['source'],
                'source_key' => $validated['source_key'],
                'business_date' => today(),
            ],
            [
                'pic_user_id' => $target->id,
                'closed_by_user_id' => $actor->id,
                'closed_at' => now(),
            ]
        );

        return back()->with('success', 'Activity removed from the monitor. The '.strtolower(str_replace('_', ' ', $validated['source'])).' work is unchanged.');
    }

    /**
     * Mark a PIC INACTIVE for today (Cuti / Sakit / …), or update the reason
     * of an existing marker. ADMIN / KOORDINATOR only, area-scoped. INACTIVE
     * is NOT an activity: it changes nothing in any module.
     */
    public function setInactive(Request $request): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor->isAdmin() || $actor->isKoordinator(), 403);

        $validated = $request->validate([
            'user_id' => ['required', 'integer'],
            'reason' => ['required', Rule::in(PicAvailability::REASONS)],
            'notes' => ['nullable', 'string', 'max:255', Rule::requiredIf($request->input('reason') === 'Other')],
        ]);

        $target = $this->authorizedTarget($actor, (int) $validated['user_id']);

        // Cannot mark someone inactive while they are actually working.
        if (app(ActiveActivityResolver::class)->currentFor($target) !== null) {
            return back()->with('warning', $target->name.' has an active activity and cannot be set inactive.');
        }

        PicAvailability::updateOrCreate(
            ['user_id' => $target->id, 'date' => today()->toDateString()],
            [
                'reason' => $validated['reason'],
                'notes' => trim((string) ($validated['notes'] ?? '')) ?: null,
                'set_by_user_id' => $actor->id,
            ],
        );

        return back()->with('success', $target->name.' set inactive ('.$validated['reason'].').');
    }

    /**
     * Clear a PIC's inactive marker — they become NOT STARTED again.
     */
    public function clearInactive(Request $request, PicAvailability $picAvailability): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor->isAdmin() || $actor->isKoordinator(), 403);
        $this->authorizedTarget($actor, $picAvailability->user_id);

        $name = $picAvailability->user?->name ?? 'PIC';
        $picAvailability->delete();

        return back()->with('success', $name.' set available.');
    }

    /**
     * The target PIC, or abort(403) if the actor may not control them.
     */
    private function authorizedTarget(User $actor, int $userId): User
    {
        $target = $this->assignablePics($actor)->firstWhere('id', $userId);

        abort_if($target === null, 403, 'You may not manage activities for this PIC.');

        return $target;
    }

    /**
     * Deep-link to the source module's own screen for a non-manual activity,
     * or null (manual activities are edited in this panel).
     *
     * @return array{label: string, url: string}|null
     */
    private function moduleLinkFor(ActiveActivity $activity): ?array
    {
        return match ($activity->source) {
            'PM' => $activity->recordId
                ? ['label' => 'Open Fill PM', 'url' => route('pm-schedules.edit', $activity->recordId)]
                : null,
            'GREASING' => $activity->recordId
                ? ['label' => 'Open Execute', 'url' => route('greasings.execute', $activity->recordId)]
                : null,
            'OIL_AUDIT' => ['label' => 'Open Oil Audit', 'url' => route('oil-audits.scan')],
            'OIL_AUDIT_ACTION' => ['label' => 'Open Oil Audit Action', 'url' => route('oil-audits.report')],
            default => null,
        };
    }

    /**
     * PICs the actor may act for:
     * ADMIN -> every active PIC (WWD + BUL);
     * KOORDINATOR WWD / BUL -> active PICs in their own area only.
     *
     * @return Collection<int, User>
     */
    private function assignablePics(User $actor): Collection
    {
        $roles = match (true) {
            $actor->isAdmin() => [User::ROLE_PIC_WWD, User::ROLE_PIC_BUL],
            $actor->isKoordinatorWwd() => [User::ROLE_PIC_WWD],
            $actor->isKoordinatorBul() => [User::ROLE_PIC_BUL],
            default => [],
        };

        if ($roles === []) {
            return collect();
        }

        return User::query()
            ->whereIn('role', $roles)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }
}
