@extends('layouts.app')

@section('title', 'Machine Checklist Master')

@section('content')
    @php
        $fullWidth = true;
        $label = 'text-xs font-medium text-text-muted';
        $canManage = ! auth()->user()->isSupervisor();
        $hasFilters = collect(request()->only(['search', 'machine_type', 'section', 'maintenance_type']))->filter()->isNotEmpty();
        $actions = fn ($c, $mobile = false) => ['edit' => route('machine-checklists.edit', ['machine_checklist' => $c->id, 'page' => request('page')]), 'delete' => route('machine-checklists.destroy', $c->id), 'confirm' => 'Delete this checklist?', 'mobile' => $mobile];
        $mtype = fn ($t) => \Illuminate\Support\Str::title(strtolower($t));
    @endphp

    <div class="space-y-5">
        <x-ui.page-header title="Machine Checklist Master" description="Manage Machine Checklist Master Data">
            @if ($canManage)
                <x-slot:actions>
                    <x-ui.button href="{{ route('machine-checklists.create') }}">Add Checklist</x-ui.button>
                </x-slot:actions>
            @endif
        </x-ui.page-header>

        @if ($canManage)
            @include('partials.master.import-form', ['action' => route('machine-checklists.import'), 'id' => 'fileInput'])
        @endif

        @if (session('success'))
            <x-ui.alert tone="success">{{ session('success') }}</x-ui.alert>
        @endif
        @if (session('error'))
            <x-ui.alert tone="danger">{{ session('error') }}</x-ui.alert>
        @endif
        @if ($errors->has('file'))
            <x-ui.alert tone="danger">{{ $errors->first('file') }}</x-ui.alert>
        @endif
        @if (session('machine_checklists_import_result'))
            @include('partials.master.import-result', ['result' => session('machine_checklists_import_result')])
        @endif

        <form method="GET" class="card flex flex-col gap-3 p-4 sm:flex-row sm:flex-wrap sm:items-end">
            <div class="grid gap-1.5 sm:w-64">
                <label for="mc-search" class="{{ $label }}">Search</label>
                <input id="mc-search" type="text" name="search" value="{{ request('search') }}" placeholder="Checklist item" class="input lg:min-h-9">
            </div>
            <div class="grid gap-1.5 sm:w-44">
                <label for="machineTypeFilter" class="{{ $label }}">Machine Type</label>
                <select name="machine_type" id="machineTypeFilter" class="select lg:min-h-9">
                    <option value="">All Machine Type</option>
                    @foreach ($machineTypes as $machine)
                        <option value="{{ $machine }}" @selected(request('machine_type') == $machine)>{{ $machine }}</option>
                    @endforeach
                </select>
            </div>
            <div class="grid gap-1.5 sm:w-44">
                <label for="sectionFilter" class="{{ $label }}">Section</label>
                <select name="section" id="sectionFilter" class="select lg:min-h-9">
                    <option value="">All Section</option>
                    @foreach ($sections as $section)
                        <option value="{{ $section }}" @selected(request('section') == $section)>{{ $section }}</option>
                    @endforeach
                </select>
            </div>
            <div class="grid gap-1.5 sm:w-48">
                <label for="mc-mtype" class="{{ $label }}">Maintenance Type</label>
                <select id="mc-mtype" name="maintenance_type" class="select lg:min-h-9">
                    <option value="">All Maintenance Type</option>
                    @foreach ($maintenanceTypes as $type)
                        <option value="{{ $type }}" @selected(request('maintenance_type') == $type)>{{ \Illuminate\Support\Str::title($type) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="grid gap-1.5 sm:w-48">
                <label for="mc-sort" class="{{ $label }}">Sort</label>
                <select id="mc-sort" name="sort" class="select lg:min-h-9">
                    <option value="">Default Sort</option>
                    <option value="machine_type_asc" @selected(request('sort') == 'machine_type_asc')>Machine Type ↑</option>
                    <option value="machine_type_desc" @selected(request('sort') == 'machine_type_desc')>Machine Type ↓</option>
                    <option value="section_order_asc" @selected(request('sort') == 'section_order_asc')>Checklist Order ↑</option>
                    <option value="section_order_desc" @selected(request('sort') == 'section_order_desc')>Checklist Order ↓</option>
                    <option value="item_asc" @selected(request('sort') == 'item_asc')>Checklist Item ↑</option>
                    <option value="item_desc" @selected(request('sort') == 'item_desc')>Checklist Item ↓</option>
                </select>
            </div>
            <div class="flex gap-2 sm:ml-auto">
                <x-ui.button type="submit" class="flex-1 sm:flex-none lg:min-h-9">Filter</x-ui.button>
                <x-ui.button variant="ghost" href="{{ route('machine-checklists.index') }}" class="lg:min-h-9">Reset</x-ui.button>
            </div>
        </form>

        @if ($checklists->isEmpty())
            <x-ui.empty-state title="No checklist found." :description="$hasFilters ? 'Change the filters, or reset them to see every checklist item.' : ($canManage ? 'Add a checklist item or import a file above.' : null)" />
        @endif

        {{-- Desktop table. Keep this list first: infinite scroll matches lists by key and position. --}}
        <div @class(['card hidden overflow-hidden', 'md:block' => $checklists->isNotEmpty()])>
            <div class="relative overflow-x-auto">
                <table class="data-table min-w-full">
                    <thead>
                        <tr>
                            <th scope="col">Machine Type</th>
                            <th scope="col">Section</th>
                            <th scope="col" class="text-right">Section Order</th>
                            <th scope="col">Checklist Item</th>
                            <th scope="col">Maintenance Type</th>
                            <th scope="col" class="text-right">Item Order</th>
                            @if ($canManage)
                                <th scope="col"><span class="sr-only">Action</span></th>
                            @endif
                        </tr>
                    </thead>
                    <tbody data-infinite-list="checklists">
                        @foreach ($checklists as $checklist)
                            <tr>
                                <td class="whitespace-nowrap font-semibold text-text">{{ $checklist->machine_type }}</td>
                                <td>{{ $checklist->section }}</td>
                                <td class="text-right tabular-nums">{{ $checklist->section_order }}</td>
                                <td class="min-w-56 text-text">{{ $checklist->checklist_item }}</td>
                                <td>{{ $mtype($checklist->maintenance_type) }}</td>
                                <td class="text-right tabular-nums">{{ $checklist->item_order }}</td>
                                @if ($canManage)
                                    <td>@include('partials.master.row-actions', $actions($checklist))</td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Phone cards (below md). Second list with the same key. --}}
        <div class="space-y-3 md:hidden" data-infinite-list="checklists">
            @foreach ($checklists as $checklist)
                <article class="card min-w-0 p-4">
                    <h2 class="text-base font-semibold text-text">{{ $checklist->machine_type }}</h2>
                    <p class="text-sm text-text-muted">{{ $checklist->section }} · {{ $mtype($checklist->maintenance_type) }}</p>
                    <p class="mt-3 break-words border-t border-border pt-3 text-sm font-medium text-text">{{ $checklist->checklist_item }}</p>
                    <dl class="mt-2 grid grid-cols-2 gap-x-4 text-sm">
                        <div><dt class="text-xs text-text-muted">Section Order</dt><dd class="font-medium tabular-nums text-text">{{ $checklist->section_order }}</dd></div>
                        <div><dt class="text-xs text-text-muted">Item Order</dt><dd class="font-medium tabular-nums text-text">{{ $checklist->item_order }}</dd></div>
                    </dl>
                    @if ($canManage)
                        <div class="mt-3 border-t border-border pt-3">@include('partials.master.row-actions', $actions($checklist, true))</div>
                    @endif
                </article>
            @endforeach
        </div>

        <x-infinite-scroll :paginator="$checklists" key="checklists" />
    </div>

    <script>
        // Dependent filter: Section options follow the selected Machine Type
        (function() {
            const sectionsByType = @json($sectionsByType);
            const allSections = @json($allSections);
            const typeSelect = document.getElementById('machineTypeFilter');
            const sectionSelect = document.getElementById('sectionFilter');

            typeSelect.addEventListener('change', function() {
                const sections = typeSelect.value ? (sectionsByType[typeSelect.value] || []) : allSections;
                const current = sectionSelect.value;

                sectionSelect.innerHTML = '';
                sectionSelect.add(new Option('All Section', ''));
                sections.forEach(function(section) {
                    sectionSelect.add(new Option(section, section));
                });

                // Falls back to "All Section" when the old section isn't in the new list
                sectionSelect.value = sections.includes(current) ? current : '';
            });
        })();
    </script>
@endsection
