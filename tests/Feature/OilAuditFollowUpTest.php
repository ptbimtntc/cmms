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
        'machine_type' => oilAuditMachineType(),
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
// PANTAU actions (refill oil and/or full check)
// ---------------------------------------------------------------------------

test('PANTAU: refill oil alone is enough and stores no problems', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $audit = fuAudit(fuMachine(), ['condition' => 'PANTAU']);

    $this->actingAs($admin)
        ->post(route('oil-audits.follow-up.store', $audit), ['refill_oil' => '1'])
        ->assertSessionHasNoErrors();

    $followUp = $audit->fresh()->followUp;
    expect($followUp->refill_oil)->toBeTrue()
        ->and($followUp->full_check)->toBeFalse()
        ->and($followUp->action_taken)->toBeNull()
        ->and($followUp->problems)->toHaveCount(0);
});

test('PANTAU: full check needs the same problem, finding and action fields', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $audit = fuAudit(fuMachine(), ['condition' => 'PANTAU']);

    $this->actingAs($admin)
        ->post(route('oil-audits.follow-up.store', $audit), ['full_check' => '1'])
        ->assertSessionHasErrors(['problems', 'action_taken']);

    $this->actingAs($admin)
        ->post(route('oil-audits.follow-up.store', $audit), ['full_check' => '1', 'refill_oil' => '1'] + fuPayload([['Bocor Oli', ['Kapstan 1']]]))
        ->assertSessionHasNoErrors();

    $followUp = $audit->fresh()->followUp;
    expect($followUp->refill_oil)->toBeTrue()
        ->and($followUp->full_check)->toBeTrue()
        ->and($followUp->problems)->toHaveCount(1);
});

test('PANTAU: picking neither action is rejected', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $audit = fuAudit(fuMachine(), ['condition' => 'PANTAU']);

    $this->actingAs($admin)
        ->post(route('oil-audits.follow-up.store', $audit), [])
        ->assertSessionHasErrors('refill_oil');

    expect($audit->fresh()->followUp)->toBeNull();
});

test('PANTAU: an action never makes the audit a pending finding', function () {
    $audit = fuAudit(fuMachine(), ['condition' => 'PANTAU']);

    expect($audit->needsFollowUp())->toBeFalse()
        ->and($audit->canReceiveAction())->toBeTrue()
        ->and(OilAudit::requiringFollowUp()->whereKey($audit->id)->exists())->toBeFalse();
});

test('PANTAU: an action on the newest audit closes the older open PANTAU audits the same way', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = fuMachine();
    $oldest = fuAudit($machine, ['condition' => 'PANTAU', 'audited_at' => now()->subDays(14)]);
    $older = fuAudit($machine, ['condition' => 'PANTAU', 'audited_at' => now()->subDays(7)]);
    $newest = fuAudit($machine, ['condition' => 'PANTAU']);
    $otherCondition = fuAudit($machine, ['condition' => 'OKE', 'audited_at' => now()->subDays(10)]);

    $this->actingAs($admin)
        ->post(route('oil-audits.follow-up.store', $newest), ['refill_oil' => '1'])
        ->assertSessionHasNoErrors();

    foreach ([$oldest, $older, $newest] as $audit) {
        $followUp = $audit->fresh()->followUp;
        expect($followUp)->not->toBeNull()
            ->and($followUp->refill_oil)->toBeTrue()
            ->and($followUp->full_check)->toBeFalse();
    }
    expect($otherCondition->fresh()->followUp)->toBeNull();
});

test('PANTAU: a full check on the newest audit copies its findings to the older open ones', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = fuMachine();
    $older = fuAudit($machine, ['condition' => 'PANTAU', 'audited_at' => now()->subDays(7)]);
    $newest = fuAudit($machine, ['condition' => 'PANTAU']);

    $this->actingAs($admin)
        ->post(route('oil-audits.follow-up.store', $newest), ['full_check' => '1'] + fuPayload([['Bocor Oli', ['Kapstan 1']]]))
        ->assertSessionHasNoErrors();

    $copy = $older->fresh()->followUp;
    expect($copy->full_check)->toBeTrue()
        ->and($copy->problems->pluck('problem')->all())->toBe(['Bocor Oli']);
});

test('PANTAU: the history page shows the action choices and the recorded refill', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = fuMachine();
    $audit = fuAudit($machine, ['condition' => 'PANTAU']);

    $this->actingAs($admin)->get(route('oil-audits.history', $machine->machine_number))
        ->assertOk()
        ->assertSee('Refill oil')
        ->assertSee('Full check')
        ->assertDontSee('Action recorded');

    $audit->followUp()->create(['refill_oil' => true, 'full_check' => false, 'pic_name' => 'PIC', 'actioned_at' => now()]);

    $this->actingAs($admin)->get(route('oil-audits.history', $machine->machine_number))
        ->assertOk()
        ->assertSee('Action recorded')
        ->assertSee('Oil refilled');
});

// ---------------------------------------------------------------------------
// Edit the finding (audit condition)
// ---------------------------------------------------------------------------

function fuPic(string $name = 'PIC'): User
{
    return User::factory()->create([...roleAttributes('PIC WWD'), 'name' => $name]);
}

test('condition edit: the PIC who made the audit can change it, another PIC cannot', function () {
    $owner = fuPic('Owner');
    $other = fuPic('Other');
    $audit = fuAudit(fuMachine(), ['audited_by_user_id' => $owner->id]);

    $this->actingAs($other)->put(route('oil-audits.condition.update', $audit), ['condition' => 'PANTAU'])->assertForbidden();
    expect($audit->fresh()->condition)->toBe('KRITIS');

    $this->actingAs($owner)->put(route('oil-audits.condition.update', $audit), ['condition' => 'PANTAU'])
        ->assertSessionHasNoErrors();
    expect($audit->fresh()->condition)->toBe('PANTAU');
});

test('condition edit: an admin can change any audit, a supervisor cannot', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $supervisor = User::factory()->create(['role' => User::ROLE_SUPERVISOR]);
    $audit = fuAudit(fuMachine());

    $this->actingAs($supervisor)->put(route('oil-audits.condition.update', $audit), ['condition' => 'OKE'])->assertForbidden();
    $this->actingAs($admin)->put(route('oil-audits.condition.update', $audit), ['condition' => 'OKE'])->assertSessionHasNoErrors();
    expect($audit->fresh()->condition)->toBe('OKE');
});

test('condition edit: an invalid condition is rejected', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $audit = fuAudit(fuMachine());

    $this->actingAs($admin)->put(route('oil-audits.condition.update', $audit), ['condition' => 'NOPE'])
        ->assertSessionHasErrors('condition');
});

test('condition edit: the follow-up survives a change between conditions that need one', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $audit = fuAudit(fuMachine());
    seedFollowUp($audit, [['Bocor Oli', ['Kapstan 1']]]);

    $this->actingAs($admin)->put(route('oil-audits.condition.update', $audit), ['condition' => 'PAS_GARIS']);
    expect($audit->fresh()->followUp)->not->toBeNull();

    $this->actingAs($admin)->put(route('oil-audits.condition.update', $audit), ['condition' => 'PANTAU']);
    expect($audit->fresh()->followUp)->not->toBeNull();
});

test('condition edit: changing to Oke removes the follow-up, and refill-only is removed outside PANTAU', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $audit = fuAudit(fuMachine());
    seedFollowUp($audit, [['Bocor Oli', ['Kapstan 1']]]);
    $this->actingAs($admin)->put(route('oil-audits.condition.update', $audit), ['condition' => 'OKE']);
    expect($audit->fresh()->followUp)->toBeNull();

    $pantau = fuAudit(fuMachine(), ['condition' => 'PANTAU']);
    $pantau->followUp()->create(['refill_oil' => true, 'full_check' => false, 'pic_name' => 'PIC', 'actioned_at' => now()]);
    $this->actingAs($admin)->put(route('oil-audits.condition.update', $pantau), ['condition' => 'KRITIS']);
    expect($pantau->fresh()->followUp)->toBeNull()
        ->and($pantau->fresh()->condition)->toBe('KRITIS');
});

test('condition edit: the history page shows Edit finding only to people who may use it', function () {
    $owner = fuPic('Owner');
    $other = fuPic('Other');
    $machine = fuMachine();
    fuAudit($machine, ['audited_by_user_id' => $owner->id]);

    $this->actingAs($owner)->get(route('oil-audits.history', $machine->machine_number))->assertSee('Edit finding');
    $this->actingAs($other)->get(route('oil-audits.history', $machine->machine_number))->assertDontSee('Edit finding');
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
    $koordinator = User::factory()->create([...roleAttributes('KOORDINATOR WWD')]);
    $audit = fuAudit(fuMachine());
    seedFollowUp($audit, [['Bocor Oli', ['Kapstan 1']]]);

    $this->actingAs($koordinator)
        ->delete(route('oil-audits.follow-up.destroy', $audit))
        ->assertRedirect();

    expect($audit->followUp()->exists())->toBeFalse();
});

test('delete: pic wwd is forbidden', function () {
    $pic = User::factory()->create([...roleAttributes('PIC WWD')]);
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

// ---------------------------------------------------------------------------
// Repeating low-priority findings close together
// ---------------------------------------------------------------------------

test('following up the newest repeating Level Glass Burem also closes the older open ones with the same fields', function () {
    $machine = fuMachine();
    $old = fuAudit($machine, ['condition' => 'GLASS_BUREM', 'audited_at' => now()->subDays(14), 'audit_date' => now()->subDays(14)->toDateString()]);
    $mid = fuAudit($machine, ['condition' => 'GLASS_BUREM', 'audited_at' => now()->subDays(7), 'audit_date' => now()->subDays(7)->toDateString()]);
    $new = fuAudit($machine, ['condition' => 'GLASS_BUREM', 'audit_date' => now()->toDateString()]);
    $admin = User::factory()->create(roleAttributes('ADMIN'));

    test()->actingAs($admin)
        ->post(route('oil-audits.follow-up.store', $new), fuPayload([['Level Glass Burem', ['Lainnya']]], 'Bersihkan level glass.'))
        ->assertSessionHas('success');

    foreach ([$old, $mid, $new] as $audit) {
        $fu = $audit->fresh()->followUp;
        expect($fu)->not->toBeNull()
            ->and($fu->action_taken)->toBe('Bersihkan level glass.')
            ->and($fu->pic_user_id)->toBe($admin->id)
            ->and($fu->problems()->first()->problem)->toBe('Level Glass Burem')
            ->and($fu->problems()->first()->findings()->first()->finding)->toBe('Lainnya');
    }
});

test('Oli Keruh repeats close together, but other conditions, other machines and newer audits are untouched', function () {
    $machine = fuMachine();
    $other = fuMachine();
    $oldKeruh = fuAudit($machine, ['condition' => 'OLI_KERUH', 'audited_at' => now()->subDays(3), 'audit_date' => now()->subDays(3)->toDateString()]);
    $oldGlass = fuAudit($machine, ['condition' => 'GLASS_BUREM', 'audited_at' => now()->subDays(4), 'audit_date' => now()->subDays(4)->toDateString()]);
    $otherMachine = fuAudit($other, ['condition' => 'OLI_KERUH', 'audited_at' => now()->subDays(3), 'audit_date' => now()->subDays(3)->toDateString()]);
    $target = fuAudit($machine, ['condition' => 'OLI_KERUH', 'audited_at' => now()->subDay(), 'audit_date' => now()->subDay()->toDateString()]);
    $newer = fuAudit($machine, ['condition' => 'OLI_KERUH', 'audit_date' => now()->toDateString()]);
    $admin = User::factory()->create(roleAttributes('ADMIN'));

    test()->actingAs($admin)
        ->post(route('oil-audits.follow-up.store', $target), fuPayload([['Oli Keruh/Hitam', ['Lainnya']]]));

    expect($oldKeruh->fresh()->followUp)->not->toBeNull()
        ->and($target->fresh()->followUp)->not->toBeNull()
        ->and($oldGlass->fresh()->followUp)->toBeNull()
        ->and($otherMachine->fresh()->followUp)->toBeNull()
        ->and($newer->fresh()->followUp)->toBeNull();
});

test('non low-priority conditions do not cascade, and already-closed older audits are kept as they are', function () {
    $machine = fuMachine();
    $oldCritical = fuAudit($machine, ['condition' => 'PAS_GARIS', 'audited_at' => now()->subDays(2), 'audit_date' => now()->subDays(2)->toDateString()]);
    $newCritical = fuAudit($machine, ['condition' => 'PAS_GARIS', 'audit_date' => now()->toDateString()]);
    $admin = User::factory()->create(roleAttributes('ADMIN'));

    test()->actingAs($admin)->post(route('oil-audits.follow-up.store', $newCritical), fuPayload([['Bocor Oli', ['Kapstan 1']]]));
    expect($oldCritical->fresh()->followUp)->toBeNull();

    $m2 = fuMachine();
    $closed = fuAudit($m2, ['condition' => 'GLASS_BUREM', 'audited_at' => now()->subDays(5), 'audit_date' => now()->subDays(5)->toDateString()]);
    seedFollowUp($closed, [['Bocor Oli', ['Kapstan 1']]]);
    $fresh = fuAudit($m2, ['condition' => 'GLASS_BUREM', 'audit_date' => now()->toDateString()]);

    test()->actingAs($admin)->post(route('oil-audits.follow-up.store', $fresh), fuPayload([['Level Glass Burem', ['Lainnya']]], 'Baru'));

    expect($closed->fresh()->followUp->action_taken)->toBe('Awal')
        ->and($closed->fresh()->followUp->problems()->first()->problem)->toBe('Bocor Oli');
});
