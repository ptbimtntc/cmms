@extends('layouts.app')

@section('title', 'Add Sparepart')

@section('content')
    @php
        $fullWidth = true;
        $contentMaxWidth = 'max-w-3xl';
    @endphp

    {{-- Same field set and order as spareparts/edit, so adding and editing a
         sparepart look like one form. Only material_number, description, stock,
         rop and price are validated server-side; the rest are optional. --}}
    <x-ui.form-page title="Add Sparepart" description="Create a sparepart in the master so it can be used in PM sparepart usage and planning."
        :action="route('spareparts.store')" method="POST" :back="route('spareparts.index')" back-label="Back to Sparepart Master" submit="Save">
        <div class="grid gap-5 md:grid-cols-2">
            <x-ui.field label="Material Number" for="spc-material-number" error="material_number" hint="Must not already exist in the master.">
                <input id="spc-material-number" type="text" name="material_number" value="{{ old('material_number') }}" required @class(['input', 'is-invalid' => $errors->has('material_number')])>
            </x-ui.field>
            <x-ui.field label="Location" for="spc-location" error="location">
                <input id="spc-location" type="text" name="location" value="{{ old('location') }}" @class(['input', 'is-invalid' => $errors->has('location')])>
            </x-ui.field>
            <x-ui.field label="Description" for="spc-description" error="description" class="md:col-span-2">
                <input id="spc-description" type="text" name="description" value="{{ old('description') }}" required @class(['input', 'is-invalid' => $errors->has('description')])>
            </x-ui.field>
            <x-ui.field label="Remarks" for="spc-remarks" error="remarks" class="md:col-span-2">
                <textarea id="spc-remarks" name="remarks" rows="3" @class(['textarea', 'is-invalid' => $errors->has('remarks')])>{{ old('remarks') }}</textarea>
            </x-ui.field>
            <x-ui.field label="Stock" for="spc-stock" error="stock">
                <input id="spc-stock" type="number" step="0.01" name="stock" value="{{ old('stock') }}" @class(['input', 'is-invalid' => $errors->has('stock')])>
            </x-ui.field>
            <x-ui.field label="Unit" for="spc-unit" error="unit">
                <input id="spc-unit" type="text" name="unit" value="{{ old('unit') }}" @class(['input', 'is-invalid' => $errors->has('unit')])>
            </x-ui.field>
            <x-ui.field label="ROP" for="spc-rop" error="rop">
                <input id="spc-rop" type="number" name="rop" value="{{ old('rop') }}" @class(['input', 'is-invalid' => $errors->has('rop')])>
            </x-ui.field>
            <x-ui.field label="MRP Type" for="spc-mrp-type" error="mrp_type">
                <input id="spc-mrp-type" type="text" name="mrp_type" value="{{ old('mrp_type') }}" @class(['input', 'is-invalid' => $errors->has('mrp_type')])>
            </x-ui.field>
            <x-ui.field label="Price" for="spc-price" error="price">
                <input id="spc-price" type="number" step="0.01" name="price" value="{{ old('price') }}" @class(['input', 'is-invalid' => $errors->has('price')])>
            </x-ui.field>
            <x-ui.field label="Status" for="spc-status" error="status">
                <select id="spc-status" name="status" class="select">
                    <option value="ACTIVE" @selected(old('status', 'ACTIVE') === 'ACTIVE')>ACTIVE</option>
                    <option value="INACTIVE" @selected(old('status') === 'INACTIVE')>INACTIVE</option>
                </select>
            </x-ui.field>
            <x-ui.field label="Machine Type" for="spc-machine-type" error="machine_type">
                <select id="spc-machine-type" name="machine_type" class="select">
                    <option value="">Select Type</option>
                    @foreach ($machines as $machine)
                        <option value="{{ $machine->machine_type }}" @selected(old('machine_type') === $machine->machine_type)>{{ $machine->machine_type }}</option>
                    @endforeach
                </select>
            </x-ui.field>
            <x-ui.field label="Segment" for="spc-segment" error="segment">
                <input id="spc-segment" type="text" name="segment" value="{{ old('segment') }}" @class(['input', 'is-invalid' => $errors->has('segment')])>
            </x-ui.field>
            <x-ui.field label="PDT" for="spc-pdt" error="pdt">
                <input id="spc-pdt" type="text" name="pdt" value="{{ old('pdt') }}" @class(['input', 'is-invalid' => $errors->has('pdt')])>
            </x-ui.field>
        </div>
    </x-ui.form-page>
@endsection
