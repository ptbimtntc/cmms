@extends('layouts.app')

@section('content')

@if (session('success') || session('password_success') || $errors->any())
    <div
        x-data="{ show: true }"
        x-show="show"
        x-init="setTimeout(() => show = false, 5000)"
        x-transition
        class="fixed right-4 top-4 z-50 w-full max-w-sm rounded-xl border px-4 py-3 text-sm shadow-lg {{ $errors->any() ? 'border-red-200 bg-red-50 text-red-700' : 'border-emerald-200 bg-emerald-50 text-emerald-700' }}"
    >
        <div class="flex items-start justify-between gap-3">
            <p>
                @if (session('success'))
                    {{ session('success') }}
                @elseif (session('password_success'))
                    {{ session('password_success') }}
                @elseif ($errors->any())
                    Please fix the errors below and try again.
                @endif
            </p>
            <button type="button" @click="show = false" class="shrink-0 text-current opacity-60 hover:opacity-100">
                &times;
            </button>
        </div>
    </div>
@endif

<div class="mx-auto max-w-4xl space-y-6">

    {{-- Profile Information --}}
    <div class="rounded-3xl border border-slate-200 bg-white shadow-sm">

        <div class="border-b border-slate-200 px-6 py-5">
            <h1 class="text-2xl font-semibold text-slate-800">
                My Profile
            </h1>

            <p class="mt-1 text-sm text-slate-500">
                Manage your account information.
            </p>
        </div>

        <form action="{{ route('profile.update') }}" method="POST" class="space-y-5 p-6">
            @csrf
            @method('PUT')

            {{-- Name --}}
            <div>
                <label for="name" class="mb-2 block text-sm font-medium text-slate-700">
                    Name
                </label>

                <input id="name" type="text" name="name" value="{{ old('name', auth()->user()->name) }}"
                    class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-700 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                    required>

                @error('name')
                <p class="mt-1 text-xs text-red-600">
                    {{ $message }}
                </p>
                @enderror
            </div>

            {{-- Email --}}
            <div>
                <label for="email" class="mb-2 block text-sm font-medium text-slate-700">
                    Email
                </label>

                <input id="email" type="email" name="email" value="{{ old('email', auth()->user()->email) }}"
                    class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-700 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                    required>

                @error('email')
                <p class="mt-1 text-xs text-red-600">
                    {{ $message }}
                </p>
                @enderror
            </div>

            {{-- Role --}}
            <div>
                <label class="mb-2 block text-sm font-medium text-slate-700">
                    Role
                </label>

                <input type="text" value="{{ auth()->user()->role }}" disabled
                    class="w-full rounded-xl border border-slate-200 bg-slate-100 px-3 py-2.5 text-sm font-medium text-slate-500">

                <p class="mt-1 text-xs text-slate-500">
                    Your role can only be changed by an administrator.
                </p>
            </div>

            <div class="flex justify-end border-t border-slate-200 pt-5">
                <button type="submit"
                    class="rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-medium text-white transition hover:bg-blue-700">
                    Save Changes
                </button>
            </div>

        </form>
    </div>


    {{-- Change Password --}}
    <div class="rounded-3xl border border-slate-200 bg-white shadow-sm">

        <div class="border-b border-slate-200 px-6 py-5">
            <h2 class="text-xl font-semibold text-slate-800">
                Change Password
            </h2>

            <p class="mt-1 text-sm text-slate-500">
                Make sure your new password is at least 8 characters long.
            </p>
        </div>

        <form action="{{ route('profile.password.update') }}" method="POST" class="space-y-5 p-6">
            @csrf
            @method('PUT')

            {{-- Current Password --}}
            <div>
                <label for="current_password" class="mb-2 block text-sm font-medium text-slate-700">
                    Current Password
                </label>

                <input id="current_password" type="password" name="current_password"
                    class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-700 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                    required>

                @error('current_password')
                <p class="mt-1 text-xs text-red-600">
                    {{ $message }}
                </p>
                @enderror
            </div>

            {{-- New Password --}}
            <div>
                <label for="password" class="mb-2 block text-sm font-medium text-slate-700">
                    New Password
                </label>

                <input id="password" type="password" name="password"
                    class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-700 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                    required>

                @error('password')
                <p class="mt-1 text-xs text-red-600">
                    {{ $message }}
                </p>
                @enderror
            </div>

            {{-- Confirm Password --}}
            <div>
                <label for="password_confirmation" class="mb-2 block text-sm font-medium text-slate-700">
                    Confirm New Password
                </label>

                <input id="password_confirmation" type="password" name="password_confirmation"
                    class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-700 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                    required>
            </div>

            <div class="flex justify-end border-t border-slate-200 pt-5">
                <button type="submit"
                    class="rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-medium text-white transition hover:bg-blue-700">
                    Change Password
                </button>
            </div>

        </form>
    </div>

</div>

@endsection