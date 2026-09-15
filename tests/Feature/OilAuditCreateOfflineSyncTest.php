<?php

use App\Models\Machine;
use App\Models\OilAudit;
use App\Models\SyncOperation;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * FreeDOMS offline-first — Task 7 (Implement Offline Oil Audit Create).
 *
 * Complements tests/Feature/SyncOperationOilAuditTest.php (Task 2 — basic
 * OIL_AUDIT_CREATE success/authorship/scope/authorization) and
 * SyncOperationIdempotencyTest.php (Task 2 — generic idempotency/
 * payload-mismatch/race/rollback proofs already using OIL_AUDIT_CREATE as
 * their example). This file proves the two guarantees specific to Task 7:
 * the machine SNAPSHOT (machine_number/machine_type/area) always comes
 * from the server's own Machine record — never anything a client could
 * smuggle in — and that the exact minimal envelope
 * resources/js/offline/oilAuditCreate.js actually sends is idempotent and
 * duplicate-proof end to end.
 */
function offlineOaMachine(array $overrides = []): Machine
{
    return Machine::create(array_merge([
        'machine_number' => 'MC-'.uniqid(),
        'area' => 'WWD',
        'machine_type' => 'NDE',
        'status' => 'ACTIVE',
    ], $overrides));
}

/** Mirrors resources/js/offline/oilAuditCreate.js's saveOffline() envelope exactly. */
function offlineOaPayload(int $machineId, string $uuid, string $condition = 'OKE'): array
{
    return [
        'operation_uuid' => $uuid,
        'transaction_type' => 'OIL_AUDIT_CREATE',
        'payload' => ['machine_id' => $machineId, 'condition' => $condition],
        'expected_state' => [],
    ];
}

test('the stored audit\'s machine snapshot always comes from the server\'s Machine record, never anything the client could send', function () {
    $pic = User::factory()->create(['role' => User::ROLE_PIC_WWD, 'name' => 'Budi']);
    $machine = offlineOaMachine(['machine_number' => 'REAL-001', 'machine_type' => 'NDE', 'area' => 'WWD']);

    $payload = offlineOaPayload($machine->id, (string) Str::uuid(), 'KRITIS');
    // Attempt to smuggle a different snapshot in — the payload envelope
    // (see PM_SAVE/PM_CHECKLIST_SAVE precedent) only ever carries
    // machine_id/condition, so these extra keys are simply never read by
    // OilAuditCreateService::create(), which takes a resolved Machine
    // model, not an array.
    $payload['payload']['machine_number'] = 'FAKE-999';
    $payload['payload']['machine_type'] = 'BF';
    $payload['payload']['area'] = 'BUL';

    $response = test()->actingAs($pic)->postJson(route('api.sync'), $payload);

    $response->assertOk()->assertJson(['status' => 'processed']);

    $audit = OilAudit::first();

    expect($audit->machine_number)->toBe('REAL-001')
        ->and($audit->machine_type)->toBe('NDE')
        ->and($audit->area)->toBe('WWD');
});

test('OIL_AUDIT_CREATE idempotency with the exact envelope pmSave.js-style clients send: retry never creates a second audit', function () {
    $pic = User::factory()->create(['role' => User::ROLE_PIC_WWD, 'name' => 'Budi']);
    $machine = offlineOaMachine();
    $uuid = (string) Str::uuid();
    $payload = offlineOaPayload($machine->id, $uuid, 'PANTAU');

    $first = test()->actingAs($pic)->postJson(route('api.sync'), $payload);
    $first->assertOk()->assertJson(['status' => 'processed']);

    expect(OilAudit::count())->toBe(1);

    // Simulates the response-lost-after-commit scenario (section 10/18):
    // the device retries with the SAME operation_uuid.
    $second = test()->actingAs($pic)->postJson(route('api.sync'), $payload);
    $third = test()->actingAs($pic)->postJson(route('api.sync'), $payload);

    $second->assertOk()->assertJson(['success' => true, 'status' => 'already_processed', 'operation_uuid' => $uuid]);
    $third->assertOk()->assertJson(['success' => true, 'status' => 'already_processed', 'operation_uuid' => $uuid]);

    expect(OilAudit::count())->toBe(1)
        ->and(SyncOperation::where('operation_uuid', $uuid)->count())->toBe(1);
});

test('a machine_id that does not exist at all (not just out of scope) is rejected as validation_failed, not a crash', function () {
    $pic = User::factory()->create(['role' => User::ROLE_PIC_WWD, 'name' => 'Budi']);

    $response = test()->actingAs($pic)->postJson(route('api.sync'), offlineOaPayload(999999, (string) Str::uuid()));

    $response->assertStatus(422)->assertJson(['success' => false, 'status' => 'validation_failed']);
    expect(OilAudit::count())->toBe(0);
});

test('an invalid condition value is rejected as validation_failed', function () {
    $pic = User::factory()->create(['role' => User::ROLE_PIC_WWD, 'name' => 'Budi']);
    $machine = offlineOaMachine();

    $response = test()->actingAs($pic)->postJson(route('api.sync'), offlineOaPayload($machine->id, (string) Str::uuid(), 'NOT_A_REAL_CONDITION'));

    $response->assertStatus(422)->assertJson(['success' => false, 'status' => 'validation_failed']);
    expect(OilAudit::count())->toBe(0);
});

test('an unauthenticated request to create an Oil Audit via sync is rejected — guests cannot write', function () {
    $machine = offlineOaMachine();

    test()->postJson(route('api.sync'), offlineOaPayload($machine->id, (string) Str::uuid()))
        ->assertStatus(401);

    expect(OilAudit::count())->toBe(0);
});

test('existing online Oil Audit create workflow keeps working unchanged after Task 7', function () {
    $pic = User::factory()->create(['role' => User::ROLE_PIC_WWD, 'name' => 'Budi']);
    $machine = offlineOaMachine();

    test()->actingAs($pic)
        ->post(route('oil-audits.store'), ['machine_id' => $machine->id, 'condition' => 'OKE'])
        ->assertRedirect(route('oil-audits.scan'));

    expect(OilAudit::count())->toBe(1)
        ->and(OilAudit::first()->audited_by_name)->toBe('Budi');
});

test('the scan page embeds the offline machine cache but never changes its own authorization/visible behavior', function () {
    $pic = User::factory()->create(['role' => User::ROLE_PIC_WWD, 'name' => 'Budi']);
    offlineOaMachine(['machine_number' => 'CACHE-001']);
    offlineOaMachine(['area' => 'BUL', 'machine_type' => 'BF', 'machine_number' => 'OUT-OF-SCOPE']);

    $response = test()->actingAs($pic)->get(route('oil-audits.scan'));

    $response->assertOk()
        ->assertSee('CACHE-001')
        ->assertDontSee('OUT-OF-SCOPE');
});
