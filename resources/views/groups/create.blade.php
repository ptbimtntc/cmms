@extends('layouts.app')

@section('title', 'Add Group')

@section('content')
    @php
        $fullWidth = true;
        $contentMaxWidth = 'max-w-2xl';
    @endphp

    <x-ui.form-page title="Add Group" description="Create a new machine group." :action="route('groups.store')" :back="route('groups.index')" back-label="Kembali ke Group Master">
        <x-ui.field label="Group Name" for="gr-name" error="name">
            <input id="gr-name" type="text" name="name" value="{{ old('name') }}" placeholder="e.g. Line 1" @class(['input', 'is-invalid' => $errors->has('name')])>
        </x-ui.field>
        <x-ui.field label="Area" for="gr-area" error="area_id" hint="Used to determine which PIC (by area) may be assigned to this group's greasing schedules.">
            <select id="gr-area" name="area_id" @class(['select', 'is-invalid' => $errors->has('area_id')])>
                <option value="">No Area</option>
                @foreach ($areas as $area)
                    <option value="{{ $area->id }}" @selected((string) old('area_id') === (string) $area->id)>{{ $area->name }}</option>
                @endforeach
            </select>
        </x-ui.field>
    </x-ui.form-page>
@endsection
