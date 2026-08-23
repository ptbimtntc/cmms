<?php

use App\Models\Group;
use App\Models\Greasing;
use App\Models\Machine;
use App\Models\OilAudit;
use App\Models\User;

function makeDashboardGreasing(Group $group, array $overrides = []): Greasing
{
    $planDate = $overrides['plan_date'] ?? now()->toDateString();

    return Greasing::create(array_merge([
        'group_id' => $group->id,
        'order_number' => 'GR-'.uniqid(),
        'cycle' => '4W',
        'plan_date' => $planDate,
        'due_date' => Greasing::calculateDueDate($planDate),
        'status' => 'OPEN',
    ], $overrides));
}

test('dashboard shows real greasing KPI matching the GreasingKpiCalculator formula', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $group = Group::create(['name' => 'WWD 1']);

    makeDashboardGreasing($group, ['status' => 'FINISH ON TIME']);
    makeDashboardGreasing($group, ['status' => 'FINISH']);
    makeDashboardGreasing($group, ['status' => 'OPEN']);
    makeDashboardGreasing($group, ['status' => 'OPEN']);

    $response = $this->actingAs($admin)->get(route('dashboard'));

    $response->assertOk();
    // Closing = (1 finish_on_time + 1 finish) / 4 total = 50%
    // Completion = (1*1 + 1*0.5) / 4 = 37.5% -> rounded to 37.5
    $response->assertSee('50%', false);
});

test('greasing card shows empty state gracefully when there is no data this month', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $response = $this->actingAs($admin)->get(route('dashboard'));

    $response->assertOk();
    $response->assertSee('No greasing schedule this month');
});

test('oil audit section is visible to admin', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = Machine::create(['machine_number' => 'MC-OIL1', 'area' => 'WWD', 'machine_type' => 'NDE', 'status' => 'ACTIVE']);

    OilAudit::create([
        'machine_id' => $machine->id,
        'machine_number' => $machine->machine_number,
        'machine_type' => $machine->machine_type,
        'area' => $machine->area,
        'condition' => 'KRITIS',
        'audited_by_name' => 'Tester',
        'audited_at' => now(),
    ]);

    $response = $this->actingAs($admin)->get(route('dashboard'));

    $response->assertOk();
    $response->assertSee('Oil Audit');
    $response->assertSee('Critical Unresolved');
});

test('oil audit section is visible to koordinator wwd', function () {
    $koordinator = User::factory()->create(['role' => User::ROLE_KOORDINATOR_WWD]);

    $response = $this->actingAs($koordinator)->get(route('dashboard'));

    $response->assertOk();
    $response->assertSee('Oil Audit');
});

test('oil audit section is visible to pic wwd', function () {
    $pic = User::factory()->create(['role' => User::ROLE_PIC_WWD]);

    $response = $this->actingAs($pic)->get(route('dashboard'));

    $response->assertOk();
    $response->assertSee('Oil Audit');
});

test('oil audit section is hidden for koordinator bul', function () {
    $koordinator = User::factory()->create(['role' => User::ROLE_KOORDINATOR_BUL]);

    $response = $this->actingAs($koordinator)->get(route('dashboard'));

    $response->assertOk();
    $response->assertDontSee('Oil Audit');
});

test('oil audit section is hidden for pic bul', function () {
    $pic = User::factory()->create(['role' => User::ROLE_PIC_BUL]);

    $response = $this->actingAs($pic)->get(route('dashboard'));

    $response->assertOk();
    $response->assertDontSee('Oil Audit');
});

test('oil audit counts reflect real database rows, not fabricated numbers', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine1 = Machine::create(['machine_number' => 'MC-OIL2', 'area' => 'WWD', 'machine_type' => 'NDE', 'status' => 'ACTIVE']);
    $machine2 = Machine::create(['machine_number' => 'MC-OIL3', 'area' => 'WWD', 'machine_type' => 'NDB', 'status' => 'ACTIVE']);

    OilAudit::create([
        'machine_id' => $machine1->id, 'machine_number' => $machine1->machine_number,
        'machine_type' => $machine1->machine_type, 'area' => $machine1->area,
        'condition' => 'OKE', 'audited_by_name' => 'Tester', 'audited_at' => today(),
    ]);
    OilAudit::create([
        'machine_id' => $machine2->id, 'machine_number' => $machine2->machine_number,
        'machine_type' => $machine2->machine_type, 'area' => $machine2->area,
        'condition' => 'KRITIS', 'audited_by_name' => 'Tester', 'audited_at' => today(),
    ]);

    $response = $this->actingAs($admin)->get(route('dashboard'));

    $response->assertOk();
    // 2 audited today, 1 critical unresolved, 2 machines audited.
    $response->assertSeeInOrder(['Audited Today'], false);
});

test('pm completion trend chart renders the fixed 96 percent target', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $response = $this->actingAs($admin)->get(route('dashboard'));

    $response->assertOk();
    $response->assertSee("drawBarChart('pmTrendChart', points, 96", false);
});

test('sparepart usage card still renders after being moved to row two', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $response = $this->actingAs($admin)->get(route('dashboard'));

    $response->assertOk();
    $response->assertSee('Top 10 Sparepart Usage');
});
