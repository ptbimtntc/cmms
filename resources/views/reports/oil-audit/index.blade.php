@extends('layouts.app')

@section('content')

    <div class="mb-6 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-slate-800">Oil Audit Report</h1>
            <p class="text-sm text-slate-500">Every machine in Oil Audit scope, one row each, with its latest audit.</p>
        </div>
        <a href="{{ route('reports.index') }}"
            class="inline-flex w-fit items-center rounded-lg bg-slate-700 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
            ← Report Center
        </a>
    </div>

    {{-- ============ Filters ============ --}}
    <form method="GET" class="mb-6 flex flex-wrap gap-2 rounded-2xl border border-slate-200 bg-slate-50 p-4 shadow-sm">
        <input type="text" name="search" value="{{ $search }}" placeholder="Search Machine Number / Type / Description..."
            title="Search Machine Number / Type / Description"
            class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 sm:w-64">

        <select name="area" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700">
            <option value="">ALL Areas</option>
            <option value="WWD" {{ $selectedArea === 'WWD' ? 'selected' : '' }}>WWD</option>
            <option value="BUL" {{ $selectedArea === 'BUL' ? 'selected' : '' }}>BUL</option>
        </select>

        <select name="machine_type" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700">
            <option value="">All Machine Types</option>
            @foreach ($machineTypes as $type)
                <option value="{{ $type }}" {{ $selectedMachineType === $type ? 'selected' : '' }}>{{ $type }}</option>
            @endforeach
        </select>

        <select name="condition" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700">
            <option value="">All Latest Conditions</option>
            @foreach ($conditions as $value => $label)
                <option value="{{ $value }}" {{ $selectedCondition === $value ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>

        <select name="year" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700">
            <option value="">All Years (latest audit)</option>
            @foreach ($years as $y)
                <option value="{{ $y }}" {{ $selectedYear == $y ? 'selected' : '' }}>{{ $y }}</option>
            @endforeach
        </select>

        <select name="month" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700">
            <option value="">All Months (latest audit)</option>
            @foreach (range(1, 12) as $m)
                <option value="{{ $m }}" {{ $selectedMonth == $m ? 'selected' : '' }}>
                    {{ \Carbon\Carbon::create(null, $m, 1)->format('F') }}</option>
            @endforeach
        </select>

        <button class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-700">
            Filter
        </button>
        <a href="{{ route('reports.oil-audit') }}"
            class="rounded-lg bg-slate-500 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-600">
            Reset
        </a>
    </form>

    {{-- ============ Summary (machine-centric) ============ --}}
    <div class="mb-6 grid grid-cols-1 gap-3 sm:grid-cols-3">
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="text-xs uppercase tracking-wide text-slate-400">Total Machines in Oil Audit Scope</div>
            <div class="mt-1 text-xl font-bold text-slate-800">{{ $summary['total_machines'] }}</div>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="text-xs uppercase tracking-wide text-slate-400">Machines Never Audited</div>
            <div class="mt-1 text-xl font-bold text-slate-500">{{ $summary['never_audited'] }}</div>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="text-xs uppercase tracking-wide text-slate-400">Machines With Latest Finding</div>
            <div class="mt-1 text-xl font-bold text-rose-600">{{ $summary['with_latest_finding'] }}</div>
        </div>
    </div>

    {{-- ============ Table ============ --}}
    @if ($machines->isEmpty())
        <div class="rounded-2xl border border-dashed border-slate-300 bg-white py-12 text-center text-slate-500">
            No machines found.
        </div>
    @else
        <div class="hidden overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm md:block">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Machine Number</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Machine Type</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Area</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Latest Audit Condition</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Latest Audit Date</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">PIC</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Follow-up Status</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider text-slate-500">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @foreach ($machines as $machine)
                            @php
                                $audit = $machine->latestOilAudit;
                                $colors = $audit?->conditionColor();
                            @endphp
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-3 font-medium text-slate-800">{{ $machine->machine_number }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $machine->machine_type }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $machine->area }}</td>
                                <td class="px-4 py-3">
                                    @if ($audit)
                                        <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $colors['badge'] }}">
                                            {{ $audit->conditionLabel() }}
                                        </span>
                                    @else
                                        <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-400">
                                            Belum Audit
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-slate-600">{{ $audit ? $audit->audited_at->format('d-m-Y') : '-' }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $audit->audited_by_name ?? '-' }}</td>
                                <td class="px-4 py-3">
                                    @if (! $audit || ! $audit->needsFollowUp())
                                        <span class="text-slate-400">-</span>
                                    @elseif ($audit->followUp)
                                        <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700">Completed</span>
                                    @else
                                        <span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-700">Open</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <a href="{{ route('oil-audits.history', [$machine->machine_number, 'from' => 'report', 'return' => request()->getQueryString() ?? '']) }}"
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
            @foreach ($machines as $machine)
                @php
                    $audit = $machine->latestOilAudit;
                    $colors = $audit?->conditionColor();
                @endphp
                <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="mb-2 flex items-start justify-between gap-2">
                        <div>
                            <div class="text-sm font-semibold text-slate-800">{{ $machine->machine_number }}</div>
                            <div class="text-xs text-slate-500">{{ $machine->machine_type }} • {{ $machine->area }}</div>
                        </div>
                        @if ($audit)
                            <span class="shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold {{ $colors['badge'] }}">
                                {{ $audit->conditionLabel() }}
                            </span>
                        @else
                            <span class="shrink-0 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-400">
                                Belum Audit
                            </span>
                        @endif
                    </div>

                    <div class="grid grid-cols-2 gap-y-2 border-t border-slate-100 pt-3 text-xs">
                        <div>
                            <div class="text-slate-400">Latest Audit Date</div>
                            <div class="font-medium text-slate-700">{{ $audit ? $audit->audited_at->format('d-m-Y') : '-' }}</div>
                        </div>
                        <div>
                            <div class="text-slate-400">PIC</div>
                            <div class="font-medium text-slate-700">{{ $audit->audited_by_name ?? '-' }}</div>
                        </div>
                        <div class="col-span-2">
                            <div class="text-slate-400">Follow-up Status</div>
                            <div class="mt-0.5">
                                @if (! $audit || ! $audit->needsFollowUp())
                                    <span class="text-slate-400">-</span>
                                @elseif ($audit->followUp)
                                    <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-[11px] font-semibold text-emerald-700">Completed</span>
                                @else
                                    <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-semibold text-amber-700">Open</span>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="mt-3 border-t border-slate-100 pt-3 text-right">
                        <a href="{{ route('oil-audits.history', [$machine->machine_number, 'from' => 'report', 'return' => request()->getQueryString() ?? '']) }}"
                            class="rounded bg-blue-600 px-3 py-1 text-xs font-medium text-white hover:bg-blue-700">
                            View
                        </a>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-4">
            {{ $machines->links() }}
        </div>
    @endif

@endsection
