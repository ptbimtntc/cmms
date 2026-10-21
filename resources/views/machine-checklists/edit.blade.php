@extends('layouts.app')

@section('content')

    <div class="container mx-auto px-6 py-6 max-w-3xl">

        <div class="bg-white rounded-lg shadow">

            <div class="border-b px-6 py-4">
                <h1 class="text-2xl font-bold text-gray-800">
                    Edit Machine Checklist
                </h1>

                <p class="text-gray-500 text-sm">
                    Update checklist information
                </p>
            </div>

            <div class="p-6">

                @if (session('error'))
                    <div class="bg-red-100 border border-red-300 text-red-700 px-4 py-3 rounded mb-4">
                        {{ session('error') }}
                    </div>
                @endif

                @if ($errors->any())
                    <div class="bg-red-100 border border-red-300 text-red-700 px-4 py-3 rounded mb-4">

                        <ul class="list-disc ml-5">

                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach

                        </ul>

                    </div>
                @endif

                <form action="{{ route('machine-checklists.update', $machineChecklist->id) }}" method="POST">

                    @csrf
                    @method('PUT')
                    <input type="hidden" name="return_page" value="{{ request('page') }}">

                    <!-- Machine Type -->
                    <div class="mb-5">

                        <label class="block font-medium mb-2">
                            Machine Type
                        </label>

                        <select name="machine_type" class="border rounded w-full px-3 py-2" required>

                            @foreach ($machines as $machine)
                                <option value="{{ $machine->machine_type }}"
                                    {{ old('machine_type', $machineChecklist->machine_type) == $machine->machine_type ? 'selected' : '' }}>

                                    {{ $machine->machine_type }}

                                </option>
                            @endforeach

                        </select>

                    </div>

                    <!-- Section -->
                    <div class="mb-5">

                        <label class="block font-medium mb-2">
                            Section
                        </label>

                        <input type="text" name="section" value="{{ old('section', $machineChecklist->section) }}"
                            class="border rounded w-full px-3 py-2" required>

                    </div>

                    <!-- Section Order -->
                    <div class="mb-5">

                        <label class="block font-medium mb-2">
                            Section Order
                        </label>

                        <input type="number" name="section_order"
                            value="{{ old('section_order', $machineChecklist->section_order) }}"
                            class="border rounded w-full px-3 py-2" min="1" required>

                    </div>

                    <!-- Checklist Item -->
                    <div class="mb-5">

                        <label class="block font-medium mb-2">
                            Checklist Item
                        </label>

                        <input type="text" name="checklist_item"
                            value="{{ old('checklist_item', $machineChecklist->checklist_item) }}"
                            class="border rounded w-full px-3 py-2" required>

                    </div>

                    {{-- Maintenance Type --}}
                    <div class="mt-4">

                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Maintenance Type
                        </label>

                        <select name="maintenance_type" class="w-full border rounded-lg px-3 py-2" required>

                            <option value="check" {{ $machineChecklist->maintenance_type == 'check' ? 'selected' : '' }}>
                                Check
                            </option>

                            <option value="clean" {{ $machineChecklist->maintenance_type == 'clean' ? 'selected' : '' }}>
                                Clean
                            </option>

                            <option value="lubrication"
                                {{ $machineChecklist->maintenance_type == 'lubrication' ? 'selected' : '' }}>
                                Lubrication
                            </option>

                            <option value="replace"
                                {{ $machineChecklist->maintenance_type == 'replace' ? 'selected' : '' }}>
                                Replace
                            </option>

                        </select>

                    </div>

                    <!-- Item Order -->
                    <div class="mb-6">

                        <label class="block font-medium mb-2">
                            Item Order
                        </label>

                        <input type="number" name="item_order"
                            value="{{ old('item_order', $machineChecklist->item_order) }}"
                            class="border rounded w-full px-3 py-2" min="1" required>

                    </div>

                    <div class="flex justify-end gap-3">

                        <a href="{{ route('machine-checklists.index') }}"
                            class="bg-gray-500 hover:bg-gray-600 text-white px-5 py-2 rounded">

                            Cancel

                        </a>

                        <button class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2 rounded">

                            Update

                        </button>

                    </div>

                </form>

            </div>

        </div>

    </div>

@endsection
