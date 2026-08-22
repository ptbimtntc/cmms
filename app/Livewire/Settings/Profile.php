<?php

namespace App\Livewire\Settings;

use App\Concerns\ProfileValidationRules;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
     * Triggered as soon as a photo is chosen. Instead of uploading it as-is,
     * we hand the temporary preview URL to the browser so it can open the
     * crop/position modal before anything is saved.
     */
    public function updatedPhoto(): void
    {
        $this->validate([
            'photo' => ['required', 'image', 'max:2048'], // max 2MB
        ]);

        $this->dispatch('photo-selected', url: $this->photo->temporaryUrl());
    }

    /**
     * Discard the chosen photo without saving (crop modal cancelled).
     */
    public function cancelPhotoCrop(): void
    {
        $this->reset('photo');
        $this->resetErrorBag('photo');
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
     * Save the cropped/positioned profile photo produced by the browser-side
     * canvas editor. $dataUrl is a base64 "data:image/jpeg;base64,..." string
     * representing exactly what the user framed inside the circle.
     */
    public function saveCroppedPhoto(string $dataUrl): void
    {
        if (! preg_match('/^data:image\/(png|jpe?g|webp);base64,([a-zA-Z0-9+\/=]+)$/', $dataUrl, $matches)) {
            $this->addError('photo', __('The uploaded photo is invalid.'));

            return;
        }

        $binary = base64_decode($matches[2], true);

        if ($binary === false || $binary === '') {
            $this->addError('photo', __('The uploaded photo is invalid.'));

            return;
        }

        // 2MB ceiling, matching the original file-size rule.
        if (strlen($binary) > 2048 * 1024) {
            $this->addError('photo', __('The photo may not be larger than 2MB.'));

            return;
        }

        $user = Auth::user();

        // Hapus foto lama supaya tidak menumpuk file yatim di storage.
        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
        }

        $path = 'avatars/'.Str::uuid().'.jpg';

        Storage::disk('public')->put($path, $binary);

        $user->update(['avatar_path' => $path]);

        $this->reset('photo');

        $this->modal('crop-photo')->close();

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
