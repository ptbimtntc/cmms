<?php

use App\Models\Machine;
use App\Models\PMSchedule;

function machineHistoryMachine(array $overrides = []): Machine
{
    return Machine::create(array_merge([
        'machine_number' => 'MC-'.uniqid(),
        'area' => 'WWD',
        'machine_type' => 'NDE',
        'status' => 'ACTIVE',
    ], $overrides));
}

function machineHistoryPmSchedule(Machine $machine, array $overrides = []): PMSchedule
{
    return PMSchedule::create(array_merge([
        'machine_id' => $machine->id,
        'machine_number' => $machine->machine_number,
        'machine_type' => $machine->machine_type,
        'area' => $machine->area,
        'order_number' => 'WO-'.uniqid(),
        'plan_date' => now(),
        'plan_month' => now()->format('F'),
        'plan_year' => now()->year,
        'due_date' => now(),
        'status' => 'OPEN',
    ], $overrides));
}

test('a machine with no pm schedule history still appears in the list', function () {
    machineHistoryMachine(['machine_number' => 'MC-NEVER-PM']);

    $response = $this->get(route('machine-history.index'));

    $response->assertOk();
    $numbers = $response->viewData('machines')->pluck('machine_number')->all();

    expect($numbers)->toContain('MC-NEVER-PM');
});

test('the list defaults to all machines in machine master, not just those with pm history', function () {
    $withHistory = machineHistoryMachine(['machine_number' => 'MC-WITH-PM']);
    machineHistoryMachine(['machine_number' => 'MC-NO-PM']);
    machineHistoryPmSchedule($withHistory, ['actual_date' => '2026-08-01']);

    $response = $this->get(route('machine-history.index'));

    $response->assertOk();
    $numbers = $response->viewData('machines')->pluck('machine_number')->all();

    expect($numbers)->toContain('MC-WITH-PM', 'MC-NO-PM');
});

test('a machine appears exactly once even with many pm schedule records', function () {
    $machine = machineHistoryMachine(['machine_number' => 'MC-MANY-PM']);
    machineHistoryPmSchedule($machine, ['actual_date' => '2026-08-01']);
    machineHistoryPmSchedule($machine, ['actual_date' => '2026-08-10']);
    machineHistoryPmSchedule($machine, ['actual_date' => '2026-08-20']);

    $response = $this->get(route('machine-history.index'));

    $response->assertOk();
    $rows = $response->viewData('machines')->where('machine_number', 'MC-MANY-PM');

    expect($rows)->toHaveCount(1);
});

test('last pm shows the most recent actual_date across all pm schedules for that machine', function () {
    $machine = machineHistoryMachine(['machine_number' => 'MC-LAST-PM']);
    machineHistoryPmSchedule($machine, ['actual_date' => '2026-08-01']);
    machineHistoryPmSchedule($machine, ['actual_date' => '2026-08-20']);
    machineHistoryPmSchedule($machine, ['actual_date' => '2026-08-10']);

    $response = $this->get(route('machine-history.index'));

    $response->assertOk();
    $row = $response->viewData('machines')->firstWhere('machine_number', 'MC-LAST-PM');

    expect(Carbon\Carbon::parse($row->last_pm)->toDateString())->toBe('2026-08-20');
});

test('a machine with no pm schedule shows a null last pm, not an error', function () {
    machineHistoryMachine(['machine_number' => 'MC-NULL-PM']);

    $response = $this->get(route('machine-history.index'));

    $response->assertOk();
    $row = $response->viewData('machines')->firstWhere('machine_number', 'MC-NULL-PM');

    expect($row->last_pm)->toBeNull();
    $response->assertSee('-');
});

test('area and machine type filter options come from machine master, including machines with no pm history', function () {
    machineHistoryMachine(['machine_number' => 'MC-BUL-1', 'area' => 'BUL', 'machine_type' => 'BFM']);

    $response = $this->get(route('machine-history.index'));

    $response->assertOk();
    expect($response->viewData('areas'))->toContain('BUL');
    expect($response->viewData('machineTypes'))->toContain('BFM');
});

test('search filters by machine number or type against machine master', function () {
    machineHistoryMachine(['machine_number' => 'UNIQUE-HIST-001']);
    machineHistoryMachine(['machine_number' => 'OTHER-HIST-002']);

    $response = $this->get(route('machine-history.index', ['search' => 'UNIQUE-HIST-001']));

    $response->assertOk();
    expect($response->viewData('machines')->pluck('machine_number')->all())->toBe(['UNIQUE-HIST-001']);
});

test('area filter narrows the machine list', function () {
    machineHistoryMachine(['machine_number' => 'MC-WWD-F', 'area' => 'WWD']);
    machineHistoryMachine(['machine_number' => 'MC-BUL-F', 'area' => 'BUL']);

    $response = $this->get(route('machine-history.index', ['area' => 'BUL']));

    $response->assertOk();
    expect($response->viewData('machines')->pluck('machine_number')->all())->toBe(['MC-BUL-F']);
});

test('pagination is machine-centric: 20 machines per page regardless of pm schedule count per machine', function () {
    for ($i = 0; $i < 25; $i++) {
        $machine = machineHistoryMachine(['machine_number' => 'MC-'.str_pad((string) $i, 3, '0', STR_PAD_LEFT)]);
        if ($i < 3) {
            machineHistoryPmSchedule($machine, ['actual_date' => now()->subDays($i)]);
            machineHistoryPmSchedule($machine, ['actual_date' => now()->subDays($i + 100)]);
        }
    }

    $page1 = $this->get(route('machine-history.index', ['page' => 1]));
    $page2 = $this->get(route('machine-history.index', ['page' => 2]));

    expect($page1->viewData('machines'))->toHaveCount(20);
    expect($page2->viewData('machines'))->toHaveCount(5);
    expect($page1->viewData('machines')->pluck('machine_number')->unique())->toHaveCount(20);
});

test('view history link is still available for a machine with no pm history', function () {
    $machine = machineHistoryMachine(['machine_number' => 'MC-LINK-TEST']);

    $response = $this->get(route('machine-history.index'));

    $response->assertOk();
    $response->assertSee(route('machine-history.show', $machine->machine_number), false);
});
