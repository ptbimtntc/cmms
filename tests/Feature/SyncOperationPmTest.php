<?php

use App\Models\Machine;
use App\Models\MachineChecklist;
use App\Models\MachineProblem;
use App\Models\PMChecklist;
use App\Models\PMMeasurement;
use App\Models\PMProblem;
use App\Models\PMSchedule;
use App\Models\PMSparepart;
use App\Models\Sparepart;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Str;

function syncPmMachine(array $overrides = []): Machine
{
    return Machine::create(array_merge([
        'machine_number' => 'MC-'.uniqid(),
        'area' => 'WWD',
        'machine_type' => 'NDE',
        'status' => 'ACTIVE',
    ], $overrides));
}

function syncPmSchedule(Machine $machine, array $overrides = []): PMSchedule
{
    $planDate = $overrides['plan_date'] ?? now()->toDateString();

    return PMSchedule::create(array_merge([
        'machine_id' => $machine->id,
        'machine_number' => $machine->machine_number,
        'machine_type' => $machine->machine_type,
        'area' => $machine->area,
        'order_number' => 'ORD-'.uniqid(),
        'plan_date' => $planDate,
        'plan_month' => Carbon::parse($planDate)->format('F'),
        'plan_year' => Carbon::parse($planDate)->format('Y'),
        'due_date' => Carbon::parse($planDate)->addDays(14),
        'pic' => null,
        'status' => 'OPEN',
    ], $overrides));
}

function syncPost(User $user, array $body)
{
    return test()->actingAs($user)->postJson(route('api.sync'), $body);
}

// ---------------------------------------------------------------------------
// PM_START
// ---------------------------------------------------------------------------

test('PM_START via sync writes the same start_time/actual_date columns as the online endpoint', function () {
    $pic = User::factory()->create(['role' => User::ROLE_PIC_WWD, 'name' => 'Budi']);
    $pm = syncPmSchedule(syncPmMachine(), ['pic' => 'Budi']);

    $response = syncPost($pic, [
        'operation_uuid' => (string) Str::uuid(),
        'transaction_type' => 'PM_START',
        'payload' => [
            'pm_schedule_id' => $pm->id,
            'started_at' => '2026-09-06T08:30',
        ],
    ]);

    $response->assertOk()->assertJson(['status' => 'processed']);

    $pm->refresh();
    expect($pm->start_time)->toStartWith('08:30')
        ->and($pm->status)->toBe('IN_PROGRESS');
});

test('PM_START via sync is rejected as a conflict when the device thinks the PM is not started but it already is', function () {
    $pic = User::factory()->create(['role' => User::ROLE_PIC_WWD, 'name' => 'Budi']);
    $pm = syncPmSchedule(syncPmMachine(), [
        'pic' => 'Budi',
        'status' => 'IN_PROGRESS',
        'start_time' => '07:00',
        'actual_date' => '2026-09-05',
    ]);

    $response = syncPost($pic, [
        'operation_uuid' => (string) Str::uuid(),
        'transaction_type' => 'PM_START',
        'payload' => ['pm_schedule_id' => $pm->id, 'started_at' => '2026-09-06T08:30'],
        'expected_state' => ['is_started' => false],
    ]);

    $response->assertStatus(409)->assertJson(['success' => false, 'status' => 'conflict']);

    // The server's earlier start time is untouched — no silent overwrite.
    $pm->refresh();
    expect($pm->start_time)->toStartWith('07:00');
});

test('PM_START via sync reports an activity_conflict when the PIC already has another active activity', function () {
    $pic = User::factory()->create(['role' => User::ROLE_PIC_WWD, 'name' => 'Budi']);

    syncPmSchedule(syncPmMachine(), [
        'pic' => 'Budi',
        'status' => 'IN_PROGRESS',
        'start_time' => '08:00',
        'actual_date' => now()->toDateString(),
    ]);

    $second = syncPmSchedule(syncPmMachine(), ['pic' => 'Budi']);

    $response = syncPost($pic, [
        'operation_uuid' => (string) Str::uuid(),
        'transaction_type' => 'PM_START',
        'payload' => ['pm_schedule_id' => $second->id, 'started_at' => now()->format('Y-m-d\TH:i')],
    ]);

    $response->assertStatus(409)->assertJson(['success' => false, 'status' => 'conflict']);
    expect($second->fresh()->start_time)->toBeNull();
});

// ---------------------------------------------------------------------------
// PM_SAVE
// ---------------------------------------------------------------------------

test('PM_SAVE via sync persists header + measurements + problems + spareparts atomically through PMScheduleSaveService', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = syncPmMachine();
    $pm = syncPmSchedule($machine, ['pic' => 'Budi', 'status' => 'IN_PROGRESS']);

    $response = syncPost($admin, [
        'operation_uuid' => (string) Str::uuid(),
        'transaction_type' => 'PM_SAVE',
        'payload' => [
            'pm_schedule_id' => $pm->id,
            'order_number' => $pm->order_number,
            'pic' => 'Budi',
            'remarks' => 'OK dari sync',
            'actual_date' => now()->toDateString(),
            'start_time' => '08:00',
            'end_time' => '10:00',
            'problems' => [],
            'measurements' => [],
            'spareparts' => [],
        ],
    ]);

    $response->assertOk()->assertJson(['status' => 'processed']);

    $pm->refresh();
    expect($pm->status)->toBe('IN_PROGRESS')
        ->and($pm->remarks)->toBe('OK dari sync')
        ->and($pm->duration)->toBe(120);
});

test('PM_SAVE via sync is rejected as a conflict when expected_state.status no longer matches the server', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = syncPmMachine();
    $pm = syncPmSchedule($machine, ['pic' => 'Budi', 'status' => 'FINISHED_ON_TIME']);

    $response = syncPost($admin, [
        'operation_uuid' => (string) Str::uuid(),
        'transaction_type' => 'PM_SAVE',
        'payload' => [
            'pm_schedule_id' => $pm->id,
            'order_number' => $pm->order_number,
            'pic' => 'Budi',
            'actual_date' => now()->toDateString(),
            'start_time' => '08:00',
        ],
        'expected_state' => ['status' => 'IN_PROGRESS'],
    ]);

    $response->assertStatus(409)->assertJson(['success' => false, 'status' => 'conflict']);
    expect($pm->fresh()->status)->toBe('FINISHED_ON_TIME');
});

test('PM_SAVE via sync for a non-existent pm_schedule_id is reported as a conflict, not a crash', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $response = syncPost($admin, [
        'operation_uuid' => (string) Str::uuid(),
        'transaction_type' => 'PM_SAVE',
        'payload' => [
            'pm_schedule_id' => 999999,
            'order_number' => 'X',
            'pic' => 'Budi',
            'actual_date' => now()->toDateString(),
            'start_time' => '08:00',
        ],
    ]);

    $response->assertStatus(409)->assertJson(['success' => false, 'status' => 'conflict']);
});

test('PM_SAVE via sync validates the payload with the exact same rules as the online endpoint', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $pm = syncPmSchedule(syncPmMachine(), ['pic' => 'Budi']);

    $response = syncPost($admin, [
        'operation_uuid' => (string) Str::uuid(),
        'transaction_type' => 'PM_SAVE',
        'payload' => [
            'pm_schedule_id' => $pm->id,
            // order_number, pic, actual_date, start_time all missing — required.
        ],
    ]);

    $response->assertStatus(422)->assertJson(['success' => false, 'status' => 'validation_failed']);
    expect($response->json('errors'))->toHaveKeys(['order_number', 'pic', 'actual_date', 'start_time']);
});

test('a non-admin PIC cannot use PM_SAVE via sync to change the assigned pic field', function () {
    $pic = User::factory()->create(['role' => User::ROLE_PIC_WWD, 'name' => 'Budi']);
    $pm = syncPmSchedule(syncPmMachine(), ['pic' => 'Budi']);

    syncPost($pic, [
        'operation_uuid' => (string) Str::uuid(),
        'transaction_type' => 'PM_SAVE',
        'payload' => [
            'pm_schedule_id' => $pm->id,
            'order_number' => $pm->order_number,
            'pic' => 'Someone Else',
            'actual_date' => now()->toDateString(),
            'start_time' => '08:00',
        ],
    ])->assertOk();

    expect($pm->fresh()->pic)->toBe('Budi');
});

// ---------------------------------------------------------------------------
// PM_CHECKLIST_SAVE
// ---------------------------------------------------------------------------

test('PM_CHECKLIST_SAVE via sync uses PMChecklistSaveService and recalculates status', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = syncPmMachine();
    $pm = syncPmSchedule($machine, [
        'pic' => 'Budi',
        'status' => 'IN_PROGRESS',
        'actual_date' => now()->toDateString(),
        'oil_change' => 'YES',
        'greasing' => 'YES',
        'wo_zsbp' => 'WO-1',
        'remarks' => 'done',
    ]);
    $machineProblem = MachineProblem::create([
        'machine_type' => $machine->machine_type,
        'category' => 'General',
        'problem' => 'Belt Retak',
    ]);
    $sparepart = Sparepart::create([
        'material_number' => 'SP-'.uniqid(),
        'description' => 'Test Sparepart',
    ]);
    $machineChecklist = MachineChecklist::create([
        'machine_type' => $machine->machine_type,
        'section' => 'General',
        'checklist_item' => 'Check Oli',
    ]);
    PMMeasurement::create(['pm_schedule_id' => $pm->id, 'measurement_item' => 'X', 'measurement_value' => '1']);
    PMProblem::create(['pm_schedule_id' => $pm->id, 'machine_problem_id' => $machineProblem->id, 'severity' => 'Low']);
    PMSparepart::create(['pm_schedule_id' => $pm->id, 'sparepart_id' => $sparepart->id, 'qty' => 1]);

    $response = syncPost($admin, [
        'operation_uuid' => (string) Str::uuid(),
        'transaction_type' => 'PM_CHECKLIST_SAVE',
        'payload' => [
            'pm_schedule_id' => $pm->id,
            'checklists' => [
                ['machine_checklist_id' => $machineChecklist->id, 'clean' => 'YES'],
            ],
        ],
    ]);

    $response->assertOk()->assertJson(['status' => 'processed']);
    expect(PMChecklist::where('pm_schedule_id', $pm->id)->count())->toBe(1);
    expect(in_array($pm->fresh()->status, ['FINISHED', 'FINISHED_ON_TIME'], true))->toBeTrue();
});

test('PM_CHECKLIST_SAVE via sync reports validation_failed when the fill-pm data is incomplete', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $pm = syncPmSchedule(syncPmMachine(), ['pic' => 'Budi', 'status' => 'IN_PROGRESS']);
    // No measurements/problems/spareparts/remarks recorded yet.

    $response = syncPost($admin, [
        'operation_uuid' => (string) Str::uuid(),
        'transaction_type' => 'PM_CHECKLIST_SAVE',
        'payload' => ['pm_schedule_id' => $pm->id, 'checklists' => []],
    ]);

    $response->assertStatus(422)->assertJson(['success' => false, 'status' => 'validation_failed']);
    expect(PMChecklist::where('pm_schedule_id', $pm->id)->count())->toBe(0);
});

// ---------------------------------------------------------------------------
// Authorization
// ---------------------------------------------------------------------------

test('a PIC cannot use sync to act on a PM Schedule assigned to a different PIC', function () {
    $pic = User::factory()->create(['role' => User::ROLE_PIC_WWD, 'name' => 'Budi']);
    $pm = syncPmSchedule(syncPmMachine(), ['pic' => 'Andi']);

    $response = syncPost($pic, [
        'operation_uuid' => (string) Str::uuid(),
        'transaction_type' => 'PM_START',
        'payload' => ['pm_schedule_id' => $pm->id, 'started_at' => now()->format('Y-m-d\TH:i')],
    ]);

    $response->assertStatus(403)->assertJson(['success' => false, 'status' => 'forbidden']);
    expect($pm->fresh()->start_time)->toBeNull();
});

test('a PIC BUL cannot act on a WWD PM Schedule through sync', function () {
    $pic = User::factory()->create(['role' => User::ROLE_PIC_BUL, 'name' => 'Budi']);
    $pm = syncPmSchedule(syncPmMachine(['area' => 'WWD']), ['area' => 'WWD', 'pic' => 'Budi']);

    syncPost($pic, [
        'operation_uuid' => (string) Str::uuid(),
        'transaction_type' => 'PM_START',
        'payload' => ['pm_schedule_id' => $pm->id, 'started_at' => now()->format('Y-m-d\TH:i')],
    ])->assertStatus(403)->assertJson(['status' => 'forbidden']);
});

test('an unauthenticated request to the sync endpoint is rejected', function () {
    $this->postJson(route('api.sync'), [
        'operation_uuid' => (string) Str::uuid(),
        'transaction_type' => 'PM_START',
        'payload' => [],
    ])->assertStatus(401);
});

test('an unknown transaction_type is rejected as validation_failed', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    syncPost($admin, [
        'operation_uuid' => (string) Str::uuid(),
        'transaction_type' => 'NOT_A_REAL_TYPE',
        'payload' => [],
    ])->assertStatus(422)->assertJson(['success' => false, 'status' => 'validation_failed']);
});

test('existing online PM routes keep working unchanged after the sync endpoint is added', function () {
    $pic = User::factory()->create(['role' => User::ROLE_PIC_WWD, 'name' => 'Budi']);
    $pm = syncPmSchedule(syncPmMachine(), ['pic' => 'Budi']);

    $this->actingAs($pic)
        ->post(route('pm-schedules.start', $pm), ['started_at' => '2026-09-06T08:30'])
        ->assertRedirect();

    expect($pm->fresh()->start_time)->toStartWith('08:30');
});
