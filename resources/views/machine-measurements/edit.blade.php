@extends('layouts.app')

@section('title', 'Edit Measurement')

@section('content')
    @php
        $fullWidth = true;
        $contentMaxWidth = 'max-w-2xl';
    @endphp

    <div class="space-y-5">
        @if (session('error'))
            <x-ui.alert tone="danger">{{ session('error') }}</x-ui.alert>
        @endif

        <x-ui.form-page title="Edit Measurement" description="Update measurement item"
            :action="route('machine-measurements.update', $machineMeasurement->id)" method="PUT" :back="route('machine-measurements.index')" back-label="Kembali ke Measurement Master" submit="Update">
            <input type="hidden" name="return_page" value="{{ request('page') }}">
            <x-ui.field label="Machine Type" for="mme-type" error="machine_type">
                <select id="mme-type" name="machine_type" class="select">
                    @foreach ($machines as $machine)
                        <option value="{{ $machine->machine_type }}" @selected($machineMeasurement->machine_type == $machine->machine_type)>{{ $machine->machine_type }}</option>
                    @endforeach
                </select>
            </x-ui.field>
            <x-ui.field label="Measurement Item" for="mme-item" error="measurement_item">
                <input id="mme-item" type="text" name="measurement_item" value="{{ old('measurement_item', $machineMeasurement->measurement_item) }}" class="input">
            </x-ui.field>
            <div class="grid gap-5 sm:grid-cols-2">
                <x-ui.field label="Unit" for="mme-unit" error="unit">
                    <input id="mme-unit" type="text" name="unit" value="{{ old('unit', $machineMeasurement->unit) }}" class="input">
                </x-ui.field>
                <x-ui.field label="Standard" for="mme-standard" error="standard">
                    <input id="mme-standard" type="text" name="standard" value="{{ old('standard', $machineMeasurement->standard) }}" class="input">
                </x-ui.field>
            </div>
        </x-ui.form-page>
    </div>
@endsection
