@extends('layouts.app')

@section('title', 'Add Area')

@section('content')
    @php
        $fullWidth = true;
        $contentMaxWidth = 'max-w-2xl';
    @endphp

    <x-ui.form-page title="Add Area" description="Create a new operational area (e.g. GRIPPER, PACKING). Once saved, it appears everywhere areas are selected, with no further setup needed."
        :action="route('areas.store')" :back="route('areas.index')" back-label="Kembali ke Area Management">
        <x-ui.field label="Area Name" for="ar-name" error="name" hint="The name cannot be changed after creation: machines, PM schedules, and users already assigned to an area are matched against this exact value.">
            <input id="ar-name" type="text" name="name" value="{{ old('name') }}" placeholder="e.g. GRIPPER" @class(['input', 'is-invalid' => $errors->has('name')])>
        </x-ui.field>
    </x-ui.form-page>
@endsection
