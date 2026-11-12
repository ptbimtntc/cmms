@extends('layouts.app')

@section('title', 'Machine Report')

@section('content')
    @php
        $fullWidth = true;
        $label = 'text-xs font-medium text-text-muted';
        $historyUrl = fn ($row) => route('machine-history.show', ['machine_history' => $row->machine_number, 'from' => 'report-machine', 'return_url' => request()->fullUrl()]);
        $stats = [
            ['label' => 'Total Machine', 'value' => $summary['total_machine']],
            ['label' => 'Active Machine', 'value' => $summary['active_machine']],
            ['label' => 'Inactive Machine', 'value' => $summary['inactive_machine']],
        ];
        if ($showGearboxMetric) {
            $stats[] = ['label' => 'Gearbox Machines (WWD)', 'value' => $gearboxCount];
        }
    @endphp

    <div class="space-y-5">
        <x-ui.page-header title="Machine Report" description="Summary and analysis across every machine. For per-machine history, open a machine below." />

        {{-- Filters: plain GET; the multi-select sits last so its open list never covers a control. --}}
        <form method="GET" class="card flex flex-col gap-3 p-4 sm:flex-row sm:flex-wrap sm:items-end">
            <div class="grid gap-1.5 sm:w-64">
                <label for="rm-search" class="{{ $label }}">Search</label>
                <input id="rm-search" type="text" name="search" value="{{ $search }}" placeholder="Machine Number / Machine Type" class="input lg:min-h-9">
            </div>
            @if ($isAdmin)
                <div class="grid gap-1.5 sm:w-36">
                    <label for="rm-area" class="{{ $label }}">Area</label>
                    <select id="rm-area" name="area" class="select lg:min-h-9">
                        <option value="">All Areas</option>
                        @foreach ($areas as $a)
                            <option value="{{ $a }}" @selected($selectedArea === $a)>{{ $a }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div class="grid gap-1.5 sm:w-44">
                <label for="rm-type" class="{{ $label }}">Machine Type</label>
                <select id="rm-type" name="machine_type" class="select lg:min-h-9">
                    <option value="">All Machine Type</option>
                    @foreach ($machineTypes as $type)
                        <option value="{{ $type }}" @selected($selectedMachineType === $type)>{{ $type }}</option>
                    @endforeach
                </select>
            </div>
            @if ($groups->isNotEmpty())
                <div class="grid gap-1.5 sm:w-44">
                    <label for="rm-group" class="{{ $label }}">Group</label>
                    <select id="rm-group" name="group_id" class="select lg:min-h-9">
                        <option value="">All Groups</option>
                        @foreach ($groups as $g)
                            <option value="{{ $g->id }}" @selected((int) $selectedGroupId === $g->id)>{{ $g->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div class="grid gap-1.5">
                <span class="{{ $label }}">Status</span>
                <x-checkbox-dropdown name="status" label="Status" :options="collect($statuses)->mapWithKeys(fn ($s) => [$s => $s])->all()" :selected="$selectedStatuses" />
            </div>
            <div class="flex gap-2 sm:ml-auto">
                <x-ui.button type="submit" class="flex-1 sm:flex-none lg:min-h-9">Filter</x-ui.button>
                <x-ui.button variant="ghost" href="{{ route('reports.machine') }}" class="lg:min-h-9">Reset</x-ui.button>
            </div>
        </form>

        @include('reports.partials.stats', ['cols' => 'grid-cols-2 sm:grid-cols-3'.($showGearboxMetric ? ' lg:grid-cols-4' : ''), 'items' => $stats])

        @if ($machines->isEmpty())
            <x-ui.empty-state title="No Machine data found." description="Change the filters, or reset them to see every machine." />
        @else
            {{-- Desktop table. Keep this list first: infinite scroll matches lists by key and position. --}}
            <div class="card hidden overflow-hidden md:block">
                <div class="relative overflow-x-auto">
                    <table class="data-table min-w-full">
                        <thead>
                            <tr>
                                <th scope="col">Machine Number</th>
                                <th scope="col">Machine Type</th>
                                <th scope="col">Area</th>
                                <th scope="col">Group</th>
                                <th scope="col">Status</th>
                                <th scope="col">Last PM</th>
                                <th scope="col">Next PM</th>
                                <th scope="col" class="text-right">PM Count</th>
                                <th scope="col" class="sticky right-0 bg-surface-sunken"><span class="sr-only">Action</span></th>
                            </tr>
                        </thead>
                        <tbody data-infinite-list="machines">
                            @foreach ($machines as $row)
                                <tr>
                                    <td class="whitespace-nowrap font-semibold text-text">{{ $row->machine_number }}</td>
                                    <td>{{ $row->machine_type }}</td>
                                    <td>{{ $row->area }}</td>
                                    <td>{{ $row->group_name ?: '-' }}</td>
                                    <td><x-ui.badge :tone="$row->status === 'ACTIVE' ? 'success' : 'neutral'">{{ $row->status }}</x-ui.badge></td>
                                    <td class="whitespace-nowrap">{{ $row->last_pm ? \Carbon\Carbon::parse($row->last_pm)->format('d-m-Y') : '-' }}</td>
                                    <td class="whitespace-nowrap">{{ $row->next_pm ? $row->next_pm->format('d-m-Y') : '-' }}</td>
                                    <td class="text-right tabular-nums">{{ $row->pm_count ?? 0 }}</td>
                                    <td class="sticky right-0 bg-surface text-right shadow-[-10px_0_10px_-10px_rgb(15_23_42/0.25)]">
                                        <x-ui.button size="sm" variant="outline" href="{{ $historyUrl($row) }}">View</x-ui.button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Phone cards (below md). Second list with the same key. --}}
            <div class="space-y-3 md:hidden" data-infinite-list="machines">
                @foreach ($machines as $row)
                    <article class="card min-w-0 p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <h2 class="break-words text-base font-semibold text-text">{{ $row->machine_number }}</h2>
                                <p class="text-sm text-text-muted">{{ $row->machine_type }} · {{ $row->area }} · {{ $row->group_name ?: '-' }}</p>
                            </div>
                            <x-ui.badge :tone="$row->status === 'ACTIVE' ? 'success' : 'neutral'" class="shrink-0">{{ $row->status }}</x-ui.badge>
                        </div>
                        <dl class="mt-3 grid grid-cols-3 gap-x-4 gap-y-2 border-t border-border pt-3 text-sm">
                            <div><dt class="text-xs text-text-muted">Last PM</dt><dd class="font-medium text-text">{{ $row->last_pm ? \Carbon\Carbon::parse($row->last_pm)->format('d-m-Y') : '-' }}</dd></div>
                            <div><dt class="text-xs text-text-muted">Next PM</dt><dd class="font-medium text-text">{{ $row->next_pm ? $row->next_pm->format('d-m-Y') : '-' }}</dd></div>
                            <div><dt class="text-xs text-text-muted">PM Count</dt><dd class="font-medium tabular-nums text-text">{{ $row->pm_count ?? 0 }}</dd></div>
                        </dl>
                        <x-ui.button variant="outline" class="mt-3 w-full" href="{{ $historyUrl($row) }}">View</x-ui.button>
                    </article>
                @endforeach
            </div>

            <x-infinite-scroll :paginator="$machines" key="machines" />
        @endif
    </div>
@endsection
