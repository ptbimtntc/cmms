@extends('layouts.app')

@section('title', 'Maintenance Cost Report')

@section('content')
    @php
        $fullWidth = true;
        $label = 'text-xs font-medium text-text-muted';
        $usd = fn ($v) => 'USD '.number_format($v, 2);
        $maxArea = (float) collect($summary['cost_by_area'])->max();
        $maxType = (float) $costByMachineType->max('cost');
        $maxMachine = (float) $topMachines->max('cost');
    @endphp

    <div class="space-y-5">
        <x-ui.page-header title="Maintenance Cost Report" description="Monthly cost trend and detail, sourced from real sparepart usage, the only maintenance cost currently tracked in FreeDOMS." />

        {{-- Filters: plain GET; the multi-select sits last so its open list never covers a control. --}}
        <form method="GET" class="card flex flex-col gap-3 p-4 sm:flex-row sm:flex-wrap sm:items-end">
            @if ($isAdmin)
                <div class="grid gap-1.5 sm:w-36">
                    <label for="rc-area" class="{{ $label }}">Area</label>
                    <select id="rc-area" name="area" class="select lg:min-h-9">
                        <option value="">All Areas</option>
                        @foreach ($areas as $a)
                            <option value="{{ $a }}" @selected($selectedArea === $a)>{{ $a }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div class="grid gap-1.5 sm:w-28">
                <label for="rc-year" class="{{ $label }}">Year</label>
                <select id="rc-year" name="year" class="select lg:min-h-9">
                    @foreach ($years as $y)
                        <option value="{{ $y }}" @selected($selectedYear == $y)>{{ $y }}</option>
                    @endforeach
                </select>
            </div>
            <div class="grid gap-1.5 sm:w-40">
                <label for="rc-machine" class="{{ $label }}">Machine</label>
                <select id="rc-machine" name="machine" class="select lg:min-h-9">
                    <option value="">All Machines</option>
                    @foreach ($machines as $m)
                        <option value="{{ $m }}" @selected($selectedMachine === $m)>{{ $m }}</option>
                    @endforeach
                </select>
            </div>
            <div class="grid gap-1.5 sm:w-44">
                <label for="rc-type" class="{{ $label }}">Machine Type</label>
                <select id="rc-type" name="machine_type" class="select lg:min-h-9">
                    <option value="">All Machine Type</option>
                    @foreach ($machineTypes as $type)
                        <option value="{{ $type }}" @selected($selectedMachineType === $type)>{{ $type }}</option>
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
                <x-ui.button variant="ghost" href="{{ route('reports.cost') }}" class="lg:min-h-9">Reset</x-ui.button>
            </div>
        </form>

        @include('reports.partials.stats', ['cols' => 'grid-cols-1 sm:grid-cols-3', 'items' => [
            ['label' => 'Total Maintenance Cost', 'value' => $usd($summary['total_cost'])],
            ['label' => 'Sparepart Cost', 'value' => $usd($summary['sparepart_cost']), 'hint' => 'Currently the only tracked cost source.'],
            ['label' => 'Cost per Machine (Highest)', 'value' => $summary['top_machine']['label'] ?? '-',
                'hint' => $summary['top_machine'] ? $usd($summary['top_machine']['cost']) : null],
        ]])

        <div class="grid gap-4 lg:grid-cols-3">
            {{-- Monthly trend: which months cost the most? One hue; empty months stay a short grey stub. --}}
            <section class="card p-5 lg:col-span-2" aria-labelledby="rc-trend">
                <h2 id="rc-trend" class="font-semibold text-text">Monthly Cost Trend {{ $selectedYear }}</h2>
                <p class="mb-4 text-xs text-text-muted">Which months cost the most? Values in USD.</p>
                <div class="flex items-end gap-1.5 sm:gap-2">
                    @foreach ($monthlyTrend as $m)
                        @php($has = $m['cost'] > 0)
                        <div class="flex min-w-0 flex-1 flex-col items-center gap-1" title="{{ $m['label'] }}: {{ $usd($m['cost']) }}">
                            <span @class(['hidden text-[10px] font-semibold tabular-nums sm:block', 'text-text' => $has, 'text-text-muted' => ! $has])>{{ $has ? number_format($m['cost'], 0) : '-' }}</span>
                            {{-- Fixed-height track so the percentage bar has a definite height to resolve against. --}}
                            <div class="relative h-32 w-full">
                                <div class="absolute bottom-0 w-full rounded-t {{ $has ? 'bg-chart-1' : 'bg-surface-muted' }}"
                                    style="height: {{ $maxMonthlyCost > 0 && $has ? max(($m['cost'] / $maxMonthlyCost) * 100, 4) : 4 }}%"></div>
                            </div>
                            <span class="text-[10px] text-text-muted">{{ $m['label'] }}</span>
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="card p-5" aria-labelledby="rc-area-title">
                <h2 id="rc-area-title" class="font-semibold text-text">Cost per Area</h2>
                <p class="mb-4 text-xs text-text-muted">Where is the money going?</p>
                @if (collect($summary['cost_by_area'])->isEmpty())
                    <p class="text-sm text-text-muted">No Maintenance Cost data found.</p>
                @else
                    @include('reports.partials.rank-bars', ['rows' => collect($summary['cost_by_area'])->map(fn ($cost, $areaName) => [
                        'label' => $areaName, 'value' => $usd($cost), 'percent' => $maxArea > 0 ? $cost / $maxArea * 100 : 0,
                    ])->values()->all()])
                @endif
            </section>
        </div>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
            <section class="card p-5" aria-labelledby="rc-type-title">
                <h2 id="rc-type-title" class="mb-4 font-semibold text-text">Cost by Machine Type</h2>
                @if ($costByMachineType->isEmpty())
                    <p class="text-sm text-text-muted">No Maintenance Cost data found.</p>
                @else
                    @include('reports.partials.rank-bars', ['rows' => $costByMachineType->map(fn ($row) => [
                        'label' => $row->machine_type, 'value' => $usd($row->cost), 'percent' => $maxType > 0 ? $row->cost / $maxType * 100 : 0,
                    ])->all()])
                @endif
            </section>

            <section class="card p-5" aria-labelledby="rc-top-title">
                <h2 id="rc-top-title" class="mb-4 font-semibold text-text">Top 10 Machines by Cost</h2>
                @if ($topMachines->isEmpty())
                    <p class="text-sm text-text-muted">No Maintenance Cost data found.</p>
                @else
                    @include('reports.partials.rank-bars', ['numbered' => true, 'rows' => $topMachines->map(fn ($row) => [
                        'label' => $row->machine_number, 'value' => $usd($row->cost), 'percent' => $maxMachine > 0 ? $row->cost / $maxMachine * 100 : 0,
                    ])->all()])
                @endif
            </section>
        </div>
    </div>
@endsection
