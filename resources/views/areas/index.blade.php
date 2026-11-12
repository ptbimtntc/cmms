@extends('layouts.app')

@section('title', 'Area Management')

@section('content')
    @php($fullWidth = true)

    <div class="space-y-5">
        <x-ui.page-header title="Area Management" description="Master data for operational areas. Adding an area here makes it immediately available in User Management, Machines, PM Schedules, the Dashboard, and the PM Status Board, with no code change needed.">
            <x-slot:actions>
                <x-ui.button href="{{ route('areas.create') }}">Add Area</x-ui.button>
            </x-slot:actions>
        </x-ui.page-header>

        @if (session('success'))
            <x-ui.alert tone="success">{{ session('success') }}</x-ui.alert>
        @endif

        @if ($areas->isEmpty())
            <x-ui.empty-state title="No areas found" description="Add the first operational area to start assigning users and machines." />
        @else
            <div class="card overflow-hidden">
                <div class="relative overflow-x-auto">
                    <table class="data-table min-w-full">
                        <thead>
                            <tr>
                                <th scope="col">Name</th>
                                <th scope="col">Slug</th>
                                <th scope="col" class="text-right">Users</th>
                                <th scope="col" class="text-right">Groups</th>
                                <th scope="col">Status</th>
                                <th scope="col"><span class="sr-only">Action</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($areas as $area)
                                <tr>
                                    <td class="font-semibold text-text">{{ $area->name }}</td>
                                    <td class="text-text-muted">{{ $area->slug }}</td>
                                    <td class="text-right tabular-nums">{{ $area->users_count }}</td>
                                    <td class="text-right tabular-nums">{{ $area->groups_count }}</td>
                                    <td><x-ui.badge :tone="$area->is_active ? 'success' : 'neutral'">{{ $area->is_active ? 'Active' : 'Inactive' }}</x-ui.badge></td>
                                    <td>@include('partials.master.row-actions', ['edit' => route('areas.edit', $area)])</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
@endsection
