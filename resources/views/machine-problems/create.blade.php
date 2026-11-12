@extends('layouts.app')

@section('title', 'Add Machine Big Problem')

@section('content')
    @php
        $fullWidth = true;
        $contentMaxWidth = 'max-w-3xl';
        $selectedType = old('machine_type', request('machine_type'));
        $selectedCategory = old('category', request('category'));
    @endphp

    <div class="space-y-5">
        <x-ui.button variant="ghost" href="{{ route('machine-problems.index') }}" class="-ml-2">Kembali ke Big Problem Master</x-ui.button>
        <x-ui.page-header title="Add Machine Big Problem" description="Add one problem without importing a file" />

        @include('partials.master.form-messages')

        <form action="{{ route('machine-problems.store') }}" method="POST" class="card space-y-5 p-5 sm:p-6">
            @csrf
            <x-ui.field label="Machine Type" for="machine_type">
                <select id="machine_type" name="machine_type" class="select" required>
                    <option value="">Select machine type</option>
                    @foreach ($machines as $machine)
                        <option value="{{ $machine->machine_type }}" @selected($selectedType == $machine->machine_type)>{{ $machine->machine_type }}</option>
                    @endforeach
                </select>
            </x-ui.field>
            <x-ui.field label="Category" for="category" hint="Pick an existing category or type a new one. Problem Findings are grouped by this category.">
                <input type="text" id="category" name="category" list="categoryOptions" value="{{ $selectedCategory }}" autocomplete="off" class="input" required>
                <datalist id="categoryOptions">
                    @foreach ($categories as $category)
                        <option value="{{ $category }}"></option>
                    @endforeach
                </datalist>
            </x-ui.field>
            <x-ui.field label="Big Problem" for="problem">
                <input type="text" id="problem" name="problem" value="{{ old('problem') }}" class="input" required @if ($selectedType && $selectedCategory) autofocus @endif>
            </x-ui.field>
            <div class="flex flex-col-reverse gap-2 border-t border-border pt-5 sm:flex-row sm:justify-end">
                <x-ui.button variant="ghost" href="{{ route('machine-problems.index') }}">Cancel</x-ui.button>
                <x-ui.button type="submit" variant="outline" name="add_another" value="1">Save &amp; Add Another</x-ui.button>
                <x-ui.button type="submit">Save</x-ui.button>
            </div>
        </form>
    </div>
@endsection
