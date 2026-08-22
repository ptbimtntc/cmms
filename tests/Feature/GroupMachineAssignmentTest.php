<?php

use App\Models\Group;
use App\Models\Machine;
use App\Models\User;

function assignmentUser(string $role): User
{
    return User::factory()->create(['role' => $role]);
}

function assignmentMachine(array $attributes = []): Machine
{
    return Machine::create(array_merge([
        'machine_number' => 'MC-'.uniqid(),
        'area' => 'WWD',
        'machine_type' => 'NDE',
        'status' => 'ACTIVE',
    ], $attributes));
}

test('checking machines on the edit group page assigns them to that group', function () {
    $admin = assignmentUser('ADMIN');
    $group = Group::create(['name' => 'Line 1']);
    $machineA = assignmentMachine();
    $machineB = assignmentMachine();

    $response = $this->actingAs($admin)->put(route('groups.update', $group), [
        'name' => 'Line 1',
        'machine_ids' => [$machineA->id, $machineB->id],
    ]);

    $response->assertRedirect(route('groups.index'));
    expect($machineA->fresh()->group_id)->toBe($group->id)
        ->and($machineB->fresh()->group_id)->toBe($group->id);
});

test('unchecking a machine that was previously in the group removes it from the group', function () {
    $admin = assignmentUser('ADMIN');
    $group = Group::create(['name' => 'Line 1']);
    $machine = assignmentMachine(['group_id' => $group->id]);

    $response = $this->actingAs($admin)->put(route('groups.update', $group), [
        'name' => 'Line 1',
        'machine_ids' => [],
    ]);

    $response->assertRedirect(route('groups.index'));
    expect($machine->fresh()->group_id)->toBeNull();
});

test('checking a machine that belongs to another group moves it, never duplicates the relation', function () {
    $admin = assignmentUser('ADMIN');
    $groupA = Group::create(['name' => 'Line A']);
    $groupB = Group::create(['name' => 'Line B']);
    $machine = assignmentMachine(['group_id' => $groupA->id]);

    $this->actingAs($admin)->put(route('groups.update', $groupB), [
        'name' => 'Line B',
        'machine_ids' => [$machine->id],
    ]);

    $machine->refresh();
    expect($machine->group_id)->toBe($groupB->id)
        ->and($machine->group_id)->not->toBe($groupA->id);

    // A machine has exactly one group_id column — it can never belong to two groups at once.
    expect(Machine::where('id', $machine->id)->count())->toBe(1);
});

test('the number of machines shown on the index still reflects the real relation after assignment', function () {
    $admin = assignmentUser('ADMIN');
    $group = Group::create(['name' => 'Line 1']);
    $machineA = assignmentMachine();
    $machineB = assignmentMachine();

    $this->actingAs($admin)->put(route('groups.update', $group), [
        'name' => 'Line 1',
        'machine_ids' => [$machineA->id, $machineB->id],
    ]);

    $response = $this->actingAs($admin)->get(route('groups.index'));

    $response->assertOk();
    $response->assertSee('2');
    expect($group->fresh()->machines()->count())->toBe(2);
});

test('machines not touched (left unchecked and not previously in the group) are unaffected', function () {
    $admin = assignmentUser('ADMIN');
    $group = Group::create(['name' => 'Line 1']);
    $otherGroup = Group::create(['name' => 'Line 2']);
    $untouched = assignmentMachine(['group_id' => $otherGroup->id]);

    $this->actingAs($admin)->put(route('groups.update', $group), [
        'name' => 'Line 1',
        'machine_ids' => [],
    ]);

    expect($untouched->fresh()->group_id)->toBe($otherGroup->id);
});

test('edit group page lists machines with their current group assignment', function () {
    $admin = assignmentUser('ADMIN');
    $group = Group::create(['name' => 'Line 1']);
    $otherGroup = Group::create(['name' => 'Line 2']);
    $mine = assignmentMachine(['group_id' => $group->id, 'machine_number' => 'MC-MINE']);
    $elsewhere = assignmentMachine(['group_id' => $otherGroup->id, 'machine_number' => 'MC-ELSEWHERE']);

    $response = $this->actingAs($admin)->get(route('groups.edit', $group));

    $response->assertOk();
    $response->assertSee('MC-MINE');
    $response->assertSee('MC-ELSEWHERE');
    $response->assertSee('Line 2'); // hint showing where the other machine currently lives
});

test('invalid machine id in the request is rejected by validation', function () {
    $admin = assignmentUser('ADMIN');
    $group = Group::create(['name' => 'Line 1']);

    $response = $this->actingAs($admin)->put(route('groups.update', $group), [
        'name' => 'Line 1',
        'machine_ids' => [999999],
    ]);

    $response->assertSessionHasErrors('machine_ids.0');
});

test('pic and guest cannot reach the edit group page or update assignments', function (string $role) {
    $group = Group::create(['name' => 'Line 1']);
    $machine = assignmentMachine();
    $user = assignmentUser($role);

    $this->actingAs($user)->get(route('groups.edit', $group))->assertForbidden();
    $this->actingAs($user)->put(route('groups.update', $group), [
        'name' => 'Line 1',
        'machine_ids' => [$machine->id],
    ])->assertForbidden();

    expect($machine->fresh()->group_id)->toBeNull();
})->with(['PIC WWD', 'PIC BUL', 'GUEST']);
