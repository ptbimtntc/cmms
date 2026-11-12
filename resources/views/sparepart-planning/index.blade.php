@extends('layouts.app')

@section('title', 'Sparepart Planning')

@section('content')
    @php
        $fullWidth = true;
        $monthNames = collect(range(1, 12))->mapWithKeys(fn ($m) => [$m => \Illuminate\Support\Carbon::create(null, $m, 1)->translatedFormat('F')]);
        $tabs = ['mandatory' => 'Mandatory Sparepart', 'estimate' => 'Sparepart Order Estimation'];
        $label = 'text-xs font-medium text-text-muted';
    @endphp

    <div class="space-y-5">
        <x-ui.page-header title="Sparepart Planning" description="Mandatory sparepart per machine type and the order estimation for upcoming PM." />

        <nav class="-mt-1 flex overflow-x-auto border-b border-border" aria-label="Sparepart Planning">
            @foreach ($tabs as $key => $text)
                @php($active = $tab === $key)
                <a href="{{ route('sparepart-planning.index', ['tab' => $key]) }}" @if ($active) aria-current="page" @endif
                    @class([
                        '-mb-px inline-flex min-h-11 flex-1 items-center justify-center border-b-2 px-3 py-1.5 text-center text-sm font-semibold transition-colors sm:flex-none sm:whitespace-nowrap',
                        'border-primary text-primary-hover' => $active,
                        'border-transparent text-text-muted hover:border-border-strong hover:text-text' => ! $active,
                    ])>{{ $text }}</a>
            @endforeach
        </nav>

        @if (session('success'))
            <x-ui.alert tone="success">{{ session('success') }}</x-ui.alert>
        @endif

        @if ($tab === 'mandatory')
            @if ($canManage)
                <form action="{{ route('sparepart-planning.store') }}" method="POST" class="card grid gap-3 p-4 md:grid-cols-12 md:items-start">
                    @csrf
                    <div class="grid gap-1.5 md:col-span-3">
                        <label for="sp-type" class="{{ $label }}">Machine Type</label>
                        <select id="sp-type" name="machine_type" required class="select lg:min-h-9">
                            <option value="">-- Select --</option>
                            @foreach ($machineTypes as $type)
                                <option value="{{ $type }}" @selected(old('machine_type') === $type)>{{ $type }}</option>
                            @endforeach
                        </select>
                        @error('machine_type') <p class="form-error">{{ $message }}</p> @enderror
                    </div>
                    <div class="grid min-w-0 gap-1.5 md:col-span-6">
                        <label for="mandatory-sparepart" class="{{ $label }}">Sparepart</label>
                        <select name="sparepart_id" id="mandatory-sparepart" required class="select">
                            <option value="">-- Select Sparepart --</option>
                            @foreach ($spareparts as $sp)
                                <option value="{{ $sp->id }}" @selected((int) old('sparepart_id') === $sp->id)>{{ $sp->material_number }} - {{ $sp->description }}</option>
                            @endforeach
                        </select>
                        @error('sparepart_id') <p class="form-error">{{ $message }}</p> @enderror
                    </div>
                    <div class="grid gap-1.5 md:col-span-1">
                        <label for="sp-qty" class="{{ $label }}">Qty</label>
                        <input id="sp-qty" type="number" name="qty" min="1" step="1" required value="{{ old('qty', 1) }}" class="input lg:min-h-9">
                        @error('qty') <p class="form-error">{{ $message }}</p> @enderror
                    </div>
                    <div class="grid gap-1.5 md:col-span-2">
                        {{-- Spacer label keeps the button on the same line as the fields. --}}
                        <span class="{{ $label }} hidden md:block" aria-hidden="true">&nbsp;</span>
                        <x-ui.button type="submit" class="w-full lg:min-h-9">Add</x-ui.button>
                    </div>
                </form>
            @else
                <x-ui.alert tone="info">Read-only view.</x-ui.alert>
            @endif

            @forelse ($mandatories as $type => $items)
                <section class="card overflow-hidden" aria-label="Machine Type {{ $type }}">
                    <h2 class="border-b border-border bg-surface-sunken px-4 py-3 text-sm font-semibold text-text">Machine Type: {{ $type }}</h2>
                    <div class="relative overflow-x-auto">
                        <table class="data-table min-w-full">
                            <thead>
                                <tr>
                                    <th scope="col">Material Number</th>
                                    <th scope="col">Description</th>
                                    <th scope="col" class="text-right">Qty</th>
                                    @if ($canManage)
                                        {{-- Actions stay pinned on the right when the table scrolls sideways. --}}
                                        <th scope="col" class="sticky right-0 bg-surface-sunken"><span class="sr-only">Action</span></th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($items as $item)
                                    <tr>
                                        <td class="whitespace-nowrap font-semibold text-text">{{ $item->sparepart->material_number }}</td>
                                        <td class="min-w-48">{{ $item->sparepart->description }}</td>
                                        <td class="whitespace-nowrap text-right tabular-nums text-text">{{ $item->qty }} {{ $item->sparepart->unit }}</td>
                                        @if ($canManage)
                                            <td class="sticky right-0 bg-surface shadow-[-10px_0_10px_-10px_rgb(15_23_42/0.25)]">
                                                <div class="flex justify-end gap-2">
                                                    <x-ui.button size="sm" variant="outline" href="{{ route('sparepart-planning.edit', $item) }}">Edit</x-ui.button>
                                                    <form action="{{ route('sparepart-planning.destroy', $item) }}" method="POST" onsubmit="return confirm('Remove this mandatory sparepart?');">
                                                        @csrf
                                                        @method('DELETE')
                                                        <x-ui.button type="submit" size="sm" variant="ghost" class="text-danger hover:bg-danger-light hover:text-danger-hover">Delete</x-ui.button>
                                                    </form>
                                                </div>
                                            </td>
                                        @endif
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            @empty
                <x-ui.empty-state title="No mandatory sparepart configured yet."
                    :description="$canManage ? 'Pick a machine type and sparepart above, then Add.' : null" />
            @endforelse

            @if ($canManage)
                <link href="https://cdn.jsdelivr.net/npm/tom-select/dist/css/tom-select.css" rel="stylesheet">
                <script src="https://cdn.jsdelivr.net/npm/tom-select/dist/js/tom-select.complete.min.js"></script>
                <script>
                    window.addEventListener('load', function () {
                        if (window.TomSelect) { new TomSelect('#mandatory-sparepart', { maxOptions: 100 }); }
                    });
                </script>
            @endif
        @else
            {{-- One GET form for the whole estimate: Enter recalculates; the named buttons below
                 (reset, remove, add_sparepart_id, export via formaction) each submit it with their value. --}}
            <form method="GET" action="{{ route('sparepart-planning.index') }}" id="estimate-form" class="space-y-5">
                <input type="hidden" name="tab" value="estimate">
                @if ($estimate)
                    <input type="hidden" name="prev_scope" value="{{ $scope }}">
                @endif
                {{-- Default (Enter key) action = recalculate, never a remove/add button. --}}
                <button type="submit" class="sr-only" tabindex="-1" aria-hidden="true">Recalculate</button>

                <div class="card grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-5 lg:items-end">
                    <div class="grid gap-1.5">
                        <label for="se-type" class="{{ $label }}">Machine Type</label>
                        <select id="se-type" name="machine_type" class="select lg:min-h-9">
                            <option value="">-- Select machine type --</option>
                            @foreach ($machineTypes as $type)
                                <option value="{{ $type }}" @selected($machineType === $type)>{{ $type }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="grid gap-1.5">
                        <label for="se-month" class="{{ $label }}">Month</label>
                        <select id="se-month" name="month" class="select lg:min-h-9">
                            <option value="all" @selected(! $month)>All months</option>
                            @foreach ($monthNames as $num => $name)
                                <option value="{{ $num }}" @selected($month === $num)>{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="grid gap-1.5">
                        <label for="se-year" class="{{ $label }}">Year</label>
                        <select id="se-year" name="year" class="select lg:min-h-9">
                            @foreach ($years as $y)
                                <option value="{{ $y }}" @selected($year === $y)>{{ $y }}</option>
                            @endforeach
                        </select>
                    </div>
                    @if ($areas->isNotEmpty())
                        <div class="grid gap-1.5">
                            <label for="se-area" class="{{ $label }}">Area</label>
                            <select id="se-area" name="area" class="select lg:min-h-9">
                                <option value="">All areas</option>
                                @foreach ($areas as $a)
                                    <option value="{{ $a }}" @selected($area === $a)>{{ $a }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    <x-ui.button type="submit" class="w-full lg:min-h-9">Calculate</x-ui.button>
                </div>

                @unless ($estimate)
                    <x-ui.empty-state title="Select a machine type and PM period to build the order estimate." />
                @else
                    @foreach ($messages as $message)
                        <x-ui.alert tone="warning">{{ $message }}</x-ui.alert>
                    @endforeach

                    <div class="card grid gap-4 p-4 sm:grid-cols-3 sm:p-5">
                        <div>
                            <p class="{{ $label }}">Machines with PM in period (system)</p>
                            <p class="mt-1 font-signage text-3xl font-semibold tabular-nums text-text">{{ $estimate['machines_system'] }}</p>
                            <p class="text-xs text-text-muted">Distinct machines with an unfinished PM schedule.</p>
                        </div>
                        <div class="grid content-start gap-1.5">
                            <label for="machines_final" class="{{ $label }}">Final Machine Count (editable)</label>
                            <input id="machines_final" type="number" name="machines_final" min="0" step="1" value="{{ $estimate['machines_final'] }}"
                                class="input font-signage text-xl font-semibold tabular-nums" aria-describedby="machines_final-hint">
                            <p id="machines_final-hint" class="form-hint">Used for all mandatory quantities. Does not change PM schedules.</p>
                        </div>
                        <div class="flex flex-wrap content-end items-end gap-2">
                            <x-ui.button type="submit" class="lg:min-h-9">Recalculate</x-ui.button>
                            <x-ui.button type="submit" variant="outline" name="reset" value="qty" class="lg:min-h-9"
                                onclick="return confirm('Reset all quantity corrections to the calculated values? Manual items are kept.');">Reset quantities</x-ui.button>
                            <x-ui.button type="submit" variant="outline" formaction="{{ route('sparepart-planning.export') }}" class="lg:min-h-9">Export Excel</x-ui.button>
                        </div>
                    </div>

                    <section class="card overflow-hidden" aria-labelledby="se-order-list">
                        <h2 id="se-order-list" class="border-b border-border bg-surface-sunken px-4 py-3 text-sm font-semibold text-text">Order list: {{ $machineType }}</h2>
                        <div class="relative overflow-x-auto">
                            <table class="data-table min-w-full">
                                <thead>
                                    <tr>
                                        <th scope="col">Source</th>
                                        <th scope="col">Material Number</th>
                                        <th scope="col">Description</th>
                                        <th scope="col">Unit</th>
                                        <th scope="col" class="text-right">Qty / Machine</th>
                                        <th scope="col" class="text-right">System Estimate</th>
                                        <th scope="col" class="text-right">Order Qty (final)</th>
                                        <th scope="col"><span class="sr-only">Action</span></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($estimate['rows'] as $row)
                                        @php($id = $row['sparepart_id'])
                                        <tr>
                                            <td><x-ui.badge :tone="$row['source'] === 'Mandatory' ? 'primary' : 'info'">{{ $row['source'] }}</x-ui.badge></td>
                                            <td class="whitespace-nowrap font-semibold text-text">{{ $row['material_number'] }}</td>
                                            <td class="min-w-48">{{ $row['description'] }}</td>
                                            <td>{{ $row['unit'] ?? '-' }}</td>
                                            <td class="text-right tabular-nums">{{ $row['per_machine'] ?? '-' }}</td>
                                            <td class="text-right tabular-nums">{{ $row['system_qty'] ?? '-' }}</td>
                                            <td class="text-right">
                                                @if ($row['source'] === 'Mandatory')
                                                    <input type="hidden" name="orig[{{ $id }}]" value="{{ $row['auto_qty'] }}">
                                                    <input type="number" min="0" step="1" name="qty[{{ $id }}]" value="{{ $row['order_qty'] }}" aria-label="Order qty {{ $row['material_number'] }}"
                                                        @class(['input w-24 text-right tabular-nums lg:min-h-9', 'border-warning bg-warning-light' => $row['overridden']])>
                                                    @if ($row['overridden'])
                                                        <p class="mt-1 text-xs text-warning-text">Edited (calculated: {{ $row['auto_qty'] }})</p>
                                                    @endif
                                                @else
                                                    <input type="number" min="0" step="1" name="manual[{{ $id }}]" value="{{ $row['order_qty'] }}" aria-label="Order qty {{ $row['material_number'] }}"
                                                        class="input w-24 text-right tabular-nums lg:min-h-9">
                                                @endif
                                            </td>
                                            <td class="text-right">
                                                @if ($row['source'] === 'Manual')
                                                    <x-ui.button type="submit" size="sm" variant="ghost" name="remove" value="{{ $id }}" class="text-danger hover:bg-danger-light hover:text-danger-hover">Remove</x-ui.button>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="8" class="py-8 text-center text-text-muted">No mandatory sparepart is configured for {{ $machineType }}. Add items manually below.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        <p class="border-t border-border px-4 py-3 text-xs text-text-muted">System Estimate = system machine count × qty per machine. Order Qty starts as final machine count × qty per machine and can be corrected; 0 excludes the item. Corrections are kept when the machine count changes.</p>
                    </section>

                    <div class="card grid gap-3 p-4 md:grid-cols-12 md:items-end">
                        <div class="grid min-w-0 gap-1.5 md:col-span-8">
                            <label for="add-sparepart" class="{{ $label }}">Add extra sparepart (not multiplied by machine count)</label>
                            <select name="add_sparepart_id" id="add-sparepart" class="select">
                                <option value="">-- Select Sparepart --</option>
                                @foreach ($spareparts as $sp)
                                    <option value="{{ $sp->id }}">{{ $sp->material_number }} - {{ $sp->description }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="grid gap-1.5 md:col-span-2">
                            <label for="se-add-qty" class="{{ $label }}">Qty</label>
                            <input id="se-add-qty" type="number" name="add_qty" min="0" step="1" value="1" class="input lg:min-h-9">
                        </div>
                        <x-ui.button type="submit" variant="outline" class="w-full md:col-span-2 lg:min-h-9">Add</x-ui.button>
                    </div>

                    @php($existing = $estimate['rows']->pluck('sparepart_id')->all())
                    <section class="card overflow-hidden" aria-labelledby="se-usage">
                        <div class="border-b border-border bg-surface-sunken px-4 py-3">
                            <h2 id="se-usage" class="text-sm font-semibold text-text">Actual usage history (reference only)</h2>
                            <p class="text-xs text-text-muted">
                                Finished {{ $machineType }} PMs, {{ $usageReference['from']->format('d M Y') }} to {{ $usageReference['to']->format('d M Y') }}.
                                Not included in the order list unless you add it.
                            </p>
                        </div>
                        <div class="relative overflow-x-auto">
                            <table class="data-table min-w-full">
                                <thead>
                                    <tr>
                                        <th scope="col">Material Number</th>
                                        <th scope="col">Description</th>
                                        <th scope="col">Unit</th>
                                        <th scope="col" class="text-right">Total Used</th>
                                        <th scope="col" class="text-right">PM Count</th>
                                        <th scope="col"><span class="sr-only">Action</span></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($usageReference['rows'] as $ref)
                                        <tr>
                                            <td class="whitespace-nowrap font-semibold text-text">{{ $ref['material_number'] }}</td>
                                            <td class="min-w-48">{{ $ref['description'] }}</td>
                                            <td>{{ $ref['unit'] ?? '-' }}</td>
                                            <td class="text-right tabular-nums">{{ $ref['total'] }}</td>
                                            <td class="text-right tabular-nums">{{ $ref['pm_count'] }}</td>
                                            <td class="text-right">
                                                @if (in_array($ref['sparepart_id'], $existing, true))
                                                    <span class="text-xs text-text-muted">In list</span>
                                                @else
                                                    <x-ui.button type="submit" size="sm" variant="outline" name="add_sparepart_id" value="{{ $ref['sparepart_id'] }}">Add</x-ui.button>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="6" class="py-8 text-center text-text-muted">No finished PM usage recorded in the reference period.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </section>
                @endunless
            </form>

            <link href="https://cdn.jsdelivr.net/npm/tom-select/dist/css/tom-select.css" rel="stylesheet">
            <script src="https://cdn.jsdelivr.net/npm/tom-select/dist/js/tom-select.complete.min.js"></script>
            <script>
                window.addEventListener('load', function () {
                    if (window.TomSelect && document.getElementById('add-sparepart')) { new TomSelect('#add-sparepart', { maxOptions: 100 }); }
                });
            </script>
        @endif
    </div>
@endsection
