@extends('layouts.app')

@section('title', $group->name)

@section('content')
    @php
        $fullWidth = true;
        $contentMaxWidth = 'max-w-4xl';
    @endphp

    <div class="space-y-5">
        <x-ui.button variant="ghost" href="{{ route('groups.index') }}" class="-ml-2">Back to Groups</x-ui.button>
        <x-ui.page-header :title="$group->name" :description="$machines->count().' machine(s)'.($group->area ? ' · Area: '.$group->area->name : '')" />

        @if ($machines->isEmpty())
            <x-ui.empty-state title="No machines in this group" />
        @else
            <div class="card hidden overflow-hidden md:block">
                <div class="relative overflow-x-auto">
                    <table class="data-table min-w-full">
                        <thead>
                            <tr>
                                <th scope="col">Machine Number</th>
                                <th scope="col">Type</th>
                                <th scope="col">Area</th>
                                <th scope="col">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($machines as $machine)
                                <tr>
                                    <td class="whitespace-nowrap font-semibold text-text">{{ $machine->machine_number }}</td>
                                    <td>{{ $machine->machine_type }}</td>
                                    <td>{{ $machine->area }}</td>
                                    <td><x-ui.badge :tone="$machine->status === 'ACTIVE' ? 'success' : 'neutral'">{{ $machine->status }}</x-ui.badge></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <ul class="card divide-y divide-border md:hidden">
                @foreach ($machines as $machine)
                    <li class="flex items-start justify-between gap-3 p-4">
                        <div class="min-w-0">
                            <p class="break-words font-semibold text-text">{{ $machine->machine_number }}</p>
                            <p class="text-sm text-text-muted">{{ $machine->machine_type }} · {{ $machine->area }}</p>
                        </div>
                        <x-ui.badge :tone="$machine->status === 'ACTIVE' ? 'success' : 'neutral'" class="shrink-0">{{ $machine->status }}</x-ui.badge>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
@endsection
