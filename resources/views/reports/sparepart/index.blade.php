@extends('layouts.app')

@section('title', 'Sparepart Usage Report')

@section('content')
    @php
        $fullWidth = true;
        $label = 'text-xs font-medium text-text-muted';
        $fmt = fn ($d) => $d ? \Carbon\Carbon::parse($d)->format('d-m-Y') : '-';
        $usd = fn ($v) => 'USD '.number_format($v, 2);
        $maxQty = (float) $topUsage->max('total_qty');
        $maxCost = (float) $topCost->max('total_cost');
    @endphp

    <div class="space-y-5">
        <x-ui.page-header title="Sparepart Usage Report" description="Detail, filter, and drill-down for every sparepart used in PM execution." />

        {{-- Filters: plain GET; the multi-selects sit last so their open lists never cover a control. --}}
        <form method="GET" class="card flex flex-col gap-3 p-4 sm:flex-row sm:flex-wrap sm:items-end">
            <div class="grid gap-1.5 sm:w-72">
                <label for="rs-search" class="{{ $label }}">Search</label>
                <input id="rs-search" type="text" name="search" value="{{ $search }}" placeholder="Material Number / Description / Order Number" class="input lg:min-h-9">
            </div>
            @if ($isAdmin)
                <div class="grid gap-1.5 sm:w-36">
                    <label for="rs-area" class="{{ $label }}">Area</label>
                    <select id="rs-area" name="area" class="select lg:min-h-9">
                        <option value="">All Areas</option>
                        @foreach ($areas as $a)
                            <option value="{{ $a }}" @selected($selectedArea === $a)>{{ $a }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div class="grid gap-1.5 sm:w-32">
                <label for="rs-year" class="{{ $label }}">Year</label>
                <select id="rs-year" name="year" class="select lg:min-h-9">
                    <option value="">All Years</option>
                    @foreach ($years as $y)
                        <option value="{{ $y }}" @selected($selectedYear == $y)>{{ $y }}</option>
                    @endforeach
                </select>
            </div>
            <div class="grid gap-1.5 sm:w-40">
                <label for="rs-machine" class="{{ $label }}">Machine</label>
                <select id="rs-machine" name="machine" class="select lg:min-h-9">
                    <option value="">All Machines</option>
                    @foreach ($machines as $m)
                        <option value="{{ $m }}" @selected($selectedMachine === $m)>{{ $m }}</option>
                    @endforeach
                </select>
            </div>
            <div class="grid gap-1.5 sm:w-44">
                <label for="rs-type" class="{{ $label }}">Machine Type</label>
                <select id="rs-type" name="machine_type" class="select lg:min-h-9">
                    <option value="">All Machine Type</option>
                    @foreach ($machineTypes as $type)
                        <option value="{{ $type }}" @selected($selectedMachineType === $type)>{{ $type }}</option>
                    @endforeach
                </select>
            </div>
            <div class="grid gap-1.5 sm:w-40">
                <label for="rs-segment" class="{{ $label }}">Segment</label>
                <select id="rs-segment" name="segment" class="select lg:min-h-9">
                    <option value="">All Segments</option>
                    @foreach ($segments as $seg)
                        <option value="{{ $seg }}" @selected($selectedSegment === $seg)>{{ $seg }}</option>
                    @endforeach
                </select>
            </div>
            <div class="grid gap-1.5">
                <span class="{{ $label }}">Month</span>
                <x-checkbox-dropdown name="month" label="Month"
                    :options="collect(range(1, 12))->mapWithKeys(fn ($m) => [$m => \Carbon\Carbon::create(null, $m, 1)->format('F')])->all()"
                    :selected="$selectedMonths" />
            </div>
            <div class="grid gap-1.5">
                <span class="{{ $label }}">Status</span>
                <x-checkbox-dropdown name="status" label="Status" :options="collect($statuses)->mapWithKeys(fn ($s) => [$s => $s])->all()" :selected="$selectedStatuses" />
            </div>
            <div class="flex gap-2 sm:ml-auto">
                <x-ui.button type="submit" class="flex-1 sm:flex-none lg:min-h-9">Filter</x-ui.button>
                <x-ui.button variant="ghost" href="{{ route('reports.sparepart') }}" class="lg:min-h-9">Reset</x-ui.button>
            </div>
        </form>

        @include('reports.partials.stats', ['items' => [
            ['label' => 'Total Usage Transaction', 'value' => $summary['total_transactions']],
            ['label' => 'Total Quantity Used', 'value' => number_format($summary['total_quantity'])],
            ['label' => 'Total Usage Cost', 'value' => $usd($summary['total_cost'])],
            ['label' => 'Unique Material Number', 'value' => $summary['unique_materials']],
        ]])

        <div class="grid gap-4 lg:grid-cols-2">
            <section class="card p-5" aria-labelledby="rs-top-usage">
                <h2 id="rs-top-usage" class="font-semibold text-text">Top 10 Most Used Spareparts</h2>
                <p class="mb-4 text-xs text-text-muted">Which spareparts were used most in this period?</p>
                @if ($topUsage->isEmpty())
                    <p class="text-sm text-text-muted">No usage data for this period.</p>
                @else
                    @include('reports.partials.rank-bars', ['numbered' => true, 'rows' => $topUsage->map(fn ($row) => [
                        'label' => ($row->description ?: '-').' ('.$row->material_number.')',
                        'value' => number_format($row->total_qty),
                        'percent' => $maxQty > 0 ? $row->total_qty / $maxQty * 100 : 0,
                    ])->all()])
                @endif
            </section>
            <section class="card p-5" aria-labelledby="rs-top-cost">
                <h2 id="rs-top-cost" class="font-semibold text-text">Top 10 Highest Cost Spareparts</h2>
                <p class="mb-4 text-xs text-text-muted">Which spareparts cost the most in this period?</p>
                @if ($topCost->isEmpty())
                    <p class="text-sm text-text-muted">No usage data for this period.</p>
                @else
                    @include('reports.partials.rank-bars', ['numbered' => true, 'rows' => $topCost->map(fn ($row) => [
                        'label' => ($row->description ?: '-').' ('.$row->material_number.')',
                        'value' => $usd($row->total_cost),
                        'percent' => $maxCost > 0 ? $row->total_cost / $maxCost * 100 : 0,
                    ])->all()])
                @endif
            </section>
        </div>

        @if ($usages->isEmpty())
            <x-ui.empty-state title="No Sparepart Usage data found." description="Change the filters, or reset them to see every usage." />
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
                                <th scope="col">Order Number</th>
                                <th scope="col">Material Number</th>
                                <th scope="col">Description</th>
                                <th scope="col" class="text-right">Qty</th>
                                <th scope="col">Unit</th>
                                <th scope="col" class="text-right">Price</th>
                                <th scope="col" class="text-right">Total Cost</th>
                            </tr>
                        </thead>
                        <tbody data-infinite-list="usages">
                            @foreach ($usages as $row)
                                <tr>
                                    <td class="whitespace-nowrap">{{ $fmt($row->actual_date) }}</td>
                                    <td>{{ $row->area }}</td>
                                    <td class="whitespace-nowrap font-semibold text-text">{{ $row->machine_number }}</td>
                                    <td class="whitespace-nowrap">{{ $row->order_number ?: '-' }}</td>
                                    <td class="whitespace-nowrap">{{ $row->material_number }}</td>
                                    <td class="min-w-48">{{ $row->description }}</td>
                                    <td class="text-right tabular-nums">{{ $row->qty }}</td>
                                    <td>{{ $row->unit ?: '-' }}</td>
                                    <td class="whitespace-nowrap text-right tabular-nums">{{ $usd($row->price) }}</td>
                                    <td class="whitespace-nowrap text-right font-semibold tabular-nums text-text">{{ $usd(($row->qty ?? 0) * ($row->price ?? 0)) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Phone cards (below md). Second list with the same key. --}}
            <div class="space-y-3 md:hidden" data-infinite-list="usages">
                @foreach ($usages as $row)
                    <article class="card min-w-0 p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <h2 class="text-base font-semibold text-text">{{ $row->material_number }}</h2>
                                <p class="break-words text-sm text-text-muted">{{ $row->description }}</p>
                            </div>
                            <p class="shrink-0 text-right text-xs text-text-muted">{{ $row->machine_number }}<br>{{ $row->area }}</p>
                        </div>
                        <dl class="mt-3 grid grid-cols-2 gap-x-4 gap-y-2 border-t border-border pt-3 text-sm">
                            <div><dt class="text-xs text-text-muted">Date</dt><dd class="font-medium text-text">{{ $fmt($row->actual_date) }}</dd></div>
                            <div><dt class="text-xs text-text-muted">Order Number</dt><dd class="break-words font-medium text-text">{{ $row->order_number ?: '-' }}</dd></div>
                            <div><dt class="text-xs text-text-muted">Qty / Unit</dt><dd class="font-medium text-text">{{ $row->qty }} {{ $row->unit ?: '-' }}</dd></div>
                            <div><dt class="text-xs text-text-muted">Price</dt><dd class="font-medium tabular-nums text-text">{{ $usd($row->price) }}</dd></div>
                            <div class="col-span-2 flex items-baseline justify-between border-t border-border pt-2"><dt class="text-xs text-text-muted">Total</dt><dd class="font-semibold tabular-nums text-text">{{ $usd(($row->qty ?? 0) * ($row->price ?? 0)) }}</dd></div>
                        </dl>
                    </article>
                @endforeach
            </div>

            <x-infinite-scroll :paginator="$usages" key="usages" />
        @endif

        @include('reports.partials.forecast-note', ['title' => 'Forecasting / Predictive Sparepart Needs', 'text' => 'Predicted sparepart needs per machine for the next PM will appear here once available. Not implemented yet.'])
    </div>
@endsection
