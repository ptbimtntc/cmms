@extends('layouts.app')

@section('title', 'My Profile')

@section('content')
    @php
        $fullWidth = true;
        $contentMaxWidth = 'max-w-3xl';
    @endphp

    <div class="space-y-5">
        <x-ui.page-header title="My Profile" description="Manage your account information." />

        @if (session('success'))
            <x-ui.alert tone="success">{{ session('success') }}</x-ui.alert>
        @elseif (session('password_success'))
            <x-ui.alert tone="success">{{ session('password_success') }}</x-ui.alert>
        @elseif ($errors->any())
            <x-ui.alert tone="danger">Please fix the errors below and try again.</x-ui.alert>
        @endif

        <form action="{{ route('profile.update') }}" method="POST" class="card space-y-5 p-5 sm:p-6">
            @csrf
            @method('PUT')
            <h2 class="text-lg font-semibold text-text">Account</h2>

            <div class="grid gap-5 md:grid-cols-2">
                <x-ui.field label="Name" for="name" error="name">
                    <input id="name" type="text" name="name" value="{{ old('name', auth()->user()->name) }}" required @class(['input', 'is-invalid' => $errors->has('name')])>
                </x-ui.field>
                <x-ui.field label="Email" for="email" error="email">
                    <input id="email" type="email" name="email" value="{{ old('email', auth()->user()->email) }}" required @class(['input', 'is-invalid' => $errors->has('email')])>
                </x-ui.field>
            </div>
            <x-ui.field label="Role" for="role-ro" hint="Your role can only be changed by an administrator.">
                <input id="role-ro" type="text" value="{{ auth()->user()->role }}" disabled class="input">
            </x-ui.field>

            <div class="flex justify-end border-t border-border pt-5">
                <x-ui.button type="submit">Save Changes</x-ui.button>
            </div>
        </form>

        <form action="{{ route('profile.password.update') }}" method="POST" class="card space-y-5 p-5 sm:p-6">
            @csrf
            @method('PUT')
            <div>
                <h2 class="text-lg font-semibold text-text">Change Password</h2>
                <p class="mt-1 text-sm text-text-muted">Make sure your new password is at least 8 characters long.</p>
            </div>

            <x-ui.field label="Current Password" for="current_password" error="current_password">
                <input id="current_password" type="password" name="current_password" required autocomplete="current-password" @class(['input', 'is-invalid' => $errors->has('current_password')])>
            </x-ui.field>
            <div class="grid gap-5 md:grid-cols-2">
                <x-ui.field label="New Password" for="password" error="password">
                    <input id="password" type="password" name="password" required autocomplete="new-password" @class(['input', 'is-invalid' => $errors->has('password')])>
                </x-ui.field>
                <x-ui.field label="Confirm New Password" for="password_confirmation">
                    <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password" class="input">
                </x-ui.field>
            </div>

            <div class="flex justify-end border-t border-border pt-5">
                <x-ui.button type="submit">Change Password</x-ui.button>
            </div>
        </form>
    </div>
@endsection
