@extends('layouts.app')

@php($fullWidth = true)

{{-- "PM Status: {area}" is the page's public name (bookmarked by Production, pinned by tests). --}}
@section('title', 'PM Status: '.$currentArea)

@section('content')
    <div class="space-y-5">
        @guest
            {{-- Guests get no sidebar or topbar, so the page carries the brand itself. --}}
            <x-brand-logo :framed="false" class="h-7 w-auto" />
        @endguest

        <x-ui.page-header title="PM Status: {{ $currentArea }}" description="Preventive Maintenance progress per machine. Check GAP DAY sebelum meminta geser jadwal.">
            <x-slot:actions>
                {{-- Area switcher: each active area has its own public URL (not a filter), read
                     from the Area master so a new area appears here by itself.
                     bg-blue-600 marks the current area; tests look for it. --}}
                <nav class="inline-flex max-w-full overflow-x-auto rounded-control border border-border bg-surface-muted p-1" aria-label="Select an area">
                    @foreach ($areas as $areaOption)
                        @php($isCurrent = $areaSlug === $areaOption->slug)
                        <a href="{{ route('pm-status.show', $areaOption->slug) }}" @if ($isCurrent) aria-current="page" @endif
                            class="{{ $isCurrent ? 'bg-blue-600 text-white shadow-sm' : 'text-text-muted hover:bg-surface hover:text-text' }} inline-flex min-h-11 items-center whitespace-nowrap rounded-[calc(var(--radius-control)-4px)] px-4 text-sm font-semibold transition-colors lg:min-h-9">
                            {{ $areaOption->name }}
                        </a>
                    @endforeach
                </nav>
            </x-slot:actions>
        </x-ui.page-header>

        <x-ui.alert tone="info" title="How to read GAP DAY">
            <p><span class="font-semibold">GAP DAY</span> = today minus the Plan Date. The normal range is <span class="font-semibold">-14 to +14 days</span>.</p>
            <p class="mt-1">A red GAP DAY is outside that range, so it is a poor choice for a replacement machine.</p>
        </x-ui.alert>

        {{-- Desktop table. Rows for later months are appended by resources/js/pm-status/load-month.js. --}}
        <div class="card hidden overflow-hidden md:block">
            <div class="relative overflow-x-auto">
                <table class="data-table min-w-full">
                    <thead>
                        <tr>
                            <th scope="col">Status</th>
                            <th scope="col">Month</th>
                            <th scope="col">Machine</th>
                            <th scope="col">Plan Date</th>
                            <th scope="col" class="text-center">Gap Day</th>
                            <th scope="col">Actual PM Date</th>
                        </tr>
                    </thead>
                    <tbody data-pm-rows-desktop @if ($canOverrideStatus) data-pm-can-override @endif>
                        @include('pm-status-board._rows-desktop')
                        @if ($schedules->isEmpty())
                            <tr data-pm-empty>
                                <td colspan="6" class="py-10 text-center text-text-muted">No PM machine is still open.</td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Phone cards. --}}
        <div class="space-y-3 md:hidden" data-pm-rows-mobile @if ($canOverrideStatus) data-pm-can-override @endif>
            @include('pm-status-board._cards-mobile')
            @if ($schedules->isEmpty())
                <x-ui.empty-state data-pm-empty title="No PM machine is still open." description="Load the next month to see jadwal yang akan datang." />
            @endif
        </div>

        {{-- Loads one future month at a time (up to $maxMonthOffset ahead) without a reload. --}}
        <div class="flex flex-col items-center gap-2">
            <button type="button"
                data-pm-month-next
                data-url="{{ route('pm-status.month', $areaSlug) }}"
                data-offset="1"
                class="btn btn-outline min-h-11 w-full whitespace-normal text-center sm:w-auto">Click here to load machines for {{ $nextMonthLabel }}</button>
            <p data-pm-month-error class="form-error hidden" role="alert">Could not load the data, try again.</p>
        </div>
    </div>
@endsection
