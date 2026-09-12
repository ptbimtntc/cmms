@extends('layouts.app')

@section('title', 'Machine History')

@section('content')

    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold">
                Machine History
            </h1>
            <p class="text-slate-500">
                Preventive Maintenance History
            </p>
        </div>
    </div>
    <form method="GET" class="mb-4 flex flex-wrap gap-2 rounded-2xl border border-slate-200 bg-slate-50 p-4 shadow-sm">
        <input title="Search machine / type / order number..." type="text" name="search" value="{{ request('search') }}"
            placeholder="Search..."
            class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 sm:w-40">

        <select name="area" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700">
            <option value="">All Areas</option>
            @foreach ($areas as $area)
                <option value="{{ $area }}" {{ request('area') == $area ? 'selected' : '' }}>{{ $area }}
                </option>
            @endforeach
        </select>

        <select name="machine_type" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700">
            <option value="">All Machine Type</option>
            @foreach ($machineTypes as $type)
                <option value="{{ $type }}" {{ request('machine_type') == $type ? 'selected' : '' }}>
                    {{ $type }}</option>
            @endforeach
        </select>

        <button
            class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-700">Filter</button>
        <a href="{{ route('machine-history.index') }}"
            class="rounded-lg bg-slate-500 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-600">Reset</a>
    </form>
    {{-- ============ DESKTOP: Tabel (md ke atas) ============ --}}
    <div class="hidden overflow-x-auto rounded-xl border md:block">
        <table class="min-w-full">
            <thead class="bg-slate-100">
                <tr>
                    <th class="px-4 py-3 text-left">Machine Number</th>
                    <th class="px-4 py-3 text-left">Machine Type</th>
                    <th class="px-4 py-3 text-left">Area</th>
                    <th class="px-4 py-3 text-left">Last PM</th>
                    <th class="px-4 py-3 text-center">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($machines as $machine)
                    <tr class="border-t">
                        <td class="px-4 py-3">{{ $machine->machine_number }}</td>
                        <td class="px-4 py-3">{{ $machine->machine_type }}</td>
                        <td class="px-4 py-3">{{ $machine->area }}</td>
                        <td class="px-4 py-3">
                            {{ $machine->last_pm ? \Carbon\Carbon::parse($machine->last_pm)->format('d-m-Y') : '-' }}
                        </td>
                        <td class="px-4 py-3 text-center">
                            <a href="{{ route('machine-history.show', $machine->machine_number) }}"
                                class="inline-flex items-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                                View History
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="py-10 text-center text-slate-500">
                            No machine history found.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- ============ MOBILE: Card List (di bawah md) ============ --}}
    <div class="space-y-3 md:hidden">
        @forelse($machines as $machine)
            <div class="rounded-xl border bg-white p-4 shadow-sm">

                <div class="mb-3 flex items-start justify-between gap-2">
                    <div>
                        <div class="text-sm font-semibold text-slate-800">{{ $machine->machine_number }}</div>
                        <div class="text-xs text-slate-500">{{ $machine->machine_type }}</div>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-y-2 border-t border-slate-100 pt-3 text-xs">
                    <div>
                        <div class="text-slate-400">Area</div>
                        <div class="font-medium text-slate-700">{{ $machine->area }}</div>
                    </div>
                    <div>
                        <div class="text-slate-400">Last PM</div>
                        <div class="font-medium text-slate-700">
                            {{ $machine->last_pm ? \Carbon\Carbon::parse($machine->last_pm)->format('d-m-Y') : '-' }}
                        </div>
                    </div>
                </div>

                <div class="mt-3 border-t border-slate-100 pt-3 text-right">
                    <a href="{{ route('machine-history.show', $machine->machine_number) }}"
                        class="inline-flex items-center rounded-lg bg-blue-600 px-4 py-2 text-xs font-medium text-white hover:bg-blue-700">
                        View History
                    </a>
                </div>
            </div>
        @empty
            <div class="rounded-xl border bg-white py-10 text-center text-sm text-slate-500">
                No machine history found.
            </div>
        @endforelse
    </div>

    <div class="mt-6">
        {{ $machines->links() }}
    </div>

@endsection
