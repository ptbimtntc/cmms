@extends('layouts.app')

@section('title', 'Edit Row')

@section('content')
    @php
        $fullWidth = true;
        $contentMaxWidth = 'max-w-3xl';
        $checked = old('machine_ids', $machines->where('small_pm_row_id', $row->id)->pluck('id')->all());
    @endphp

    <div class="space-y-5">
        @if (session('success'))
            <x-ui.alert tone="success">{{ session('success') }}</x-ui.alert>
        @endif

        <x-ui.form-page title="Edit Row" description="Change the row details and pick the machines that belong to it."
            :action="route('small-pm-rows.update', $row)" method="PUT" :back="route('small-pm-rows.index')" back-label="Back to Small PM Rows" submit="Save Perubahan" cancel-label="Batal">
            <input type="hidden" name="return_page" value="{{ request('page') }}">

            @include('small-pm-rows._fields', ['row' => $row])

            <fieldset class="rounded-control border border-border bg-surface-sunken p-4 sm:p-5">
                <legend class="sr-only">Machines in this row</legend>
                <div class="mb-3 flex items-start justify-between gap-3">
                    <div>
                        <p class="text-sm font-semibold text-text">Machines in this row</p>
                        <p class="mt-0.5 text-xs text-text-muted">Only {{ $row->area->name ?? '-' }} machines that are not in another row. A machine can belong to one row only.</p>
                    </div>
                    <x-ui.badge id="machineSelectedCount" class="shrink-0" aria-live="polite">{{ count($checked) }} dipilih</x-ui.badge>
                </div>

                <label for="machineSearchInput" class="sr-only">Search machines</label>
                <input type="text" id="machineSearchInput" placeholder="Search machine number or type" class="input mb-3">

                <div class="max-h-72 space-y-0.5 overflow-y-auto rounded-control border border-border bg-surface p-1.5" id="machineList">
                    @forelse ($machines as $machine)
                        <label class="machine-row flex min-h-11 cursor-pointer items-center gap-3 rounded-control px-3 py-1.5 text-sm hover:bg-surface-muted"
                            data-search="{{ strtolower($machine->machine_number.' '.$machine->machine_type) }}">
                            <input type="checkbox" name="machine_ids[]" value="{{ $machine->id }}"
                                class="machine-checkbox h-4 w-4 shrink-0 rounded border-border-strong text-primary focus:ring-primary"
                                @checked(in_array($machine->id, $checked))
                                onchange="updateMachineSelectedCount()">
                            <span class="font-medium text-text">{{ $machine->machine_number }}</span>
                            <span class="truncate text-text-muted">{{ $machine->machine_type }}</span>
                        </label>
                    @empty
                        <p class="p-3 text-sm text-text-muted">No machine available to pick.</p>
                    @endforelse
                </div>
                @error('machine_ids')
                    <p class="form-error mt-1">{{ $message }}</p>
                @enderror
            </fieldset>
        </x-ui.form-page>
    </div>

    <script>
        document.getElementById('machineSearchInput').addEventListener('input', function (e) {
            const term = e.target.value.trim().toLowerCase();
            document.querySelectorAll('#machineList .machine-row').forEach(function (row) {
                row.style.display = row.dataset.search.includes(term) ? '' : 'none';
            });
        });

        function updateMachineSelectedCount() {
            const count = document.querySelectorAll('.machine-checkbox:checked').length;
            document.getElementById('machineSelectedCount').textContent = count + ' dipilih';
        }
    </script>
@endsection
