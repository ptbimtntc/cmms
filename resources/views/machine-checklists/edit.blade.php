@extends('layouts.app')

@section('title', 'Edit Machine Checklist')

@section('content')
    @php
        $fullWidth = true;
        $contentMaxWidth = 'max-w-3xl';
    @endphp

    <div class="space-y-5">
        @include('partials.master.form-messages')

        <x-ui.form-page title="Edit Machine Checklist" description="Update checklist information"
            :action="route('machine-checklists.update', $machineChecklist->id)" method="PUT" :back="route('machine-checklists.index')" back-label="Kembali ke Machine Checklist" submit="Update">
            <input type="hidden" name="return_page" value="{{ request('page') }}">

            <x-ui.field label="Machine Type" for="mce-type">
                <select id="mce-type" name="machine_type" class="select" required>
                    @foreach ($machines as $machine)
                        <option value="{{ $machine->machine_type }}" @selected(old('machine_type', $machineChecklist->machine_type) == $machine->machine_type)>{{ $machine->machine_type }}</option>
                    @endforeach
                </select>
            </x-ui.field>
            <x-ui.field label="Section" for="mce-section">
                <input id="mce-section" type="text" name="section" value="{{ old('section', $machineChecklist->section) }}" class="input" required>
            </x-ui.field>
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <x-ui.field label="Section Order" for="mce-section-order">
                    <input id="mce-section-order" type="number" name="section_order" value="{{ old('section_order', $machineChecklist->section_order) }}" class="input" min="1" required>
                </x-ui.field>
                <x-ui.field label="Item Order" for="mce-item-order">
                    <input id="mce-item-order" type="number" name="item_order" value="{{ old('item_order', $machineChecklist->item_order) }}" class="input" min="1" required>
                </x-ui.field>
            </div>
            <x-ui.field label="Checklist Item" for="mce-item">
                <input id="mce-item" type="text" name="checklist_item" value="{{ old('checklist_item', $machineChecklist->checklist_item) }}" class="input" required>
            </x-ui.field>
            <x-ui.field label="Maintenance Type" for="mce-mtype">
                <select id="mce-mtype" name="maintenance_type" class="select" required>
                    @foreach (['check' => 'Check', 'clean' => 'Clean', 'lubrication' => 'Lubrication', 'replace' => 'Replace'] as $value => $text)
                        <option value="{{ $value }}" @selected($machineChecklist->maintenance_type == $value)>{{ $text }}</option>
                    @endforeach
                </select>
            </x-ui.field>
        </x-ui.form-page>
    </div>
@endsection
