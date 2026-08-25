@extends('layouts.app')

@section('content')

    <div class="mb-6 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-slate-800">Machine Report</h1>
            <p class="text-sm text-slate-500">Summary and analysis across every machine. For per-machine history, open a machine below.</p>
        </div>
        <a href="{{ route('reports.index') }}"
            class="inline-flex w-fit items-center rounded-lg bg-slate-700 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
            ← Report Center
        </a>
    </div>

    {{-- ============ Filters ============ --}}
    <form method="GET" class="mb-6 flex flex-wrap gap-2 rounded-2xl border border-slate-200 bg-slate-50 p-4 shadow-sm">
        <input type="text" name="search" value="{{ $search }}" placeholder="Search Machine Number / Machine Type..."
            title="Search Machine Number or Machine Type"
            class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 sm:w-64">

        @if ($isAdmin)
            <select name="area" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700">
                <option value="">ALL Areas</option>
                @foreach ($areas as $a)
                    <option value="{{ $a }}" {{ $selectedArea === $a ? 'selected' : '' }}>{{ $a }}</option>
                @endforeach
            </select>
        @endif

        <select name="machine_type" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700">
            <option value="">All Machine Type</option>
            @foreach ($machineTypes as $type)
                <option value="{{ $type }}" {{ $selectedMachineType === $type ? 'selected' : '' }}>{{ $type }}</option>
            @endforeach
        </select>

        <select name="status" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700">
            <option value="">All Status</option>
            @foreach ($statuses as $s)
                <option value="{{ $s }}" {{ $selectedStatus === $s ? 'selected' : '' }}>{{ $s }}</option>
            @endforeach
        </select>

        @if ($groups->isNotEmpty())
            <select name="group_id" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700">
                <option value="">All Groups</option>
                @foreach ($groups as $g)
                    <option value="{{ $g->id }}" {{ (int) $selectedGroupId === $g->id ? 'selected' : '' }}>{{ $g->name }}</option>
                @endforeach
            </select>
        @endif

        <button class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-700">
            Filter
        </button>
        <a href="{{ route('reports.machine') }}"
            class="rounded-lg bg-slate-500 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-600">
            Reset
        </a>
    </form>

    {{-- ============ Summary ============ --}}
    <div class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-3 {{ $showGearboxMetric ? 'lg:grid-cols-4' : '' }}">
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="text-xs uppercase tracking-wide text-slate-400">Total Machine</div>
            <div class="mt-1 text-xl font-bold text-slate-800">{{ $summary['total_machine'] }}</div>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="text-xs uppercase tracking-wide text-slate-400">Active Machine</div>
            <div class="mt-1 text-xl font-bold text-emerald-600">{{ $summary['active_machine'] }}</div>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="text-xs uppercase tracking-wide text-slate-400">Inactive Machine</div>
            <div class="mt-1 text-xl font-bold text-rose-600">{{ $summary['inactive_machine'] }}</div>
        </div>
        @if ($showGearboxMetric)
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="text-xs uppercase tracking-wide text-slate-400">Gearbox Machines — WWD</div>
                <div class="mt-1 text-xl font-bold text-sky-600">{{ $gearboxCount }}</div>
            </div>
        @endif
    </div>

    {{-- ============ Table ============ --}}
    @if ($machines->isEmpty())
        <div class="rounded-2xl border border-dashed border-slate-300 bg-white py-12 text-center text-slate-500">
            No Machine data found.
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
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Group</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Last PM</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Next PM</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider text-slate-500">PM Count</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider text-slate-500">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @foreach ($machines as $row)
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-3 font-medium text-slate-800">{{ $row->machine_number }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $row->machine_type }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $row->area }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $row->group_name ?: '-' }}</td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $row->status === 'ACTIVE' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-700' }}">
                                        {{ $row->status }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-slate-600">{{ $row->last_pm ? \Carbon\Carbon::parse($row->last_pm)->format('d-m-Y') : '-' }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $row->next_pm ? $row->next_pm->format('d-m-Y') : '-' }}</td>
                                <td class="px-4 py-3 text-center text-slate-600">{{ $row->pm_count ?? 0 }}</td>
                                <td class="px-4 py-3 text-center">
                                    <a href="{{ route('machine-history.show', $row->machine_number) }}"
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
            @foreach ($machines as $row)
                <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="mb-2 flex items-start justify-between gap-2">
                        <div>
                            <div class="text-sm font-semibold text-slate-800">{{ $row->machine_number }}</div>
                            <div class="text-xs text-slate-500">{{ $row->machine_type }} • {{ $row->area }} • {{ $row->group_name ?: '-' }}</div>
                        </div>
                        <span class="shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold {{ $row->status === 'ACTIVE' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-700' }}">
                            {{ $row->status }}
                        </span>
                    </div>

                    <div class="grid grid-cols-2 gap-y-2 border-t border-slate-100 pt-3 text-xs">
                        <div>
                            <div class="text-slate-400">Last PM</div>
                            <div class="font-medium text-slate-700">{{ $row->last_pm ? \Carbon\Carbon::parse($row->last_pm)->format('d-m-Y') : '-' }}</div>
                        </div>
                        <div>
                            <div class="text-slate-400">Next PM</div>
                            <div class="font-medium text-slate-700">{{ $row->next_pm ? $row->next_pm->format('d-m-Y') : '-' }}</div>
                        </div>
                        <div>
                            <div class="text-slate-400">PM Count</div>
                            <div class="font-medium text-slate-700">{{ $row->pm_count ?? 0 }}</div>
                        </div>
                    </div>

                    <div class="mt-3 border-t border-slate-100 pt-3 text-right">
                        <a href="{{ route('machine-history.show', $row->machine_number) }}"
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
