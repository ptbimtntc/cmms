<?php

use App\Models\Machine;
use App\Models\OilAudit;
use App\Models\OilAuditFollowUp;
use App\Models\OilAuditFollowUpProblem;
use App\Models\SyncOperation;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * FreeDOMS offline-first — Task 8 (Implement Offline Oil Audit Follow Up
 * Save).
 *
 * Complements tests/Feature/SyncOperationOilAuditTest.php (Task 2 — basic
 * OIL_AUDIT_FOLLOW_UP_SAVE create/update/create-conflict/scope/validation)
 * and SyncOperationIdempotencyTest.php (Task 2 — generic idempotency/
 * payload-mismatch/race/rollback proofs, already exercising the SAME
 * shared ledger+DB::transaction code path every transaction type — this
 * one included — goes through). This file proves the guarantees specific
 * to Task 8: a MULTI-problem/MULTI-finding aggregate is stored and
 * replaced atomically as one whole, a retry never duplicates that nested
 * data, the update-direction conflict (device expected an existing
 * follow-up that is no longer there) is caught too, the PIC WWD-only role
 * scope from the online route is enforced here as well, and
 * pic_user_id/pic_name/actioned_at can never be spoofed from the payload.
 */
function offlineFuMachine(array $overrides = []): Machine
{
    return Machine::create(array_merge([
        'machine_number' => 'MC-'.uniqid(),
        'area' => 'WWD',
        'machine_type' => 'NDE',
        'status' => 'ACTIVE',
    ], $overrides));
}

function offlineFuAudit(Machine $machine, array $overrides = []): OilAudit
{
    return OilAudit::create(array_merge([
        'machine_id' => $machine->id,
        'machine_number' => $machine->machine_number,
        'machine_type' => $machine->machine_type,
        'area' => $machine->area,
        'condition' => 'KRITIS',
        'audited_by_name' => 'Tester',
        'audited_at' => now(),
    ], $overrides));
}

/** Mirrors resources/js/offline/oilAuditFollowUp.js's saveOffline() envelope exactly. */
function offlineFuPayload(int $oilAuditId, array $problems, string $actionTaken, bool $followUpExists, string $uuid): array
{
    return [
        'operation_uuid' => $uuid,
        'transaction_type' => 'OIL_AUDIT_FOLLOW_UP_SAVE',
        'payload' => [
            'oil_audit_id' => $oilAuditId,
            'problems' => collect($problems)->map(fn ($p) => [
                'problem' => $p[0],
                'findings' => collect($p[1])->map(fn ($f) => ['finding' => $f])->all(),
            ])->all(),
            'action_taken' => $actionTaken,
        ],
        'expected_state' => ['follow_up_exists' => $followUpExists],
    ];
}

test('a MULTI-problem, MULTI-finding aggregate is stored atomically in one operation', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $audit = offlineFuAudit(offlineFuMachine());

    $payload = offlineFuPayload($audit->id, [
        ['Bocor Oli', ['Kapstan 1', 'Kapstan 2']],
        ['Baut Kendor', ['Kapstan 3']],
    ], 'Ganti seal dan kencangkan baut.', false, (string) Str::uuid());

    $this->actingAs($admin)->postJson(route('api.sync'), $payload)
        ->assertOk()->assertJson(['status' => 'processed']);

    expect(SyncOperation::count())->toBe(1);

    $followUp = $audit->followUp()->first();
    expect($followUp->problems)->toHaveCount(2);

    $byProblem = $followUp->problems->keyBy('problem');
    expect($byProblem->get('Bocor Oli')->findings->pluck('finding')->sort()->values()->all())
        ->toBe(['Kapstan 1', 'Kapstan 2'])
        ->and($byProblem->get('Baut Kendor')->findings->pluck('finding')->all())
        ->toBe(['Kapstan 3']);
});

test('updating replaces the ENTIRE problem/finding set (delete-and-recreate), never leaving stale rows behind', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $audit = offlineFuAudit(offlineFuMachine());
    $existing = OilAuditFollowUp::create([
        'oil_audit_id' => $audit->id,
        'problem' => 'Bocor Oli',
        'action_taken' => 'Awal',
        'pic_name' => 'PIC Lama',
        'actioned_at' => now(),
    ]);
    $existing->problems()->create(['problem' => 'Bocor Oli'])->findings()->createMany([
        ['finding' => 'Kapstan 1'],
        ['finding' => 'Kapstan 2'],
    ]);

    $payload = offlineFuPayload($audit->id, [
        ['Baut Kendor', ['Kapstan 3']],
        ['Bearing Oblak', ['Kapstan 1']],
    ], 'Sudah diganti oli.', true, (string) Str::uuid());

    $this->actingAs($admin)->postJson(route('api.sync'), $payload)->assertOk();

    $existing->refresh();
    expect($existing->problems)->toHaveCount(2)
        ->and($existing->problems->pluck('problem')->sort()->values()->all())
        ->toBe(['Baut Kendor', 'Bearing Oblak'])
        ->and(OilAuditFollowUpProblem::where('problem', 'Bocor Oli')->where('oil_audit_follow_up_id', $existing->id)->exists())
        ->toBeFalse();
});

test('retrying the SAME operation_uuid never duplicates the follow-up, its problems, or its findings', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $audit = offlineFuAudit(offlineFuMachine());
    $uuid = (string) Str::uuid();

    $payload = offlineFuPayload($audit->id, [['Bocor Oli', ['Kapstan 1', 'Kapstan 2']]], 'Ganti seal.', false, $uuid);

    $first = $this->actingAs($admin)->postJson(route('api.sync'), $payload);
    $first->assertOk()->assertJson(['status' => 'processed']);

    $second = $this->actingAs($admin)->postJson(route('api.sync'), $payload);
    $second->assertOk()->assertJson(['status' => 'already_processed']);

    expect(OilAuditFollowUp::count())->toBe(1)
        ->and($audit->followUp()->first()->problems)->toHaveCount(1)
        ->and($audit->followUp()->first()->problems->first()->findings)->toHaveCount(2)
        ->and(SyncOperation::where('operation_uuid', $uuid)->count())->toBe(1);
});

test('the same operation_uuid with a DIFFERENT payload is rejected and never touches the follow-up a second time', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $audit = offlineFuAudit(offlineFuMachine());
    $uuid = (string) Str::uuid();

    $original = offlineFuPayload($audit->id, [['Bocor Oli', ['Kapstan 1']]], 'Ganti seal.', false, $uuid);
    $this->actingAs($admin)->postJson(route('api.sync'), $original)->assertOk();

    $tampered = offlineFuPayload($audit->id, [['Baut Kendor', ['Kapstan 2']]], 'Payload lain.', false, $uuid);
    $this->actingAs($admin)->postJson(route('api.sync'), $tampered)
        ->assertStatus(409)->assertJson(['success' => false, 'status' => 'payload_mismatch']);

    $followUp = $audit->followUp()->first();
    expect($followUp->action_taken)->toBe('Ganti seal.')
        ->and($followUp->problems->pluck('problem')->all())->toBe(['Bocor Oli']);
});

test('is a conflict when the device expects an existing follow-up but none exists on the server (e.g. it was deleted since)', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $audit = offlineFuAudit(offlineFuMachine());
    // No OilAuditFollowUp exists — the device's belief (`follow_up_exists:
    // true`) is stale, e.g. it loaded the edit form, then someone else
    // deleted the follow-up before this offline edit synced.

    $payload = offlineFuPayload($audit->id, [['Baut Kendor', ['Kapstan 2']]], 'Edit offline.', true, (string) Str::uuid());

    $this->actingAs($admin)->postJson(route('api.sync'), $payload)
        ->assertStatus(409)->assertJson(['success' => false, 'status' => 'conflict']);

    expect(OilAuditFollowUp::count())->toBe(0);
});

test('a PIC BUL is forbidden from OIL_AUDIT_FOLLOW_UP_SAVE via sync (Oil Audit follow-up is WWD-only), matching the online route\'s role middleware', function () {
    $pic = User::factory()->create(['role' => User::ROLE_PIC_BUL]);
    $audit = offlineFuAudit(offlineFuMachine());

    $payload = offlineFuPayload($audit->id, [['Bocor Oli', ['Kapstan 1']]], 'x', false, (string) Str::uuid());

    $this->actingAs($pic)->postJson(route('api.sync'), $payload)
        ->assertStatus(403)->assertJson(['status' => 'forbidden']);

    expect(OilAuditFollowUp::count())->toBe(0);
});

test('pic_user_id/pic_name/actioned_at always come from the authenticated user and the server clock, never from anything in the payload', function () {
    $pic = User::factory()->create(['role' => User::ROLE_PIC_WWD, 'name' => 'Budi']);
    $someoneElse = User::factory()->create(['role' => User::ROLE_ADMIN, 'name' => 'Bukan Budi']);
    $audit = offlineFuAudit(offlineFuMachine());

    $payload = offlineFuPayload($audit->id, [['Bocor Oli', ['Kapstan 1']]], 'x', false, (string) Str::uuid());
    // Attempt to smuggle a spoofed author/timestamp in — these are simply
    // not fields OilAuditFollowUpService::rules() or store() reads from
    // the payload for that purpose.
    $payload['payload']['pic_user_id'] = $someoneElse->id;
    $payload['payload']['pic_name'] = $someoneElse->name;
    $payload['payload']['actioned_at'] = now()->subYears(2)->toIso8601String();

    $before = now();
    $this->actingAs($pic)->postJson(route('api.sync'), $payload)->assertOk();

    $followUp = $audit->followUp()->first();
    expect($followUp->pic_user_id)->toBe($pic->id)
        ->and($followUp->pic_name)->toBe('Budi')
        ->and($followUp->actioned_at->diffInSeconds($before))->toBeLessThan(5);
});

test('actioned_at / pic_name are left untouched by an update, exactly like the online endpoint', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $audit = offlineFuAudit(offlineFuMachine());
    $originalActionedAt = now()->subDays(3);
    $existing = OilAuditFollowUp::create([
        'oil_audit_id' => $audit->id,
        'problem' => 'Bocor Oli',
        'action_taken' => 'Awal',
        'pic_name' => 'PIC Lama',
        'pic_user_id' => null,
        'actioned_at' => $originalActionedAt,
    ]);
    $existing->problems()->create(['problem' => 'Bocor Oli'])->findings()->create(['finding' => 'Kapstan 1']);

    $payload = offlineFuPayload($audit->id, [['Baut Kendor', ['Kapstan 2']]], 'Update.', true, (string) Str::uuid());

    $this->actingAs($admin)->postJson(route('api.sync'), $payload)->assertOk();

    $existing->refresh();
    expect($existing->pic_name)->toBe('PIC Lama')
        ->and($existing->actioned_at->toDateTimeString())->toBe($originalActionedAt->toDateTimeString());
});

test('existing online Oil Audit Follow Up routes keep working unchanged after the sync endpoint is added', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $audit = offlineFuAudit(offlineFuMachine());

    $this->actingAs($admin)
        ->post(route('oil-audits.follow-up.store', $audit), [
            'problems' => [['problem' => 'Bocor Oli', 'findings' => [['finding' => 'Kapstan 1']]]],
            'action_taken' => 'Ganti seal.',
        ])
        ->assertRedirect();

    expect($audit->followUp()->first())->not->toBeNull();
});
