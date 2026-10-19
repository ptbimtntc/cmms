@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-3xl">
    <div class="mb-6 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-slate-800">{{ $group->name }}</h1>
            <p class="text-sm text-slate-500">
                {{ $machines->count() }} machine(s)
                @if ($group->area)
                    &middot; Area: {{ $group->area->name }}
                @endif
            </p>
        </div>

        <a href="{{ route('groups.index') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-100">
            Back to Groups
        </a>
    </div>

    {{-- ============ DESKTOP: Table (md and up) ============ --}}
    <div class="hidden overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm md:block">
        <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Machine Number</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Type</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Area</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 bg-white">
                @forelse ($machines as $machine)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3 text-sm font-semibold text-slate-800">{{ $machine->machine_number }}</td>
                        <td class="px-4 py-3 text-sm text-slate-700">{{ $machine->machine_type }}</td>
                        <td class="px-4 py-3 text-sm text-slate-700">{{ $machine->area }}</td>
                        <td class="px-4 py-3 text-sm text-slate-700">{{ $machine->status }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-8 text-center text-sm text-slate-500">No machines in this group</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </div>

    {{-- ============ MOBILE: Card List (below md) ============ --}}
    <div class="space-y-3 md:hidden">
        @forelse ($machines as $machine)
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <div class="truncate text-sm font-semibold text-slate-800">{{ $machine->machine_number }}</div>
                        <div class="text-xs text-slate-500">{{ $machine->machine_type }}</div>
                    </div>
                    <span class="shrink-0 text-xs text-slate-500">{{ $machine->status }}</span>
                </div>
                <div class="mt-2 text-xs text-slate-500">Area: {{ $machine->area }}</div>
            </div>
        @empty
            <p class="rounded-2xl border border-slate-200 bg-white p-6 text-center text-sm text-slate-500">No machines in this group</p>
        @endforelse
    </div>
</div>
@endsection
