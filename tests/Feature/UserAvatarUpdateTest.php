<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('admin edit user page renders with the crop modal available', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $target = User::factory()->create(['role' => User::ROLE_GUEST]);

    $this->actingAs($admin);

    $response = $this->get(route('users.edit', $target));

    $response->assertOk();
    $response->assertSee('cropOpen', false);
    $response->assertSee('Adjust Photo');
});

test('admin can upload a new avatar for another user', function () {
    Storage::fake('public');

    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $target = User::factory()->create([
        'role' => User::ROLE_GUEST,
        'avatar_path' => 'avatars/old-avatar.jpg',
    ]);
    Storage::disk('public')->put('avatars/old-avatar.jpg', 'old-binary');

    $this->actingAs($admin);

    $response = $this->put(route('users.update', $target), [
        'name' => $target->name,
        'email' => $target->email,
        'role' => $target->role,
        'is_active' => 1,
        'avatar' => UploadedFile::fake()->image('avatar.jpg'),
    ]);

    $response->assertRedirect(route('users.index'));

    $target->refresh();

    expect($target->avatar_path)->not->toBeNull();
    expect($target->avatar_path)->not->toEqual('avatars/old-avatar.jpg');

    Storage::disk('public')->assertExists($target->avatar_path);
    Storage::disk('public')->assertMissing('avatars/old-avatar.jpg');
});

test('admin can remove another user\'s avatar', function () {
    Storage::fake('public');

    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $target = User::factory()->create([
        'role' => User::ROLE_GUEST,
        'avatar_path' => 'avatars/old-avatar.jpg',
    ]);
    Storage::disk('public')->put('avatars/old-avatar.jpg', 'old-binary');

    $this->actingAs($admin);

    $response = $this->put(route('users.update', $target), [
        'name' => $target->name,
        'email' => $target->email,
        'role' => $target->role,
        'is_active' => 1,
        'remove_avatar' => 1,
    ]);

    $response->assertRedirect(route('users.index'));

    expect($target->refresh()->avatar_path)->toBeNull();
    Storage::disk('public')->assertMissing('avatars/old-avatar.jpg');
});
