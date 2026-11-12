@extends('layouts.app')

@section('title', 'Add Sparepart')

{{-- Placeholder form with no action or field names, kept as it was (not linked from any page). --}}
@section('content')
    @php
        $fullWidth = true;
        $contentMaxWidth = 'max-w-2xl';
    @endphp

    <div class="space-y-5">
        <x-ui.button variant="ghost" href="{{ route('spareparts.index') }}" class="-ml-2">Kembali ke Sparepart Master</x-ui.button>
        <x-ui.page-header title="Add Sparepart" description="Create a new sparepart entry for maintenance inventory." />

        <form class="card space-y-5 p-5 sm:p-6">
            <x-ui.field label="SAP Number" for="spc-sap"><input id="spc-sap" type="text" class="input" placeholder="SAP-0001"></x-ui.field>
            <x-ui.field label="Description" for="spc-desc"><input id="spc-desc" type="text" class="input" placeholder="Bearing / Seal / Motor Part"></x-ui.field>
            <x-ui.field label="Location" for="spc-loc"><input id="spc-loc" type="text" class="input" placeholder="Warehouse A / B / C"></x-ui.field>
            <div class="flex flex-col-reverse gap-2 border-t border-border pt-5 sm:flex-row sm:justify-end">
                <x-ui.button variant="ghost" href="{{ route('spareparts.index') }}">Cancel</x-ui.button>
                <x-ui.button type="submit">Save</x-ui.button>
            </div>
        </form>
    </div>
@endsection
