<?php

use App\Models\Machine;
use App\Models\PMSchedule;
use App\Models\User;

function machineTypeFilterSchedule(string $machineType): PMSchedule
{
    $machine = Machine::create([
        'machine_number' => 'MC-'.uniqid(),
        'area' => 'WWD',
        'machine_type' => $machineType,
        'status' => 'ACTIVE',
    ]);

    return PMSchedule::create([
        'machine_id' => $machine->id,
        'machine_number' => $machine->machine_number,
        'machine_type' => $machineType,
        'area' => 'WWD',
        'order_number' => 'ORD-'.uniqid(),
        'plan_date' => now()->toDateString(),
        'plan_month' => now()->format('F'),
        'plan_year' => now()->format('Y'),
        'due_date' => now()->addDays(14),
        'status' => 'OPEN',
    ]);
}

test('machine type filter accepts more than one value at once', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $nde = machineTypeFilterSchedule('NDE');
    $de = machineTypeFilterSchedule('DE');
    $gearbox = machineTypeFilterSchedule('GEARBOX');

    $response = $this->actingAs($admin)->get(route('pm-schedules.index', [
        'machine_type' => ['NDE', 'DE'],
    ]));

    $response->assertOk();
    $numbers = $response->viewData('schedules')->pluck('machine_number')->sort()->values()->all();

    expect($numbers)->toContain($nde->machine_number);
    expect($numbers)->toContain($de->machine_number);
    expect($numbers)->not->toContain($gearbox->machine_number);
});

test('machine type filter still works with a single legacy value', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $nde = machineTypeFilterSchedule('NDE');
    $de = machineTypeFilterSchedule('DE');

    $response = $this->actingAs($admin)->get(route('pm-schedules.index', [
        'machine_type' => 'NDE',
    ]));

    $response->assertOk();
    $numbers = $response->viewData('schedules')->pluck('machine_number')->sort()->values()->all();

    expect($numbers)->toContain($nde->machine_number);
    expect($numbers)->not->toContain($de->machine_number);
});

test('machine type filter dropdown renders each machine type as a checkbox option', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    machineTypeFilterSchedule('NDE');
    machineTypeFilterSchedule('DE');

    $response = $this->actingAs($admin)->get(route('pm-schedules.index'));

    $response->assertOk();
    $response->assertSee('name="machine_type[]"', false);
    $response->assertSee('value="NDE"', false);
    $response->assertSee('value="DE"', false);
});
