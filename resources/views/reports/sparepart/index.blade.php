@extends('layouts.app')

@section('content')

    <div class="mb-6 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-slate-800">Sparepart Usage Report</h1>
            <p class="text-sm text-slate-500">Detail, filter, and drill-down for every sparepart used in PM execution.</p>
        </div>
        <a href="{{ route('reports.index') }}"
            class="inline-flex w-fit items-center rounded-lg bg-slate-700 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
            ← Report Center
        </a>
    </div>

    {{-- ============ Filters ============ --}}
    <form method="GET" class="mb-6 flex flex-wrap gap-2 rounded-2xl border border-slate-200 bg-slate-50 p-4 shadow-sm">
        <input type="text" name="search" value="{{ $search }}" placeholder="Search Material Number / Description / Order Number..."
            title="Search Material Number, Description, or Order Number"
            class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 sm:w-72">

        @if ($isAdmin)
            <select name="area" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700">
                <option value="">ALL Areas</option>
                @foreach ($areas as $a)
                    <option value="{{ $a }}" {{ $selectedArea === $a ? 'selected' : '' }}>{{ $a }}</option>
                @endforeach
            </select>
        @endif

        <select name="year" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700">
            <option value="">All Years</option>
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

        <select name="segment" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700">
            <option value="">All Segments</option>
            @foreach ($segments as $seg)
                <option value="{{ $seg }}" {{ $selectedSegment === $seg ? 'selected' : '' }}>{{ $seg }}</option>
            @endforeach
        </select>

        <select name="status" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700">
            <option value="">All Status</option>
            @foreach ($statuses as $s)
                <option value="{{ $s }}" {{ $selectedStatus === $s ? 'selected' : '' }}>{{ $s }}</option>
            @endforeach
        </select>

        <button class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-700">
            Filter
        </button>
        <a href="{{ route('reports.sparepart') }}"
            class="rounded-lg bg-slate-500 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-600">
            Reset
        </a>
    </form>

    {{-- ============ Summary ============ --}}
    <div class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="text-xs uppercase tracking-wide text-slate-400">Total Usage Transaction</div>
            <div class="mt-1 text-xl font-bold text-slate-800">{{ $summary['total_transactions'] }}</div>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="text-xs uppercase tracking-wide text-slate-400">Total Quantity Used</div>
            <div class="mt-1 text-xl font-bold text-blue-600">{{ number_format($summary['total_quantity']) }}</div>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="text-xs uppercase tracking-wide text-slate-400">Total Usage Cost</div>
            <div class="mt-1 text-xl font-bold text-emerald-600">USD {{ number_format($summary['total_cost'], 2) }}</div>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="text-xs uppercase tracking-wide text-slate-400">Unique Material Number</div>
            <div class="mt-1 text-xl font-bold text-sky-600">{{ $summary['unique_materials'] }}</div>
        </div>
    </div>

    {{-- ============ Top Analysis ============ --}}
    <div class="mb-6 grid gap-4 lg:grid-cols-2">
        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-4 py-3">
                <h2 class="text-sm font-semibold text-slate-800">Top 10 Most Used Spareparts</h2>
            </div>
            <div class="divide-y divide-slate-100">
                @forelse ($topUsage as $row)
                    <div class="flex items-center justify-between gap-2 px-4 py-2.5 text-sm">
                        <div class="min-w-0">
                            <div class="truncate font-medium text-slate-800">{{ $row->description ?: '-' }}</div>
                            <div class="text-xs text-slate-400">{{ $row->material_number }}</div>
                        </div>
                        <div class="shrink-0 font-semibold text-blue-600">{{ number_format($row->total_qty) }}</div>
                    </div>
                @empty
                    <p class="px-4 py-6 text-center text-sm text-slate-500">No usage data for this period.</p>
                @endforelse
            </div>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-4 py-3">
                <h2 class="text-sm font-semibold text-slate-800">Top 10 Highest Cost Spareparts</h2>
            </div>
            <div class="divide-y divide-slate-100">
                @forelse ($topCost as $row)
                    <div class="flex items-center justify-between gap-2 px-4 py-2.5 text-sm">
                        <div class="min-w-0">
                            <div class="truncate font-medium text-slate-800">{{ $row->description ?: '-' }}</div>
                            <div class="text-xs text-slate-400">{{ $row->material_number }}</div>
                        </div>
                        <div class="shrink-0 font-semibold text-emerald-600">USD {{ number_format($row->total_cost, 2) }}</div>
                    </div>
                @empty
                    <p class="px-4 py-6 text-center text-sm text-slate-500">No usage data for this period.</p>
                @endforelse
            </div>
        </div>
    </div>

    {{-- ============ Detail Table ============ --}}
    @if ($usages->isEmpty())
        <div class="rounded-2xl border border-dashed border-slate-300 bg-white py-12 text-center text-slate-500">
            No Sparepart Usage data found.
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
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Order Number</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Material Number</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Description</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider text-slate-500">Qty</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider text-slate-500">Unit</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Price</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Total Cost</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @foreach ($usages as $row)
                            @php $lineTotal = ($row->qty ?? 0) * ($row->price ?? 0); @endphp
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-3 text-slate-600">{{ $row->actual_date ? \Carbon\Carbon::parse($row->actual_date)->format('d-m-Y') : '-' }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $row->area }}</td>
                                <td class="px-4 py-3 font-medium text-slate-800">{{ $row->machine_number }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $row->order_number ?: '-' }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $row->material_number }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $row->description }}</td>
                                <td class="px-4 py-3 text-center text-slate-600">{{ $row->qty }}</td>
                                <td class="px-4 py-3 text-center text-slate-600">{{ $row->unit ?: '-' }}</td>
                                <td class="px-4 py-3 text-right text-slate-600">USD {{ number_format($row->price, 2) }}</td>
                                <td class="px-4 py-3 text-right font-semibold text-slate-800">USD {{ number_format($lineTotal, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- ============ Mobile cards ============ --}}
        <div class="space-y-3 md:hidden">
            @foreach ($usages as $row)
                @php $lineTotal = ($row->qty ?? 0) * ($row->price ?? 0); @endphp
                <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="mb-2 flex items-start justify-between gap-2">
                        <div>
                            <div class="text-sm font-semibold text-slate-800">{{ $row->material_number }}</div>
                            <div class="text-xs text-slate-500">{{ $row->description }}</div>
                        </div>
                        <div class="shrink-0 text-xs text-slate-500">{{ $row->machine_number }} • {{ $row->area }}</div>
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
                        <div>
                            <div class="text-slate-400">Qty / Unit</div>
                            <div class="font-medium text-slate-700">{{ $row->qty }} {{ $row->unit ?: '-' }}</div>
                        </div>
                        <div>
                            <div class="text-slate-400">Price</div>
                            <div class="font-medium text-slate-700">USD {{ number_format($row->price, 2) }}</div>
                        </div>
                    </div>

                    <div class="mt-3 flex items-center justify-between border-t border-slate-100 pt-3">
                        <span class="text-xs text-slate-400">Total</span>
                        <span class="font-semibold text-emerald-600">USD {{ number_format($lineTotal, 2) }}</span>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-4">
            {{ $usages->links() }}
        </div>
    @endif

    {{-- ============ Forecasting (placeholder — foundation only) ============ --}}
    <div class="mt-8 rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-6 text-center">
        <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Forecasting / Predictive Sparepart Needs</h2>
        <p class="mt-1 text-sm text-slate-400">
            Predicted sparepart needs per machine for the next PM will appear here once available. Not implemented yet.
        </p>
    </div>

@endsection
