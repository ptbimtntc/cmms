@extends('layouts.app')

@php
    // Standalone full-screen page (same flags as the Maintenance Intelligence
    // Center). VME is not part of the main sidebar.
    $hideSidebar = true;
    $hideTopbar = true;
    $fullWidth = true;
    $contentMaxWidth = 'max-w-[110rem]';
@endphp

@section('content')
<div class="space-y-3">
    <div class="card flex flex-wrap items-center justify-between gap-3 px-4 py-3">
        <div>
            <a href="{{ route('home') }}" class="btn btn-ghost btn-sm -ml-2 min-h-11 lg:min-h-9">Landing Page</a>
            <h1 class="font-signage text-xl font-semibold leading-tight text-text sm:text-2xl">Virtual Maintenance Environment</h1>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            {{-- Area filter: shows the map of one Area (next to Office and Workshop). Only Areas the viewer may see. --}}
            <label for="vme-area" class="flex items-center gap-2 text-xs font-medium text-text-muted">
                Area
                <select id="vme-area" class="select w-auto lg:min-h-9">
                    <option value="">All areas</option>
                    @foreach ($areaOptions as $option)
                        <option value="{{ $option->slug }}" @selected($selectedArea === $option->slug)>{{ $option->name }}</option>
                    @endforeach
                </select>
            </label>

            <div id="vme-hud" class="flex flex-wrap items-center gap-2 text-xs">
                <span id="vme-render" class="rounded-full bg-slate-100 px-3 py-1 font-medium text-slate-600" title="Status rendering peta">Peta: memuat…</span>
                <span id="vme-sync" class="rounded-full bg-slate-100 px-3 py-1 font-medium text-slate-600">Memuat…</span>
                <span id="vme-updated" class="text-text-muted"></span>
            </div>
        </div>
    </div>

    <div id="vme-banner" class="alert alert-warning hidden" role="status"></div>

    {{-- minmax(0,1fr): without it the wide canvas stretches the column past the viewport on mobile. --}}
    <div class="grid gap-3 lg:grid-cols-[minmax(0,1fr)_16rem]">
        <div class="relative min-w-0 self-start overflow-hidden rounded-card border border-border-strong bg-neutral-900 shadow-sm">
            <div id="vme-root"
                 data-state="loading"
                 data-area="{{ $selectedArea }}"
                 data-snapshot-url="{{ $snapshotUrl }}"
                 data-config-url="{{ $configUrl }}"
                 data-pic-url="{{ $picUrl }}"
                 data-machine-url="{{ $machineUrl }}"
                 data-ai-url="{{ $aiUrl }}"
                 class="w-full"
                 style="height: clamp(22rem, 72vh, 46rem); touch-action: none;"></div>

            <div class="absolute left-2 top-2 flex flex-col overflow-hidden rounded-control border border-neutral-600 bg-neutral-800/90 text-white shadow">
                <button type="button" id="vme-zoom-in" class="flex h-11 w-11 items-center justify-center text-lg leading-none hover:bg-neutral-700 focus-visible:outline-2 focus-visible:outline-white" aria-label="Perbesar">+</button>
                <button type="button" id="vme-zoom-out" class="flex h-11 w-11 items-center justify-center border-t border-neutral-600 text-lg leading-none hover:bg-neutral-700 focus-visible:outline-2 focus-visible:outline-white" aria-label="Perkecil">&minus;</button>
                <button type="button" id="vme-zoom-fit" class="flex h-11 w-11 items-center justify-center border-t border-neutral-600 text-[11px] font-semibold hover:bg-neutral-700 focus-visible:outline-2 focus-visible:outline-white" aria-label="Tampilkan seluruh peta">FIT</button>
            </div>

            {{-- Detail of the clicked PIC / machine. Filled by main.js from /vme/pics/{id} and /vme/machines/{id}. --}}
            <aside id="vme-detail" class="absolute inset-y-0 right-0 z-10 hidden w-full max-w-sm flex-col border-l border-border bg-surface shadow-popover" aria-live="polite">
                <header class="flex items-start justify-between gap-2 border-b border-border px-4 py-3">
                    <div class="min-w-0">
                        <p id="vme-detail-kind" class="text-xs font-medium text-text-muted"></p>
                        <h3 id="vme-detail-title" class="truncate text-base font-semibold text-text"></h3>
                    </div>
                    <div class="flex shrink-0 items-center gap-1">
                        <button type="button" id="vme-detail-reload" class="btn btn-ghost btn-sm min-h-11 lg:min-h-9" title="Reload the detail">Reload</button>
                        <button type="button" id="vme-detail-close" class="btn btn-ghost min-h-11 min-w-11 px-0 text-lg leading-none lg:min-h-9 lg:min-w-9" aria-label="Tutup detail">&times;</button>
                    </div>
                </header>
                <div id="vme-detail-body" class="flex-1 overflow-y-auto px-4 py-3 text-sm"></div>
            </aside>

            <div id="vme-overlay" class="absolute inset-0 hidden flex-col items-center justify-center gap-2 bg-slate-900/90 p-6 text-center text-sm text-slate-100"></div>
        </div>

        <aside class="card p-4">
            <h2 class="font-semibold text-text">Live Activity</h2>
            <p class="mt-1 text-xs text-text-muted">Only PICs who actually started an activity in FreeDOMS.</p>
            <ul id="vme-list" class="mt-3 space-y-2 text-sm"></ul>
            <p id="vme-empty" class="mt-3 hidden text-sm text-text-muted">No active activity.</p>
        </aside>
    </div>
</div>

@vite('resources/js/vme/main.js')
@endsection
