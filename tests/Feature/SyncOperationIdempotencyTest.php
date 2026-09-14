<?php

use App\Http\Controllers\SyncOperationController;
use App\Models\Machine;
use App\Models\OilAudit;
use App\Models\PMMeasurement;
use App\Models\PMSchedule;
use App\Models\SyncOperation;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;

function idemMachine(array $overrides = []): Machine
{
    return Machine::create(array_merge([
        'machine_number' => 'MC-'.uniqid(),
        'area' => 'WWD',
        'machine_type' => 'NDE',
        'status' => 'ACTIVE',
    ], $overrides));
}

function idemAdmin(): User
{
    return User::factory()->create(['role' => User::ROLE_ADMIN]);
}

function idemOilAuditCreatePayload(Machine $machine, string $uuid, string $condition = 'OKE'): array
{
    return [
        'operation_uuid' => $uuid,
        'transaction_type' => 'OIL_AUDIT_CREATE',
        'payload' => [
            'machine_id' => $machine->id,
            'condition' => $condition,
        ],
    ];
}

test('a brand new operation is processed successfully', function () {
    $admin = idemAdmin();
    $machine = idemMachine();
    $uuid = (string) Str::uuid();

    $response = $this->actingAs($admin)
        ->postJson(route('api.sync'), idemOilAuditCreatePayload($machine, $uuid));

    $response->assertOk()
        ->assertJson(['success' => true, 'status' => 'processed', 'operation_uuid' => $uuid]);

    expect(OilAudit::count())->toBe(1);

    $syncOp = SyncOperation::where('operation_uuid', $uuid)->first();
    expect($syncOp)->not->toBeNull()
        ->and($syncOp->processed_at)->not->toBeNull()
        ->and($syncOp->transaction_type)->toBe('OIL_AUDIT_CREATE')
        ->and($syncOp->subject_type)->not->toBeNull()
        ->and((int) $syncOp->subject_id)->toBe(OilAudit::first()->id);
});

test('sending the same operation_uuid twice with the same payload does not create duplicate business data', function () {
    $admin = idemAdmin();
    $machine = idemMachine();
    $uuid = (string) Str::uuid();
    $payload = idemOilAuditCreatePayload($machine, $uuid);

    $first = $this->actingAs($admin)->postJson(route('api.sync'), $payload);
    $second = $this->actingAs($admin)->postJson(route('api.sync'), $payload);

    $first->assertOk()->assertJson(['status' => 'processed']);
    $second->assertOk()->assertJson(['success' => true, 'status' => 'already_processed', 'operation_uuid' => $uuid]);

    // Only ONE OilAudit row exists — the retry did not re-run the business
    // service, only looked up the already-recorded result.
    expect(OilAudit::count())->toBe(1)
        ->and(SyncOperation::where('operation_uuid', $uuid)->count())->toBe(1);
});

test('the second response for a retried operation reports the same subject as the first', function () {
    $admin = idemAdmin();
    $machine = idemMachine();
    $uuid = (string) Str::uuid();
    $payload = idemOilAuditCreatePayload($machine, $uuid);

    $first = $this->actingAs($admin)->postJson(route('api.sync'), $payload);
    $second = $this->actingAs($admin)->postJson(route('api.sync'), $payload);

    expect($second->json('subject_id'))->toBe($first->json('subject_id'))
        ->and($second->json('subject_type'))->toBe($first->json('subject_type'));
});

test('the same operation_uuid with a DIFFERENT payload is rejected and never runs the business logic again', function () {
    $admin = idemAdmin();
    $machineA = idemMachine();
    $machineB = idemMachine();
    $uuid = (string) Str::uuid();

    $first = $this->actingAs($admin)
        ->postJson(route('api.sync'), idemOilAuditCreatePayload($machineA, $uuid, 'OKE'));

    // Same uuid, different payload (different condition this time).
    $second = $this->actingAs($admin)
        ->postJson(route('api.sync'), idemOilAuditCreatePayload($machineA, $uuid, 'KRITIS'));

    $first->assertOk();
    $second->assertStatus(409)
        ->assertJson(['success' => false, 'status' => 'payload_mismatch', 'operation_uuid' => $uuid]);

    // Only the FIRST payload's audit exists — the mismatched retry never
    // touched business data.
    expect(OilAudit::count())->toBe(1)
        ->and(OilAudit::first()->condition)->toBe('OKE');
});

test('operation_uuid has a real unique constraint at the database level', function () {
    $admin = idemAdmin();
    $uuid = (string) Str::uuid();

    SyncOperation::create([
        'operation_uuid' => $uuid,
        'transaction_type' => 'OIL_AUDIT_CREATE',
        'created_by_user_id' => $admin->id,
        'payload_hash' => 'x',
    ]);

    expect(fn () => SyncOperation::create([
        'operation_uuid' => $uuid,
        'transaction_type' => 'OIL_AUDIT_CREATE',
        'created_by_user_id' => $admin->id,
        'payload_hash' => 'y',
    ]))->toThrow(UniqueConstraintViolationException::class);
});

test('a request that races an in-flight (not-yet-committed) operation is told to retry, not treated as success or failure', function () {
    $admin = idemAdmin();
    $machine = idemMachine();
    $uuid = (string) Str::uuid();
    $payload = idemOilAuditCreatePayload($machine, $uuid);

    // Simulate the split second where another request's transaction has
    // inserted the ledger row but not committed the business write yet —
    // exactly what the unique index makes a genuinely concurrent duplicate
    // request observe (see section 14 of the task brief).
    SyncOperation::create([
        'operation_uuid' => $uuid,
        'transaction_type' => 'OIL_AUDIT_CREATE',
        'created_by_user_id' => $admin->id,
        'payload_hash' => SyncOperationController::hashPayload($payload['payload']),
        'processed_at' => null,
    ]);

    $response = $this->actingAs($admin)->postJson(route('api.sync'), $payload);

    $response->assertStatus(409)->assertJson(['success' => false, 'status' => 'in_progress']);

    // The concurrent request must NOT have run the business service itself.
    expect(OilAudit::count())->toBe(0);
});

test('a business failure mid-transaction rolls back both the business data and the sync ledger row, and the operation stays retryable', function () {
    $admin = idemAdmin();
    $machine = idemMachine();
    $pmSchedule = PMSchedule::create([
        'machine_id' => $machine->id,
        'machine_number' => $machine->machine_number,
        'machine_type' => $machine->machine_type,
        'area' => $machine->area,
        'order_number' => 'ORD-'.uniqid(),
        'plan_date' => now()->toDateString(),
        'plan_month' => now()->format('F'),
        'plan_year' => now()->format('Y'),
        'due_date' => now()->addDays(14),
        'pic' => 'Budi',
        'status' => 'OPEN',
    ]);
    $uuid = (string) Str::uuid();

    // machine_measurement_id references a row that does not exist — this
    // passes validation (that field has no exists: rule, mirroring the
    // online form) but violates the pm_measurements FK constraint deep
    // inside PMScheduleSaveService, well after the DB transaction opened.
    $badPayload = [
        'operation_uuid' => $uuid,
        'transaction_type' => 'PM_SAVE',
        'payload' => [
            'pm_schedule_id' => $pmSchedule->id,
            'order_number' => $pmSchedule->order_number,
            'pic' => 'Budi',
            'actual_date' => now()->toDateString(),
            'start_time' => '08:00',
            'end_time' => '10:00',
            'measurements' => [
                [
                    'machine_measurement_id' => 999999,
                    'measurement_item' => 'Vibration',
                    'standard' => '<= 5',
                    'measurement_value' => '3',
                    'unit' => 'mm/s',
                ],
            ],
        ],
    ];

    $response = $this->actingAs($admin)->postJson(route('api.sync'), $badPayload);

    $response->assertStatus(500)->assertJson(['success' => false, 'status' => 'failed']);

    // Rolled back: no measurement row, PM header untouched, and — crucially
    // — no sync_operations row either, so the SAME uuid can be retried.
    expect(PMMeasurement::count())->toBe(0)
        ->and($pmSchedule->fresh()->status)->toBe('OPEN')
        ->and(SyncOperation::where('operation_uuid', $uuid)->exists())->toBeFalse();

    // Retry with the SAME operation_uuid but a corrected payload must be
    // accepted and processed normally — not rejected as a duplicate.
    $goodPayload = $badPayload;
    unset($goodPayload['payload']['measurements']);

    $retry = $this->actingAs($admin)->postJson(route('api.sync'), $goodPayload);

    $retry->assertOk()->assertJson(['status' => 'processed']);
    expect($pmSchedule->fresh()->status)->toBe('IN_PROGRESS');
});
