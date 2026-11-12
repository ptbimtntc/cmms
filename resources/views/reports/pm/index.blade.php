@extends('layouts.app')

@section('title', 'PM Report')

@section('content')
    @php
        $fullWidth = true;
        $label = 'text-xs font-medium text-text-muted';
        $fmt = fn ($d) => $d ? \Carbon\Carbon::parse($d)->format('d-m-Y') : '-';
        $detailUrl = fn ($pm) => route('machine-history.detail', ['machineNumber' => $pm->machine_number, 'pmSchedule' => $pm->id, 'from' => 'report-pm', 'return_url' => request()->fullUrl()]);
    @endphp

    <div class="space-y-5">
        <x-ui.page-header title="PM Report" description="Detail, filter, and drill-down for every PM Schedule record." />

        {{-- Filters: plain GET; the multi-selects sit last so their open lists never cover a control. --}}
        <form method="GET" class="card flex flex-col gap-3 p-4 sm:flex-row sm:flex-wrap sm:items-end">
            <div class="grid gap-1.5 sm:w-64">
                <label for="rp-search" class="{{ $label }}">Search</label>
                <input id="rp-search" type="text" name="search" value="{{ $search }}" placeholder="Machine Number / Order Number" class="input lg:min-h-9">
            </div>
            @if ($isAdmin)
                <div class="grid gap-1.5 sm:w-36">
                    <label for="rp-area" class="{{ $label }}">Area</label>
                    <select id="rp-area" name="area" class="select lg:min-h-9">
                        <option value="">All Areas</option>
                        @foreach ($areas as $a)
                            <option value="{{ $a }}" @selected($selectedArea === $a)>{{ $a }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div class="grid gap-1.5 sm:w-32">
                <label for="rp-year" class="{{ $label }}">Year</label>
                <select id="rp-year" name="year" class="select lg:min-h-9">
                    <option value="">All Years</option>
                    @foreach ($years as $y)
                        <option value="{{ $y }}" @selected($selectedYear == $y)>{{ $y }}</option>
                    @endforeach
                </select>
            </div>
            <div class="grid gap-1.5 sm:w-44">
                <label for="rp-type" class="{{ $label }}">Machine Type</label>
                <select id="rp-type" name="machine_type" class="select lg:min-h-9">
                    <option value="">All Machine Type</option>
                    @foreach ($machineTypes as $type)
                        <option value="{{ $type }}" @selected($selectedMachineType === $type)>{{ $type }}</option>
                    @endforeach
                </select>
            </div>
            <div class="grid gap-1.5 sm:w-40">
                <label for="rp-machine" class="{{ $label }}">Machine</label>
                <select id="rp-machine" name="machine" class="select lg:min-h-9">
                    <option value="">All Machines</option>
                    @foreach ($machines as $m)
                        <option value="{{ $m }}" @selected($selectedMachine === $m)>{{ $m }}</option>
                    @endforeach
                </select>
            </div>
            <div class="grid gap-1.5 sm:w-44">
                <label for="rp-pic" class="{{ $label }}">PIC</label>
                <select id="rp-pic" name="pic" class="select lg:min-h-9">
                    <option value="">All PIC</option>
                    @foreach ($pics as $p)
                        <option value="{{ $p }}" @selected($selectedPic === $p)>{{ \Illuminate\Support\Str::title(strtolower($p)) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="grid gap-1.5">
                <span class="{{ $label }}">Month</span>
                <x-checkbox-dropdown name="month" label="Month" :options="collect(range(1, 12))->mapWithKeys(fn ($m) => [$m => \Carbon\Carbon::create(null, $m, 1)->format('F')])->all()" :selected="$selectedMonths" />
            </div>
            <div class="grid gap-1.5">
                <span class="{{ $label }}">Status</span>
                <x-checkbox-dropdown name="status" label="Status"
                    :options="collect($statuses)->mapWithKeys(fn ($s) => [$s => str_replace('_', ' ', $s)])->all()"
                    :selected="$selectedStatuses" />
            </div>
            <div class="flex gap-2 sm:ml-auto">
                <x-ui.button type="submit" class="flex-1 sm:flex-none lg:min-h-9">Filter</x-ui.button>
                <x-ui.button variant="ghost" href="{{ route('reports.pm') }}" class="lg:min-h-9">Reset</x-ui.button>
            </div>
        </form>

        {{-- Trend charts: did each month reach the 96% target? --}}
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
            <section class="card p-5" aria-labelledby="rp-completion-title">
                <h2 id="rp-completion-title" class="font-semibold text-text">Completion Trend {{ $trendYear }}</h2>
                <p class="text-xs text-text-muted">Did each month reach the 96% target? Jan to Dec.</p>
                <div class="mt-3 h-56">
                    <canvas id="pmCompletionTrendChart" role="img" aria-label="Completion percent per month, {{ $trendYear }}"></canvas>
                </div>
            </section>
            <section class="card p-5" aria-labelledby="rp-closing-title">
                <h2 id="rp-closing-title" class="font-semibold text-text">Closing Trend {{ $trendYear }}</h2>
                <p class="text-xs text-text-muted">How many scheduled PMs were closed each month? Jan to Dec.</p>
                <div class="mt-3 h-56">
                    <canvas id="pmClosingTrendChart" role="img" aria-label="Closing percent per month, {{ $trendYear }}"></canvas>
                </div>
            </section>
        </div>

        @include('reports.partials.stats', ['cols' => 'grid-cols-2 sm:grid-cols-4 lg:grid-cols-7', 'items' => [
            ['label' => 'Total Schedule', 'value' => $summary['total']],
            ['label' => 'Finish', 'value' => $summary['finished']],
            ['label' => 'Finish On Time', 'value' => $summary['finished_on_time']],
            ['label' => 'Open', 'value' => $summary['open']],
            ['label' => 'Missed', 'value' => $summary['missed'], 'tone' => $summary['missed'] > 0 ? 'danger' : null],
            ['label' => 'Completion %', 'value' => $summary['completion_percent'].'%'],
            ['label' => 'Closing %', 'value' => $summary['closing_percent'].'%'],
        ]])

        @if ($schedules->isEmpty())
            <x-ui.empty-state title="No PM data found." description="Change the filters, or reset them to see every PM Schedule record." />
        @else
            {{-- Desktop table. Keep this list first: infinite scroll matches lists by key and position. --}}
            <div class="card hidden overflow-hidden md:block">
                <div class="relative overflow-x-auto">
                    <table class="data-table min-w-full">
                        <thead>
                            <tr>
                                <th scope="col">Plan Date</th>
                                <th scope="col">Action Date</th>
                                <th scope="col">Area</th>
                                <th scope="col">Machine Number</th>
                                <th scope="col">Machine Type</th>
                                <th scope="col">Order Number</th>
                                <th scope="col">PIC</th>
                                <th scope="col">Due Date</th>
                                <th scope="col">Status</th>
                                <th scope="col" class="sticky right-0 bg-surface-sunken"><span class="sr-only">Action</span></th>
                            </tr>
                        </thead>
                        <tbody data-infinite-list="schedules">
                            @foreach ($schedules as $pm)
                                <tr>
                                    <td class="whitespace-nowrap">{{ $fmt($pm->plan_date) }}</td>
                                    <td class="whitespace-nowrap">{{ $fmt($pm->actual_date) }}</td>
                                    <td>{{ $pm->area }}</td>
                                    <td class="whitespace-nowrap font-semibold text-text">{{ $pm->machine_number }}</td>
                                    <td>{{ $pm->machine_type }}</td>
                                    <td class="whitespace-nowrap">{{ $pm->order_number ?: '-' }}</td>
                                    <td>{{ $pm->pic ? \Illuminate\Support\Str::title(strtolower($pm->pic)) : '-' }}</td>
                                    <td class="whitespace-nowrap">{{ $fmt($pm->due_date) }}</td>
                                    <td>@include('pm-schedules.partials.status-badge', ['status' => $pm->status])</td>
                                    <td class="sticky right-0 bg-surface text-right shadow-[-10px_0_10px_-10px_rgb(15_23_42/0.25)]">
                                        <x-ui.button size="sm" variant="outline" href="{{ $detailUrl($pm) }}">View</x-ui.button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Phone cards (below md). Second list with the same key. --}}
            <div class="space-y-3 md:hidden" data-infinite-list="schedules">
                @foreach ($schedules as $pm)
                    <article class="card min-w-0 p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <h2 class="break-words text-base font-semibold text-text">{{ $pm->machine_number }}</h2>
                                <p class="text-sm text-text-muted">{{ $pm->machine_type }} · {{ $pm->area }}</p>
                            </div>
                            @include('pm-schedules.partials.status-badge', ['status' => $pm->status])
                        </div>
                        <dl class="mt-3 grid grid-cols-2 gap-x-4 gap-y-2 border-t border-border pt-3 text-sm">
                            <div><dt class="text-xs text-text-muted">Order Number</dt><dd class="break-words font-medium text-text">{{ $pm->order_number ?: '-' }}</dd></div>
                            <div><dt class="text-xs text-text-muted">PIC</dt><dd class="font-medium text-text">{{ $pm->pic ? \Illuminate\Support\Str::title(strtolower($pm->pic)) : '-' }}</dd></div>
                            <div><dt class="text-xs text-text-muted">Plan Date</dt><dd class="font-medium text-text">{{ $fmt($pm->plan_date) }}</dd></div>
                            <div><dt class="text-xs text-text-muted">Due Date</dt><dd class="font-medium text-text">{{ $fmt($pm->due_date) }}</dd></div>
                            <div><dt class="text-xs text-text-muted">Action Date</dt><dd class="font-medium text-text">{{ $fmt($pm->actual_date) }}</dd></div>
                        </dl>
                        <x-ui.button variant="outline" class="mt-3 w-full" href="{{ $detailUrl($pm) }}">View</x-ui.button>
                    </article>
                @endforeach
            </div>

            <x-infinite-scroll :paginator="$schedules" key="schedules" />
        @endif

        @include('reports.partials.forecast-note', ['title' => 'Forecasting / Predictive Maintenance', 'text' => 'Predicted problems, spareparts, and confidence per machine will appear here once available. Not implemented yet.'])
    </div>

    @include('reports.partials.chart-theme')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const trend = @json($trend);

            const percentScale = {
                beginAtZero: true,
                max: 100,
                border: { display: false },
                ticks: {
                    callback: (v) => v + '%',
                },
            };
            const monthScale = { grid: { display: false } };

            const tooltipLabel = (ctx) => ctx.raw === null ? 'No data' : ctx.raw + '%';

            // Fixed 96% target reference line, drawn on top of the bars:
            // same idea as the dashed target line on the Dashboard's trend
            // chart, just orange here per request and pinned to 96 rather
            // than a per-user target.
            const targetLinePlugin = {
                id: 'targetLine',
                afterDatasetsDraw(chart) {
                    const { ctx, chartArea, scales } = chart;
                    const y = scales.y.getPixelForValue(96);

                    ctx.save();
                    ctx.strokeStyle = '#f97316';
                    ctx.setLineDash([5, 4]);
                    ctx.lineWidth = 1.5;
                    ctx.beginPath();
                    ctx.moveTo(chartArea.left, y);
                    ctx.lineTo(chartArea.right, y);
                    ctx.stroke();

                    ctx.setLineDash([]);
                    ctx.fillStyle = '#f97316';
                    ctx.font = '600 11px ' + Chart.defaults.font.family;
                    ctx.textAlign = 'left';
                    ctx.fillText('Target 96%', chartArea.left + 4, y - 5);
                    ctx.restore();
                },
            };

            new Chart(document.getElementById('pmCompletionTrendChart'), {
                type: 'bar',
                data: {
                    labels: trend.map((t) => t.label),
                    datasets: [{
                        label: 'Completion %',
                        data: trend.map((t) => t.has_data ? t.completion_percent : null),
                        backgroundColor: window.reportChartTheme.bar,
                        borderRadius: 4,
                        maxBarThickness: 28,
                    }],
                },
                options: {
                    scales: { x: monthScale, y: percentScale },
                    plugins: {
                        legend: { display: false },
                        tooltip: { callbacks: { label: tooltipLabel } },
                    },
                },
                plugins: [targetLinePlugin],
            });

            new Chart(document.getElementById('pmClosingTrendChart'), {
                type: 'bar',
                data: {
                    labels: trend.map((t) => t.label),
                    datasets: [{
                        label: 'Closing %',
                        data: trend.map((t) => t.has_data ? t.closing_percent : null),
                        backgroundColor: window.reportChartTheme.bar,
                        borderRadius: 4,
                        maxBarThickness: 28,
                    }],
                },
                options: {
                    scales: { x: monthScale, y: percentScale },
                    plugins: {
                        legend: { display: false },
                        tooltip: { callbacks: { label: tooltipLabel } },
                    },
                },
                plugins: [targetLinePlugin],
            });
        });
    </script>

@endsection
