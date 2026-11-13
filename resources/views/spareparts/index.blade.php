@extends('layouts.app')

@section('title', 'Sparepart Master')

@section('content')
    @php
        $fullWidth = true;
        $label = 'text-xs font-medium text-text-muted';
        $canManage = auth()->user()->isAdmin() || auth()->user()->isKoordinator();
        $hasFilters = collect(request()->only(['search', 'machine_type', 'status', 'segment']))->filter()->isNotEmpty();
        $price = fn ($v) => '$ '.number_format($v, 0, ',', '.');
        $actions = fn ($sp, $mobile = false) => ['edit' => route('spareparts.edit', ['sparepart' => $sp->id, 'page' => request('page')]), 'delete' => route('spareparts.destroy', $sp->id), 'confirm' => 'Delete sparepart?', 'mobile' => $mobile];
    @endphp

    @include('spareparts._tabs')

    <div class="space-y-5">
        <x-ui.page-header title="Sparepart Master" description="Manage sparepart master data">
            @if ($canManage)
                <x-slot:actions>
                    <x-ui.button href="{{ route('spareparts.create') }}">Add Sparepart</x-ui.button>
                </x-slot:actions>
            @endif
        </x-ui.page-header>

        @if ($canManage)
            @include('partials.master.import-form', ['action' => route('spareparts.import'), 'accept' => '.csv', 'id' => 'sparepartFileInput'])
        @endif

        @if ($errors->has('file'))
            <x-ui.alert tone="danger">{{ $errors->first('file') }}</x-ui.alert>
        @endif
        @if (session('success'))
            <x-ui.alert tone="success">{{ session('success') }}</x-ui.alert>
        @endif
        @if (session('spareparts_import_result'))
            @include('partials.master.import-result', ['result' => session('spareparts_import_result')])
        @endif

        <form method="GET" class="card flex flex-col gap-3 p-4 sm:flex-row sm:flex-wrap sm:items-end">
            <div class="grid gap-1.5 sm:w-64">
                <label for="sp-search" class="{{ $label }}">Search</label>
                <input id="sp-search" type="text" name="search" value="{{ request('search') }}" placeholder="Material number or description" class="input lg:min-h-9">
            </div>
            <div class="grid gap-1.5 sm:w-44">
                <label for="sp-type" class="{{ $label }}">Machine Type</label>
                <select id="sp-type" name="machine_type" class="select lg:min-h-9">
                    <option value="">All Machine Type</option>
                    @foreach ($machineTypes as $type)
                        <option value="{{ $type }}" @selected(request('machine_type') == $type)>{{ $type }}</option>
                    @endforeach
                </select>
            </div>
            <div class="grid gap-1.5 sm:w-36">
                <label for="sp-status" class="{{ $label }}">Status</label>
                <select id="sp-status" name="status" class="select lg:min-h-9">
                    <option value="">All Status</option>
                    <option value="ACTIVE" @selected(request('status') == 'ACTIVE')>ACTIVE</option>
                    <option value="INACTIVE" @selected(request('status') == 'INACTIVE')>INACTIVE</option>
                </select>
            </div>
            <div class="grid gap-1.5 sm:w-36">
                <label for="sp-segment" class="{{ $label }}">Segment</label>
                <select id="sp-segment" name="segment" class="select lg:min-h-9">
                    <option value="">All Segment</option>
                    @foreach ($segments as $segment)
                        <option value="{{ $segment }}" @selected(request('segment') == $segment)>{{ $segment }}</option>
                    @endforeach
                </select>
            </div>
            <div class="grid gap-1.5 sm:w-48">
                <label for="sp-sort" class="{{ $label }}">Sort</label>
                <select id="sp-sort" name="sort" class="select lg:min-h-9">
                    <option value="">Default Sort</option>
                    <option value="material_asc" @selected(request('sort') == 'material_asc')>Material No ↑</option>
                    <option value="material_desc" @selected(request('sort') == 'material_desc')>Material No ↓</option>
                    <option value="stock_asc" @selected(request('sort') == 'stock_asc')>Stock Low → High</option>
                    <option value="stock_desc" @selected(request('sort') == 'stock_desc')>Stock High → Low</option>
                </select>
            </div>
            <div class="flex gap-2 sm:ml-auto">
                <x-ui.button type="submit" class="flex-1 sm:flex-none lg:min-h-9">Filter</x-ui.button>
                <x-ui.button variant="ghost" href="{{ route('spareparts.index') }}" class="lg:min-h-9">Reset</x-ui.button>
            </div>
        </form>

        @if ($spareparts->isEmpty())
            <x-ui.empty-state title="No Data Found" :description="$hasFilters ? 'Change the filters, or reset them to see every sparepart.' : ($canManage ? 'Add a sparepart, or import a CSV file above.' : null)" />
        @endif

        {{-- Desktop table. Keep this list first: infinite scroll matches lists by key and position. --}}
        <div @class(['card hidden overflow-hidden', 'md:block' => $spareparts->isNotEmpty()])>
            <div class="relative overflow-x-auto">
                <table class="data-table min-w-full">
                    <thead>
                        <tr>
                            <th scope="col">Material No</th>
                            <th scope="col">Description</th>
                            <th scope="col">Machine Type</th>
                            <th scope="col" class="text-right">Stock</th>
                            <th scope="col">Unit</th>
                            <th scope="col" class="text-right">ROP</th>
                            <th scope="col">Location</th>
                            <th scope="col" class="text-right">Price</th>
                            <th scope="col">Status</th>
                            @if ($canManage)
                                <th scope="col" class="sticky right-0 bg-surface-sunken"><span class="sr-only">Action</span></th>
                            @endif
                        </tr>
                    </thead>
                    <tbody data-infinite-list="spareparts">
                        @foreach ($spareparts as $sparepart)
                            @php($low = $sparepart->stock <= $sparepart->rop)
                            <tr>
                                <td class="whitespace-nowrap font-semibold text-text">{{ $sparepart->material_number }}</td>
                                <td class="min-w-48">{{ $sparepart->description }}</td>
                                <td>{{ $sparepart->machine_type }}</td>
                                <td @class(['whitespace-nowrap text-right tabular-nums', 'font-semibold text-danger' => $low])>{{ $sparepart->stock }}{{ $low ? ' (LOW)' : '' }}</td>
                                <td>{{ $sparepart->unit }}</td>
                                <td class="text-right tabular-nums">{{ $sparepart->rop }}</td>
                                <td>{{ $sparepart->location }}</td>
                                <td class="whitespace-nowrap text-right tabular-nums">{{ $price($sparepart->price) }}</td>
                                <td><x-ui.badge :tone="$sparepart->status == 'ACTIVE' ? 'success' : 'neutral'">{{ $sparepart->status == 'ACTIVE' ? 'ACTIVE' : 'INACTIVE' }}</x-ui.badge></td>
                                @if ($canManage)
                                    <td class="sticky right-0 bg-surface shadow-[-10px_0_10px_-10px_rgb(15_23_42/0.25)] [&>div]:flex-nowrap">@include('partials.master.row-actions', $actions($sparepart))</td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Phone cards (below md). Second list with the same key. --}}
        <div class="space-y-3 md:hidden" data-infinite-list="spareparts">
            @foreach ($spareparts as $sparepart)
                @php($low = $sparepart->stock <= $sparepart->rop)
                <article class="card min-w-0 p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h2 class="break-words text-base font-semibold text-text">{{ $sparepart->material_number }}</h2>
                            <p class="break-words text-sm text-text-muted">{{ $sparepart->description }}</p>
                        </div>
                        <x-ui.badge :tone="$sparepart->status == 'ACTIVE' ? 'success' : 'neutral'" class="shrink-0">{{ $sparepart->status == 'ACTIVE' ? 'ACTIVE' : 'INACTIVE' }}</x-ui.badge>
                    </div>
                    <dl class="mt-3 grid grid-cols-2 gap-x-4 gap-y-2 border-t border-border pt-3 text-sm">
                        <div><dt class="text-xs text-text-muted">Machine Type</dt><dd class="font-medium text-text">{{ $sparepart->machine_type ?: '-' }}</dd></div>
                        <div><dt class="text-xs text-text-muted">Stock</dt><dd @class(['tabular-nums', 'font-semibold text-danger' => $low, 'font-medium text-text' => ! $low])>{{ $sparepart->stock }}{{ $low ? ' (LOW)' : '' }}</dd></div>
                        <div><dt class="text-xs text-text-muted">Unit</dt><dd class="font-medium text-text">{{ $sparepart->unit ?: '-' }}</dd></div>
                        <div><dt class="text-xs text-text-muted">ROP</dt><dd class="font-medium tabular-nums text-text">{{ $sparepart->rop }}</dd></div>
                        <div><dt class="text-xs text-text-muted">Location</dt><dd class="font-medium text-text">{{ $sparepart->location ?: '-' }}</dd></div>
                        <div><dt class="text-xs text-text-muted">Price</dt><dd class="font-medium tabular-nums text-text">{{ $price($sparepart->price) }}</dd></div>
                    </dl>
                    @if ($canManage)
                        <div class="mt-3">@include('partials.master.row-actions', $actions($sparepart, true))</div>
                    @endif
                </article>
            @endforeach
        </div>

        <x-infinite-scroll :paginator="$spareparts" key="spareparts" />
    </div>
@endsection
