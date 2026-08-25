<?php

use App\Models\Machine;
use App\Models\PMSchedule;
use App\Models\User;
use Carbon\Carbon;

function makeReportMachine(array $overrides = []): Machine
{
    return Machine::create(array_merge([
        'machine_number' => 'MC-'.uniqid(),
        'area' => 'WWD',
        'machine_type' => 'NDE',
        'status' => 'ACTIVE',
    ], $overrides));
}

function makeReportPm(Machine $machine, array $overrides = []): PMSchedule
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

test('report center no longer shows the old dummy content', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $response = $this->actingAs($admin)->get(route('reports.index'));

    $response->assertOk();
    $response->assertDontSee('Total Machines: 120');
    $response->assertDontSee('Completed PM: 85');
    $response->assertSee('PM Report');
    $response->assertSee(route('reports.pm'), false);
});

test('pm report renders with a correct summary for admin', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = makeReportMachine(['area' => 'WWD']);

    makeReportPm($machine, ['status' => 'FINISHED_ON_TIME', 'plan_date' => '2026-05-10']);
    makeReportPm($machine, ['status' => 'FINISHED', 'plan_date' => '2026-05-11']);
    makeReportPm($machine, ['status' => 'OPEN', 'plan_date' => '2026-05-12']);
    makeReportPm($machine, ['status' => 'MISSED', 'plan_date' => '2026-05-13']);

    $response = $this->actingAs($admin)->get(route('reports.pm', ['year' => 2026, 'month' => 5]));

    $response->assertOk();
    // total=4, closing=(1 finished_on_time + 1 finished)/4=50%
    // completion=(1*1 + 1*0.5)/4=37.5%
    expect($response->viewData('summary'))->toMatchArray([
        'total' => 4,
        'open' => 1,
        'finished' => 1,
        'finished_on_time' => 1,
        'missed' => 1,
        'closing_percent' => 50.0,
        'completion_percent' => 37.5,
    ]);
});

test('area filter is admin-only and defaults to all areas', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $wwd = makeReportMachine(['area' => 'WWD']);
    $bul = makeReportMachine(['area' => 'BUL']);

    makeReportPm($wwd, ['plan_date' => '2026-05-01']);
    makeReportPm($bul, ['plan_date' => '2026-05-02']);

    $all = $this->actingAs($admin)->get(route('reports.pm', ['year' => 2026, 'month' => 5]));
    $wwdOnly = $this->actingAs($admin)->get(route('reports.pm', ['year' => 2026, 'month' => 5, 'area' => 'WWD']));

    expect($all->viewData('summary')['total'])->toBe(2)
        ->and($wwdOnly->viewData('summary')['total'])->toBe(1);
});

test('koordinator area filter is ignored and fixed to their own area', function () {
    $koordinator = User::factory()->create(['role' => User::ROLE_KOORDINATOR_BUL]);
    $wwd = makeReportMachine(['area' => 'WWD']);
    $bul = makeReportMachine(['area' => 'BUL']);

    makeReportPm($wwd, ['plan_date' => '2026-05-01']);
    makeReportPm($bul, ['plan_date' => '2026-05-02']);

    // Koordinator BUL tries to force area=WWD via query string — must be ignored.
    $response = $this->actingAs($koordinator)->get(route('reports.pm', [
        'year' => 2026, 'month' => 5, 'area' => 'WWD',
    ]));

    $response->assertOk();
    expect($response->viewData('summary')['total'])->toBe(1);
    expect($response->viewData('schedules')->first()->area)->toBe('BUL');
});

test('pic only sees their own pm schedules regardless of filters', function () {
    $pic = User::factory()->create(['role' => User::ROLE_PIC_WWD, 'name' => 'Andi']);
    $machine = makeReportMachine(['area' => 'WWD']);

    makeReportPm($machine, ['pic' => 'Andi', 'plan_date' => '2026-05-01']);
    makeReportPm($machine, ['pic' => 'Budi', 'plan_date' => '2026-05-02']);

    $response = $this->actingAs($pic)->get(route('reports.pm', ['year' => 2026, 'month' => 5]));

    $response->assertOk();
    expect($response->viewData('summary')['total'])->toBe(1);
});

test('search matches machine number', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = makeReportMachine(['machine_number' => 'UNIQUE-MC-001']);
    $other = makeReportMachine(['machine_number' => 'OTHER-MC-002']);

    makeReportPm($machine);
    makeReportPm($other);

    $response = $this->actingAs($admin)->get(route('reports.pm', ['search' => 'UNIQUE-MC-001']));

    $response->assertOk();
    expect($response->viewData('schedules')->total())->toBe(1);
});

test('search matches order number', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = makeReportMachine();

    makeReportPm($machine, ['order_number' => '12345678']);
    makeReportPm($machine, ['order_number' => '99999999']);

    $response = $this->actingAs($admin)->get(route('reports.pm', ['search' => '12345678']));

    $response->assertOk();
    expect($response->viewData('schedules')->total())->toBe(1);
});

test('filter and search combine with AND logic', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $wwd = makeReportMachine(['area' => 'WWD']);
    $bul = makeReportMachine(['area' => 'BUL']);

    // Matches area + year + search.
    makeReportPm($wwd, ['order_number' => '12345678', 'plan_date' => '2026-05-01']);
    // Same search term but wrong area — must be excluded.
    makeReportPm($bul, ['order_number' => '12345678', 'plan_date' => '2026-05-02']);
    // Same area+year but wrong search term — must be excluded.
    makeReportPm($wwd, ['order_number' => '00000000', 'plan_date' => '2026-05-03']);

    $response = $this->actingAs($admin)->get(route('reports.pm', [
        'area' => 'WWD', 'year' => 2026, 'search' => '12345678',
    ]));

    $response->assertOk();
    expect($response->viewData('schedules')->total())->toBe(1);
});

test('machine type filter narrows results', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $nde = makeReportMachine(['machine_type' => 'NDE']);
    $bfm = makeReportMachine(['machine_type' => 'BFM']);

    makeReportPm($nde);
    makeReportPm($bfm);

    $response = $this->actingAs($admin)->get(route('reports.pm', ['machine_type' => 'BFM']));

    $response->assertOk();
    expect($response->viewData('schedules')->total())->toBe(1);
});

test('status filter narrows results', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = makeReportMachine();

    makeReportPm($machine, ['status' => 'MISSED']);
    makeReportPm($machine, ['status' => 'OPEN']);

    $response = $this->actingAs($admin)->get(route('reports.pm', ['status' => 'MISSED']));

    $response->assertOk();
    expect($response->viewData('schedules')->total())->toBe(1);
});

test('pagination preserves active filters across pages', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = makeReportMachine(['area' => 'WWD']);

    for ($i = 0; $i < 25; $i++) {
        makeReportPm($machine, ['plan_date' => now()->subDays($i)->toDateString()]);
    }

    $response = $this->actingAs($admin)->get(route('reports.pm', ['area' => 'WWD', 'page' => 2]));

    $response->assertOk();
    $response->assertSee('area=WWD', false);
    expect($response->viewData('schedules')->currentPage())->toBe(2);
});

test('empty state is shown when no pm data matches', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $response = $this->actingAs($admin)->get(route('reports.pm', ['search' => 'no-such-machine']));

    $response->assertOk();
    $response->assertSee('No PM data found.');
});

test('view action links to the existing pm detail page', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = makeReportMachine();
    $pm = makeReportPm($machine);

    $response = $this->actingAs($admin)->get(route('reports.pm'));

    $response->assertOk();
    $response->assertSee(route('machine-history.detail', [
        'machineNumber' => $pm->machine_number,
        'pmSchedule' => $pm->id,
    ]), false);
});

test('guest role cannot access the pm report', function () {
    $guest = User::factory()->create(['role' => User::ROLE_GUEST]);

    $response = $this->actingAs($guest)->get(route('reports.pm'));

    $response->assertForbidden();
});

test('unauthenticated users are redirected to login', function () {
    $response = $this->get(route('reports.pm'));

    $response->assertRedirect(route('login'));
});
