<?php

use App\Models\Machine;
use App\Models\MachineProblem;
use App\Models\PMSchedule;
use App\Models\User;
use Carbon\Carbon;

function makeGearboxTestMachine(array $overrides = []): Machine
{
    return Machine::create(array_merge([
        'machine_number' => 'MC-'.uniqid(),
        'area' => 'WWD',
        'machine_type' => 'NDE',
        'status' => 'ACTIVE',
    ], $overrides));
}

function makeGearboxTestPmSchedule(Machine $machine, array $overrides = []): PMSchedule
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

function submitPmUpdate($admin, PMSchedule $pmSchedule, array $problems = [])
{
    return test()->actingAs($admin)->put(route('pm-schedules.update', $pmSchedule), [
        'order_number' => $pmSchedule->order_number,
        'pic' => 'Tester',
        'actual_date' => now()->toDateString(),
        'start_time' => '08:00',
        'end_time' => '10:00',
        'problems' => $problems,
    ]);
}

test('wwd pm schedule flagged yes when a problem mentions mainshaft', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = makeGearboxTestMachine(['area' => 'WWD']);
    $pmSchedule = makeGearboxTestPmSchedule($machine, ['area' => 'WWD']);

    $mainshaftProblem = MachineProblem::create([
        'machine_type' => $machine->machine_type,
        'category' => 'Mainshaft WWD',
        'problem' => 'Mainshaft',
    ]);

    submitPmUpdate($admin, $pmSchedule, [
        ['problem' => $mainshaftProblem->id, 'finding' => null, 'severity' => 'Low'],
    ]);

    expect($pmSchedule->fresh()->gearbox_problem)->toBe('YES');
});

test('wwd pm schedule flagged yes when a problem mentions innershaft', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = makeGearboxTestMachine(['area' => 'WWD']);
    $pmSchedule = makeGearboxTestPmSchedule($machine, ['area' => 'WWD']);

    $innershaftProblem = MachineProblem::create([
        'machine_type' => $machine->machine_type,
        'category' => 'Innershaft',
        'problem' => 'Innershaft 1',
    ]);

    submitPmUpdate($admin, $pmSchedule, [
        ['problem' => $innershaftProblem->id, 'finding' => null, 'severity' => 'Medium'],
    ]);

    expect($pmSchedule->fresh()->gearbox_problem)->toBe('YES');
});

test('wwd pm schedule stays no when problems are unrelated to the gearbox', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = makeGearboxTestMachine(['area' => 'WWD']);
    $pmSchedule = makeGearboxTestPmSchedule($machine, ['area' => 'WWD']);

    $otherProblem = MachineProblem::create([
        'machine_type' => $machine->machine_type,
        'category' => 'Belt',
        'problem' => 'Belt Retak',
    ]);

    submitPmUpdate($admin, $pmSchedule, [
        ['problem' => $otherProblem->id, 'finding' => null, 'severity' => 'Low'],
    ]);

    expect($pmSchedule->fresh()->gearbox_problem)->toBe('NO');
});

test('wwd pm schedule stays no when no problems are recorded', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = makeGearboxTestMachine(['area' => 'WWD']);
    $pmSchedule = makeGearboxTestPmSchedule($machine, ['area' => 'WWD']);

    submitPmUpdate($admin, $pmSchedule, []);

    expect($pmSchedule->fresh()->gearbox_problem)->toBe('NO');
});

test('bul pm schedule stays no even with a mainshaft problem recorded', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = makeGearboxTestMachine(['area' => 'BUL', 'machine_type' => 'BF']);
    $pmSchedule = makeGearboxTestPmSchedule($machine, ['area' => 'BUL', 'machine_type' => 'BF']);

    $mainshaftProblem = MachineProblem::create([
        'machine_type' => 'BF',
        'category' => 'Mainshaft BUL',
        'problem' => 'Mainshaft',
    ]);

    submitPmUpdate($admin, $pmSchedule, [
        ['problem' => $mainshaftProblem->id, 'finding' => null, 'severity' => 'High'],
    ]);

    expect($pmSchedule->fresh()->gearbox_problem)->toBe('NO');
});

test('gearbox problem field is visible on the checklist page for wwd', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = makeGearboxTestMachine(['area' => 'WWD']);
    $pmSchedule = makeGearboxTestPmSchedule($machine, ['area' => 'WWD', 'gearbox_problem' => 'YES']);

    $response = $this->actingAs($admin)->get(route('pm-schedules.checklist', $pmSchedule));

    $response->assertOk();
    $response->assertSee('Gearbox Problem');
});

test('gearbox problem field is hidden on the checklist page for bul', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = makeGearboxTestMachine(['area' => 'BUL', 'machine_type' => 'BF']);
    $pmSchedule = makeGearboxTestPmSchedule($machine, ['area' => 'BUL', 'machine_type' => 'BF']);

    $response = $this->actingAs($admin)->get(route('pm-schedules.checklist', $pmSchedule));

    $response->assertOk();
    $response->assertDontSee('Gearbox Problem');
});

test('gearbox problem field is visible on the machine history detail page for wwd', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = makeGearboxTestMachine(['area' => 'WWD']);
    $pmSchedule = makeGearboxTestPmSchedule($machine, [
        'area' => 'WWD',
        'gearbox_problem' => 'YES',
        'actual_date' => now()->toDateString(),
    ]);

    $response = $this->actingAs($admin)->get(route('machine-history.detail', [
        'machineNumber' => $machine->machine_number,
        'pmSchedule' => $pmSchedule->id,
    ]));

    $response->assertOk();
    $response->assertSee('Gearbox Problem');
    $response->assertSee('YES');
});

test('gearbox problem field is hidden on the machine history detail page for bul', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = makeGearboxTestMachine(['area' => 'BUL', 'machine_type' => 'BF']);
    $pmSchedule = makeGearboxTestPmSchedule($machine, [
        'area' => 'BUL',
        'machine_type' => 'BF',
        'actual_date' => now()->toDateString(),
    ]);

    $response = $this->actingAs($admin)->get(route('machine-history.detail', [
        'machineNumber' => $machine->machine_number,
        'pmSchedule' => $pmSchedule->id,
    ]));

    $response->assertOk();
    $response->assertDontSee('Gearbox Problem');
});

test('pdf export still succeeds for wwd and bul pm schedules after adding gearbox field', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $wwdMachine = makeGearboxTestMachine(['area' => 'WWD']);
    $wwdSchedule = makeGearboxTestPmSchedule($wwdMachine, ['area' => 'WWD', 'gearbox_problem' => 'YES']);

    $bulMachine = makeGearboxTestMachine(['area' => 'BUL', 'machine_type' => 'BF']);
    $bulSchedule = makeGearboxTestPmSchedule($bulMachine, ['area' => 'BUL', 'machine_type' => 'BF']);

    $wwdResponse = $this->actingAs($admin)->get(route('pm-schedules.pdf', $wwdSchedule));
    $bulResponse = $this->actingAs($admin)->get(route('pm-schedules.pdf', $bulSchedule));

    $wwdResponse->assertOk();
    $bulResponse->assertOk();
    expect(base64_decode($wwdResponse->json('content')))->toStartWith('%PDF');
    expect(base64_decode($bulResponse->json('content')))->toStartWith('%PDF');
});
