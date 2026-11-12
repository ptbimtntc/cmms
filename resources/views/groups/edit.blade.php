@extends('layouts.app')

@section('title', 'Edit Group')

@section('content')
    @php
        $fullWidth = true;
        $contentMaxWidth = 'max-w-3xl';
        $checked = old('machine_ids', $machines->where('group_id', $group->id)->pluck('id')->all());
    @endphp

    <x-ui.form-page title="Edit Group" description="Update group details and manage which machines belong to this group."
        :action="route('groups.update', $group)" method="PUT" :back="route('groups.index')" back-label="Kembali ke Group Master" submit="Save Changes">
        <input type="hidden" name="return_page" value="{{ request('page') }}">

        <x-ui.field label="Group Name" for="ge-name" error="name">
            <input id="ge-name" type="text" name="name" value="{{ old('name', $group->name) }}" @class(['input', 'is-invalid' => $errors->has('name')])>
        </x-ui.field>
        <x-ui.field label="Area" for="ge-area" error="area_id" hint="Used to determine which PIC (by area) may be assigned to this group's greasing schedules.">
            <select id="ge-area" name="area_id" @class(['select', 'is-invalid' => $errors->has('area_id')])>
                <option value="">No Area</option>
                @foreach ($areas as $area)
                    <option value="{{ $area->id }}" @selected((string) old('area_id', $group->area_id) === (string) $area->id)>{{ $area->name }}</option>
                @endforeach
            </select>
        </x-ui.field>

        <fieldset class="rounded-control border border-border bg-surface-sunken p-4 sm:p-5">
            <legend class="sr-only">Machines in this Group</legend>
            <div class="mb-3 flex items-start justify-between gap-3">
                <div>
                    <p class="text-sm font-semibold text-text">Machines in this Group</p>
                    <p class="mt-0.5 text-xs text-text-muted">Number of Machines is calculated from what you check here; it is not a separate field.</p>
                </div>
                <x-ui.badge id="machineSelectedCount" class="shrink-0" aria-live="polite">{{ $machines->where('group_id', $group->id)->count() }} selected</x-ui.badge>
            </div>

            <label for="machineSearchInput" class="sr-only">Search machine</label>
            <input type="text" id="machineSearchInput" placeholder="Search machine number or type" class="input mb-3">

            <div class="max-h-72 space-y-0.5 overflow-y-auto rounded-control border border-border bg-surface p-1.5" id="machineList">
                @forelse ($machines as $machine)
                    <label class="machine-row flex min-h-11 cursor-pointer items-center justify-between gap-3 rounded-control px-3 py-1.5 text-sm hover:bg-surface-muted"
                        data-search="{{ strtolower($machine->machine_number.' '.$machine->machine_type) }}">
                        <span class="flex min-w-0 items-center gap-3">
                            <input type="checkbox" name="machine_ids[]" value="{{ $machine->id }}"
                                class="machine-checkbox h-4 w-4 shrink-0 rounded border-border-strong text-primary focus:ring-primary"
                                @checked(in_array($machine->id, $checked))
                                onchange="updateMachineSelectedCount()">
                            <span class="font-medium text-text">{{ $machine->machine_number }}</span>
                            <span class="truncate text-text-muted">{{ $machine->machine_type }}</span>
                        </span>
                        @if ($machine->group_id && $machine->group_id !== $group->id)
                            <span class="shrink-0 text-xs text-warning-text">currently in: {{ $machine->group->name ?? '-' }}</span>
                        @endif
                    </label>
                @empty
                    <p class="p-3 text-sm text-text-muted">No machines found.</p>
                @endforelse
            </div>
            @error('machine_ids')
                <p class="form-error mt-1">{{ $message }}</p>
            @enderror
        </fieldset>
    </x-ui.form-page>

    <script>
        document.getElementById('machineSearchInput').addEventListener('input', function (e) {
            const term = e.target.value.trim().toLowerCase();
            document.querySelectorAll('#machineList .machine-row').forEach(function (row) {
                row.style.display = row.dataset.search.includes(term) ? '' : 'none';
            });
        });

        function updateMachineSelectedCount() {
            const count = document.querySelectorAll('.machine-checkbox:checked').length;
            document.getElementById('machineSelectedCount').textContent = count + ' selected';
        }
    </script>
@endsection
