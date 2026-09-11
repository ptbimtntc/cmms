@extends('layouts.app')

@section('title', "Today&#39;s Activity")

@section('content')
    <div class="space-y-6">

        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold text-text">Today's Activity</h1>
                <p class="mt-1 text-sm text-text-muted">
                    {{ now()->translatedFormat('l, d F Y') }}
                    @unless ($canManage)
                        · {{ auth()->user()->name }}
                    @endunless
                </p>
            </div>

            @if ($canManage)
                <button type="button" id="manual-activity-open"
                    class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-700">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 5v14" />
                        <path d="M5 12h14" />
                    </svg>
                    ACTIVITY
                </button>
            @endif
        </div>

        @if (session('success'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                {{ session('success') }}
            </div>
        @endif
        @if (session('warning'))
            <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700">
                {{ session('warning') }}
            </div>
        @endif

        {{-- ------------------------------------------------------------------ --}}
        {{-- ACTIVE NOW                                                          --}}
        {{-- ------------------------------------------------------------------ --}}
        <div class="rounded-2xl border border-border bg-surface p-5 shadow-sm">
            <div class="mb-3 text-xs font-semibold uppercase tracking-[0.18em] text-text-muted">
                Active Now ({{ $activeRows->count() }})
            </div>

            @if ($activeRows->isEmpty())
                <p class="text-sm text-text-muted">No active activity right now.</p>
            @else
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($activeRows as $row)
                        @php($a = $row['activity'])
                        <div class="flex flex-col rounded-xl border border-border bg-surface-muted/40 p-4">
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <div class="truncate text-sm font-medium text-text-muted">{{ $row['pic']->name }}</div>
                                    <div class="truncate text-base font-semibold text-text">{{ $a->displayLabel() }}</div>
                                    <div class="mt-1 truncate text-xs text-text-muted">
                                        {{ $a->label }}@if ($a->locationLabel()) · {{ $a->locationLabel() }}@endif
                                    </div>
                                    <div class="text-xs text-text-muted">Since {{ $a->startedAt->format('H:i') }}</div>
                                </div>
                                <span class="shrink-0 rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-700">ACTIVE</span>
                            </div>

                            @if ($canManage)
                                <div class="mt-3 flex flex-wrap gap-2">
                                    @if ($a->source === 'MANUAL')
                                        <button type="button"
                                            class="manual-edit-btn rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-100"
                                            data-id="{{ $a->recordId }}" data-name="{{ $a->title }}"
                                            data-machine="{{ $a->machineNumber }}"
                                            data-started="{{ $a->startedAt->format('Y-m-d\TH:i') }}">Edit</button>
                                    @elseif ($row['moduleLink'])
                                        <a href="{{ $row['moduleLink']['url'] }}"
                                            class="rounded-lg border border-emerald-600 px-3 py-1.5 text-xs font-medium text-emerald-700 hover:bg-emerald-50">{{ $row['moduleLink']['label'] }}</a>
                                    @endif
                                    <form method="POST" action="{{ route('today-activity.finish') }}"
                                        onsubmit="return confirm('Remove this activity from the monitor? The underlying work is NOT completed.');">
                                        @csrf
                                        <input type="hidden" name="source" value="{{ $a->source }}">
                                        <input type="hidden" name="source_key" value="{{ $row['monitorKey'] }}">
                                        <input type="hidden" name="pic_user_id" value="{{ $row['pic']->id }}">
                                        <button type="submit"
                                            class="rounded-lg bg-slate-800 px-3 py-1.5 text-xs font-medium text-white hover:bg-slate-700">Finish</button>
                                    </form>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- ------------------------------------------------------------------ --}}
        {{-- PIC AVAILABILITY (ADMIN / KOORDINATOR)                              --}}
        {{-- ------------------------------------------------------------------ --}}
        @if ($canManage)
            <div class="rounded-2xl border border-border bg-surface p-5 shadow-sm">
                <div class="mb-3 text-xs font-semibold uppercase tracking-[0.18em] text-text-muted">PIC Availability</div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="text-text-muted">
                            <tr>
                                <th class="pb-2 pr-4">PIC</th>
                                <th class="pb-2 pr-4">Status</th>
                                <th class="pb-2 pr-4">Reason</th>
                                <th class="pb-2">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border text-text">
                            @foreach ($picStatuses as $s)
                                <tr>
                                    <td class="py-2 pr-4 font-medium">{{ $s['pic']->name }}</td>
                                    <td class="py-2 pr-4">
                                        @if ($s['status'] === 'ACTIVE')
                                            <span class="rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-700">ACTIVE</span>
                                        @elseif ($s['status'] === 'INACTIVE')
                                            <span class="rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-700">INACTIVE</span>
                                        @else
                                            <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-500">NOT STARTED</span>
                                        @endif
                                    </td>
                                    <td class="py-2 pr-4 text-text-muted">
                                        @if ($s['availability'])
                                            {{ $s['availability']->reason }}@if ($s['availability']->notes) — {{ $s['availability']->notes }}@endif
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="py-2">
                                        <div class="flex flex-wrap gap-2">
                                            @if ($s['status'] === 'NOT STARTED')
                                                <button type="button"
                                                    class="pic-inactive-btn rounded-lg border border-amber-500 px-3 py-1 text-xs font-medium text-amber-700 hover:bg-amber-50"
                                                    data-id="{{ $s['pic']->id }}" data-name="{{ $s['pic']->name }}"
                                                    data-reason="" data-notes="">Set Inactive</button>
                                            @elseif ($s['status'] === 'INACTIVE')
                                                <button type="button"
                                                    class="pic-inactive-btn rounded-lg border border-slate-300 px-3 py-1 text-xs font-medium text-slate-700 hover:bg-slate-100"
                                                    data-id="{{ $s['pic']->id }}" data-name="{{ $s['pic']->name }}"
                                                    data-reason="{{ $s['availability']->reason }}"
                                                    data-notes="{{ $s['availability']->notes }}">Edit</button>
                                                <form method="POST" action="{{ route('today-activity.inactive.clear', $s['availability']->id) }}">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit"
                                                        class="rounded-lg bg-slate-800 px-3 py-1 text-xs font-medium text-white hover:bg-slate-700">Set Available</button>
                                                </form>
                                            @else
                                                <span class="text-xs text-text-disabled">—</span>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        {{-- ------------------------------------------------------------------ --}}
        {{-- STARTED TODAY                                                       --}}
        {{-- ------------------------------------------------------------------ --}}
        <div class="rounded-2xl border border-border bg-surface p-5 shadow-sm">
            <div class="mb-3 text-xs font-semibold uppercase tracking-[0.18em] text-text-muted">Started Today</div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="text-text-muted">
                        <tr>
                            <th class="pb-2 pr-4">PIC</th>
                            <th class="pb-2 pr-4">Activity</th>
                            <th class="pb-2 pr-4">Source</th>
                            <th class="pb-2 pr-4">Machine / Location</th>
                            <th class="pb-2 pr-4">Start</th>
                            <th class="pb-2 pr-4">Status</th>
                            @if ($canManage)
                                <th class="pb-2">Actions</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border text-text">
                        @forelse ($rows as $row)
                            @php($a = $row['activity'])
                            <tr>
                                <td class="py-2 pr-4">{{ $row['pic']->name }}</td>
                                <td class="py-2 pr-4">{{ $a->displayLabel() }}</td>
                                <td class="py-2 pr-4 text-text-muted">{{ $a->label }}</td>
                                <td class="py-2 pr-4">{{ $a->locationLabel() ?: '—' }}</td>
                                <td class="py-2 pr-4 tabular-nums">{{ $a->startedAt->format('H:i') }}</td>
                                <td class="py-2 pr-4">
                                    @if ($row['isActive'])
                                        <span class="rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-700">ACTIVE</span>
                                    @elseif ($a->isFinished())
                                        <span class="rounded-full bg-slate-200 px-2.5 py-0.5 text-xs font-semibold text-slate-600">FINISHED</span>
                                    @else
                                        <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-500">CLOSED</span>
                                    @endif
                                </td>
                                @if ($canManage)
                                    <td class="py-2">
                                        <div class="flex flex-wrap gap-2">
                                            @if ($a->source === 'MANUAL')
                                                <button type="button"
                                                    class="manual-edit-btn rounded-lg border border-slate-300 px-3 py-1 text-xs font-medium text-slate-700 hover:bg-slate-100"
                                                    data-id="{{ $a->recordId }}" data-name="{{ $a->title }}"
                                                    data-machine="{{ $a->machineNumber }}"
                                                    data-started="{{ $a->startedAt->format('Y-m-d\TH:i') }}">Edit</button>
                                            @elseif ($row['moduleLink'])
                                                <a href="{{ $row['moduleLink']['url'] }}"
                                                    class="rounded-lg border border-emerald-600 px-3 py-1 text-xs font-medium text-emerald-700 hover:bg-emerald-50">{{ $row['moduleLink']['label'] }}</a>
                                            @endif
                                            @unless ($a->isFinished())
                                                <form method="POST" action="{{ route('today-activity.finish') }}"
                                                    onsubmit="return confirm('Remove this activity from the monitor? The underlying work is NOT completed.');">
                                                    @csrf
                                                    <input type="hidden" name="source" value="{{ $a->source }}">
                                                    <input type="hidden" name="source_key" value="{{ $row['monitorKey'] }}">
                                                    <input type="hidden" name="pic_user_id" value="{{ $row['pic']->id }}">
                                                    <button type="submit"
                                                        class="rounded-lg bg-slate-800 px-3 py-1 text-xs font-medium text-white hover:bg-slate-700">Finish</button>
                                                </form>
                                            @endunless
                                        </div>
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $canManage ? 7 : 6 }}" class="py-4 text-center text-text-muted">No activity started today.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @if ($canManage)
        {{-- ============ START MANUAL ACTIVITY MODAL ============ --}}
        <div id="manual-activity-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 p-4">
            <div class="w-full max-w-sm rounded-2xl bg-white p-6 shadow-xl">
                <h3 class="text-lg font-semibold text-slate-800">Start Manual Activity</h3>

                <form method="POST" action="{{ route('today-activity.manual.store') }}" class="mt-4 space-y-4">
                    @csrf
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">User / PIC</label>
                        <select name="user_id" required
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 focus:border-blue-500 focus:outline-none">
                            <option value="">— Select PIC —</option>
                            @foreach ($assignablePics as $pic)
                                <option value="{{ $pic->id }}" @selected((string) old('user_id') === (string) $pic->id)>
                                    {{ $pic->name }} ({{ $pic->role }})
                                </option>
                            @endforeach
                        </select>
                        @error('user_id')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Activity Name</label>
                        <input type="text" name="name" value="{{ old('name') }}" required maxlength="255"
                            placeholder="e.g. Repair Conveyor"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 focus:border-blue-500 focus:outline-none">
                        @error('name')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Machine / Location <span class="text-slate-400">(optional)</span></label>
                        <input type="text" name="machine_number" value="{{ old('machine_number') }}" maxlength="255"
                            placeholder="e.g. M-1023 or Workshop"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 focus:border-blue-500 focus:outline-none">
                        @error('machine_number')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Start Date &amp; Time</label>
                        <input type="datetime-local" name="started_at" id="manual-activity-started-at" required
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 focus:border-blue-500 focus:outline-none">
                        @error('started_at')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div class="flex justify-end gap-2">
                        <button type="button" id="manual-activity-cancel"
                            class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100">CANCEL</button>
                        <button type="submit"
                            class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">START</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- ============ EDIT MANUAL ACTIVITY MODAL ============ --}}
        <div id="manual-edit-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 p-4">
            <div class="w-full max-w-sm rounded-2xl bg-white p-6 shadow-xl">
                <h3 class="text-lg font-semibold text-slate-800">Edit Manual Activity</h3>

                <form id="manual-edit-form" method="POST" class="mt-4 space-y-4">
                    @csrf
                    @method('PATCH')
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Activity Name</label>
                        <input type="text" name="name" id="manual-edit-name" required maxlength="255"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 focus:border-blue-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Machine / Location <span class="text-slate-400">(optional)</span></label>
                        <input type="text" name="machine_number" id="manual-edit-machine" maxlength="255"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 focus:border-blue-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Start Date &amp; Time</label>
                        <input type="datetime-local" name="started_at" id="manual-edit-started" required
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 focus:border-blue-500 focus:outline-none">
                    </div>
                    <div class="flex justify-end gap-2">
                        <button type="button" id="manual-edit-cancel"
                            class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100">CANCEL</button>
                        <button type="submit"
                            class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">SAVE</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- ============ SET PIC INACTIVE MODAL ============ --}}
        <div id="pic-inactive-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 p-4">
            <div class="w-full max-w-sm rounded-2xl bg-white p-6 shadow-xl">
                <h3 class="text-lg font-semibold text-slate-800">Set PIC Inactive</h3>
                <p id="pic-inactive-name" class="mt-1 text-sm font-medium text-slate-500"></p>

                <form method="POST" action="{{ route('today-activity.inactive.set') }}" class="mt-4 space-y-4">
                    @csrf
                    <input type="hidden" name="user_id" id="pic-inactive-user-id">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Reason</label>
                        <select name="reason" id="pic-inactive-reason" required
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 focus:border-blue-500 focus:outline-none">
                            @foreach ($inactiveReasons as $reason)
                                <option value="{{ $reason }}">{{ $reason }}</option>
                            @endforeach
                        </select>
                        @error('reason')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div id="pic-inactive-notes-wrap">
                        <label class="mb-1 block text-sm font-medium text-slate-700">
                            Notes <span id="pic-inactive-notes-opt" class="text-slate-400">(optional)</span>
                        </label>
                        <input type="text" name="notes" id="pic-inactive-notes" maxlength="255"
                            placeholder="e.g. detail keterangan"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 focus:border-blue-500 focus:outline-none">
                        @error('notes')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div class="flex justify-end gap-2">
                        <button type="button" id="pic-inactive-cancel"
                            class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100">CANCEL</button>
                        <button type="submit"
                            class="rounded-lg bg-amber-600 px-4 py-2 text-sm font-medium text-white hover:bg-amber-700">SAVE</button>
                    </div>
                </form>
            </div>
        </div>

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
