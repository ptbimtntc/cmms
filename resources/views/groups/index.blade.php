@extends('layouts.app')

@section('title', 'Group Master')

@section('content')
    @php
        $fullWidth = true;
        $canManage = ! auth()->user()->isSupervisor();
        $actions = fn ($group, $mobile = false) => ['view' => route('groups.show', $group->id), 'mobile' => $mobile]
            + ($canManage ? ['edit' => route('groups.edit', ['group' => $group->id, 'page' => request('page')]), 'delete' => route('groups.destroy', $group->id), 'confirm' => 'Delete group?'] : []);
    @endphp

    <div class="space-y-5">
        <x-ui.page-header title="Group Master" description="Manage machine group master data">
            @if ($canManage)
                <x-slot:actions>
                    <x-ui.button href="{{ route('groups.create') }}">Add Group</x-ui.button>
                </x-slot:actions>
            @endif
        </x-ui.page-header>

        @if (session('success'))
            <x-ui.alert tone="success">{{ session('success') }}</x-ui.alert>
        @endif
        @if (session('error'))
            <x-ui.alert tone="danger">{{ session('error') }}</x-ui.alert>
        @endif

        <form method="GET" class="card flex flex-col gap-3 p-4 sm:flex-row sm:items-end">
            <div class="grid gap-1.5 sm:w-72">
                <label for="gr-search" class="text-xs font-medium text-text-muted">Search</label>
                <input id="gr-search" type="text" name="search" value="{{ request('search') }}" placeholder="Group name" class="input lg:min-h-9">
            </div>
            <div class="flex gap-2 sm:ml-auto">
                <x-ui.button type="submit" class="flex-1 sm:flex-none lg:min-h-9">Filter</x-ui.button>
                <x-ui.button variant="ghost" href="{{ route('groups.index') }}" class="lg:min-h-9">Reset</x-ui.button>
            </div>
        </form>

        @if ($groups->isEmpty())
            <x-ui.empty-state title="No groups found" :description="request('search') ? 'Try another name, or reset the search.' : ($canManage ? 'Add a group, then assign machines to it.' : null)" />
        @endif

        {{-- Desktop table. Keep this list first: infinite scroll matches lists by key and position. --}}
        <div @class(['card hidden overflow-hidden', 'md:block' => $groups->isNotEmpty()])>
            <div class="relative overflow-x-auto">
                <table class="data-table min-w-full">
                    <thead>
                        <tr>
                            <th scope="col">Group Name</th>
                            <th scope="col" class="text-right">Number of Machines</th>
                            <th scope="col"><span class="sr-only">Action</span></th>
                        </tr>
                    </thead>
                    <tbody data-infinite-list="groups">
                        @foreach ($groups as $group)
                            <tr>
                                <td class="font-semibold text-text">{{ $group->name }}</td>
                                <td class="text-right tabular-nums">{{ $group->machines_count }}</td>
                                <td>@include('partials.master.row-actions', $actions($group))</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Phone cards (below md). Second list with the same key. --}}
        <div class="space-y-3 md:hidden" data-infinite-list="groups">
            @foreach ($groups as $group)
                <article class="card min-w-0 p-4">
                    <h2 class="break-words text-base font-semibold text-text">{{ $group->name }}</h2>
                    <p class="text-sm text-text-muted">{{ $group->machines_count }} machine(s)</p>
                    <div class="mt-3 border-t border-border pt-3">@include('partials.master.row-actions', $actions($group, true))</div>
                </article>
            @endforeach
        </div>

        <x-infinite-scroll :paginator="$groups" key="groups" />
    </div>
@endsection
