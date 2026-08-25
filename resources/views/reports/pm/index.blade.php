@extends('layouts.app')

@section('content')

    <div class="mb-6 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-slate-800">PM Report</h1>
            <p class="text-sm text-slate-500">Detail, filter, and drill-down for every PM Schedule record.</p>
        </div>
        <a href="{{ route('reports.index') }}"
            class="inline-flex w-fit items-center rounded-lg bg-slate-700 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
            ← Report Center
        </a>
    </div>

    {{-- ============ Filters ============ --}}
    <form method="GET" class="mb-6 flex flex-wrap gap-2 rounded-2xl border border-slate-200 bg-slate-50 p-4 shadow-sm">
        <input type="text" name="search" value="{{ $search }}" placeholder="Search Machine Number / Order Number..."
            title="Search Machine Number or Order Number"
            class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 sm:w-64">

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

        <select name="machine_type" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700">
            <option value="">All Machine Type</option>
            @foreach ($machineTypes as $type)
                <option value="{{ $type }}" {{ $selectedMachineType === $type ? 'selected' : '' }}>{{ $type }}</option>
            @endforeach
        </select>

        <select name="machine" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700">
            <option value="">All Machines</option>
            @foreach ($machines as $m)
                <option value="{{ $m }}" {{ $selectedMachine === $m ? 'selected' : '' }}>{{ $m }}</option>
            @endforeach
        </select>

        <select name="pic" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700">
            <option value="">All PIC</option>
            @foreach ($pics as $p)
                <option value="{{ $p }}" {{ $selectedPic === $p ? 'selected' : '' }}>
                    {{ \Illuminate\Support\Str::title(strtolower($p)) }}</option>
            @endforeach
        </select>

        <select name="status" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700">
            <option value="">All Status</option>
            @foreach ($statuses as $s)
                <option value="{{ $s }}" {{ $selectedStatus === $s ? 'selected' : '' }}>
                    {{ str_replace('_', ' ', $s) }}</option>
            @endforeach
        </select>

        <button class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-700">
            Filter
        </button>
        <a href="{{ route('reports.pm') }}"
            class="rounded-lg bg-slate-500 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-600">
            Reset
        </a>
    </form>

    {{-- ============ Summary ============ --}}
    <div class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-7">
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="text-xs uppercase tracking-wide text-slate-400">Total Schedule</div>
            <div class="mt-1 text-xl font-bold text-slate-800">{{ $summary['total'] }}</div>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="text-xs uppercase tracking-wide text-slate-400">Finish</div>
            <div class="mt-1 text-xl font-bold text-violet-600">{{ $summary['finished'] }}</div>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="text-xs uppercase tracking-wide text-slate-400">Finish On Time</div>
            <div class="mt-1 text-xl font-bold text-emerald-600">{{ $summary['finished_on_time'] }}</div>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="text-xs uppercase tracking-wide text-slate-400">Open</div>
            <div class="mt-1 text-xl font-bold text-amber-600">{{ $summary['open'] }}</div>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="text-xs uppercase tracking-wide text-slate-400">Missed</div>
            <div class="mt-1 text-xl font-bold text-rose-600">{{ $summary['missed'] }}</div>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="text-xs uppercase tracking-wide text-slate-400">Completion %</div>
            <div class="mt-1 text-xl font-bold text-blue-600">{{ $summary['completion_percent'] }}%</div>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="text-xs uppercase tracking-wide text-slate-400">Closing %</div>
            <div class="mt-1 text-xl font-bold text-sky-600">{{ $summary['closing_percent'] }}%</div>
        </div>
    </div>

    {{-- ============ Table ============ --}}
    @if ($schedules->isEmpty())
        <div class="rounded-2xl border border-dashed border-slate-300 bg-white py-12 text-center text-slate-500">
            No PM data found.
        </div>
    @else
        <div class="hidden overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm md:block">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Plan Date</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Action Date</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Area</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Machine Number</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Machine Type</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Order Number</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">PIC</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Due Date</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Status</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider text-slate-500">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @foreach ($schedules as $pm)
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-3 text-slate-600">{{ $pm->plan_date ? \Carbon\Carbon::parse($pm->plan_date)->format('d-m-Y') : '-' }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $pm->actual_date ? \Carbon\Carbon::parse($pm->actual_date)->format('d-m-Y') : '-' }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $pm->area }}</td>
                                <td class="px-4 py-3 font-medium text-slate-800">{{ $pm->machine_number }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $pm->machine_type }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $pm->order_number ?: '-' }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $pm->pic ? \Illuminate\Support\Str::title(strtolower($pm->pic)) : '-' }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $pm->due_date ? \Carbon\Carbon::parse($pm->due_date)->format('d-m-Y') : '-' }}</td>
                                <td class="px-4 py-3">
                                    @php
                                        $statusColor = match ($pm->status) {
                                            'OPEN' => 'bg-amber-100 text-amber-700',
                                            'IN_PROGRESS' => 'bg-slate-200 text-slate-700',
                                            'FINISHED' => 'bg-violet-100 text-violet-700',
                                            'FINISHED_ON_TIME' => 'bg-emerald-100 text-emerald-700',
                                            'MISSED' => 'bg-rose-100 text-rose-700',
                                            default => 'bg-slate-100 text-slate-700',
                                        };
                                    @endphp
                                    <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusColor }}">
                                        {{ str_replace('_', ' ', $pm->status) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <a href="{{ route('machine-history.detail', ['machineNumber' => $pm->machine_number, 'pmSchedule' => $pm->id]) }}"
                                        class="rounded bg-blue-600 px-3 py-1 text-xs font-medium text-white hover:bg-blue-700">
                                        View
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- ============ Mobile cards ============ --}}
        <div class="space-y-3 md:hidden">
            @foreach ($schedules as $pm)
                <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="mb-2 flex items-start justify-between gap-2">
                        <div>
                            <div class="text-sm font-semibold text-slate-800">{{ $pm->machine_number }}</div>
                            <div class="text-xs text-slate-500">{{ $pm->machine_type }} • {{ $pm->area }}</div>
                        </div>
                        @php
                            $statusColor = match ($pm->status) {
                                'OPEN' => 'bg-amber-100 text-amber-700',
                                'IN_PROGRESS' => 'bg-slate-200 text-slate-700',
                                'FINISHED' => 'bg-violet-100 text-violet-700',
                                'FINISHED_ON_TIME' => 'bg-emerald-100 text-emerald-700',
                                'MISSED' => 'bg-rose-100 text-rose-700',
                                default => 'bg-slate-100 text-slate-700',
                            };
                        @endphp
                        <span class="shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusColor }}">
                            {{ str_replace('_', ' ', $pm->status) }}
                        </span>
                    </div>

                    <div class="grid grid-cols-2 gap-y-2 border-t border-slate-100 pt-3 text-xs">
                        <div>
                            <div class="text-slate-400">Order Number</div>
                            <div class="font-medium text-slate-700">{{ $pm->order_number ?: '-' }}</div>
                        </div>
                        <div>
                            <div class="text-slate-400">PIC</div>
                            <div class="font-medium text-slate-700">{{ $pm->pic ? \Illuminate\Support\Str::title(strtolower($pm->pic)) : '-' }}</div>
                        </div>
                        <div>
                            <div class="text-slate-400">Plan Date</div>
                            <div class="font-medium text-slate-700">{{ $pm->plan_date ? \Carbon\Carbon::parse($pm->plan_date)->format('d-m-Y') : '-' }}</div>
                        </div>
                        <div>
                            <div class="text-slate-400">Due Date</div>
                            <div class="font-medium text-slate-700">{{ $pm->due_date ? \Carbon\Carbon::parse($pm->due_date)->format('d-m-Y') : '-' }}</div>
                        </div>
                        <div>
                            <div class="text-slate-400">Action Date</div>
                            <div class="font-medium text-slate-700">{{ $pm->actual_date ? \Carbon\Carbon::parse($pm->actual_date)->format('d-m-Y') : '-' }}</div>
                        </div>
                    </div>

                    <div class="mt-3 border-t border-slate-100 pt-3 text-right">
                        <a href="{{ route('machine-history.detail', ['machineNumber' => $pm->machine_number, 'pmSchedule' => $pm->id]) }}"
                            class="rounded bg-blue-600 px-3 py-1 text-xs font-medium text-white hover:bg-blue-700">
                            View
                        </a>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-4">
            {{ $schedules->links() }}
        </div>
    @endif

    {{-- ============ Forecasting (placeholder — foundation only) ============ --}}
    <div class="mt-8 rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-6 text-center">
        <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Forecasting / Predictive Maintenance</h2>
        <p class="mt-1 text-sm text-slate-400">
            Predicted problems, spareparts, and confidence per machine will appear here once available. Not implemented yet.
        </p>
    </div>

@endsection
