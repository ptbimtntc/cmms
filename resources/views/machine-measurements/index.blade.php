@extends('layouts.app')

@section('title', 'Measurement Master')

@section('content')
    @php
        $fullWidth = true;
        $label = 'text-xs font-medium text-text-muted';
        $canManage = ! auth()->user()->isSupervisor();
        $actions = fn ($m, $mobile = false) => ['edit' => route('machine-measurements.edit', ['machine_measurement' => $m->id, 'page' => request('page')]), 'delete' => route('machine-measurements.destroy', $m->id), 'confirm' => 'Delete this measurement?', 'mobile' => $mobile];
        $firstNo = method_exists($measurements, 'firstItem') ? ($measurements->firstItem() ?? 1) : 1;
    @endphp

    <div class="space-y-5">
        <x-ui.page-header title="Measurement Master" description="Measurement master items per machine type">
            @if ($canManage)
                <x-slot:actions>
                    <x-ui.button href="{{ route('machine-measurements.create') }}">Add Measurement</x-ui.button>
                </x-slot:actions>
            @endif
        </x-ui.page-header>

        @if ($canManage)
            @include('partials.master.import-form', ['action' => route('machine-measurements.import'), 'accept' => '.csv', 'id' => 'fileInput'])
        @endif

        @if (session('success'))
            <x-ui.alert tone="success">{{ session('success') }}</x-ui.alert>
        @endif
        @if ($errors->has('file'))
            <x-ui.alert tone="danger">{{ $errors->first('file') }}</x-ui.alert>
        @endif
        @if (session('machine_measurements_import_result'))
            @include('partials.master.import-result', ['result' => session('machine_measurements_import_result')])
        @endif

        {{-- Filters submit on change. --}}
        <form method="GET" class="card flex flex-col gap-3 p-4 sm:flex-row sm:flex-wrap sm:items-end">
            <div class="grid gap-1.5 sm:w-56">
                <label for="mm-type" class="{{ $label }}">Machine Type</label>
                <select id="mm-type" name="machine_type" class="select lg:min-h-9" onchange="this.form.submit()">
                    <option value="">All Machine Type</option>
                    @foreach ($machines->unique('machine_type') as $machine)
                        <option value="{{ $machine->machine_type }}" @selected(request('machine_type') == $machine->machine_type)>{{ $machine->machine_type }}</option>
                    @endforeach
                </select>
            </div>
            <div class="grid gap-1.5 sm:w-56">
                <label for="mm-measurement" class="{{ $label }}">Measurement</label>
                <select id="mm-measurement" name="measurement" class="select lg:min-h-9" onchange="this.form.submit()">
                    <option value="">All Measurements</option>
                    @foreach ($measurements->pluck('measurement_item')->unique() as $measurement)
                        <option value="{{ $measurement }}" @selected(request('measurement') == $measurement)>{{ $measurement }}</option>
                    @endforeach
                </select>
            </div>
            <div class="grid gap-1.5 sm:w-52">
                <label for="mm-sort" class="{{ $label }}">Sort</label>
                <select id="mm-sort" name="sort" class="select lg:min-h-9" onchange="this.form.submit()">
                    <option value="">Default Sort</option>
                    <option value="machine_type_asc" @selected(request('sort') == 'machine_type_asc')>Machine Type ↑</option>
                    <option value="machine_type_desc" @selected(request('sort') == 'machine_type_desc')>Machine Type ↓</option>
                    <option value="measurement_asc" @selected(request('sort') == 'measurement_asc')>Measurement A-Z</option>
                    <option value="measurement_desc" @selected(request('sort') == 'measurement_desc')>Measurement Z-A</option>
                </select>
            </div>
            @if (request()->hasAny(['machine_type', 'measurement', 'sort']))
                <x-ui.button variant="ghost" href="{{ route('machine-measurements.index') }}" class="sm:ml-auto lg:min-h-9">Reset Filter</x-ui.button>
            @endif
        </form>

        @if ($measurements->isEmpty())
            <x-ui.empty-state title="No data found" :description="$canManage ? 'Add a measurement item or import a CSV file above.' : null" />
        @endif

        {{-- Desktop table. Keep this list first: infinite scroll matches lists by key and position. --}}
        <div @class(['card hidden overflow-hidden', 'md:block' => $measurements->isNotEmpty()])>
            <div class="relative overflow-x-auto">
                <table class="data-table min-w-full">
                    <thead>
                        <tr>
                            <th scope="col" class="text-right">No</th>
                            <th scope="col">Machine Type</th>
                            <th scope="col">Measurement Item</th>
                            <th scope="col">Unit</th>
                            <th scope="col">Standard</th>
                            @if ($canManage)
                                <th scope="col"><span class="sr-only">Action</span></th>
                            @endif
                        </tr>
                    </thead>
                    <tbody data-infinite-list="measurements">
                        @foreach ($measurements as $measurement)
                            <tr>
                                <td class="text-right tabular-nums text-text-muted">{{ $firstNo + $loop->index }}</td>
                                <td class="whitespace-nowrap font-semibold text-text">{{ $measurement->machine_type }}</td>
                                <td class="text-text">{{ $measurement->measurement_item }}</td>
                                <td>{{ $measurement->unit }}</td>
                                <td>{{ $measurement->standard }}</td>
                                @if ($canManage)
                                    <td>@include('partials.master.row-actions', $actions($measurement))</td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Phone cards (below md). Second list with the same key. --}}
        <div class="space-y-3 md:hidden" data-infinite-list="measurements">
            @foreach ($measurements as $measurement)
                <article class="card min-w-0 p-4">
                    <h2 class="text-base font-semibold text-text">{{ $measurement->machine_type }}</h2>
                    <p class="break-words text-sm text-text-muted">{{ $measurement->measurement_item }}</p>
                    <dl class="mt-3 grid grid-cols-2 gap-x-4 border-t border-border pt-3 text-sm">
                        <div><dt class="text-xs text-text-muted">Unit</dt><dd class="font-medium text-text">{{ $measurement->unit ?: '-' }}</dd></div>
                        <div><dt class="text-xs text-text-muted">Standard</dt><dd class="font-medium text-text">{{ $measurement->standard ?: '-' }}</dd></div>
                    </dl>
                    @if ($canManage)
                        <div class="mt-3 border-t border-border pt-3">@include('partials.master.row-actions', $actions($measurement, true))</div>
                    @endif
                </article>
            @endforeach
        </div>

        <x-infinite-scroll :paginator="$measurements" key="measurements" />
    </div>
@endsection
