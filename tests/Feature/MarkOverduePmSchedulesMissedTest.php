<?php

use App\Models\Machine;
use App\Models\PMSchedule;
use Carbon\Carbon;

function overdueSweepMachine(array $overrides = []): Machine
{
    return Machine::create(array_merge([
        'machine_number' => 'MC-'.uniqid(),
        'area' => 'WWD',
        'machine_type' => 'NDE',
        'status' => 'ACTIVE',
    ], $overrides));
}

function overdueSweepPmSchedule(Machine $machine, array $overrides = []): PMSchedule
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

test('marks an untouched open pm schedule as missed once its due date has passed', function () {
    $machine = overdueSweepMachine();
    $pm = overdueSweepPmSchedule($machine, [
        'due_date' => now()->subDay()->toDateString(),
        'actual_date' => null,
        'status' => 'OPEN',
    ]);

    $this->artisan('pm-schedules:mark-overdue-missed')->assertExitCode(0);

    expect($pm->fresh()->status)->toBe('MISSED');
});

test('leaves an open pm schedule alone while its due date is still in the future', function () {
    $machine = overdueSweepMachine();
    $pm = overdueSweepPmSchedule($machine, [
        'due_date' => now()->addDay()->toDateString(),
        'actual_date' => null,
        'status' => 'OPEN',
    ]);

    $this->artisan('pm-schedules:mark-overdue-missed')->assertExitCode(0);

    expect($pm->fresh()->status)->toBe('OPEN');
});

test('does not touch an in progress pm schedule that has already been started', function () {
    $machine = overdueSweepMachine();
    // Starting a PM sets actual_date immediately (PMStartService::start()) —
    // an overdue due_date here means it's late, not untouched/missed.
    $pm = overdueSweepPmSchedule($machine, [
        'due_date' => now()->subDay()->toDateString(),
        'actual_date' => now()->toDateString(),
        'status' => 'IN_PROGRESS',
    ]);

    $this->artisan('pm-schedules:mark-overdue-missed')->assertExitCode(0);

    expect($pm->fresh()->status)->toBe('IN_PROGRESS');
});

test('does not touch already finished pm schedules', function () {
    $machine = overdueSweepMachine();
    $pm = overdueSweepPmSchedule($machine, [
        'due_date' => now()->subDay()->toDateString(),
        'actual_date' => now()->subDays(2)->toDateString(),
        'status' => 'FINISHED_ON_TIME',
    ]);

    $this->artisan('pm-schedules:mark-overdue-missed')->assertExitCode(0);

    expect($pm->fresh()->status)->toBe('FINISHED_ON_TIME');
});

test('is idempotent for pm schedules already marked missed', function () {
    $machine = overdueSweepMachine();
    $pm = overdueSweepPmSchedule($machine, [
        'due_date' => now()->subDays(5)->toDateString(),
        'actual_date' => null,
        'status' => 'MISSED',
    ]);

    $this->artisan('pm-schedules:mark-overdue-missed')->assertExitCode(0);

    expect($pm->fresh()->status)->toBe('MISSED');
});
