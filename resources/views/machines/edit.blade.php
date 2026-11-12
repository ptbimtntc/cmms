@extends('layouts.app')

@section('title', 'Edit Machine')

@section('content')
    @php
        $fullWidth = true;
        $contentMaxWidth = 'max-w-3xl';
    @endphp

    <div class="space-y-5">
        <x-ui.button variant="ghost" href="{{ route('machines.index') }}" class="-ml-2">Kembali ke Machine Master</x-ui.button>
        <x-ui.page-header title="Edit Machine" description="Update machine details and maintenance cycle settings." />

        <form action="{{ route('machines.update', $machine) }}" method="POST" class="card space-y-5 p-5 sm:p-6">
            @csrf
            @method('PUT')
            <input type="hidden" name="return_page" value="{{ request('page') }}">

            <div class="grid gap-5 md:grid-cols-2">
                <x-ui.field label="Machine Number" for="me-number" error="machine_number">
                    <input id="me-number" type="text" name="machine_number" value="{{ old('machine_number', $machine->machine_number) }}" @class(['input', 'is-invalid' => $errors->has('machine_number')])>
                </x-ui.field>
                <x-ui.field label="Machine Type" for="me-type" error="machine_type">
                    <input id="me-type" type="text" name="machine_type" value="{{ old('machine_type', $machine->machine_type) }}" @class(['input', 'is-invalid' => $errors->has('machine_type')])>
                </x-ui.field>
                <x-ui.field label="Area" for="me-area" error="area">
                    <select id="me-area" name="area" @class(['select', 'is-invalid' => $errors->has('area')])>
                        @foreach ($areas as $areaOption)
                            <option value="{{ $areaOption->name }}" @selected(old('area', $machine->area) === $areaOption->name)>{{ $areaOption->name }}</option>
                        @endforeach
                        @unless ($areas->contains('name', $machine->area))
                            {{-- The machine's current area was since deactivated (or is otherwise not in the
                                 active list): keep it selectable so saving other fields doesn't force an area change. --}}
                            <option value="{{ $machine->area }}" selected>{{ $machine->area }} (inactive)</option>
                        @endunless
                    </select>
                </x-ui.field>
                <x-ui.field label="Status" for="me-status" error="status">
                    <select id="me-status" name="status" class="select">
                        <option value="ACTIVE" @selected($machine->status == 'ACTIVE')>ACTIVE</option>
                        <option value="INACTIVE" @selected($machine->status == 'INACTIVE')>INACTIVE</option>
                    </select>
                </x-ui.field>
                <x-ui.field label="Group" for="me-group" error="group_id">
                    <select id="me-group" name="group_id" class="select">
                        <option value="">-- No Group --</option>
                        @foreach ($groups as $group)
                            <option value="{{ $group->id }}" @selected(old('group_id', $machine->group_id) == $group->id)>{{ $group->name }}</option>
                        @endforeach
                    </select>
                </x-ui.field>
            </div>

            <fieldset class="rounded-control border border-border bg-surface-sunken p-4 sm:p-5">
                <legend class="px-1 text-sm font-semibold text-text">PM Cycle</legend>
                <div class="grid gap-5 md:grid-cols-2">
                    <x-ui.field label="Cycle Value" for="me-cycle-value" error="pm_cycle_value">
                        <input id="me-cycle-value" type="number" min="1" name="pm_cycle_value" value="{{ old('pm_cycle_value', $machine->pm_cycle_value) }}" class="input">
                    </x-ui.field>
                    <x-ui.field label="Cycle Unit" for="me-cycle-unit" error="pm_cycle_unit">
                        <select id="me-cycle-unit" name="pm_cycle_unit" class="select">
                            <option value="">-- Select --</option>
                            @foreach (['DAY' => 'Day', 'WEEK' => 'Week', 'MONTH' => 'Month', 'HOUR' => 'Hour'] as $value => $text)
                                <option value="{{ $value }}" @selected($machine->pm_cycle_unit == $value)>{{ $text }}</option>
                            @endforeach
                        </select>
                    </x-ui.field>
                </div>
            </fieldset>

            <div class="flex flex-col-reverse gap-2 border-t border-border pt-5 sm:flex-row sm:justify-end">
                <x-ui.button variant="ghost" href="{{ route('machines.index') }}">Cancel</x-ui.button>
                <x-ui.button type="submit">Save Changes</x-ui.button>
            </div>
        </form>
    </div>
@endsection
