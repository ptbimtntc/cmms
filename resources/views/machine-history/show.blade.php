@extends('layouts.app')

@section('title', 'Machine History')

@section('content')

    <div class="mb-6 flex flex-row items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold">
                {{ $machine->machine_number }}
            </h1>
            <p class="text-slate-500">
                Machine History
            </p>
        </div>
        <div>
            @php
                $backRoute = auth()->check() && auth()->user()->role !== 'guest'
                    ? route('machine-history.index')
                    : route('qr.scan');
            @endphp
            <a href="{{ $backRoute }}"
                class="inline-flex w-fit items-center rounded-lg bg-slate-700 px-4 py-2 text-white">
                ← Back
            </a>
        </div>
    </div>

    <div class="mb-4 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">

        <div class="rounded-xl border bg-white p-4 shadow-sm">
            <div class="text-xs text-slate-500">Machine Type</div>
            <div class="mt-1 text-lg font-semibold">{{ $machine->machine_type }}</div>
        </div>

        <div class="rounded-xl border bg-white p-4 shadow-sm">
            <div class="text-xs text-slate-500">Area</div>
            <div class="mt-1 text-lg font-semibold">{{ $machine->area }}</div>
        </div>

        <div class="rounded-xl border bg-white p-4 shadow-sm">
            <div class="text-xs text-slate-500">PM Cycle</div>
            <div class="mt-1 text-lg font-semibold">
                {{ $machine->pm_cycle_value }}
                {{ strtoupper($machine->pm_cycle_unit) }}
            </div>
        </div>

        <div class="rounded-xl border bg-orange-100 p-4 shadow-sm">
            <div class="text-xs text-slate-500">Last PM</div>
            <div class="mt-1 text-lg font-semibold">
                {{ $lastPm ? $lastPm->format('d-m-Y') : '-' }}
            </div>
        </div>

        <div class="rounded-xl border bg-green-100 p-4 shadow-sm">
            <div class="text-xs text-slate-500">Next PM</div>
            <div class="mt-1 text-lg font-semibold">
                {{ $nextPm ? $nextPm->format('d-m-Y') : '-' }}
            </div>
            @if (strtolower($machine->pm_cycle_unit) === 'hour')
                <div class="mt-1 text-xs text-slate-500">Estimasi</div>
            @endif
        </div>

    </div>

    @if (strtolower($machine->pm_cycle_unit) === 'hour')
        <div class="mb-4 rounded-xl border border-slate-200 bg-slate-50/50 px-4 py-2 text-sm text-slate-700">
            Note: Next PM akan akurat jika mesin running non stop
        </div>
    @endif

    {{-- ============ DESKTOP: Tabel (md ke atas) ============ --}}
    <div class="hidden overflow-x-auto rounded-xl border md:block">
        <table class="min-w-full">
            <thead class="bg-slate-100">
                <tr>
                    <th class="px-4 py-3 text-left">Actual Date</th>
                    <th class="px-4 py-3 text-left">Order Number</th>
                    <th class="px-4 py-3 text-left">PIC</th>
                    <th class="px-4 py-3 text-left">Duration</th>
                    <th class="px-4 py-3 text-left">Status</th>
                    <th class="px-4 py-3 text-left">Action</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($pmHistories as $pm)
                    <tr class="border-t">
                        <td class="px-4 py-3">
                            {{ \Carbon\Carbon::parse($pm->actual_date)->format('d-m-Y') }}
                        </td>
                        <td class="px-4 py-3">{{ $pm->order_number }}</td>
                        <td class="px-4 py-3">{{ \Illuminate\Support\Str::title(strtolower($pm->pic)) }}</td>
                        <td class="px-4 py-3">{{ $pm->duration_formatted }}</td>
                        <td class="px-4 py-3">
                            @switch($pm->status)
                                @case('OPEN')
                                    <span
                                        class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-700">OPEN</span>
                                @break

                                @case('IN_PROGRESS')
                                    <span class="rounded-full bg-slate-200 px-2.5 py-1 text-xs font-semibold text-slate-700">IN
                                        PROGRESS</span>
                                @break

                                @case('FINISHED')
                                    <span
                                        class="rounded-full bg-violet-100 px-2.5 py-1 text-xs font-semibold text-violet-700">FINISHED</span>
                                @break

                                @case('FINISHED_ON_TIME')
                                    <span
                                        class="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700">FINISHED
                                        ON TIME</span>
                                @break

                                @case('MISSED')
                                    <span
                                        class="rounded-full bg-rose-100 px-2.5 py-1 text-xs font-semibold text-rose-700">MISSED</span>
                                @break

                                @default
                                    <span
                                        class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">{{ $pm->status }}</span>
                            @endswitch
                        </td>
                        <td class="px-4 py-3">
                            <a href="{{ route('machine-history.detail', [
                                'machineNumber' => $machine->machine_number,
                                'pmSchedule' => $pm->id,
                            ]) }}"
                                class="inline-flex items-center rounded-lg bg-blue-600 px-3 py-2 text-sm font-medium text-white transition hover:bg-blue-700">
                                <svg xmlns="http://www.w3.org/2000/svg" class="mr-2 h-4 w-4" fill="none"
                                    viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M15 12H9m12 0A9 9 0 1112 3a9 9 0 019 9z" />
                                </svg>
                                Detail
                            </a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- ============ MOBILE: Card List (di bawah md) ============ --}}
    <div class="space-y-3 md:hidden">
        @forelse($pmHistories as $pm)
            <div class="rounded-xl border bg-white p-4 shadow-sm">

                <div class="mb-3 flex items-start justify-between gap-2">
                    <div>
                        <div class="text-sm font-semibold text-slate-800">
                            {{ \Carbon\Carbon::parse($pm->actual_date)->format('d-m-Y') }}
                        </div>
                        <div class="text-xs text-slate-500">Order #{{ $pm->order_number }}</div>
                    </div>
                    <span class="shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold text-slate-700">
                        @switch($pm->status)
                            @case('OPEN')
                                <span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-700">OPEN</span>
                            @break

                            @case('IN_PROGRESS')
                                <span class="rounded-full bg-slate-200 px-2.5 py-1 text-xs font-semibold text-slate-700">IN
                                    PROGRESS</span>
                            @break

                            @case('FINISHED')
                                <span
                                    class="rounded-full bg-violet-100 px-2.5 py-1 text-xs font-semibold text-violet-700">FINISHED</span>
                            @break

                            @case('FINISHED_ON_TIME')
                                <span
                                    class="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700">FINISHED
                                    ON TIME</span>
                            @break

                            @case('MISSED')
                                <span class="rounded-full bg-rose-100 px-2.5 py-1 text-xs font-semibold text-rose-700">MISSED</span>
                            @break

                            @default
                                <span
                                    class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">{{ $pm->status }}</span>
                        @endswitch
                    </span>
                </div>

                <div class="grid grid-cols-2 gap-y-2 border-t border-slate-100 pt-3 text-xs">
                    <div>
                        <div class="text-slate-400">PIC</div>
                        <div class="font-medium text-slate-700">{{ $pm->pic }}</div>
                    </div>
                    <div>
                        <div class="text-slate-400">Duration</div>
                        <div class="font-medium text-slate-700">{{ $pm->duration_formatted }}</div>
                    </div>
                </div>

                <div class="mt-3 border-t border-slate-100 pt-3 text-right">
                    <a href="{{ route('machine-history.detail', [
                        'machineNumber' => $machine->machine_number,
                        'pmSchedule' => $pm->id,
                    ]) }}"
                        class="inline-flex items-center rounded-lg bg-blue-600 px-3 py-2 text-xs font-medium text-white transition hover:bg-blue-700">
                        <svg xmlns="http://www.w3.org/2000/svg" class="mr-1.5 h-4 w-4" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M15 12H9m12 0A9 9 0 1112 3a9 9 0 019 9z" />
                        </svg>
                        Detail
                    </a>
                </div>
            </div>
            @empty
                <div class="rounded-xl border bg-white py-10 text-center text-sm text-slate-500">
                    No PM history found.
                </div>
            @endforelse
        </div>

        <div class="mt-6">
            {{ $pmHistories->links() }}
        </div>

    @endsection
