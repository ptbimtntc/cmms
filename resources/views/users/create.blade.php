@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-4xl">

    <div class="rounded-3xl border border-slate-200 bg-white shadow-sm">

        {{-- Header --}}
        <div class="border-b border-slate-200 px-6 py-5">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold text-slate-800">
                        Add User
                    </h1>

                    <p class="mt-1 text-sm text-slate-500">
                        Create a new user account and assign its role.
                    </p>
                </div>

                <a
                    href="{{ route('users.index') }}"
                    class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-100"
                >
                    Back
                </a>
            </div>
        </div>

        {{-- Form --}}
        <form
            action="{{ route('users.store') }}"
            method="POST"
            class="space-y-5 p-6"
        >
            @csrf

            {{-- Name --}}
            <div>
                <label
                    for="name"
                    class="mb-2 block text-sm font-medium text-slate-700"
                >
                    Name
                </label>

                <input
                    id="name"
                    type="text"
                    name="name"
                    value="{{ old('name') }}"
                    class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-700 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                    placeholder="Enter full name"
                    required
                    autofocus
                >

                @error('name')
                    <p class="mt-1 text-xs text-red-600">
                        {{ $message }}
                    </p>
                @enderror
            </div>

            {{-- Email --}}
            <div>
                <label
                    for="email"
                    class="mb-2 block text-sm font-medium text-slate-700"
                >
                    Email
                </label>

                <input
                    id="email"
                    type="email"
                    name="email"
                    value="{{ old('email') }}"
                    class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-700 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                    placeholder="user@example.com"
                    required
                >

                @error('email')
                    <p class="mt-1 text-xs text-red-600">
                        {{ $message }}
                    </p>
                @enderror
            </div>

            {{-- Password --}}
            <div>
                <label
                    for="password"
                    class="mb-2 block text-sm font-medium text-slate-700"
                >
                    Password
                </label>

                <input
                    id="password"
                    type="password"
                    name="password"
                    class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-700 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                    placeholder="Minimum 8 characters"
                    required
                >

                @error('password')
                    <p class="mt-1 text-xs text-red-600">
                        {{ $message }}
                    </p>
                @enderror

                <p class="mt-1 text-xs text-slate-500">
                    The user can change their password later if that feature is enabled.
                </p>
            </div>

            {{-- Role --}}
            <div>
                <label
                    for="role"
                    class="mb-2 block text-sm font-medium text-slate-700"
                >
                    Role
                </label>

                <select
                    id="role"
                    name="role"
                    onchange="toggleAreaField(this.value)"
                    class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-700 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                    required
                >
                    <option value="">Select Role</option>

                    @foreach ($roles as $role)
                        <option
                            value="{{ $role }}"
                            @selected(old('role') === $role)
                        >
                            {{ $role }}
                        </option>
                    @endforeach
                </select>

                @error('role')
                    <p class="mt-1 text-xs text-red-600">
                        {{ $message }}
                    </p>
                @enderror
            </div>

            {{-- Area — only meaningful for KOORDINATOR/PIC; ADMIN implicitly
                 accesses every area, GUEST has no area, SUPERVISOR uses
                 the multi-select below. Options come from
                 the Area master list (see Area Management), never
                 hardcoded, so a newly added area shows up here immediately. --}}
            <div id="areaField">
                <label
                    for="area_id"
                    class="mb-2 block text-sm font-medium text-slate-700"
                >
                    Area
                </label>

                <select
                    id="area_id"
                    name="area_id"
                    class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-700 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                >
                    <option value="">Select Area</option>

                    @foreach ($areas as $area)
                        <option
                            value="{{ $area->id }}"
                            @selected((string) old('area_id') === (string) $area->id)
                        >
                            {{ $area->name }}
                        </option>
                    @endforeach
                </select>

                @error('area_id')
                    <p class="mt-1 text-xs text-red-600">
                        {{ $message }}
                    </p>
                @enderror
            </div>

            {{-- Areas — SUPERVISOR only: none checked = all areas, otherwise
                 limited to the checked areas. --}}
            <div id="supervisorAreasField" style="display: none">
                <label class="mb-2 block text-sm font-medium text-slate-700">
                    Areas <span class="font-normal text-slate-500">(leave empty for all areas)</span>
                </label>

                <div class="flex flex-wrap gap-4 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5">
                    @foreach ($areas as $area)
                        <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                            <input
                                type="checkbox"
                                name="area_ids[]"
                                value="{{ $area->id }}"
                                @checked(in_array($area->id, array_map('intval', (array) old('area_ids', []))))
                            >
                            {{ $area->name }}
                        </label>
                    @endforeach
                </div>

                @error('area_ids.*')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <script>
                function toggleAreaField(role) {
                    const field = document.getElementById('areaField');
                    const needsArea = role === 'KOORDINATOR' || role === 'PIC';
                    field.style.display = needsArea ? '' : 'none';
                    document.getElementById('area_id').required = needsArea;
                    document.getElementById('supervisorAreasField').style.display = role === 'SUPERVISOR' ? '' : 'none';
                }
                toggleAreaField(document.getElementById('role').value);
            </script>

            {{-- Actions --}}
            <div class="flex flex-wrap items-center gap-3 border-t border-slate-200 pt-5">

                <button
                    type="submit"
                    class="rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-medium text-white transition hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                >
                    Create User
                </button>

                <a
                    href="{{ route('users.index') }}"
                    class="rounded-xl border border-slate-300 px-5 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-slate-100"
                >
                    Cancel
                </a>

            </div>

        </form>
    </div>
</div>
@endsection