@extends('layouts.app')

@section('content')

    <div class="mb-6 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-slate-800">Problem Analysis Report</h1>
            <p class="text-sm text-slate-500">Analyze machine problems/findings — frequency by machine, by category, and repeated occurrences.</p>
        </div>
        <a href="{{ route('reports.index') }}"
            class="inline-flex w-fit items-center rounded-lg bg-slate-700 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
            ← Report Center
        </a>
    </div>

    {{-- ============ Filters ============ --}}
    <form method="GET" class="mb-6 flex flex-wrap gap-2 rounded-2xl border border-slate-200 bg-slate-50 p-4 shadow-sm">
        <input type="text" name="search" value="{{ $search }}" placeholder="Search Machine Number / Problem / Order Number..."
            title="Search Machine Number, Problem, or Order Number"
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

        <select name="category" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700">
            <option value="">All Categories</option>
            @foreach ($categories as $c)
                <option value="{{ $c }}" {{ $selectedCategory === $c ? 'selected' : '' }}>{{ $c }}</option>
            @endforeach
        </select>

        <button class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-700">
            Filter
        </button>
        <a href="{{ route('reports.problem') }}"
            class="rounded-lg bg-slate-500 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-600">
            Reset
        </a>
    </form>

    {{-- ============ Summary ============ --}}
    <div class="mb-6 grid grid-cols-1 gap-3 sm:grid-cols-3">
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="text-xs uppercase tracking-wide text-slate-400">Total Problem/Finding</div>
            <div class="mt-1 text-xl font-bold text-slate-800">{{ $summary['total_problems'] }}</div>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="text-xs uppercase tracking-wide text-slate-400">Top Problem Category</div>
            @if ($summary['top_category'])
                <div class="mt-1 text-lg font-bold text-rose-600">{{ $summary['top_category']['label'] }}</div>
                <p class="mt-0.5 text-xs text-slate-400">{{ $summary['top_category']['count'] }} occurrences</p>
            @else
                <div class="mt-1 text-lg font-bold text-slate-400">-</div>
            @endif
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="text-xs uppercase tracking-wide text-slate-400">Machine With Most Problems</div>
            @if ($summary['top_machine'])
                <div class="mt-1 text-lg font-bold text-blue-600">{{ $summary['top_machine']['label'] }}</div>
                <p class="mt-0.5 text-xs text-slate-400">{{ $summary['top_machine']['count'] }} problems</p>
            @else
                <div class="mt-1 text-lg font-bold text-slate-400">-</div>
            @endif
        </div>
    </div>

    {{-- ============ Chart + Repeated Problems ============ --}}
    <div class="mb-6 grid gap-4 lg:grid-cols-2">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-4 text-sm font-semibold text-slate-800">Top Problem Categories</h2>
            @if ($topCategories->isEmpty())
                <p class="text-sm text-slate-500">No problem data for this period.</p>
            @else
                <div class="space-y-3">
                    @foreach ($topCategories as $row)
                        <div>
                            <div class="mb-1 flex items-center justify-between text-xs">
                                <span class="font-medium text-slate-700">{{ $row['label'] }}</span>
                                <span class="text-slate-400">{{ $row['value'] }}</span>
                            </div>
                            <div class="h-3 w-full rounded-full bg-slate-100">
                                <div class="h-3 rounded-full bg-rose-500" style="width: {{ max($row['percent'], 3) }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-3">
                <h2 class="text-sm font-semibold text-slate-800">Repeated Problems</h2>
                <p class="text-xs text-slate-400">Same machine, same problem, recorded more than once in this period.</p>
            </div>
            <div class="divide-y divide-slate-100">
                @forelse ($repeatedProblems as $row)
                    <div class="flex items-center justify-between gap-2 px-5 py-2.5 text-sm">
                        <div class="min-w-0">
                            <div class="truncate font-medium text-slate-800">{{ $row->machine_number }} — {{ $row->problem }}</div>
                            <div class="text-xs text-slate-400">{{ $row->category ?: '-' }}</div>
                        </div>
                        <div class="shrink-0 rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-700">
                            {{ $row->occurrences }}x
                        </div>
                    </div>
                @empty
                    <p class="px-5 py-6 text-center text-sm text-slate-500">No repeated problem found in this period.</p>
                @endforelse
            </div>
        </div>
    </div>

    {{-- ============ Detail Table ============ --}}
    @if ($problems->isEmpty())
        <div class="rounded-2xl border border-dashed border-slate-300 bg-white py-12 text-center text-slate-500">
            No Problem data found.
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
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Order Number</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Problem</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Category</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Severity</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">PIC</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @foreach ($problems as $row)
                            @php
                                $severityColor = match ($row->severity) {
                                    'High' => 'bg-red-100 text-red-700',
                                    'Medium' => 'bg-amber-100 text-amber-700',
                                    'Low' => 'bg-emerald-100 text-emerald-700',
                                    default => 'bg-slate-100 text-slate-500',
                                };
                            @endphp
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-3 text-slate-600">{{ $row->actual_date ? \Carbon\Carbon::parse($row->actual_date)->format('d-m-Y') : '-' }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $row->area }}</td>
                                <td class="px-4 py-3 font-medium text-slate-800">{{ $row->machine_number }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $row->machine_type }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $row->order_number ?: '-' }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $row->problem }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $row->category ?: '-' }}</td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $severityColor }}">
                                        {{ $row->severity ?: '-' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-slate-600">{{ $row->pic ? \Illuminate\Support\Str::title(strtolower($row->pic)) : '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- ============ Mobile cards ============ --}}
        <div class="space-y-3 md:hidden">
            @foreach ($problems as $row)
                @php
                    $severityColor = match ($row->severity) {
                        'High' => 'bg-red-100 text-red-700',
                        'Medium' => 'bg-amber-100 text-amber-700',
                        'Low' => 'bg-emerald-100 text-emerald-700',
                        default => 'bg-slate-100 text-slate-500',
                    };
                @endphp
                <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="mb-2 flex items-start justify-between gap-2">
                        <div>
                            <div class="text-sm font-semibold text-slate-800">{{ $row->machine_number }}</div>
                            <div class="text-xs text-slate-500">{{ $row->machine_type }} • {{ $row->area }}</div>
                        </div>
                        <span class="shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold {{ $severityColor }}">
                            {{ $row->severity ?: '-' }}
                        </span>
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
                        <div class="col-span-2">
                            <div class="text-slate-400">Problem</div>
                            <div class="font-medium text-slate-700">{{ $row->problem }} <span class="text-slate-400">({{ $row->category ?: '-' }})</span></div>
                        </div>
                        <div>
                            <div class="text-slate-400">PIC</div>
                            <div class="font-medium text-slate-700">{{ $row->pic ? \Illuminate\Support\Str::title(strtolower($row->pic)) : '-' }}</div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-4">
            {{ $problems->links() }}
        </div>
    @endif

    {{-- ============ Forecasting (placeholder — foundation only) ============ --}}
    <div class="mt-8 rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-6 text-center">
        <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Forecasting / Predictive Maintenance</h2>
        <p class="mt-1 text-sm text-slate-400">
            Likely next problem and frequency per machine will appear here once available. Not implemented yet.
        </p>
    </div>

@endsection
