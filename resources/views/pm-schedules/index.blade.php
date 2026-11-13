@extends('layouts.app')

@php
    // This table has many columns and feels cramped under the shared
    // max-w-7xl content width, so it uses the full content area.
    $contentMaxWidth = 'max-w-full';
    $fullWidth = true;

    $role = auth()->user()->role;
    $canManage = $role === 'ADMIN' || str_starts_with($role, 'KOORDINATOR');
    $canImport = auth()->user()->isAdmin() || auth()->user()->isKoordinator();
    $hasFilters = collect(request()->except('page'))->filter(fn ($v) => filled($v))->isNotEmpty();
    $date = fn ($value) => $value ? \Carbon\Carbon::parse($value)->format('d-m-Y') : '-';
    $picName = fn ($pic) => $pic ? \Illuminate\Support\Str::title(strtolower($pic)) : null;
@endphp

@section('content')
    <div class="space-y-5">
        <x-ui.page-header title="PM Schedule" description="Find a machine's PM, then start it, fill it, or assign a PIC.">
            @if ($canImport)
                <x-slot:actions>
                    <x-ui.button variant="outline" id="pm-import-open">Import CSV</x-ui.button>
                    <x-ui.button href="{{ route('pm-schedules.create') }}">Add Schedule</x-ui.button>
                </x-slot:actions>
            @endif
        </x-ui.page-header>

        @if ($errors->has('file'))
            <x-ui.alert tone="danger">{{ $errors->first('file') }}</x-ui.alert>
        @endif
        @if (session('success'))
            <x-ui.alert tone="success">{{ session('success') }}</x-ui.alert>
        @endif
        @if (session('warning'))
            <x-ui.alert tone="warning">{{ session('warning') }}</x-ui.alert>
        @endif
        @if (session('pm_schedules_import_result'))
            @php($importResult = session('pm_schedules_import_result'))
            <x-ui.alert tone="info" title="Import result">
                <span class="font-semibold">{{ $importResult['imported'] }}</span> imported,
                <span class="font-semibold">{{ $importResult['duplicate'] }}</span> duplicate,
                <span class="font-semibold">{{ $importResult['skipped'] }}</span> skipped or invalid.
            </x-ui.alert>
        @endif

        {{-- Filters: plain GET form, same parameter names as before. Single-choice fields first;
             the two multi-select dropdowns share the last row with the buttons, so an open
             list never covers another control. --}}
        <form method="GET" class="card space-y-3 p-4">
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-[minmax(14rem,2fr)_repeat(4,minmax(0,1fr))]">
                <div class="grid gap-1.5 sm:col-span-2 lg:col-span-1">
                    <label for="pm-filter-search" class="text-xs font-medium text-text-muted">Search</label>
                    <input id="pm-filter-search" title="Search machine / type / order number..." type="text" name="search"
                        value="{{ request('search') }}" placeholder="Machine, type, or order number" class="input lg:min-h-9">
                </div>

                @if (auth()->user()->seesAllAreas())
                    <div class="grid gap-1.5">
                        <label for="pm-filter-area" class="text-xs font-medium text-text-muted">Area</label>
                        <select id="pm-filter-area" name="area" class="select lg:min-h-9">
                            <option value="">All Areas</option>
                            @foreach ($areas as $area)
                                <option value="{{ $area }}" {{ request('area') == $area ? 'selected' : '' }}>{{ $area }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                <div class="grid gap-1.5">
                    <label for="pm-filter-type" class="text-xs font-medium text-text-muted">Machine type</label>
                    <select id="pm-filter-type" name="machine_type" class="select lg:min-h-9">
                        <option value="">All Machine Type</option>
                        @foreach ($machineTypes as $type)
                            <option value="{{ $type }}" {{ request('machine_type') == $type ? 'selected' : '' }}>{{ $type }}</option>
                        @endforeach
                    </select>
                </div>

                @if ($canFilterPic)
                    <div class="grid gap-1.5">
                        <label for="pm-filter-pic" class="text-xs font-medium text-text-muted">PIC</label>
                        <select id="pm-filter-pic" name="pic" class="select lg:min-h-9">
                            <option value="">All PIC</option>
                            <option value="__UNASSIGNED__" {{ request('pic') === '__UNASSIGNED__' ? 'selected' : '' }}>Unassigned</option>
                            @foreach ($picFilterOptions as $picOption)
                                <option value="{{ $picOption }}" {{ request('pic') === $picOption ? 'selected' : '' }}>{{ $picOption }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                <div class="grid gap-1.5">
                    <label for="pm-filter-year" class="text-xs font-medium text-text-muted">Year</label>
                    <select id="pm-filter-year" name="plan_year" class="select lg:min-h-9">
                        <option value="">All Year</option>
                        @foreach ($years as $y)
                            <option value="{{ $y }}" {{ request('plan_year') == $y ? 'selected' : '' }}>{{ $y }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end">
                <div class="grid gap-1.5">
                    <span class="text-xs font-medium text-text-muted">Status</span>
                    <x-checkbox-dropdown name="status" label="Status" :options="[
                        'OPEN' => 'OPEN',
                        'IN_PROGRESS' => 'IN PROGRESS',
                        'FINISHED' => 'FINISHED',
                        'FINISHED_ON_TIME' => 'FINISHED ON TIME',
                        'MISSED' => 'MISSED',
                    ]" :selected="(array) request('status', [])" />
                </div>

                <div class="grid gap-1.5">
                    <span class="text-xs font-medium text-text-muted">Month</span>
                    <x-checkbox-dropdown name="plan_month" label="Month" :options="array_combine($months, $months)"
                        :selected="(array) request('plan_month', [])" />
                </div>

                <div class="flex gap-2 sm:ml-auto">
                    <x-ui.button type="submit" class="flex-1 sm:flex-none lg:min-h-9">Filter</x-ui.button>
                    <x-ui.button variant="ghost" href="{{ route('pm-schedules.index') }}" class="lg:min-h-9">Reset</x-ui.button>
                </div>
            </div>
        </form>

        @if ($schedules->isEmpty())
            <x-ui.empty-state
                :title="$hasFilters ? 'No PM schedule matches these filters.' : 'No PM schedule yet.'"
                :description="$hasFilters
                    ? 'Widen the filters, or reset them to see every schedule you can access.'
                    : ($canImport ? 'Add a schedule or import a CSV to fill this list.' : 'Schedules assigned to you will appear here.')">
                @if ($hasFilters)
                    <x-ui.button variant="outline" href="{{ route('pm-schedules.index') }}">Reset filters</x-ui.button>
                @elseif ($canImport)
                    <x-ui.button href="{{ route('pm-schedules.create') }}">Add Schedule</x-ui.button>
                @endif
            </x-ui.empty-state>
        @endif

        {{-- Desktop table. Keep this list first: infinite scroll matches lists by key and position. --}}
        <div @class(['card hidden overflow-hidden', 'md:block' => $schedules->isNotEmpty()])>
            <div class="relative overflow-x-auto">
                <table class="data-table min-w-full">
                    <thead>
                        <tr>
                            <th scope="col">Machine</th>
                            <th scope="col">Area</th>
                            <th scope="col">Plan</th>
                            <th scope="col">Due</th>
                            <th scope="col">PIC</th>
                            <th scope="col">Status</th>
                            <th scope="col">Order Number</th>
                            {{-- Actions stay pinned on the right when the table scrolls sideways on narrower screens. --}}
                            <th scope="col" class="sticky right-0 bg-surface-sunken"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody data-infinite-list="schedules">
                        @foreach ($schedules as $pm)
                            <tr>
                                <td>
                                    <div class="font-semibold text-text">{{ $pm->machine_number }}</div>
                                    <div class="text-xs text-text-muted">{{ $pm->machine_type }}</div>
                                </td>
                                <td class="text-text-muted">{{ $pm->area }}</td>
                                <td class="whitespace-nowrap">
                                    <div>{{ $date($pm->plan_date) }}</div>
                                    <div class="text-xs text-text-muted">{{ $pm->plan_month }} {{ $pm->plan_year }}</div>
                                </td>
                                <td class="whitespace-nowrap">{{ $date($pm->due_date) }}</td>
                                <td>
                                    @if ($canManage)
                                        @include('partials.assign-pic-select', ['id' => 'assign-pic-t-' . $pm->id, 'recordId' => $pm->id, 'label' => $pm->machine_number, 'pics' => $picsByArea[$pm->area] ?? [], 'current' => $pm->pic])
                                    @else
                                        {{ $picName($pm->pic) ?? '-' }}
                                    @endif
                                </td>
                                <td>@include('pm-schedules.partials.status-badge', ['status' => $pm->status])</td>
                                <td class="text-text-muted">{{ $pm->order_number ?? '-' }}</td>
                                <td class="sticky right-0 bg-surface shadow-[-10px_0_10px_-10px_rgb(15_23_42/0.25)] whitespace-nowrap [&>div]:flex-nowrap">@include('pm-schedules.partials.row-actions')</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Phone cards (below md). Second list with the same key. --}}
        <div class="space-y-3 md:hidden" data-infinite-list="schedules">
            @foreach ($schedules as $pm)
                <article class="card min-w-0 p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h2 class="truncate text-base font-semibold text-text">{{ $pm->machine_number }}</h2>
                            <p class="truncate text-sm text-text-muted">{{ $pm->machine_type }} · {{ $pm->area }}</p>
                        </div>
                        @include('pm-schedules.partials.status-badge', ['status' => $pm->status, 'short' => true])
                    </div>

                    <dl class="mt-3 grid grid-cols-2 gap-x-4 gap-y-2 border-t border-border pt-3 text-sm">
                        <div>
                            <dt class="text-xs text-text-muted">Plan Date</dt>
                            <dd class="font-medium text-text">{{ $date($pm->plan_date) }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-text-muted">Due Date</dt>
                            <dd class="font-medium text-text">{{ $date($pm->due_date) }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-text-muted">Month / Year</dt>
                            <dd class="font-medium text-text">{{ $pm->plan_month }} {{ $pm->plan_year }}</dd>
                        </div>
                        @unless ($canManage)
                            <div>
                                <dt class="text-xs text-text-muted">PIC</dt>
                                <dd class="font-medium text-text">{{ $picName($pm->pic) ?? '-' }}</dd>
                            </div>
                        @endunless
                    </dl>

                    @if ($canManage)
                        <div class="mt-3 border-t border-border pt-3">
                            <p class="mb-1.5 text-xs text-text-muted">PIC: {{ $picName($pm->pic) ?? 'Unassigned' }}</p>
                            @include('partials.assign-pic-select', ['id' => 'assign-pic-c-' . $pm->id, 'recordId' => $pm->id, 'label' => $pm->machine_number, 'pics' => $picsByArea[$pm->area] ?? [], 'current' => $pm->pic])
                        </div>
                    @endif

                    <div class="mt-3 border-t border-border pt-3">
                        @include('pm-schedules.partials.row-actions')
                    </div>
                </article>
            @endforeach
        </div>

        <x-infinite-scroll :paginator="$schedules" key="schedules" />
    </div>

    @if ($canImport)
        <x-ui.modal id="pm-import-modal" title="Import PM schedules"
            description="Upload a .csv file in the PM Schedule import template format.">
            <form action="{{ route('pm-schedules.import') }}" method="POST" enctype="multipart/form-data" class="mt-5 space-y-4">
                @csrf
                <x-ui.field label="CSV file" for="fileInput" error="file" required>
                    <input type="file" id="fileInput" name="file" accept=".csv" required
                        class="block w-full text-sm text-text file:mr-3 file:min-h-11 file:cursor-pointer file:rounded-control file:border file:border-border-strong file:bg-surface file:px-3 file:text-sm file:font-medium file:text-text hover:file:bg-surface-muted">
                </x-ui.field>
                <div class="flex justify-end gap-2 pt-2">
                    <x-ui.button variant="ghost" id="pm-import-cancel">Cancel</x-ui.button>
                    <x-ui.button type="submit" variant="success">Import</x-ui.button>
                </div>
            </form>
        </x-ui.modal>

        <script>
            (function () {
                const modal = document.getElementById('pm-import-modal');
                const show = () => { modal.classList.remove('hidden'); modal.classList.add('flex'); };
                const hide = () => { modal.classList.add('hidden'); modal.classList.remove('flex'); };
                document.getElementById('pm-import-open').addEventListener('click', show);
                document.getElementById('pm-import-cancel').addEventListener('click', hide);
                modal.addEventListener('click', (e) => { if (e.target === modal) hide(); });
                @if ($errors->has('file')) show(); @endif
            })();
        </script>
    @endif

    @include('partials.assign-pic-script', ['baseUrl' => '/pm-schedules'])

    <x-ui.modal id="pm-start-modal" title="Start PM activity" size="sm">
        <p id="pm-start-machine" class="mt-1 text-sm text-text-muted"></p>

        {{-- Wiring (open/close, online vs offline, offline queue) lives in resources/js/pm/start.js. --}}
        <form id="pm-start-form" method="POST" class="mt-5 space-y-4" data-base-action="{{ url('pm-schedules') }}">
            @csrf
            <x-ui.field label="Start date & time" for="pm-start-input" required>
                <input type="datetime-local" name="started_at" id="pm-start-input" required class="input">
            </x-ui.field>
            <div class="flex justify-end gap-2 pt-2">
                <x-ui.button variant="ghost" id="pm-start-cancel">Cancel</x-ui.button>
                <x-ui.button type="submit">START</x-ui.button>
            </div>
        </form>
    </x-ui.modal>

    @include('partials.activity-conflict-modal')
@endsection
