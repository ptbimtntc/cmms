@extends('layouts.app')

@section('title', 'Add Machine Problem Finding')

@section('content')
    @php
        $fullWidth = true;
        $contentMaxWidth = 'max-w-3xl';
        $selectedCategory = old('category', request('category'));
    @endphp

    <div class="space-y-5">
        <x-ui.button variant="ghost" href="{{ route('machine-problem-findings.index') }}" class="-ml-2">Kembali ke Problem Findings</x-ui.button>
        <x-ui.page-header title="Add Machine Problem Finding" description="Add one finding without importing a file" />

        @include('partials.master.form-messages')
        @if ($categories->isEmpty())
            <x-ui.alert tone="warning">
                No category exists yet. Add one in <a href="{{ route('machine-problems.create') }}" class="font-semibold underline">Problem Category</a> first.
            </x-ui.alert>
        @endif

        <form action="{{ route('machine-problem-findings.store') }}" method="POST" class="card space-y-5 p-5 sm:p-6">
            @csrf
            <x-ui.field label="Category" for="category">
                <select id="category" name="category" class="select" required>
                    <option value="">Select category</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category }}" @selected($selectedCategory == $category)>{{ $category }}</option>
                    @endforeach
                </select>
            </x-ui.field>
            <x-ui.field label="Finding" for="finding">
                <input type="text" id="finding" name="finding" value="{{ old('finding') }}" class="input" required @if ($selectedCategory) autofocus @endif>
            </x-ui.field>
            <div class="flex flex-col-reverse gap-2 border-t border-border pt-5 sm:flex-row sm:justify-end">
                <x-ui.button variant="ghost" href="{{ route('machine-problem-findings.index') }}">Cancel</x-ui.button>
                <x-ui.button type="submit" variant="outline" name="add_another" value="1">Save &amp; Add Another</x-ui.button>
                <x-ui.button type="submit">Save</x-ui.button>
            </div>
        </form>
    </div>
@endsection
