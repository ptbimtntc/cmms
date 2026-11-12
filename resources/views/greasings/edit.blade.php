@extends('layouts.app')

@php($fullWidth = true)

@section('content')
    <div class="mx-auto max-w-3xl space-y-5">
        <x-ui.page-header title="Edit Greasing Schedule"
            description="Update schedule planning details. Due date is recalculated automatically. Execution (action date, remarks, findings) is done from the Execute page." />

        @if ($errors->any())
            <x-ui.alert tone="danger" title="Not saved yet">Check the fields marked below.</x-ui.alert>
        @endif

        <form action="{{ route('greasings.update', $greasing) }}" method="POST" class="card space-y-5 p-5 sm:p-6">
            @csrf
            @method('PUT')
            <input type="hidden" name="return_page" value="{{ request('page') }}">

            @include('greasings.partials.form-fields')

            <div class="flex flex-col-reverse gap-2 border-t border-border pt-5 sm:flex-row sm:justify-end">
                <x-ui.button variant="ghost" href="{{ route('greasings.index', ['page' => request('page')]) }}">Cancel</x-ui.button>
                <x-ui.button type="submit">Save Changes</x-ui.button>
            </div>
        </form>
    </div>
@endsection
