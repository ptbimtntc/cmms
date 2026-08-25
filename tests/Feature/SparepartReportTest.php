<?php

use App\Models\Machine;
use App\Models\PMSchedule;
use App\Models\PMSparepart;
use App\Models\Sparepart;
use App\Models\User;
use Carbon\Carbon;

function reportUsageMachine(array $overrides = []): Machine
{
    return Machine::create(array_merge([
        'machine_number' => 'MC-'.uniqid(),
        'area' => 'WWD',
        'machine_type' => 'NDE',
        'status' => 'ACTIVE',
    ], $overrides));
}

function reportUsagePmSchedule(Machine $machine, array $overrides = []): PMSchedule
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

function reportUsageSparepart(array $overrides = []): Sparepart
{
    return Sparepart::create(array_merge([
        'material_number' => 'SP-'.uniqid(),
        'description' => 'Test Sparepart',
        'unit' => 'PCS',
        'price' => 100.00,
        'status' => 'ACTIVE',
    ], $overrides));
}

function reportUsage(PMSchedule $pmSchedule, Sparepart $sparepart, int $qty = 1): PMSparepart
{
    return PMSparepart::create([
        'pm_schedule_id' => $pmSchedule->id,
        'sparepart_id' => $sparepart->id,
        'qty' => $qty,
    ]);
}

test('report page requires authentication', function () {
    $this->get(route('reports.sparepart'))->assertRedirect(route('login'));
});

test('guest role cannot access the sparepart usage report', function () {
    $guest = User::factory()->create(['role' => User::ROLE_GUEST]);

    $this->actingAs($guest)->get(route('reports.sparepart'))->assertForbidden();
});

test('area all shows both wwd and bul usage for admin', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $wwdMachine = reportUsageMachine(['area' => 'WWD']);
    $bulMachine = reportUsageMachine(['area' => 'BUL']);

    reportUsage(reportUsagePmSchedule($wwdMachine, ['actual_date' => '2026-08-01']), reportUsageSparepart());
    reportUsage(reportUsagePmSchedule($bulMachine, ['actual_date' => '2026-08-02']), reportUsageSparepart());

    $response = $this->actingAs($admin)->get(route('reports.sparepart', ['year' => 2026, 'month' => 8]));

    $response->assertOk();
    expect($response->viewData('summary')['total_transactions'])->toBe(2);
});

test('area wwd filter narrows to wwd usage only for admin', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $wwdMachine = reportUsageMachine(['area' => 'WWD']);
    $bulMachine = reportUsageMachine(['area' => 'BUL']);

    reportUsage(reportUsagePmSchedule($wwdMachine, ['actual_date' => '2026-08-01']), reportUsageSparepart());
    reportUsage(reportUsagePmSchedule($bulMachine, ['actual_date' => '2026-08-02']), reportUsageSparepart());

    $response = $this->actingAs($admin)->get(route('reports.sparepart', [
        'year' => 2026, 'month' => 8, 'area' => 'WWD',
    ]));

    $response->assertOk();
    expect($response->viewData('summary')['total_transactions'])->toBe(1);
});

test('koordinator bul area is fixed regardless of the area filter param', function () {
    $koordinator = User::factory()->create(['role' => User::ROLE_KOORDINATOR_BUL]);
    $wwdMachine = reportUsageMachine(['area' => 'WWD']);
    $bulMachine = reportUsageMachine(['area' => 'BUL']);

    reportUsage(reportUsagePmSchedule($wwdMachine, ['actual_date' => '2026-08-01']), reportUsageSparepart());
    reportUsage(reportUsagePmSchedule($bulMachine, ['actual_date' => '2026-08-02']), reportUsageSparepart());

    $response = $this->actingAs($koordinator)->get(route('reports.sparepart', [
        'year' => 2026, 'month' => 8, 'area' => 'WWD',
    ]));

    $response->assertOk();
    expect($response->viewData('summary')['total_transactions'])->toBe(1);
});

test('pic only sees usage from their own pm schedules', function () {
    $pic = User::factory()->create(['role' => User::ROLE_PIC_WWD, 'name' => 'Andi']);
    $machine = reportUsageMachine();

    reportUsage(reportUsagePmSchedule($machine, ['pic' => 'Andi', 'actual_date' => '2026-08-01']), reportUsageSparepart());
    reportUsage(reportUsagePmSchedule($machine, ['pic' => 'Budi', 'actual_date' => '2026-08-02']), reportUsageSparepart());

    $response = $this->actingAs($pic)->get(route('reports.sparepart', ['year' => 2026, 'month' => 8]));

    $response->assertOk();
    expect($response->viewData('summary')['total_transactions'])->toBe(1);
});

test('year filter isolates usage from other years', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = reportUsageMachine();

    reportUsage(reportUsagePmSchedule($machine, ['actual_date' => '2026-01-15']), reportUsageSparepart());
    reportUsage(reportUsagePmSchedule($machine, ['actual_date' => '2025-01-15']), reportUsageSparepart());

    $response = $this->actingAs($admin)->get(route('reports.sparepart', ['year' => 2026]));

    $response->assertOk();
    expect($response->viewData('summary')['total_transactions'])->toBe(1);
});

test('month filter isolates usage from other months', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = reportUsageMachine();

    reportUsage(reportUsagePmSchedule($machine, ['actual_date' => '2026-08-05']), reportUsageSparepart());
    reportUsage(reportUsagePmSchedule($machine, ['actual_date' => '2026-09-05']), reportUsageSparepart());

    $response = $this->actingAs($admin)->get(route('reports.sparepart', ['year' => 2026, 'month' => 8]));

    $response->assertOk();
    expect($response->viewData('summary')['total_transactions'])->toBe(1);
});

test('machine filter narrows to the selected machine', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machineA = reportUsageMachine(['machine_number' => 'MC-A']);
    $machineB = reportUsageMachine(['machine_number' => 'MC-B']);

    reportUsage(reportUsagePmSchedule($machineA, ['actual_date' => '2026-08-01']), reportUsageSparepart());
    reportUsage(reportUsagePmSchedule($machineB, ['actual_date' => '2026-08-02']), reportUsageSparepart());

    $response = $this->actingAs($admin)->get(route('reports.sparepart', [
        'year' => 2026, 'month' => 8, 'machine' => 'MC-A',
    ]));

    $response->assertOk();
    expect($response->viewData('usages')->pluck('machine_number')->all())->toBe(['MC-A']);
});

test('search matches material number', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = reportUsageMachine();

    reportUsage(reportUsagePmSchedule($machine), reportUsageSparepart(['material_number' => 'UNIQUE-MAT-001']));
    reportUsage(reportUsagePmSchedule($machine), reportUsageSparepart(['material_number' => 'OTHER-MAT-002']));

    $response = $this->actingAs($admin)->get(route('reports.sparepart', ['search' => 'UNIQUE-MAT-001']));

    $response->assertOk();
    expect($response->viewData('usages')->total())->toBe(1);
});

test('search matches description', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = reportUsageMachine();

    reportUsage(reportUsagePmSchedule($machine), reportUsageSparepart(['description' => 'Very Unique Bearing Description']));
    reportUsage(reportUsagePmSchedule($machine), reportUsageSparepart(['description' => 'Some Other Part']));

    $response = $this->actingAs($admin)->get(route('reports.sparepart', ['search' => 'Very Unique Bearing']));

    $response->assertOk();
    expect($response->viewData('usages')->total())->toBe(1);
});

test('search matches order number', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = reportUsageMachine();

    reportUsage(reportUsagePmSchedule($machine, ['order_number' => '12345678']), reportUsageSparepart());
    reportUsage(reportUsagePmSchedule($machine, ['order_number' => '99999999']), reportUsageSparepart());

    $response = $this->actingAs($admin)->get(route('reports.sparepart', ['search' => '12345678']));

    $response->assertOk();
    expect($response->viewData('usages')->total())->toBe(1);
});

test('total cost matches the qty times price formula', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = reportUsageMachine();

    reportUsage(reportUsagePmSchedule($machine, ['actual_date' => '2026-08-01']), reportUsageSparepart(['price' => 50.00]), 3);
    reportUsage(reportUsagePmSchedule($machine, ['actual_date' => '2026-08-02']), reportUsageSparepart(['price' => 20.00]), 2);

    $response = $this->actingAs($admin)->get(route('reports.sparepart', ['year' => 2026, 'month' => 8]));

    $response->assertOk();
    // (3 * 50) + (2 * 20) = 190
    expect($response->viewData('summary')['total_cost'])->toBe(190.0)
        ->and($response->viewData('summary')['total_quantity'])->toBe(5)
        ->and($response->viewData('summary')['unique_materials'])->toBe(2);
});

test('top 10 most used ranks by quantity', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = reportUsageMachine();

    $high = reportUsageSparepart(['material_number' => 'HIGH-QTY', 'description' => 'High Qty Part']);
    $low = reportUsageSparepart(['material_number' => 'LOW-QTY', 'description' => 'Low Qty Part']);

    reportUsage(reportUsagePmSchedule($machine, ['actual_date' => '2026-08-01']), $high, 50);
    reportUsage(reportUsagePmSchedule($machine, ['actual_date' => '2026-08-02']), $low, 2);

    $response = $this->actingAs($admin)->get(route('reports.sparepart', ['year' => 2026, 'month' => 8]));

    $response->assertOk();
    $topUsage = $response->viewData('topUsage');

    expect($topUsage->first()->material_number)->toBe('HIGH-QTY')
        ->and((int) $topUsage->first()->total_qty)->toBe(50);
});

test('top 10 highest cost ranks by total cost not quantity', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = reportUsageMachine();

    // High quantity but cheap.
    $cheap = reportUsageSparepart(['material_number' => 'CHEAP-BULK', 'price' => 1.00]);
    // Low quantity but expensive — should rank #1 by cost despite lower qty.
    $expensive = reportUsageSparepart(['material_number' => 'EXPENSIVE-ITEM', 'price' => 1000.00]);

    reportUsage(reportUsagePmSchedule($machine, ['actual_date' => '2026-08-01']), $cheap, 100);
    reportUsage(reportUsagePmSchedule($machine, ['actual_date' => '2026-08-02']), $expensive, 1);

    $response = $this->actingAs($admin)->get(route('reports.sparepart', ['year' => 2026, 'month' => 8]));

    $response->assertOk();
    $topCost = $response->viewData('topCost');

    expect($topCost->first()->material_number)->toBe('EXPENSIVE-ITEM')
        ->and((float) $topCost->first()->total_cost)->toBe(1000.0);
});

test('pagination preserves active filters across pages', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = reportUsageMachine();

    for ($i = 0; $i < 25; $i++) {
        reportUsage(reportUsagePmSchedule($machine, ['actual_date' => now()->subDays($i)->toDateString(), 'order_number' => 'ORD-'.$i]), reportUsageSparepart());
    }

    $response = $this->actingAs($admin)->get(route('reports.sparepart', ['machine' => $machine->machine_number, 'page' => 2]));

    $response->assertOk();
    $response->assertSee('machine='.$machine->machine_number, false);
    expect($response->viewData('usages')->currentPage())->toBe(2);
});

test('empty state is shown when no usage data matches', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $response = $this->actingAs($admin)->get(route('reports.sparepart', ['search' => 'no-such-material']));

    $response->assertOk();
    $response->assertSee('No Sparepart Usage data found.');
    expect($response->viewData('summary')['total_transactions'])->toBe(0);
});
