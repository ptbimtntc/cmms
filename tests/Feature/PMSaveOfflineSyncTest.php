<?php

use App\Models\Machine;
use App\Models\MachineChecklist;
use App\Models\MachineMeasurement;
use App\Models\MachineProblem;
use App\Models\PMChecklist;
use App\Models\PMMeasurement;
use App\Models\PMProblem;
use App\Models\PMSchedule;
use App\Models\PMSparepart;
use App\Models\PMWorkSession;
use App\Models\Sparepart;
use App\Models\SyncOperation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Str;

/**
 * FreeDOMS offline-first — Task 5 (Implement Offline PM Save).
 *
 * Complements tests/Feature/SyncOperationPmTest.php (Task 2, basic PM_SAVE
 * success/conflict/authorization) and SyncOperationIdempotencyTest.php
 * (Task 2, generic rollback-on-failure proof already using PM_SAVE as its
 * example). This file proves the guarantees Task 5 specifically requires:
 * PM_SAVE idempotency under retry, payload-hash protection, aggregate
 * integrity (one operation_uuid covers header+sessions+measurements+
 * problems+spareparts, never one per child record), multi-day sessions,
 * gearbox computation, and that PM_CHECKLIST_SAVE is never touched.
 */
function pmSaveMachine(array $overrides = []): Machine
{
    return Machine::create(array_merge([
        'machine_number' => 'MC-'.uniqid(),
        'area' => 'WWD',
        'machine_type' => 'NDE',
        'status' => 'ACTIVE',
    ], $overrides));
}

function pmSaveSchedule(Machine $machine, array $overrides = []): PMSchedule
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

/** Mirrors resources/js/offline/pmSave.js's saveOffline() envelope exactly. */
function pmSavePayload(int $pmScheduleId, string $uuid, array $payload, string $expectedStatus = 'OPEN'): array
{
    return [
        'operation_uuid' => $uuid,
        'transaction_type' => 'PM_SAVE',
        'payload' => array_merge(['pm_schedule_id' => $pmScheduleId], $payload),
        'expected_state' => ['status' => $expectedStatus],
    ];
}

test('PM_SAVE idempotency: retrying the same operation_uuid never duplicates measurements/problems/spareparts', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = pmSaveMachine();
    $pm = pmSaveSchedule($machine, ['pic' => 'Budi']);
    $machineMeasurement = MachineMeasurement::create([
        'machine_type' => $machine->machine_type, 'measurement_item' => 'Vibration', 'unit' => 'mm/s',
    ]);
    $machineProblem = MachineProblem::create([
        'machine_type' => $machine->machine_type, 'category' => 'General', 'problem' => 'Belt Retak',
    ]);
    $sparepart = Sparepart::create(['material_number' => 'SP-'.uniqid(), 'description' => 'Test Sparepart']);

    $uuid = (string) Str::uuid();
    $payload = pmSavePayload($pm->id, $uuid, [
        'order_number' => $pm->order_number,
        'pic' => 'Budi',
        'actual_date' => now()->toDateString(),
        'start_time' => '08:00',
        'end_time' => '10:00',
        'measurements' => [['machine_measurement_id' => $machineMeasurement->id, 'measurement_item' => 'Vibration', 'standard' => '<=5', 'measurement_value' => '3', 'unit' => 'mm/s']],
        'problems' => [['problem' => $machineProblem->id, 'finding' => null, 'severity' => 'Low']],
        'spareparts' => [['sparepart_id' => $sparepart->id, 'qty' => 2]],
    ]);

    $first = test()->actingAs($admin)->postJson(route('api.sync'), $payload);
    $first->assertOk()->assertJson(['status' => 'processed']);

    expect(PMMeasurement::where('pm_schedule_id', $pm->id)->count())->toBe(1)
        ->and(PMProblem::where('pm_schedule_id', $pm->id)->count())->toBe(1)
        ->and(PMSparepart::where('pm_schedule_id', $pm->id)->count())->toBe(1);

    // Retry twice — the exact same request (e.g. the queue's response was
    // lost and it retries with the SAME operation_uuid).
    $second = test()->actingAs($admin)->postJson(route('api.sync'), $payload);
    $third = test()->actingAs($admin)->postJson(route('api.sync'), $payload);

    $second->assertOk()->assertJson(['success' => true, 'status' => 'already_processed', 'operation_uuid' => $uuid]);
    $third->assertOk()->assertJson(['success' => true, 'status' => 'already_processed', 'operation_uuid' => $uuid]);

    // Still exactly ONE of each child record — retries never re-ran
    // PMScheduleSaveService's delete+recreate a second/third time.
    expect(PMMeasurement::where('pm_schedule_id', $pm->id)->count())->toBe(1)
        ->and(PMProblem::where('pm_schedule_id', $pm->id)->count())->toBe(1)
        ->and(PMSparepart::where('pm_schedule_id', $pm->id)->count())->toBe(1)
        ->and(SyncOperation::where('operation_uuid', $uuid)->count())->toBe(1);
});

test('PM_SAVE payload mismatch: same operation_uuid with different payload content is rejected, not applied', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $pm = pmSaveSchedule(pmSaveMachine(), ['pic' => 'Budi']);
    $uuid = (string) Str::uuid();

    $requestA = pmSavePayload($pm->id, $uuid, [
        'order_number' => $pm->order_number, 'pic' => 'Budi', 'actual_date' => now()->toDateString(), 'start_time' => '08:00', 'remarks' => 'Versi A',
    ]);
    $requestB = pmSavePayload($pm->id, $uuid, [
        'order_number' => $pm->order_number, 'pic' => 'Budi', 'actual_date' => now()->toDateString(), 'start_time' => '08:00', 'remarks' => 'Versi B — payload berbeda',
    ]);

    $first = test()->actingAs($admin)->postJson(route('api.sync'), $requestA);
    $first->assertOk();

    $second = test()->actingAs($admin)->postJson(route('api.sync'), $requestB);
    $second->assertStatus(409)->assertJson(['success' => false, 'status' => 'payload_mismatch', 'operation_uuid' => $uuid]);

    // The mismatched retry never touched the PM — remarks is still "Versi A".
    expect($pm->fresh()->remarks)->toBe('Versi A');
});

test('PM_SAVE aggregate integrity: one operation_uuid persists header + sessions + measurements + problems + spareparts together, never as separate operations', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = pmSaveMachine();
    $pm = pmSaveSchedule($machine, ['pic' => 'Budi']);
    $mm1 = MachineMeasurement::create(['machine_type' => $machine->machine_type, 'measurement_item' => 'Vibration', 'unit' => 'mm/s']);
    $mm2 = MachineMeasurement::create(['machine_type' => $machine->machine_type, 'measurement_item' => 'Temperature', 'unit' => 'C']);
    $mp1 = MachineProblem::create(['machine_type' => $machine->machine_type, 'category' => 'General', 'problem' => 'Belt Retak']);
    $mp2 = MachineProblem::create(['machine_type' => $machine->machine_type, 'category' => 'General', 'problem' => 'Bearing Aus']);
    $sp1 = Sparepart::create(['material_number' => 'SP-'.uniqid(), 'description' => 'Sparepart 1']);
    $sp2 = Sparepart::create(['material_number' => 'SP-'.uniqid(), 'description' => 'Sparepart 2']);

    $uuid = (string) Str::uuid();
    $payload = pmSavePayload($pm->id, $uuid, [
        'order_number' => $pm->order_number,
        'pic' => 'Budi',
        'sessions' => [
            ['actual_date' => '2026-09-10', 'start_time' => '08:00', 'end_time' => '12:00'],
            ['actual_date' => '2026-09-11', 'start_time' => '08:00', 'end_time' => '10:00'],
        ],
        'measurements' => [
            ['machine_measurement_id' => $mm1->id, 'measurement_item' => 'Vibration', 'standard' => null, 'measurement_value' => '3', 'unit' => 'mm/s'],
            ['machine_measurement_id' => $mm2->id, 'measurement_item' => 'Temperature', 'standard' => null, 'measurement_value' => '40', 'unit' => 'C'],
        ],
        'problems' => [
            ['problem' => $mp1->id, 'finding' => null, 'severity' => 'Low'],
            ['problem' => $mp2->id, 'finding' => null, 'severity' => 'High'],
        ],
        'spareparts' => [
            ['sparepart_id' => $sp1->id, 'qty' => 1],
            ['sparepart_id' => $sp2->id, 'qty' => 3],
        ],
    ]);

    $response = test()->actingAs($admin)->postJson(route('api.sync'), $payload);

    $response->assertOk()->assertJson(['status' => 'processed']);

    // Exactly ONE sync_operations row for this whole aggregate — not one
    // per child record.
    expect(SyncOperation::where('operation_uuid', $uuid)->count())->toBe(1);

    expect(PMWorkSession::where('pm_schedule_id', $pm->id)->count())->toBe(2)
        ->and(PMMeasurement::where('pm_schedule_id', $pm->id)->count())->toBe(2)
        ->and(PMProblem::where('pm_schedule_id', $pm->id)->count())->toBe(2)
        ->and(PMSparepart::where('pm_schedule_id', $pm->id)->count())->toBe(2);
});

test('PM_SAVE via sync supports multi-day work sessions exactly like the online endpoint', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $pm = pmSaveSchedule(pmSaveMachine(), ['pic' => 'Budi']);

    $response = test()->actingAs($admin)->postJson(route('api.sync'), pmSavePayload($pm->id, (string) Str::uuid(), [
        'order_number' => $pm->order_number,
        'pic' => 'Budi',
        'sessions' => [
            ['actual_date' => '2026-09-10', 'start_time' => '08:00', 'end_time' => '12:00'],
            ['actual_date' => '2026-09-11', 'start_time' => '13:00', 'end_time' => '15:30'],
        ],
    ]));

    $response->assertOk();

    $sessions = PMWorkSession::where('pm_schedule_id', $pm->id)->orderBy('actual_date')->get();

    expect($sessions)->toHaveCount(2)
        ->and($sessions[0]->duration)->toBe(240) // 08:00-12:00
        ->and($sessions[1]->duration)->toBe(150); // 13:00-15:30

    // Legacy single-day columns are left untouched for multi-day saves —
    // same semantics as PMScheduleSaveService::save() online.
    expect($pm->fresh()->duration)->toBeNull();
});

test('PM_SAVE via sync computes gearbox_problem the same way as online, from problem keywords', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = pmSaveMachine(['area' => 'WWD']);
    $pm = pmSaveSchedule($machine, ['area' => 'WWD', 'pic' => 'Budi']);
    $mainshaftProblem = MachineProblem::create([
        'machine_type' => $machine->machine_type, 'category' => 'Mainshaft WWD', 'problem' => 'Mainshaft',
    ]);

    $response = test()->actingAs($admin)->postJson(route('api.sync'), pmSavePayload($pm->id, (string) Str::uuid(), [
        'order_number' => $pm->order_number,
        'pic' => 'Budi',
        'actual_date' => now()->toDateString(),
        'start_time' => '08:00',
        'problems' => [['problem' => $mainshaftProblem->id, 'finding' => null, 'severity' => 'High']],
    ]));

    $response->assertOk();
    expect($pm->fresh()->gearbox_problem)->toBe('YES');
});

test('PM_SAVE via sync never touches PM Checklist data — PM_SAVE and PM_CHECKLIST_SAVE stay fully separate', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $pm = pmSaveSchedule(pmSaveMachine(), ['pic' => 'Budi', 'status' => 'IN_PROGRESS']);
    // A checklist already exists for this PM (as if the online checklist
    // step had already run before this offline PM_SAVE operation syncs).
    PMChecklist::create(['pm_schedule_id' => $pm->id, 'machine_checklist_id' => MachineChecklist::create([
        'machine_type' => $pm->machine_type, 'section' => 'General', 'checklist_item' => 'Check Oli',
    ])->id, 'clean' => 'YES']);

    $response = test()->actingAs($admin)->postJson(route('api.sync'), pmSavePayload($pm->id, (string) Str::uuid(), [
        'order_number' => $pm->order_number, 'pic' => 'Budi', 'actual_date' => now()->toDateString(), 'start_time' => '08:00',
    ], 'IN_PROGRESS'));

    $response->assertOk()->assertJson(['status' => 'processed']);

    // The checklist row PM_SAVE never touches stays exactly as it was.
    expect(PMChecklist::where('pm_schedule_id', $pm->id)->count())->toBe(1);
});

test('an unauthorized PIC cannot use PM_SAVE via sync to save a PM Schedule that is not theirs', function () {
    $pic = User::factory()->create(['role' => User::ROLE_PIC_WWD, 'name' => 'Budi']);
    $pm = pmSaveSchedule(pmSaveMachine(), ['pic' => 'Andi']);

    $response = test()->actingAs($pic)->postJson(route('api.sync'), pmSavePayload($pm->id, (string) Str::uuid(), [
        'order_number' => $pm->order_number, 'pic' => 'Andi', 'actual_date' => now()->toDateString(), 'start_time' => '08:00',
    ]));

    $response->assertStatus(403)->assertJson(['success' => false, 'status' => 'forbidden']);
    expect($pm->fresh()->remarks)->toBeNull();
});
