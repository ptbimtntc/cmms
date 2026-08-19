<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading class="sr-only">{{ __('Profile settings') }}</flux:heading>

    <x-settings.layout :heading="__('Profile')" :subheading="__('Update your name and email address')">

        {{-- Foto profil --}}
        <div class="my-6 flex items-center gap-4">
            <flux:avatar
                size="xl"
                :src="auth()->user()->photo_url"
                :name="auth()->user()->name"
                :initials="auth()->user()->initials()"
            />

            <div class="flex flex-col gap-2">
                <form wire:submit="updatePhoto" class="flex items-center gap-2">
                    <input type="file" wire:model="photo" accept="image/*" />
                    <flux:button type="submit" size="sm" variant="primary">{{ __('Upload Photo') }}</flux:button>
                </form>

                <div wire:loading wire:target="photo">{{ __('Uploading...') }}</div>

                @error('photo')
                    <flux:text class="text-red-600">{{ $message }}</flux:text>
                @enderror

                @if (auth()->user()->avatar_path)
                    <flux:button wire:click="removePhoto" size="sm" variant="ghost">
                        {{ __('Remove Photo') }}
                    </flux:button>
                @endif
            </div>
        </div>

        <form wire:submit="updateProfileInformation" class="my-6 w-full space-y-6">
            <flux:input wire:model="name" :label="__('Name')" type="text" required autofocus autocomplete="name" />

            <div>
                <flux:input wire:model="email" :label="__('Email')" type="email" required autocomplete="email" />

            </div>

            <div class="flex items-center gap-4">
                <flux:button variant="primary" type="submit">{{ __('Save') }}</flux:button>
            </div>
        </form>

            <livewire:settings.delete-user-form />
    </x-settings.layout>
</section>
