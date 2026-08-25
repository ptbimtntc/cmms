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
        'machine_type' => 'NDE',
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
    $koordinator = User::factory()->create(['role' => User::ROLE_KOORDINATOR_BUL]);
    $pic = User::factory()->create(['role' => User::ROLE_PIC_BUL]);

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
    $nde = actionOilAuditMachine(['machine_number' => 'MC-NDE-1', 'machine_type' => 'NDE']);
    $ndb = actionOilAuditMachine(['machine_number' => 'MC-NDB-1', 'machine_type' => 'NDB']);
    actionOilAuditMachine(['machine_number' => 'MC-NDE-2', 'machine_type' => 'NDE']);
    actionOilAuditMachine(['machine_number' => 'MC-NDB-2', 'machine_type' => 'NDB']);

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

test('average action duration only counts findings with an action date', function () {
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

    expect($summary['total_finding'])->toBe(2)
        ->and($summary['findings_with_action'])->toBe(1)
        ->and($summary['average_action_duration'])->toBe(3.0);
});

test('filters still work on the action page', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $nde = actionOilAuditMachine(['machine_type' => 'NDE']);
    $ndb = actionOilAuditMachine(['machine_type' => 'NDB']);

    actionOilAudit($nde);
    actionOilAudit($ndb);

    $response = $this->actingAs($admin)->get(route('oil-audits.report', ['machine_type' => 'NDB']));

    $response->assertOk();
    expect($response->viewData('audits')->pluck('machine_number')->all())->toBe([$ndb->machine_number]);
});
