@extends('layouts.app')

@php
    $fullWidth = true;
    $months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
@endphp

@section('content')
    <div class="mx-auto max-w-3xl space-y-5">
        <x-ui.page-header title="Create PM Schedule"
            description="Plan a new preventive maintenance schedule for a machine. The PIC is assigned afterwards from the schedule list, the same as an imported schedule." />

        @if ($errors->any())
            <x-ui.alert tone="danger" title="Not saved yet">Check the fields marked below.</x-ui.alert>
        @endif

        {{-- Required marks mirror PMScheduleController::store() validation. --}}
        <form method="POST" action="{{ route('pm-schedules.store') }}" class="card space-y-5 p-5 sm:p-6">
            @csrf

            <x-ui.field label="Machine" for="machine_id" error="machine_id" required>
                <select id="machine_id" name="machine_id" required class="select">
                    <option value="">Select machine</option>
                    @foreach ($machines as $machine)
                        <option value="{{ $machine->id }}" {{ (string) old('machine_id') === (string) $machine->id ? 'selected' : '' }}>{{ $machine->machine_number }} - {{ $machine->machine_type }}</option>
                    @endforeach
                </select>
            </x-ui.field>

            <div class="grid gap-5 sm:grid-cols-2">
                <x-ui.field label="Order Number" for="order_number" error="order_number" hint="Must not be used by another schedule." required>
                    <input id="order_number" type="text" name="order_number" value="{{ old('order_number') }}" required class="input">
                </x-ui.field>

                <x-ui.field label="Plan Date" for="plan_date" error="plan_date" hint="Due date is set to plan date + 14 days." required>
                    <input id="plan_date" type="date" name="plan_date" value="{{ old('plan_date') }}" required class="input">
                </x-ui.field>

                <x-ui.field label="Plan Month" for="plan_month" error="plan_month" required>
                    <select id="plan_month" name="plan_month" required class="select">
                        <option value="">Select month</option>
                        @foreach ($months as $monthName)
                            <option value="{{ $monthName }}" {{ old('plan_month') === $monthName ? 'selected' : '' }}>{{ $monthName }}</option>
                        @endforeach
                    </select>
                </x-ui.field>

                <x-ui.field label="Plan Year" for="plan_year" error="plan_year" required>
                    <input id="plan_year" type="number" name="plan_year" value="{{ old('plan_year', date('Y')) }}" required class="input">
                </x-ui.field>
            </div>

            <div class="flex flex-col-reverse gap-2 border-t border-border pt-5 sm:flex-row sm:justify-end">
                <x-ui.button variant="ghost" href="{{ route('pm-schedules.index') }}">Cancel</x-ui.button>
                <x-ui.button type="submit">Save schedule</x-ui.button>
            </div>
        </form>
    </div>
@endsection
