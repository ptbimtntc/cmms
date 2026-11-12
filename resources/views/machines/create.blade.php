@extends('layouts.app')

@section('title', 'Add Machine')

@section('content')
    @php
        $fullWidth = true;
        $contentMaxWidth = 'max-w-3xl';
    @endphp

    <div class="space-y-5">
        <x-ui.button variant="ghost" href="{{ route('machines.index') }}" class="-ml-2">Kembali ke Machine Master</x-ui.button>
        <x-ui.page-header title="Add Machine" description="Create a new machine master entry." />

        <form action="{{ route('machines.store') }}" method="POST" class="card space-y-5 p-5 sm:p-6">
            @csrf
            <div class="grid gap-5 md:grid-cols-2">
                <x-ui.field label="Machine Number" for="mc-number" error="machine_number">
                    <input id="mc-number" type="text" name="machine_number" value="{{ old('machine_number') }}" @class(['input', 'is-invalid' => $errors->has('machine_number')])>
                </x-ui.field>
                <x-ui.field label="Machine Type" for="mc-type" error="machine_type">
                    <input id="mc-type" type="text" name="machine_type" value="{{ old('machine_type') }}" @class(['input', 'is-invalid' => $errors->has('machine_type')])>
                </x-ui.field>
                <x-ui.field label="Area" for="mc-area" error="area">
                    <select id="mc-area" name="area" @class(['select', 'is-invalid' => $errors->has('area')])>
                        <option value="">Select Area</option>
                        @foreach ($areas as $areaOption)
                            <option value="{{ $areaOption->name }}" @selected(old('area') === $areaOption->name)>{{ $areaOption->name }}</option>
                        @endforeach
                    </select>
                </x-ui.field>
                <x-ui.field label="Status" for="mc-status" error="status">
                    <select id="mc-status" name="status" @class(['select', 'is-invalid' => $errors->has('status')])>
                        <option value="ACTIVE" @selected(old('status') == 'ACTIVE')>ACTIVE</option>
                        <option value="INACTIVE" @selected(old('status') == 'INACTIVE')>INACTIVE</option>
                    </select>
                </x-ui.field>
                <x-ui.field label="Group" for="mc-group" error="group_id">
                    <select id="mc-group" name="group_id" @class(['select', 'is-invalid' => $errors->has('group_id')])>
                        <option value="">-- No Group --</option>
                        @foreach ($groups as $group)
                            <option value="{{ $group->id }}" @selected((string) old('group_id') === (string) $group->id)>{{ $group->name }}</option>
                        @endforeach
                    </select>
                </x-ui.field>
                <x-ui.field label="Description" for="mc-desc" error="description" class="md:col-span-2">
                    <input id="mc-desc" type="text" name="description" value="{{ old('description') }}" @class(['input', 'is-invalid' => $errors->has('description')])>
                </x-ui.field>
            </div>

            <div class="flex flex-col-reverse gap-2 border-t border-border pt-5 sm:flex-row sm:justify-end">
                <x-ui.button variant="ghost" href="{{ route('machines.index') }}">Cancel</x-ui.button>
                <x-ui.button type="submit">Save</x-ui.button>
            </div>
        </form>
    </div>
@endsection
