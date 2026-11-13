<?php

use App\Livewire\Settings\Profile;
use App\Livewire\Settings\Security;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Route::livewire('settings/profile', Profile::class)->name('profile.edit');
});

Route::middleware(['auth', 'verified'])->group(function () {
    // The Appearance screen is off until dark mode is actually built: its only
    // control is a light/dark switch, and the app layout renders one theme, so
    // the switch would change nothing. App\Livewire\Settings\Appearance and its
    // view are kept, so turning this route back on is a one-line change.

    Route::livewire('settings/security', Security::class)
        ->middleware([
            'password.confirm',
        ])
        ->name('security.edit');
});
