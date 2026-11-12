@extends('layouts.app')

@section('title', 'Add Machine Type')

@section('content')
    @php
        $fullWidth = true;
        $contentMaxWidth = 'max-w-2xl';
    @endphp

    <x-ui.form-page title="Add Machine Type" description="Machine types are pulled from master Machine data. Types already configured here are left out of the list below."
        :action="route('machine-maintenance-requirements.store')" :back="route('machine-maintenance-requirements.index')" back-label="Kembali ke Oil Change Requirements">
        <x-ui.field label="Machine Type" for="mmr-type" error="machine_type" :hint="empty($machineTypes) ? 'All machine types from master data are already configured.' : null">
            <select id="mmr-type" name="machine_type" @class(['select', 'is-invalid' => $errors->has('machine_type')])>
                <option value="">-- Select Machine Type --</option>
                @foreach ($machineTypes as $type)
                    <option value="{{ $type }}" @selected(old('machine_type') == $type)>{{ $type }}</option>
                @endforeach
            </select>
        </x-ui.field>
        <x-ui.field label="Requires Oil Change" for="mmr-oil" error="requires_oil_change">
            <select id="mmr-oil" name="requires_oil_change" @class(['select', 'is-invalid' => $errors->has('requires_oil_change')])>
                <option value="1" @selected(old('requires_oil_change') == '1')>YES</option>
                <option value="0" @selected(old('requires_oil_change') == '0')>NO</option>
            </select>
        </x-ui.field>
    </x-ui.form-page>
@endsection
