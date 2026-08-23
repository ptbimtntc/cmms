<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('updating profile information shows a success toast', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = $this->put(route('profile.update'), [
        'name' => 'Renamed User',
        'email' => $user->email,
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success', 'Profile updated successfully.');

    $follow = $this->get(route('profile.edit'));
    $follow->assertSee('Profile updated successfully.');
});

test('changing password shows a success toast', function () {
    $user = User::factory()->create([
        'password' => Hash::make('old-password'),
    ]);

    $this->actingAs($user);

    $response = $this->put(route('profile.password.update'), [
        'current_password' => 'old-password',
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('password_success', 'Password changed successfully.');

    $follow = $this->get(route('profile.edit'));
    $follow->assertSee('Password changed successfully.');

    expect(Hash::check('new-password', $user->refresh()->password))->toBeTrue();
});

test('wrong current password redirects back with a validation error', function () {
    $user = User::factory()->create([
        'password' => Hash::make('old-password'),
    ]);

    $this->actingAs($user);

    $response = $this->from(route('profile.edit'))->put(route('profile.password.update'), [
        'current_password' => 'wrong-password',
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ]);

    $response->assertSessionHasErrors('current_password');

    expect(Hash::check('old-password', $user->refresh()->password))->toBeTrue();
});
