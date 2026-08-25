@extends('layouts.app')

@section('content')

    <div class="mb-6 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-slate-800">Oil Audit Report</h1>
            <p class="text-sm text-slate-500">Detail, filter, and drill-down for every Oil Audit record.</p>
        </div>
        <a href="{{ route('reports.index') }}"
            class="inline-flex w-fit items-center rounded-lg bg-slate-700 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
            ← Report Center
        </a>
    </div>

    {{-- ============ Filters ============ --}}
    <form method="GET" class="mb-6 flex flex-wrap gap-2 rounded-2xl border border-slate-200 bg-slate-50 p-4 shadow-sm">
        <input type="text" name="search" value="{{ $search }}" placeholder="Search Machine Number..."
            title="Search Machine Number"
            class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 sm:w-56">

        <select name="area" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700">
            <option value="">ALL Areas</option>
            <option value="WWD" {{ $selectedArea === 'WWD' ? 'selected' : '' }}>WWD</option>
            <option value="BUL" {{ $selectedArea === 'BUL' ? 'selected' : '' }}>BUL</option>
        </select>

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

        <select name="pic" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700">
            <option value="">All PIC</option>
            @foreach ($pics as $p)
                <option value="{{ $p }}" {{ $selectedPic === $p ? 'selected' : '' }}>{{ $p }}</option>
            @endforeach
        </select>

        <select name="condition" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700">
            <option value="">All Audit Status</option>
            @foreach ($conditions as $value => $label)
                <option value="{{ $value }}" {{ $selectedCondition === $value ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>

        <select name="finding_status" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700">
            <option value="">All Finding Status</option>
            <option value="NO_FINDING" {{ $selectedFindingStatus === 'NO_FINDING' ? 'selected' : '' }}>No Finding</option>
            <option value="OPEN" {{ $selectedFindingStatus === 'OPEN' ? 'selected' : '' }}>Open</option>
            <option value="COMPLETED" {{ $selectedFindingStatus === 'COMPLETED' ? 'selected' : '' }}>Completed</option>
        </select>

        <button class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-700">
            Filter
        </button>
        <a href="{{ route('reports.oil-audit') }}"
            class="rounded-lg bg-slate-500 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-600">
            Reset
        </a>
    </form>

    {{-- ============ Summary ============ --}}
    <div class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="text-xs uppercase tracking-wide text-slate-400">Total Audit</div>
            <div class="mt-1 text-xl font-bold text-slate-800">{{ $summary['total_audit'] }}</div>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="text-xs uppercase tracking-wide text-slate-400">Total Finding</div>
            <div class="mt-1 text-xl font-bold text-rose-600">{{ $summary['total_finding'] }}</div>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="text-xs uppercase tracking-wide text-slate-400">Findings With Action Date</div>
            <div class="mt-1 text-xl font-bold text-emerald-600">{{ $summary['findings_with_action'] }}</div>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="text-xs uppercase tracking-wide text-slate-400">Avg. Action Duration</div>
            <div class="mt-1 text-xl font-bold text-sky-600">
                {{ $summary['average_action_duration'] !== null ? $summary['average_action_duration'].' days' : '-' }}
            </div>
            <p class="mt-0.5 text-[11px] text-slate-400">Only findings with an Action Date; over the filtered period.</p>
        </div>
    </div>

    {{-- ============ Table ============ --}}
    @if ($audits->isEmpty())
        <div class="rounded-2xl border border-dashed border-slate-300 bg-white py-12 text-center text-slate-500">
            No Oil Audit data found.
        </div>
    @else
        <div class="hidden overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm md:block">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Audit Date</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Area</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Machine</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Machine Type</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">PIC</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Result</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Finding</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Follow-up Status</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider text-slate-500">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @foreach ($audits as $audit)
                            @php
                                $needsFollowUp = $audit->needsFollowUp();
                                $hasFollowUp = $audit->followUp !== null;
                            @endphp
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-3 text-slate-600">{{ $audit->audited_at->format('d-m-Y') }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $audit->area }}</td>
                                <td class="px-4 py-3 font-medium text-slate-800">{{ $audit->machine_number }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $audit->machine_type }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $audit->audited_by_name ?: '-' }}</td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $audit->conditionColor()['badge'] }}">
                                        {{ $audit->conditionLabel() }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-slate-600">
                                    @if (! $needsFollowUp)
                                        <span class="text-slate-400">No Finding</span>
                                    @elseif ($hasFollowUp)
                                        {{ $audit->followUp->problems->pluck('problem')->implode(', ') ?: $audit->conditionLabel() }}
                                    @else
                                        {{ $audit->conditionLabel() }} <span class="text-amber-600">(Pending)</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    @if (! $needsFollowUp)
                                        <span class="text-slate-400">-</span>
                                    @elseif ($hasFollowUp)
                                        <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700">Completed</span>
                                    @else
                                        <span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-700">Open</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <a href="{{ route('oil-audits.history', $audit->machine_number) }}"
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
            @foreach ($audits as $audit)
                @php
                    $needsFollowUp = $audit->needsFollowUp();
                    $hasFollowUp = $audit->followUp !== null;
                @endphp
                <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="mb-2 flex items-start justify-between gap-2">
                        <div>
                            <div class="text-sm font-semibold text-slate-800">{{ $audit->machine_number }}</div>
                            <div class="text-xs text-slate-500">{{ $audit->machine_type }} • {{ $audit->area }}</div>
                        </div>
                        <span class="shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold {{ $audit->conditionColor()['badge'] }}">
                            {{ $audit->conditionLabel() }}
                        </span>
                    </div>

                    <div class="grid grid-cols-2 gap-y-2 border-t border-slate-100 pt-3 text-xs">
                        <div>
                            <div class="text-slate-400">Audit Date</div>
                            <div class="font-medium text-slate-700">{{ $audit->audited_at->format('d-m-Y') }}</div>
                        </div>
                        <div>
                            <div class="text-slate-400">PIC</div>
                            <div class="font-medium text-slate-700">{{ $audit->audited_by_name ?: '-' }}</div>
                        </div>
                        <div class="col-span-2">
                            <div class="text-slate-400">Finding</div>
                            <div class="font-medium text-slate-700">
                                @if (! $needsFollowUp)
                                    No Finding
                                @elseif ($hasFollowUp)
                                    {{ $audit->followUp->problems->pluck('problem')->implode(', ') ?: $audit->conditionLabel() }}
                                @else
                                    {{ $audit->conditionLabel() }} (Pending)
                                @endif
                            </div>
                        </div>
                        <div>
                            <div class="text-slate-400">Follow-up</div>
                            <div class="mt-0.5">
                                @if (! $needsFollowUp)
                                    <span class="text-slate-400">-</span>
                                @elseif ($hasFollowUp)
                                    <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-[11px] font-semibold text-emerald-700">Completed</span>
                                @else
                                    <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-semibold text-amber-700">Open</span>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="mt-3 border-t border-slate-100 pt-3 text-right">
                        <a href="{{ route('oil-audits.history', $audit->machine_number) }}"
                            class="rounded bg-blue-600 px-3 py-1 text-xs font-medium text-white hover:bg-blue-700">
                            View
                        </a>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-4">
            {{ $audits->links() }}
        </div>
    @endif

@endsection
