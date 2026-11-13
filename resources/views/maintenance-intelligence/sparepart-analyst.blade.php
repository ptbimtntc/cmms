@extends('layouts.app')

@php
    $hideSidebar = true;
    $hideTopbar = true;
    $fullWidth = true;
    $contentMaxWidth = 'max-w-[110rem]';

    $card = 'card p-5 sm:p-6';
    $th = 'whitespace-nowrap';
    $td = 'align-top';
    $input = 'input lg:min-h-9';
    $select = 'select lg:min-h-9';
    $n = fn ($v, $d = 0) => number_format((float) $v, $d, ',', '.');
    $levelClass = ['LIKELY' => 'badge-success', 'POSSIBLE' => 'badge-warning', 'RARE' => 'badge-neutral'];
    $trendLabel = ['INCREASING' => '↑ Naik', 'DECREASING' => '↓ Turun', 'STABLE' => '→ Stabil', 'UNKNOWN' => '-'];
    $q = $run?->summary['quality'] ?? null;
    $sections = ['overview' => 'Overview', 'machine' => 'Machine Forecast', 'demand' => 'Sparepart Demand', 'type' => 'Machine Type', 'history' => 'Historical Usage', 'evaluation' => 'Evaluation', 'quality' => 'Data Quality', 'recs' => 'Recommendations', 'runs' => 'History'];
@endphp

@section('content')
<div class="space-y-6">

    <div class="{{ $card }}">
        <div class="flex flex-wrap items-center gap-2 text-sm">
            <a href="{{ route('maintenance-intelligence.index') }}" class="btn btn-ghost -ml-2 min-h-11">Maintenance Intelligence Center</a>
            <a href="{{ route('home') }}" class="btn btn-ghost min-h-11">Landing Page</a>
        </div>
        <h1 class="mt-3 font-signage text-2xl font-semibold leading-tight text-text sm:text-3xl">AI Sparepart Analyst</h1>
        <p class="mt-1 text-sm text-text-muted">
            Forecasts sparepart needs per machine from PM Sparepart Usage history. The numbers come from a statistical engine
            (version {{ \App\Services\SparepartForecast\ForecastEngine::VERSION }}); the AI explanation (Gemini) is not wired up yet.
            Mandatory Sparepart and Sparepart Injection are not used as sources.
        </p>

        @if (session('success'))<div class="alert alert-success mt-4" role="status">{{ session('success') }}</div>@endif
        @if (session('error'))<div class="alert alert-danger mt-4" role="alert">{{ session('error') }}</div>@endif
        @if ($errors->any())<div class="alert alert-danger mt-4" role="alert">{{ $errors->first() }}</div>@endif

        <form method="POST" action="{{ route('maintenance-intelligence.sparepart-analyst.run') }}" class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
            @csrf
            <div>
                <label class="text-xs font-medium text-text-muted">Period</label>
                <select name="period_type" class="{{ $select }}">
                    <option value="MONTH">Bulanan</option>
                    <option value="WEEK">Mingguan</option>
                </select>
            </div>
            <div>
                <label class="text-xs font-medium text-text-muted">A date inside the period</label>
                <input type="date" name="period_date" value="{{ now()->toDateString() }}" class="{{ $input }}" required>
            </div>
            <div>
                <label class="text-xs font-medium text-text-muted">Area (analysis scope)</label>
                <select name="area" class="{{ $select }}"><option value="">All</option>@foreach ($areas as $a)<option>{{ $a }}</option>@endforeach</select>
            </div>
            <div>
                <label class="text-xs font-medium text-text-muted">Machine Type (analysis scope)</label>
                <select name="machine_type" class="{{ $select }}"><option value="">All</option>@foreach ($machineTypes as $t)<option>{{ $t }}</option>@endforeach</select>
            </div>
            <div class="flex items-end">
                <button class="btn btn-primary min-h-11 w-full">Run analysis</button>
            </div>
        </form>
    </div>

    @if (! $run)
        <div class="{{ $card }} text-sm text-text-muted">No analysis yet. Run the first one from the form above.</div>
    @elseif ($run->status !== 'COMPLETED')
        <div class="{{ $card }} text-sm text-danger">Run #{{ $run->id }} berstatus {{ $run->status }}. {{ $run->error }}</div>
    @else
        <nav class="flex gap-2 overflow-x-auto text-sm">
            @foreach ($sections as $id => $label)
                <a href="#{{ $id }}" class="inline-flex min-h-11 shrink-0 items-center rounded-full border border-border bg-surface px-4 font-medium text-text-muted hover:text-text lg:min-h-9">{{ $label }}</a>
            @endforeach
        </nav>

        {{-- Filters on the displayed run --}}
        <form method="GET" class="{{ $card }} grid gap-3 sm:grid-cols-2 lg:grid-cols-6">
            <input type="hidden" name="run" value="{{ $run->id }}">
            <div><label class="text-xs font-medium text-text-muted">Area</label>
                <select name="area" class="{{ $select }}"><option value="">All</option>@foreach ($areas as $a)<option @selected(($filters['area'] ?? '') === $a)>{{ $a }}</option>@endforeach</select></div>
            <div><label class="text-xs font-medium text-text-muted">Machine Type</label>
                <select name="machine_type" class="{{ $select }}"><option value="">All</option>@foreach ($machineTypes as $t)<option @selected(($filters['machine_type'] ?? '') === $t)>{{ $t }}</option>@endforeach</select></div>
            <div><label class="text-xs font-medium text-text-muted">Machine</label>
                <input name="machine" value="{{ $filters['machine'] ?? '' }}" placeholder="Machine no." class="{{ $input }}"></div>
            <div class="lg:col-span-2"><label class="text-xs font-medium text-text-muted">Sparepart</label>
                <input name="sparepart" value="{{ $filters['sparepart'] ?? '' }}" placeholder="SAP number / description" class="{{ $input }}"></div>
            <div class="flex items-end gap-2">
                <button class="btn btn-primary min-h-11 flex-1">Filter</button>
                <a href="{{ route('maintenance-intelligence.sparepart-analyst.export', $run) }}" class="btn btn-outline min-h-11">Excel</a>
            </div>
        </form>

        {{-- 1. Overview --}}
        <section id="overview" class="{{ $card }}">
            <h2 class="text-lg font-semibold text-text">1. Forecast Overview</h2>
            <p class="mt-1 text-sm text-text-muted">
                Run #{{ $run->id }} · {{ $run->period_type === 'WEEK' ? 'Mingguan' : 'Bulanan' }}
                {{ $run->period_start->format('d M Y') }} – {{ $run->period_end->format('d M Y') }}
                · dianalisis {{ $run->finished_at?->format('d M Y H:i') }}
                · scope: {{ $run->scope_area ?? 'all areas' }} / {{ $run->scope_machine_type ?? 'all types' }}
            </p>
            <div class="mt-4 grid grid-cols-2 gap-3 lg:grid-cols-5">
                @foreach ([
                    ['Machines in scope', $q['machines_in_scope']],
                    ['Machines with a forecast', $q['machines_sufficient']],
                    ['Insufficient Historical Data', $q['machines_insufficient']],
                    ['Item LIKELY (terfilter)', $totals['likely_items']],
                    ['Total recommended qty (filtered)', $totals['recommended']],
                ] as [$label, $value])
                    <div class="rounded-control border border-border p-4">
                        <p class="text-xs text-text-muted">{{ $label }}</p>
                        <p class="mt-1 text-2xl font-semibold text-text">{{ $n($value) }}</p>
                    </div>
                @endforeach
            </div>
            <p class="mt-3 text-xs text-text-muted">
                Recommendations cover LIKELY spareparts only (usage probability ≥ {{ $run->parameters['likely_probability'] * 100 }}%).
                The PM count is scheduled PMs (OPEN / IN_PROGRESS / MISSED) in the period; PMs that are not scheduled yet are not forecast.
            </p>
        </section>

        {{-- 2. Machine-based --}}
        <section id="machine" class="{{ $card }}">
            <h2 class="text-lg font-semibold text-text">2. Machine-Based Forecast</h2>
            <div class="relative mt-3 overflow-x-auto rounded-control border border-border">
                <table class="data-table min-w-full">
                    <thead><tr>
                        @foreach (['Machine', 'Type', 'Sparepart', 'Used', 'Avg qty', 'Prob.', 'PM', 'Estimate', 'Recommended', 'Level', 'Conf.', 'Basis'] as $h)<th class="{{ $th }}">{{ $h }}</th>@endforeach
                    </tr></thead>
                    <tbody class="divide-y divide-border" data-infinite-list="forecast">
                    @forelse ($forecast as $r)
                        <tr>
                            <td class="{{ $td }} whitespace-nowrap font-medium">{{ $r->machine_number }}</td>
                            <td class="{{ $td }}">{{ $r->machine_type }}</td>
                            <td class="{{ $td }}"><span class="font-medium">{{ $r->material_number }}</span><br><span class="text-xs text-text-muted">{{ $r->description }}</span></td>
                            <td class="{{ $td }} whitespace-nowrap">{{ $r->usage_pm_count }}/{{ $r->pm_considered }} PM</td>
                            <td class="{{ $td }}">{{ $n($r->avg_qty, 2) }} {{ $r->unit }}</td>
                            <td class="{{ $td }}">{{ $n($r->probability * 100) }}%</td>
                            <td class="{{ $td }}">{{ $r->upcoming_pm_count }}</td>
                            <td class="{{ $td }}">{{ $n($r->expected_qty, 2) }}</td>
                            <td class="{{ $td }} font-semibold">{{ $r->recommended_qty }}</td>
                            <td class="{{ $td }}"><span class="badge {{ $levelClass[$r->level] }}">{{ $r->level }}</span></td>
                            <td class="{{ $td }}">{{ $r->confidence }}</td>
                            <td class="{{ $td }}"><details><summary class="cursor-pointer text-xs text-primary">View</summary>
                                <ul class="mt-1 w-64 list-disc pl-4 text-xs text-text-muted">@foreach ($r->basis['lines'] ?? [] as $line)<li>{{ $line }}</li>@endforeach</ul></details></td>
                        </tr>
                    @empty
                        <tr><td colspan="12" class="{{ $td }} text-text-muted">No forecast result for these filters.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-3"><x-infinite-scroll :paginator="$forecast" key="forecast" /></div>

            <h3 class="mt-6 text-sm font-semibold text-text">Machines with Insufficient Historical Data</h3>
            <p class="text-xs text-text-muted">No forecast number is given, and neither Mandatory Sparepart nor Injection stands in for it.</p>
            <div class="relative mt-2 overflow-x-auto rounded-control border border-border">
                <table class="data-table min-w-full">
                    <thead><tr>@foreach (['Machine', 'Type', 'Area', 'PMs done', 'PMs with records', 'PMs excluded', 'Note'] as $h)<th class="{{ $th }}">{{ $h }}</th>@endforeach</tr></thead>
                    <tbody class="divide-y divide-border" data-infinite-list="insufficient">
                    @forelse ($insufficient as $m)
                        <tr>
                            <td class="{{ $td }} font-medium">{{ $m->machine_number }}</td><td class="{{ $td }}">{{ $m->machine_type }}</td><td class="{{ $td }}">{{ $m->area }}</td>
                            <td class="{{ $td }}">{{ $m->finished_pm_count }}</td><td class="{{ $td }}">{{ $m->pm_with_usage_count }}</td><td class="{{ $td }}">{{ $m->excluded_pm_count }}</td>
                            <td class="{{ $td }}">{{ $m->note }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="{{ $td }} text-text-muted">None.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-3"><x-infinite-scroll :paginator="$insufficient" key="insufficient" /></div>
        </section>

        {{-- 3. Sparepart demand --}}
        <section id="demand" class="{{ $card }}">
            <h2 class="text-lg font-semibold text-text">3. Sparepart Demand Summary</h2>
            <p class="mt-1 text-sm text-text-muted">Every machine under these filters, totalled per sparepart (up to 100 rows; the full set is in the Excel export).</p>
            <div class="relative mt-3 overflow-x-auto rounded-control border border-border">
                <table class="data-table min-w-full">
                    <thead><tr>@foreach (['SAP Number', 'Description', 'Unit', 'Machines', 'Total estimate', 'Total recommended'] as $h)<th class="{{ $th }}">{{ $h }}</th>@endforeach</tr></thead>
                    <tbody class="divide-y divide-border">
                    @forelse ($bySparepart as $r)
                        <tr><td class="{{ $td }} font-medium">{{ $r->material_number }}</td><td class="{{ $td }}">{{ $r->description }}</td><td class="{{ $td }}">{{ $r->unit }}</td>
                            <td class="{{ $td }}">{{ $r->machines }}</td><td class="{{ $td }}">{{ $n($r->expected, 2) }}</td><td class="{{ $td }} font-semibold">{{ $n($r->recommended) }}</td></tr>
                    @empty
                        <tr><td colspan="6" class="{{ $td }} text-text-muted">No data yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        {{-- 4. Machine type / area --}}
        <section id="type" class="{{ $card }}">
            <h2 class="text-lg font-semibold text-text">4. Machine Type Analysis</h2>
            <div class="mt-3 grid gap-6 lg:grid-cols-2">
                <div class="relative overflow-x-auto rounded-control border border-border">
                    <table class="data-table min-w-full">
                        <thead><tr>@foreach (['Machine Type', 'Machines (forecast / insufficient)', 'Scheduled PMs', 'Sparepart kinds', 'Estimate', 'Recommended'] as $h)<th class="{{ $th }}">{{ $h }}</th>@endforeach</tr></thead>
                        <tbody class="divide-y divide-border">
                        @foreach ($machineStatusByType as $type => $s)
                            @php $r = $byMachineType->firstWhere('machine_type', $type); @endphp
                            <tr><td class="{{ $td }} font-medium">{{ $type }}</td><td class="{{ $td }}">{{ $s->sufficient }} / {{ $s->insufficient }}</td><td class="{{ $td }}">{{ $s->upcoming }}</td>
                                <td class="{{ $td }}">{{ $r->parts ?? 0 }}</td><td class="{{ $td }}">{{ $n($r->expected ?? 0, 2) }}</td><td class="{{ $td }} font-semibold">{{ $n($r->recommended ?? 0) }}</td></tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="relative overflow-x-auto rounded-control border border-border">
                    <table class="data-table min-w-full">
                        <thead><tr>@foreach (['Area', 'Machines', 'Estimate', 'Recommended'] as $h)<th class="{{ $th }}">{{ $h }}</th>@endforeach</tr></thead>
                        <tbody class="divide-y divide-border">
                        @forelse ($byArea as $r)
                            <tr><td class="{{ $td }} font-medium">{{ $r->area }}</td><td class="{{ $td }}">{{ $r->machines }}</td><td class="{{ $td }}">{{ $n($r->expected, 2) }}</td><td class="{{ $td }} font-semibold">{{ $n($r->recommended) }}</td></tr>
                        @empty
                            <tr><td colspan="4" class="{{ $td }} text-text-muted">No data yet.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        {{-- 5. Historical usage --}}
        <section id="history" class="{{ $card }}">
            <h2 class="text-lg font-semibold text-text">5. Historical Usage Analysis</h2>
            <p class="mt-1 text-sm text-text-muted">
                Histori {{ $run->history_from?->format('d M Y') ?? '-' }} – {{ $run->history_to?->format('d M Y') ?? '-' }}
                (a window of the last {{ $run->parameters['window'] }} PMs per machine). Frequency, total qty, and trend per sparepart.
            </p>
            <div class="relative mt-3 overflow-x-auto rounded-control border border-border">
                <table class="data-table min-w-full">
                    <thead><tr>@foreach (['SAP Number', 'Description', 'Machines that used it', 'Times used', 'Total historical qty', 'Machines trending up', 'Machines trending down'] as $h)<th class="{{ $th }}">{{ $h }}</th>@endforeach</tr></thead>
                    <tbody class="divide-y divide-border">
                    @forelse ($bySparepart->sortByDesc('hist_qty')->take(30) as $r)
                        <tr><td class="{{ $td }} font-medium">{{ $r->material_number }}</td><td class="{{ $td }}">{{ $r->description }}</td><td class="{{ $td }}">{{ $r->machines }}</td>
                            <td class="{{ $td }}">{{ $r->usage_pm }}</td><td class="{{ $td }}">{{ $n($r->hist_qty) }} {{ $r->unit }}</td><td class="{{ $td }}">{{ $r->increasing }}</td><td class="{{ $td }}">{{ $r->decreasing }}</td></tr>
                    @empty
                        <tr><td colspan="7" class="{{ $td }} text-text-muted">No data yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <p class="mt-2 text-xs text-text-muted">The interval between uses and the trend per machine are in the "Basis" column (section 2) and in the Excel export.</p>
        </section>

        {{-- 6. Evaluation --}}
        <section id="evaluation" class="{{ $card }}">
            <h2 class="text-lg font-semibold text-text">6. Forecast Evaluation &amp; Accuracy</h2>
            @if ($evaluation)
                <p class="mt-1 text-sm text-text-muted">{{ $evaluation->split_method }}.</p>
                <div class="mt-4 grid gap-4 lg:grid-cols-3">
                    <div class="rounded-control border border-border p-4 text-sm text-text">
                        <h3 class="font-semibold text-text">Training data</h3>
                        <p>Usage rows: {{ $n($evaluation->train_usage_rows) }}</p>
                        <p>PM: {{ $n($evaluation->train_pm_count) }} · Machines: {{ $n($evaluation->train_machine_count) }} · Spareparts: {{ $n($evaluation->train_sparepart_count) }}</p>
                        <p>Rentang: {{ $evaluation->train_from?->format('d M Y') ?? '-' }} – {{ $evaluation->train_to?->format('d M Y') ?? '-' }}</p>
                    </div>
                    <div class="rounded-control border border-border p-4 text-sm text-text">
                        <h3 class="font-semibold text-text">Testing data</h3>
                        <p>Usage rows: {{ $n($evaluation->test_usage_rows) }}</p>
                        <p>PM: {{ $n($evaluation->test_pm_count) }} · Machines: {{ $n($evaluation->test_machine_count) }} · Items compared: {{ $n($evaluation->compared_items) }}</p>
                        <p>Rentang: {{ $evaluation->test_from?->format('d M Y') ?? '-' }} – {{ $evaluation->test_to?->format('d M Y') ?? '-' }}</p>
                        <p class="mt-1">Enough for an evaluation: <strong>{{ $evaluation->status === 'AVAILABLE' ? 'Yes' : 'No' }}</strong></p>
                    </div>
                    <div class="rounded-control border border-border p-4 text-sm text-text">
                        <h3 class="font-semibold text-text">Accuracy</h3>
                        @if ($evaluation->status === 'AVAILABLE')
                            <p>MAE: <strong>{{ $n($evaluation->mae, 2) }}</strong> units per machine-sparepart</p>
                            <p>WAPE: <strong>{{ $n($evaluation->wape * 100, 1) }}%</strong></p>
                            <p>Bias: <strong>{{ $n($evaluation->bias * 100, 1) }}%</strong></p>
                        @else
                            <p class="font-semibold text-warning-text">Accuracy Not Available: Insufficient Testing Data</p>
                            @foreach ($evaluation->notes ?? [] as $note)<p class="text-xs text-text-muted">{{ $note }}</p>@endforeach
                        @endif
                    </div>
                </div>
                <p class="mt-3 text-xs text-text-muted">
                    Evaluation needs at least {{ $run->parameters['min_test_pm'] }} testing PMs from at least {{ $run->parameters['min_test_machines'] }} machines.
                    The numbers above are computed from the real data at the time of this run, never entered by hand.
                </p>
            @else
                <p class="mt-2 text-sm text-text-muted">No evaluation is available for this run.</p>
            @endif
        </section>

        {{-- 7. Data quality --}}
        <section id="quality" class="{{ $card }}">
            <h2 class="text-lg font-semibold text-text">7. Data Quality &amp; Model Notes</h2>
            <dl class="mt-3 grid gap-x-8 gap-y-2 text-sm sm:grid-cols-2 lg:grid-cols-3">
                @foreach ([
                    'PMs done (machines in scope)' => $q['finished_pm_total'],
                    'PMs used as history' => $q['finished_pm_used'],
                    'PMs used with no sparepart record' => $q['finished_pm_without_usage'],
                    'PM dikecualikan (memuat Injection)' => $q['finished_pm_excluded_warehouse'],
                    'PM selesai tanpa tanggal' => $q['finished_pm_without_date'],
                    'Usage rows used' => $q['usage_rows'],
                    'Jenis sparepart tercatat' => $q['distinct_spareparts'],
                    'Machines with no finished PM history' => $q['machines_without_history'],
                    'Machines with enough data but no scheduled PM' => $q['machines_without_upcoming_pm'],
                ] as $label => $value)
                    <div class="flex justify-between border-b border-border py-1"><dt class="text-text-muted">{{ $label }}</dt><dd class="font-medium text-text">{{ $n($value) }}</dd></div>
                @endforeach
            </dl>
            <ul class="mt-4 list-disc space-y-1 pl-5 text-xs text-text-muted">
                <li>Algorithm: {{ $run->algorithm_version }}. Analysed: {{ $run->finished_at?->format('d M Y H:i') }}. Data fingerprint: <code>{{ \Illuminate\Support\Str::limit($run->data_fingerprint, 16, '…') }}</code>.</li>
                <li>A machine needs at least {{ $run->parameters['min_pm'] }} finished PMs, of which at least {{ $run->parameters['min_pm_with_usage'] }} carry a sparepart record.</li>
                <li>A finished PM with no sparepart record counts as "used no sparepart". Nothing marks a PM record as complete, so a missed record menurunkan probabilitas.</li>
                <li>Nothing distinguishes a sparepart that was genuinely replaced from a default value saved along with the form.</li>
            </ul>
        </section>

        {{-- 8. Recommendations --}}
        <section id="recs" class="{{ $card }}">
            <h2 class="text-lg font-semibold text-text">8. Recommendations</h2>
            <p class="mt-1 text-sm text-text-muted">Generated from the engine numbers (source: ENGINE). The Gemini explanation is not live yet.</p>
            <ul class="mt-3 space-y-2 text-sm">
                @forelse ($recommendations as $rec)
                    <li class="rounded-control border px-3 py-2 {{ $rec->severity === 'WARNING' ? 'border-warning/40 bg-warning-light text-warning-text' : 'border-border text-text' }}">{{ $rec->message }}</li>
                @empty
                    <li class="text-text-muted">No recommendation.</li>
                @endforelse
            </ul>
        </section>
    @endif

    {{-- 9. History --}}
    <section id="runs" class="{{ $card }}">
        <h2 class="text-lg font-semibold text-text">9. Analysis History</h2>
        <div class="relative mt-3 overflow-x-auto rounded-control border border-border">
            <table class="data-table min-w-full">
                <thead><tr>@foreach (['Run', 'Time', 'Period', 'Scope', 'Version', 'Status', 'By', ''] as $h)<th class="{{ $th }}">{{ $h }}</th>@endforeach</tr></thead>
                <tbody class="divide-y divide-border">
                @forelse ($runs as $r)
                    <tr class="{{ $run && $run->id === $r->id ? 'bg-primary-light' : '' }}">
                        <td class="{{ $td }}">#{{ $r->id }}</td><td class="{{ $td }}">{{ $r->created_at->format('d M Y H:i') }}</td>
                        <td class="{{ $td }}">{{ $r->period_type }} {{ $r->period_start->format('d M') }}–{{ $r->period_end->format('d M Y') }}</td>
                        <td class="{{ $td }}">{{ $r->scope_area ?? 'All' }} / {{ $r->scope_machine_type ?? 'All' }}</td>
                        <td class="{{ $td }}">{{ $r->algorithm_version }}</td><td class="{{ $td }}">{{ $r->status }}</td><td class="{{ $td }}">{{ $r->user?->name ?? '-' }}</td>
                        <td class="{{ $td }}"><a class="font-semibold text-primary underline-offset-2 hover:underline" href="{{ route('maintenance-intelligence.sparepart-analyst', ['run' => $r->id]) }}">Buka</a></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="{{ $td }} text-text-muted">No analysis yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <p class="mt-2 text-xs text-text-muted">Older results are kept; running a new analysis does not overwrite them.</p>
    </section>
</div>
@endsection
