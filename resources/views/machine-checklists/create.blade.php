@extends('layouts.app')

@section('title', 'Add Machine Checklist')

@section('content')
    @php
        $fullWidth = true;
        $contentMaxWidth = 'max-w-3xl';
        $selectedType = old('machine_type', request('machine_type'));
        $selectedSection = old('section', request('section'));
        $selectedMaintenance = old('maintenance_type', request('maintenance_type', 'check'));
    @endphp

    <div class="space-y-5">
        <x-ui.button variant="ghost" href="{{ route('machine-checklists.index') }}" class="-ml-2">Kembali ke Machine Checklist</x-ui.button>
        <x-ui.page-header title="Add Machine Checklist" description="Add one checklist item without importing a file" />

        @include('partials.master.form-messages')

        <form action="{{ route('machine-checklists.store') }}" method="POST" class="card space-y-5 p-5 sm:p-6">
            @csrf
            <x-ui.field label="Machine Type" for="machine_type">
                <select id="machine_type" name="machine_type" class="select" required>
                    <option value="">Select machine type</option>
                    @foreach ($machines as $machine)
                        <option value="{{ $machine->machine_type }}" @selected($selectedType == $machine->machine_type)>{{ $machine->machine_type }}</option>
                    @endforeach
                </select>
            </x-ui.field>

            <x-ui.field label="Section" for="section" hint="Pick an existing section or type a new one.">
                <input type="text" id="section" name="section" list="sectionOptions" value="{{ $selectedSection }}" autocomplete="off" class="input" required>
                <datalist id="sectionOptions"></datalist>
            </x-ui.field>

            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <x-ui.field label="Section Order" for="section_order">
                    <input type="number" id="section_order" name="section_order" value="{{ old('section_order', 1) }}" class="input" min="1" required>
                </x-ui.field>
                <x-ui.field label="Item Order" for="item_order">
                    <input type="number" id="item_order" name="item_order" value="{{ old('item_order', 1) }}" class="input" min="1" required>
                </x-ui.field>
            </div>

            <x-ui.field label="Checklist Item" for="checklist_item">
                <input type="text" id="checklist_item" name="checklist_item" value="{{ old('checklist_item') }}" class="input" required @if ($selectedType && $selectedSection) autofocus @endif>
            </x-ui.field>

            <x-ui.field label="Maintenance Type" for="maintenance_type">
                <select id="maintenance_type" name="maintenance_type" class="select" required>
                    @foreach ($maintenanceTypes as $type)
                        <option value="{{ $type }}" @selected($selectedMaintenance == $type)>{{ \Illuminate\Support\Str::title($type) }}</option>
                    @endforeach
                </select>
            </x-ui.field>

            <div class="flex flex-col-reverse gap-2 border-t border-border pt-5 sm:flex-row sm:justify-end">
                <x-ui.button variant="ghost" href="{{ route('machine-checklists.index') }}">Cancel</x-ui.button>
                <x-ui.button type="submit" variant="outline" name="add_another" value="1">Save &amp; Add Another</x-ui.button>
                <x-ui.button type="submit">Save</x-ui.button>
            </div>
        </form>
    </div>

    <script>
        // Section suggestions follow the machine type; picking a section
        // prefills its section order and the next free item order.
        (function() {
            const sectionsByType = @json($sectionsByType);
            const typeSelect = document.getElementById('machine_type');
            const sectionInput = document.getElementById('section');
            const datalist = document.getElementById('sectionOptions');
            const sectionOrder = document.getElementById('section_order');
            const itemOrder = document.getElementById('item_order');
            const hasOldInput = @json(session()->hasOldInput());

            function sectionsForType() {
                return sectionsByType[typeSelect.value] || [];
            }

            function fillDatalist() {
                datalist.innerHTML = '';
                sectionsForType().forEach(function(row) {
                    datalist.appendChild(new Option(row.section));
                });
            }

            function fillOrders() {
                const name = sectionInput.value.trim().toLowerCase();
                const sections = sectionsForType();
                const match = sections.find(function(row) {
                    return row.section.toLowerCase() === name;
                });

                if (match) {
                    sectionOrder.value = match.section_order;
                    itemOrder.value = match.next_item_order;
                } else {
                    const maxOrder = sections.reduce(function(max, row) {
                        return Math.max(max, row.section_order);
                    }, 0);
                    sectionOrder.value = maxOrder + 1;
                    itemOrder.value = 1;
                }
            }

            typeSelect.addEventListener('change', function() {
                fillDatalist();
                fillOrders();
            });
            sectionInput.addEventListener('change', fillOrders);

            fillDatalist();
            // Keep the user's numbers after a validation error.
            if (!hasOldInput && typeSelect.value) {
                fillOrders();
            }
        })();
    </script>
@endsection
