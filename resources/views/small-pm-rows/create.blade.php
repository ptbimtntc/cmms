@extends('layouts.app')

@section('title', 'Add Row')

@section('content')
    @php
        $fullWidth = true;
        $contentMaxWidth = 'max-w-2xl';
    @endphp

    <x-ui.form-page title="Add Row" description="Once saved, you go to the Edit page to tick the machines in this row."
        :action="route('small-pm-rows.store')" :back="route('small-pm-rows.index')" back-label="Back to Small PM Rows" submit="Save" cancel-label="Cancel">
        @include('small-pm-rows._fields', ['row' => null])
    </x-ui.form-page>
@endsection
