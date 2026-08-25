<?php

use App\Models\Machine;
use App\Models\OilAudit;
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

test('a machine appears exactly once even when it has many audit records', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = reportOilAuditMachine(['machine_number' => '30001']);

    reportOilAudit($machine, ['audited_at' => '2026-08-01', 'condition' => 'OKE']);
    reportOilAudit($machine, ['audited_at' => '2026-08-10', 'condition' => 'PANTAU']);
    reportOilAudit($machine, ['audited_at' => '2026-08-20', 'condition' => 'KRITIS']);
    reportOilAudit($machine, ['audited_at' => '2026-08-05', 'condition' => 'OKE']);
    reportOilAudit($machine, ['audited_at' => '2026-08-15', 'condition' => 'PANTAU']);

    $response = $this->actingAs($admin)->get(route('reports.oil-audit'));

    $response->assertOk();
    $rows = $response->viewData('machines')->where('machine_number', '30001');

    expect($rows)->toHaveCount(1);
    $row = $rows->first();
    // Latest audit is 2026-08-20 (KRITIS) — the newest by date, not by
    // insertion order or condition severity.
    expect($row->latestOilAudit->condition)->toBe('KRITIS')
        ->and($row->latestOilAudit->audited_at->toDateString())->toBe('2026-08-20');
});

test('primary sort is machine number ascending, not audit date', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machineC = reportOilAuditMachine(['machine_number' => '30003']);
    $machineA = reportOilAuditMachine(['machine_number' => '30001']);
    $machineB = reportOilAuditMachine(['machine_number' => '30002']);

    // Deliberately audited in an order that would reverse the sort if the
    // page sorted by audit date instead of machine number.
    reportOilAudit($machineC, ['audited_at' => '2026-08-20']);
    reportOilAudit($machineA, ['audited_at' => '2026-08-01']);
    reportOilAudit($machineB, ['audited_at' => '2026-08-10']);

    $response = $this->actingAs($admin)->get(route('reports.oil-audit'));

    $response->assertOk();
    expect($response->viewData('machines')->pluck('machine_number')->all())->toBe(['30001', '30002', '30003']);
});

test('sorting is applied at the query level so pagination stays correct across pages', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    // Machine numbers deliberately out of insertion order, each with
    // several audits — must still collapse to one row per machine.
    foreach (['30005', '30001', '30004', '30002', '30003'] as $number) {
        $machine = reportOilAuditMachine(['machine_number' => $number]);
        for ($j = 0; $j < 5; $j++) {
            reportOilAudit($machine, ['audited_at' => now()->subDays($j)]);
        }
    }

    $page1 = $this->actingAs($admin)->get(route('reports.oil-audit', ['page' => 1]));
    $page2 = $this->actingAs($admin)->get(route('reports.oil-audit', ['page' => 2]));

    // 5 machines total, all fit on page 1 (5 rows, not 25).
    expect($page1->viewData('machines')->pluck('machine_number')->all())
        ->toBe(['30001', '30002', '30003', '30004', '30005']);
    expect($page1->viewData('machines'))->toHaveCount(5);
    expect($page2->viewData('machines'))->toHaveCount(0);
});

test('a machine that has never been audited still appears with a safe placeholder state', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    reportOilAuditMachine(['machine_number' => 'MC-NEVER']);

    $response = $this->actingAs($admin)->get(route('reports.oil-audit'));

    $response->assertOk();
    $row = $response->viewData('machines')->firstWhere('machine_number', 'MC-NEVER');

    expect($row)->not->toBeNull()
        ->and($row->latestOilAudit)->toBeNull();

    $response->assertSee('Belum Audit');
});

test('nde and ndb machines are both fully represented, exactly once each, regardless of audit history', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $ndeAudited = reportOilAuditMachine(['machine_number' => 'MC-NDE-1', 'machine_type' => 'NDE']);
    reportOilAuditMachine(['machine_number' => 'MC-NDE-2', 'machine_type' => 'NDE']);
    $ndbAudited = reportOilAuditMachine(['machine_number' => 'MC-NDB-1', 'machine_type' => 'NDB']);
    reportOilAuditMachine(['machine_number' => 'MC-NDB-2', 'machine_type' => 'NDB']);
    // A non-scope machine type must never appear.
    reportOilAuditMachine(['machine_number' => 'MC-SHX', 'machine_type' => 'SHX']);

    // Multiple audits for the audited ones — must still be 1 row each.
    reportOilAudit($ndeAudited, ['audited_at' => '2026-08-01']);
    reportOilAudit($ndeAudited, ['audited_at' => '2026-08-10']);
    reportOilAudit($ndbAudited, ['audited_at' => '2026-08-01']);

    $response = $this->actingAs($admin)->get(route('reports.oil-audit'));

    $response->assertOk();
    $numbers = $response->viewData('machines')->pluck('machine_number')->all();
    $counts = array_count_values($numbers);

    expect($numbers)->toContain('MC-NDE-1', 'MC-NDE-2', 'MC-NDB-1', 'MC-NDB-2')
        ->and($numbers)->not->toContain('MC-SHX')
        ->and($counts['MC-NDE-1'])->toBe(1)
        ->and($counts['MC-NDE-2'])->toBe(1)
        ->and($counts['MC-NDB-1'])->toBe(1)
        ->and($counts['MC-NDB-2'])->toBe(1);
});

test('bul machines never appear since oil audit is wwd-only', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    reportOilAuditMachine(['machine_number' => 'MC-BUL', 'area' => 'BUL', 'machine_type' => 'BF']);

    $response = $this->actingAs($admin)->get(route('reports.oil-audit'));

    $response->assertOk();
    expect($response->viewData('machines')->pluck('machine_number'))->not->toContain('MC-BUL');
});

test('area bul filter returns no rows since oil audit scope is fixed to wwd', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = reportOilAuditMachine();
    reportOilAudit($machine);

    $response = $this->actingAs($admin)->get(route('reports.oil-audit', ['area' => 'BUL']));

    $response->assertOk();
    expect($response->viewData('summary')['total_machines'])->toBe(0);
});

test('machine type filter narrows to the selected type', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $nde = reportOilAuditMachine(['machine_type' => 'NDE']);
    $ndb = reportOilAuditMachine(['machine_type' => 'NDB']);

    $response = $this->actingAs($admin)->get(route('reports.oil-audit', ['machine_type' => 'NDB']));

    $response->assertOk();
    expect($response->viewData('machines')->pluck('machine_number')->all())->toBe([$ndb->machine_number]);
});

test('a condition filter targets the latest audit and correctly excludes never-audited machines', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $audited = reportOilAuditMachine(['machine_number' => 'MC-AUDITED']);
    reportOilAuditMachine(['machine_number' => 'MC-NEVER']);

    reportOilAudit($audited, ['condition' => 'KRITIS', 'audited_at' => '2026-08-01']);

    $response = $this->actingAs($admin)->get(route('reports.oil-audit', ['condition' => 'KRITIS']));

    $response->assertOk();
    expect($response->viewData('machines')->pluck('machine_number')->all())->toBe(['MC-AUDITED']);
});

test('a condition filter only looks at the latest audit, ignoring older matching audits', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = reportOilAuditMachine();

    // Was KRITIS, but the latest audit is now OKE — must not match KRITIS filter.
    reportOilAudit($machine, ['condition' => 'KRITIS', 'audited_at' => '2026-08-01']);
    reportOilAudit($machine, ['condition' => 'OKE', 'audited_at' => '2026-08-10']);

    $response = $this->actingAs($admin)->get(route('reports.oil-audit', ['condition' => 'KRITIS']));

    $response->assertOk();
    expect($response->viewData('machines')->pluck('machine_number')->all())->not->toContain($machine->machine_number);
});

test('year and month filters target the latest audit date only', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = reportOilAuditMachine(['machine_number' => 'MC-YEAR']);

    // Older audit is 2025, but the latest is 2026 — filtering by 2025 must
    // exclude it, since only the latest audit's date is considered.
    reportOilAudit($machine, ['audited_at' => '2025-08-15']);
    reportOilAudit($machine, ['audited_at' => '2026-01-15']);

    $matches2026 = $this->actingAs($admin)->get(route('reports.oil-audit', ['year' => 2026, 'month' => 1]));
    $matches2025 = $this->actingAs($admin)->get(route('reports.oil-audit', ['year' => 2025]));

    expect($matches2026->viewData('machines')->pluck('machine_number')->all())->toBe(['MC-YEAR']);
    expect($matches2025->viewData('machines')->pluck('machine_number')->all())->not->toContain('MC-YEAR');
});

test('search matches machine number, machine type, or description', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $byNumber = reportOilAuditMachine(['machine_number' => 'UNIQUE-OIL-001']);
    reportOilAuditMachine(['machine_number' => 'OTHER-OIL-002']);
    $byDescription = reportOilAuditMachine(['machine_number' => 'MC-DESC', 'description' => 'Special Gearbox Unit']);

    $numberResponse = $this->actingAs($admin)->get(route('reports.oil-audit', ['search' => 'UNIQUE-OIL-001']));
    $descriptionResponse = $this->actingAs($admin)->get(route('reports.oil-audit', ['search' => 'Special Gearbox']));

    expect($numberResponse->viewData('machines')->pluck('machine_number')->all())->toBe([$byNumber->machine_number]);
    expect($descriptionResponse->viewData('machines')->pluck('machine_number')->all())->toBe([$byDescription->machine_number]);
});

test('view action links to the existing oil audit history page', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = reportOilAuditMachine();
    reportOilAudit($machine);

    $response = $this->actingAs($admin)->get(route('reports.oil-audit'));

    $response->assertOk();
    $response->assertSee(route('oil-audits.history', $machine->machine_number), false);
});

test('summary counts machines, never distinct audit records', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $auditedTwice = reportOilAuditMachine(['machine_number' => 'MC-1']);
    $auditedOnce = reportOilAuditMachine(['machine_number' => 'MC-2']);
    reportOilAuditMachine(['machine_number' => 'MC-3']); // never audited

    reportOilAudit($auditedTwice, ['condition' => 'KRITIS', 'audited_at' => '2026-08-01']);
    reportOilAudit($auditedTwice, ['condition' => 'KRITIS', 'audited_at' => '2026-08-10']);
    reportOilAudit($auditedOnce, ['condition' => 'OKE', 'audited_at' => '2026-08-05']);

    $response = $this->actingAs($admin)->get(route('reports.oil-audit'));

    $response->assertOk();
    $summary = $response->viewData('summary');

    // 3 machines total (not 3 audit records), 1 never audited, 1 with a
    // latest-audit finding (auditedTwice's latest is still KRITIS).
    expect($summary['total_machines'])->toBe(3)
        ->and($summary['never_audited'])->toBe(1)
        ->and($summary['with_latest_finding'])->toBe(1);
});

test('pagination is machine-centric: 20 machines per page regardless of audit count per machine', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    for ($i = 0; $i < 25; $i++) {
        $machine = reportOilAuditMachine(['machine_number' => 'MC-'.str_pad((string) $i, 3, '0', STR_PAD_LEFT)]);
        // Give the first few machines multiple audits — should not change
        // how many machines fit on a page.
        reportOilAudit($machine, ['audited_at' => now()->subDays($i)]);
        if ($i < 3) {
            reportOilAudit($machine, ['audited_at' => now()->subDays($i + 100)]);
        }
    }

    $page1 = $this->actingAs($admin)->get(route('reports.oil-audit', ['page' => 1]));
    $page2 = $this->actingAs($admin)->get(route('reports.oil-audit', ['page' => 2]));

    expect($page1->viewData('machines'))->toHaveCount(20);
    expect($page2->viewData('machines'))->toHaveCount(5);
    expect($page1->viewData('machines')->pluck('machine_number')->unique())->toHaveCount(20);
});
