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
 * FreeDOMS offline-first — Task 6 (Implement Offline PM Checklist Save).
 *
 * Complements tests/Feature/SyncOperationPmTest.php (Task 2, basic
 * PM_CHECKLIST_SAVE success/validation-failed). This file proves the
 * guarantees Task 6 specifically requires: idempotency under retry,
 * payload-hash protection, aggregate integrity (one operation_uuid for
 * every checklist row, never one per row), the existing
 * actual_date-from-session + status-recalculation behaviour, multi-day
 * session compatibility, conflict detection, and authorization.
 */
function checklistMachine(array $overrides = []): Machine
{
    return Machine::create(array_merge([
        'machine_number' => 'MC-'.uniqid(),
        'area' => 'WWD',
        'machine_type' => 'NDE',
        'status' => 'ACTIVE',
    ], $overrides));
}

function checklistSchedule(Machine $machine, array $overrides = []): PMSchedule
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
        'due_date' => Carbon::parse($planDate)->addDays(30),
        'pic' => null,
        'status' => 'OPEN',
    ], $overrides));
}

/**
 * Fills in the completionErrors() prerequisites (oil_change/greasing/
 * wo_zsbp/remarks/problem/measurement/sparepart) so a checklist save is
 * actually allowed to proceed — mirrors what Fill PM (PM_SAVE) already
 * leaves behind before a real user reaches the checklist step.
 */
function makeChecklistEligible(PMSchedule $pm): void
{
    $machine = $pm->machine;
    $pm->update(['oil_change' => 'YES', 'greasing' => 'YES', 'wo_zsbp' => 'YES', 'remarks' => 'OK']);

    $mp = MachineProblem::create(['machine_type' => $machine->machine_type, 'category' => 'General', 'problem' => 'Belt Retak']);
    PMProblem::create(['pm_schedule_id' => $pm->id, 'machine_problem_id' => $mp->id, 'severity' => 'Low']);

    $mm = MachineMeasurement::create(['machine_type' => $machine->machine_type, 'measurement_item' => 'Vibration', 'unit' => 'mm/s']);
    PMMeasurement::create(['pm_schedule_id' => $pm->id, 'machine_measurement_id' => $mm->id, 'measurement_item' => 'Vibration', 'measurement_value' => '3', 'unit' => 'mm/s']);

    $sp = Sparepart::create(['material_number' => 'SP-'.uniqid(), 'description' => 'Test Sparepart']);
    PMSparepart::create(['pm_schedule_id' => $pm->id, 'sparepart_id' => $sp->id, 'qty' => 1]);
}

/** Mirrors resources/js/offline/pmChecklist.js's saveOffline() envelope exactly. */
function checklistPayload(int $pmScheduleId, string $uuid, array $checklists, string $expectedStatus = 'IN_PROGRESS'): array
{
    return [
        'operation_uuid' => $uuid,
        'transaction_type' => 'PM_CHECKLIST_SAVE',
        'payload' => ['pm_schedule_id' => $pmScheduleId, 'checklists' => $checklists],
        'expected_state' => ['status' => $expectedStatus],
    ];
}

test('PM_CHECKLIST_SAVE idempotency: retrying the same operation_uuid never duplicates checklist rows', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = checklistMachine();
    $pm = checklistSchedule($machine, ['pic' => 'Budi', 'status' => 'IN_PROGRESS', 'actual_date' => now()->toDateString()]);
    makeChecklistEligible($pm);
    $item1 = MachineChecklist::create(['machine_type' => $machine->machine_type, 'section' => 'General', 'checklist_item' => 'Check Oli']);
    $item2 = MachineChecklist::create(['machine_type' => $machine->machine_type, 'section' => 'General', 'checklist_item' => 'Clean Body']);

    $uuid = (string) Str::uuid();
    $payload = checklistPayload($pm->id, $uuid, [
        ['machine_checklist_id' => $item1->id, 'clean' => 'YES', 'check' => 'NO', 'lubrication' => 'NO', 'replace' => 'NO', 'remarks' => ''],
        ['machine_checklist_id' => $item2->id, 'clean' => 'NO', 'check' => 'YES', 'lubrication' => 'NO', 'replace' => 'NO', 'remarks' => 'catatan'],
    ]);

    $first = test()->actingAs($admin)->postJson(route('api.sync'), $payload);
    $first->assertOk()->assertJson(['status' => 'processed']);

    expect(PMChecklist::where('pm_schedule_id', $pm->id)->count())->toBe(2);

    $second = test()->actingAs($admin)->postJson(route('api.sync'), $payload);
    $third = test()->actingAs($admin)->postJson(route('api.sync'), $payload);

    $second->assertOk()->assertJson(['success' => true, 'status' => 'already_processed', 'operation_uuid' => $uuid]);
    $third->assertOk()->assertJson(['success' => true, 'status' => 'already_processed', 'operation_uuid' => $uuid]);

    // Still exactly 2 rows — retries never re-ran the delete+recreate a
    // second/third time.
    expect(PMChecklist::where('pm_schedule_id', $pm->id)->count())->toBe(2)
        ->and(SyncOperation::where('operation_uuid', $uuid)->count())->toBe(1);
});

test('PM_CHECKLIST_SAVE payload mismatch: same operation_uuid with different checklist content is rejected, not applied', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = checklistMachine();
    $pm = checklistSchedule($machine, ['pic' => 'Budi', 'status' => 'IN_PROGRESS', 'actual_date' => now()->toDateString()]);
    makeChecklistEligible($pm);
    $item = MachineChecklist::create(['machine_type' => $machine->machine_type, 'section' => 'General', 'checklist_item' => 'Check Oli']);
    $uuid = (string) Str::uuid();

    $requestA = checklistPayload($pm->id, $uuid, [
        ['machine_checklist_id' => $item->id, 'clean' => 'YES', 'check' => 'NO', 'lubrication' => 'NO', 'replace' => 'NO', 'remarks' => 'Versi A'],
    ]);
    $requestB = checklistPayload($pm->id, $uuid, [
        ['machine_checklist_id' => $item->id, 'clean' => 'NO', 'check' => 'YES', 'lubrication' => 'NO', 'replace' => 'NO', 'remarks' => 'Versi B'],
    ]);

    $first = test()->actingAs($admin)->postJson(route('api.sync'), $requestA);
    $first->assertOk();

    $second = test()->actingAs($admin)->postJson(route('api.sync'), $requestB);
    $second->assertStatus(409)->assertJson(['success' => false, 'status' => 'payload_mismatch', 'operation_uuid' => $uuid]);

    expect(PMChecklist::where('pm_schedule_id', $pm->id)->first()->remarks)->toBe('Versi A');
});

test('PM_CHECKLIST_SAVE aggregate integrity: one operation_uuid persists every checklist row together, never one operation per row', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = checklistMachine();
    $pm = checklistSchedule($machine, ['pic' => 'Budi', 'status' => 'IN_PROGRESS', 'actual_date' => now()->toDateString()]);
    makeChecklistEligible($pm);
    $items = collect(range(1, 4))->map(fn ($i) => MachineChecklist::create([
        'machine_type' => $machine->machine_type, 'section' => 'General', 'checklist_item' => "Item {$i}",
    ]));

    $uuid = (string) Str::uuid();
    $payload = checklistPayload($pm->id, $uuid, $items->map(fn ($item) => [
        'machine_checklist_id' => $item->id, 'clean' => 'YES', 'check' => 'NO', 'lubrication' => 'NO', 'replace' => 'NO', 'remarks' => '',
    ])->all());

    $response = test()->actingAs($admin)->postJson(route('api.sync'), $payload);

    $response->assertOk()->assertJson(['status' => 'processed']);

    expect(SyncOperation::where('operation_uuid', $uuid)->count())->toBe(1)
        ->and(PMChecklist::where('pm_schedule_id', $pm->id)->count())->toBe(4);
});

test('PM_CHECKLIST_SAVE via sync still sets actual_date from the last work session and recalculates status, same as online', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = checklistMachine();
    $pm = checklistSchedule($machine, [
        'pic' => 'Budi', 'status' => 'IN_PROGRESS',
        'due_date' => now()->addDays(30),
    ]);
    makeChecklistEligible($pm);
    PMWorkSession::create(['pm_schedule_id' => $pm->id, 'actual_date' => now()->subDays(2)->toDateString(), 'start_time' => '08:00', 'end_time' => '10:00', 'duration' => 120]);
    PMWorkSession::create(['pm_schedule_id' => $pm->id, 'actual_date' => now()->toDateString(), 'start_time' => '08:00', 'end_time' => '10:00', 'duration' => 120]);
    $item = MachineChecklist::create(['machine_type' => $machine->machine_type, 'section' => 'General', 'checklist_item' => 'Check Oli']);

    $response = test()->actingAs($admin)->postJson(route('api.sync'), checklistPayload($pm->id, (string) Str::uuid(), [
        ['machine_checklist_id' => $item->id, 'clean' => 'YES', 'check' => 'NO', 'lubrication' => 'NO', 'replace' => 'NO', 'remarks' => ''],
    ]));

    $response->assertOk();

    $pm->refresh();

    // actual_date was pulled from the LAST (latest) work session, exactly
    // like PMChecklistSaveService::save() does online.
    expect(Carbon::parse($pm->actual_date)->toDateString())->toBe(now()->toDateString())
        ->and($pm->status)->toBe('FINISHED_ON_TIME');
});

test('PM_CHECKLIST_SAVE via sync remains compatible with multi-day PM work sessions', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = checklistMachine();
    $pm = checklistSchedule($machine, ['pic' => 'Budi', 'status' => 'IN_PROGRESS']);
    makeChecklistEligible($pm);
    PMWorkSession::create(['pm_schedule_id' => $pm->id, 'actual_date' => '2026-09-10', 'start_time' => '08:00', 'end_time' => '12:00', 'duration' => 240]);
    PMWorkSession::create(['pm_schedule_id' => $pm->id, 'actual_date' => '2026-09-12', 'start_time' => '08:00', 'end_time' => '10:00', 'duration' => 120]);
    $item = MachineChecklist::create(['machine_type' => $machine->machine_type, 'section' => 'General', 'checklist_item' => 'Check Oli']);

    $response = test()->actingAs($admin)->postJson(route('api.sync'), checklistPayload($pm->id, (string) Str::uuid(), [
        ['machine_checklist_id' => $item->id, 'clean' => 'YES', 'check' => 'NO', 'lubrication' => 'NO', 'replace' => 'NO', 'remarks' => ''],
    ]));

    $response->assertOk();

    // actual_date follows the LATEST session date (2026-09-12), not the
    // first — multi-day sessions remain untouched/unre-implemented.
    expect(Carbon::parse($pm->fresh()->actual_date)->toDateString())->toBe('2026-09-12')
        ->and(PMWorkSession::where('pm_schedule_id', $pm->id)->count())->toBe(2);
});

test('PM_CHECKLIST_SAVE via sync is rejected as a conflict when the server status changed since the device last knew it', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = checklistMachine();
    $pm = checklistSchedule($machine, ['pic' => 'Budi', 'status' => 'FINISHED_ON_TIME', 'actual_date' => now()->toDateString()]);
    makeChecklistEligible($pm);
    $item = MachineChecklist::create(['machine_type' => $machine->machine_type, 'section' => 'General', 'checklist_item' => 'Check Oli']);

    $response = test()->actingAs($admin)->postJson(route('api.sync'), checklistPayload($pm->id, (string) Str::uuid(), [
        ['machine_checklist_id' => $item->id, 'clean' => 'YES', 'check' => 'NO', 'lubrication' => 'NO', 'replace' => 'NO', 'remarks' => ''],
    ], 'IN_PROGRESS'));

    $response->assertStatus(409)->assertJson(['success' => false, 'status' => 'conflict']);

    // No silent overwrite — no checklist row was created.
    expect(PMChecklist::where('pm_schedule_id', $pm->id)->count())->toBe(0);
});

test('an unauthorized PIC cannot use PM_CHECKLIST_SAVE via sync for a PM Schedule that is not theirs', function () {
    $pic = User::factory()->create(['role' => User::ROLE_PIC_WWD, 'name' => 'Budi']);
    $machine = checklistMachine();
    $pm = checklistSchedule($machine, ['pic' => 'Andi', 'status' => 'IN_PROGRESS']);
    makeChecklistEligible($pm);
    $item = MachineChecklist::create(['machine_type' => $machine->machine_type, 'section' => 'General', 'checklist_item' => 'Check Oli']);

    $response = test()->actingAs($pic)->postJson(route('api.sync'), checklistPayload($pm->id, (string) Str::uuid(), [
        ['machine_checklist_id' => $item->id, 'clean' => 'YES', 'check' => 'NO', 'lubrication' => 'NO', 'replace' => 'NO', 'remarks' => ''],
    ]));

    $response->assertStatus(403)->assertJson(['success' => false, 'status' => 'forbidden']);
    expect(PMChecklist::where('pm_schedule_id', $pm->id)->count())->toBe(0);
});

test('PM_CHECKLIST_SAVE via sync still reports validation_failed when Fill PM data is incomplete, same as online', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = checklistMachine();
    // Deliberately NOT made eligible — no problems/measurements/spareparts.
    $pm = checklistSchedule($machine, ['pic' => 'Budi', 'status' => 'IN_PROGRESS']);
    $item = MachineChecklist::create(['machine_type' => $machine->machine_type, 'section' => 'General', 'checklist_item' => 'Check Oli']);

    $response = test()->actingAs($admin)->postJson(route('api.sync'), checklistPayload($pm->id, (string) Str::uuid(), [
        ['machine_checklist_id' => $item->id, 'clean' => 'YES', 'check' => 'NO', 'lubrication' => 'NO', 'replace' => 'NO', 'remarks' => ''],
    ]));

    $response->assertStatus(422)->assertJson(['success' => false, 'status' => 'validation_failed']);
    expect(PMChecklist::where('pm_schedule_id', $pm->id)->count())->toBe(0);
});
