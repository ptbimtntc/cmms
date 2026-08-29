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

test('the edit user form opts out of native html5 validation so a hidden/dynamic control never blocks submission', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $target = User::factory()->create(['role' => User::ROLE_GUEST]);

    $response = $this->actingAs($admin)->get(route('users.edit', $target));

    $response->assertOk();
    $response->assertSee('novalidate', false);
});

test('photo_url is null when avatar_path points to a file missing from disk, instead of a broken image link', function () {
    Storage::fake('public');

    $user = User::factory()->create(['avatar_path' => 'avatars/gone.jpg']);
    // Deliberately never written to the fake disk — simulates a file lost
    // outside the app (manual deletion, storage reset, etc.) while the DB
    // column still references it.

    expect($user->photo_url)->toBeNull();
});

test('photo_url returns a real url when avatar_path points to a file that actually exists', function () {
    Storage::fake('public');

    $user = User::factory()->create(['avatar_path' => 'avatars/present.jpg']);
    Storage::disk('public')->put('avatars/present.jpg', 'binary');

    expect($user->photo_url)->not->toBeNull()
        ->and($user->photo_url)->toContain('avatars/present.jpg');
});

test('photo_url is null when the user has no avatar_path at all', function () {
    $user = User::factory()->create(['avatar_path' => null]);

    expect($user->photo_url)->toBeNull();
});

test('a user whose avatar file is missing shows the initials placeholder on the edit page, not a broken image', function () {
    Storage::fake('public');

    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $target = User::factory()->create(['role' => User::ROLE_GUEST, 'avatar_path' => 'avatars/gone.jpg']);

    $response = $this->actingAs($admin)->get(route('users.edit', $target));

    $response->assertOk();
    // previewUrl is seeded from photo_url, which must be null here — the
    // Blade @js() dump should show `null`, not a stale /storage/... URL.
    $response->assertSee('previewUrl: null', false);
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
