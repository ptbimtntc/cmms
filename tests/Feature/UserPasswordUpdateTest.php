<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('admin sees a distinct confirmation when changing another user\'s password', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $target = User::factory()->create(['role' => User::ROLE_GUEST]);

    $this->actingAs($admin);

    $response = $this->put(route('users.update', $target), [
        'name' => $target->name,
        'email' => $target->email,
        'role' => $target->role,
        'is_active' => 1,
        'password' => 'new-secret-password',
    ]);

    $response->assertRedirect(route('users.index'));
    $response->assertSessionHas('success', 'User updated successfully. Password has been changed.');

    expect(Hash::check('new-secret-password', $target->refresh()->password))->toBeTrue();
});

test('admin updating a user without touching the password sees the generic confirmation', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $target = User::factory()->create(['role' => User::ROLE_GUEST]);

    $this->actingAs($admin);

    $response = $this->put(route('users.update', $target), [
        'name' => 'Renamed User',
        'email' => $target->email,
        'role' => $target->role,
        'is_active' => 1,
    ]);

    $response->assertRedirect(route('users.index'));
    $response->assertSessionHas('success', 'User updated successfully.');
});

test('weak password on admin edit user is rejected with a visible error', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $target = User::factory()->create(['role' => User::ROLE_GUEST]);

    $this->actingAs($admin);

    $response = $this->put(route('users.update', $target), [
        'name' => $target->name,
        'email' => $target->email,
        'role' => $target->role,
        'is_active' => 1,
        'password' => 'short',
    ]);

    $response->assertSessionHasErrors('password');
});
