@extends('layouts.app')

@section('title', 'Greasing Report')

@section('content')
    @php
        $fullWidth = true;
        $label = 'text-xs font-medium text-text-muted';
        $findingLabel = fn ($n) => $n > 0 ? '[ '.$n.' '.\Illuminate\Support\Str::plural('Finding', $n).' ]' : '[ No Finding ]';
        $showing = ($periodType === 'monthly' && count($selectedMonths)
                ? collect($selectedMonths)->sort()->map(fn ($m) => \Carbon\Carbon::create(null, $m, 1)->format('F'))->implode(', ').' '.$year
                : $year).($area ? ' · '.$area : '');
        $rings = [
            ['title' => 'Closing %', 'percent' => $kpi['closing_percent'], 'key' => 'closing_percent', 'color' => 'var(--color-success)', 'bar' => 'bg-success',
                'formula' => '(FINISH ON TIME + FINISH) / Total Schedule',
                'detail' => $kpi['finish_on_time'].' Finish On Time + '.$kpi['finish'].' Finish of '.$kpi['total'].' Total'],
            ['title' => 'Completion %', 'percent' => $kpi['completion_percent'], 'key' => 'completion_percent', 'color' => 'var(--color-chart-1)', 'bar' => 'bg-chart-1',
                'formula' => '(FINISH ON TIME x1 + FINISH x0.5) / Total Schedule',
                'detail' => $kpi['open'].' Open of '.$kpi['total'].' Total'],
        ];
    @endphp

    <div class="space-y-5">
        <x-ui.page-header title="Greasing Report" description="Closing & Completion, trend, and greasing/finding detail for the selected period." />

        {{-- Filters submit on change; the multi-selects sit last so their open lists never cover a control. --}}
        <form method="GET" class="card flex flex-col gap-3 p-4 sm:flex-row sm:flex-wrap sm:items-end">
            <fieldset class="grid gap-1.5">
                <legend class="{{ $label }} mb-1.5">Period</legend>
                <div class="inline-flex rounded-control border border-border-strong bg-surface-muted p-1">
                    @foreach (['monthly' => 'Monthly', 'yearly' => 'Yearly'] as $value => $text)
                        <label @class([
                            'inline-flex min-h-10 cursor-pointer items-center rounded-[calc(var(--radius-control)-4px)] px-4 lg:min-h-8 text-sm font-semibold transition-colors has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-primary',
                            'bg-primary text-white shadow-sm' => $periodType === $value,
                            'text-text-muted hover:bg-surface hover:text-text' => $periodType !== $value,
                        ])>
                            <input type="radio" name="period_type" value="{{ $value }}" class="sr-only" onchange="this.form.submit()" @checked($periodType === $value)>
                            {{ $text }}
                        </label>
                    @endforeach
                </div>
            </fieldset>
            <div class="grid gap-1.5 sm:w-28">
                <label for="rg-year" class="{{ $label }}">Year</label>
                <select id="rg-year" name="year" onchange="this.form.submit()" class="select lg:min-h-9">
                    @foreach ($years as $y)
                        <option value="{{ $y }}" @selected((int) $year === $y)>{{ $y }}</option>
                    @endforeach
                </select>
            </div>
            @if ($isAdmin)
                <div class="grid gap-1.5 sm:w-32">
                    <label for="rg-area" class="{{ $label }}">Area</label>
                    <select id="rg-area" name="area" onchange="this.form.submit()" class="select lg:min-h-9">
                        <option value="" @selected(! $area)>All Area</option>
                        @foreach ($areas as $areaOption)
                            <option value="{{ $areaOption }}" @selected($area === $areaOption)>{{ $areaOption }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div class="grid gap-1.5 sm:w-40">
                <label for="rg-group" class="{{ $label }}">Group</label>
                <select id="rg-group" name="group_id" onchange="this.form.submit()" class="select lg:min-h-9">
                    <option value="">All Groups</option>
                    @foreach ($groups as $g)
                        <option value="{{ $g->id }}" @selected((int) $groupId === $g->id)>{{ $g->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="grid gap-1.5 sm:w-32">
                <label for="rg-cycle" class="{{ $label }}">Cycle</label>
                <select id="rg-cycle" name="cycle" onchange="this.form.submit()" class="select lg:min-h-9">
                    <option value="">All Cycles</option>
                    @foreach ($cycles as $c)
                        <option value="{{ $c }}" @selected($cycle === $c)>{{ $c }}</option>
                    @endforeach
                </select>
            </div>
            @unless ($isPic)
                <div class="grid gap-1.5 sm:w-40">
                    <label for="rg-pic" class="{{ $label }}">PIC</label>
                    <select id="rg-pic" name="pic" onchange="this.form.submit()" class="select lg:min-h-9">
                        <option value="">All PIC</option>
                        @foreach ($pics as $p)
                            <option value="{{ $p }}" @selected($pic === $p)>{{ \Illuminate\Support\Str::title(strtolower($p)) }}</option>
                        @endforeach
                    </select>
                </div>
            @endunless
            <div class="grid gap-1.5 sm:w-52">
                <label for="rg-search" class="{{ $label }}">Search</label>
                <input id="rg-search" type="text" name="search" value="{{ $search }}" placeholder="Order Number / Group" class="input lg:min-h-9">
            </div>
            @if ($periodType === 'monthly')
                <div class="grid gap-1.5">
                    <span class="{{ $label }}">Month</span>
                    <x-checkbox-dropdown name="month" label="Month" :options="$months" :selected="$selectedMonths" :auto-submit="true" />
                </div>
            @endif
            <div class="grid gap-1.5">
                <span class="{{ $label }}">Status</span>
                <x-checkbox-dropdown name="status" label="Status"
                    :options="collect(\App\Models\Greasing::STATUSES)->mapWithKeys(fn ($s) => [$s => $s])->all()"
                    :selected="$statuses" :auto-submit="true" />
            </div>
            <div class="flex gap-2 sm:ml-auto">
                <x-ui.button type="submit" class="flex-1 sm:flex-none lg:min-h-9">Filter</x-ui.button>
                <x-ui.button variant="ghost" href="{{ route('reports.greasing') }}" class="lg:min-h-9">Reset</x-ui.button>
            </div>
            <p class="w-full text-sm text-text-muted">Showing: <span class="font-semibold text-text">{{ $showing }}</span></p>
        </form>

        {{-- KPI rings, each with its monthly trend in the yearly view. --}}
        <div class="grid gap-4 md:grid-cols-2">
            @foreach ($rings as $ring)
                <section class="card min-w-0 p-5" aria-label="{{ $ring['title'] }}">
                    <div class="flex items-center justify-between gap-4">
                        <div class="min-w-0">
                            <h2 class="text-sm font-medium text-text-muted">{{ $ring['title'] }}</h2>
                            <p class="mt-1 font-signage text-4xl font-semibold tabular-nums text-text">{{ $ring['percent'] }}%</p>
                            <p class="mt-1 text-xs text-text-muted">{{ $ring['formula'] }}</p>
                            <p class="mt-1 text-xs text-text-muted">{{ $ring['detail'] }}</p>
                        </div>
                        <div class="relative h-24 w-24 shrink-0 rounded-full" aria-hidden="true"
                            style="background: conic-gradient({{ $ring['color'] }} 0% {{ $ring['percent'] }}%, var(--color-surface-muted) {{ $ring['percent'] }}% 100%)">
                            <div class="absolute inset-2.5 rounded-full bg-surface"></div>
                        </div>
                    </div>

                    @if ($periodType === 'yearly' && $monthlyTrend)
                        <div class="mt-4 flex items-end gap-1.5 border-t border-border pt-5">
                            @foreach ($monthlyTrend as $m)
                                @php($has = $m['total'] > 0)
                                <div class="flex min-w-0 flex-1 flex-col items-center gap-1" title="{{ $m['label'] }}: {{ $has ? $m[$ring['key']].'%' : 'No schedule' }}">
                                    <span @class(['hidden text-[10px] font-semibold tabular-nums sm:block', 'text-text' => $has, 'text-text-muted' => ! $has])>{{ $has ? $m[$ring['key']].'%' : '-' }}</span>
                                    {{-- Fixed-height track so the percentage bar has a definite height to resolve against. bg-slate-100 marks a month with no schedule. --}}
                                    <div class="relative h-20 w-full">
                                        <div class="absolute bottom-0 w-full rounded-t {{ $has ? $ring['bar'] : 'bg-slate-100' }}" style="height: {{ $has ? max($m[$ring['key']], 4) : 4 }}%"></div>
                                    </div>
                                    <span class="text-[10px] text-text-muted">{{ $m['label'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </section>
            @endforeach
        </div>

        <section id="greasing-report-panel" class="card overflow-hidden" aria-labelledby="rg-table-title">
            <h2 id="rg-table-title" class="border-b border-border px-4 py-3 font-semibold text-text">Greasing Report</h2>

            @if ($greasings->isEmpty())
                <p class="px-4 py-8 text-center text-sm text-text-muted">No greasing schedule found for this period</p>
            @endif

            {{-- Desktop table. Keep this list first: infinite scroll matches lists by key and position. --}}
            <div @class(['relative hidden overflow-x-auto', 'md:block' => $greasings->isNotEmpty()])>
                <table class="data-table min-w-full">
                    <thead>
                        <tr>
                            <th scope="col">Action Date</th>
                            <th scope="col">Group</th>
                            <th scope="col">Order Number</th>
                            <th scope="col">PIC</th>
                            <th scope="col">Status</th>
                            <th scope="col">Finding</th>
                        </tr>
                    </thead>
                    <tbody data-infinite-list="greasings">
                        @foreach ($greasings as $greasing)
                            <tr>
                                <td class="whitespace-nowrap">{{ $greasing->action_date ? $greasing->action_date->format('d M Y') : '-' }}</td>
                                <td class="font-semibold text-text">{{ $greasing->group->name ?? '-' }}</td>
                                <td class="whitespace-nowrap">{{ $greasing->order_number ?? '-' }}</td>
                                <td>{{ $greasing->pic ?? '-' }}</td>
                                <td>@include('greasings.partials.status-badge', ['status' => $greasing->status])</td>
                                <td><x-ui.badge :tone="$greasing->findings_count > 0 ? 'warning' : 'neutral'">{{ $findingLabel($greasing->findings_count) }}</x-ui.badge></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Phone cards (below md). Second list with the same key. --}}
            <div @class(['space-y-3 md:hidden', 'p-3' => $greasings->isNotEmpty()]) data-infinite-list="greasings">
                @foreach ($greasings as $greasing)
                    <article class="rounded-control border border-border p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <h3 class="break-words font-semibold text-text">{{ $greasing->group->name ?? '-' }}</h3>
                                <p class="text-sm text-text-muted">{{ $greasing->order_number ?? '-' }}</p>
                            </div>
                            @include('greasings.partials.status-badge', ['status' => $greasing->status])
                        </div>
                        <dl class="mt-3 grid grid-cols-2 gap-x-4 gap-y-2 border-t border-border pt-3 text-sm">
                            <div><dt class="text-xs text-text-muted">Action Date</dt><dd class="font-medium text-text">{{ $greasing->action_date ? $greasing->action_date->format('d M Y') : '-' }}</dd></div>
                            <div><dt class="text-xs text-text-muted">PIC</dt><dd class="font-medium text-text">{{ $greasing->pic ?? '-' }}</dd></div>
                            <div class="col-span-2"><dt class="text-xs text-text-muted">Finding</dt><dd class="mt-0.5"><x-ui.badge :tone="$greasing->findings_count > 0 ? 'warning' : 'neutral'">{{ $findingLabel($greasing->findings_count) }}</x-ui.badge></dd></div>
                        </dl>
                    </article>
                @endforeach
            </div>

            <div class="border-t border-border px-3"><x-infinite-scroll :paginator="$greasings" key="greasings" /></div>
        </section>

        <section id="finding-report-panel" class="card overflow-hidden" aria-labelledby="rg-finding-title">
            <h2 id="rg-finding-title" class="border-b border-border px-4 py-3 font-semibold text-text">Finding Report</h2>

            <dl class="grid gap-px border-b border-border bg-border sm:grid-cols-3">
                <div class="flex flex-col-reverse justify-end gap-1 bg-surface p-4">
                    <dt class="text-xs text-text-muted">Total Finding</dt>
                    <dd class="font-signage text-2xl font-semibold tabular-nums text-text">{{ $findingSummary['total'] }}</dd>
                </div>
                @foreach (['top_machine' => 'Top Problem Machine', 'top_area' => 'Top Finding Area'] as $key => $text)
                    <div class="flex flex-col-reverse justify-end gap-1 bg-surface p-4">
                        <dt class="text-xs text-text-muted">{{ $text }}</dt>
                        <dd class="break-words font-signage text-2xl font-semibold text-text">
                            @if ($findingSummary[$key])
                                {{ $findingSummary[$key]->label }}
                                <span class="block font-sans text-sm font-medium text-text-muted">{{ $findingSummary[$key]->total }} {{ \Illuminate\Support\Str::plural('Finding', $findingSummary[$key]->total) }}</span>
                            @else
                                -
                            @endif
                        </dd>
                    </div>
                @endforeach
            </dl>

            <div class="relative overflow-x-auto">
                <table class="data-table min-w-full">
                    <thead>
                        <tr>
                            <th scope="col">Machine</th>
                            <th scope="col">Finding Area</th>
                            <th scope="col">Remarks</th>
                            <th scope="col">Status</th>
                            <th scope="col">Action Date</th>
                        </tr>
                    </thead>
                    <tbody data-infinite-list="findings">
                        @forelse ($findings as $finding)
                            <tr class="align-top">
                                <td class="whitespace-nowrap font-semibold text-text">{{ $finding->machine?->machine_number ?? '-' }}</td>
                                <td>{{ $finding->finding_area ?? '-' }}</td>
                                <td class="min-w-56">
                                    {{ $finding->finding }}
                                    @if ($finding->action)
                                        <p class="mt-1 text-xs text-text-muted">Action: {{ $finding->action }}</p>
                                    @endif
                                </td>
                                <td><x-ui.badge :tone="$finding->status === 'COMPLETED' ? 'success' : ($finding->status === 'OPEN' ? 'warning' : 'neutral')">{{ $finding->status }}</x-ui.badge></td>
                                <td class="whitespace-nowrap">{{ $finding->action_date ? $finding->action_date->format('d M Y') : '-' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="py-8 text-center text-text-muted">No finding recorded for this period.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-border px-3"><x-infinite-scroll :paginator="$findings" key="findings" /></div>
        </section>
    </div>
@endsection
