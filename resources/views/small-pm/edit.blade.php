@extends('layouts.app')

@php($fullWidth = true)

@section('content')
    <div class="mx-auto max-w-3xl space-y-5">
        <x-ui.page-header title="Edit Small PM Schedule" description="Action date and status are filled in from the Execute page." />

        @if ($errors->any())
            <x-ui.alert tone="danger" title="Not saved">Check the fields marked below.</x-ui.alert>
        @endif

        <form action="{{ route('small-pm.update', $schedule) }}" method="POST" class="card space-y-5 p-5 sm:p-6">
            @csrf
            @method('PUT')
            <input type="hidden" name="return_page" value="{{ request('page') }}">

            @include('small-pm._form', ['schedule' => $schedule])

            <div class="flex flex-col-reverse gap-2 border-t border-border pt-5 sm:flex-row sm:justify-end">
                <x-ui.button variant="ghost" href="{{ route('small-pm.index', ['tab' => $tab]) }}">Cancel</x-ui.button>
                <x-ui.button type="submit">Save changes</x-ui.button>
            </div>
        </form>
    </div>
@endsection
