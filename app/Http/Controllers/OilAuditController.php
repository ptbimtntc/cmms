<?php

namespace App\Http\Controllers;

use App\Models\Machine;
use App\Models\OilAudit;
use App\Models\OilAuditFollowUp;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\View\View;

class OilAuditController extends Controller
{
    private const AUDIT_AREA = 'WWD';

    private const AUDIT_MACHINE_TYPES = ['NDE', 'NDB'];

    public function scan(): View
    {
        return view('oil-audits.scan');
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
        $validated = $request->validate([
            'machine_id' => ['required', 'exists:machines,id'],
            'condition' => [
                'required',
                'in:'.implode(',', array_keys(OilAudit::CONDITION_LABELS)),
            ],
        ]);

        $machine = Machine::whereKey($validated['machine_id'])
            ->where('area', self::AUDIT_AREA)
            ->whereIn('machine_type', self::AUDIT_MACHINE_TYPES)
            ->firstOrFail();
        $user = $request->user();

        OilAudit::create([
            'machine_id' => $machine->id,
            'machine_number' => $machine->machine_number,
            'machine_type' => $machine->machine_type,
            'area' => $machine->area,
            'condition' => $validated['condition'],
            'audited_by_user_id' => $user->id,
            'audited_by_name' => $user->name,
            'audited_at' => now(),
        ]);

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
            ->with(['followUp.problems'])
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

        return view('oil-audits.action', compact(
            'audits',
            'areas',
            'machineTypes',
            'pics',
            'summary',
            'pendingAudits'
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
            ->with('followUp.problems')
            ->latest('audited_at')
            ->paginate(15);

        $latestAudit = $machine->latestOilAudit()->with('followUp.problems')->first();
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
        abort_unless(
            $oilAudit->area === self::AUDIT_AREA
                && in_array($oilAudit->machine_type, self::AUDIT_MACHINE_TYPES, true),
            404
        );
        abort_unless(
            $oilAudit->needsFollowUp(),
            422,
            'Follow up hanya diperlukan untuk kondisi oli yang tidak oke.'
        );

        if ($oilAudit->followUp()->exists()) {
            return back()->with('warning', 'Follow up untuk audit ini sudah disimpan.');
        }

        $validated = $request->validate([
            'problems' => ['required', 'array', 'min:1'],
            'problems.*' => [
                'required',
                'in:'.implode(',', OilAudit::PROBLEM_OPTIONS),
            ],
            'action_taken' => ['required', 'string', 'max:2000'],
        ]);

        $user = $request->user();

        $followUp = OilAuditFollowUp::create([
            'oil_audit_id' => $oilAudit->id,
            // Keep the legacy column populated for backward compatibility.
            'problem' => $validated['problems'][0],
            'action_taken' => $validated['action_taken'],
            'pic_user_id' => $user->id,
            'pic_name' => $user->name,
            'actioned_at' => now(),
        ]);

        $followUp->problems()->createMany(
            collect($validated['problems'])
                ->map(fn (string $problem) => ['problem' => $problem])
                ->all()
        );

        return back()->with('success', 'Tindak lanjut berhasil disimpan dan tercatat pada riwayat mesin.');
    }
}
