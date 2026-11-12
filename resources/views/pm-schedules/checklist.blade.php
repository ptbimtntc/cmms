@extends('layouts.app')

@php
    $fullWidth = true;
    $pmDate = $executionDate
        ? $executionDate->format('d-m-Y')
        : ($pmSchedule->actual_date ? \Carbon\Carbon::parse($pmSchedule->actual_date)->format('d-m-Y') : '-');

    // Sparepart cost bands: the number carries the value, the colour only flags the band.
    $costTone = match (true) {
        $spareCost == 0 => 'text-text-muted',
        $spareCost < 1000 => 'text-success',
        $spareCost < 3000 => 'text-warning-text',
        default => 'text-danger',
    };

    // One set of inputs serves both layouts: a table from md, a card per item below md.
    $actions = [
        'clean' => ['label' => 'Clean', 'applies' => fn ($item) => $item->maintenance_type == 'clean'],
        'check' => ['label' => 'Check', 'applies' => fn ($item) => $item->maintenance_type != 'replace'],
        'lubrication' => ['label' => 'Lubrication', 'applies' => fn ($item) => $item->maintenance_type == 'lubrication'],
    ];
@endphp

@section('content')
    <div class="space-y-5">
        <x-ui.page-header title="PM Checklist" :description="$pmSchedule->machine_number . ' · ' . $pmSchedule->machine_type . ' · ' . $pmSchedule->order_number">
            <x-slot:actions>
                <x-ui.button variant="outline" href="{{ route('pm-schedules.edit', $pmSchedule->id) }}">Back to Fill PM</x-ui.button>
            </x-slot:actions>
        </x-ui.page-header>

        @if (session('warning'))
            <x-ui.alert tone="danger" title="Warning!">{{ session('warning') }}</x-ui.alert>
        @endif

        <section class="card p-5" aria-labelledby="checklist-machine-heading">
            <h2 id="checklist-machine-heading" class="sr-only">Machine Information</h2>
            <dl class="grid grid-cols-2 gap-x-4 gap-y-3 sm:grid-cols-3 lg:grid-cols-5">
                <div>
                    <dt class="text-xs text-text-muted">Machine Number</dt>
                    <dd class="font-signage text-2xl font-semibold text-text">{{ $pmSchedule->machine_number }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-text-muted">Machine Type</dt>
                    <dd class="mt-1 font-medium text-text">{{ $pmSchedule->machine_type }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-text-muted">Order Number</dt>
                    <dd class="mt-1 font-medium text-text">{{ $pmSchedule->order_number }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-text-muted">PIC PM</dt>
                    <dd class="mt-1 font-medium text-text">{{ $pmSchedule->pic ?: '-' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-text-muted">PM Date</dt>
                    <dd class="mt-1 font-medium text-text">{{ $pmDate }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-text-muted">Duration</dt>
                    <dd class="mt-1 font-medium text-text">{{ $pmSchedule->duration_formatted }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-text-muted">Cycle PM</dt>
                    <dd class="mt-1 font-medium text-text">{{ $pmSchedule->machine->pm_cycle_value }} {{ ucfirst($pmSchedule->machine->pm_cycle_unit) }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-text-muted">Next PM</dt>
                    <dd class="mt-1 font-medium text-text">{{ $nextPm ? $nextPm->format('d-m-Y') : '-' }}</dd>
                </div>
                @if ($pmSchedule->isGearboxApplicable())
                    <div>
                        <dt class="text-xs text-text-muted">Gearbox Problem</dt>
                        <dd class="mt-1 font-medium text-text">{{ $pmSchedule->gearbox_problem }}</dd>
                    </div>
                @endif
                <div>
                    <dt class="text-xs text-text-muted">Sparepart Cost</dt>
                    <dd class="mt-1 font-semibold {{ $costTone }}">$ {{ number_format($spareCost, 2, ',', '.') }}</dd>
                </div>
            </dl>
        </section>

        {{-- Offline-first PM Checklist Save: hidden by default; resources/js/pm/checklist.js shows it
             only when a local draft or "saved offline" state exists for this PM. --}}
        <div id="pm-checklist-draft-banner" class="alert alert-info hidden" role="status"></div>

        <form id="pm-checklist-form" action="{{ route('pm-schedules.checklist.save', $pmSchedule) }}" method="POST" class="space-y-5">
            @csrf
            <input type="hidden" name="return_page" value="{{ request('page') }}">

            <div id="pm-checklist-data" data-pm-schedule-id="{{ $pmSchedule->id }}" data-pm-status="{{ $pmSchedule->status }}" data-pm-checklist-baseline="{{ json_encode($checklistBaseline) }}"></div>

            <section aria-labelledby="checklist-heading">
                <div class="mb-3 flex flex-wrap items-baseline justify-between gap-2">
                    <h2 id="checklist-heading" class="font-signage text-xl font-semibold text-text">Checklist</h2>
                    <p class="text-sm text-text-muted">Tick each action done. Items marked <x-ui.badge tone="danger">REP</x-ui.badge> need a replacement.</p>
                </div>

                {{-- md and up: a table whose header stays in view while the page scrolls (overflow-clip, not
                     auto, so the sticky header follows the page scroll instead of an inner one). --}}
                <div class="md:relative md:overflow-clip md:rounded-card md:border md:border-border md:bg-surface md:shadow-card">
                    <table class="block w-full md:table md:border-collapse">
                        <thead class="sticky top-0 z-10 hidden bg-surface-sunken md:table-header-group">
                            <tr>
                                <th scope="col" class="border-b border-border px-4 py-3 text-left text-sm font-semibold text-text-muted">Checklist Item</th>
                                <th scope="col" class="w-24 border-b border-border px-2 py-3 text-center text-sm font-semibold text-text-muted">Clean</th>
                                <th scope="col" class="w-24 border-b border-border px-2 py-3 text-center text-sm font-semibold text-text-muted">Check</th>
                                <th scope="col" class="w-28 border-b border-border px-2 py-3 text-center text-sm font-semibold text-text-muted">Lubrication</th>
                                <th scope="col" class="w-24 border-b border-border px-2 py-3 text-center text-sm font-semibold text-text-muted">Replace</th>
                                <th scope="col" class="w-32 border-b border-border px-2 py-3 text-center text-sm font-semibold text-text-muted">Remarks</th>
                            </tr>
                        </thead>

                        <tbody class="block space-y-3 md:table-row-group md:space-y-0">
                            @php($currentSection = '')

                            @foreach ($checklists as $i => $item)
                                @php($oldChecklist = $pmChecklists[$item->id] ?? null)
                                @php($isReplace = $item->maintenance_type == 'replace')

                                @if ($currentSection != $item->section)
                                    @php($currentSection = $item->section)
                                    <tr class="block md:table-row">
                                        <th scope="colgroup" colspan="6"
                                            class="block pt-3 text-left text-sm font-semibold text-text md:table-cell md:border-b md:border-border md:bg-surface-muted md:px-4 md:py-2">
                                            {{ $item->section }}
                                        </th>
                                    </tr>
                                @endif

                                <tr class="block rounded-card border border-border bg-surface p-3 md:table-row md:rounded-none md:border-0 md:bg-transparent md:p-0 md:hover:bg-surface-sunken">
                                    {{-- Item --}}
                                    <td class="block pb-2 md:table-cell md:border-b md:border-border md:px-4 md:py-2.5">
                                        <div class="flex items-start gap-2">
                                            <span class="font-medium text-text">{{ $item->checklist_item }}</span>
                                            @if ($isReplace)
                                                <x-ui.badge tone="danger">REP</x-ui.badge>
                                            @endif
                                        </div>
                                        <input type="hidden" name="checklists[{{ $i }}][machine_checklist_id]" value="{{ $item->id }}">
                                    </td>

                                    {{-- Clean / Check / Lubrication: only where the item calls for it --}}
                                    @foreach ($actions as $field => $action)
                                        @php($applies = ($action['applies'])($item))
                                        <td @class([
                                            'border-t border-border md:table-cell md:border-t-0 md:border-b md:px-2 md:py-1 md:text-center',
                                            'block' => $applies,
                                            'hidden' => ! $applies,
                                        ])>
                                            @if ($applies)
                                                <label class="flex min-h-11 cursor-pointer items-center justify-between gap-3 rounded-control md:justify-center md:hover:bg-surface-muted">
                                                    <span class="text-sm text-text md:sr-only">{{ $action['label'] }}<span class="sr-only">: {{ $item->checklist_item }}</span></span>
                                                    <input type="hidden" name="checklists[{{ $i }}][{{ $field }}]" value="NO">
                                                    <input type="checkbox" name="checklists[{{ $i }}][{{ $field }}]" value="YES"
                                                        {{ $oldChecklist?->{$field} == 'YES' ? 'checked' : '' }}
                                                        class="h-6 w-6 shrink-0 accent-success">
                                                </label>
                                            @endif
                                        </td>
                                    @endforeach

                                    {{-- Replace: always available; emphasised on items that require it --}}
                                    <td class="block border-t border-border md:table-cell md:border-t-0 md:border-b md:px-2 md:py-1 md:text-center">
                                        <label @class([
                                            'flex min-h-11 cursor-pointer items-center justify-between gap-3 rounded-control md:justify-center',
                                            'bg-danger-light px-2 md:bg-transparent md:px-0' => $isReplace,
                                            'md:hover:bg-surface-muted' => ! $isReplace,
                                        ])>
                                            <span @class(['text-sm md:sr-only', 'font-semibold text-danger-hover' => $isReplace, 'text-text-muted' => ! $isReplace])>
                                                Replace{{ $isReplace ? ' (required)' : '' }}<span class="sr-only">: {{ $item->checklist_item }}</span>
                                            </span>
                                            <input type="hidden" name="checklists[{{ $i }}][replace]" value="NO">
                                            <input type="checkbox" name="checklists[{{ $i }}][replace]" value="YES"
                                                {{ $oldChecklist?->replace == 'YES' ? 'checked' : '' }}
                                                @class(['h-6 w-6 shrink-0', 'accent-danger outline-2 outline-offset-2 outline-danger' => $isReplace, 'accent-success' => ! $isReplace])>
                                        </label>
                                    </td>

                                    {{-- Remarks --}}
                                    <td class="block border-t border-border pt-1 md:table-cell md:border-t-0 md:border-b md:px-2 md:py-1 md:text-center">
                                        {{-- text-gray-500 / text-green-600 are toggled by resources/js/checklist/index.js and pm/checklist.js. --}}
                                        <button type="button"
                                            class="remark-btn inline-flex min-h-11 w-full items-center justify-center rounded-control px-2 text-sm font-medium hover:bg-surface-muted md:w-auto {{ $oldChecklist?->remarks ? 'text-green-600' : 'text-gray-500' }}"
                                            data-index="{{ $i }}" data-item="{{ $item->checklist_item }}"
                                            aria-label="Remarks for {{ $item->checklist_item }}">{{ $oldChecklist?->remarks ? 'Edit note' : 'Add note' }}</button>
                                        <input type="hidden" id="remark-{{ $i }}" name="checklists[{{ $i }}][remarks]"
                                            value="{{ $oldChecklist?->remarks ?? '' }}">
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>

            <div class="sticky bottom-0 z-10 -mx-4 border-t border-border bg-surface/95 px-4 py-3 sm:-mx-6 sm:px-6 lg:-mx-8 lg:px-8">
                <div class="flex flex-col-reverse gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <x-ui.button variant="ghost" href="{{ route('pm-schedules.edit', $pmSchedule->id) }}" class="hidden sm:inline-flex">Back to Fill PM</x-ui.button>
                    <x-ui.button type="submit" size="lg" class="w-full sm:w-auto">Save Checklist</x-ui.button>
                </div>
            </div>
        </form>
    </div>

    {{-- Remarks dialog: opened, filled and saved by resources/js/checklist/index.js. --}}
    <x-ui.modal id="remarkModal" title="Checklist Remarks" size="lg">
        <div class="mt-4">
            <p class="text-xs text-text-muted">Checklist Item</p>
            <div id="remarkItem" class="mt-1 rounded-control bg-surface-muted px-3 py-2 font-semibold text-text"></div>
        </div>

        <x-ui.field label="Remarks" for="remarkTextarea" class="mt-4">
            <textarea id="remarkTextarea" rows="5" class="textarea" placeholder="Input remarks..."></textarea>
        </x-ui.field>

        <div class="mt-6 flex justify-end gap-2">
            <x-ui.button variant="ghost" id="cancelRemark">Cancel</x-ui.button>
            <x-ui.button id="saveRemark">Save note</x-ui.button>
        </div>
    </x-ui.modal>
@endsection
