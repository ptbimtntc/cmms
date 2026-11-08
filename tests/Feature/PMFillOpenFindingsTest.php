<?php

use App\Models\Area;
use App\Models\Greasing;
use App\Models\GreasingFinding;
use App\Models\Group;
use App\Models\Machine;
use App\Models\OilAudit;
use App\Models\OilAuditFollowUp;
use App\Models\PMSchedule;
use App\Models\PredictiveWorkOrder;
use App\Models\SmallPmFinding;
use App\Models\SmallPmRow;
use App\Models\SmallPmSchedule;
use App\Models\User;
use App\Services\MachineOpenFindings;
use Carbon\Carbon;

/**
 * Fill PM shows the findings still open on the machine in other modules,
 * read-only, so the PIC can handle them during the PM.
 */
function ofArea(): Area
{
    return Area::firstOrCreate(['name' => 'WWD'], ['slug' => 'wwd', 'is_active' => true]);
}

function ofMachine(string $number): Machine
{
    return Machine::create(['machine_number' => $number, 'area' => 'WWD', 'machine_type' => 'NDE', 'status' => 'ACTIVE']);
}

function ofSchedule(Machine $machine, string $pic = 'Budi'): PMSchedule
{
    $plan = now()->toDateString();

    return PMSchedule::create([
        'machine_id' => $machine->id,
        'machine_number' => $machine->machine_number,
        'machine_type' => $machine->machine_type,
        'area' => $machine->area,
        'order_number' => 'ORD-'.uniqid(),
        'plan_date' => $plan,
        'plan_month' => Carbon::parse($plan)->format('F'),
        'plan_year' => Carbon::parse($plan)->format('Y'),
        'due_date' => Carbon::parse($plan)->addDays(14),
        'pic' => $pic,
        'status' => 'OPEN',
    ]);
}

function ofOil(Machine $machine, string $condition): OilAudit
{
    return OilAudit::create([
        'machine_id' => $machine->id,
        'machine_number' => $machine->machine_number,
        'machine_type' => $machine->machine_type,
        'area' => $machine->area,
        'condition' => $condition,
        'audited_by_name' => 'Tester',
        'audited_at' => now(),
    ]);
}

function ofWo(Machine $machine, string $description, string $status = 'OPEN', bool $withMachineId = true): PredictiveWorkOrder
{
    return PredictiveWorkOrder::create([
        'category' => PredictiveWorkOrder::CATEGORY_VIBRATION,
        'order_number' => 'WO-'.uniqid(),
        'machine_id' => $withMachineId ? $machine->id : null,
        'machine_number' => $machine->machine_number,
        'area' => $machine->area,
        'machine_type' => $machine->machine_type,
        'wo_date' => '2026-11-01',
        'problem_description' => $description,
        'due_date' => '2026-11-15',
        'status' => $status,
    ]);
}

function ofGreasingFinding(Machine $machine, string $finding, string $status = 'OPEN', ?string $pic = null): GreasingFinding
{
    $group = Group::create(['name' => 'GRP '.uniqid(), 'area_id' => ofArea()->id]);
    $greasing = Greasing::create([
        'group_id' => $group->id,
        'order_number' => 'GR-'.uniqid(),
        'cycle' => '4W',
        'plan_date' => '2026-11-01',
        'due_date' => Greasing::calculateDueDate('2026-11-01'),
        'pic' => $pic,
        'status' => 'OPEN',
    ]);

    return GreasingFinding::create([
        'greasing_id' => $greasing->id,
        'machine_id' => $machine->id,
        'finding_area' => 'kapstan1',
        'finding' => $finding,
        'status' => $status,
    ]);
}

function ofBlowerFinding(Machine $machine, string $status = 'OPEN', string $task = SmallPmRow::TASK_FILTER_PANEL): SmallPmFinding
{
    $row = SmallPmRow::create(['name' => 'Row '.uniqid(), 'area_id' => ofArea()->id, 'sort_order' => 1]);
    $schedule = SmallPmSchedule::create([
        'small_pm_row_id' => $row->id,
        'task_type' => $task,
        'cycle' => '26W',
        'plan_date' => '2026-11-09',
        'due_date' => SmallPmSchedule::calculateDueDate('2026-11-09')->toDateString(),
        'status' => 'OPEN',
    ]);

    return SmallPmFinding::create(['small_pm_schedule_id' => $schedule->id, 'machine_id' => $machine->id, 'status' => $status]);
}

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $this->machine = ofMachine('NDE-OF-1');
    $this->other = ofMachine('NDE-OF-2');
    $this->pm = ofSchedule($this->machine);
});

test('shows open findings of every source for the PM machine', function () {
    ofOil($this->machine, 'KRITIS');
    ofWo($this->machine, 'Getaran bearing tinggi');
    ofGreasingFinding($this->machine, 'Nipple grease patah');
    ofBlowerFinding($this->machine);

    $this->actingAs($this->admin)->get(route('pm-schedules.edit', $this->pm))
        ->assertOk()
        ->assertSee('Open findings on this machine')
        ->assertSee('Kritis')
        ->assertSee('Getaran bearing tinggi')
        ->assertSee('Nipple grease patah')
        ->assertSee('Blower filter dead')
        ->assertViewHas('openFindings', fn (array $f) => collect($f)->map(fn ($s) => $s['total'])->all() === [
            'oil' => 1, 'predictive' => 1, 'greasing' => 1, 'blower' => 1,
        ]);
});

test('closed findings and findings of other machines are left out', function () {
    ofOil($this->machine, 'OKE');
    $followedUp = ofOil($this->machine, 'PAS_GARIS');
    OilAuditFollowUp::create(['oil_audit_id' => $followedUp->id, 'problem' => 'x', 'action_taken' => 'Tambah oli', 'pic_name' => 'Budi', 'actioned_at' => now()]);
    ofWo($this->machine, 'Sudah ditutup', 'CLOSED');
    ofGreasingFinding($this->machine, 'Sudah selesai', 'COMPLETED');
    ofBlowerFinding($this->machine, 'COMPLETED');
    ofBlowerFinding($this->machine, 'OPEN', SmallPmRow::TASK_CLEANING_MOTOR);
    ofOil($this->other, 'KRITIS');
    ofWo($this->other, 'Mesin lain');

    $this->actingAs($this->admin)->get(route('pm-schedules.edit', $this->pm))
        ->assertOk()
        ->assertSee('No open findings for this machine.')
        ->assertDontSee('Open findings on this machine')
        ->assertDontSee('Mesin lain');
});

test('a predictive WO without machine_id is matched by machine number', function () {
    ofWo($this->machine, 'WO lama tanpa machine id', 'OPEN', false);

    $this->actingAs($this->admin)->get(route('pm-schedules.edit', $this->pm))
        ->assertSee('WO lama tanpa machine id');
});

test('links only point to pages the user may open', function () {
    $pic = User::factory()->create(['role' => User::ROLE_PIC, 'area_id' => ofArea()->id, 'name' => 'Budi', 'is_active' => true]);
    $finding = ofGreasingFinding($this->machine, 'Bukan jadwal PIC ini', 'OPEN', 'Orang Lain');

    $this->actingAs($pic)->get(route('pm-schedules.edit', $this->pm))
        ->assertOk()
        ->assertSee('Bukan jadwal PIC ini')
        ->assertDontSee(route('greasings.execute', $finding->greasing_id));

    $this->actingAs($this->admin)->get(route('pm-schedules.edit', $this->pm))
        ->assertSee(route('greasings.execute', $finding->greasing_id));
});

test('a failing source is flagged without breaking Fill PM', function () {
    ofWo($this->machine, 'Tetap tampil');

    $this->mock(MachineOpenFindings::class)->shouldReceive('forSchedule')->andReturn([
        'oil' => ['label' => 'Oil Audit', 'items' => [], 'total' => 0, 'more_url' => null, 'error' => true],
        'predictive' => ['label' => 'Predictive', 'items' => [['title' => 'Tetap tampil', 'detail' => null, 'date' => null, 'url' => null]], 'total' => 1, 'more_url' => null, 'error' => false],
        'greasing' => ['label' => 'Greasing', 'items' => [], 'total' => 0, 'more_url' => null, 'error' => false],
        'blower' => ['label' => 'Blower Panel', 'items' => [], 'total' => 0, 'more_url' => null, 'error' => false],
    ]);

    $this->actingAs($this->admin)->get(route('pm-schedules.edit', $this->pm))
        ->assertOk()
        ->assertSee('Could not load Oil Audit findings.')
        ->assertSee('Tetap tampil');
});
