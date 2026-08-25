<?php

use App\Models\Machine;
use App\Models\PMSchedule;
use App\Models\User;
use Carbon\Carbon;

function reportMachine(array $overrides = []): Machine
{
    return Machine::create(array_merge([
        'machine_number' => 'MC-'.uniqid(),
        'area' => 'WWD',
        'machine_type' => 'NDE',
        'status' => 'ACTIVE',
        'pm_cycle_value' => 1,
        'pm_cycle_unit' => 'MONTH',
    ], $overrides));
}

function reportMachinePm(Machine $machine, array $overrides = []): PMSchedule
{
    $planDate = $overrides['plan_date'] ?? now()->toDateString();

    return PMSchedule::create(array_merge([
        'machine_id' => $machine->id,
        'machine_number' => $machine->machine_number,
        'machine_type' => $machine->machine_type,
        'area' => $machine->area,
        'order_number' => 'ORD-'.uniqid(),
        'plan_date' => $planDate,
        'plan_month' => Carbon::parse($planDate)->format('F'),
        'plan_year' => Carbon::parse($planDate)->format('Y'),
        'due_date' => Carbon::parse($planDate)->addDays(14),
        'pic' => null,
        'status' => 'OPEN',
    ], $overrides));
}

test('report page requires authentication', function () {
    $this->get(route('reports.machine'))->assertRedirect(route('login'));
});

test('guest role cannot access the machine report', function () {
    $guest = User::factory()->create(['role' => User::ROLE_GUEST]);

    $this->actingAs($guest)->get(route('reports.machine'))->assertForbidden();
});

test('area all shows both wwd and bul machines for admin', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    reportMachine(['machine_number' => 'MC-WWD', 'area' => 'WWD']);
    reportMachine(['machine_number' => 'MC-BUL', 'area' => 'BUL', 'machine_type' => 'BF']);

    $response = $this->actingAs($admin)->get(route('reports.machine'));

    $response->assertOk();
    expect($response->viewData('summary')['total_machine'])->toBe(2);
});

test('area wwd filter narrows to wwd machines only for admin', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    reportMachine(['machine_number' => 'MC-WWD', 'area' => 'WWD']);
    reportMachine(['machine_number' => 'MC-BUL', 'area' => 'BUL', 'machine_type' => 'BF']);

    $response = $this->actingAs($admin)->get(route('reports.machine', ['area' => 'WWD']));

    $response->assertOk();
    expect($response->viewData('summary')['total_machine'])->toBe(1);
});

test('area bul filter narrows to bul machines only for admin', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    reportMachine(['machine_number' => 'MC-WWD', 'area' => 'WWD']);
    reportMachine(['machine_number' => 'MC-BUL', 'area' => 'BUL', 'machine_type' => 'BF']);

    $response = $this->actingAs($admin)->get(route('reports.machine', ['area' => 'BUL']));

    $response->assertOk();
    expect($response->viewData('summary')['total_machine'])->toBe(1);
});

test('koordinator wwd is fixed to wwd regardless of the area filter param', function () {
    $koordinator = User::factory()->create(['role' => User::ROLE_KOORDINATOR_WWD]);
    reportMachine(['machine_number' => 'MC-WWD', 'area' => 'WWD']);
    reportMachine(['machine_number' => 'MC-BUL', 'area' => 'BUL', 'machine_type' => 'BF']);

    $response = $this->actingAs($koordinator)->get(route('reports.machine', ['area' => 'BUL']));

    $response->assertOk();
    expect($response->viewData('summary')['total_machine'])->toBe(1);
});

test('pic bul is fixed to bul regardless of the area filter param', function () {
    $pic = User::factory()->create(['role' => User::ROLE_PIC_BUL]);
    reportMachine(['machine_number' => 'MC-WWD', 'area' => 'WWD']);
    reportMachine(['machine_number' => 'MC-BUL', 'area' => 'BUL', 'machine_type' => 'BF']);

    $response = $this->actingAs($pic)->get(route('reports.machine', ['area' => 'WWD']));

    $response->assertOk();
    expect($response->viewData('summary')['total_machine'])->toBe(1);
});

test('search matches machine number', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    reportMachine(['machine_number' => 'UNIQUE-MC-001']);
    reportMachine(['machine_number' => 'OTHER-MC-002']);

    $response = $this->actingAs($admin)->get(route('reports.machine', ['search' => 'UNIQUE-MC-001']));

    $response->assertOk();
    expect($response->viewData('machines')->total())->toBe(1);
});

test('search matches machine type', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    reportMachine(['machine_type' => 'SHX']);
    reportMachine(['machine_type' => 'NDE']);

    $response = $this->actingAs($admin)->get(route('reports.machine', ['search' => 'SHX']));

    $response->assertOk();
    expect($response->viewData('machines')->total())->toBe(1);
});

test('machine type filter narrows results', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    reportMachine(['machine_type' => 'NDE']);
    reportMachine(['machine_type' => 'SHX']);

    $response = $this->actingAs($admin)->get(route('reports.machine', ['machine_type' => 'SHX']));

    $response->assertOk();
    expect($response->viewData('machines')->total())->toBe(1);
});

test('status filter narrows results', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    reportMachine(['status' => 'ACTIVE']);
    reportMachine(['status' => 'INACTIVE']);

    $response = $this->actingAs($admin)->get(route('reports.machine', ['status' => 'INACTIVE']));

    $response->assertOk();
    expect($response->viewData('machines')->total())->toBe(1)
        ->and($response->viewData('summary')['inactive_machine'])->toBe(1);
});

test('gearbox count only includes wwd nde/ndb machines, excluding shx', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    reportMachine(['machine_number' => 'MC-NDE', 'area' => 'WWD', 'machine_type' => 'NDE']);
    reportMachine(['machine_number' => 'MC-SHX', 'area' => 'WWD', 'machine_type' => 'SHX']);
    reportMachine(['machine_number' => 'MC-BF', 'area' => 'BUL', 'machine_type' => 'BF']);

    $response = $this->actingAs($admin)->get(route('reports.machine'));

    $response->assertOk();
    expect($response->viewData('gearboxCount'))->toBe(1)
        ->and($response->viewData('showGearboxMetric'))->toBeTrue();
});

test('gearbox metric is hidden when the effective area is bul', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    reportMachine(['area' => 'BUL', 'machine_type' => 'BF']);

    $response = $this->actingAs($admin)->get(route('reports.machine', ['area' => 'BUL']));

    $response->assertOk();
    expect($response->viewData('showGearboxMetric'))->toBeFalse();
    $response->assertDontSee('Gearbox Machines');
});

test('last pm reflects the most recent actual_date across pm schedules', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = reportMachine();

    reportMachinePm($machine, ['actual_date' => '2026-06-01']);
    reportMachinePm($machine, ['actual_date' => '2026-08-01']);
    reportMachinePm($machine, ['actual_date' => null]);

    $response = $this->actingAs($admin)->get(route('reports.machine'));

    $response->assertOk();
    $row = $response->viewData('machines')->firstWhere('machine_number', $machine->machine_number);

    expect(Carbon::parse($row->last_pm)->toDateString())->toBe('2026-08-01')
        ->and($row->pm_count)->toBe(3);
});

test('next pm is calculated from last pm plus the machine cycle', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = reportMachine(['pm_cycle_value' => 2, 'pm_cycle_unit' => 'MONTH']);

    reportMachinePm($machine, ['actual_date' => '2026-06-01']);

    $response = $this->actingAs($admin)->get(route('reports.machine'));

    $response->assertOk();
    $row = $response->viewData('machines')->firstWhere('machine_number', $machine->machine_number);

    expect($row->next_pm->toDateString())->toBe('2026-08-01');
});

test('next pm is null when there is no last pm yet', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = reportMachine();

    $response = $this->actingAs($admin)->get(route('reports.machine'));

    $response->assertOk();
    $row = $response->viewData('machines')->firstWhere('machine_number', $machine->machine_number);

    expect($row->next_pm)->toBeNull()
        ->and($row->pm_count)->toBeNull();
});

test('pagination preserves active filters across pages', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    for ($i = 0; $i < 25; $i++) {
        reportMachine(['machine_number' => 'MC-PAGE-'.$i, 'machine_type' => 'NDE']);
    }

    $response = $this->actingAs($admin)->get(route('reports.machine', ['machine_type' => 'NDE', 'page' => 2]));

    $response->assertOk();
    $response->assertSee('machine_type=NDE', false);
    expect($response->viewData('machines')->currentPage())->toBe(2);
});

test('empty state is shown when no machine data matches', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $response = $this->actingAs($admin)->get(route('reports.machine', ['search' => 'no-such-machine']));

    $response->assertOk();
    $response->assertSee('No Machine data found.');
});

test('view action links to the existing machine history page', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = reportMachine();

    $response = $this->actingAs($admin)->get(route('reports.machine'));

    $response->assertOk();
    $response->assertSee(route('machine-history.show', $machine->machine_number), false);
});
