<?php

use App\Models\Machine;
use App\Models\OilAudit;
use App\Models\User;

function navHistoryMachine(array $overrides = []): Machine
{
    return Machine::create(array_merge([
        'machine_number' => '30001',
        'area' => 'WWD',
        'machine_type' => 'NDE',
        'status' => 'ACTIVE',
    ], $overrides));
}

function navHistoryAudit(Machine $machine, array $overrides = []): OilAudit
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

test('report page view link carries from=report and the current filters as return', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = navHistoryMachine();
    navHistoryAudit($machine);

    $response = $this->actingAs($admin)->get(route('reports.oil-audit', ['area' => 'WWD', 'year' => 2026]));

    $response->assertOk();
    $response->assertSee('from=report', false);
    $response->assertSee('return=area%3DWWD%26year%3D2026', false);
});

test('action page view link carries from=action and the current filters as return', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = navHistoryMachine();
    navHistoryAudit($machine);

    $response = $this->actingAs($admin)->get(route('oil-audits.report', ['machine_type' => 'NDE']));

    $response->assertOk();
    $response->assertSee('from=action', false);
    $response->assertSee('return=machine_type%3DNDE', false);
});

test('history opened from report shows a back link to the report page with filters restored', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = navHistoryMachine();
    navHistoryAudit($machine);

    $response = $this->actingAs($admin)->get(route('oil-audits.history', [
        $machine->machine_number, 'from' => 'report', 'return' => 'area=WWD&year=2026',
    ]));

    $response->assertOk();
    $response->assertSee('Back to Oil Audit Report');
    $response->assertSee(route('reports.oil-audit', ['area' => 'WWD', 'year' => 2026]));
});

test('history opened from action shows a back link to the action page with filters restored', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = navHistoryMachine();
    navHistoryAudit($machine);

    $response = $this->actingAs($admin)->get(route('oil-audits.history', [
        $machine->machine_number, 'from' => 'action', 'return' => 'machine_type=NDE',
    ]));

    $response->assertOk();
    $response->assertSee('Back to Oil Audit Action');
    $response->assertSee(route('oil-audits.report', ['machine_type' => 'NDE']));
});

test('history opened without a from parameter falls back safely to the report page', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = navHistoryMachine();
    navHistoryAudit($machine);

    $response = $this->actingAs($admin)->get(route('oil-audits.history', $machine->machine_number));

    $response->assertOk();
    $response->assertSee('Back to Oil Audit Report');
    $response->assertSee(route('reports.oil-audit'), false);
});

test('history page renders standalone without the app sidebar or topbar', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = navHistoryMachine();
    navHistoryAudit($machine);

    $response = $this->actingAs($admin)->get(route('oil-audits.history', $machine->machine_number));

    $response->assertOk();
    $response->assertDontSee('Preventive Maintenance System');
    $response->assertDontSee('Account menu', false);
});

test('an invalid from value never causes an arbitrary redirect and falls back to the report page', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = navHistoryMachine();
    navHistoryAudit($machine);

    $response = $this->actingAs($admin)->get(route('oil-audits.history', [
        $machine->machine_number, 'from' => 'invalid',
    ]));

    $response->assertOk();
    $response->assertSee('Back to Oil Audit Report');
    $response->assertDontSee('evil-site.com');
});

test('an arbitrary redirect query value is never used as the back destination', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = navHistoryMachine();
    navHistoryAudit($machine);

    $response = $this->actingAs($admin)->get(route('oil-audits.history', $machine->machine_number).'?redirect=https://evil-site.com');

    $response->assertOk();
    $response->assertDontSee('evil-site.com');
});

test('unrecognized return keys are ignored, only whitelisted filter keys are restored', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = navHistoryMachine();
    navHistoryAudit($machine);

    $response = $this->actingAs($admin)->get(route('oil-audits.history', [
        $machine->machine_number,
        'from' => 'report',
        'return' => 'area=WWD&malicious=<script>alert(1)</script>',
    ]));

    $response->assertOk();
    $response->assertDontSee('malicious');
    $response->assertDontSee('<script>alert(1)</script>', false);
    $response->assertSee(route('reports.oil-audit', ['area' => 'WWD']), false);
});
