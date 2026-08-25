@extends('layouts.app')

@section('content')

    <div class="mb-6 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-slate-800">Maintenance Cost Report</h1>
            <p class="text-sm text-slate-500">Monthly cost trend and detail, sourced from real sparepart usage — the
                only maintenance cost currently tracked in FreeDOMS.</p>
        </div>
        <a href="{{ route('reports.index') }}"
            class="inline-flex w-fit items-center rounded-lg bg-slate-700 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
            ← Report Center
        </a>
    </div>

    {{-- ============ Filters ============ --}}
    <form method="GET" class="mb-6 flex flex-wrap gap-2 rounded-2xl border border-slate-200 bg-slate-50 p-4 shadow-sm">
        @if ($isAdmin)
            <select name="area" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700">
                <option value="">ALL Areas</option>
                @foreach ($areas as $a)
                    <option value="{{ $a }}" {{ $selectedArea === $a ? 'selected' : '' }}>{{ $a }}</option>
                @endforeach
            </select>
        @endif

        <select name="year" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700">
            @foreach ($years as $y)
                <option value="{{ $y }}" {{ $selectedYear == $y ? 'selected' : '' }}>{{ $y }}</option>
            @endforeach
        </select>

        <select name="month" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700">
            <option value="">All Months</option>
            @foreach (range(1, 12) as $m)
                <option value="{{ $m }}" {{ $selectedMonth == $m ? 'selected' : '' }}>
                    {{ \Carbon\Carbon::create(null, $m, 1)->format('F') }}</option>
            @endforeach
        </select>

        <select name="machine" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700">
            <option value="">All Machines</option>
            @foreach ($machines as $m)
                <option value="{{ $m }}" {{ $selectedMachine === $m ? 'selected' : '' }}>{{ $m }}</option>
            @endforeach
        </select>

        <select name="machine_type" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700">
            <option value="">All Machine Type</option>
            @foreach ($machineTypes as $type)
                <option value="{{ $type }}" {{ $selectedMachineType === $type ? 'selected' : '' }}>{{ $type }}</option>
            @endforeach
        </select>

        <button class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-700">
            Filter
        </button>
        <a href="{{ route('reports.cost') }}"
            class="rounded-lg bg-slate-500 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-600">
            Reset
        </a>
    </form>

    {{-- ============ Summary ============ --}}
    <div class="mb-6 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="text-xs uppercase tracking-wide text-slate-400">Total Maintenance Cost</div>
            <div class="mt-1 text-xl font-bold text-slate-800">USD {{ number_format($summary['total_cost'], 2) }}</div>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="text-xs uppercase tracking-wide text-slate-400">Sparepart Cost</div>
            <div class="mt-1 text-xl font-bold text-emerald-600">USD {{ number_format($summary['sparepart_cost'], 2) }}</div>
            <p class="mt-0.5 text-[11px] text-slate-400">Currently the only tracked cost source.</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="text-xs uppercase tracking-wide text-slate-400">Cost per Machine (Highest)</div>
            @if ($summary['top_machine'])
                <div class="mt-1 text-lg font-bold text-blue-600">{{ $summary['top_machine']['label'] }}</div>
                <p class="mt-0.5 text-xs text-slate-400">USD {{ number_format($summary['top_machine']['cost'], 2) }}</p>
            @else
                <div class="mt-1 text-lg font-bold text-slate-400">-</div>
            @endif
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="text-xs uppercase tracking-wide text-slate-400">Cost per Area</div>
            <div class="mt-1 space-y-0.5 text-sm">
                @foreach ($summary['cost_by_area'] as $areaName => $cost)
                    <div class="flex items-center justify-between">
                        <span class="text-slate-500">{{ $areaName }}</span>
                        <span class="font-semibold text-slate-800">USD {{ number_format($cost, 2) }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- ============ Monthly Cost Trend ============ --}}
    <div class="mb-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="mb-4 text-sm font-semibold text-slate-800">Monthly Cost Trend — {{ $selectedYear }}</h2>
        <div class="flex items-end gap-2">
            @foreach ($monthlyTrend as $m)
                <div class="flex flex-1 flex-col items-center gap-1" title="{{ $m['label'] }}: USD {{ number_format($m['cost'], 2) }}">
                    <span class="text-[10px] font-medium {{ $m['cost'] > 0 ? 'text-blue-700' : 'text-slate-300' }}">
                        {{ $m['cost'] > 0 ? number_format($m['cost'], 0) : '–' }}
                    </span>
                    {{-- Fixed-height track so the percentage bar below has a definite height to resolve against. --}}
                    <div class="relative h-32 w-full">
                        <div class="absolute bottom-0 w-full rounded-t {{ $m['cost'] > 0 ? 'bg-blue-500' : 'bg-slate-100' }}"
                            style="height: {{ $maxMonthlyCost > 0 && $m['cost'] > 0 ? max(($m['cost'] / $maxMonthlyCost) * 100, 4) : 4 }}%">
                        </div>
                    </div>
                    <span class="text-[10px] text-slate-400">{{ $m['label'] }}</span>
                </div>
            @endforeach
        </div>
    </div>

    {{-- ============ Detail Table ============ --}}
    @if ($usages->isEmpty())
        <div class="rounded-2xl border border-dashed border-slate-300 bg-white py-12 text-center text-slate-500">
            No Maintenance Cost data found.
        </div>
    @else
        <div class="hidden overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm md:block">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Date</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Area</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Machine</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Machine Type</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Maintenance Type</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Order Number</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Sparepart Cost</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Total Cost</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @foreach ($usages as $row)
                            @php $lineCost = ($row->qty ?? 0) * ($row->price ?? 0); @endphp
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-3 text-slate-600">{{ $row->actual_date ? \Carbon\Carbon::parse($row->actual_date)->format('d-m-Y') : '-' }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $row->area }}</td>
                                <td class="px-4 py-3 font-medium text-slate-800">{{ $row->machine_number }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $row->machine_type }}</td>
                                <td class="px-4 py-3 text-slate-600">PM</td>
                                <td class="px-4 py-3 text-slate-600">{{ $row->order_number ?: '-' }}</td>
                                <td class="px-4 py-3 text-right text-slate-600">USD {{ number_format($lineCost, 2) }}</td>
                                <td class="px-4 py-3 text-right font-semibold text-slate-800">USD {{ number_format($lineCost, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- ============ Mobile cards ============ --}}
        <div class="space-y-3 md:hidden">
            @foreach ($usages as $row)
                @php $lineCost = ($row->qty ?? 0) * ($row->price ?? 0); @endphp
                <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="mb-2 flex items-start justify-between gap-2">
                        <div>
                            <div class="text-sm font-semibold text-slate-800">{{ $row->machine_number }}</div>
                            <div class="text-xs text-slate-500">{{ $row->machine_type }} • {{ $row->area }}</div>
                        </div>
                        <div class="shrink-0 text-xs text-slate-500">PM</div>
                    </div>

                    <div class="grid grid-cols-2 gap-y-2 border-t border-slate-100 pt-3 text-xs">
                        <div>
                            <div class="text-slate-400">Date</div>
                            <div class="font-medium text-slate-700">{{ $row->actual_date ? \Carbon\Carbon::parse($row->actual_date)->format('d-m-Y') : '-' }}</div>
                        </div>
                        <div>
                            <div class="text-slate-400">Order Number</div>
                            <div class="font-medium text-slate-700">{{ $row->order_number ?: '-' }}</div>
                        </div>
                    </div>

                    <div class="mt-3 flex items-center justify-between border-t border-slate-100 pt-3">
                        <span class="text-xs text-slate-400">Total Cost</span>
                        <span class="font-semibold text-slate-800">USD {{ number_format($lineCost, 2) }}</span>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-4">
            {{ $usages->links() }}
        </div>
    @endif

@endsection
