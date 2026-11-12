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
    // Status labels are honest: nothing here is implemented yet. Add a real
    // status (e.g. "In Development", "Active") only when the backend exists.
    $agents = [
        ['name' => 'AI Sparepart Analyst', 'status' => 'In Development', 'priority' => true,
         'points' => [
             'Forecast kebutuhan mandatory sparepart',
             'Forecast sparepart tambahan berdasarkan histori aktual',
             'Analisis pola pemakaian sparepart',
             'Rekomendasi persiapan sparepart',
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
        <a href="{{ route('home') }}" class="btn btn-ghost -ml-2 min-h-11">Kembali ke Landing Page</a>
        <h1 class="mt-3 font-signage text-2xl font-semibold leading-tight text-text sm:text-3xl">Maintenance Intelligence Center</h1>
        <p class="mt-1 text-sm text-text-muted">
            Central management for AI-powered maintenance analysis and virtual maintenance monitoring.
            Halaman ini hanya dapat diakses ADMIN dan bukan bagian dari menu utama.
        </p>
        <div class="mt-4 flex flex-wrap gap-2 text-xs">
            <span class="badge badge-neutral">AI Maintenance Intelligence: Planned</span>
            <span class="badge badge-neutral">Virtual Maintenance Environment: Planned</span>
        </div>
    </div>

    {{-- AI Maintenance Intelligence --}}
    <section class="card p-5 sm:p-6">
        <h2 class="text-lg font-semibold text-text">AI Maintenance Intelligence</h2>
        <p class="mt-1 text-sm text-text-muted">
            AI Agent adalah fitur tambahan; CMMS tetap berfungsi penuh tanpanya. Baru AI Sparepart Analyst yang sedang dikembangkan.
        </p>

        @php $first = $agents[0]; @endphp
        <div class="mt-5 rounded-card border border-primary/30 bg-primary-light p-5">
            <div class="flex flex-wrap items-center gap-2">
                <h3 class="text-base font-semibold text-text">{{ $first['name'] }}</h3>
                <span class="badge badge-primary">Prioritas pertama</span>
                <span class="badge badge-neutral">{{ $first['status'] }}</span>
            </div>
            <p class="mt-2 text-sm text-text-muted">Engine forecast statistik dan dashboard sudah tersedia; integrasi Gemini dan backend AI terpisah belum.</p>
            <a href="{{ route('maintenance-intelligence.sparepart-analyst') }}" class="btn btn-primary mt-3 min-h-11">Buka AI Sparepart Analyst</a>
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
            Visualisasi 2D aktivitas maintenance FreeDOMS. Tahap awal tersedia: dunia 2D dan karakter PIC berbasis aktivitas aktual (default OFF). Karakter AI dan komponen lain masih direncanakan.
        </p>
        <a href="{{ route('maintenance-intelligence.vme') }}" class="btn btn-primary mt-3 min-h-11">Kelola VME</a>
        <div class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($vme as $item)
                <div class="rounded-control border border-border p-4">
                    <h3 class="text-sm font-semibold text-text">{{ $item }}</h3>
                    <span class="badge badge-neutral mt-2">Planned</span>
                </div>
            @endforeach
        </div>
    </section>

    {{-- Development Overview --}}
    <section class="card p-5 sm:p-6">
        <h2 class="text-lg font-semibold text-text">Development Overview</h2>
        <p class="mt-1 text-sm text-text-muted">Urutan milestone pengembangan. Belum ada status progres yang dilacak.</p>
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
