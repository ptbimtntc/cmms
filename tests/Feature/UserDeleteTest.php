<?php

use App\Models\User;
use Illuminate\Support\Facades\Storage;

test('admin can delete another user', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $target = User::factory()->create(['role' => User::ROLE_GUEST]);

    $this->actingAs($admin);

    $response = $this->delete(route('users.destroy', $target));

    $response->assertRedirect(route('users.index'));

    expect(User::find($target->id))->toBeNull();
});

test('deleting a user also removes their avatar file', function () {
    Storage::fake('public');

    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $target = User::factory()->create([
        'role' => User::ROLE_GUEST,
        'avatar_path' => 'avatars/to-be-deleted.jpg',
    ]);
    Storage::disk('public')->put('avatars/to-be-deleted.jpg', 'binary');

    $this->actingAs($admin);

    $this->delete(route('users.destroy', $target));

    Storage::disk('public')->assertMissing('avatars/to-be-deleted.jpg');
});

test('admin cannot delete their own account', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $this->actingAs($admin);

    $response = $this->delete(route('users.destroy', $admin));

    $response->assertSessionHasErrors('delete');

    expect(User::find($admin->id))->not->toBeNull();
});

test('non-admin cannot delete users', function () {
    $guest = User::factory()->create(['role' => User::ROLE_GUEST]);
    $target = User::factory()->create(['role' => User::ROLE_GUEST]);

    $this->actingAs($guest);

    $response = $this->delete(route('users.destroy', $target));

    $response->assertForbidden();

    expect(User::find($target->id))->not->toBeNull();
});

test('users index page shows delete button next to edit, except for the current admin', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $other = User::factory()->create(['role' => User::ROLE_GUEST]);

    $this->actingAs($admin);

    $response = $this->get(route('users.index'));

    $response->assertOk();
    $response->assertSee(route('users.destroy', $other), false);
});
