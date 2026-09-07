<?php

use App\Models\Greasing;
use App\Models\Group;
use App\Models\Machine;
use App\Models\PMSchedule;
use App\Models\User;
use App\Services\ActiveActivityResolver;
use Carbon\Carbon;

function conflictPic(string $name = 'Budi'): User
{
    return User::factory()->create(['role' => User::ROLE_PIC_WWD, 'name' => $name]);
}

function conflictPm(string $pic, array $overrides = []): PMSchedule
{
    $machine = Machine::create([
        'machine_number' => 'MC-'.uniqid(),
        'area' => 'WWD',
        'machine_type' => 'NDE',
        'status' => 'ACTIVE',
    ]);

    $planDate = now()->toDateString();

    return PMSchedule::create(array_merge([
        'machine_id' => $machine->id,
        'machine_number' => $machine->machine_number,
        'machine_type' => $machine->machine_type,
        'area' => 'WWD',
        'order_number' => 'ORD-'.uniqid(),
        'plan_date' => $planDate,
        'plan_month' => Carbon::parse($planDate)->format('F'),
        'plan_year' => Carbon::parse($planDate)->format('Y'),
        'due_date' => Carbon::parse($planDate)->addDays(14),
        'pic' => $pic,
        'status' => 'OPEN',
    ], $overrides));
}

function conflictGreasing(string $pic, array $overrides = []): Greasing
{
    $group = Group::create(['name' => 'WWD Group '.uniqid()]);

    return Greasing::create(array_merge([
        'group_id' => $group->id,
        'order_number' => 'WO-'.uniqid(),
        'cycle' => '4W',
        'plan_date' => now()->toDateString(),
        'due_date' => Greasing::calculateDueDate(now()->toDateString()),
        'pic' => $pic,
        'status' => 'OPEN',
    ], $overrides));
}

test('a PIC with no active activity starts normally, no confirmation', function () {
    $pic = conflictPic();
    $pm = conflictPm('Budi');

    $this->actingAs($pic)
        ->post(route('pm-schedules.start', $pm), ['started_at' => now()->format('Y-m-d\TH:i')])
        ->assertSessionHas('success')
        ->assertSessionMissing('activity_conflict');

    expect($pm->fresh()->isActiveActivity())->toBeTrue();
});

test('an active greasing blocks starting a PM with a cross-module confirmation', function () {
    $pic = conflictPic();
    conflictGreasing('Budi', ['start_time' => now()->subHour()]);
    $pm = conflictPm('Budi');

    $this->actingAs($pic)
        ->post(route('pm-schedules.start', $pm), ['started_at' => now()->format('Y-m-d\TH:i')])
        ->assertSessionHas('activity_conflict');

    $conflict = session('activity_conflict');

    expect($conflict['pic'])->toBe('Budi')
        ->and($conflict['current'])->toBe('Greasing')
        ->and($pm->fresh()->start_time)->toBeNull();
});

test('an active PM blocks starting the daily Oil Audit with a confirmation', function () {
    $pic = conflictPic();
    conflictPm('Budi', [
        'status' => 'IN_PROGRESS',
        'start_time' => now()->subHour()->format('H:i'),
        'actual_date' => now()->toDateString(),
    ]);

    $this->actingAs($pic)
        ->post(route('oil-audits.start-daily'), ['started_at' => now()->format('Y-m-d\TH:i')])
        ->assertRedirect(route('oil-audits.scan'))
        ->assertSessionHas('activity_conflict');

    expect($pic->fresh()->oil_audit_started_at)->toBeNull();
});

test('END & START ends the previous activity and starts the new one without completing the old work', function () {
    $pic = conflictPic();
    $greasing = conflictGreasing('Budi', ['start_time' => now()->subHour()]);
    $pm = conflictPm('Budi');

    $this->actingAs($pic)
        ->post(route('pm-schedules.start', $pm), [
            'started_at' => now()->format('Y-m-d\TH:i'),
            'confirm_end_start' => '1',
        ])
        ->assertSessionHas('success');

    $resolver = app(ActiveActivityResolver::class);

    // The new PM is now the single active activity.
    expect($resolver->currentFor($pic->fresh())->source)->toBe('PM')
        ->and($pm->fresh()->isActiveActivity())->toBeTrue();

    // The greasing work status was NOT forced to complete.
    expect($greasing->fresh()->status)->toBe('OPEN');
});

test('a PIC can only ever have one active activity — newest start wins', function () {
    $pic = conflictPic();

    conflictGreasing('Budi', ['start_time' => now()->subHours(3)]);
    conflictPm('Budi', [
        'status' => 'IN_PROGRESS',
        'start_time' => now()->subHour()->format('H:i'),
        'actual_date' => now()->toDateString(),
    ]);

    $today = app(ActiveActivityResolver::class)->forToday($pic);

    expect($today)->toHaveCount(2)
        ->and($today->first()->source)->toBe('PM'); // most recent start
});

test('an activity started yesterday is auto-closed at day rollover (derived, not active today)', function () {
    $pic = conflictPic();

    // Started yesterday, never finished.
    conflictGreasing('Budi', ['start_time' => now()->subDay()->setTime(9, 0)]);
    $pm = conflictPm('Budi');

    // No conflict — yesterday's greasing is no longer active.
    $this->actingAs($pic)
        ->post(route('pm-schedules.start', $pm), ['started_at' => now()->format('Y-m-d\TH:i')])
        ->assertSessionHas('success')
        ->assertSessionMissing('activity_conflict');

    expect(app(ActiveActivityResolver::class)->forToday($pic))->toHaveCount(1);
});

test('day rollover changes no underlying work status (derived, nothing is mutated)', function () {
    $pic = conflictPic();
    $greasing = conflictGreasing('Budi', ['start_time' => now()->subDay()->setTime(9, 0)]);
    $pm = conflictPm('Budi', [
        'status' => 'IN_PROGRESS',
        'start_time' => '09:00',
        'actual_date' => now()->subDay()->toDateString(),
    ]);

    // Yesterday's activities are simply not "active" today...
    expect(app(ActiveActivityResolver::class)->forToday($pic))->toHaveCount(0);

    // ...and no record was touched to make that happen.
    expect($greasing->fresh()->status)->toBe('OPEN')
        ->and($pm->fresh()->status)->toBe('IN_PROGRESS')
        ->and($greasing->fresh()->start_time->format('Y-m-d'))->toBe(now()->subDay()->format('Y-m-d'))
        ->and($pm->fresh()->actual_date)->toBe(now()->subDay()->toDateString());
});
