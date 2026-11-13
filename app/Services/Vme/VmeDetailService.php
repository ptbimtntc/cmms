<?php

namespace App\Services\Vme;

use App\Models\Area;
use App\Models\Greasing;
use App\Models\Machine;
use App\Models\MandatorySparepart;
use App\Models\ManualActivity;
use App\Models\OilAudit;
use App\Models\PicAvailability;
use App\Models\PMSchedule;
use App\Models\SmallPmSchedule;
use App\Models\PMSparepart;
use App\Models\User;
use App\Support\Activities\ActiveActivity;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Read-only detail for the VME side panel (click a PIC / a machine). Everything
 * comes from the FreeDOMS tables; nothing is written. Who may ask for what is
 * decided by the controller through VmeAccess, never here.
 */
class VmeDetailService
{
    public function __construct(private readonly VmeSnapshotService $snapshots) {}

    /** Area ids as a lookup, used to decide whether a machine/PIC is inside the viewer's scope. */
    public function areaIdForMachine(Machine $machine): ?int
    {
        return Area::query()->where('name', $machine->area)->value('id');
    }

    /**
     * @return array<string, mixed>
     */
    public function pic(User $pic, User $viewer): array
    {
        $state = $this->snapshots->picState($pic);
        $availability = PicAvailability::query()->where('user_id', $pic->id)->whereDate('date', today())->first();

        return [
            'type' => 'pic',
            'sync_enabled' => $state['sync'],
            'pic' => [
                'id' => $pic->id,
                'name' => $pic->name,
                'area' => $pic->area?->name,
                'status' => $state['status'],
                'unavailable_reason' => $availability?->label(),
            ],
            'activity' => $state['current'] ? $this->activityDetail($state['current'], $viewer) : null,
            'last_ended' => $state['last_ended'],
            'today' => $this->todayList($state['today']),
        ];
    }

    /**
     * The current activity with the details of the record it comes from.
     *
     * @return array<string, mixed>
     */
    private function activityDetail(ActiveActivity $activity, User $viewer): array
    {
        $source = $activity->source;
        $recordId = $activity->recordId;

        $out = [
            'source' => $source,
            'label' => $activity->displayLabel(),
            'location' => $activity->locationLabel(),
            'status' => $activity->status,
            'started_at' => $activity->startedAt->toIso8601String(),
            'record_id' => $recordId,
            'job_type' => $activity->jobTypeKey,
            'record' => null,
            'link' => null,
        ];

        if ($source === 'PM' && $recordId && ($pm = PMSchedule::find($recordId))) {
            $out['record'] = $this->pmRow($pm);
            $out['link'] = ['label' => 'Lihat riwayat PM', 'url' => route('machine-history.detail', [$pm->machine_number, $pm->id])];
        } elseif ($source === 'GREASING' && $recordId && ($g = Greasing::with('group:id,name')->find($recordId))) {
            $out['record'] = [
                'id' => $g->id,
                'order_number' => $g->order_number,
                'cycle' => $g->cycle,
                'group' => $g->group?->name,
                'plan_date' => $g->plan_date?->toDateString(),
                'due_date' => $g->due_date?->toDateString(),
                'status' => $g->status,
            ];
            if ($viewer->isAdmin() || $viewer->isKoordinator()) {
                $out['link'] = ['label' => 'Buka Execute Greasing', 'url' => route('greasings.execute', $g->id)];
            }
        } elseif ($source === 'SMALL_PM' && $recordId && ($s = SmallPmSchedule::with('row:id,name')->find($recordId))) {
            $out['record'] = [
                'id' => $s->id,
                'order_number' => $s->order_number,
                'row' => $s->row?->name,
                'cycle' => $s->cycle,
                'plan_date' => $s->plan_date?->toDateString(),
                'due_date' => $s->due_date?->toDateString(),
                'status' => $s->status,
            ];
            if ($viewer->isAdmin() || $viewer->isKoordinator()) {
                $out['link'] = ['label' => 'Buka Execute Small PM', 'url' => route('small-pm.execute', $s->id)];
            }
        } elseif ($source === 'MANUAL' && $recordId && ($m = ManualActivity::find($recordId))) {
            $out['record'] = ['id' => $m->id, 'name' => $m->name, 'machine_number' => $m->machine_number];
        } elseif ($source === 'PREDICTIVE') {
            // Daily marker on the user, like Oil Audit: no record of its own.
            $out['record'] = null;
            if ($viewer->isAdmin() || $viewer->isKoordinator()) {
                $out['link'] = ['label' => 'Buka Action Predictive', 'url' => route('predictive.index')];
            }
        } elseif (in_array($source, ['OIL_AUDIT', 'OIL_AUDIT_ACTION'], true)) {
            // Oil Audit is a daily marker on the user, not a record of its own.
            $out['record'] = null;
            if ($viewer->hasArea('WWD')) {
                $out['link'] = $source === 'OIL_AUDIT'
                    ? ['label' => 'Buka Oil Audit', 'url' => route('oil-audits.scan')]
                    : ['label' => 'Buka Oil Audit Action', 'url' => route('oil-audits.report')];
            }
        }

        return $out;
    }

    /**
     * Everything the PIC started today, as the map sees it.
     *
     * @param  Collection<int, ActiveActivity>  $all
     * @return list<array<string, mixed>>
     */
    private function todayList($all): array
    {
        return $all->map(fn (ActiveActivity $a) => [
            'source' => $a->source,
            'label' => $a->displayLabel(),
            'location' => $a->locationLabel(),
            'started_at' => $a->startedAt->toIso8601String(),
            'status' => $a->status,
        ])->take(10)->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function machine(Machine $machine): array
    {
        $machine->loadMissing('group:id,name');

        $running = PMSchedule::query()->where('machine_id', $machine->id)->activeActivity()
            ->orderByDesc('actual_date')->first();

        // The next scheduled PM: the nearest upcoming OPEN one, or — when every OPEN PM is already
        // past its plan date — the most recent of those, flagged as overdue.
        $open = PMSchedule::query()->where('machine_id', $machine->id)->where('status', 'OPEN');
        $next = (clone $open)->whereDate('plan_date', '>=', today())->orderBy('plan_date')->first();
        $overdue = false;
        if (! $next) {
            $next = (clone $open)->orderByDesc('plan_date')->first();
            $overdue = $next !== null;
        }

        $last = PMSchedule::query()->where('machine_id', $machine->id)->whereIn('status', PMSchedule::DONE_STATUSES)
            ->orderByDesc('actual_date')->orderByDesc('id')->first();

        $statusCounts = PMSchedule::query()->where('machine_id', $machine->id)
            ->select('status', DB::raw('count(*) as total'))->groupBy('status')->pluck('total', 'status');

        $recent = PMSchedule::query()->where('machine_id', $machine->id)->whereNotNull('actual_date')
            ->orderByDesc('actual_date')->orderByDesc('id')->limit(5)->get();

        $audit = OilAudit::query()->where('machine_id', $machine->id)->orderByDesc('audited_at')->first();

        return [
            'type' => 'machine',
            'machine' => [
                'id' => $machine->id,
                'number' => $machine->machine_number,
                'type' => $machine->machine_type,
                'description' => $machine->description,
                'group' => $machine->group?->name,
                'area' => $machine->area,
                'status' => $machine->status,
                'criticality' => $machine->criticality,
                'install_date' => $machine->install_date ? Carbon::parse($machine->install_date)->toDateString() : null,
                'pm_cycle' => $machine->pm_cycle_value ? $machine->pm_cycle_value.' '.strtolower((string) $machine->pm_cycle_unit) : null,
                'link' => ['label' => 'Open the machine history', 'url' => route('machine-history.show', $machine->machine_number)],
            ],
            'pm' => [
                'running' => $running ? $this->pmRow($running) : null,
                'next' => $next ? $this->pmRow($next) + ['overdue' => $overdue] : null,
                'next_estimated' => $this->estimatedNextPm($machine, $last),
                'last' => $last ? $this->pmRow($last) : null,
            ],
            'history' => [
                'pm_total' => (int) $statusCounts->sum(),
                'pm_finished' => (int) (($statusCounts['FINISHED'] ?? 0) + ($statusCounts['FINISHED_ON_TIME'] ?? 0)),
                'pm_missed' => (int) ($statusCounts['MISSED'] ?? 0),
                'pm_open' => (int) (($statusCounts['OPEN'] ?? 0) + ($statusCounts['IN_PROGRESS'] ?? 0)),
                'recent' => $recent->map(fn (PMSchedule $pm) => $this->pmRow($pm))->all(),
                'last_oil_audit' => $audit ? [
                    'audited_at' => $audit->audited_at?->toIso8601String(),
                    'condition' => $audit->condition,
                    'audited_by' => $audit->audited_by_name,
                ] : null,
            ],
            'spareparts' => [
                'mandatory' => $this->mandatory($machine),
                'used' => $this->usedInPm($machine),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function pmRow(PMSchedule $pm): array
    {
        return [
            'id' => $pm->id,
            'order_number' => $pm->order_number,
            'plan_date' => $this->date($pm->plan_date),
            'due_date' => $this->date($pm->due_date),
            'actual_date' => $this->date($pm->actual_date),
            'start_time' => $pm->start_time,
            'end_time' => $pm->end_time,
            'status' => $pm->status,
            'pic' => $pm->pic,
            'machine_number' => $pm->machine_number,
            'link' => ['label' => 'Detail PM', 'url' => route('machine-history.detail', [$pm->machine_number, $pm->id])],
        ];
    }

    /** Same rule as the machine history page: last finished PM + the machine's PM cycle. */
    private function estimatedNextPm(Machine $machine, ?PMSchedule $last): ?string
    {
        if (! $last?->actual_date || ! $machine->pm_cycle_value) {
            return null;
        }

        $date = Carbon::parse($last->actual_date);

        return match (strtolower((string) $machine->pm_cycle_unit)) {
            'day' => $date->addDays($machine->pm_cycle_value)->toDateString(),
            'week' => $date->addWeeks($machine->pm_cycle_value)->toDateString(),
            'month' => $date->addMonths($machine->pm_cycle_value)->toDateString(),
            default => null,
        };
    }

    /**
     * Mandatory spareparts are defined per machine TYPE (not per machine).
     *
     * @return list<array<string, mixed>>
     */
    private function mandatory(Machine $machine): array
    {
        return MandatorySparepart::query()->where('machine_type', $machine->machine_type)
            ->with('sparepart:id,material_number,description,stock,unit')->limit(30)->get()
            ->map(fn (MandatorySparepart $m) => [
                'material_number' => $m->sparepart?->material_number,
                'description' => $m->sparepart?->description,
                'qty' => (int) $m->qty,
                'stock' => $m->sparepart ? (float) $m->sparepart->stock : null,
                'unit' => $m->sparepart?->unit,
            ])->all();
    }

    /**
     * Spareparts actually used in this machine's PMs (all time), newest use first.
     *
     * @return list<array<string, mixed>>
     */
    private function usedInPm(Machine $machine): array
    {
        return PMSparepart::query()
            ->join('pm_schedules', 'pm_schedules.id', '=', 'pm_spareparts.pm_schedule_id')
            ->join('spareparts', 'spareparts.id', '=', 'pm_spareparts.sparepart_id')
            ->where('pm_schedules.machine_id', $machine->id)
            ->groupBy('spareparts.id', 'spareparts.material_number', 'spareparts.description', 'spareparts.unit')
            ->orderByDesc(DB::raw('max(pm_schedules.actual_date)'))
            ->limit(15)
            ->get([
                'spareparts.material_number',
                'spareparts.description',
                'spareparts.unit',
                DB::raw('sum(pm_spareparts.qty) as total_qty'),
                DB::raw('count(distinct pm_spareparts.pm_schedule_id) as pm_count'),
                DB::raw('max(pm_schedules.actual_date) as last_used'),
            ])
            ->map(fn ($r) => [
                'material_number' => $r->material_number,
                'description' => $r->description,
                'unit' => $r->unit,
                'total_qty' => (int) $r->total_qty,
                'pm_count' => (int) $r->pm_count,
                'last_used' => $r->last_used ? Carbon::parse($r->last_used)->toDateString() : null,
            ])->all();
    }

    private function date($value): ?string
    {
        return $value ? Carbon::parse($value)->toDateString() : null;
    }
}
