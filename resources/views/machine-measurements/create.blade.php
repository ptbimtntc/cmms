@extends('layouts.app')

@section('title', 'Add Measurement Master')

@section('content')
    @php
        $fullWidth = true;
        $contentMaxWidth = 'max-w-4xl';
    @endphp

    <x-ui.form-page title="Add Measurement Master" description="Tambahkan measurement berdasarkan tipe mesin"
        :action="route('machine-measurements.store')" :back="route('machine-measurements.index')" back-label="Kembali ke Measurement Master">
        <x-ui.field label="Machine Type" for="mmc-type" error="machine_type">
            <select id="mmc-type" name="machine_type" @class(['select', 'is-invalid' => $errors->has('machine_type')])>
                <option value="">Select Machine Type</option>
                @foreach ($machines as $machine)
                    <option value="{{ $machine->machine_type }}">{{ $machine->machine_type }}</option>
                @endforeach
            </select>
        </x-ui.field>

        <fieldset class="grid gap-2">
            <legend class="form-label mb-1.5">Measurement Items</legend>
            <div class="hidden gap-3 px-0.5 text-xs font-medium text-text-muted md:grid md:grid-cols-[1fr_1fr_1fr_auto]" aria-hidden="true">
                <span>Measurement Item</span><span>Unit</span><span>Standard</span><span class="w-20"></span>
            </div>
            <div id="measurement-wrapper" class="space-y-3">
                <div class="measurement-row grid gap-2 rounded-control border border-border p-3 md:grid-cols-[1fr_1fr_1fr_auto] md:gap-3 md:border-0 md:p-0">
                    <input type="text" name="measurements[]" placeholder="Measurement Item" aria-label="Measurement Item" class="input">
                    <input type="text" name="units[]" placeholder="Unit (mm, °C, bar, mm/s)" aria-label="Unit" class="input">
                    <input type="text" name="standards[]" placeholder="Standard" aria-label="Standard" class="input">
                    <button type="button" onclick="removeMeasurement(this)" class="btn btn-ghost min-h-11 w-20 text-danger hover:bg-danger-light hover:text-danger-hover">Remove</button>
                </div>
            </div>
            <x-ui.button variant="outline" size="sm" class="justify-self-start" onclick="addMeasurement()">Add More</x-ui.button>
        </fieldset>
    </x-ui.form-page>

    <script>
        function addMeasurement() {
            const wrapper = document.getElementById('measurement-wrapper');
            const html = `
                <div class="measurement-row grid gap-2 rounded-control border border-border p-3 md:grid-cols-[1fr_1fr_1fr_auto] md:gap-3 md:border-0 md:p-0">
                    <input type="text" name="measurements[]" placeholder="Measurement Item" aria-label="Measurement Item" class="input">
                    <input type="text" name="units[]" placeholder="Unit (mm, °C, bar, mm/s)" aria-label="Unit" class="input">
                    <input type="text" name="standards[]" placeholder="Standard" aria-label="Standard" class="input">
                    <button type="button" onclick="removeMeasurement(this)" class="btn btn-ghost min-h-11 w-20 text-danger hover:bg-danger-light hover:text-danger-hover">Remove</button>
                </div>`;
            wrapper.insertAdjacentHTML('beforeend', html);
        }

        function removeMeasurement(button) {
            const wrapper = document.getElementById('measurement-wrapper');
            if (wrapper.querySelectorAll('.measurement-row').length > 1) {
                button.closest('.measurement-row').remove();
            }
        }
    </script>
@endsection
