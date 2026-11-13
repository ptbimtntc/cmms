@extends('layouts.app')

@php($fullWidth = true)

@section('title', 'Machine History')

@section('content')
    @php($hasFilters = collect(request()->only(['search', 'area', 'machine_type']))->filter()->isNotEmpty())

    <div class="space-y-5">
        @guest
            {{-- Guests get no sidebar or topbar, so the page carries the brand itself. --}}
            <x-brand-logo :framed="false" class="h-7 w-auto" />
        @endguest

        <x-ui.page-header title="Machine History" description="Preventive Maintenance history per machine. Pick a machine to see all of its PM-nya." />

        {{-- Filters: plain GET, every field labelled. --}}
        <form method="GET" class="card flex flex-col gap-3 p-4 sm:flex-row sm:flex-wrap sm:items-end">
            <div class="grid gap-1.5 sm:w-64">
                <label for="mh-search" class="text-xs font-medium text-text-muted">Search</label>
                <input id="mh-search" type="text" name="search" value="{{ request('search') }}" placeholder="Machine number, type, or order" class="input lg:min-h-9">
            </div>
            <div class="grid gap-1.5 sm:w-40">
                <label for="mh-area" class="text-xs font-medium text-text-muted">Area</label>
                <select id="mh-area" name="area" class="select lg:min-h-9">
                    <option value="">All Areas</option>
                    @foreach ($areas as $area)
                        <option value="{{ $area }}" @selected(request('area') == $area)>{{ $area }}</option>
                    @endforeach
                </select>
            </div>
            <div class="grid gap-1.5 sm:w-48">
                <label for="mh-type" class="text-xs font-medium text-text-muted">Machine Type</label>
                <select id="mh-type" name="machine_type" class="select lg:min-h-9">
                    <option value="">All Machine Type</option>
                    @foreach ($machineTypes as $type)
                        <option value="{{ $type }}" @selected(request('machine_type') == $type)>{{ $type }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex gap-2 sm:ml-auto">
                <x-ui.button type="submit" class="flex-1 sm:flex-none lg:min-h-9">Filter</x-ui.button>
                <x-ui.button variant="ghost" href="{{ route('machine-history.index') }}" class="lg:min-h-9">Reset</x-ui.button>
            </div>
        </form>

        @if ($machines->isEmpty())
            <x-ui.empty-state title="No machine history found."
                :description="$hasFilters ? 'Loosen the filters, or reset them to see every machine.' : 'Machines from Machine Master show ucul di sini.'">
                @if ($hasFilters)
                    <x-ui.button variant="outline" href="{{ route('machine-history.index') }}">Reset filter</x-ui.button>
                @endif
            </x-ui.empty-state>
        @endif

        {{-- Desktop table. Keep this list first: infinite scroll matches lists by key and position. --}}
        <div @class(['card hidden overflow-hidden', 'md:block' => $machines->isNotEmpty()])>
            <div class="relative overflow-x-auto">
                <table class="data-table min-w-full">
                    <thead>
                        <tr>
                            <th scope="col">Machine Number</th>
                            <th scope="col">Machine Type</th>
                            <th scope="col">Area</th>
                            <th scope="col">Last PM</th>
                            <th scope="col"><span class="sr-only">Action</span></th>
                        </tr>
                    </thead>
                    <tbody data-infinite-list="machines">
                        @foreach ($machines as $machine)
                            @php($shownLastPm = $machine->lastPmOverride?->override_date ?? ($machine->last_pm ? \Carbon\Carbon::parse($machine->last_pm) : null))
                            <tr>
                                <td class="whitespace-nowrap font-semibold text-text">{{ $machine->machine_number }}</td>
                                <td>{{ $machine->machine_type }}</td>
                                <td>{{ $machine->area }}</td>
                                <td class="whitespace-nowrap">
                                    {{ $shownLastPm ? $shownLastPm->format('d-m-Y') : '-' }}
                                    @if ($machine->lastPmOverride)
                                        <x-ui.badge tone="warning" class="ml-1">Manual Override</x-ui.badge>
                                    @endif
                                </td>
                                <td class="text-right">
                                    <x-ui.button size="sm" variant="outline" href="{{ route('machine-history.show', ['machine_history' => $machine->machine_number, 'return_url' => request()->fullUrl()]) }}">View History</x-ui.button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Phone cards (below md). Second list with the same key. --}}
        <div class="space-y-3 md:hidden" data-infinite-list="machines">
            @foreach ($machines as $machine)
                @php($shownLastPm = $machine->lastPmOverride?->override_date ?? ($machine->last_pm ? \Carbon\Carbon::parse($machine->last_pm) : null))
                <article class="card min-w-0 p-4">
                    <h2 class="break-words text-base font-semibold text-text">{{ $machine->machine_number }}</h2>
                    <p class="text-sm text-text-muted">{{ $machine->machine_type }}</p>
                    <dl class="mt-3 grid grid-cols-2 gap-x-4 gap-y-2 border-t border-border pt-3 text-sm">
                        <div>
                            <dt class="text-xs text-text-muted">Area</dt>
                            <dd class="font-medium text-text">{{ $machine->area }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-text-muted">Last PM</dt>
                            <dd class="font-medium text-text">
                                {{ $shownLastPm ? $shownLastPm->format('d-m-Y') : '-' }}
                                @if ($machine->lastPmOverride)
                                    <x-ui.badge tone="warning" class="mt-1">Manual Override</x-ui.badge>
                                @endif
                            </dd>
                        </div>
                    </dl>
                    <x-ui.button variant="outline" class="mt-3 w-full" href="{{ route('machine-history.show', ['machine_history' => $machine->machine_number, 'return_url' => request()->fullUrl()]) }}">View History</x-ui.button>
                </article>
            @endforeach
        </div>

        <x-infinite-scroll :paginator="$machines" key="machines" />
    </div>
@endsection
