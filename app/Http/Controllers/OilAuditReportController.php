<?php

namespace App\Http\Controllers;

use App\Models\Machine;
use App\Models\OilAudit;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class OilAuditReportController extends Controller
{
    /**
     * Oil Audit is a WWD-only module — this is an unconditional business
     * rule, not a user-selectable filter. These two constants are
     * intentionally kept identical to OilAuditController::AUDIT_AREA /
     * AUDIT_MACHINE_TYPES rather than re-derived independently: after
     * inspecting Machine, MachineChecklist, and MachineProblem, there is no
     * other column/master table anywhere that encodes "which machine types
     * are in Oil Audit scope" — this pair of constants IS the single
     * existing source of truth for that scope.
     */
    private const AUDIT_AREA = 'WWD';

    private const AUDIT_MACHINE_TYPES = ['NDE', 'NDB'];

    /**
     * MACHINE-CENTRIC: 1 row = 1 machine, always — regardless of whether
     * that machine has 0, 1, or 100 audit records. Only the LATEST audit
     * (via Machine::latestOilAudit(), a real hasOne()->latestOfMany()
     * relation) is ever shown here. Full audit history belongs to
     * oil-audits.history (existing "View" link target), never to this
     * table. See OilAuditController::action() for the audit-centric
     * (1 row = 1 audit record) counterpart.
     */
    public function index(Request $request)
    {
        $area = in_array($request->input('area'), ['WWD', 'BUL'], true) ? $request->input('area') : null;
        $machineType = $request->input('machine_type') ?: null;
        $condition = array_key_exists($request->input('condition'), OilAudit::CONDITION_LABELS)
            ? $request->input('condition')
            : null;
        $year = $request->filled('year') ? (int) $request->input('year') : null;
        $month = $request->filled('month') ? (int) $request->input('month') : null;
        $search = trim((string) $request->input('search', ''));

        $query = $this->filteredQuery($area, $machineType, $condition, $year, $month, $search);

        // --- Summary — machine-centric, same filtered scope as the table.
        // Never counts audits as if they were machines. ---
        $totalMachines = (clone $query)->count();
        $neverAudited = (clone $query)->whereDoesntHave('oilAudits')->count();
        $withLatestFinding = (clone $query)
            ->whereHas('latestOilAudit', fn (Builder $q) => $q->whereIn('condition', OilAudit::followUpConditions()))
            ->count();

        $summary = [
            'total_machines' => $totalMachines,
            'never_audited' => $neverAudited,
            'with_latest_finding' => $withLatestFinding,
        ];

        // Sorting: Machine Number ASC only — there is no audit-history
        // ordering here at all, since exactly one row exists per machine.
        // Pagination is therefore machine-centric by construction (COUNT/
        // LIMIT/OFFSET all operate over the Machine query, never over
        // audits), and eager-loading the latest audit via with() is a
        // single extra query for the whole page — never N+1, never a
        // "fetch all audits then group in PHP" approach.
        $machines = (clone $query)
            ->with(['latestOilAudit.followUp.problems'])
            ->orderBy('machine_number')
            ->paginate(20)
            ->withQueryString();

        // --- Filter options ---
        $optionsScope = $this->baseScope()->when($area, fn (Builder $q) => $q->where('area', $area));
        $machineTypes = (clone $optionsScope)->distinct()->orderBy('machine_type')->pluck('machine_type');

        $minDate = OilAudit::where('area', self::AUDIT_AREA)->whereIn('machine_type', self::AUDIT_MACHINE_TYPES)->min('audited_at');
        $maxDate = OilAudit::where('area', self::AUDIT_AREA)->whereIn('machine_type', self::AUDIT_MACHINE_TYPES)->max('audited_at');
        $years = $minDate
            ? range((int) Carbon::parse($maxDate)->format('Y'), (int) Carbon::parse($minDate)->format('Y'))
            : [];
        if ($year && ! in_array($year, $years, true)) {
            $years[] = $year;
        }
        rsort($years);

        return view('reports.oil-audit.index', [
            'summary' => $summary,
            'machines' => $machines,
            'machineTypes' => $machineTypes,
            'conditions' => OilAudit::CONDITION_LABELS,
            'years' => $years,
            'selectedArea' => $area,
            'selectedMachineType' => $machineType,
            'selectedCondition' => $condition,
            'selectedYear' => $year,
            'selectedMonth' => $month,
            'search' => $search,
        ]);
    }

    private function baseScope(): Builder
    {
        return Machine::query()
            ->where('area', self::AUDIT_AREA)
            ->whereIn('machine_type', self::AUDIT_MACHINE_TYPES);
    }

    /**
     * Condition/Year/Month all target the machine's LATEST audit only
     * (whereHas('latestOilAudit', ...)) — a machine whose latest audit
     * doesn't match is excluded, which is expected: these filters only
     * make sense against an actual (latest) audit. A never-audited machine
     * has no latestOilAudit at all, so it is naturally excluded once any
     * of these three is used — documented behavior, not a regression.
     * Area/Machine Type/Search always target plain machines.* columns, so
     * they never exclude a never-audited machine.
     */
    private function applyFilters(
        Builder $query,
        ?string $area,
        ?string $machineType,
        ?string $condition,
        ?int $year,
        ?int $month,
        string $search
    ): Builder {
        if ($area) {
            $query->where('area', $area);
        }

        if ($machineType) {
            $query->where('machine_type', $machineType);
        }

        if ($condition || $year || $month) {
            $query->whereHas('latestOilAudit', function (Builder $q) use ($condition, $year, $month) {
                if ($condition) {
                    $q->where('condition', $condition);
                }
                if ($year) {
                    $q->whereYear('audited_at', $year);
                }
                if ($month) {
                    $q->whereMonth('audited_at', $month);
                }
            });
        }

        if ($search !== '') {
            $query->where(function (Builder $q) use ($search) {
                $q->where('machine_number', 'like', "%{$search}%")
                    ->orWhere('machine_type', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        return $query;
    }

    private function filteredQuery(
        ?string $area,
        ?string $machineType,
        ?string $condition,
        ?int $year,
        ?int $month,
        string $search
    ): Builder {
        return $this->applyFilters($this->baseScope(), $area, $machineType, $condition, $year, $month, $search);
    }
}
