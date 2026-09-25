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

// ---------------------------------------------------------------
// Jan-Sep completion/closing trend charts
// ---------------------------------------------------------------

test('trend covers exactly january through september, in order', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $response = $this->actingAs($admin)->get(route('reports.pm', ['year' => 2026]));

    $trend = $response->viewData('trend');
    expect($trend)->toHaveCount(9);
    expect(array_column($trend, 'label'))->toBe(['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep']);
    expect(array_column($trend, 'month'))->toBe([1, 2, 3, 4, 5, 6, 7, 8, 9]);
});

test('trend follows the selected year filter', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = makeReportMachine();

    makeReportPm($machine, ['status' => 'FINISHED_ON_TIME', 'plan_date' => '2025-03-05']);
    makeReportPm($machine, ['status' => 'OPEN', 'plan_date' => '2026-03-05']);

    $response2025 = $this->actingAs($admin)->get(route('reports.pm', ['year' => 2025]));
    $response2026 = $this->actingAs($admin)->get(route('reports.pm', ['year' => 2026]));

    expect($response2025->viewData('trendYear'))->toBe(2025);
    $march2025 = collect($response2025->viewData('trend'))->firstWhere('month', 3);
    expect($march2025['closing_percent'])->toBe(100.0);

    expect($response2026->viewData('trendYear'))->toBe(2026);
    $march2026 = collect($response2026->viewData('trend'))->firstWhere('month', 3);
    expect($march2026['closing_percent'])->toBe(0.0);
});

test('trend defaults to the current year when "all years" is selected', function () {
    Carbon::setTestNow(Carbon::parse('2026-09-25'));
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $response = $this->actingAs($admin)->get(route('reports.pm'));

    expect($response->viewData('trendYear'))->toBe(2026);

    Carbon::setTestNow();
});

test('trend percentages match the same closing/completion formula as the summary cards', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = makeReportMachine();

    makeReportPm($machine, ['status' => 'FINISHED_ON_TIME', 'plan_date' => '2026-05-10']);
    makeReportPm($machine, ['status' => 'FINISHED', 'plan_date' => '2026-05-11']);
    makeReportPm($machine, ['status' => 'OPEN', 'plan_date' => '2026-05-12']);
    makeReportPm($machine, ['status' => 'MISSED', 'plan_date' => '2026-05-13']);

    $response = $this->actingAs($admin)->get(route('reports.pm', ['year' => 2026]));

    $may = collect($response->viewData('trend'))->firstWhere('month', 5);
    expect($may['closing_percent'])->toBe(50.0);
    expect($may['completion_percent'])->toBe(37.5);
    expect($may['has_data'])->toBeTrue();
});

test('a month with no pm schedules has no data rather than a misleading 0%', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = makeReportMachine();
    makeReportPm($machine, ['status' => 'FINISHED', 'plan_date' => '2026-05-10']);

    $response = $this->actingAs($admin)->get(route('reports.pm', ['year' => 2026]));

    $january = collect($response->viewData('trend'))->firstWhere('month', 1);
    expect($january['has_data'])->toBeFalse();
});

test('trend respects the area filter', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $wwd = makeReportMachine(['area' => 'WWD']);
    $bul = makeReportMachine(['area' => 'BUL']);

    makeReportPm($wwd, ['status' => 'FINISHED', 'plan_date' => '2026-04-01']);
    makeReportPm($bul, ['status' => 'OPEN', 'plan_date' => '2026-04-02']);

    $response = $this->actingAs($admin)->get(route('reports.pm', ['year' => 2026, 'area' => 'WWD']));

    $april = collect($response->viewData('trend'))->firstWhere('month', 4);
    expect($april['closing_percent'])->toBe(100.0);
});

test('trend respects the status filter', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = makeReportMachine();

    makeReportPm($machine, ['status' => 'FINISHED', 'plan_date' => '2026-04-01']);
    makeReportPm($machine, ['status' => 'OPEN', 'plan_date' => '2026-04-02']);

    $response = $this->actingAs($admin)->get(route('reports.pm', ['year' => 2026, 'status' => ['FINISHED']]));

    $april = collect($response->viewData('trend'))->firstWhere('month', 4);
    expect($april['closing_percent'])->toBe(100.0);
});

test('trend is not narrowed by the month filter — it always shows all of jan-sep', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = makeReportMachine();

    makeReportPm($machine, ['status' => 'FINISHED', 'plan_date' => '2026-02-01']);
    makeReportPm($machine, ['status' => 'FINISHED', 'plan_date' => '2026-07-01']);

    // Filtering the table/summary to July only must not shrink the trend chart.
    $response = $this->actingAs($admin)->get(route('reports.pm', ['year' => 2026, 'month' => [7]]));

    $trend = collect($response->viewData('trend'));
    expect($trend)->toHaveCount(9);
    expect($trend->firstWhere('month', 2)['has_data'])->toBeTrue();
    expect($trend->firstWhere('month', 7)['has_data'])->toBeTrue();
});

test('the trend charts render on the page', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $response = $this->actingAs($admin)->get(route('reports.pm', ['year' => 2026]));

    $response->assertOk();
    $response->assertSee('id="pmCompletionTrendChart"', false);
    $response->assertSee('id="pmClosingTrendChart"', false);
    $response->assertSee('Completion Trend', false);
    $response->assertSee('Closing Trend', false);
});

test('both trend charts draw a fixed 96% orange target line', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $response = $this->actingAs($admin)->get(route('reports.pm', ['year' => 2026]));
    $html = $response->getContent();

    $response->assertOk();
    $response->assertSee('Target 96%', false);
    $response->assertSee("getPixelForValue(96)", false);
    $response->assertSee('#f97316', false);

    // Both charts must register the plugin, not just one.
    expect(substr_count($html, 'plugins: [targetLinePlugin]'))->toBe(2);
});
