<?php

use App\Models\Machine;
use App\Models\MachineMaintenanceRequirement;
use App\Models\User;

function createMachineOfType(string $machineType, string $machineNumber): Machine
{
    return Machine::create([
        'machine_number' => $machineNumber,
        'area' => 'WWD',
        'machine_type' => $machineType,
        'status' => 'ACTIVE',
    ]);
}

test('admin can list machine maintenance requirements', function () {
    MachineMaintenanceRequirement::create(['machine_type' => 'NDE SW', 'requires_oil_change' => true]);
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get(route('machine-maintenance-requirements.index'))
        ->assertOk()
        ->assertSee('NDE SW');
});

test('the add form dropdown only offers machine types from master data that are not yet configured', function () {
    createMachineOfType('NDE SW', 'MC-1');
    createMachineOfType('NDE NEW', 'MC-2');
    MachineMaintenanceRequirement::create(['machine_type' => 'NDE SW', 'requires_oil_change' => true]);
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->get(route('machine-maintenance-requirements.create'));

    $response->assertOk()
        ->assertSee('NDE NEW')
        ->assertDontSee('NDE SW');
});

test('admin can add a new machine type from master data', function () {
    createMachineOfType('NDE NEW', 'MC-3');
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->post(route('machine-maintenance-requirements.store'), [
        'machine_type' => 'NDE NEW',
        'requires_oil_change' => '1',
    ]);

    $response->assertRedirect(route('machine-maintenance-requirements.index'));
    expect(MachineMaintenanceRequirement::where('machine_type', 'NDE NEW')->first()->requires_oil_change)->toBeTrue();
});

test('cannot add a machine type that does not exist in master data', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->post(route('machine-maintenance-requirements.store'), [
        'machine_type' => 'GHOST TYPE',
        'requires_oil_change' => '1',
    ]);

    $response->assertSessionHasErrors('machine_type');
    expect(MachineMaintenanceRequirement::where('machine_type', 'GHOST TYPE')->exists())->toBeFalse();
});

test('machine type must be unique', function () {
    createMachineOfType('NDE SW', 'MC-4');
    MachineMaintenanceRequirement::create(['machine_type' => 'NDE SW', 'requires_oil_change' => true]);
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->post(route('machine-maintenance-requirements.store'), [
        'machine_type' => 'NDE SW',
        'requires_oil_change' => '1',
    ]);

    $response->assertSessionHasErrors('machine_type');
});

test('admin can update a machine type requirement', function () {
    createMachineOfType('NDE SW', 'MC-5');
    $requirement = MachineMaintenanceRequirement::create(['machine_type' => 'NDE SW', 'requires_oil_change' => true]);
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->put(route('machine-maintenance-requirements.update', $requirement), [
        'machine_type' => 'NDE SW',
        'requires_oil_change' => '0',
    ]);

    expect($requirement->fresh()->requires_oil_change)->toBeFalse();
});

test('admin can delete a machine type requirement', function () {
    createMachineOfType('NDE SW', 'MC-6');
    $requirement = MachineMaintenanceRequirement::create(['machine_type' => 'NDE SW', 'requires_oil_change' => true]);
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->delete(route('machine-maintenance-requirements.destroy', $requirement));

    expect(MachineMaintenanceRequirement::find($requirement->id))->toBeNull();
});

test('non-admin roles cannot access machine maintenance requirement management', function (string $role) {
    createMachineOfType('X', 'MC-7');
    $user = User::factory()->create(roleAttributes($role));

    $this->actingAs($user)->get(route('machine-maintenance-requirements.index'))->assertForbidden();
    $this->actingAs($user)->post(route('machine-maintenance-requirements.store'), ['machine_type' => 'X', 'requires_oil_change' => '1'])->assertForbidden();
})->with(['KOORDINATOR WWD', 'PIC WWD', 'GUEST']);

test('a newly added machine type immediately gates the oil change field on Fill PM', function () {
    createMachineOfType('BFM', 'MC-8');
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post(route('machine-maintenance-requirements.store'), [
        'machine_type' => 'BFM',
        'requires_oil_change' => '1',
    ]);

    $pmSchedule = new App\Models\PMSchedule(['machine_type' => 'BFM']);
    expect($pmSchedule->requiresOilChange())->toBeTrue();
});
