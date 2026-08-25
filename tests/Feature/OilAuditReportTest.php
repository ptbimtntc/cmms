<?php

use App\Models\Machine;
use App\Models\OilAudit;
use App\Models\OilAuditFollowUp;
use App\Models\User;

function reportOilAuditMachine(array $overrides = []): Machine
{
    return Machine::create(array_merge([
        'machine_number' => 'MC-'.uniqid(),
        'area' => 'WWD',
        'machine_type' => 'NDE',
        'status' => 'ACTIVE',
    ], $overrides));
}

function reportOilAudit(Machine $machine, array $overrides = []): OilAudit
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

test('report page requires authentication', function () {
    $this->get(route('reports.oil-audit'))->assertRedirect(route('login'));
});

test('guest role cannot access the oil audit report', function () {
    $guest = User::factory()->create(['role' => User::ROLE_GUEST]);

    $this->actingAs($guest)->get(route('reports.oil-audit'))->assertForbidden();
});

test('koordinator bul cannot access the oil audit report', function () {
    $koordinator = User::factory()->create(['role' => User::ROLE_KOORDINATOR_BUL]);

    $this->actingAs($koordinator)->get(route('reports.oil-audit'))->assertForbidden();
});

test('pic bul cannot access the oil audit report', function () {
    $pic = User::factory()->create(['role' => User::ROLE_PIC_BUL]);

    $this->actingAs($pic)->get(route('reports.oil-audit'))->assertForbidden();
});

test('admin koordinator wwd and pic wwd can all access the oil audit report', function () {
    foreach ([User::ROLE_ADMIN, User::ROLE_KOORDINATOR_WWD, User::ROLE_PIC_WWD] as $role) {
        $user = User::factory()->create(['role' => $role]);

        $this->actingAs($user)->get(route('reports.oil-audit'))->assertOk();
    }
});

test('area all shows every wwd audit by default', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = reportOilAuditMachine();

    reportOilAudit($machine, ['audited_at' => '2026-08-01']);
    reportOilAudit($machine, ['audited_at' => '2026-08-02']);

    $response = $this->actingAs($admin)->get(route('reports.oil-audit', ['year' => 2026, 'month' => 8]));

    $response->assertOk();
    expect($response->viewData('summary')['total_audit'])->toBe(2);
});

test('area wwd filter still shows the same wwd-only data', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = reportOilAuditMachine();

    reportOilAudit($machine, ['audited_at' => '2026-08-01']);

    $response = $this->actingAs($admin)->get(route('reports.oil-audit', [
        'year' => 2026, 'month' => 8, 'area' => 'WWD',
    ]));

    $response->assertOk();
    expect($response->viewData('summary')['total_audit'])->toBe(1);
});

test('area bul always returns no data since oil audit is wwd-only by business rule', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = reportOilAuditMachine();

    reportOilAudit($machine, ['audited_at' => '2026-08-01']);

    $response = $this->actingAs($admin)->get(route('reports.oil-audit', [
        'year' => 2026, 'month' => 8, 'area' => 'BUL',
    ]));

    $response->assertOk();
    expect($response->viewData('summary')['total_audit'])->toBe(0);
    $response->assertSee('No Oil Audit data found.');
});

test('year filter isolates audits from other years', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = reportOilAuditMachine(['machine_number' => 'MC-YEAR']);

    reportOilAudit($machine, ['audited_at' => '2026-01-15']);
    reportOilAudit($machine, ['audited_at' => '2025-01-15']);

    $response = $this->actingAs($admin)->get(route('reports.oil-audit', ['year' => 2026]));

    $response->assertOk();
    expect($response->viewData('summary')['total_audit'])->toBe(1);
});

test('month filter isolates audits from other months', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = reportOilAuditMachine();

    reportOilAudit($machine, ['audited_at' => '2026-08-05']);
    reportOilAudit($machine, ['audited_at' => '2026-09-05']);

    $response = $this->actingAs($admin)->get(route('reports.oil-audit', ['year' => 2026, 'month' => 8]));

    $response->assertOk();
    expect($response->viewData('summary')['total_audit'])->toBe(1);
});

test('machine filter narrows to the selected machine', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machineA = reportOilAuditMachine(['machine_number' => 'MC-A']);
    $machineB = reportOilAuditMachine(['machine_number' => 'MC-B']);

    reportOilAudit($machineA, ['audited_at' => '2026-08-01']);
    reportOilAudit($machineB, ['audited_at' => '2026-08-02']);

    $response = $this->actingAs($admin)->get(route('reports.oil-audit', [
        'year' => 2026, 'month' => 8, 'machine' => 'MC-A',
    ]));

    $response->assertOk();
    expect($response->viewData('summary')['total_audit'])->toBe(1);
    // Checked via the table data, not raw page text — the Machine filter
    // dropdown itself legitimately lists every machine in scope (including
    // MC-B) as a selectable option, independent of which one is active.
    expect($response->viewData('audits')->pluck('machine_number')->all())->toBe(['MC-A']);
});

test('pic filter narrows to the selected auditor', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = reportOilAuditMachine();

    reportOilAudit($machine, ['audited_at' => '2026-08-01', 'audited_by_name' => 'Andi']);
    reportOilAudit($machine, ['audited_at' => '2026-08-02', 'audited_by_name' => 'Budi']);

    $response = $this->actingAs($admin)->get(route('reports.oil-audit', [
        'year' => 2026, 'month' => 8, 'pic' => 'Andi',
    ]));

    $response->assertOk();
    expect($response->viewData('summary')['total_audit'])->toBe(1);
});

test('search matches machine number', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = reportOilAuditMachine(['machine_number' => 'UNIQUE-OIL-001']);
    $other = reportOilAuditMachine(['machine_number' => 'OTHER-OIL-002']);

    reportOilAudit($machine);
    reportOilAudit($other);

    $response = $this->actingAs($admin)->get(route('reports.oil-audit', ['search' => 'UNIQUE-OIL-001']));

    $response->assertOk();
    expect($response->viewData('audits')->total())->toBe(1);
});

test('average action duration only counts findings with an action date, matching the 3-day example', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = reportOilAuditMachine();

    $audit = reportOilAudit($machine, [
        'condition' => 'KRITIS',
        'audited_at' => '2026-08-01',
    ]);

    OilAuditFollowUp::create([
        'oil_audit_id' => $audit->id,
        'problem' => 'Kapstan 1',
        'action_taken' => 'Replaced bearing',
        'pic_name' => 'Andi',
        'actioned_at' => '2026-08-04',
    ]);

    $response = $this->actingAs($admin)->get(route('reports.oil-audit', ['year' => 2026, 'month' => 8]));

    $response->assertOk();
    expect($response->viewData('summary')['average_action_duration'])->toBe(3.0);
});

test('findings without an action date are excluded from the average, not counted as zero', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = reportOilAuditMachine();

    // Has a follow-up: 4-day duration.
    $audited = reportOilAudit($machine, ['condition' => 'KRITIS', 'audited_at' => '2026-08-01']);
    OilAuditFollowUp::create([
        'oil_audit_id' => $audited->id,
        'problem' => 'Mainshaft',
        'action_taken' => 'Fixed',
        'pic_name' => 'Andi',
        'actioned_at' => '2026-08-05',
    ]);

    // Needs follow-up but has none yet — must not drag the average toward 0.
    reportOilAudit($machine, ['condition' => 'KRITIS', 'audited_at' => '2026-08-02']);

    $response = $this->actingAs($admin)->get(route('reports.oil-audit', ['year' => 2026, 'month' => 8]));

    $response->assertOk();
    $summary = $response->viewData('summary');

    expect($summary['total_finding'])->toBe(2)
        ->and($summary['findings_with_action'])->toBe(1)
        ->and($summary['average_action_duration'])->toBe(4.0);
});

test('average action duration is null when no findings have an action date yet', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = reportOilAuditMachine();

    reportOilAudit($machine, ['condition' => 'KRITIS', 'audited_at' => '2026-08-01']);

    $response = $this->actingAs($admin)->get(route('reports.oil-audit', ['year' => 2026, 'month' => 8]));

    $response->assertOk();
    expect($response->viewData('summary')['average_action_duration'])->toBeNull();
    $response->assertSee('-', false);
});

test('finding status filter separates open from completed findings', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = reportOilAuditMachine();

    $open = reportOilAudit($machine, ['condition' => 'KRITIS', 'audited_at' => '2026-08-01']);
    $completedAudit = reportOilAudit($machine, ['condition' => 'KRITIS', 'audited_at' => '2026-08-02']);
    OilAuditFollowUp::create([
        'oil_audit_id' => $completedAudit->id,
        'problem' => 'Kapstan 2',
        'action_taken' => 'Fixed',
        'pic_name' => 'Andi',
        'actioned_at' => '2026-08-03',
    ]);
    reportOilAudit($machine, ['condition' => 'OKE', 'audited_at' => '2026-08-03']);

    $openResponse = $this->actingAs($admin)->get(route('reports.oil-audit', [
        'year' => 2026, 'month' => 8, 'finding_status' => 'OPEN',
    ]));
    $completedResponse = $this->actingAs($admin)->get(route('reports.oil-audit', [
        'year' => 2026, 'month' => 8, 'finding_status' => 'COMPLETED',
    ]));
    $noFindingResponse = $this->actingAs($admin)->get(route('reports.oil-audit', [
        'year' => 2026, 'month' => 8, 'finding_status' => 'NO_FINDING',
    ]));

    expect($openResponse->viewData('summary')['total_audit'])->toBe(1)
        ->and($completedResponse->viewData('summary')['total_audit'])->toBe(1)
        ->and($noFindingResponse->viewData('summary')['total_audit'])->toBe(1);
});

test('pagination preserves active filters across pages', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = reportOilAuditMachine();

    for ($i = 0; $i < 25; $i++) {
        reportOilAudit($machine, ['audited_at' => now()->subDays($i)]);
    }

    $response = $this->actingAs($admin)->get(route('reports.oil-audit', ['machine' => $machine->machine_number, 'page' => 2]));

    $response->assertOk();
    $response->assertSee('machine='.$machine->machine_number, false);
    expect($response->viewData('audits')->currentPage())->toBe(2);
});

test('view action links to the existing oil audit history page', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = reportOilAuditMachine();
    reportOilAudit($machine);

    $response = $this->actingAs($admin)->get(route('reports.oil-audit'));

    $response->assertOk();
    $response->assertSee(route('oil-audits.history', $machine->machine_number), false);
});
