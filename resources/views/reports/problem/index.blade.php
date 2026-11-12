@extends('layouts.app')

@section('title', 'Problem Analysis Report')

@section('content')
    @php
        $fullWidth = true;
        $label = 'text-xs font-medium text-text-muted';
        $fmt = fn ($d) => $d ? \Carbon\Carbon::parse($d)->format('d-m-Y') : '-';
        $severityTone = fn ($s) => match ($s) { 'High' => 'danger', 'Medium' => 'warning', 'Low' => 'success', default => 'neutral' };
        $pic = fn ($p) => $p ? \Illuminate\Support\Str::title(strtolower($p)) : '-';
    @endphp

    <div class="space-y-5">
        <x-ui.page-header title="Problem Analysis Report" description="Analyze machine problems and findings: frequency by machine, by category, and repeated occurrences." />

        {{-- Filters: plain GET; the multi-select sits last so its open list never covers a control. --}}
        <form method="GET" class="card flex flex-col gap-3 p-4 sm:flex-row sm:flex-wrap sm:items-end">
            <div class="grid gap-1.5 sm:w-72">
                <label for="rpr-search" class="{{ $label }}">Search</label>
                <input id="rpr-search" type="text" name="search" value="{{ $search }}" placeholder="Machine Number / Problem / Order Number" class="input lg:min-h-9">
            </div>
            @if ($isAdmin)
                <div class="grid gap-1.5 sm:w-36">
                    <label for="rpr-area" class="{{ $label }}">Area</label>
                    <select id="rpr-area" name="area" class="select lg:min-h-9">
                        <option value="">All Areas</option>
                        @foreach ($areas as $a)
                            <option value="{{ $a }}" @selected($selectedArea === $a)>{{ $a }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div class="grid gap-1.5 sm:w-32">
                <label for="rpr-year" class="{{ $label }}">Year</label>
                <select id="rpr-year" name="year" class="select lg:min-h-9">
                    <option value="">All Years</option>
                    @foreach ($years as $y)
                        <option value="{{ $y }}" @selected($selectedYear == $y)>{{ $y }}</option>
                    @endforeach
                </select>
            </div>
            <div class="grid gap-1.5 sm:w-40">
                <label for="rpr-machine" class="{{ $label }}">Machine</label>
                <select id="rpr-machine" name="machine" class="select lg:min-h-9">
                    <option value="">All Machines</option>
                    @foreach ($machines as $m)
                        <option value="{{ $m }}" @selected($selectedMachine === $m)>{{ $m }}</option>
                    @endforeach
                </select>
            </div>
            <div class="grid gap-1.5 sm:w-44">
                <label for="rpr-type" class="{{ $label }}">Machine Type</label>
                <select id="rpr-type" name="machine_type" class="select lg:min-h-9">
                    <option value="">All Machine Type</option>
                    @foreach ($machineTypes as $type)
                        <option value="{{ $type }}" @selected($selectedMachineType === $type)>{{ $type }}</option>
                    @endforeach
                </select>
            </div>
            <div class="grid gap-1.5 sm:w-44">
                <label for="rpr-category" class="{{ $label }}">Category</label>
                <select id="rpr-category" name="category" class="select lg:min-h-9">
                    <option value="">All Categories</option>
                    @foreach ($categories as $c)
                        <option value="{{ $c }}" @selected($selectedCategory === $c)>{{ $c }}</option>
                    @endforeach
                </select>
            </div>
            <div class="grid gap-1.5">
                <span class="{{ $label }}">Month</span>
                <x-checkbox-dropdown name="month" label="Month"
                    :options="collect(range(1, 12))->mapWithKeys(fn ($m) => [$m => \Carbon\Carbon::create(null, $m, 1)->format('F')])->all()"
                    :selected="$selectedMonths" />
            </div>
            <div class="flex gap-2 sm:ml-auto">
                <x-ui.button type="submit" class="flex-1 sm:flex-none lg:min-h-9">Filter</x-ui.button>
                <x-ui.button variant="ghost" href="{{ route('reports.problem') }}" class="lg:min-h-9">Reset</x-ui.button>
            </div>
        </form>

        @include('reports.partials.stats', ['cols' => 'grid-cols-1 sm:grid-cols-3', 'items' => [
            ['label' => 'Total Problem/Finding', 'value' => $summary['total_problems']],
            ['label' => 'Top Problem Category', 'value' => $summary['top_category']['label'] ?? '-',
                'hint' => $summary['top_category'] ? $summary['top_category']['count'].' occurrences' : null],
            ['label' => 'Machine With Most Problems', 'value' => $summary['top_machine']['label'] ?? '-',
                'hint' => $summary['top_machine'] ? $summary['top_machine']['count'].' problems' : null],
        ]])

        <div class="grid gap-4 lg:grid-cols-2">
            <section class="card p-5" aria-labelledby="rpr-categories">
                <h2 id="rpr-categories" class="font-semibold text-text">Top Problem Categories</h2>
                <p class="mb-4 text-xs text-text-muted">Which categories come up most often in this period?</p>
                @if ($topCategories->isEmpty())
                    <p class="text-sm text-text-muted">No problem data for this period.</p>
                @else
                    @include('reports.partials.rank-bars', ['rows' => collect($topCategories)->map(fn ($row) => [
                        'label' => $row['label'], 'value' => $row['value'], 'percent' => $row['percent'],
                    ])->all()])
                @endif
            </section>

            <section class="card overflow-hidden" aria-labelledby="rpr-repeated">
                <div class="border-b border-border px-5 py-4">
                    <h2 id="rpr-repeated" class="font-semibold text-text">Repeated Problems</h2>
                    <p class="text-xs text-text-muted">Same machine, same problem, recorded more than once in this period.</p>
                </div>
                <ul class="divide-y divide-border">
                    @forelse ($repeatedProblems as $row)
                        <li class="flex items-center justify-between gap-3 px-5 py-3 text-sm">
                            <div class="min-w-0">
                                <p class="break-words font-medium text-text">{{ $row->machine_number }}: {{ $row->problem }}</p>
                                <p class="text-xs text-text-muted">{{ $row->category ?: '-' }}</p>
                            </div>
                            <x-ui.badge tone="warning" class="shrink-0">{{ $row->occurrences }}x</x-ui.badge>
                        </li>
                    @empty
                        <li class="px-5 py-6 text-center text-sm text-text-muted">No repeated problem found in this period.</li>
                    @endforelse
                </ul>
            </section>
        </div>

        @if ($problems->isEmpty())
            <x-ui.empty-state title="No Problem data found." description="Change the filters, or reset them to see every problem." />
        @else
            {{-- Desktop table. Keep this list first: infinite scroll matches lists by key and position. --}}
            <div class="card hidden overflow-hidden md:block">
                <div class="relative overflow-x-auto">
                    <table class="data-table min-w-full">
                        <thead>
                            <tr>
                                <th scope="col">Date</th>
                                <th scope="col">Area</th>
                                <th scope="col">Machine</th>
                                <th scope="col">Machine Type</th>
                                <th scope="col">Order Number</th>
                                <th scope="col">Problem</th>
                                <th scope="col">Category</th>
                                <th scope="col">Severity</th>
                                <th scope="col">PIC</th>
                            </tr>
                        </thead>
                        <tbody data-infinite-list="problems">
                            @foreach ($problems as $row)
                                <tr>
                                    <td class="whitespace-nowrap">{{ $fmt($row->actual_date) }}</td>
                                    <td>{{ $row->area }}</td>
                                    <td class="whitespace-nowrap font-semibold text-text">{{ $row->machine_number }}</td>
                                    <td>{{ $row->machine_type }}</td>
                                    <td class="whitespace-nowrap">{{ $row->order_number ?: '-' }}</td>
                                    <td class="min-w-44">{{ $row->problem }}</td>
                                    <td>{{ $row->category ?: '-' }}</td>
                                    <td><x-ui.badge :tone="$severityTone($row->severity)">{{ $row->severity ?: '-' }}</x-ui.badge></td>
                                    <td>{{ $pic($row->pic) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Phone cards (below md). Second list with the same key. --}}
            <div class="space-y-3 md:hidden" data-infinite-list="problems">
                @foreach ($problems as $row)
                    <article class="card min-w-0 p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <h2 class="break-words text-base font-semibold text-text">{{ $row->machine_number }}</h2>
                                <p class="text-sm text-text-muted">{{ $row->machine_type }} · {{ $row->area }}</p>
                            </div>
                            <x-ui.badge :tone="$severityTone($row->severity)" class="shrink-0">{{ $row->severity ?: '-' }}</x-ui.badge>
                        </div>
                        <dl class="mt-3 grid grid-cols-2 gap-x-4 gap-y-2 border-t border-border pt-3 text-sm">
                            <div><dt class="text-xs text-text-muted">Date</dt><dd class="font-medium text-text">{{ $fmt($row->actual_date) }}</dd></div>
                            <div><dt class="text-xs text-text-muted">Order Number</dt><dd class="break-words font-medium text-text">{{ $row->order_number ?: '-' }}</dd></div>
                            <div class="col-span-2"><dt class="text-xs text-text-muted">Problem</dt><dd class="break-words font-medium text-text">{{ $row->problem }} <span class="font-normal text-text-muted">({{ $row->category ?: '-' }})</span></dd></div>
                            <div><dt class="text-xs text-text-muted">PIC</dt><dd class="font-medium text-text">{{ $pic($row->pic) }}</dd></div>
                        </dl>
                    </article>
                @endforeach
            </div>

            <x-infinite-scroll :paginator="$problems" key="problems" />
        @endif

        @include('reports.partials.forecast-note', ['title' => 'Forecasting / Predictive Maintenance', 'text' => 'Likely next problem and frequency per machine will appear here once available. Not implemented yet.'])
    </div>
@endsection
