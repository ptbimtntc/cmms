@extends('layouts.app')

@section('title', 'Machine Master')

@section('content')
    @php
        $fullWidth = true;
        $label = 'text-xs font-medium text-text-muted';
        $canManage = ! auth()->user()->isSupervisor();
        $hasFilters = collect(request()->only(['search', 'machine_type', 'area', 'status']))->filter()->isNotEmpty();
    @endphp

    <div class="space-y-5">
        <x-ui.page-header title="Machine Master" description="Manage machine master data" />

        @if ($canManage)
            @include('partials.master.import-form', ['action' => route('machines.import'), 'accept' => '.csv', 'id' => 'fileInput'])
        @endif

        @if ($errors->has('file'))
            <x-ui.alert tone="danger">{{ $errors->first('file') }}</x-ui.alert>
        @endif
        @if (session('success'))
            <x-ui.alert tone="success">{{ session('success') }}</x-ui.alert>
        @endif
        @if (session('machines_import_result'))
            @include('partials.master.import-result', ['result' => session('machines_import_result')])
        @endif

        <form method="GET" class="card flex flex-col gap-3 p-4 sm:flex-row sm:flex-wrap sm:items-end">
            <div class="grid gap-1.5 sm:w-64">
                <label for="mm-search" class="{{ $label }}">Search</label>
                <input id="mm-search" type="text" name="search" value="{{ request('search') }}" placeholder="Machine number, type, or area" class="input lg:min-h-9">
            </div>
            <div class="grid gap-1.5 sm:w-40">
                <label for="mm-type" class="{{ $label }}">Machine Type</label>
                <select id="mm-type" name="machine_type" class="select lg:min-h-9">
                    <option value="">All Type</option>
                    @foreach ($machineTypes as $type)
                        <option value="{{ $type->machine_type }}" @selected(request('machine_type') == $type->machine_type)>{{ $type->machine_type }}</option>
                    @endforeach
                </select>
            </div>
            <div class="grid gap-1.5 sm:w-32">
                <label for="mm-area" class="{{ $label }}">Area</label>
                <select id="mm-area" name="area" class="select lg:min-h-9">
                    <option value="">All Area</option>
                    @foreach ($areas as $a)
                        <option value="{{ $a->area }}" @selected(request('area') == $a->area)>{{ $a->area }}</option>
                    @endforeach
                </select>
            </div>
            <div class="grid gap-1.5 sm:w-36">
                <label for="mm-status" class="{{ $label }}">Status</label>
                <select id="mm-status" name="status" class="select lg:min-h-9">
                    <option value="">All Status</option>
                    <option value="ACTIVE" @selected(request('status') == 'ACTIVE')>ACTIVE</option>
                    <option value="INACTIVE" @selected(request('status') == 'INACTIVE')>INACTIVE</option>
                </select>
            </div>
            <div class="grid gap-1.5 sm:w-44">
                <label for="mm-sort" class="{{ $label }}">Sort</label>
                <select id="mm-sort" name="sort" class="select lg:min-h-9">
                    <option value="">Default Sort</option>
                    <option value="machine_number_asc" @selected(request('sort') == 'machine_number_asc')>Machine No ↑</option>
                    <option value="machine_number_desc" @selected(request('sort') == 'machine_number_desc')>Machine No ↓</option>
                    <option value="area_asc" @selected(request('sort') == 'area_asc')>Area A-Z</option>
                    <option value="area_desc" @selected(request('sort') == 'area_desc')>Area Z-A</option>
                </select>
            </div>
            <div class="flex gap-2 sm:ml-auto">
                <x-ui.button type="submit" class="flex-1 sm:flex-none lg:min-h-9">Filter</x-ui.button>
                <x-ui.button variant="ghost" href="{{ route('machines.index') }}" class="lg:min-h-9">Reset</x-ui.button>
            </div>
        </form>

        @if ($machines->isEmpty())
            <x-ui.empty-state title="No machines found"
                :description="$hasFilters ? 'Change the filters, or reset them to see every machine.' : ($canManage ? 'Import a CSV file above to add machines.' : null)" />
        @endif

        {{-- Desktop table. Keep this list first: infinite scroll matches lists by key and position. --}}
        <div @class(['card hidden overflow-hidden', 'md:block' => $machines->isNotEmpty()])>
            <div class="relative overflow-x-auto">
                <table class="data-table min-w-full">
                    <thead>
                        <tr>
                            <th scope="col">Area</th>
                            <th scope="col">Machine Type</th>
                            <th scope="col">Machine Number</th>
                            <th scope="col">Group</th>
                            <th scope="col">Status</th>
                            @if ($canManage)
                                <th scope="col"><span class="sr-only">Action</span></th>
                            @endif
                        </tr>
                    </thead>
                    <tbody data-infinite-list="machines">
                        @foreach ($machines as $m)
                            <tr>
                                <td>{{ $m->area }}</td>
                                <td>{{ $m->machine_type }}</td>
                                <td class="whitespace-nowrap font-semibold text-text">{{ $m->machine_number }}</td>
                                <td>{{ $m->group->name ?? '-' }}</td>
                                <td><x-ui.badge :tone="$m->status == 'ACTIVE' ? 'success' : 'neutral'">{{ $m->status }}</x-ui.badge></td>
                                @if ($canManage)
                                    <td>@include('partials.master.row-actions', ['edit' => route('machines.edit', ['machine' => $m->id, 'page' => request('page')]), 'delete' => route('machines.destroy', $m->id), 'confirm' => 'Delete machine?'])</td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Phone cards (below md). Second list with the same key. --}}
        <div class="space-y-3 md:hidden" data-infinite-list="machines">
            @foreach ($machines as $m)
                <article class="card min-w-0 p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h2 class="break-words text-base font-semibold text-text">{{ $m->machine_number }}</h2>
                            <p class="text-sm text-text-muted">{{ $m->machine_type }} · {{ $m->area }}</p>
                        </div>
                        <x-ui.badge :tone="$m->status == 'ACTIVE' ? 'success' : 'neutral'" class="shrink-0">{{ $m->status }}</x-ui.badge>
                    </div>
                    <p class="mt-3 border-t border-border pt-3 text-sm"><span class="text-text-muted">Group:</span> <span class="font-medium text-text">{{ $m->group->name ?? '-' }}</span></p>
                    @if ($canManage)
                        <div class="mt-3">@include('partials.master.row-actions', ['edit' => route('machines.edit', ['machine' => $m->id, 'page' => request('page')]), 'delete' => route('machines.destroy', $m->id), 'confirm' => 'Delete machine?', 'mobile' => true])</div>
                    @endif
                </article>
            @endforeach
        </div>

        <x-infinite-scroll :paginator="$machines" key="machines" />
    </div>
@endsection
