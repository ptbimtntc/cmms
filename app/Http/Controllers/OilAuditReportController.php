<?php

namespace App\Http\Controllers;

use App\Models\OilAudit;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class OilAuditReportController extends Controller
{
    /**
     * Oil Audit is a WWD-only module (see OilAuditController::AUDIT_AREA /
     * AUDIT_MACHINE_TYPES) — this is an unconditional business rule, not a
     * user-selectable filter. The Area dropdown below is layered on TOP of
     * this fixed floor, never a replacement for it: picking "BUL" simply
     * combines with `area = WWD` via AND and yields zero rows, which is the
     * correct, honest way to expose the mandated ALL/WWD/BUL filter without
     * ever actually showing non-WWD data.
     */
    private const AUDIT_AREA = 'WWD';

    private const AUDIT_MACHINE_TYPES = ['NDE', 'NDB'];

    private const FINDING_STATUSES = ['NO_FINDING', 'OPEN', 'COMPLETED'];

    public function index(Request $request)
    {
        $year = $request->filled('year') ? (int) $request->input('year') : null;
        $month = $request->filled('month') ? (int) $request->input('month') : null;
        $area = in_array($request->input('area'), ['WWD', 'BUL'], true) ? $request->input('area') : null;
        $machine = $request->input('machine') ?: null;
        $pic = $request->input('pic') ?: null;
        $condition = array_key_exists($request->input('condition'), OilAudit::CONDITION_LABELS)
            ? $request->input('condition')
            : null;
        $findingStatus = in_array($request->input('finding_status'), self::FINDING_STATUSES, true)
            ? $request->input('finding_status')
            : null;
        // Order Number search is intentionally not offered: Oil Audit has no
        // order_number field and no relation to a record that has one.
        $search = trim((string) $request->input('search', ''));

        $query = $this->filteredQuery($year, $month, $area, $machine, $pic, $condition, $findingStatus, $search);

        // --- Summary always reflects the exact same filtered scope as the
        // table below it (same convention as PM/Greasing Report). ---
        $totalAudit = (clone $query)->count();
        $totalFinding = (clone $query)->whereIn('condition', OilAudit::followUpConditions())->count();
        $findingsWithAction = (clone $query)
            ->whereIn('condition', OilAudit::followUpConditions())
            ->whereHas('followUp')
            ->count();

        // Average Action Duration = mean(Action Date - Audit Date) in whole
        // days, over findings that HAVE a follow-up only. A finding with no
        // Action Date yet is excluded from the average entirely — it is
        // never counted as a completed (zero-duration) action, since that
        // would understate how long unresolved findings have been open.
        $averageActionDuration = (clone $query)
            ->whereIn('condition', OilAudit::followUpConditions())
            ->whereHas('followUp')
            ->with('followUp')
            ->get()
            ->map(fn (OilAudit $audit) => $audit->audited_at->copy()->startOfDay()
                ->diffInDays($audit->followUp->actioned_at->copy()->startOfDay()))
            ->avg();

        $summary = [
            'total_audit' => $totalAudit,
            'total_finding' => $totalFinding,
            'findings_with_action' => $findingsWithAction,
            'average_action_duration' => $averageActionDuration !== null ? round($averageActionDuration, 1) : null,
        ];

        $audits = $query
            ->with(['followUp.problems'])
            ->orderByDesc('audited_at')
            ->paginate(20)
            ->withQueryString();

        // --- Year options ---
        $minDate = OilAudit::min('audited_at');
        $maxDate = OilAudit::max('audited_at');
        $years = $minDate
            ? range((int) Carbon::parse($maxDate)->format('Y'), (int) Carbon::parse($minDate)->format('Y'))
            : [];
        if ($year && ! in_array($year, $years, true)) {
            $years[] = $year;
        }
        rsort($years);

        // --- Machine/PIC filter dropdown options — scoped by the fixed
        // WWD/NDE-NDB floor and the active year/month/area, but never by the
        // other cross-filters (machine/pic/condition/finding_status/search),
        // so narrowing one of those never hides the choices in another. ---
        $optionsScope = $this->applyFilters($this->baseScope(), $year, $month, $area, null, null, null, null, '');
        $machines = (clone $optionsScope)->distinct()->orderBy('machine_number')->pluck('machine_number');
        $pics = (clone $optionsScope)->whereNotNull('audited_by_name')->distinct()->orderBy('audited_by_name')->pluck('audited_by_name');

        return view('reports.oil-audit.index', [
            'summary' => $summary,
            'audits' => $audits,
            'years' => $years,
            'machines' => $machines,
            'pics' => $pics,
            'conditions' => OilAudit::CONDITION_LABELS,
            'selectedYear' => $year,
            'selectedMonth' => $month,
            'selectedArea' => $area,
            'selectedMachine' => $machine,
            'selectedPic' => $pic,
            'selectedCondition' => $condition,
            'selectedFindingStatus' => $findingStatus,
            'search' => $search,
        ]);
    }

    /**
     * The unconditional WWD/NDE-NDB business-rule floor — identical to
     * OilAuditController::report()'s scope. Never overridden by the Area
     * filter (see the class doc comment above).
     */
    private function baseScope(): Builder
    {
        return OilAudit::where('area', self::AUDIT_AREA)->whereIn('machine_type', self::AUDIT_MACHINE_TYPES);
    }

    private function applyFilters(
        Builder $query,
        ?int $year,
        ?int $month,
        ?string $area,
        ?string $machine,
        ?string $pic,
        ?string $condition,
        ?string $findingStatus,
        string $search
    ): Builder {
        if ($year) {
            $query->whereYear('audited_at', $year);
        }

        if ($month) {
            $query->whereMonth('audited_at', $month);
        }

        if ($area) {
            $query->where('area', $area);
        }

        if ($machine) {
            $query->where('machine_number', $machine);
        }

        if ($pic) {
            $query->where('audited_by_name', $pic);
        }

        if ($condition) {
            $query->where('condition', $condition);
        }

        if ($findingStatus === 'NO_FINDING') {
            $query->whereNotIn('condition', OilAudit::followUpConditions());
        } elseif ($findingStatus === 'OPEN') {
            $query->whereIn('condition', OilAudit::followUpConditions())->whereDoesntHave('followUp');
        } elseif ($findingStatus === 'COMPLETED') {
            $query->whereIn('condition', OilAudit::followUpConditions())->whereHas('followUp');
        }

        if ($search !== '') {
            $query->where('machine_number', 'like', "%{$search}%");
        }

        return $query;
    }

    private function filteredQuery(
        ?int $year,
        ?int $month,
        ?string $area,
        ?string $machine,
        ?string $pic,
        ?string $condition,
        ?string $findingStatus,
        string $search
    ): Builder {
        return $this->applyFilters($this->baseScope(), $year, $month, $area, $machine, $pic, $condition, $findingStatus, $search);
    }
}
