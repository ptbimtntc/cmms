<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesActivityConflict;
use App\Models\ActivityMonitorClosure;
use App\Models\Machine;
use App\Models\OilAudit;
use App\Models\User;
use App\Services\ActiveActivityResolver;
use App\Services\OilAuditCreateService;
use App\Services\OilAuditFollowUpService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\View\View;

class OilAuditController extends Controller
{
    use HandlesActivityConflict;

    // Single source of truth moved to OilAudit::AREA / OilAudit::MACHINE_TYPES
    // (Task 2 — reused by the offline sync handler); kept as local aliases
    // so the rest of this controller's code is untouched.
    private const AUDIT_AREA = OilAudit::AREA;

    private const AUDIT_MACHINE_TYPES = OilAudit::MACHINE_TYPES;

    /**
     * The activity source ("OIL_AUDIT", "PM", ...) this PIC currently has
     * active, or null. Single place both the daily-start prompt and its
     * START endpoint check, so the two always agree with each other and
     * with ActiveActivityResolver (the "one active activity per PIC" source
     * of truth used everywhere else in the app).
     */
    private function currentActivitySource(User $user): ?string
    {
        return app(ActiveActivityResolver::class)->currentFor($user)?->source;
    }

    /**
     * Oil Audit / Oil Audit Action have no per-instance row — their closure
     * (see ActivityMonitorClosure) is keyed only by the PIC, unlike PM /
     * Greasing / Manual which each get their own record id. So a closure
     * from an EARLIER instance (finished, then the PIC restarted the same
     * source later the same day) must be cleared the moment a fresh start
     * is recorded — otherwise it would (a) keep the brand new instance
     * looking permanently "finished" and (b) collide with the unique
     * (source, source_key, business_date) index the next time it is
     * finished again.
     */
    private function clearStaleClosure(string $source, User $user): void
    {
        ActivityMonitorClosure::where('source', $source)
            ->where('source_key', (string) $user->id)
            ->whereDate('business_date', today())
            ->delete();
    }

    public function scan(Request $request): View
    {
        $user = $request->user();

        // The daily Start prompt is PIC-only and reflects whether Oil Audit
        // is this PIC's CURRENT activity right now (not merely "started at
        // some point today") — so it reappears if the PIC started Oil Audit,
        // then moved to a different activity, and comes back here. It does
        // not gate access to the page — NO simply dismisses it and the
        // existing scan workflow is untouched.
        return view('oil-audits.scan', [
            'promptStart' => $user->isPic() && $this->currentActivitySource($user) !== 'OIL_AUDIT',
            // FreeDOMS offline-first (Task 7): the in-scope machine list and
            // condition options are embedded on the page so
            // resources/js/oil-audits/scan.js can populate the offline
            // machine cache (Task 3's MasterDataCache) without a separate
            // request. Purely additive — nothing here changes scan()'s
            // existing behavior/response for anyone online. Kept minimal
            // (only the fields OIL_AUDIT_CREATE actually needs), never the
            // whole machines table.
            'offlineMachines' => Machine::where('area', self::AUDIT_AREA)
                ->whereIn('machine_type', self::AUDIT_MACHINE_TYPES)
                ->orderBy('machine_number')
                ->get(['id', 'machine_number', 'machine_type', 'area']),
            'offlineConditions' => OilAudit::CONDITION_LABELS,
        ]);
    }

    /**
     * Records this PIC's Oil Audit activity start for the current day.
     * Writes only users.oil_audit_started_at — no oil_audits row, no
     * follow-up, no status is touched. A no-op while Oil Audit is already
     * this PIC's current activity (so a double submit cannot overwrite the
     * current start time), but re-activates Oil Audit — going through the
     * usual one-active-activity conflict check — once the PIC has moved on
     * to something else and comes back to start it again.
     */
    public function startDaily(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'started_at' => ['required', 'date'],
        ]);

        $user = $request->user();

        if ($this->currentActivitySource($user) !== 'OIL_AUDIT') {
            $startedAt = Carbon::parse($validated['started_at']);

            // One active activity per PIC — checked across every activity
            // source. Unless END & START is already confirmed, bounce back
            // with the confirmation payload.
            if (! $request->boolean('confirm_end_start')) {
                $current = $this->activityConflictFor($user);

                if ($current) {
                    return redirect()
                        ->route('oil-audits.scan')
                        ->with('activity_conflict', $this->activityConflictPayload(
                            $current,
                            route('oil-audits.start-daily'),
                            $startedAt,
                        ));
                }
            } else {
                $startedAt = $this->confirmedStartTime($user, $startedAt);
            }

            $user->update(['oil_audit_started_at' => $startedAt]);
            $this->clearStaleClosure('OIL_AUDIT', $user);
        }

        return redirect()
            ->route('oil-audits.scan')
            ->with('success', 'Oil Audit activity dimulai. Silakan mulai scan mesin.');
    }

    /**
     * Records this PIC's Oil Audit Action activity start for the current
     * day. Same shape and guarantees as startDaily(): writes only
     * users.oil_audit_action_started_at, touches no audit / problem /
     * action-taken / follow-up / status / history data, and is a no-op
     * while Oil Audit Action is already this PIC's current activity — but
     * re-activates it once the PIC has moved on and comes back.
     */
    public function startDailyAction(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'started_at' => ['required', 'date'],
        ]);

        $user = $request->user();

        if ($this->currentActivitySource($user) !== 'OIL_AUDIT_ACTION') {
            $startedAt = Carbon::parse($validated['started_at']);

            // One active activity per PIC — checked across every activity
            // source. Unless END & START is already confirmed, bounce back
            // with the confirmation payload.
            if (! $request->boolean('confirm_end_start')) {
                $current = $this->activityConflictFor($user);

                if ($current) {
                    return redirect()
                        ->route('oil-audits.report')
                        ->with('activity_conflict', $this->activityConflictPayload(
                            $current,
                            route('oil-audits.report.start-daily'),
                            $startedAt,
                        ));
                }
            } else {
                $startedAt = $this->confirmedStartTime($user, $startedAt);
            }

            $user->update(['oil_audit_action_started_at' => $startedAt]);
            $this->clearStaleClosure('OIL_AUDIT_ACTION', $user);
        }

        return redirect()
            ->route('oil-audits.report')
            ->with('success', 'Oil Audit Action activity dimulai.');
    }

    public function entry(string $machineNumber): View
    {
        $machine = Machine::with('latestOilAudit.followUp')
            ->where('machine_number', trim($machineNumber))
            ->where('area', self::AUDIT_AREA)
            ->whereIn('machine_type', self::AUDIT_MACHINE_TYPES)
            ->firstOrFail();

        return view('oil-audits.entry', compact('machine'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate(OilAuditCreateService::rules());

        $machine = Machine::whereKey($validated['machine_id'])
            ->where('area', self::AUDIT_AREA)
            ->whereIn('machine_type', self::AUDIT_MACHINE_TYPES)
            ->firstOrFail();
        $user = $request->user();

        app(OilAuditCreateService::class)->create($machine, $user, $validated['condition']);

        return redirect()
            ->route('oil-audits.scan')
            ->with('success', "Audit oli {$machine->machine_number} berhasil disimpan. Siap scan mesin berikutnya.");
    }

    /**
     * Oil Audit Action — follow-up monitoring page. AUDIT-CENTRIC: 1 row =
     * 1 audit record. A machine audited 5 times legitimately produces 5
     * rows here — this is the whole point of the page (every audit event
     * needs its own follow-up decision), unlike OilAuditReportController
     * (machine-centric, 1 row = 1 machine, latest audit only).
     *
     * The query starts FROM OilAudit, not Machine: a machine with zero
     * audits has no OilAudit row to join back from, so it is naturally
     * absent — no whereHas/whereDoesntHave needed for that exclusion.
     *
     * Sort: audited_at DESC (newest first) with `id` DESC as a
     * deterministic tie-breaker for same-timestamp audits.
     */
    public function action(Request $request): View
    {
        $query = $this->filteredAuditQuery($request);

        // --- Summary — audit-centric, same filtered scope as the table.
        // Never counts machines as if they were audits. ---
        $totalAudit = (clone $query)->count();
        $findingsQuery = (clone $query)->whereIn('condition', OilAudit::followUpConditions());
        $totalFinding = (clone $findingsQuery)->count();
        $findingsWithAction = (clone $findingsQuery)->whereHas('followUp')->count();

        $durations = (clone $findingsQuery)
            ->whereHas('followUp')
            ->with('followUp')
            ->get()
            ->map(fn (OilAudit $audit) => abs(Carbon::parse($audit->audited_at)
                ->diffInDays(Carbon::parse($audit->followUp->actioned_at))));
        $averageActionDuration = $durations->isNotEmpty() ? round($durations->avg(), 1) : null;

        $pending = (clone $query)->requiringFollowUp()->whereDoesntHave('followUp')->count();
        $critical = (clone $query)->where('condition', 'KRITIS')->whereDoesntHave('followUp')->count();
        $today = (clone $query)->whereDate('audited_at', today())->count();

        $summary = [
            'total_audit' => $totalAudit,
            'total_finding' => $totalFinding,
            'findings_with_action' => $findingsWithAction,
            'average_action_duration' => $averageActionDuration,
            'pending' => $pending,
            'critical' => $critical,
            'today' => $today,
        ];

        $audits = (clone $query)
            ->with(['followUp.problems.findings'])
            ->orderByDesc('audited_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $areas = Machine::query()
            ->where('area', self::AUDIT_AREA)
            ->whereIn('machine_type', self::AUDIT_MACHINE_TYPES)
            ->select('area')
            ->distinct()
            ->orderBy('area')
            ->pluck('area');
        $machineTypes = Machine::query()
            ->where('area', self::AUDIT_AREA)
            ->whereIn('machine_type', self::AUDIT_MACHINE_TYPES)
            ->select('machine_type')
            ->distinct()
            ->orderBy('machine_type')
            ->pluck('machine_type');
        $pics = OilAudit::where('area', self::AUDIT_AREA)
            ->whereIn('machine_type', self::AUDIT_MACHINE_TYPES)
            ->whereNotNull('audited_by_name')
            ->select('audited_by_name')
            ->distinct()
            ->orderBy('audited_by_name')
            ->pluck('audited_by_name');

        $pendingAudits = OilAudit::query()
            ->where('area', self::AUDIT_AREA)
            ->whereIn('machine_type', self::AUDIT_MACHINE_TYPES)
            ->requiringFollowUp()
            ->whereDoesntHave('followUp')
            ->with('machine')
            ->latest('audited_at')
            ->limit(8)
            ->get();

        // Daily Start prompt — PIC-only, mirrors the Oil Audit scan menu:
        // reflects whether Oil Audit Action is CURRENT right now, not just
        // "started at some point today" (see scan() above). Purely
        // additive: it does not gate the page.
        $user = $request->user();
        $promptStart = $user->isPic() && $this->currentActivitySource($user) !== 'OIL_AUDIT_ACTION';

        return view('oil-audits.action', compact(
            'audits',
            'areas',
            'machineTypes',
            'pics',
            'summary',
            'pendingAudits',
            'promptStart'
        ));
    }

    /**
     * Base + filters for the audit-centric Action query. Every filter here
     * operates directly on oil_audits columns (or its own followUp/
     * followUp.problems relations) — never on Machine — since the base
     * model is OilAudit itself.
     */
    private function filteredAuditQuery(Request $request): Builder
    {
        return OilAudit::query()
            ->where('area', self::AUDIT_AREA)
            ->whereIn('machine_type', self::AUDIT_MACHINE_TYPES)
            ->when($request->filled('search'), function (Builder $query) use ($request) {
                $search = $request->string('search')->trim()->toString();

                $query->where(function (Builder $q) use ($search) {
                    $q->where('machine_number', 'like', "%{$search}%")
                        ->orWhere('machine_type', 'like', "%{$search}%")
                        ->orWhereHas('followUp', function (Builder $followUpQuery) use ($search) {
                            $followUpQuery->where('problem', 'like', "%{$search}%")
                                ->orWhereHas(
                                    'problems',
                                    fn (Builder $problemQuery) => $problemQuery->where('problem', 'like', "%{$search}%")
                                )
                                ->orWhereHas(
                                    'problems.findings',
                                    fn (Builder $findingQuery) => $findingQuery->where('finding', 'like', "%{$search}%")
                                );
                        });
                });
            })
            ->when(
                $request->filled('area'),
                fn (Builder $query) => $query->where('area', $request->input('area'))
            )
            ->when(
                $request->filled('machine_type'),
                fn (Builder $query) => $query->where('machine_type', $request->input('machine_type'))
            )
            ->when(
                $request->filled('year'),
                fn (Builder $query) => $query->whereYear('audited_at', (int) $request->input('year'))
            )
            ->when(
                $request->filled('month'),
                fn (Builder $query) => $query->whereMonth('audited_at', (int) $request->input('month'))
            )
            ->when(
                $request->filled('condition'),
                fn (Builder $query) => $query->where('condition', $request->input('condition'))
            )
            ->when(
                $request->filled('pic'),
                fn (Builder $query) => $query->where('audited_by_name', $request->input('pic'))
            )
            ->when($request->filled('finding_status'), function (Builder $query) use ($request) {
                $status = $request->input('finding_status');

                if ($status === 'NO_FINDING') {
                    $query->whereNotIn('condition', OilAudit::followUpConditions());
                } elseif ($status === 'OPEN') {
                    $query->whereIn('condition', OilAudit::followUpConditions())->whereDoesntHave('followUp');
                } elseif ($status === 'COMPLETED') {
                    $query->whereIn('condition', OilAudit::followUpConditions())->whereHas('followUp');
                }
            })
            ->when($request->boolean('follow_up'), function (Builder $query) {
                $query->requiringFollowUp()->whereDoesntHave('followUp');
            });
    }

    /**
     * History can be opened from two places: Oil Audit Report
     * (reports.oil-audit) or Oil Audit Action (oil-audits.report — the
     * route name was kept during the earlier rename). The Back button
     * needs to return to whichever one the user actually came from, with
     * its filters intact.
     *
     * Only an explicit, whitelisted `from` value (report|action) is ever
     * trusted — never an arbitrary redirect target — and the back URL is
     * always built via route() against a known route name, never from a
     * raw user-supplied URL. `return` only supplies the query string to
     * re-attach to that known route, and only whitelisted filter keys are
     * read out of it.
     */
    public function history(Request $request, string $machineNumber): View
    {
        $machine = Machine::where('machine_number', trim($machineNumber))
            ->where('area', self::AUDIT_AREA)
            ->whereIn('machine_type', self::AUDIT_MACHINE_TYPES)
            ->firstOrFail();
        $audits = $machine->oilAudits()
            ->with('followUp.problems.findings')
            ->latest('audited_at')
            ->paginate(15);

        $latestAudit = $machine->latestOilAudit()->with('followUp.problems.findings')->first();
        $recentAudits = $machine->oilAudits()
            ->latest('audited_at')
            ->limit(8)
            ->get()
            ->reverse()
            ->values();

        $from = in_array($request->query('from'), ['report', 'action'], true)
            ? $request->query('from')
            : 'report';

        return view('oil-audits.history', [
            'machine' => $machine,
            'audits' => $audits,
            'latestAudit' => $latestAudit,
            'recentAudits' => $recentAudits,
            'problemOptions' => OilAudit::PROBLEM_OPTIONS,
            'findingOptions' => OilAudit::FINDING_OPTIONS,
            'from' => $from,
            'backUrl' => $this->backUrl($from, $request),
            'backLabel' => $from === 'action' ? '← Back to Oil Audit Action' : '← Back to Oil Audit Report',
        ]);
    }

    /**
     * Builds the Back destination from a fixed, known route (never a raw
     * user-supplied URL) plus whatever whitelisted filter keys were active
     * on the originating page, so returning from history doesn't drop the
     * user's filters.
     */
    private function backUrl(string $from, Request $request): string
    {
        parse_str((string) $request->query('return', ''), $returnParams);

        if ($from === 'action') {
            $allowed = ['area', 'machine_type', 'year', 'month', 'condition', 'finding_status', 'pic', 'search', 'page'];

            return route('oil-audits.report', Arr::only($returnParams, $allowed));
        }

        $allowed = ['area', 'machine_type', 'condition', 'year', 'month', 'search', 'page'];

        return route('reports.oil-audit', Arr::only($returnParams, $allowed));
    }

    public function storeFollowUp(Request $request, OilAudit $oilAudit): RedirectResponse
    {
        $this->assertFollowUpAllowed($oilAudit);

        if ($oilAudit->followUp()->exists()) {
            return back()->with('warning', 'Follow up untuk audit ini sudah disimpan.');
        }

        $validated = $this->validateFollowUp($request);
        $user = $request->user();

        app(OilAuditFollowUpService::class)->store($oilAudit, $validated, $user);

        return back()->with('success', 'Tindak lanjut berhasil disimpan dan tercatat pada riwayat mesin.');
    }

    public function updateFollowUp(Request $request, OilAudit $oilAudit): RedirectResponse
    {
        $this->assertFollowUpAllowed($oilAudit);

        $followUp = $oilAudit->followUp;
        abort_unless($followUp !== null, 404);

        $validated = $this->validateFollowUp($request);

        app(OilAuditFollowUpService::class)->update($followUp, $validated);

        return back()->with('success', 'Tindak lanjut berhasil diperbarui.');
    }

    public function destroyFollowUp(Request $request, OilAudit $oilAudit): RedirectResponse
    {
        abort_unless(
            in_array($request->user()->role, ['ADMIN', 'KOORDINATOR WWD'], true),
            403,
            'Hanya Admin atau Koordinator WWD yang dapat menghapus tindak lanjut.'
        );

        // Problems + findings are removed by the FK cascade chain.
        $oilAudit->followUp?->delete();

        return back()->with('success', 'Tindak lanjut berhasil dihapus.');
    }

    /**
     * WWD + NDE/NDB scope guard shared by every follow-up write endpoint,
     * plus the "condition actually needs a follow-up" business rule.
     */
    private function assertFollowUpAllowed(OilAudit $oilAudit): void
    {
        abort_unless($oilAudit->isInAuditScope(), 404);
        abort_unless(
            $oilAudit->needsFollowUp(),
            422,
            'Follow up hanya diperlukan untuk kondisi oli yang tidak oke.'
        );
    }

    /**
     * Delegates to OilAuditFollowUpService::validate() (rules + uniqueness
     * checks extracted there in Task 2) so the online request and the
     * offline sync handler never validate this payload differently.
     * Throws ValidationException on failure, so the caller gets the same
     * redirect-back-with-errors behaviour as $request->validate().
     */
    private function validateFollowUp(Request $request): array
    {
        return app(OilAuditFollowUpService::class)->validate($request->all());
    }
}
