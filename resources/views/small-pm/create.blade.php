@extends('layouts.app')

@php($fullWidth = true)

@section('content')
    <div class="mx-auto max-w-3xl space-y-5">
        <x-ui.page-header title="Add Small PM Schedule" :description="$tabLabel" />

        @if ($errors->any())
            <x-ui.alert tone="danger" title="Not saved">Check the fields marked below.</x-ui.alert>
        @endif

        @if ($rows->isEmpty())
            <x-ui.empty-state title="No row yet." description="Small PM schedules are created per machine row. Create a row first in Master Data, Small PM Rows.">
                <x-ui.button href="{{ route('small-pm-rows.index') }}">Buka Row Small PM</x-ui.button>
            </x-ui.empty-state>
        @else
            <form action="{{ route('small-pm.store') }}" method="POST" class="card space-y-5 p-5 sm:p-6">
                @csrf
                <input type="hidden" name="tab" value="{{ $tab }}">

                @include('small-pm._form', ['schedule' => null])

                <div class="flex flex-col-reverse gap-2 border-t border-border pt-5 sm:flex-row sm:justify-end">
                    <x-ui.button variant="ghost" href="{{ route('small-pm.index', ['tab' => $tab]) }}">Cancel</x-ui.button>
                    <x-ui.button type="submit">Save</x-ui.button>
                </div>
            </form>
        @endif
    </div>
@endsection
