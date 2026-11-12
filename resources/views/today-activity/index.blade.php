@extends('layouts.app')

@php
    $fullWidth = true;
    $finishConfirm = "return confirm('Remove this activity from the monitor? The underlying work is NOT completed.');";
@endphp

@section('content')
    <div class="space-y-6">

        <x-ui.page-header title="Today's Activity"
            :description="now()->translatedFormat('l, d F Y') . ($canViewAll ? '' : ' · ' . auth()->user()->name)">
            @if ($canManage)
                <x-slot:actions>
                    <x-ui.button id="manual-activity-open">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M12 5v14" />
                            <path d="M5 12h14" />
                        </svg>
                        Start activity
                    </x-ui.button>
                </x-slot:actions>
            @endif
        </x-ui.page-header>

        @if (session('success'))
            <x-ui.alert tone="success">{{ session('success') }}</x-ui.alert>
        @endif
        @if (session('warning'))
            <x-ui.alert tone="warning">{{ session('warning') }}</x-ui.alert>
        @endif

        {{-- Active now: the screen's main question, who is working on what right now. --}}
        <section aria-labelledby="active-now-heading">
            <div class="mb-3 flex items-baseline gap-2">
                <h2 id="active-now-heading" class="font-signage text-xl font-semibold text-text">Active now</h2>
                <span class="text-sm text-text-muted">{{ $activeRows->count() }} {{ \Illuminate\Support\Str::plural('activity', $activeRows->count()) }}</span>
            </div>

            @if ($activeRows->isEmpty())
                <x-ui.empty-state title="No active activity right now."
                    description="A card appears here as soon as a PIC starts PM, greasing, oil audit, predictive, small PM, or a manual activity." />
            @else
                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach ($activeRows as $row)
                        @php($a = $row['activity'])
                        <article class="card flex min-w-0 flex-col p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="truncate text-sm text-text-muted">{{ $row['pic']->name }}</p>
                                    <h3 class="truncate text-base font-semibold text-text">{{ $a->displayLabel() }}</h3>
                                    <p class="mt-1 truncate text-sm text-text-muted">
                                        {{ $a->label }}@if ($a->locationLabel()) · {{ $a->locationLabel() }}@endif
                                    </p>
                                </div>
                                <x-ui.badge tone="success" class="shrink-0">ACTIVE</x-ui.badge>
                            </div>
                            <p class="mt-2 text-sm text-text-muted">Since <span class="font-semibold text-text">{{ $a->startedAt->format('H:i') }}</span></p>

                            @if ($canManage)
                                <div class="mt-4 border-t border-border pt-3 [&>div]:justify-start">
                                    @include('today-activity.partials.row-actions', ['showFinish' => true])
                                </div>
                            @endif
                        </article>
                    @endforeach
                </div>
            @endif
        </section>

        {{-- PIC availability (ADMIN / KOORDINATOR / SUPERVISOR) --}}
        @if ($canViewAll)
            <x-ui.card title="PIC Availability" description="Who can take work today." :padded="false">
                {{-- Phone: one row per PIC, actions under the name. --}}
                <ul class="divide-y divide-border md:hidden">
                    @foreach ($picStatuses as $s)
                        <li class="space-y-2 px-4 py-3">
                            <div class="flex items-start justify-between gap-3">
                                <span class="min-w-0 font-medium text-text">{{ $s['pic']->name }}</span>
                                @include('today-activity.partials.status-badge', ['status' => $s['status']])
                            </div>
                            @if ($s['availability'])
                                <p class="text-sm text-text-muted">{{ $s['availability']->reason }}@if ($s['availability']->notes): {{ $s['availability']->notes }}@endif</p>
                            @endif
                            @if ($canManage && in_array($s['status'], ['NOT STARTED', 'INACTIVE'], true))
                                <div class="[&>div]:justify-start">@include('today-activity.partials.pic-actions')</div>
                            @endif
                        </li>
                    @endforeach
                </ul>

                <div class="relative hidden overflow-x-auto md:block">
                    <table class="data-table min-w-full">
                        <thead>
                            <tr>
                                <th scope="col">PIC</th>
                                <th scope="col">Status</th>
                                <th scope="col">Reason</th>
                                @if ($canManage)
                                    <th scope="col"><span class="sr-only">Actions</span></th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($picStatuses as $s)
                                <tr>
                                    <td class="font-medium">{{ $s['pic']->name }}</td>
                                    <td>@include('today-activity.partials.status-badge', ['status' => $s['status']])</td>
                                    <td class="text-text-muted">
                                        @if ($s['availability'])
                                            {{ $s['availability']->reason }}@if ($s['availability']->notes): {{ $s['availability']->notes }}@endif
                                        @else
                                            <span class="text-text-disabled">None</span>
                                        @endif
                                    </td>
                                    @if ($canManage)
                                        <td class="whitespace-nowrap">@include('today-activity.partials.pic-actions')</td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-ui.card>
        @endif

        {{-- Started today: the day's log, newest work and finished work together. --}}
        <x-ui.card title="Started Today" :padded="false">
            @if ($rows->isEmpty())
                <div class="p-4 sm:p-5">
                    <x-ui.empty-state title="No activity started today."
                        description="Every PM, greasing, oil audit, predictive, small PM, and manual activity started today is listed here." />
                </div>
            @else
                {{-- Inline @php on purpose: a block @php after the inline ones above trips Blade's parser. --}}
                @php($rowStatus = fn ($row) => $row['isActive'] ? 'ACTIVE' : ($row['activity']->isFinished() ? 'FINISHED' : 'CLOSED'))

                {{-- Phone: a time-ordered list, each entry readable without sideways scrolling. --}}
                <ol class="divide-y divide-border md:hidden">
                    @foreach ($rows as $row)
                        @php($a = $row['activity'])
                        <li class="space-y-1.5 px-4 py-3">
                            <div class="flex items-start justify-between gap-3">
                                <p class="min-w-0 text-sm text-text-muted">
                                    <span class="font-semibold text-text tabular-nums">{{ $a->startedAt->format('H:i') }}</span>
                                    · {{ $row['pic']->name }}
                                </p>
                                @include('today-activity.partials.status-badge', ['status' => $rowStatus($row)])
                            </div>
                            <p class="font-medium text-text">{{ $a->displayLabel() }}</p>
                            <p class="text-sm text-text-muted">{{ $a->label }}@if ($a->locationLabel()) · {{ $a->locationLabel() }}@endif</p>
                            @if ($canManage)
                                <div class="pt-1 [&>div]:justify-start">
                                    @include('today-activity.partials.row-actions', ['showFinish' => ! $a->isFinished()])
                                </div>
                            @endif
                        </li>
                    @endforeach
                </ol>

                <div class="relative hidden overflow-x-auto md:block">
                    <table class="data-table min-w-full">
                        <thead>
                            <tr>
                                <th scope="col">Start</th>
                                <th scope="col">PIC</th>
                                <th scope="col">Activity</th>
                                <th scope="col">Source</th>
                                <th scope="col">Machine / Location</th>
                                <th scope="col">Status</th>
                                @if ($canManage)
                                    <th scope="col"><span class="sr-only">Actions</span></th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $row)
                                @php($a = $row['activity'])
                                <tr>
                                    <td class="font-semibold tabular-nums">{{ $a->startedAt->format('H:i') }}</td>
                                    <td class="whitespace-nowrap">{{ $row['pic']->name }}</td>
                                    <td>{{ $a->displayLabel() }}</td>
                                    <td class="text-text-muted">{{ $a->label }}</td>
                                    <td>{{ $a->locationLabel() ?: '-' }}</td>
                                    <td>@include('today-activity.partials.status-badge', ['status' => $rowStatus($row)])</td>
                                    @if ($canManage)
                                        <td class="whitespace-nowrap">
                                            @include('today-activity.partials.row-actions', ['showFinish' => ! $a->isFinished()])
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-ui.card>
    </div>

    @if ($canManage)
        <x-ui.modal id="manual-activity-modal" title="Start manual activity"
            description="For work outside PM, greasing, oil audit, and predictive. It shows on the monitor until finished.">
            <form method="POST" action="{{ route('today-activity.manual.store') }}" class="mt-5 space-y-4">
                @csrf
                <x-ui.field label="User / PIC" for="manual-activity-user" error="user_id" required>
                    <select name="user_id" id="manual-activity-user" required class="select">
                        <option value="">Select PIC</option>
                        @foreach ($assignablePics as $pic)
                            <option value="{{ $pic->id }}" @selected((string) old('user_id') === (string) $pic->id)>
                                {{ $pic->name }} ({{ $pic->role }})
                            </option>
                        @endforeach
                    </select>
                </x-ui.field>
                <x-ui.field label="Activity name" for="manual-activity-name" error="name" required>
                    <input type="text" name="name" id="manual-activity-name" value="{{ old('name') }}" required maxlength="255"
                        placeholder="e.g. Repair Conveyor" class="input">
                </x-ui.field>
                <x-ui.field label="Machine / Location" for="manual-activity-machine" error="machine_number" hint="Optional.">
                    <input type="text" name="machine_number" id="manual-activity-machine" value="{{ old('machine_number') }}" maxlength="255"
                        placeholder="e.g. M-1023 or Workshop" class="input">
                </x-ui.field>
                <x-ui.field label="Start date & time" for="manual-activity-started-at" error="started_at" required>
                    <input type="datetime-local" name="started_at" id="manual-activity-started-at" required class="input">
                </x-ui.field>
                <div class="flex justify-end gap-2 pt-2">
                    <x-ui.button variant="ghost" id="manual-activity-cancel">Cancel</x-ui.button>
                    <x-ui.button type="submit">Start activity</x-ui.button>
                </div>
            </form>
        </x-ui.modal>

        <x-ui.modal id="manual-edit-modal" title="Edit manual activity">
            <form id="manual-edit-form" method="POST" class="mt-5 space-y-4">
                @csrf
                @method('PATCH')
                <x-ui.field label="Activity name" for="manual-edit-name" required>
                    <input type="text" name="name" id="manual-edit-name" required maxlength="255" class="input">
                </x-ui.field>
                <x-ui.field label="Machine / Location" for="manual-edit-machine" hint="Optional.">
                    <input type="text" name="machine_number" id="manual-edit-machine" maxlength="255" class="input">
                </x-ui.field>
                <x-ui.field label="Start date & time" for="manual-edit-started" required>
                    <input type="datetime-local" name="started_at" id="manual-edit-started" required class="input">
                </x-ui.field>
                <div class="flex justify-end gap-2 pt-2">
                    <x-ui.button variant="ghost" id="manual-edit-cancel">Cancel</x-ui.button>
                    <x-ui.button type="submit">Save changes</x-ui.button>
                </div>
            </form>
        </x-ui.modal>

        <x-ui.modal id="pic-inactive-modal" title="Set PIC inactive">
            <p id="pic-inactive-name" class="mt-1 text-sm font-medium text-text-muted"></p>

            <form method="POST" action="{{ route('today-activity.inactive.set') }}" class="mt-5 space-y-4">
                @csrf
                <input type="hidden" name="user_id" id="pic-inactive-user-id">
                <x-ui.field label="Reason" for="pic-inactive-reason" error="reason" required>
                    <select name="reason" id="pic-inactive-reason" required class="select">
                        @foreach ($inactiveReasons as $reason)
                            <option value="{{ $reason }}">{{ $reason }}</option>
                        @endforeach
                    </select>
                </x-ui.field>
                <div id="pic-inactive-notes-wrap" class="grid gap-1.5">
                    <label for="pic-inactive-notes" class="form-label mb-0">
                        Notes <span id="pic-inactive-notes-opt" class="font-normal text-text-muted">(optional)</span>
                    </label>
                    <input type="text" name="notes" id="pic-inactive-notes" maxlength="255"
                        placeholder="e.g. detail keterangan" class="input">
                    @error('notes')<p class="form-error">{{ $message }}</p>@enderror
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <x-ui.button variant="ghost" id="pic-inactive-cancel">Cancel</x-ui.button>
                    <x-ui.button type="submit">Set inactive</x-ui.button>
                </div>
            </form>
        </x-ui.modal>

        <script>
            (function () {
                function nowLocal () {
                    const d = new Date();
                    d.setMinutes(d.getMinutes() - d.getTimezoneOffset());
                    return d.toISOString().slice(0, 16);
                }
                function show (el) { el.classList.remove('hidden'); el.classList.add('flex'); }
                function hide (el) { el.classList.add('hidden'); el.classList.remove('flex'); }

                // --- Start modal ---
                const startModal = document.getElementById('manual-activity-modal');
                const startInput = document.getElementById('manual-activity-started-at');
                document.getElementById('manual-activity-open').addEventListener('click', function () {
                    if (! startInput.value) startInput.value = nowLocal();
                    show(startModal);
                });
                document.getElementById('manual-activity-cancel').addEventListener('click', function () { hide(startModal); });
                startModal.addEventListener('click', function (e) { if (e.target === startModal) hide(startModal); });

                // --- Edit modal ---
                const editModal = document.getElementById('manual-edit-modal');
                const editForm = document.getElementById('manual-edit-form');
                const editBase = "{{ url('today-activity/manual') }}";
                document.querySelectorAll('.manual-edit-btn').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        editForm.action = editBase + '/' + this.dataset.id;
                        document.getElementById('manual-edit-name').value = this.dataset.name || '';
                        document.getElementById('manual-edit-machine').value = this.dataset.machine || '';
                        document.getElementById('manual-edit-started').value = this.dataset.started || nowLocal();
                        show(editModal);
                    });
                });
                document.getElementById('manual-edit-cancel').addEventListener('click', function () { hide(editModal); });
                editModal.addEventListener('click', function (e) { if (e.target === editModal) hide(editModal); });

                // --- Set inactive modal ---
                const inactiveModal = document.getElementById('pic-inactive-modal');
                const reasonSel = document.getElementById('pic-inactive-reason');
                const notesInput = document.getElementById('pic-inactive-notes');
                const notesOpt = document.getElementById('pic-inactive-notes-opt');
                function syncNotes () {
                    const other = reasonSel.value === 'Other';
                    notesInput.required = other;
                    notesOpt.textContent = other ? '(required)' : '(optional)';
                }
                reasonSel.addEventListener('change', syncNotes);
                document.querySelectorAll('.pic-inactive-btn').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        document.getElementById('pic-inactive-user-id').value = this.dataset.id;
                        document.getElementById('pic-inactive-name').textContent = this.dataset.name || '';
                        reasonSel.value = this.dataset.reason || 'Cuti';
                        notesInput.value = this.dataset.notes || '';
                        syncNotes();
                        show(inactiveModal);
                    });
                });
                document.getElementById('pic-inactive-cancel').addEventListener('click', function () { hide(inactiveModal); });
                inactiveModal.addEventListener('click', function (e) { if (e.target === inactiveModal) hide(inactiveModal); });

                @if ($errors->any())
                    if (! startInput.value) startInput.value = nowLocal();
                    show(startModal);
                @endif
            })();
        </script>
    @endif

    @include('partials.activity-conflict-modal')
@endsection
