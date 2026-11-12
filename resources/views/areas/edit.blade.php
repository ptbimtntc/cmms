@extends('layouts.app')

@section('title', 'Edit Area')

@section('content')
    @php
        $fullWidth = true;
        $contentMaxWidth = 'max-w-2xl';
    @endphp

    <x-ui.form-page title="Edit Area" description="The name cannot be changed here, only whether the area is active. Deactivating an area hides it from new selections (User Management, Machines, dashboards) without touching existing data that references it."
        :action="route('areas.update', $area)" method="PUT" :back="route('areas.index')" back-label="Kembali ke Area Management">
        <x-ui.field label="Area Name" for="ar-name-ro">
            <input id="ar-name-ro" type="text" value="{{ $area->name }}" disabled class="input">
        </x-ui.field>
        <x-ui.field label="Status" for="ar-active" error="is_active">
            <select id="ar-active" name="is_active" @class(['select', 'is-invalid' => $errors->has('is_active')])>
                <option value="1" @selected(old('is_active', $area->is_active) == 1)>Active</option>
                <option value="0" @selected(old('is_active', $area->is_active) == 0)>Inactive</option>
            </select>
        </x-ui.field>
    </x-ui.form-page>
@endsection
