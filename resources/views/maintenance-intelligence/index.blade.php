@extends('layouts.app')

@php
    // Standalone page: opt out of the global sidebar/topbar via the layout's
    // existing per-page flags (same mechanism as oil-audits.history). The
    // topbar is hidden too because its hamburger only toggles the sidebar.
    $hideSidebar = true;
    $hideTopbar = true;
    $fullWidth = true;
    $contentMaxWidth = 'max-w-[110rem]';
@endphp

@section('content')
@php
    // Every status label below must match what actually ships. "Planned" means
    // no backend exists yet; the VME items are built and only wait on their
    // feature switch, which is read live so this page cannot go stale.
    $vmeOn = \App\Services\Vme\VmeFeature::all()[\App\Services\Vme\VmeFeature::DISPLAY] ?? false;

    $agents = [
        ['name' => 'AI Sparepart Analyst', 'status' => 'In Development', 'priority' => true,
         'points' => [
             'Forecast mandatory sparepart needs',
             'Forecast extra spareparts from actual history',
             'Analyse sparepart usage patterns',
             'Recommend sparepart preparation',
         ]],
        ['name' => 'AI Machine Problem Predictor', 'status' => 'Planned', 'points' => []],
        ['name' => 'AI Oil Condition Analyst', 'status' => 'Planned', 'points' => []],
        ['name' => 'AI PM Planner', 'status' => 'Planned', 'points' => []],
        ['name' => 'AI Maintenance Performance Analyst', 'status' => 'Planned', 'points' => []],
        ['name' => 'AI Maintenance Knowledge Assistant', 'status' => 'Planned', 'points' => []],
    ];

    $vme = [
        'Virtual Office 2D', 'Production Area', 'Workshop', 'Human Characters',
        'AI Characters', 'Live Activity Synchronization', 'AI Agent Visualization',
    ];

    $roadmaps = [
        'AI Maintenance Intelligence' => [
            'Data Readiness Audit', 'Forecast Calculation Design', 'Database & API Design',
            'AI Backend Development', 'Gemini Integration', 'Dashboard Integration', 'Validation & Testing',
        ],
        'Virtual Maintenance Environment' => [
            'Concept & Architecture Design', 'Map & Environment Design', 'Dynamic Character Configuration',
            'Human Character Development', 'Today Activity Integration', 'AI Character Integration', 'Monitoring & Optimization',
        ],
    ];
@endphp

<div class="space-y-6">

    {{-- Header --}}
    <div class="card p-5 sm:p-6">
        <a href="{{ route('home') }}" class="btn btn-ghost -ml-2 min-h-11">Back to landing page</a>
        <h1 class="mt-3 font-signage text-2xl font-semibold leading-tight text-text sm:text-3xl">Maintenance Intelligence Center</h1>
        <p class="mt-1 text-sm text-text-muted">
            Central management for AI-powered maintenance analysis and virtual maintenance monitoring.
            ADMIN only, and deliberately kept out of the main menu.
        </p>
        <div class="mt-4 flex flex-wrap gap-2 text-xs">
            <span class="badge badge-neutral">AI Maintenance Intelligence: In Development</span>
            <span class="badge badge-neutral">Virtual Maintenance Environment: {{ $vmeOn ? 'Active' : 'Built, switched off' }}</span>
        </div>
    </div>

    {{-- AI Maintenance Intelligence --}}
    <section class="card p-5 sm:p-6">
        <h2 class="text-lg font-semibold text-text">AI Maintenance Intelligence</h2>
        <p class="mt-1 text-sm text-text-muted">
            AI agents are an add-on; the CMMS works in full without them. Only AI Sparepart Analyst is under development.
        </p>

        @php $first = $agents[0]; @endphp
        <div class="mt-5 rounded-card border border-primary/30 bg-primary-light p-5">
            <div class="flex flex-wrap items-center gap-2">
                <h3 class="text-base font-semibold text-text">{{ $first['name'] }}</h3>
                <span class="badge badge-primary">First priority</span>
                <span class="badge badge-neutral">{{ $first['status'] }}</span>
            </div>
            <p class="mt-2 text-sm text-text-muted">The statistical forecast engine and its dashboard are live. Gemini integration and a separate AI backend are not.</p>
            <a href="{{ route('maintenance-intelligence.sparepart-analyst') }}" class="btn btn-primary mt-3 min-h-11">Open AI Sparepart Analyst</a>
            <ul class="mt-3 grid gap-2 text-sm text-text-muted sm:grid-cols-2">
                @foreach ($first['points'] as $point)
                    <li class="flex gap-2"><span class="text-primary" aria-hidden="true">&bull;</span>{{ $point }}</li>
                @endforeach
            </ul>
        </div>

        <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach (array_slice($agents, 1) as $agent)
                <div class="rounded-control border border-border p-4">
                    <h3 class="text-sm font-semibold text-text">{{ $agent['name'] }}</h3>
                    <span class="badge badge-neutral mt-2">{{ $agent['status'] }}</span>
                </div>
            @endforeach
        </div>
    </section>

    {{-- Virtual Maintenance Environment --}}
    <section class="card p-5 sm:p-6">
        <h2 class="text-lg font-semibold text-text">Virtual Maintenance Environment</h2>
        <p class="mt-1 text-sm text-text-muted">
            A 2D view of FreeDOMS maintenance activity. All seven parts below are built; each ships behind a feature switch that is off until an admin turns it on. VME only reads data, so switching it on or off changes nothing in PM, Greasing, or Oil Audit.
        </p>
        <a href="{{ route('maintenance-intelligence.vme') }}" class="btn btn-primary mt-3 min-h-11">Manage VME</a>
        <div class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($vme as $item)
                <div class="rounded-control border border-border p-4">
                    <h3 class="text-sm font-semibold text-text">{{ $item }}</h3>
                    <span class="badge badge-neutral mt-2">{{ $vmeOn ? 'Active' : 'Built, switched off' }}</span>
                </div>
            @endforeach
        </div>
    </section>

    {{-- Development Overview --}}
    <section class="card p-5 sm:p-6">
        <h2 class="text-lg font-semibold text-text">Development Overview</h2>
        <p class="mt-1 text-sm text-text-muted">The order the milestones are worked in. No progress status is tracked yet.</p>
        <div class="mt-5 grid gap-6 lg:grid-cols-2">
            @foreach ($roadmaps as $title => $steps)
                <div>
                    <h3 class="text-sm font-semibold text-text">{{ $title }}</h3>
                    <ol class="mt-3 space-y-2">
                        @foreach ($steps as $i => $step)
                            <li class="flex items-center gap-3 text-sm text-text-muted">
                                <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-surface-muted text-xs font-semibold tabular-nums text-text-muted">{{ $i + 1 }}</span>
                                {{ $step }}
                            </li>
                        @endforeach
                    </ol>
                </div>
            @endforeach
        </div>
    </section>
</div>
@endsection
