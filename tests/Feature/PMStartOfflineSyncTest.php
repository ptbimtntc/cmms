<?php

use App\Models\Machine;
use App\Models\PMSchedule;
use App\Models\SyncOperation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Str;

/**
 * FreeDOMS offline-first — Task 4 (Implement Offline PM Start).
 *
 * Complements tests/Feature/SyncOperationPmTest.php (Task 2), which
 * already covers PM_START's basic success/conflict/activity-conflict
 * paths through /api/sync. This file proves the two guarantees Task 4
 * specifically requires for PM_START: idempotency under retry (section
 * 13/24-F) and payload-hash protection against a mismatched retry
 * (section 24-G) — using the EXACT payload/expected_state shape
 * resources/js/offline/pmStart.js actually sends.
 */
function offlineStartMachine(array $overrides = []): Machine
{
    return Machine::create(array_merge([
        'machine_number' => 'MC-'.uniqid(),
        'area' => 'WWD',
        'machine_type' => 'NDE',
        'status' => 'ACTIVE',
    ], $overrides));
}

function offlineStartPmSchedule(Machine $machine, array $overrides = []): PMSchedule
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

/** Mirrors resources/js/offline/pmStart.js's startOffline() payload exactly. */
function offlineStartPayload(int $pmScheduleId, string $uuid, string $startedAtLocal = '2026-09-14T08:30'): array
{
    return [
        'operation_uuid' => $uuid,
        'transaction_type' => 'PM_START',
        'payload' => [
            'pm_schedule_id' => $pmScheduleId,
            'started_at' => $startedAtLocal,
            'confirm_end_start' => false,
        ],
        'expected_state' => [
            'status' => 'OPEN',
            'is_started' => false,
        ],
    ];
}

test('PM_START idempotency: retrying the same operation_uuid never starts the PM a second time or moves start_time', function () {
    $pic = User::factory()->create(['role' => User::ROLE_PIC_WWD, 'name' => 'Budi']);
    $pm = offlineStartPmSchedule(offlineStartMachine(), ['pic' => 'Budi']);
    $uuid = (string) Str::uuid();
    $payload = offlineStartPayload($pm->id, $uuid);

    $first = test()->actingAs($pic)->postJson(route('api.sync'), $payload);
    $first->assertOk()->assertJson(['status' => 'processed']);

    $pm->refresh();
    $startTimeAfterFirst = $pm->start_time;
    $actualDateAfterFirst = $pm->actual_date;

    expect($startTimeAfterFirst)->not->toBeNull();

    // Retry #1 and retry #2 — the same uuid, same payload, sent again (the
    // JS queue keeps retrying an entry that's still `pending`/`syncing`
    // until it sees processed/already_processed).
    $second = test()->actingAs($pic)->postJson(route('api.sync'), $payload);
    $third = test()->actingAs($pic)->postJson(route('api.sync'), $payload);

    $second->assertOk()->assertJson(['success' => true, 'status' => 'already_processed', 'operation_uuid' => $uuid]);
    $third->assertOk()->assertJson(['success' => true, 'status' => 'already_processed', 'operation_uuid' => $uuid]);

    $pm->refresh();

    // start_time/actual_date are UNCHANGED by the retries — PMStartService
    // was never invoked again (the sync ledger short-circuited it).
    expect($pm->start_time)->toBe($startTimeAfterFirst)
        ->and($pm->actual_date)->toBe($actualDateAfterFirst);

    expect(SyncOperation::where('operation_uuid', $uuid)->count())->toBe(1);
});

test('PM_START payload mismatch: same operation_uuid but a different pm_schedule_id is rejected, not started', function () {
    $pic = User::factory()->create(['role' => User::ROLE_PIC_WWD, 'name' => 'Budi']);
    $pmA = offlineStartPmSchedule(offlineStartMachine(), ['pic' => 'Budi']);
    $pmB = offlineStartPmSchedule(offlineStartMachine(), ['pic' => 'Budi']);
    $uuid = (string) Str::uuid();

    $requestA = offlineStartPayload($pmA->id, $uuid);
    $requestB = offlineStartPayload($pmB->id, $uuid);

    $first = test()->actingAs($pic)->postJson(route('api.sync'), $requestA);
    $first->assertOk()->assertJson(['status' => 'processed']);

    $second = test()->actingAs($pic)->postJson(route('api.sync'), $requestB);
    $second->assertStatus(409)->assertJson(['success' => false, 'status' => 'payload_mismatch', 'operation_uuid' => $uuid]);

    // PM A was started (the first, legitimate request); PM B was NEVER
    // touched by the mismatched retry.
    expect($pmA->fresh()->start_time)->not->toBeNull()
        ->and($pmB->fresh()->start_time)->toBeNull();
});

test('PM_START via sync sends exactly the payload shape pmStart.js produces, including confirm_end_start=false', function () {
    $pic = User::factory()->create(['role' => User::ROLE_PIC_WWD, 'name' => 'Budi']);
    $pm = offlineStartPmSchedule(offlineStartMachine(), ['pic' => 'Budi']);

    $response = test()->actingAs($pic)->postJson(route('api.sync'), offlineStartPayload($pm->id, (string) Str::uuid(), '2026-09-14T08:30'));

    $response->assertOk()->assertJson(['status' => 'processed']);

    $pm->refresh();

    expect($pm->start_time)->toStartWith('08:30')
        ->and(Carbon::parse($pm->actual_date)->toDateString())->toBe('2026-09-14')
        ->and($pm->status)->toBe('IN_PROGRESS');
});

test('PM_START via sync still enforces one-active-activity-per-PIC when the operation is finally synced', function () {
    $pic = User::factory()->create(['role' => User::ROLE_PIC_WWD, 'name' => 'Budi']);

    // PIC already has an active PM (started online/earlier) by the time
    // the offline-queued operation for a DIFFERENT PM finally syncs.
    offlineStartPmSchedule(offlineStartMachine(), [
        'pic' => 'Budi',
        'status' => 'IN_PROGRESS',
        'start_time' => '07:00',
        'actual_date' => now()->toDateString(),
    ]);

    $offlinePm = offlineStartPmSchedule(offlineStartMachine(), ['pic' => 'Budi']);

    $response = test()->actingAs($pic)->postJson(
        route('api.sync'),
        offlineStartPayload($offlinePm->id, (string) Str::uuid(), now()->format('Y-m-d\TH:i'))
    );

    $response->assertStatus(409)->assertJson(['success' => false, 'status' => 'conflict']);

    // Not force-started — the existing activity-conflict rule (Task 1/2)
    // still wins even though this arrived through the sync queue.
    expect($offlinePm->fresh()->start_time)->toBeNull();
});
