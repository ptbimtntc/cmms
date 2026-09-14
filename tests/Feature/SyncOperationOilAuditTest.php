<?php

use App\Models\Machine;
use App\Models\OilAudit;
use App\Models\OilAuditFollowUp;
use App\Models\User;
use Illuminate\Support\Str;

function syncOaMachine(array $overrides = []): Machine
{
    return Machine::create(array_merge([
        'machine_number' => 'MC-'.uniqid(),
        'area' => 'WWD',
        'machine_type' => 'NDE',
        'status' => 'ACTIVE',
    ], $overrides));
}

function syncOaAudit(Machine $machine, array $overrides = []): OilAudit
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

function syncOaPost(User $user, array $body)
{
    return test()->actingAs($user)->postJson(route('api.sync'), $body);
}

function syncOaFollowUpPayload(array $problems, string $action = 'Ganti seal dan perbaiki bearing.'): array
{
    return [
        'problems' => collect($problems)->map(fn ($p) => [
            'problem' => $p[0],
            'findings' => collect($p[1])->map(fn ($f) => ['finding' => $f])->all(),
        ])->all(),
        'action_taken' => $action,
    ];
}

// ---------------------------------------------------------------------------
// OIL_AUDIT_CREATE
// ---------------------------------------------------------------------------

test('OIL_AUDIT_CREATE via sync creates the audit using OilAuditCreateService', function () {
    $pic = User::factory()->create(['role' => User::ROLE_PIC_WWD, 'name' => 'Budi']);
    $machine = syncOaMachine();

    $response = syncOaPost($pic, [
        'operation_uuid' => (string) Str::uuid(),
        'transaction_type' => 'OIL_AUDIT_CREATE',
        'payload' => ['machine_id' => $machine->id, 'condition' => 'OKE'],
    ]);

    $response->assertOk()->assertJson(['status' => 'processed']);

    $audit = OilAudit::first();
    expect($audit)->not->toBeNull()
        ->and($audit->machine_id)->toBe($machine->id)
        ->and($audit->condition)->toBe('OKE')
        ->and($audit->audited_by_name)->toBe('Budi');
});

test('OIL_AUDIT_CREATE via sync always uses the authenticated user, never a user id supplied in the payload', function () {
    $pic = User::factory()->create(['role' => User::ROLE_PIC_WWD, 'name' => 'Budi']);
    $someoneElse = User::factory()->create(['role' => User::ROLE_ADMIN, 'name' => 'Bukan Budi']);
    $machine = syncOaMachine();

    syncOaPost($pic, [
        'operation_uuid' => (string) Str::uuid(),
        'transaction_type' => 'OIL_AUDIT_CREATE',
        // A spoofed user id/name inside payload is simply not a field this
        // endpoint reads for authorship — SyncOperationController always
        // uses $request->user().
        'payload' => [
            'machine_id' => $machine->id,
            'condition' => 'OKE',
            'audited_by_user_id' => $someoneElse->id,
            'audited_by_name' => $someoneElse->name,
        ],
    ])->assertOk();

    $audit = OilAudit::first();
    expect($audit->audited_by_user_id)->toBe($pic->id)
        ->and($audit->audited_by_name)->toBe('Budi');
});

test('OIL_AUDIT_CREATE via sync rejects a machine outside the WWD/NDE-NDB scope', function () {
    $pic = User::factory()->create(['role' => User::ROLE_PIC_WWD, 'name' => 'Budi']);
    $outOfScope = syncOaMachine(['area' => 'BUL', 'machine_type' => 'BF']);

    syncOaPost($pic, [
        'operation_uuid' => (string) Str::uuid(),
        'transaction_type' => 'OIL_AUDIT_CREATE',
        'payload' => ['machine_id' => $outOfScope->id, 'condition' => 'OKE'],
    ])->assertStatus(422)->assertJson(['success' => false, 'status' => 'validation_failed']);

    expect(OilAudit::count())->toBe(0);
});

test('a PIC BUL is forbidden from OIL_AUDIT_CREATE via sync (Oil Audit is WWD-only)', function () {
    $pic = User::factory()->create(['role' => User::ROLE_PIC_BUL, 'name' => 'Budi']);
    $machine = syncOaMachine();

    syncOaPost($pic, [
        'operation_uuid' => (string) Str::uuid(),
        'transaction_type' => 'OIL_AUDIT_CREATE',
        'payload' => ['machine_id' => $machine->id, 'condition' => 'OKE'],
    ])->assertStatus(403)->assertJson(['status' => 'forbidden']);
});

// ---------------------------------------------------------------------------
// OIL_AUDIT_FOLLOW_UP_SAVE
// ---------------------------------------------------------------------------

test('OIL_AUDIT_FOLLOW_UP_SAVE via sync creates the follow-up + problems + findings atomically', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $audit = syncOaAudit(syncOaMachine());

    $response = syncOaPost($admin, [
        'operation_uuid' => (string) Str::uuid(),
        'transaction_type' => 'OIL_AUDIT_FOLLOW_UP_SAVE',
        'payload' => array_merge(
            ['oil_audit_id' => $audit->id],
            syncOaFollowUpPayload([['Bocor Oli', ['Kapstan 1']]])
        ),
        'expected_state' => ['follow_up_exists' => false],
    ]);

    $response->assertOk()->assertJson(['status' => 'processed']);

    $followUp = $audit->followUp()->first();
    expect($followUp)->not->toBeNull()
        ->and($followUp->problems)->toHaveCount(1)
        ->and($followUp->problems->first()->findings->pluck('finding')->all())->toBe(['Kapstan 1']);
});

test('OIL_AUDIT_FOLLOW_UP_SAVE via sync updates an existing follow-up when the device correctly expects one', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $audit = syncOaAudit(syncOaMachine());
    $existing = OilAuditFollowUp::create([
        'oil_audit_id' => $audit->id,
        'problem' => 'Bocor Oli',
        'action_taken' => 'Awal',
        'pic_name' => 'PIC Lama',
        'actioned_at' => now(),
    ]);
    $existing->problems()->create(['problem' => 'Bocor Oli'])->findings()->create(['finding' => 'Kapstan 1']);

    $response = syncOaPost($admin, [
        'operation_uuid' => (string) Str::uuid(),
        'transaction_type' => 'OIL_AUDIT_FOLLOW_UP_SAVE',
        'payload' => array_merge(
            ['oil_audit_id' => $audit->id],
            syncOaFollowUpPayload([['Baut Kendor', ['Kapstan 2']]], 'Update dari sync')
        ),
        'expected_state' => ['follow_up_exists' => true],
    ]);

    $response->assertOk()->assertJson(['status' => 'processed']);

    $existing->refresh();
    expect($existing->action_taken)->toBe('Update dari sync')
        ->and($existing->problems->first()->problem)->toBe('Baut Kendor')
        // pic_name / actioned_at are NOT overwritten by an update — same as
        // the online endpoint.
        ->and($existing->pic_name)->toBe('PIC Lama');
});

test('OIL_AUDIT_FOLLOW_UP_SAVE via sync is a conflict when the device expects no follow-up but the server already has one', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $audit = syncOaAudit(syncOaMachine());
    OilAuditFollowUp::create([
        'oil_audit_id' => $audit->id,
        'problem' => 'Bocor Oli',
        'action_taken' => 'Sudah diisi user lain',
        'pic_name' => 'User Lain',
        'actioned_at' => now(),
    ]);

    $response = syncOaPost($admin, [
        'operation_uuid' => (string) Str::uuid(),
        'transaction_type' => 'OIL_AUDIT_FOLLOW_UP_SAVE',
        'payload' => array_merge(
            ['oil_audit_id' => $audit->id],
            syncOaFollowUpPayload([['Baut Kendor', ['Kapstan 2']]], 'Device offline')
        ),
        // Device drafted this offline assuming no follow-up existed yet.
        'expected_state' => ['follow_up_exists' => false],
    ]);

    $response->assertStatus(409)->assertJson(['success' => false, 'status' => 'conflict']);

    // The existing follow-up from "another user" is completely untouched —
    // no silent overwrite.
    expect($audit->followUp()->first()->action_taken)->toBe('Sudah diisi user lain');
});

test('OIL_AUDIT_FOLLOW_UP_SAVE via sync for a non-existent oil_audit_id is reported as a conflict', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    syncOaPost($admin, [
        'operation_uuid' => (string) Str::uuid(),
        'transaction_type' => 'OIL_AUDIT_FOLLOW_UP_SAVE',
        'payload' => array_merge(
            ['oil_audit_id' => 999999],
            syncOaFollowUpPayload([['Baut Kendor', ['Kapstan 2']]])
        ),
    ])->assertStatus(409)->assertJson(['success' => false, 'status' => 'conflict']);
});

test('OIL_AUDIT_FOLLOW_UP_SAVE via sync rejects a condition that does not need a follow-up', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $audit = syncOaAudit(syncOaMachine(), ['condition' => 'OKE']);

    syncOaPost($admin, [
        'operation_uuid' => (string) Str::uuid(),
        'transaction_type' => 'OIL_AUDIT_FOLLOW_UP_SAVE',
        'payload' => array_merge(
            ['oil_audit_id' => $audit->id],
            syncOaFollowUpPayload([['Baut Kendor', ['Kapstan 2']]])
        ),
    ])->assertStatus(422)->assertJson(['success' => false, 'status' => 'validation_failed']);

    expect(OilAuditFollowUp::count())->toBe(0);
});

test('OIL_AUDIT_FOLLOW_UP_SAVE via sync validates problems/findings with the exact same rules as the online endpoint', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $audit = syncOaAudit(syncOaMachine());

    syncOaPost($admin, [
        'operation_uuid' => (string) Str::uuid(),
        'transaction_type' => 'OIL_AUDIT_FOLLOW_UP_SAVE',
        'payload' => [
            'oil_audit_id' => $audit->id,
            'problems' => [],
            'action_taken' => '',
        ],
    ])->assertStatus(422)->assertJson(['success' => false, 'status' => 'validation_failed']);
});

test('existing online Oil Audit routes keep working unchanged after the sync endpoint is added', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = syncOaMachine();

    $this->actingAs($admin)
        ->post(route('oil-audits.store'), ['machine_id' => $machine->id, 'condition' => 'OKE'])
        ->assertRedirect();

    expect(OilAudit::count())->toBe(1);
});
