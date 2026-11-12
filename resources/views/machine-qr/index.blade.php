@extends('layouts.app')

@section('title', 'Machine QR Management')

@php
    $fullWidth = true;
    $tabs = ['qr' => 'QR Code Management', 'override' => 'Last PM Override'];
@endphp

@section('content')
    <div class="space-y-5">
        <x-ui.page-header title="Machine QR Management" description="Generate printable machine QR labels and correct the Last PM shown in Machine History." />

        <nav class="-mt-1 flex overflow-x-auto border-b border-border" aria-label="Machine QR">
            @foreach ($tabs as $key => $text)
                @php($active = $tab === $key)
                <a href="{{ route('machine-qr.index', ['tab' => $key]) }}" @if ($active) aria-current="page" @endif
                    @class([
                        '-mb-px inline-flex min-h-11 flex-1 items-center justify-center whitespace-nowrap border-b-2 px-3 text-sm font-semibold transition-colors sm:flex-none',
                        'border-primary text-primary-hover' => $active,
                        'border-transparent text-text-muted hover:border-border-strong hover:text-text' => ! $active,
                    ])>{{ $text }}</a>
            @endforeach
        </nav>

        @if (session('success'))
            <x-ui.alert tone="success">{{ session('success') }}</x-ui.alert>
        @endif
        @if (session('error'))
            <x-ui.alert tone="danger">{{ session('error') }}</x-ui.alert>
        @endif
        @if ($errors->any())
            <x-ui.alert tone="danger">
                @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
            </x-ui.alert>
        @endif

        {{-- Shared search / filter bar (GET, so it survives reload + pagination) --}}
        <form method="GET" action="{{ route('machine-qr.index') }}" class="card flex flex-col gap-3 p-4 sm:flex-row sm:flex-wrap sm:items-end">
            <input type="hidden" name="tab" value="{{ $tab }}">
            <div class="grid gap-1.5 sm:w-64">
                <label for="mq-search" class="text-xs font-medium text-text-muted">Search</label>
                <input id="mq-search" type="search" name="search" value="{{ $search }}" placeholder="Machine number / name" class="input lg:min-h-9">
            </div>
            <div class="grid gap-1.5 sm:w-36">
                <label for="mq-area" class="text-xs font-medium text-text-muted">Area</label>
                <select id="mq-area" name="area" class="select lg:min-h-9">
                    <option value="">All Areas</option>
                    @foreach ($areas as $a)
                        <option value="{{ $a }}" @selected($area === $a)>{{ $a }}</option>
                    @endforeach
                </select>
            </div>
            @if ($tab === 'override')
                <div class="grid gap-1.5 sm:w-36">
                    <label for="mq-status" class="text-xs font-medium text-text-muted">Status</label>
                    <select id="mq-status" name="status" class="select lg:min-h-9">
                        <option value="">All Status</option>
                        <option value="normal" @selected($status === 'normal')>Normal</option>
                        <option value="overridden" @selected($status === 'overridden')>Overridden</option>
                    </select>
                </div>
            @endif
            <div class="flex gap-2 sm:ml-auto">
                <x-ui.button type="submit" class="flex-1 sm:flex-none lg:min-h-9">Filter</x-ui.button>
                <x-ui.button variant="ghost" href="{{ route('machine-qr.index', ['tab' => $tab]) }}" class="lg:min-h-9">Reset</x-ui.button>
            </div>
        </form>

        @if ($tab === 'qr')
            @include('machine-qr._qr-tab')
        @else
            @include('machine-qr._override-tab')
        @endif
    </div>
@endsection
