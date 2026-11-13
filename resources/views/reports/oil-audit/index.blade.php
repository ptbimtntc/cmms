@extends('layouts.app')

@section('title', 'Oil Audit Report')

@section('content')
    @php
        $fullWidth = true;
        $label = 'text-xs font-medium text-text-muted';
        $historyUrl = fn ($machineNumber) => route('oil-audits.history', [$machineNumber, 'from' => 'report', 'return' => request()->getQueryString() ?? '']);
        $maxProblemTotal = (int) $problemFrequency->max('total');
        $maxRepeat = (int) $repeatFindingMachines->max('events');
        // Fixed colour per finding slot (validated categorical palette, same order as OilAudit::FINDING_OPTIONS).
        $findingPalette = ['Kapstan 1' => '#2a78d6', 'Kapstan 2' => '#eb6834', 'Kapstan 3' => '#1baf7a', 'Kapstan 4' => '#eda100', 'Mainshaft' => '#e87ba4', 'Innershaft' => '#008300'];
        $findingColor = fn ($name) => $findingPalette[$name] ?? ($name === 'Lainnya' ? '#8a94a6' : '#c3cad5');
        $findingChart = $findingFrequency->map(fn ($row) => ['finding' => $row->finding, 'total' => $row->total, 'percent' => $row->percent, 'color' => $findingColor($row->finding)])->values();
    @endphp

    <div class="space-y-5">
        <x-ui.page-header title="Oil Audit Report" description="Every machine in Oil Audit scope, one row each, with its latest audit." />

        {{-- Filters: plain GET; the multi-selects sit last so their open lists never cover a control. --}}
        <form method="GET" class="card flex flex-col gap-3 p-4 sm:flex-row sm:flex-wrap sm:items-end">
            <div class="grid gap-1.5 sm:w-64">
                <label for="ro-search" class="{{ $label }}">Search</label>
                <input id="ro-search" type="text" name="search" value="{{ $search }}" placeholder="Machine Number / Type / Description" class="input lg:min-h-9">
            </div>
            <div class="grid gap-1.5 sm:w-44">
                <label for="ro-type" class="{{ $label }}">Machine Type</label>
                <select id="ro-type" name="machine_type" class="select lg:min-h-9">
                    <option value="">All Machine Types</option>
                    @foreach ($machineTypes as $type)
                        <option value="{{ $type }}" @selected($selectedMachineType === $type)>{{ $type }}</option>
                    @endforeach
                </select>
            </div>
            <div class="grid gap-1.5 sm:w-52">
                <label for="ro-year" class="{{ $label }}">Year</label>
                <select id="ro-year" name="year" class="select lg:min-h-9">
                    <option value="">All Years (latest audit)</option>
                    @foreach ($years as $y)
                        <option value="{{ $y }}" @selected($selectedYear == $y)>{{ $y }}</option>
                    @endforeach
                </select>
            </div>
            <div class="grid gap-1.5">
                <span class="{{ $label }}">Condition</span>
                <x-checkbox-dropdown name="condition" label="Condition" :options="$conditions" :selected="$selectedConditions" />
            </div>
            <div class="grid gap-1.5">
                <span class="{{ $label }}">Month</span>
                <x-checkbox-dropdown name="month" label="Month"
                    :options="collect(range(1, 12))->mapWithKeys(fn ($m) => [$m => \Carbon\Carbon::create(null, $m, 1)->format('F')])->all()"
                    :selected="$selectedMonths" />
            </div>
            <div class="flex gap-2 sm:ml-auto">
                <x-ui.button type="submit" class="flex-1 sm:flex-none lg:min-h-9">Filter</x-ui.button>
                <x-ui.button variant="ghost" href="{{ route('reports.oil-audit') }}" class="lg:min-h-9">Reset</x-ui.button>
            </div>
        </form>

        @include('reports.partials.stats', ['cols' => 'grid-cols-1 sm:grid-cols-3', 'items' => [
            ['label' => 'Total Machines in Oil Audit Scope', 'value' => $summary['total_machines']],
            ['label' => 'Machines Never Audited', 'value' => $summary['never_audited']],
            ['label' => 'Machines With Latest Finding', 'value' => $summary['with_latest_finding'], 'tone' => $summary['with_latest_finding'] > 0 ? 'danger' : null],
        ]])

        {{-- Oil Audit Analysis: confirmed via follow-up, scoped by the report filters. --}}
        <section aria-labelledby="ro-analysis" class="space-y-4">
            <h2 id="ro-analysis" class="font-signage text-2xl font-semibold text-text">Oil Audit Analysis</h2>

            {{-- Distribusi Finding: share of each part (Kapstan 1-4, Mainshaft, ...) among all follow-up findings.
                 Each finding keeps one colour wherever it ranks, from a colour-blind-checked palette in fixed order;
                 "Lainnya" and legacy rows without a finding are grey. The legend always names the slice and its share. --}}
            <section class="card p-5" aria-labelledby="ro-dist">
                <div class="mb-4 flex items-start justify-between gap-3">
                    <div>
                        <h3 id="ro-dist" class="font-semibold text-text">Finding Distribution</h3>
                        <p class="mt-0.5 text-xs text-text-muted">Which part shows up most often in follow-up findings?</p>
                    </div>
                    <x-ui.badge tone="primary" class="shrink-0">{{ $findingFrequency->sum('total') }} findings</x-ui.badge>
                </div>

                @if ($findingFrequency->isEmpty())
                    <p class="rounded-control border border-dashed border-border-strong py-8 text-center text-sm text-text-muted">No follow-up finding in this filter scope yet.</p>
                @else
                    <div class="mx-auto flex max-w-3xl flex-col items-center gap-6 sm:flex-row">
                        <div class="h-56 w-56 shrink-0">
                            <canvas id="findingDistributionChart" role="img" aria-label="Distribusi finding follow-up"></canvas>
                        </div>
                        <ul class="w-full min-w-0 max-w-sm space-y-2">
                            @foreach ($findingFrequency as $row)
                                <li class="flex items-center justify-between gap-3 text-sm">
                                    <span class="flex min-w-0 items-center gap-2">
                                        <span class="h-3 w-3 shrink-0 rounded-full" style="background: {{ $findingColor($row->finding) }}" aria-hidden="true"></span>
                                        <span class="break-words text-text">{{ $row->finding }}</span>
                                    </span>
                                    <span class="shrink-0 font-semibold tabular-nums text-text">{{ $row->percent }}% <span class="font-normal text-text-muted">({{ $row->total }})</span></span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </section>

            <div class="grid gap-4 lg:grid-cols-2">
                <section class="card p-5" aria-labelledby="ro-problem">
                    <div class="mb-4 flex items-start justify-between gap-3">
                        <div>
                            <h3 id="ro-problem" class="font-semibold text-text">Most Frequent Problem and Finding</h3>
                            <p class="mt-0.5 text-xs text-text-muted">Top 10 problem and finding pairs from follow-up results</p>
                        </div>
                        <x-ui.badge tone="primary" class="shrink-0">{{ $problemFrequency->sum('total') }} findings</x-ui.badge>
                    </div>
                    @if ($problemFrequency->isEmpty())
                        <p class="text-sm text-text-muted">No follow-up problem in this filter scope yet.</p>
                    @else
                        @include('reports.partials.rank-bars', ['numbered' => true, 'rows' => $problemFrequency->map(fn ($row) => [
                            'label' => $row->problem.': '.$row->finding,
                            'value' => $row->total,
                            'percent' => $maxProblemTotal > 0 ? $row->total / $maxProblemTotal * 100 : 0,
                        ])->all()])
                    @endif
                </section>

                <section class="card p-5" aria-labelledby="ro-repeat">
                    <div class="mb-4 flex items-start justify-between gap-3">
                        <div>
                            <h3 id="ro-repeat" class="font-semibold text-text">Machines with Repeat Findings</h3>
                            <p class="mt-0.5 text-xs text-text-muted">Top 10 machines with {{ $repeatFindingMin }} or more follow-ups across different audits</p>
                        </div>
                        <x-ui.badge tone="warning" class="shrink-0">&ge; {{ $repeatFindingThreshold }}&times; = repeat</x-ui.badge>
                    </div>
                    @if ($repeatFindingMachines->isEmpty())
                        <p class="text-sm text-text-muted">No machine has repeat findings in this filter scope yet.</p>
                    @else
                        @include('reports.partials.rank-bars', ['numbered' => true, 'rows' => $repeatFindingMachines->map(fn ($row) => [
                            'label' => $row->machine_number,
                            'value' => $row->events.'×',
                            'percent' => $maxRepeat > 0 ? $row->events / $maxRepeat * 100 : 0,
                            'href' => $historyUrl($row->machine_number),
                            'badge' => $row->events >= $repeatFindingThreshold ? 'Repeat' : null,
                        ])->all()])
                    @endif
                </section>
            </div>
        </section>

        @if ($machines->isEmpty())
            <x-ui.empty-state title="No machines found." description="Change the filters, or reset them to see every machine in scope." />
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
                                <th scope="col">Latest Audit Condition</th>
                                <th scope="col">Latest Audit Date</th>
                                <th scope="col">PIC</th>
                                <th scope="col">Follow-up Status</th>
                                <th scope="col" class="sticky right-0 bg-surface-sunken"><span class="sr-only">Action</span></th>
                            </tr>
                        </thead>
                        <tbody data-infinite-list="machines">
                            @foreach ($machines as $machine)
                                @php($audit = $machine->latestOilAudit)
                                <tr>
                                    <td class="whitespace-nowrap font-semibold text-text">{{ $machine->machine_number }}</td>
                                    <td>{{ $machine->machine_type }}</td>
                                    <td>{{ $machine->area }}</td>
                                    <td>@include('reports.oil-audit.partials.condition', ['audit' => $audit])</td>
                                    <td class="whitespace-nowrap">{{ $audit ? $audit->audited_at->format('d-m-Y') : '-' }}</td>
                                    <td>{{ $audit->audited_by_name ?? '-' }}</td>
                                    <td>@include('reports.oil-audit.partials.follow-up', ['audit' => $audit])</td>
                                    <td class="sticky right-0 bg-surface text-right shadow-[-10px_0_10px_-10px_rgb(15_23_42/0.25)]">
                                        <x-ui.button size="sm" variant="outline" href="{{ $historyUrl($machine->machine_number) }}">View</x-ui.button>
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
                    @php($audit = $machine->latestOilAudit)
                    <article class="card min-w-0 p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <h2 class="break-words text-base font-semibold text-text">{{ $machine->machine_number }}</h2>
                                <p class="text-sm text-text-muted">{{ $machine->machine_type }} · {{ $machine->area }}</p>
                            </div>
                            @include('reports.oil-audit.partials.condition', ['audit' => $audit])
                        </div>
                        <dl class="mt-3 grid grid-cols-2 gap-x-4 gap-y-2 border-t border-border pt-3 text-sm">
                            <div><dt class="text-xs text-text-muted">Latest Audit Date</dt><dd class="font-medium text-text">{{ $audit ? $audit->audited_at->format('d-m-Y') : '-' }}</dd></div>
                            <div><dt class="text-xs text-text-muted">PIC</dt><dd class="font-medium text-text">{{ $audit->audited_by_name ?? '-' }}</dd></div>
                            <div class="col-span-2"><dt class="text-xs text-text-muted">Follow-up Status</dt><dd class="mt-0.5">@include('reports.oil-audit.partials.follow-up', ['audit' => $audit])</dd></div>
                        </dl>
                        <x-ui.button variant="outline" class="mt-3 w-full" href="{{ $historyUrl($machine->machine_number) }}">View</x-ui.button>
                    </article>
                @endforeach
            </div>

            <x-infinite-scroll :paginator="$machines" key="machines" />
        @endif
    </div>

    @if ($findingFrequency->isNotEmpty())
        @include('reports.partials.chart-theme')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const findings = @json($findingChart);
                new Chart(document.getElementById('findingDistributionChart'), {
                    type: 'pie',
                    data: {
                        labels: findings.map((f) => f.finding),
                        datasets: [{
                            data: findings.map((f) => f.total),
                            backgroundColor: findings.map((f) => f.color),
                            borderWidth: 2,
                            borderColor: window.reportChartTheme.surface,
                        }],
                    },
                    options: {
                        plugins: {
                            legend: { display: false },
                            tooltip: { callbacks: { label: (ctx) => findings[ctx.dataIndex].finding + ': ' + findings[ctx.dataIndex].percent + '% (' + findings[ctx.dataIndex].total + ')' } },
                        },
                    },
                });
            });
        </script>
    @endif
@endsection
