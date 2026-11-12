@extends('layouts.app')

@php($fullWidth = true)

@section('content')
    <div class="mx-auto max-w-3xl space-y-5">
        <x-ui.page-header title="Add Greasing Schedule" description="Create a new greasing schedule for a machine group." />

        @if ($errors->any())
            <x-ui.alert tone="danger" title="Not saved yet">Check the fields marked below.</x-ui.alert>
        @endif

        <form action="{{ route('greasings.store') }}" method="POST" class="card space-y-5 p-5 sm:p-6">
            @csrf

            @include('greasings.partials.form-fields', ['greasing' => null])

            <div class="flex flex-col-reverse gap-2 border-t border-border pt-5 sm:flex-row sm:justify-end">
                <x-ui.button variant="ghost" href="{{ route('greasings.index') }}">Cancel</x-ui.button>
                <x-ui.button type="submit">Save schedule</x-ui.button>
            </div>
        </form>
    </div>
@endsection
