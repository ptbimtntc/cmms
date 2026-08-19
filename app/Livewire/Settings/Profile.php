<?php

namespace App\Livewire\Settings;

use App\Concerns\ProfileValidationRules;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Title('Profile settings')]
class Profile extends Component
{
    use ProfileValidationRules;
    use WithFileUploads;

    public string $name = '';

    public string $email = '';

    /** @var \Livewire\Features\SupportFileUploads\TemporaryUploadedFile|null */
    public $photo = null;

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->name = Auth::user()->name;
        $this->email = Auth::user()->email;
    }

    /**
     * Update the profile information for the currently authenticated user.
     */
    public function updateProfileInformation(): void
    {
        $user = Auth::user();

        $validated = $this->validate($this->profileRules($user->id));

        $user->fill($validated);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        Flux::toast(variant: 'success', text: __('Profile updated.'));
    }

    /**
     * Upload/replace the authenticated user's profile photo.
     */
    public function updatePhoto(): void
    {
        $this->validate([
            'photo' => ['required', 'image', 'max:2048'], // max 2MB
        ]);

        $user = Auth::user();

        // Hapus foto lama supaya tidak menumpuk file yatim di storage.
        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
        }

        $path = $this->photo->store('avatars', 'public');

        $user->update(['avatar_path' => $path]);

        $this->reset('photo');

        Flux::toast(variant: 'success', text: __('Profile photo updated.'));
    }

    /**
     * Remove the authenticated user's profile photo.
     */
    public function removePhoto(): void
    {
        $user = Auth::user();

        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
            $user->update(['avatar_path' => null]);
        }

        Flux::toast(variant: 'success', text: __('Profile photo removed.'));
    }
}
