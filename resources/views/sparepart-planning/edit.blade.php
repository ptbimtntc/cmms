@extends('layouts.app')

@section('title', 'Edit Mandatory Sparepart')

@php
    $fullWidth = true;
    $contentMaxWidth = 'max-w-2xl';
@endphp

@section('content')
    <div class="space-y-5">
        <x-ui.button variant="ghost" href="{{ route('sparepart-planning.index', ['tab' => 'mandatory']) }}" class="-ml-2">Kembali ke Sparepart Planning</x-ui.button>

        <x-ui.page-header title="Edit Mandatory Sparepart" description="Changes only affect future Fill PM forms. PM records that are already saved are not modified." />

        <form action="{{ route('sparepart-planning.update', $mandatory) }}" method="POST" class="card space-y-5 p-5 sm:p-6">
            @csrf
            @method('PUT')

            <x-ui.field label="Machine Type" for="spe-type" error="machine_type">
                <select id="spe-type" @error('machine_type') aria-invalid="true" aria-describedby="spe-type-error" @enderror name="machine_type" @class(['select', 'is-invalid' => $errors->has('machine_type')])>
                    @foreach ($machineTypes as $type)
                        <option value="{{ $type }}" @selected(old('machine_type', $mandatory->machine_type) === $type)>{{ $type }}</option>
                    @endforeach
                </select>
            </x-ui.field>

            <x-ui.field label="Sparepart" for="spe-sparepart" error="sparepart_id">
                <select id="spe-sparepart" @error('sparepart_id') aria-invalid="true" aria-describedby="spe-sparepart-error" @enderror name="sparepart_id" @class(['select', 'is-invalid' => $errors->has('sparepart_id')])>
                    @foreach ($spareparts as $sp)
                        <option value="{{ $sp->id }}" @selected((int) old('sparepart_id', $mandatory->sparepart_id) === $sp->id)>{{ $sp->material_number }} - {{ $sp->description }}</option>
                    @endforeach
                </select>
            </x-ui.field>

            <x-ui.field label="Qty per PM" for="spe-qty" error="qty">
                <input id="spe-qty" @error('qty') aria-invalid="true" aria-describedby="spe-qty-error" @enderror type="number" name="qty" min="1" step="1" value="{{ old('qty', $mandatory->qty) }}" @class(['input sm:w-40', 'is-invalid' => $errors->has('qty')])>
            </x-ui.field>

            <div class="flex flex-col-reverse gap-2 border-t border-border pt-5 sm:flex-row sm:justify-end">
                <x-ui.button variant="ghost" href="{{ route('sparepart-planning.index', ['tab' => 'mandatory']) }}">Cancel</x-ui.button>
                <x-ui.button type="submit">Save</x-ui.button>
            </div>
        </form>
    </div>

    {{-- Same searchable picker as the Add form on the index page. --}}
    <link href="https://cdn.jsdelivr.net/npm/tom-select/dist/css/tom-select.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/tom-select/dist/js/tom-select.complete.min.js"></script>
    <script>
        window.addEventListener('load', function () {
            if (window.TomSelect) { new TomSelect('#spe-sparepart', { maxOptions: 100 }); }
        });
    </script>
@endsection
