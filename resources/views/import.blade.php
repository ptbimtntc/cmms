@extends('layouts.app')

@section('content')

<h1 class="mb-6 font-signage text-2xl font-bold text-text">
    Import Machine
</h1>

@include('partials.master.import-form', [
    'action' => route('machines.import'),
    'fileLabel' => 'Machine file',
])

@endsection