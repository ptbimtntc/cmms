<?php

use App\Models\Machine;
use App\Models\PMSchedule;
use App\Models\User;

function deleteBtnSchedule(): PMSchedule
{
    $machine = Machine::create([
        'machine_number' => 'MC-'.uniqid(),
        'area' => 'WWD',
        'machine_type' => 'NDE SW',
        'status' => 'ACTIVE',
    ]);

    return PMSchedule::create([
        'machine_id' => $machine->id,
        'machine_number' => $machine->machine_number,
        'machine_type' => $machine->machine_type,
        'area' => $machine->area,
        'order_number' => 'ORD-'.uniqid(),
        'plan_date' => now()->toDateString(),
        'plan_month' => now()->format('F'),
        'plan_year' => now()->format('Y'),
        'due_date' => now()->addDays(14),
        'status' => 'OPEN',
        'pic' => 'Budi',
    ]);
}

test('delete button is shown on the PM schedule list for admin and koordinator', function (string $role) {
    deleteBtnSchedule();
    $user = User::factory()->create(roleAttributes($role));

    $this->actingAs($user)->get(route('pm-schedules.index'))
        ->assertOk()
        ->assertSee('name="_method" value="DELETE"', false);
})->with(['ADMIN', 'KOORDINATOR WWD']);

test('delete button is not shown to PIC', function () {
    deleteBtnSchedule();
    $pic = User::factory()->create([...roleAttributes('PIC WWD'), 'name' => 'Budi']);

    $this->actingAs($pic)->get(route('pm-schedules.index'))
        ->assertOk()
        ->assertDontSee('name="_method" value="DELETE"', false);
});

test('admin and koordinator can delete a PM schedule, PIC cannot', function () {
    $pm = deleteBtnSchedule();
    $pic = User::factory()->create([...roleAttributes('PIC WWD'), 'name' => 'Budi']);
    $this->actingAs($pic)->delete(route('pm-schedules.destroy', $pm->id))->assertForbidden();
    expect(PMSchedule::find($pm->id))->not->toBeNull();

    $koordinator = User::factory()->create(roleAttributes('KOORDINATOR WWD'));
    $this->actingAs($koordinator)->delete(route('pm-schedules.destroy', $pm->id))->assertRedirect();
    expect(PMSchedule::find($pm->id))->toBeNull();
});
