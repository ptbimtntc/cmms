<?php

use App\Livewire\Settings\Profile;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

function fakeCroppedPhotoDataUrl(): string
{
    $image = imagecreatetruecolor(4, 4);
    imagefill($image, 0, 0, imagecolorallocate($image, 10, 20, 30));

    ob_start();
    imagejpeg($image);
    $binary = ob_get_clean();
    imagedestroy($image);

    return 'data:image/jpeg;base64,'.base64_encode($binary);
}

test('profile page is displayed', function () {
    $this->actingAs($user = User::factory()->create());

    $this->get('/settings/profile')->assertOk();
});

test('profile information can be updated', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = Livewire::test(Profile::class)
        ->set('name', 'Test User')
        ->set('email', 'test@example.com')
        ->call('updateProfileInformation');

    $response->assertHasNoErrors();

    $user->refresh();

    expect($user->name)->toEqual('Test User');
    expect($user->email)->toEqual('test@example.com');
    expect($user->email_verified_at)->toBeNull();
});

test('email verification status is unchanged when email address is unchanged', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = Livewire::test(Profile::class)
        ->set('name', 'Test User')
        ->set('email', $user->email)
        ->call('updateProfileInformation');

    $response->assertHasNoErrors();

    expect($user->refresh()->email_verified_at)->not->toBeNull();
});

test('user can delete their account', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = Livewire::test('settings.delete-user-form')
        ->set('password', 'password')
        ->call('deleteUser');

    $response
        ->assertHasNoErrors()
        ->assertRedirect('/');

    expect($user->fresh())->toBeNull();
    expect(auth()->check())->toBeFalse();
});

test('choosing a photo dispatches an event to open the crop modal', function () {
    Storage::fake('public');

    $user = User::factory()->create();

    $this->actingAs($user);

    $photo = \Illuminate\Http\UploadedFile::fake()->image('avatar.jpg');

    Livewire::test(Profile::class)
        ->set('photo', $photo)
        ->assertHasNoErrors('photo')
        ->assertDispatched('photo-selected');
});

test('cropped photo can be saved and replaces the previous avatar', function () {
    Storage::fake('public');

    $user = User::factory()->create([
        'avatar_path' => 'avatars/old-avatar.jpg',
    ]);
    Storage::disk('public')->put('avatars/old-avatar.jpg', 'old-binary');

    $this->actingAs($user);

    $response = Livewire::test(Profile::class)
        ->call('saveCroppedPhoto', fakeCroppedPhotoDataUrl());

    $response->assertHasNoErrors();

    $user->refresh();

    expect($user->avatar_path)->not->toBeNull();
    expect($user->avatar_path)->not->toEqual('avatars/old-avatar.jpg');

    Storage::disk('public')->assertExists($user->avatar_path);
    Storage::disk('public')->assertMissing('avatars/old-avatar.jpg');
});

test('invalid cropped photo payload is rejected', function () {
    Storage::fake('public');

    $user = User::factory()->create();

    $this->actingAs($user);

    $response = Livewire::test(Profile::class)
        ->call('saveCroppedPhoto', 'not-a-valid-data-url');

    $response->assertHasErrors('photo');

    expect($user->fresh()->avatar_path)->toBeNull();
});

test('cancelling the crop modal discards the chosen photo', function () {
    Storage::fake('public');

    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test(Profile::class)
        ->set('photo', \Illuminate\Http\UploadedFile::fake()->image('avatar.jpg'))
        ->call('cancelPhotoCrop')
        ->assertSet('photo', null);
});

test('correct password must be provided to delete account', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = Livewire::test('settings.delete-user-form')
        ->set('password', 'wrong-password')
        ->call('deleteUser');

    $response->assertHasErrors(['password']);

    expect($user->fresh())->not->toBeNull();
});