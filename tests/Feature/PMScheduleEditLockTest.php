<?php

use App\Models\Machine;
use App\Models\MachineChecklist;
use App\Models\PMChecklist;
use App\Models\PMSchedule;
use App\Models\User;
use Carbon\Carbon;

function makeLockTestMachine(array $overrides = []): Machine
{
    return Machine::create(array_merge([
        'machine_number' => 'MC-'.uniqid(),
        'area' => 'WWD',
        'machine_type' => 'NDE',
        'status' => 'ACTIVE',
    ], $overrides));
}

function makeLockTestPmSchedule(Machine $machine, array $overrides = []): PMSchedule
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
        'pic' => 'Andi',
        'status' => 'FINISHED',
    ], $overrides));
}

test('pic cannot update a finished pm schedule', function () {
    $pic = User::factory()->create([...roleAttributes('PIC WWD'), 'name' => 'Andi']);
    $machine = makeLockTestMachine(['area' => 'WWD']);
    $pmSchedule = makeLockTestPmSchedule($machine, ['area' => 'WWD', 'pic' => 'Andi']);

    $response = $this->actingAs($pic)->put(route('pm-schedules.update', $pmSchedule), [
        'order_number' => $pmSchedule->order_number,
        'remarks' => 'trying to sneak an edit in',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('warning');
    expect($pmSchedule->fresh()->status)->toBe('FINISHED');
    expect($pmSchedule->fresh()->remarks)->not->toBe('trying to sneak an edit in');
});

test('admin cannot update a finished pm schedule without reopening it first', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = makeLockTestMachine();
    $pmSchedule = makeLockTestPmSchedule($machine);

    $response = $this->actingAs($admin)->put(route('pm-schedules.update', $pmSchedule), [
        'order_number' => $pmSchedule->order_number,
        'remarks' => 'trying to sneak an edit in',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('warning');
    expect($pmSchedule->fresh()->status)->toBe('FINISHED');
});

test('pic cannot save checklist on a finished pm schedule', function () {
    $pic = User::factory()->create([...roleAttributes('PIC WWD'), 'name' => 'Andi']);
    $machine = makeLockTestMachine(['area' => 'WWD']);
    $pmSchedule = makeLockTestPmSchedule($machine, ['area' => 'WWD', 'pic' => 'Andi']);

    $response = $this->actingAs($pic)->post(route('pm-schedules.checklist.save', $pmSchedule), [
        'checklists' => [],
    ]);

    $response->assertRedirect(route('pm-schedules.checklist', ['pmSchedule' => $pmSchedule->id]));
    $response->assertSessionHas('warning');
    expect($pmSchedule->fresh()->status)->toBe('FINISHED');
});

test('koordinator cannot assign a pic on a finished pm schedule', function () {
    $koordinator = User::factory()->create([...roleAttributes('KOORDINATOR WWD')]);
    $machine = makeLockTestMachine(['area' => 'WWD']);
    $pmSchedule = makeLockTestPmSchedule($machine, ['area' => 'WWD']);
    User::factory()->create([...roleAttributes('PIC WWD'), 'name' => 'Budi']);

    $response = $this->actingAs($koordinator)->post(route('pm-schedules.assign-pic', $pmSchedule), [
        'pic' => 'Budi',
    ]);

    $response->assertForbidden();
    expect($pmSchedule->fresh()->pic)->toBe('Andi');
});

test('koordinator can reopen a finished pm schedule for editing', function () {
    $koordinator = User::factory()->create([...roleAttributes('KOORDINATOR WWD')]);
    $machine = makeLockTestMachine(['area' => 'WWD']);
    $pmSchedule = makeLockTestPmSchedule($machine, ['area' => 'WWD', 'status' => 'FINISHED_ON_TIME']);

    $machineChecklist = MachineChecklist::create([
        'machine_type' => $machine->machine_type,
        'section' => 'General',
        'checklist_item' => 'Check bolts',
        'section_order' => 1,
        'item_order' => 1,
    ]);

    PMChecklist::create([
        'pm_schedule_id' => $pmSchedule->id,
        'machine_checklist_id' => $machineChecklist->id,
        'check' => 'YES',
        'remarks' => 'keep me',
    ]);

    $response = $this->actingAs($koordinator)->post(route('pm-schedules.reopen-edit', $pmSchedule));

    $response->assertRedirect(route('pm-schedules.edit', ['pm_schedule' => $pmSchedule->id]));
    $response->assertSessionHas('success');

    $fresh = $pmSchedule->fresh();
    expect($fresh->status)->toBe('IN_PROGRESS');
    expect($fresh->checklists()->first()->remarks)->toBe('keep me');
});

test('admin can reopen a finished pm schedule for editing', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = makeLockTestMachine();
    $pmSchedule = makeLockTestPmSchedule($machine);

    $response = $this->actingAs($admin)->post(route('pm-schedules.reopen-edit', $pmSchedule));

    $response->assertRedirect(route('pm-schedules.edit', ['pm_schedule' => $pmSchedule->id]));
    expect($pmSchedule->fresh()->status)->toBe('IN_PROGRESS');
});

test('reopening a pm schedule that is not finished is a no-op', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = makeLockTestMachine();
    $pmSchedule = makeLockTestPmSchedule($machine, ['status' => 'OPEN']);

    $response = $this->actingAs($admin)->post(route('pm-schedules.reopen-edit', $pmSchedule));

    $response->assertSessionHas('warning');
    expect($pmSchedule->fresh()->status)->toBe('OPEN');
});

test('pic cannot reach the reopen-edit route', function () {
    $pic = User::factory()->create([...roleAttributes('PIC WWD'), 'name' => 'Andi']);
    $machine = makeLockTestMachine(['area' => 'WWD']);
    $pmSchedule = makeLockTestPmSchedule($machine, ['area' => 'WWD', 'pic' => 'Andi']);

    $response = $this->actingAs($pic)->post(route('pm-schedules.reopen-edit', $pmSchedule));

    $response->assertForbidden();
});

test('supervisor cannot reach the reopen-edit route', function () {
    $supervisor = User::factory()->create(['role' => User::ROLE_SUPERVISOR]);
    $machine = makeLockTestMachine();
    $pmSchedule = makeLockTestPmSchedule($machine);

    $response = $this->actingAs($supervisor)->post(route('pm-schedules.reopen-edit', $pmSchedule));

    $response->assertForbidden();
});

test('edit page shows a buka edit button for koordinator when the pm is finished', function () {
    $koordinator = User::factory()->create([...roleAttributes('KOORDINATOR WWD')]);
    $machine = makeLockTestMachine(['area' => 'WWD']);
    $pmSchedule = makeLockTestPmSchedule($machine, ['area' => 'WWD']);

    $response = $this->actingAs($koordinator)->get(route('pm-schedules.edit', $pmSchedule));

    $response->assertOk();
    $response->assertSee('Reopen Edit');
    $response->assertSee(route('pm-schedules.reopen-edit', $pmSchedule), false);
});

test('index shows buka edit action for koordinator on a finished pm row', function () {
    $koordinator = User::factory()->create([...roleAttributes('KOORDINATOR WWD')]);
    $machine = makeLockTestMachine(['area' => 'WWD']);
    $pmSchedule = makeLockTestPmSchedule($machine, ['area' => 'WWD']);

    $response = $this->actingAs($koordinator)->get(route('pm-schedules.index'));

    $response->assertOk();
    $response->assertSee('Reopen Edit');
    $response->assertSee(route('pm-schedules.reopen-edit', $pmSchedule->id), false);
});
