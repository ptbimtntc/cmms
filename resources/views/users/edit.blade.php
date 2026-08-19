@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-4xl">
    <div class="rounded-3xl border border-slate-200 bg-white shadow-sm">

        <div class="border-b border-slate-200 px-6 py-5">
            <h1 class="text-2xl font-semibold text-slate-800">
                Edit User
            </h1>

            <p class="mt-1 text-sm text-slate-500">
                Update user account, role, or password.
            </p>
        </div>

        <form
            action="{{ route('users.update', $user) }}"
            method="POST"
            enctype="multipart/form-data"
            class="space-y-5 p-6"
        >
            @csrf
            @method('PUT')

            <div>
                <label class="mb-2 block text-sm font-medium text-slate-700">
                    Profile Photo
                </label>

                <div class="flex items-center gap-4">
                    @if ($user->avatar_path)
                        <img
                            src="{{ $user->photo_url }}"
                            alt="{{ $user->name }}"
                            class="h-16 w-16 rounded-full object-cover"
                        >
                    @else
                        <div class="flex h-16 w-16 items-center justify-center rounded-full bg-slate-200 text-sm font-semibold text-slate-600">
                            {{ $user->initials() }}
                        </div>
                    @endif

                    <div>
                        <input
                            type="file"
                            name="avatar"
                            accept="image/*"
                            class="block text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-slate-700 hover:file:bg-slate-200"
                        >

                        <p class="mt-1 text-xs text-slate-500">
                            JPG or PNG, max 2MB. Leave blank to keep current photo.
                        </p>

                        @if ($user->avatar_path)
                            <label class="mt-2 flex items-center gap-2 text-xs text-slate-500">
                                <input type="checkbox" name="remove_avatar" value="1">
                                Remove current photo
                            </label>
                        @endif
                    </div>
                </div>

                @error('avatar')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="mb-2 block text-sm font-medium text-slate-700">
                    Name
                </label>

                <input
                    type="text"
                    name="name"
                    value="{{ old('name', $user->name) }}"
                    class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-700 focus:border-blue-500 focus:outline-none"
                    required
                >

                @error('name')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="mb-2 block text-sm font-medium text-slate-700">
                    Email
                </label>

                <input
                    type="email"
                    name="email"
                    value="{{ old('email', $user->email) }}"
                    class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-700 focus:border-blue-500 focus:outline-none"
                    required
                >

                @error('email')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="mb-2 block text-sm font-medium text-slate-700">
                    New Password
                </label>

                <input
                    type="password"
                    name="password"
                    class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-700 focus:border-blue-500 focus:outline-none"
                    placeholder="Leave blank to keep current password"
                >

                <p class="mt-1 text-xs text-slate-500">
                    Leave blank if you don't want to change the password.
                </p>

                @error('password')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="mb-2 block text-sm font-medium text-slate-700">
                    Role
                </label>

                @if (auth()->id() === $user->id)

                    <input
                        type="text"
                        value="{{ $user->role }}"
                        class="w-full rounded-xl border border-slate-200 bg-slate-100 px-3 py-2.5 text-sm font-medium text-slate-500"
                        disabled
                    >

                    <input
                        type="hidden"
                        name="role"
                        value="{{ $user->role }}"
                    >

                    <p class="mt-1 text-xs text-slate-500">
                        You cannot change your own role.
                    </p>

                @else

                    <select
                        name="role"
                        class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-700 focus:border-blue-500 focus:outline-none"
                        required
                    >
                        @foreach ($roles as $role)
                            <option
                                value="{{ $role }}"
                                @selected(old('role', $user->role) === $role)
                            >
                                {{ $role }}
                            </option>
                        @endforeach
                    </select>

                @endif

                @error('role')
                    <p class="mt-1 text-xs text-red-600">
                        {{ $message }}
                    </p>
                @enderror
            </div>

            <div>
                <label
                    for="is_active"
                    class="mb-2 block text-sm font-medium text-slate-700"
                >
                    Status
                </label>

                @if (auth()->id() === $user->id)

                    <input
                        type="text"
                        value="Active"
                        class="w-full rounded-xl border border-slate-200 bg-slate-100 px-3 py-2.5 text-sm font-medium text-slate-500"
                        disabled
                    >

                    <input
                        type="hidden"
                        name="is_active"
                        value="1"
                    >

                    <p class="mt-1 text-xs text-slate-500">
                        You cannot deactivate your own account.
                    </p>

                @else

                    <select
                        id="is_active"
                        name="is_active"
                        class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-700 focus:border-blue-500 focus:outline-none"
                        required
                    >
                        <option
                            value="1"
                            @selected(old('is_active', $user->is_active) == true)
                        >
                            Active
                        </option>

                        <option
                            value="0"
                            @selected(old('is_active', $user->is_active) == false)
                        >
                            Inactive
                        </option>
                    </select>

                @endif

                @error('is_active')
                    <p class="mt-1 text-xs text-red-600">
                        {{ $message }}
                    </p>
                @enderror
            </div>

            <div class="flex gap-3 pt-2">
                <button
                    type="submit"
                    class="rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-blue-700"
                >
                    Update User
                </button>

                <a
                    href="{{ route('users.index') }}"
                    class="rounded-xl border border-slate-300 px-5 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-100"
                >
                    Cancel
                </a>
            </div>
        </form>
    </div>
</div>
@endsection