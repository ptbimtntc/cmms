<?php

use App\Models\Machine;
use App\Models\PMSchedule;
use App\Models\User;
use App\Services\ActiveActivityResolver;
use Carbon\Carbon;

function makeStartTestMachine(array $overrides = []): Machine
{
    return Machine::create(array_merge([
        'machine_number' => 'MC-'.uniqid(),
        'area' => 'WWD',
        'machine_type' => 'NDE',
        'status' => 'ACTIVE',
    ], $overrides));
}

function makeStartTestPmSchedule(Machine $machine, array $overrides = []): PMSchedule
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

test('pic can start a pm activity, writing the existing start_time and actual_date columns', function () {
    $pic = User::factory()->create(['role' => User::ROLE_PIC_WWD, 'name' => 'Budi']);
    $machine = makeStartTestMachine();
    $pm = makeStartTestPmSchedule($machine, ['pic' => 'Budi']);

    $this->actingAs($pic)
        ->post(route('pm-schedules.start', $pm), ['started_at' => '2026-09-06T08:30'])
        ->assertRedirect();

    $pm->refresh();

    expect($pm->start_time)->toStartWith('08:30')
        ->and(Carbon::parse($pm->actual_date)->toDateString())->toBe('2026-09-06')
        ->and($pm->status)->toBe('IN_PROGRESS')
        ->and($pm->isActiveActivity())->toBeTrue();
});

test('starting does not overwrite an already started pm', function () {
    $pic = User::factory()->create(['role' => User::ROLE_PIC_WWD, 'name' => 'Budi']);
    $machine = makeStartTestMachine();
    $pm = makeStartTestPmSchedule($machine, [
        'pic' => 'Budi',
        'status' => 'IN_PROGRESS',
        'start_time' => '07:00',
        'actual_date' => '2026-09-05',
    ]);

    $this->actingAs($pic)
        ->post(route('pm-schedules.start', $pm), ['started_at' => '2026-09-06T09:00'])
        ->assertSessionHas('warning');

    $pm->refresh();

    expect($pm->start_time)->toStartWith('07:00')
        ->and(Carbon::parse($pm->actual_date)->toDateString())->toBe('2026-09-05');
});

test('a finished pm cannot be started', function () {
    $pic = User::factory()->create(['role' => User::ROLE_PIC_WWD, 'name' => 'Budi']);
    $machine = makeStartTestMachine();
    $pm = makeStartTestPmSchedule($machine, ['pic' => 'Budi', 'status' => 'FINISHED_ON_TIME']);

    $this->actingAs($pic)
        ->post(route('pm-schedules.start', $pm), ['started_at' => '2026-09-06T08:30'])
        ->assertSessionHas('warning');

    expect($pm->fresh()->start_time)->toBeNull();
});

test('a pic cannot start a pm assigned to someone else', function () {
    $pic = User::factory()->create(['role' => User::ROLE_PIC_WWD, 'name' => 'Budi']);
    $machine = makeStartTestMachine();
    $pm = makeStartTestPmSchedule($machine, ['pic' => 'Andi']);

    $this->actingAs($pic)
        ->post(route('pm-schedules.start', $pm), ['started_at' => '2026-09-06T08:30'])
        ->assertForbidden();

    expect($pm->fresh()->start_time)->toBeNull();
});

test('starting a second pm asks for END & START confirmation while the pic already has an active one', function () {
    $pic = User::factory()->create(['role' => User::ROLE_PIC_WWD, 'name' => 'Budi']);
    $machine = makeStartTestMachine();

    makeStartTestPmSchedule($machine, [
        'pic' => 'Budi',
        'status' => 'IN_PROGRESS',
        'start_time' => '08:00',
        'actual_date' => now()->toDateString(),
    ]);

    $second = makeStartTestPmSchedule(makeStartTestMachine(), ['pic' => 'Budi']);

    $this->actingAs($pic)
        ->post(route('pm-schedules.start', $second), ['started_at' => now()->format('Y-m-d\TH:i')])
        ->assertSessionHas('activity_conflict');

    // Not started yet — the PIC still has to confirm END & START.
    expect($second->fresh()->start_time)->toBeNull();
});

test('END & START on a second pm closes the first and starts the second', function () {
    $pic = User::factory()->create(['role' => User::ROLE_PIC_WWD, 'name' => 'Budi']);

    $first = makeStartTestPmSchedule(makeStartTestMachine(), [
        'pic' => 'Budi',
        'status' => 'IN_PROGRESS',
        'start_time' => '08:00',
        'actual_date' => now()->toDateString(),
    ]);

    $second = makeStartTestPmSchedule(makeStartTestMachine(), ['pic' => 'Budi']);

    $this->actingAs($pic)
        ->post(route('pm-schedules.start', $second), [
            'started_at' => now()->format('Y-m-d\TH:i'),
            'confirm_end_start' => '1',
        ])
        ->assertSessionHas('success');

    $second->refresh();
    $first->refresh();

    // New activity started and is now the PIC's single active activity.
    expect($second->start_time)->not->toBeNull()
        ->and($second->isActiveActivity())->toBeTrue()
        ->and(app(ActiveActivityResolver::class)->currentFor($pic->fresh())->recordId)
        ->toBe($second->id);

    // The first PM's work status is NOT forced to complete — closing an
    // activity is not completing work.
    expect($first->status)->toBe('IN_PROGRESS');
});

test('the pm index shows START next to Fill PM, then STARTED after starting', function () {
    $pic = User::factory()->create(['role' => User::ROLE_PIC_WWD, 'name' => 'Budi']);
    $machine = makeStartTestMachine();
    $pm = makeStartTestPmSchedule($machine, ['pic' => 'Budi']);

    $this->actingAs($pic)->get(route('pm-schedules.index'))
        ->assertSee('START')
        ->assertSee('Fill PM');

    $pm->update(['start_time' => '08:30', 'actual_date' => '2026-09-06', 'status' => 'IN_PROGRESS']);

    $this->actingAs($pic)->get(route('pm-schedules.index'))
        ->assertSee('STARTED');
});
