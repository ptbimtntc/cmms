@extends('layouts.app')

@section('title', 'Edit Sparepart')

@section('content')
    @php
        $fullWidth = true;
        $contentMaxWidth = 'max-w-3xl';
    @endphp

    <x-ui.form-page title="Edit Sparepart" description="Adjust sparepart details, stock, and assignment."
        :action="route('spareparts.update', $sparepart->id)" method="PUT" :back="route('spareparts.index')" back-label="Kembali ke Sparepart Master" submit="Update">
        <input type="hidden" name="return_page" value="{{ request('page') }}">
        <div class="grid gap-5 md:grid-cols-2">
                <x-ui.field label="Material Number" for="spe-material-number" error="material_number">
                    <input id="spe-material-number" type="text" name="material_number" value="{{ old('material_number', $sparepart->material_number) }}" @class(['input', 'is-invalid' => $errors->has('material_number')])>
                </x-ui.field>
                <x-ui.field label="Location" for="spe-location" error="location">
                    <input id="spe-location" type="text" name="location" value="{{ old('location', $sparepart->location) }}" @class(['input', 'is-invalid' => $errors->has('location')])>
                </x-ui.field>
                <x-ui.field label="Description" for="spe-description" error="description" class="md:col-span-2">
                    <input id="spe-description" type="text" name="description" value="{{ old('description', $sparepart->description) }}" @class(['input', 'is-invalid' => $errors->has('description')])>
                </x-ui.field>
                <x-ui.field label="Remarks" for="spe-remarks" error="remarks" class="md:col-span-2">
                    <textarea id="spe-remarks" name="remarks" rows="3" @class(['textarea', 'is-invalid' => $errors->has('remarks')])>{{ old('remarks', $sparepart->remarks) }}</textarea>
                </x-ui.field>
                <x-ui.field label="Stock" for="spe-stock" error="stock">
                    <input id="spe-stock" type="number" step="0.01" name="stock" value="{{ old('stock', $sparepart->stock) }}" @class(['input', 'is-invalid' => $errors->has('stock')])>
                </x-ui.field>
                <x-ui.field label="Unit" for="spe-unit" error="unit">
                    <input id="spe-unit" type="text" name="unit" value="{{ old('unit', $sparepart->unit) }}" @class(['input', 'is-invalid' => $errors->has('unit')])>
                </x-ui.field>
                <x-ui.field label="ROP" for="spe-rop" error="rop">
                    <input id="spe-rop" type="number" name="rop" value="{{ old('rop', $sparepart->rop) }}" @class(['input', 'is-invalid' => $errors->has('rop')])>
                </x-ui.field>
                <x-ui.field label="MRP Type" for="spe-mrp-type" error="mrp_type">
                    <input id="spe-mrp-type" type="text" name="mrp_type" value="{{ old('mrp_type', $sparepart->mrp_type) }}" @class(['input', 'is-invalid' => $errors->has('mrp_type')])>
                </x-ui.field>
                <x-ui.field label="Price" for="spe-price" error="price">
                    <input id="spe-price" type="number" step="0.01" name="price" value="{{ old('price', $sparepart->price) }}" @class(['input', 'is-invalid' => $errors->has('price')])>
                </x-ui.field>
                <x-ui.field label="Status" for="spe-status" error="status">
                    <select id="spe-status" name="status" class="select">
                        <option value="ACTIVE" @selected($sparepart->status == 'ACTIVE')>ACTIVE</option>
                        <option value="INACTIVE" @selected($sparepart->status == 'INACTIVE')>INACTIVE</option>
                    </select>
                </x-ui.field>
                <x-ui.field label="Machine Type" for="spe-machine-type" error="machine_type">
                    <select id="spe-machine-type" name="machine_type" class="select">
                        <option value="">Select Type</option>
                        @foreach ($machines as $machine)
                            <option value="{{ $machine->machine_type }}" @selected($sparepart->machine_type == $machine->machine_type)>{{ $machine->machine_type }}</option>
                        @endforeach
                    </select>
                </x-ui.field>
                <x-ui.field label="Segment" for="spe-segment" error="segment">
                    <input id="spe-segment" type="text" name="segment" value="{{ old('segment', $sparepart->segment) }}" @class(['input', 'is-invalid' => $errors->has('segment')])>
                </x-ui.field>
                <x-ui.field label="PDT" for="spe-pdt" error="pdt">
                    <input id="spe-pdt" type="text" name="pdt" value="{{ old('pdt', $sparepart->pdt) }}" @class(['input', 'is-invalid' => $errors->has('pdt')])>
                </x-ui.field>
        </div>
    </x-ui.form-page>
@endsection
