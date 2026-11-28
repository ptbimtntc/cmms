<?php

use App\Models\Machine;
use App\Models\OilAudit;
use App\Models\OilAuditFollowUp;
use App\Models\User;

function actionOilAuditMachine(array $overrides = []): Machine
{
    return Machine::create(array_merge([
        'machine_number' => 'MC-'.uniqid(),
        'area' => 'WWD',
        'machine_type' => oilAuditMachineType(),
        'status' => 'ACTIVE',
    ], $overrides));
}

function actionOilAudit(Machine $machine, array $overrides = []): OilAudit
{
    return OilAudit::create(array_merge([
        'machine_id' => $machine->id,
        'machine_number' => $machine->machine_number,
        'machine_type' => $machine->machine_type,
        'area' => $machine->area,
        'condition' => 'OKE',
        'audited_by_name' => 'Tester',
        'audited_at' => now(),
    ], $overrides));
}

test('action page requires authentication', function () {
    $this->get(route('oil-audits.report'))->assertRedirect(route('login'));
});

test('guest role cannot access the action page', function () {
    $guest = User::factory()->create(['role' => User::ROLE_GUEST]);

    $this->actingAs($guest)->get(route('oil-audits.report'))->assertForbidden();
});

test('koordinator bul and pic bul cannot access the action page since oil audit is wwd-only', function () {
    $koordinator = User::factory()->create([...roleAttributes('KOORDINATOR BUL')]);
    $pic = User::factory()->create([...roleAttributes('PIC BUL')]);

    $this->actingAs($koordinator)->get(route('oil-audits.report'))->assertForbidden();
    $this->actingAs($pic)->get(route('oil-audits.report'))->assertForbidden();
});

test('page title is oil audit action, not report', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $response = $this->actingAs($admin)->get(route('oil-audits.report'));

    $response->assertOk();
    $response->assertSee('Oil Audit Action');
    $response->assertDontSee('Report Audit Oli');
});

test('a machine with multiple audits appears once per audit record, not collapsed to one row', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = actionOilAuditMachine(['machine_number' => '30001']);

    actionOilAudit($machine, ['audited_at' => '2026-08-01']);
    actionOilAudit($machine, ['audited_at' => '2026-08-10']);
    actionOilAudit($machine, ['audited_at' => '2026-08-20']);

    $response = $this->actingAs($admin)->get(route('oil-audits.report'));

    $response->assertOk();
    $rows = $response->viewData('audits');

    expect($rows->count())->toBe(3)
        ->and($rows->pluck('machine_number')->all())->toBe(['30001', '30001', '30001']);
});

test('primary sort is audit date descending, newest audit first', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = actionOilAuditMachine(['machine_number' => '30001']);

    actionOilAudit($machine, ['audited_at' => '2026-08-01']);
    actionOilAudit($machine, ['audited_at' => '2026-08-20']);
    actionOilAudit($machine, ['audited_at' => '2026-08-10']);

    $response = $this->actingAs($admin)->get(route('oil-audits.report'));

    $response->assertOk();
    $dates = $response->viewData('audits')->pluck('audited_at')
        ->map(fn ($d) => Carbon\Carbon::parse($d)->toDateString())->all();

    expect($dates)->toBe(['2026-08-20', '2026-08-10', '2026-08-01']);
});

test('audits sharing the same timestamp fall back to a deterministic id-based order', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = actionOilAuditMachine();
    $sameTimestamp = '2026-08-15 10:00:00';

    $first = actionOilAudit($machine, ['audited_at' => $sameTimestamp]);
    $second = actionOilAudit($machine, ['audited_at' => $sameTimestamp]);
    $third = actionOilAudit($machine, ['audited_at' => $sameTimestamp]);

    $response = $this->actingAs($admin)->get(route('oil-audits.report'));

    $response->assertOk();
    $ids = $response->viewData('audits')->pluck('id')->all();

    // Newest id (most recently created) first, fully deterministic.
    expect($ids)->toBe([$third->id, $second->id, $first->id]);
});

test('machines never audited do not appear on the action page at all', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $audited = actionOilAuditMachine(['machine_number' => 'MC-AUDITED']);
    actionOilAuditMachine(['machine_number' => 'MC-NEVER']);

    actionOilAudit($audited, ['audited_at' => '2026-08-01']);

    $response = $this->actingAs($admin)->get(route('oil-audits.report'));

    $response->assertOk();
    $numbers = $response->viewData('audits')->pluck('machine_number')->all();

    expect($numbers)->toContain('MC-AUDITED')
        ->and($numbers)->not->toContain('MC-NEVER');
});

test('nde and ndb machines with multiple audits each show every audit record as a separate row', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $nde = actionOilAuditMachine(['machine_number' => 'MC-NDE-1', 'machine_type' => oilAuditMachineType('NDE SW')]);
    $ndb = actionOilAuditMachine(['machine_number' => 'MC-NDB-1', 'machine_type' => oilAuditMachineType('NDB ONO')]);
    actionOilAuditMachine(['machine_number' => 'MC-NDE-2', 'machine_type' => oilAuditMachineType('NDE SW')]);
    actionOilAuditMachine(['machine_number' => 'MC-NDB-2', 'machine_type' => oilAuditMachineType('NDB ONO')]);

    actionOilAudit($nde, ['audited_at' => '2026-08-01']);
    actionOilAudit($nde, ['audited_at' => '2026-08-05']);
    actionOilAudit($ndb, ['audited_at' => '2026-08-02']);

    $response = $this->actingAs($admin)->get(route('oil-audits.report'));

    $response->assertOk();
    $numbers = $response->viewData('audits')->pluck('machine_number')->all();

    expect(array_count_values($numbers)['MC-NDE-1'])->toBe(2)
        ->and(array_count_values($numbers)['MC-NDB-1'])->toBe(1)
        ->and($numbers)->not->toContain('MC-NDE-2')
        ->and($numbers)->not->toContain('MC-NDB-2');
});

test('sorting is applied at the query level so pagination stays correct across pages', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = actionOilAuditMachine();

    for ($i = 0; $i < 25; $i++) {
        actionOilAudit($machine, ['audited_at' => now()->subDays(24 - $i)]);
    }

    $page1 = $this->actingAs($admin)->get(route('oil-audits.report', ['page' => 1]));
    $page2 = $this->actingAs($admin)->get(route('oil-audits.report', ['page' => 2]));

    expect($page1->viewData('audits')->count())->toBe(20);
    expect($page2->viewData('audits')->count())->toBe(5);
    // Newest audit (i=24, audited today) must be first overall.
    expect(Carbon\Carbon::parse($page1->viewData('audits')->first()->audited_at)->toDateString())
        ->toBe(now()->toDateString());
});

test('latest condition and pending follow-up are shown for an audit needing action', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = actionOilAuditMachine();
    actionOilAudit($machine, ['condition' => 'KRITIS', 'audited_at' => now()]);

    $response = $this->actingAs($admin)->get(route('oil-audits.report'));

    $response->assertOk();
    $response->assertSee('Kritis');
    $response->assertSee(route('oil-audits.history', $machine->machine_number), false);
});

test('open follow-ups card only includes Hampir Garis, Pas Garis, and Kritis', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $criticalMachines = collect(['HAMPIR_GARIS', 'PAS_GARIS', 'KRITIS'])
        ->map(function (string $condition) {
            $machine = actionOilAuditMachine(['machine_number' => 'MC-'.$condition]);
            actionOilAudit($machine, ['condition' => $condition]);

            return $machine;
        });

    foreach (['OLI_KERUH', 'GLASS_BUREM'] as $condition) {
        $machine = actionOilAuditMachine(['machine_number' => 'MC-'.$condition]);
        actionOilAudit($machine, ['condition' => $condition]);
    }

    $response = $this->actingAs($admin)->get(route('oil-audits.report'));

    $response->assertOk()->assertSee('Open follow-ups');

    $pendingAudits = $response->viewData('pendingAudits');
    expect($response->viewData('summary')['pending'])->toBe(3)
        ->and($pendingAudits->count())->toBe(3)
        ->and($pendingAudits->pluck('condition')->all())
        ->toContain('HAMPIR_GARIS', 'PAS_GARIS', 'KRITIS')
        ->not->toContain('OLI_KERUH', 'GLASS_BUREM')
        ->and($pendingAudits->pluck('machine_number')->all())
        ->toContain(...$criticalMachines->pluck('machine_number')->all())
        ->not->toContain('MC-OLI_KERUH', 'MC-GLASS_BUREM');
});

test('completed follow-up no longer shows as pending', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = actionOilAuditMachine();
    $audit = actionOilAudit($machine, ['condition' => 'KRITIS', 'audited_at' => now()]);

    OilAuditFollowUp::create([
        'oil_audit_id' => $audit->id,
        'problem' => 'Mainshaft',
        'action_taken' => 'Replaced seal',
        'pic_name' => 'Andi',
        'actioned_at' => now(),
    ]);

    $response = $this->actingAs($admin)->get(route('oil-audits.report', ['follow_up' => 1]));

    $response->assertOk();
    expect($response->viewData('audits')->pluck('id'))->not->toContain($audit->id);
});

test('See all of the open follow-ups lists only Hampir Garis, Pas Garis and Kritis', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $ids = [];
    foreach (['HAMPIR_GARIS', 'PAS_GARIS', 'KRITIS', 'OLI_KERUH', 'GLASS_BUREM', 'PANTAU', 'OKE'] as $condition) {
        $ids[$condition] = actionOilAudit(actionOilAuditMachine(), ['condition' => $condition, 'audited_at' => now()])->id;
    }

    $response = $this->actingAs($admin)->get(route('oil-audits.report', ['follow_up' => 1]));

    $response->assertOk();
    expect($response->viewData('audits')->pluck('id')->all())
        ->toEqualCanonicalizing([$ids['HAMPIR_GARIS'], $ids['PAS_GARIS'], $ids['KRITIS']]);
});

test('search finds an audit by its finding text', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = actionOilAuditMachine();
    $audit = actionOilAudit($machine, ['condition' => 'KRITIS', 'audited_at' => now()]);
    actionOilAudit(actionOilAuditMachine(), ['condition' => 'OKE', 'audited_at' => now()]);

    OilAuditFollowUp::create([
        'oil_audit_id' => $audit->id,
        'problem' => 'Kapstan 1',
        'action_taken' => 'Replaced bearing',
        'pic_name' => 'Andi',
        'actioned_at' => now(),
    ]);

    $response = $this->actingAs($admin)->get(route('oil-audits.report', ['search' => 'Kapstan 1']));

    $response->assertOk();
    expect($response->viewData('audits')->pluck('id')->all())->toBe([$audit->id]);
});

test('year, month, condition, and pic filters narrow the audit list', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = actionOilAuditMachine();

    $target = actionOilAudit($machine, [
        'audited_at' => '2026-08-10', 'condition' => 'KRITIS', 'audited_by_name' => 'Andi',
    ]);
    actionOilAudit($machine, ['audited_at' => '2025-08-10', 'condition' => 'KRITIS', 'audited_by_name' => 'Andi']);
    actionOilAudit($machine, ['audited_at' => '2026-09-10', 'condition' => 'KRITIS', 'audited_by_name' => 'Andi']);
    actionOilAudit($machine, ['audited_at' => '2026-08-11', 'condition' => 'OKE', 'audited_by_name' => 'Andi']);
    actionOilAudit($machine, ['audited_at' => '2026-08-12', 'condition' => 'KRITIS', 'audited_by_name' => 'Budi']);

    $response = $this->actingAs($admin)->get(route('oil-audits.report', [
        'year' => 2026, 'month' => 8, 'condition' => 'KRITIS', 'pic' => 'Andi',
    ]));

    $response->assertOk();
    expect($response->viewData('audits')->pluck('id')->all())->toBe([$target->id]);
});

test('finding status filter separates open from completed findings', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = actionOilAuditMachine();

    $open = actionOilAudit($machine, ['condition' => 'KRITIS', 'audited_at' => '2026-08-01']);
    $completedAudit = actionOilAudit($machine, ['condition' => 'KRITIS', 'audited_at' => '2026-08-02']);
    OilAuditFollowUp::create([
        'oil_audit_id' => $completedAudit->id,
        'problem' => 'Kapstan 2',
        'action_taken' => 'Fixed',
        'pic_name' => 'Andi',
        'actioned_at' => '2026-08-03',
    ]);
    $noFinding = actionOilAudit($machine, ['condition' => 'OKE', 'audited_at' => '2026-08-03']);

    $openResponse = $this->actingAs($admin)->get(route('oil-audits.report', ['finding_status' => 'OPEN']));
    $completedResponse = $this->actingAs($admin)->get(route('oil-audits.report', ['finding_status' => 'COMPLETED']));
    $noFindingResponse = $this->actingAs($admin)->get(route('oil-audits.report', ['finding_status' => 'NO_FINDING']));

    expect($openResponse->viewData('audits')->pluck('id')->all())->toBe([$open->id])
        ->and($completedResponse->viewData('audits')->pluck('id')->all())->toBe([$completedAudit->id])
        ->and($noFindingResponse->viewData('audits')->pluck('id')->all())->toBe([$noFinding->id]);
});

test('action duration breakdown only counts findings with an action date', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = actionOilAuditMachine();

    $audit = actionOilAudit($machine, ['condition' => 'KRITIS', 'audited_at' => '2026-08-01']);
    OilAuditFollowUp::create([
        'oil_audit_id' => $audit->id,
        'problem' => 'Kapstan 1',
        'action_taken' => 'Replaced bearing',
        'pic_name' => 'Andi',
        'actioned_at' => '2026-08-04',
    ]);
    // Needs follow-up but has none yet — must not drag the average toward 0.
    actionOilAudit($machine, ['condition' => 'KRITIS', 'audited_at' => '2026-08-02']);

    $response = $this->actingAs($admin)->get(route('oil-audits.report'));

    $response->assertOk();
    $summary = $response->viewData('summary');
    $byCondition = $summary['action_duration_by_condition']->keyBy('code');

    expect($summary['total_finding'])->toBe(2)
        ->and($summary['findings_with_action'])->toBe(1)
        ->and($byCondition['KRITIS']['value'])->toBe(3.0);
});

test('pantau is included in the action duration breakdown even though it is not a required follow-up condition', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = actionOilAuditMachine();

    $audit = actionOilAudit($machine, ['condition' => 'PANTAU', 'audited_at' => '2026-08-01']);
    OilAuditFollowUp::create([
        'oil_audit_id' => $audit->id,
        'refill_oil' => true,
        'full_check' => false,
        'pic_name' => 'Andi',
        'actioned_at' => '2026-08-03',
    ]);

    $response = $this->actingAs($admin)->get(route('oil-audits.report'));

    $response->assertOk();
    $byCondition = $response->viewData('summary')['action_duration_by_condition']->keyBy('code');

    expect($byCondition->has('PANTAU'))->toBeTrue()
        ->and($byCondition['PANTAU']['value'])->toBe(2.0);
});

test('conditions without any completed action are hidden from the duration breakdown', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = actionOilAuditMachine();
    actionOilAudit($machine, ['condition' => 'KRITIS', 'audited_at' => '2026-08-01']); // no follow-up

    $response = $this->actingAs($admin)->get(route('oil-audits.report'));

    $response->assertOk();
    $codes = $response->viewData('summary')['action_duration_by_condition']->pluck('code');

    expect($codes)->not->toContain('KRITIS');
});

test('condition filter narrows the action duration breakdown too', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = actionOilAuditMachine();

    $kritis = actionOilAudit($machine, ['condition' => 'KRITIS', 'audited_at' => '2026-08-01']);
    OilAuditFollowUp::create(['oil_audit_id' => $kritis->id, 'problem' => 'Kapstan 1', 'action_taken' => 'Fixed', 'pic_name' => 'Andi', 'actioned_at' => '2026-08-03']);

    $pantau = actionOilAudit($machine, ['condition' => 'PANTAU', 'audited_at' => '2026-08-01']);
    OilAuditFollowUp::create(['oil_audit_id' => $pantau->id, 'refill_oil' => true, 'full_check' => false, 'pic_name' => 'Andi', 'actioned_at' => '2026-08-02']);

    $response = $this->actingAs($admin)->get(route('oil-audits.report', ['condition' => ['KRITIS']]));

    $response->assertOk();
    $codes = $response->viewData('summary')['action_duration_by_condition']->pluck('code');

    expect($codes->all())->toBe(['KRITIS']);
});

test('area and machine type filters are no longer offered on the action page', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    actionOilAudit(actionOilAuditMachine());

    $this->actingAs($admin)->get(route('oil-audits.report'))
        ->assertOk()
        ->assertDontSee('name="area"', false)
        ->assertDontSee('name="machine_type"', false)
        ->assertSee('name="date_from"', false)
        ->assertSee('name="date_to"', false);
});

test('audit date range filter narrows the action page', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $old = actionOilAudit(actionOilAuditMachine(), ['audited_at' => '2026-09-01 10:00:00']);
    $mid = actionOilAudit(actionOilAuditMachine(), ['audited_at' => '2026-09-15 23:30:00']);
    $new = actionOilAudit(actionOilAuditMachine(), ['audited_at' => '2026-10-05 08:00:00']);

    $ids = fn (array $query) => $this->actingAs($admin)->get(route('oil-audits.report', $query))
        ->viewData('audits')->pluck('id')->sort()->values()->all();

    expect($ids(['date_from' => '2026-09-10', 'date_to' => '2026-09-15']))->toBe([$mid->id])
        ->and($ids(['date_from' => '2026-09-10']))->toBe([$mid->id, $new->id])
        ->and($ids(['date_to' => '2026-09-01']))->toBe([$old->id])
        ->and($ids(['date_from' => 'not-a-date', 'date_to' => '2026-13-45']))->toBe([$old->id, $mid->id, $new->id]);
});

test('action date range filter narrows the action page to findings actioned in range', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $early = actionOilAudit(actionOilAuditMachine(), ['condition' => 'KRITIS', 'audited_at' => '2026-09-01 10:00:00']);
    $mid = actionOilAudit(actionOilAuditMachine(), ['condition' => 'KRITIS', 'audited_at' => '2026-09-01 10:00:00']);
    $late = actionOilAudit(actionOilAuditMachine(), ['condition' => 'KRITIS', 'audited_at' => '2026-09-01 10:00:00']);
    $open = actionOilAudit(actionOilAuditMachine(), ['condition' => 'KRITIS', 'audited_at' => '2026-09-01 10:00:00']);

    foreach ([[$early, '2026-09-03 09:00:00'], [$mid, '2026-09-15 23:30:00'], [$late, '2026-10-05 08:00:00']] as [$audit, $at]) {
        OilAuditFollowUp::create(['oil_audit_id' => $audit->id, 'problem' => 'Kapstan', 'action_taken' => 'Fixed', 'pic_name' => 'Andi', 'actioned_at' => $at]);
    }

    $ids = fn (array $query) => $this->actingAs($admin)->get(route('oil-audits.report', $query))
        ->viewData('audits')->pluck('id')->sort()->values()->all();

    expect($ids(['action_date_from' => '2026-09-10', 'action_date_to' => '2026-09-15']))->toBe([$mid->id])
        ->and($ids(['action_date_from' => '2026-09-10']))->toBe([$mid->id, $late->id])
        ->and($ids(['action_date_to' => '2026-09-03']))->toBe([$early->id])
        ->and($ids(['action_date_from' => 'not-a-date', 'action_date_to' => '2026-13-45']))->toBe([$early->id, $mid->id, $late->id, $open->id]);

    $this->actingAs($admin)->get(route('oil-audits.report'))
        ->assertSee('name="action_date_from"', false)
        ->assertSee('name="action_date_to"', false);
});

test('auditor and action PIC filters are separate on the action page', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $byBudi = actionOilAudit(actionOilAuditMachine(), ['condition' => 'KRITIS', 'audited_by_name' => 'Budi']);
    $byCici = actionOilAudit(actionOilAuditMachine(), ['condition' => 'KRITIS', 'audited_by_name' => 'Cici']);
    $open = actionOilAudit(actionOilAuditMachine(), ['condition' => 'KRITIS', 'audited_by_name' => 'Budi']);

    OilAuditFollowUp::create(['oil_audit_id' => $byBudi->id, 'problem' => 'Kapstan', 'action_taken' => 'Fixed', 'pic_name' => 'Andi', 'actioned_at' => '2026-09-03']);
    OilAuditFollowUp::create(['oil_audit_id' => $byCici->id, 'problem' => 'Kapstan', 'action_taken' => 'Fixed', 'pic_name' => 'Dodi', 'actioned_at' => '2026-09-03']);

    $ids = fn (array $query) => $this->actingAs($admin)->get(route('oil-audits.report', $query))
        ->viewData('audits')->pluck('id')->sort()->values()->all();

    expect($ids(['action_pic' => 'Andi']))->toBe([$byBudi->id])
        ->and($ids(['pic' => 'Budi']))->toBe([$byBudi->id, $open->id])
        ->and($ids(['pic' => 'Budi', 'action_pic' => 'Dodi']))->toBe([]);

    $response = $this->actingAs($admin)->get(route('oil-audits.report'));
    $response->assertOk()
        ->assertSee('name="action_pic"', false)
        ->assertSee('All auditors')
        ->assertSee('All PICs');
    expect($response->viewData('actionPics')->all())->toBe(['Andi', 'Dodi']);
});

test('action year and month filters narrow by actioned date, not audit date', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $sep = actionOilAudit(actionOilAuditMachine(), ['condition' => 'KRITIS', 'audited_at' => '2025-12-20 10:00:00']);
    $nov = actionOilAudit(actionOilAuditMachine(), ['condition' => 'KRITIS', 'audited_at' => '2025-12-20 10:00:00']);
    $lastYear = actionOilAudit(actionOilAuditMachine(), ['condition' => 'KRITIS', 'audited_at' => '2025-08-20 10:00:00']);
    $open = actionOilAudit(actionOilAuditMachine(), ['condition' => 'KRITIS', 'audited_at' => '2026-09-01 10:00:00']);

    foreach ([[$sep, '2026-09-03'], [$nov, '2026-11-10'], [$lastYear, '2025-09-15']] as [$audit, $at]) {
        OilAuditFollowUp::create(['oil_audit_id' => $audit->id, 'problem' => 'Kapstan', 'action_taken' => 'Fixed', 'pic_name' => 'Andi', 'actioned_at' => $at]);
    }

    $ids = fn (array $query) => $this->actingAs($admin)->get(route('oil-audits.report', $query))
        ->viewData('audits')->pluck('id')->sort()->values()->all();

    expect($ids(['action_year' => 2026]))->toBe([$sep->id, $nov->id])
        ->and($ids(['action_month' => [9]]))->toBe([$sep->id, $lastYear->id])
        ->and($ids(['action_month' => [9, 11], 'action_year' => 2026]))->toBe([$sep->id, $nov->id])
        ->and($ids(['action_month' => ['x', '13']]))->toBe([$sep->id, $nov->id, $lastYear->id, $open->id]);

    $this->actingAs($admin)->get(route('oil-audits.report'))
        ->assertSee('name="action_year"', false)
        ->assertSee('action_month', false);
});

test('audit and action filter groups start collapsed on phones unless one of their filters is active', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $this->actingAs($admin)->get(route('oil-audits.report'))
        ->assertSee('aria-labelledby="oa-group-audit" class="grid gap-2 py-3 first:pt-0 last:pb-0 lg:grid-cols-[4.5rem_1fr] lg:items-end lg:gap-4" x-data="{ open: false }"', false)
        ->assertSee('aria-controls="oa-fields-action"', false);

    $this->actingAs($admin)->get(route('oil-audits.report', ['action_pic' => 'Andi']))
        ->assertSee('aria-labelledby="oa-group-audit" class="grid gap-2 py-3 first:pt-0 last:pb-0 lg:grid-cols-[4.5rem_1fr] lg:items-end lg:gap-4" x-data="{ open: false }"', false)
        ->assertSee('aria-labelledby="oa-group-action" class="grid gap-2 py-3 first:pt-0 last:pb-0 lg:grid-cols-[4.5rem_1fr] lg:items-end lg:gap-4" x-data="{ open: true }"', false);
});

test('the action table shows the auditor and the action PIC in separate columns', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $audit = actionOilAudit(actionOilAuditMachine(), ['condition' => 'KRITIS', 'audited_by_name' => 'Budi Auditor']);
    OilAuditFollowUp::create(['oil_audit_id' => $audit->id, 'problem' => 'Kapstan', 'action_taken' => 'Fixed', 'pic_name' => 'Andi Teknisi', 'actioned_at' => '2026-09-03']);

    $this->actingAs($admin)->get(route('oil-audits.report'))
        ->assertOk()
        ->assertSeeInOrder(['>Auditor</th>', '>Action PIC</th>'], false)
        ->assertSeeInOrder(['Budi Auditor', 'Andi Teknisi']);
});

test('oil refill due card lists machines with a pantau streak at the reminder threshold', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $series = function (array $conditions) {
        $machine = actionOilAuditMachine();
        $audits = [];
        foreach ($conditions as $i => $condition) {
            $audits[] = actionOilAudit($machine, ['condition' => $condition, 'audited_at' => now()->subDays(count($conditions) - $i)]);
        }

        return [$machine, $audits];
    };

    [$four] = $series(['OKE', 'PANTAU', 'PANTAU', 'PANTAU', 'PANTAU']);
    [$five] = $series(['PANTAU', 'PANTAU', 'PANTAU', 'PANTAU', 'PANTAU']);
    [$three] = $series(['PANTAU', 'PANTAU', 'PANTAU']);
    [$broken] = $series(['PANTAU', 'PANTAU', 'PANTAU', 'PANTAU', 'OKE', 'PANTAU']);
    [$refilled, $refilledAudits] = $series(['PANTAU', 'PANTAU', 'PANTAU', 'PANTAU', 'PANTAU']);
    OilAuditFollowUp::create(['oil_audit_id' => end($refilledAudits)->id, 'refill_oil' => true, 'full_check' => false, 'pic_name' => 'Andi', 'actioned_at' => now()]);

    $response = $this->actingAs($admin)->get(route('oil-audits.report'));
    $response->assertOk()->assertSee('Oil refill due');

    $due = $response->viewData('refillDue')->mapWithKeys(fn ($row) => [$row->machine_id => (int) $row->streak])->all();
    expect($due)->toBe([$five->id => 5, $four->id => 4]);

    foreach ([$four, $five, $three, $broken, $refilled] as $machine) {
        $expected = OilAudit::pantauStreak($machine->id) >= OilAudit::PANTAU_REFILL_REMINDER_AT;
        expect(array_key_exists($machine->id, $due))->toBe($expected);
    }
});

test('oil refill due card is hidden when no machine reaches the threshold', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = actionOilAuditMachine();
    foreach (range(3, 1) as $daysAgo) {
        actionOilAudit($machine, ['condition' => 'PANTAU', 'audited_at' => now()->subDays($daysAgo)]);
    }

    $this->actingAs($admin)->get(route('oil-audits.report'))
        ->assertOk()
        ->assertDontSee('Oil refill due');
});

test('other filters still work on the action page', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $kritis = actionOilAudit(actionOilAuditMachine(), ['condition' => 'KRITIS']);
    actionOilAudit(actionOilAuditMachine(), ['condition' => 'OKE']);

    $response = $this->actingAs($admin)->get(route('oil-audits.report', ['condition' => 'KRITIS']));

    $response->assertOk();
    expect($response->viewData('audits')->pluck('id')->all())->toBe([$kritis->id]);
});

test('month filter accepts several months and a legacy single month', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $sep = actionOilAudit(actionOilAuditMachine(), ['audited_at' => '2026-09-10 10:00:00']);
    $oct = actionOilAudit(actionOilAuditMachine(), ['audited_at' => '2026-10-05 10:00:00']);
    $nov = actionOilAudit(actionOilAuditMachine(), ['audited_at' => '2026-11-02 10:00:00']);

    $ids = fn (array $query) => $this->actingAs($admin)->get(route('oil-audits.report', $query))
        ->viewData('audits')->pluck('id')->sort()->values()->all();

    expect($ids(['month' => [9, 11]]))->toBe([$sep->id, $nov->id])
        ->and($ids(['month' => '10']))->toBe([$oct->id])
        ->and($ids(['month' => [10], 'year' => 2026]))->toBe([$oct->id])
        ->and($ids(['month' => ['x', '13', '0']]))->toBe([$sep->id, $oct->id, $nov->id]);

    $this->actingAs($admin)->get(route('oil-audits.report', ['month' => [9, 11]]))
        ->assertOk()
        ->assertSee('Month (2)');
    $this->actingAs($admin)->get(route('oil-audits.report'))->assertSee('All months');
});

test('condition filter accepts several conditions and a legacy single condition', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $oke = actionOilAudit(actionOilAuditMachine(), ['condition' => 'OKE']);
    $kritis = actionOilAudit(actionOilAuditMachine(), ['condition' => 'KRITIS']);
    $pas = actionOilAudit(actionOilAuditMachine(), ['condition' => 'PAS_GARIS']);

    $ids = fn (array $query) => $this->actingAs($admin)->get(route('oil-audits.report', $query))
        ->viewData('audits')->pluck('id')->sort()->values()->all();

    expect($ids(['condition' => ['KRITIS', 'PAS_GARIS']]))->toBe([$kritis->id, $pas->id])
        ->and($ids(['condition' => 'OKE']))->toBe([$oke->id])
        ->and($ids(['condition' => ['BOGUS']]))->toBe([$oke->id, $kritis->id, $pas->id]);

    $this->actingAs($admin)->get(route('oil-audits.report', ['condition' => ['KRITIS', 'PAS_GARIS']]))
        ->assertSee('Condition (2)');
    $this->actingAs($admin)->get(route('oil-audits.report'))->assertSee('All conditions');
});

test('back from history keeps the date and multi-select filters of the action page', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = actionOilAuditMachine();
    actionOilAudit($machine);
    $return = 'date_from=2026-09-01&date_to=2026-09-30&action_date_from=2026-09-05&action_date_to=2026-09-20&action_pic=Andi&action_year=2026&action_month%5B0%5D=9&condition%5B0%5D=KRITIS&condition%5B1%5D=OKE&month%5B0%5D=9';

    $response = $this->actingAs($admin)->get(route('oil-audits.history', [
        $machine->machine_number, 'from' => 'action', 'return' => $return,
    ]));

    $expected = route('oil-audits.report', [
        'date_from' => '2026-09-01', 'date_to' => '2026-09-30', 'action_date_from' => '2026-09-05', 'action_date_to' => '2026-09-20', 'action_pic' => 'Andi', 'action_year' => '2026', 'action_month' => ['9'], 'condition' => ['KRITIS', 'OKE'], 'month' => [9],
    ]);
    $response->assertOk()->assertSee(e($expected), false);
});
