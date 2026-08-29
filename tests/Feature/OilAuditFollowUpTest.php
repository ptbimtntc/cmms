<?php

use App\Models\Machine;
use App\Models\OilAudit;
use App\Models\OilAuditFollowUp;
use App\Models\OilAuditFollowUpFinding;
use App\Models\OilAuditFollowUpProblem;
use App\Models\User;

function fuMachine(array $overrides = []): Machine
{
    return Machine::create(array_merge([
        'machine_number' => 'MC-'.uniqid(),
        'area' => 'WWD',
        'machine_type' => 'NDE',
        'status' => 'ACTIVE',
    ], $overrides));
}

function fuAudit(Machine $machine, array $overrides = []): OilAudit
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

/**
 * Nested request payload. $problems is [ [problemOption, [findingOption, ...]], ... ].
 * problemOption must be one of OilAudit::PROBLEM_OPTIONS, findingOption one of
 * OilAudit::FINDING_OPTIONS.
 */
function fuPayload(array $problems, string $action = 'Ganti seal dan perbaiki bearing.'): array
{
    return [
        'problems' => collect($problems)->map(fn ($p) => [
            'problem' => $p[0],
            'findings' => collect($p[1])->map(fn ($f) => ['finding' => $f])->all(),
        ])->all(),
        'action_taken' => $action,
    ];
}

function seedFollowUp(OilAudit $audit, array $problems): OilAuditFollowUp
{
    $followUp = OilAuditFollowUp::create([
        'oil_audit_id' => $audit->id,
        'problem' => $problems[0][0],
        'action_taken' => 'Awal',
        'pic_name' => 'PIC Lama',
        'actioned_at' => now(),
    ]);

    foreach ($problems as [$problem, $findings]) {
        $row = $followUp->problems()->create(['problem' => $problem]);
        foreach ($findings as $finding) {
            $row->findings()->create(['finding' => $finding]);
        }
    }

    return $followUp;
}

// ---------------------------------------------------------------------------
// Create
// ---------------------------------------------------------------------------

test('create: one problem with one finding', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $audit = fuAudit(fuMachine());

    $this->actingAs($admin)
        ->post(route('oil-audits.follow-up.store', $audit), fuPayload([['Bocor Oli', ['Kapstan 1']]]))
        ->assertRedirect();

    $followUp = $audit->followUp()->first();
    expect($followUp)->not->toBeNull()
        ->and($followUp->problems)->toHaveCount(1)
        ->and($followUp->problems->first()->problem)->toBe('Bocor Oli')
        ->and($followUp->problems->first()->findings->pluck('finding')->all())->toBe(['Kapstan 1'])
        ->and($followUp->action_taken)->toBe('Ganti seal dan perbaiki bearing.')
        // legacy column stays populated
        ->and($followUp->problem)->toBe('Bocor Oli');
});

test('create: one problem with many findings', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $audit = fuAudit(fuMachine());

    $this->actingAs($admin)
        ->post(route('oil-audits.follow-up.store', $audit), fuPayload([['Bearing Oblak', ['Kapstan 1', 'Mainshaft', 'Innershaft']]]))
        ->assertRedirect();

    expect($audit->followUp->problems->first()->findings->pluck('finding')->all())
        ->toBe(['Kapstan 1', 'Mainshaft', 'Innershaft']);
});

test('create: many problems each with findings', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $audit = fuAudit(fuMachine());

    $this->actingAs($admin)
        ->post(route('oil-audits.follow-up.store', $audit), fuPayload([
            ['Bocor Oli', ['Kapstan 1', 'Mainshaft']],
            ['Bearing Oblak', ['Kapstan 2']],
        ]))
        ->assertRedirect();

    $problems = $audit->followUp->problems;
    expect($problems->pluck('problem')->all())->toBe(['Bocor Oli', 'Bearing Oblak'])
        ->and($problems[0]->findings)->toHaveCount(2)
        ->and($problems[1]->findings)->toHaveCount(1)
        ->and(OilAuditFollowUpFinding::count())->toBe(3);
});

// ---------------------------------------------------------------------------
// Validation
// ---------------------------------------------------------------------------

test('validation: problem must be one of the allowed options', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $audit = fuAudit(fuMachine());

    $this->actingAs($admin)
        ->post(route('oil-audits.follow-up.store', $audit), fuPayload([['Bukan Opsi', ['Kapstan 1']]]))
        ->assertSessionHasErrors('problems.0.problem');

    expect($audit->followUp()->exists())->toBeFalse();
});

test('validation: finding must be one of the allowed options', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $audit = fuAudit(fuMachine());

    $this->actingAs($admin)
        ->post(route('oil-audits.follow-up.store', $audit), fuPayload([['Bocor Oli', ['Bukan Bagian']]]))
        ->assertSessionHasErrors('problems.0.findings.0.finding');

    expect($audit->followUp()->exists())->toBeFalse();
});

test('validation: an oil/glass-condition problem only accepts the generic finding', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    // Rejected: a machine-location finding under "Oli Keruh/Hitam".
    $rejected = fuAudit(fuMachine());
    $this->actingAs($admin)
        ->post(route('oil-audits.follow-up.store', $rejected), fuPayload([['Oli Keruh/Hitam', ['Kapstan 1']]]))
        ->assertSessionHasErrors('problems.0.findings.0.finding');
    expect($rejected->followUp()->exists())->toBeFalse();

    // Accepted: the same problem with "Lainnya".
    $accepted = fuAudit(fuMachine());
    $this->actingAs($admin)
        ->post(route('oil-audits.follow-up.store', $accepted), fuPayload([['Level Glass Burem', ['Lainnya']]]))
        ->assertRedirect()
        ->assertSessionHasNoErrors();
    expect($accepted->followUp->problems->first()->findings->pluck('finding')->all())->toBe(['Lainnya']);
});

test('validation: the same problem cannot be selected on two rows', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $audit = fuAudit(fuMachine());

    $this->actingAs($admin)
        ->post(route('oil-audits.follow-up.store', $audit), fuPayload([
            ['Bearing Oblak', ['Kapstan 3']],
            ['Bearing Oblak', ['Kapstan 2']],
        ]))
        ->assertSessionHasErrors('problems');

    expect($audit->followUp()->exists())->toBeFalse();
});

test('validation: a finding cannot repeat inside the same problem', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $audit = fuAudit(fuMachine());

    $this->actingAs($admin)
        ->post(route('oil-audits.follow-up.store', $audit), fuPayload([['Bocor Oli', ['Kapstan 3', 'Kapstan 3']]]))
        ->assertSessionHasErrors('problems.0.findings');

    expect($audit->followUp()->exists())->toBeFalse();
});

test('the same finding may be used in two different problems', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $audit = fuAudit(fuMachine());

    $this->actingAs($admin)
        ->post(route('oil-audits.follow-up.store', $audit), fuPayload([
            ['Bocor Oli', ['Kapstan 3']],
            ['Bearing Oblak', ['Kapstan 3']],
        ]))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($audit->followUp->problems->pluck('findings')->flatten(1)->pluck('finding')->all())
        ->toBe(['Kapstan 3', 'Kapstan 3']);
});

test('validation: each problem needs at least one non-empty finding', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $audit = fuAudit(fuMachine());

    $this->actingAs($admin)
        ->post(route('oil-audits.follow-up.store', $audit), [
            'problems' => [['problem' => 'Bocor Oli', 'findings' => [['finding' => '']]]],
            'action_taken' => 'x',
        ])
        ->assertSessionHasErrors('problems.0.findings.0.finding');

    expect($audit->followUp()->exists())->toBeFalse();
});

test('validation: action taken is required', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $audit = fuAudit(fuMachine());

    $this->actingAs($admin)
        ->post(route('oil-audits.follow-up.store', $audit), [
            'problems' => [['problem' => 'Bocor Oli', 'findings' => [['finding' => 'Kapstan 1']]]],
        ])
        ->assertSessionHasErrors('action_taken');
});

test('an audit that does not need a follow-up rejects the write', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $audit = fuAudit(fuMachine(), ['condition' => 'OKE']);

    $this->actingAs($admin)
        ->post(route('oil-audits.follow-up.store', $audit), fuPayload([['Bocor Oli', ['Kapstan 1']]]))
        ->assertStatus(422);
});

// ---------------------------------------------------------------------------
// Edit page
// ---------------------------------------------------------------------------

test('the history page shows every existing problem and finding', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = fuMachine();
    $audit = fuAudit($machine);
    seedFollowUp($audit, [
        ['Bocor Oli', ['Kapstan 1', 'Mainshaft']],
        ['Bearing Oblak', ['Kapstan 2']],
    ]);

    $response = $this->actingAs($admin)->get(route('oil-audits.history', $machine->machine_number));

    $response->assertOk();
    $response->assertSee('Bocor Oli');
    $response->assertSee('Bearing Oblak');
    $response->assertSee('Mainshaft');
    // edit form is pre-rendered (hidden) with the existing values selected
    $response->assertSee('name="problems[1][findings][0][finding]"', false);
});

// ---------------------------------------------------------------------------
// Update
// ---------------------------------------------------------------------------

test('update: add a problem and a finding, drop another problem and finding', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $audit = fuAudit(fuMachine());
    $followUp = seedFollowUp($audit, [
        ['Bocor Oli', ['Kapstan 1', 'Mainshaft']],
        ['Bearing Oblak', ['Kapstan 2']],
    ]);
    $staleProblemIds = $followUp->problems->pluck('id');
    $staleFindingIds = OilAuditFollowUpFinding::pluck('id');

    $this->actingAs($admin)
        ->put(route('oil-audits.follow-up.update', $audit), fuPayload([
            ['Bocor Oli', ['Kapstan 1']],                  // finding "Mainshaft" removed
            ['Baut Kendor', ['Kapstan 3', 'Innershaft']],  // brand new problem
        ], 'Tindakan baru.'))
        ->assertRedirect();

    $followUp->refresh()->load('problems.findings');

    expect($followUp->problems->pluck('problem')->all())->toBe(['Bocor Oli', 'Baut Kendor'])
        ->and($followUp->problems[0]->findings->pluck('finding')->all())->toBe(['Kapstan 1'])
        ->and($followUp->problems[1]->findings->pluck('finding')->all())->toBe(['Kapstan 3', 'Innershaft'])
        ->and($followUp->action_taken)->toBe('Tindakan baru.');

    // Old rows really gone (delete-and-recreate).
    expect(OilAuditFollowUpProblem::whereIn('id', $staleProblemIds)->count())->toBe(0)
        ->and(OilAuditFollowUpFinding::whereIn('id', $staleFindingIds)->count())->toBe(0);
});

test('update: the original PIC and action date are preserved', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'name' => 'Editor']);
    $audit = fuAudit(fuMachine());
    $followUp = seedFollowUp($audit, [['Bocor Oli', ['Kapstan 1']]]);
    $originalPic = $followUp->pic_name;
    $originalDate = $followUp->actioned_at;

    $this->actingAs($admin)
        ->put(route('oil-audits.follow-up.update', $audit), fuPayload([['Bocor Oli', ['Mainshaft']]]))
        ->assertRedirect();

    $followUp->refresh();
    expect($followUp->pic_name)->toBe($originalPic)
        ->and($followUp->actioned_at->eq($originalDate))->toBeTrue();
});

// ---------------------------------------------------------------------------
// Delete
// ---------------------------------------------------------------------------

test('delete: admin removes the follow-up and cascades problems + findings', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $audit = fuAudit(fuMachine());
    seedFollowUp($audit, [['Bocor Oli', ['Kapstan 1', 'Mainshaft']]]);

    $this->actingAs($admin)
        ->delete(route('oil-audits.follow-up.destroy', $audit))
        ->assertRedirect();

    expect($audit->followUp()->exists())->toBeFalse()
        ->and(OilAuditFollowUpProblem::count())->toBe(0)
        ->and(OilAuditFollowUpFinding::count())->toBe(0);
});

test('delete: koordinator wwd is allowed', function () {
    $koordinator = User::factory()->create(['role' => User::ROLE_KOORDINATOR_WWD]);
    $audit = fuAudit(fuMachine());
    seedFollowUp($audit, [['Bocor Oli', ['Kapstan 1']]]);

    $this->actingAs($koordinator)
        ->delete(route('oil-audits.follow-up.destroy', $audit))
        ->assertRedirect();

    expect($audit->followUp()->exists())->toBeFalse();
});

test('delete: pic wwd is forbidden', function () {
    $pic = User::factory()->create(['role' => User::ROLE_PIC_WWD]);
    $audit = fuAudit(fuMachine());
    seedFollowUp($audit, [['Bocor Oli', ['Kapstan 1']]]);

    $this->actingAs($pic)
        ->delete(route('oil-audits.follow-up.destroy', $audit))
        ->assertForbidden();

    expect($audit->followUp()->exists())->toBeTrue();
});

// ---------------------------------------------------------------------------
// Backward compatibility
// ---------------------------------------------------------------------------

test('a legacy follow-up with problems but no findings still renders', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = fuMachine();
    $audit = fuAudit($machine);

    $followUp = OilAuditFollowUp::create([
        'oil_audit_id' => $audit->id,
        'problem' => 'Kapstan 1',
        'action_taken' => 'Legacy action',
        'pic_name' => 'PIC',
        'actioned_at' => now(),
    ]);
    $followUp->problems()->create(['problem' => 'Kapstan 1']);
    $followUp->problems()->create(['problem' => 'Kapstan 2']);

    $response = $this->actingAs($admin)->get(route('oil-audits.history', $machine->machine_number));

    $response->assertOk();
    $response->assertSee('Legacy action');
    $response->assertSee('Kapstan 2');
});
