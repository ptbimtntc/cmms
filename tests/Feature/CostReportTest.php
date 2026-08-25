<?php

use App\Models\Machine;
use App\Models\PMSchedule;
use App\Models\PMSparepart;
use App\Models\Sparepart;
use App\Models\User;
use Carbon\Carbon;

function reportCostMachine(array $overrides = []): Machine
{
    return Machine::create(array_merge([
        'machine_number' => 'MC-'.uniqid(),
        'area' => 'WWD',
        'machine_type' => 'NDE',
        'status' => 'ACTIVE',
    ], $overrides));
}

function reportCostPmSchedule(Machine $machine, array $overrides = []): PMSchedule
{
    $planDate = $overrides['plan_date'] ?? now()->toDateString();
    $actualDate = $overrides['actual_date'] ?? $planDate;

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
        'actual_date' => $actualDate,
        'pic' => null,
        'status' => 'FINISHED',
    ], $overrides));
}

function reportCostSparepart(array $overrides = []): Sparepart
{
    return Sparepart::create(array_merge([
        'material_number' => 'SP-'.uniqid(),
        'description' => 'Test Sparepart',
        'unit' => 'PCS',
        'price' => 100.00,
        'status' => 'ACTIVE',
    ], $overrides));
}

function reportCostUsage(PMSchedule $pmSchedule, Sparepart $sparepart, int $qty = 1): PMSparepart
{
    return PMSparepart::create([
        'pm_schedule_id' => $pmSchedule->id,
        'sparepart_id' => $sparepart->id,
        'qty' => $qty,
    ]);
}

test('report page requires authentication', function () {
    $this->get(route('reports.cost'))->assertRedirect(route('login'));
});

test('guest role cannot access the cost report', function () {
    $guest = User::factory()->create(['role' => User::ROLE_GUEST]);

    $this->actingAs($guest)->get(route('reports.cost'))->assertForbidden();
});

test('area all shows both wwd and bul cost for admin', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $wwdMachine = reportCostMachine(['area' => 'WWD']);
    $bulMachine = reportCostMachine(['area' => 'BUL', 'machine_type' => 'BF']);

    reportCostUsage(reportCostPmSchedule($wwdMachine, ['actual_date' => '2026-08-01']), reportCostSparepart(['price' => 50]), 2);
    reportCostUsage(reportCostPmSchedule($bulMachine, ['actual_date' => '2026-08-02']), reportCostSparepart(['price' => 30]), 1);

    $response = $this->actingAs($admin)->get(route('reports.cost', ['year' => 2026, 'month' => 8]));

    $response->assertOk();
    // (2*50) + (1*30) = 130
    expect($response->viewData('summary')['total_cost'])->toBe(130.0);
});

test('area wwd filter narrows to wwd cost only for admin', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $wwdMachine = reportCostMachine(['area' => 'WWD']);
    $bulMachine = reportCostMachine(['area' => 'BUL', 'machine_type' => 'BF']);

    reportCostUsage(reportCostPmSchedule($wwdMachine, ['actual_date' => '2026-08-01']), reportCostSparepart(['price' => 50]), 2);
    reportCostUsage(reportCostPmSchedule($bulMachine, ['actual_date' => '2026-08-02']), reportCostSparepart(['price' => 30]), 1);

    $response = $this->actingAs($admin)->get(route('reports.cost', ['year' => 2026, 'month' => 8, 'area' => 'WWD']));

    $response->assertOk();
    expect($response->viewData('summary')['total_cost'])->toBe(100.0);
});

test('area bul filter narrows to bul cost only for admin', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $wwdMachine = reportCostMachine(['area' => 'WWD']);
    $bulMachine = reportCostMachine(['area' => 'BUL', 'machine_type' => 'BF']);

    reportCostUsage(reportCostPmSchedule($wwdMachine, ['actual_date' => '2026-08-01']), reportCostSparepart(['price' => 50]), 2);
    reportCostUsage(reportCostPmSchedule($bulMachine, ['actual_date' => '2026-08-02']), reportCostSparepart(['price' => 30]), 1);

    $response = $this->actingAs($admin)->get(route('reports.cost', ['year' => 2026, 'month' => 8, 'area' => 'BUL']));

    $response->assertOk();
    expect($response->viewData('summary')['total_cost'])->toBe(30.0);
});

test('koordinator bul area is fixed regardless of the area filter param', function () {
    $koordinator = User::factory()->create(['role' => User::ROLE_KOORDINATOR_BUL]);
    $wwdMachine = reportCostMachine(['area' => 'WWD']);
    $bulMachine = reportCostMachine(['area' => 'BUL', 'machine_type' => 'BF']);

    reportCostUsage(reportCostPmSchedule($wwdMachine, ['actual_date' => '2026-08-01']), reportCostSparepart(['price' => 50]), 2);
    reportCostUsage(reportCostPmSchedule($bulMachine, ['actual_date' => '2026-08-02']), reportCostSparepart(['price' => 30]), 1);

    $response = $this->actingAs($koordinator)->get(route('reports.cost', ['year' => 2026, 'month' => 8, 'area' => 'WWD']));

    $response->assertOk();
    expect($response->viewData('summary')['total_cost'])->toBe(30.0);
});

test('year filter isolates cost from other years', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = reportCostMachine();

    reportCostUsage(reportCostPmSchedule($machine, ['actual_date' => '2026-01-15']), reportCostSparepart(['price' => 40]), 1);
    reportCostUsage(reportCostPmSchedule($machine, ['actual_date' => '2025-01-15']), reportCostSparepart(['price' => 999]), 1);

    $response = $this->actingAs($admin)->get(route('reports.cost', ['year' => 2026]));

    $response->assertOk();
    expect($response->viewData('summary')['total_cost'])->toBe(40.0);
});

test('month filter isolates cost from other months', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = reportCostMachine();

    reportCostUsage(reportCostPmSchedule($machine, ['actual_date' => '2026-08-05']), reportCostSparepart(['price' => 40]), 1);
    reportCostUsage(reportCostPmSchedule($machine, ['actual_date' => '2026-09-05']), reportCostSparepart(['price' => 999]), 1);

    $response = $this->actingAs($admin)->get(route('reports.cost', ['year' => 2026, 'month' => 8]));

    $response->assertOk();
    expect($response->viewData('summary')['total_cost'])->toBe(40.0);
});

test('machine filter narrows results', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machineA = reportCostMachine(['machine_number' => 'MC-A']);
    $machineB = reportCostMachine(['machine_number' => 'MC-B']);

    reportCostUsage(reportCostPmSchedule($machineA, ['actual_date' => '2026-08-01']), reportCostSparepart(['price' => 40]), 1);
    reportCostUsage(reportCostPmSchedule($machineB, ['actual_date' => '2026-08-02']), reportCostSparepart(['price' => 999]), 1);

    $response = $this->actingAs($admin)->get(route('reports.cost', ['year' => 2026, 'month' => 8, 'machine' => 'MC-A']));

    $response->assertOk();
    expect($response->viewData('summary')['total_cost'])->toBe(40.0);
});

test('machine type filter narrows results', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $nde = reportCostMachine(['machine_type' => 'NDE']);
    $shx = reportCostMachine(['machine_type' => 'SHX']);

    reportCostUsage(reportCostPmSchedule($nde, ['actual_date' => '2026-08-01']), reportCostSparepart(['price' => 40]), 1);
    reportCostUsage(reportCostPmSchedule($shx, ['actual_date' => '2026-08-02']), reportCostSparepart(['price' => 999]), 1);

    $response = $this->actingAs($admin)->get(route('reports.cost', ['year' => 2026, 'month' => 8, 'machine_type' => 'NDE']));

    $response->assertOk();
    expect($response->viewData('summary')['total_cost'])->toBe(40.0);
});

test('monthly cost trend has 12 months and correctly sums cost per month via sql aggregation', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = reportCostMachine();

    reportCostUsage(reportCostPmSchedule($machine, ['actual_date' => '2026-03-01']), reportCostSparepart(['price' => 50]), 2);
    reportCostUsage(reportCostPmSchedule($machine, ['actual_date' => '2026-03-15']), reportCostSparepart(['price' => 10]), 1);
    reportCostUsage(reportCostPmSchedule($machine, ['actual_date' => '2026-07-01']), reportCostSparepart(['price' => 200]), 1);

    $response = $this->actingAs($admin)->get(route('reports.cost', ['year' => 2026]));

    $response->assertOk();
    $trend = collect($response->viewData('monthlyTrend'));

    expect($trend)->toHaveCount(12);
    expect($trend->firstWhere('month', 3)['cost'])->toBe(110.0)
        ->and($trend->firstWhere('month', 7)['cost'])->toBe(200.0)
        ->and($trend->firstWhere('month', 1)['cost'])->toBe(0.0);
});

test('monthly trend chart is affected by the area filter', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $wwdMachine = reportCostMachine(['area' => 'WWD']);
    $bulMachine = reportCostMachine(['area' => 'BUL', 'machine_type' => 'BF']);

    reportCostUsage(reportCostPmSchedule($wwdMachine, ['actual_date' => '2026-05-01']), reportCostSparepart(['price' => 70]), 1);
    reportCostUsage(reportCostPmSchedule($bulMachine, ['actual_date' => '2026-05-02']), reportCostSparepart(['price' => 30]), 1);

    $all = $this->actingAs($admin)->get(route('reports.cost', ['year' => 2026]));
    $wwdOnly = $this->actingAs($admin)->get(route('reports.cost', ['year' => 2026, 'area' => 'WWD']));

    $allMay = collect($all->viewData('monthlyTrend'))->firstWhere('month', 5)['cost'];
    $wwdMay = collect($wwdOnly->viewData('monthlyTrend'))->firstWhere('month', 5)['cost'];

    expect($allMay)->toBe(100.0)
        ->and($wwdMay)->toBe(70.0);
});

test('cost per area breaks down cost by area within the filtered scope', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $wwdMachine = reportCostMachine(['area' => 'WWD']);
    $bulMachine = reportCostMachine(['area' => 'BUL', 'machine_type' => 'BF']);

    reportCostUsage(reportCostPmSchedule($wwdMachine, ['actual_date' => '2026-08-01']), reportCostSparepart(['price' => 70]), 1);
    reportCostUsage(reportCostPmSchedule($bulMachine, ['actual_date' => '2026-08-02']), reportCostSparepart(['price' => 30]), 1);

    $response = $this->actingAs($admin)->get(route('reports.cost', ['year' => 2026, 'month' => 8]));

    $response->assertOk();
    $costByArea = $response->viewData('summary')['cost_by_area'];

    expect($costByArea['WWD'])->toBe(70.0)
        ->and($costByArea['BUL'])->toBe(30.0);
});

test('top machine reflects the highest cost machine in the filtered scope', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $cheapMachine = reportCostMachine(['machine_number' => 'MC-CHEAP']);
    $expensiveMachine = reportCostMachine(['machine_number' => 'MC-EXPENSIVE']);

    reportCostUsage(reportCostPmSchedule($cheapMachine, ['actual_date' => '2026-08-01']), reportCostSparepart(['price' => 10]), 1);
    reportCostUsage(reportCostPmSchedule($expensiveMachine, ['actual_date' => '2026-08-02']), reportCostSparepart(['price' => 500]), 1);

    $response = $this->actingAs($admin)->get(route('reports.cost', ['year' => 2026, 'month' => 8]));

    $response->assertOk();
    expect($response->viewData('summary')['top_machine'])->toBe(['label' => 'MC-EXPENSIVE', 'cost' => 500.0]);
});

test('detail table shows the correct total cost per row', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = reportCostMachine();

    reportCostUsage(reportCostPmSchedule($machine, ['actual_date' => '2026-08-01', 'order_number' => 'ORD-XYZ']), reportCostSparepart(['price' => 25]), 4);

    $response = $this->actingAs($admin)->get(route('reports.cost', ['year' => 2026, 'month' => 8]));

    $response->assertOk();
    $row = $response->viewData('usages')->first();

    expect((float) $row->qty * (float) $row->price)->toBe(100.0);
    $response->assertSee('ORD-XYZ');
});

test('pagination preserves active filters across pages', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = reportCostMachine();

    for ($i = 0; $i < 25; $i++) {
        reportCostUsage(reportCostPmSchedule($machine, ['actual_date' => now()->subDays($i)->toDateString()]), reportCostSparepart());
    }

    $response = $this->actingAs($admin)->get(route('reports.cost', ['machine' => $machine->machine_number, 'page' => 2]));

    $response->assertOk();
    $response->assertSee('machine='.$machine->machine_number, false);
    expect($response->viewData('usages')->currentPage())->toBe(2);
});

test('empty state is shown when no cost data matches', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $response = $this->actingAs($admin)->get(route('reports.cost', ['year' => 2019]));

    $response->assertOk();
    $response->assertSee('No Maintenance Cost data found.');
    expect($response->viewData('summary')['total_cost'])->toBe(0.0);
});
