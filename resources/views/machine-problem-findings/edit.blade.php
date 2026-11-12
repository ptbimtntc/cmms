@extends('layouts.app')

@section('title', 'Edit Machine Problem Finding')

@section('content')
    @php
        $fullWidth = true;
        $contentMaxWidth = 'max-w-2xl';
    @endphp

    <div class="space-y-5">
        @include('partials.master.form-messages')

        <x-ui.form-page title="Edit Machine Problem Finding"
            :action="route('machine-problem-findings.update', $machineProblemFinding->id)" method="PUT" :back="route('machine-problem-findings.index')" back-label="Kembali ke Problem Findings" submit="Update">
            <input type="hidden" name="return_page" value="{{ request('page') }}">
            <x-ui.field label="Category" for="mpfe-category">
                <select id="mpfe-category" name="category" class="select" required>
                    @foreach ($categories as $category)
                        <option value="{{ $category }}" @selected(old('category', $machineProblemFinding->category) == $category)>{{ $category }}</option>
                    @endforeach
                </select>
            </x-ui.field>
            <x-ui.field label="Finding" for="mpfe-finding">
                <input id="mpfe-finding" type="text" name="finding" value="{{ old('finding', $machineProblemFinding->finding) }}" class="input" required>
            </x-ui.field>
        </x-ui.form-page>
    </div>
@endsection
