@extends('layouts.app')

@section('title', 'Edit Machine Problem')

@section('content')
    @php
        $fullWidth = true;
        $contentMaxWidth = 'max-w-2xl';
    @endphp

    <div class="space-y-5">
        @include('partials.master.form-messages')

        <x-ui.form-page title="Edit Machine Problem" description="Update big problem master"
            :action="route('machine-problems.update', $machineProblem->id)" method="PUT" :back="route('machine-problems.index')" back-label="Kembali ke Big Problem Master" submit="Update">
            <x-ui.field label="Machine Type" for="mpe-type">
                <select id="mpe-type" name="machine_type" class="select">
                    @foreach ($machines as $machine)
                        <option value="{{ $machine->machine_type }}" @selected($machineProblem->machine_type == $machine->machine_type)>{{ $machine->machine_type }}</option>
                    @endforeach
                </select>
            </x-ui.field>
            <x-ui.field label="Category" for="category">
                <input type="text" id="category" name="category" list="categoryOptions" autocomplete="off" value="{{ old('category', $machineProblem->category) }}" class="input" required>
                <datalist id="categoryOptions">
                    @foreach ($categories as $category)
                        <option value="{{ $category }}"></option>
                    @endforeach
                </datalist>
            </x-ui.field>
            <x-ui.field label="Big Problem" for="mpe-problem">
                <input id="mpe-problem" type="text" name="problem" value="{{ old('problem', $machineProblem->problem) }}" class="input">
            </x-ui.field>
        </x-ui.form-page>
    </div>
@endsection
