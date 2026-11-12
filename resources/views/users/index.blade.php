@extends('layouts.app')

@section('title', 'User Management')

@section('content')
    @php
        $fullWidth = true;
        $scope = fn ($user) => $user->area?->name
            ?? ($user->isSupervisor() && $user->areas->isNotEmpty() ? $user->areas->pluck('name')->join(', ') : ($user->seesAllAreas() ? 'All Areas' : null));
        $actions = fn ($user, $mobile = false) => ['edit' => route('users.edit', ['user' => $user, 'page' => request('page')]), 'mobile' => $mobile]
            + (auth()->id() !== $user->id ? ['delete' => route('users.destroy', $user), 'confirm' => 'Delete this user? This action cannot be undone.'] : []);
    @endphp

    <div class="space-y-5">
        <x-ui.page-header title="User Management" description="Manage system user access">
            <x-slot:actions>
                <x-ui.button href="{{ route('users.create') }}">Add User</x-ui.button>
            </x-slot:actions>
        </x-ui.page-header>

        @if (session('success'))
            <x-ui.alert tone="success">{{ session('success') }}</x-ui.alert>
        @endif

        @if ($users->isEmpty())
            <x-ui.empty-state title="No users found" />
        @endif

        {{-- Desktop table. Keep this list first: infinite scroll matches lists by key and position. --}}
        <div @class(['card hidden overflow-hidden', 'md:block' => $users->isNotEmpty()])>
            <div class="relative overflow-x-auto">
                <table class="data-table min-w-full">
                    <thead>
                        <tr>
                            <th scope="col">Name</th>
                            <th scope="col">Email</th>
                            <th scope="col">Role</th>
                            <th scope="col">Status</th>
                            <th scope="col"><span class="sr-only">Action</span></th>
                        </tr>
                    </thead>
                    <tbody data-infinite-list="users">
                        @foreach ($users as $user)
                            <tr>
                                <td>
                                    <div class="flex items-center gap-3">
                                        @include('users.partials.avatar', ['user' => $user, 'size' => 'h-8 w-8'])
                                        <span class="font-semibold text-text">{{ $user->name }}</span>
                                    </div>
                                </td>
                                <td>{{ $user->email }}</td>
                                <td>
                                    <div class="flex flex-wrap gap-1">
                                        <x-ui.badge tone="primary">{{ $user->role }}</x-ui.badge>
                                        @if ($scope($user))
                                            <x-ui.badge>{{ $scope($user) }}</x-ui.badge>
                                        @endif
                                    </div>
                                </td>
                                <td><x-ui.badge :tone="$user->is_active ? 'success' : 'neutral'">{{ $user->is_active ? 'Active' : 'Inactive' }}</x-ui.badge></td>
                                <td>@include('partials.master.row-actions', $actions($user))</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Phone cards (below md). Second list with the same key. --}}
        <div class="space-y-3 md:hidden" data-infinite-list="users">
            @foreach ($users as $user)
                <article class="card min-w-0 p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex min-w-0 items-center gap-3">
                            @include('users.partials.avatar', ['user' => $user, 'size' => 'h-10 w-10'])
                            <div class="min-w-0">
                                <h2 class="truncate font-semibold text-text">{{ $user->name }}</h2>
                                <p class="truncate text-sm text-text-muted">{{ $user->email }}</p>
                            </div>
                        </div>
                        <x-ui.badge :tone="$user->is_active ? 'success' : 'neutral'" class="shrink-0">{{ $user->is_active ? 'Active' : 'Inactive' }}</x-ui.badge>
                    </div>
                    <div class="mt-3 flex flex-wrap gap-1 border-t border-border pt-3">
                        <x-ui.badge tone="primary">{{ $user->role }}</x-ui.badge>
                        @if ($scope($user))
                            <x-ui.badge>{{ $scope($user) }}</x-ui.badge>
                        @endif
                    </div>
                    <div class="mt-3">@include('partials.master.row-actions', $actions($user, true))</div>
                </article>
            @endforeach
        </div>

        <x-infinite-scroll :paginator="$users" key="users" />
    </div>
@endsection
