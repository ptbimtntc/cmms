@extends('layouts.app')

@section('title', 'Add User')

@section('content')
    @php
        $fullWidth = true;
        $contentMaxWidth = 'max-w-3xl';
    @endphp

    <x-ui.form-page title="Add User" description="Create a new user account and assign its role."
        :action="route('users.store')" :back="route('users.index')" back-label="Kembali ke User Management" submit="Create User">
        <div class="grid gap-5 md:grid-cols-2">
            <x-ui.field label="Name" for="name" error="name">
                <input id="name" type="text" name="name" value="{{ old('name') }}" placeholder="Enter full name" required autofocus @class(['input', 'is-invalid' => $errors->has('name')])>
            </x-ui.field>
            <x-ui.field label="Email" for="email" error="email">
                <input id="email" type="email" name="email" value="{{ old('email') }}" placeholder="user@example.com" required @class(['input', 'is-invalid' => $errors->has('email')])>
            </x-ui.field>
        </div>

        <x-ui.field label="Password" for="password" error="password" hint="Minimum 8 characters. The user can change it later from their profile.">
            <input id="password" type="password" name="password" required autocomplete="new-password" @class(['input', 'is-invalid' => $errors->has('password')])>
        </x-ui.field>

        <x-ui.field label="Role" for="role" error="role">
            <select id="role" name="role" onchange="toggleAreaField(this.value)" required @class(['select', 'is-invalid' => $errors->has('role')])>
                <option value="">Select Role</option>
                @foreach ($roles as $role)
                    <option value="{{ $role }}" @selected(old('role') === $role)>{{ $role }}</option>
                @endforeach
            </select>
        </x-ui.field>

        {{-- Area: only meaningful for KOORDINATOR/PIC; ADMIN sees every area, GUEST has none,
             SUPERVISOR uses the multi-select below. Options come from the Area master list. --}}
        <x-ui.field label="Area" for="area_id" error="area_id" id="areaField">
            <select id="area_id" name="area_id" @class(['select', 'is-invalid' => $errors->has('area_id')])>
                <option value="">Select Area</option>
                @foreach ($areas as $area)
                    <option value="{{ $area->id }}" @selected((string) old('area_id') === (string) $area->id)>{{ $area->name }}</option>
                @endforeach
            </select>
        </x-ui.field>

        {{-- Areas: SUPERVISOR only. None checked = all areas, otherwise limited to the checked areas. --}}
        <fieldset id="supervisorAreasField" style="display: none" class="grid gap-1.5">
            <legend class="form-label mb-1.5">Areas <span class="font-normal text-text-muted">(leave empty for all areas)</span></legend>
            <div class="flex flex-wrap gap-x-5 gap-y-1 rounded-control border border-border bg-surface-sunken px-3 py-1">
                @foreach ($areas as $area)
                    <label class="inline-flex min-h-11 items-center gap-2 text-sm text-text">
                        <input type="checkbox" name="area_ids[]" value="{{ $area->id }}" class="h-4 w-4 rounded border-border-strong text-primary"
                            @checked(in_array($area->id, array_map('intval', (array) old('area_ids', []))))>
                        {{ $area->name }}
                    </label>
                @endforeach
            </div>
            @error('area_ids.*')
                <p class="form-error">{{ $message }}</p>
            @enderror
        </fieldset>
    </x-ui.form-page>

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
@endsection
