@extends('layouts.app')

@php
    $contentMaxWidth = 'max-w-full';
    $fullWidth = true;
@endphp
@section('title', 'Action Predictive')

@section('content')
    @php
        $isDashboard = $tab === 'dashboard';
        $reopenedAction = null;
        if (old('_action_wo')) {
            $reopenedWo = \App\Models\PredictiveWorkOrder::find((int) old('_action_wo'));
            if ($reopenedWo && $reopenedWo->canBeActionedBy(auth()->user())) {
                $reopenedAction = [
                    'id' => $reopenedWo->id, 'order' => $reopenedWo->order_number, 'machine' => $reopenedWo->machine_number,
                    'url' => route('predictive.action', $reopenedWo), 'next' => $reopenedWo->actions()->count() + 1,
                ];
            }
        }
        $reopenedEdit = null;
        if (old('_edit_wo')) {
            $editWo = \App\Models\PredictiveWorkOrder::withCount(['actions', 'reopens'])->find((int) old('_edit_wo'));
            if ($editWo && $editWo->isManageableBy(auth()->user())) {
                $reopenedEdit = [
                    'id' => $editWo->id, 'url' => route('predictive.update', $editWo), 'order' => $editWo->order_number,
                    'order_input' => old('order_number', $editWo->order_number), 'machine_number' => old('machine_number', $editWo->machine_number),
                    'wo_date' => old('wo_date', $editWo->wo_date->format('Y-m-d')), 'due_date' => old('due_date', $editWo->due_date->format('Y-m-d')),
                    'problem' => old('problem_description', $editWo->problem_description),
                    'locked' => $editWo->pic_user_id !== null || $editWo->actions_count + $editWo->reopens_count > 0,
                ];
            }
        }
    @endphp

    <div x-data="{ promptOpen: @json($promptStart), startOpen: false, startAt: '', openStart() { const d = new Date(); d.setMinutes(d.getMinutes() - d.getTimezoneOffset()); this.startAt = d.toISOString().slice(0, 16); this.promptOpen = false; this.startOpen = true; }, addOpen: {{ old('_form') === 'add' ? 'true' : 'false' }}, importOpen: {{ $errors->has('file') ? 'true' : 'false' }}, action: @js($reopenedAction), edit: @js($reopenedEdit) }">
        <div class="space-y-5">
        <x-ui.page-header title="Action Predictive" description="Work order hasil pemeriksaan Vibration dan Air Leaked: claim, closing, dan reopen.">
            @if ($canManage && ! $isDashboard)
                <x-slot:actions>
                    <x-ui.button variant="outline" class="flex-1 sm:flex-none" x-on:click="importOpen = true">Import WO</x-ui.button>
                    <x-ui.button class="flex-1 sm:flex-none" x-on:click="addOpen = true">Add WO</x-ui.button>
                </x-slot:actions>
            @endif
        </x-ui.page-header>

        {{-- On a phone the tabs scroll sideways; bring the current one into view so it is never off-screen. --}}
        <nav class="-mt-1 flex overflow-x-auto border-b border-border" aria-label="Tab Action Predictive"
            x-init="$el.querySelector('[aria-current]')?.scrollIntoView({ inline: 'center', block: 'nearest' })">
            @foreach ($tabs as $key => $t)
                @php($active = $tab === $key)
                <a href="{{ route('predictive.index', ['tab' => $key]) }}" @if ($active) aria-current="page" @endif
                    @class([
                        '-mb-px inline-flex min-h-11 flex-1 items-center justify-center gap-2 whitespace-nowrap border-b-2 px-3 text-sm font-semibold transition-colors sm:flex-none',
                        'border-primary text-primary-hover' => $active,
                        'border-transparent text-text-muted hover:border-border-strong hover:text-text' => ! $active,
                    ])>
                    @if ($key === 'dashboard')
                        Dashboard
                    @else
                        <span><span class="hidden sm:inline">Action Predictive </span>{{ \Illuminate\Support\Str::after($t['label'], 'Action Predictive ') }}</span>
                    @endif
                    @if ($t['open'] !== null)
                        <x-ui.badge tone="warning" class="hidden sm:inline-flex" title="{{ $t['open'] }} WO open">{{ $t['open'] }} open</x-ui.badge>
                    @endif
                </a>
            @endforeach
        </nav>

        @foreach (['success' => 'success', 'error' => 'danger', 'warning' => 'warning'] as $flash => $tone)
            @if (session($flash))
                <x-ui.alert :tone="$tone">{{ session($flash) }}</x-ui.alert>
            @endif
        @endforeach

        @if (auth()->user()->isPic() && ! $canWork)
            <div class="alert alert-warning flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between" role="status">
                <p>Aktivitas Action Predictive belum dimulai. Claim dan Action aktif setelah Anda memulainya.</p>
                <x-ui.button class="shrink-0" x-on:click="openStart()">Start Predictive</x-ui.button>
            </div>
        @endif

        @if (session('predictive_import_result'))
            @include('predictive.partials.import-result')
        @endif

        @if ($isDashboard)
            @include('predictive.partials.dashboard')
        @else
            @php($hasFilters = request()->hasAny(['search', 'status', 'claim', 'area', 'date_from', 'date_to']))
            {{-- Filters: plain GET, every field labelled. --}}
            <form method="GET" action="{{ route('predictive.index') }}" class="card flex flex-col gap-3 p-4 sm:flex-row sm:flex-wrap sm:items-end">
                <input type="hidden" name="tab" value="{{ $tab }}">
                <div class="grid gap-1.5 sm:w-72">
                    <label for="pv-search" class="text-xs font-medium text-text-muted">Cari</label>
                    <input id="pv-search" name="search" value="{{ request('search') }}" placeholder="Order, mesin, tipe, atau problem" class="input lg:min-h-9">
                </div>
                <div class="grid gap-1.5 sm:w-40">
                    <label for="pv-status" class="text-xs font-medium text-text-muted">Status</label>
                    <select id="pv-status" name="status" class="select lg:min-h-9">
                        <option value="">Semua status</option>
                        <option value="OPEN" @selected(request('status') === 'OPEN')>OPEN</option>
                        <option value="CLOSED" @selected(request('status') === 'CLOSED')>CLOSED</option>
                    </select>
                </div>
                <div class="grid gap-1.5 sm:w-44">
                    <label for="pv-claim" class="text-xs font-medium text-text-muted">Claim</label>
                    <select id="pv-claim" name="claim" class="select lg:min-h-9">
                        <option value="">Semua claim</option>
                        <option value="unclaimed" @selected(request('claim') === 'unclaimed')>Belum di-claim</option>
                        @if (auth()->user()->isPic())
                            <option value="mine" @selected(request('claim') === 'mine')>Claim saya</option>
                        @endif
                    </select>
                </div>
                @if ($areaOptions->isNotEmpty())
                    <div class="grid gap-1.5 sm:w-36">
                        <label for="pv-area" class="text-xs font-medium text-text-muted">Area</label>
                        <select id="pv-area" name="area" class="select lg:min-h-9">
                            <option value="">Semua area</option>
                            @foreach ($areaOptions as $a)
                                <option value="{{ $a }}" @selected(request('area') === $a)>{{ $a }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <div class="grid gap-3 sm:flex">
                    <div class="grid min-w-0 gap-1.5 sm:w-40">
                        <label for="pv-from" class="text-xs font-medium text-text-muted">Tanggal WO dari</label>
                        <input id="pv-from" type="date" name="date_from" value="{{ request('date_from') }}" class="input lg:min-h-9">
                    </div>
                    <div class="grid min-w-0 gap-1.5 sm:w-40">
                        <label for="pv-to" class="text-xs font-medium text-text-muted">Sampai</label>
                        <input id="pv-to" type="date" name="date_to" value="{{ request('date_to') }}" class="input lg:min-h-9">
                    </div>
                </div>
                <div class="flex gap-2 sm:ml-auto">
                    <x-ui.button type="submit" class="flex-1 sm:flex-none lg:min-h-9">Filter</x-ui.button>
                    <x-ui.button variant="ghost" href="{{ route('predictive.index', ['tab' => $tab]) }}" class="lg:min-h-9">Reset</x-ui.button>
                </div>
            </form>

            @if ($workOrders->isEmpty())
                @if ($hasFilters)
                    <x-ui.empty-state title="Tidak ada WO {{ $categoryLabel }} yang sesuai filter." description="Longgarkan filter, atau reset untuk melihat semua WO.">
                        <x-ui.button variant="outline" href="{{ route('predictive.index', ['tab' => $tab]) }}">Reset filter</x-ui.button>
                    </x-ui.empty-state>
                @else
                    <x-ui.empty-state title="Belum ada WO {{ $categoryLabel }}."
                        :description="$canManage ? 'Gunakan Add WO atau Import WO untuk menambahkan.' : 'WO dari hasil pemeriksaan di area Anda akan muncul di sini.'" />
                @endif
            @else
                {{-- Desktop table. Keep this list first: infinite scroll matches lists by key and position. --}}
                <div class="card hidden overflow-hidden md:block">
                    <div class="relative overflow-x-auto">
                        <table class="data-table min-w-full">
                            <thead>
                                <tr>
                                    @foreach (['Order Number', 'Status / PIC', 'Area', 'Machine', 'Date / Due Date', 'Problem Description'] as $h)
                                        <th scope="col" class="whitespace-nowrap">{{ $h }}</th>
                                    @endforeach
                                    {{-- Actions stay pinned on the right when the table scrolls sideways. --}}
                                    <th scope="col" class="sticky right-0 bg-surface-sunken"><span class="sr-only">Action</span></th>
                                </tr>
                            </thead>
                            <tbody data-infinite-list="wos">
                                @foreach ($workOrders as $wo)
                                    @include('predictive.partials.wo-row', ['wo' => $wo])
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Phone cards (below md). Second list with the same key: the loader appends to both, only one is visible. --}}
                <div class="space-y-3 md:hidden" data-infinite-list="wos">
                    @foreach ($workOrders as $wo)
                        @include('predictive.partials.wo-card', ['wo' => $wo])
                    @endforeach
                </div>

                <x-infinite-scroll :paginator="$workOrders" key="wos" />
            @endif

        @endif
        </div>

        {{-- Dialogs sit outside the space-y wrapper: its margin would shorten a fixed inset-0 overlay. --}}
        @if ($canManage && ! $isDashboard)
            @include('predictive.partials.machine-picker-script')
            @include('predictive.partials.add-modal')
            @include('predictive.partials.import-modal')
            @include('predictive.partials.edit-modal')
        @endif

        @include('predictive.partials.action-modal')

        @if (auth()->user()->isPic())
            {{-- Daily Start prompt, same flow as Oil Audit Action: NO only closes it (the banner above stays), YES asks for the start time. --}}
            @if ($promptStart)
            <div x-show="promptOpen" x-cloak x-transition.opacity.duration.150ms @keydown.escape.window="promptOpen = false"
                class="fixed inset-0 z-50 flex items-center justify-center bg-neutral-900/40 p-4" role="dialog" aria-modal="true" aria-labelledby="predictive-prompt-title">
                <div class="w-full max-w-sm rounded-card border border-border bg-surface p-6 text-center shadow-popover">
                    <h3 id="predictive-prompt-title" class="text-lg font-semibold text-text">APAKAH ANDA INGIN MEMULAI ACTION PREDICTIVE?</h3>
                    <p class="mt-2 text-sm text-text-muted">Aktivitas ini tercatat di Today's Activity. Tanpa memulainya, Anda belum bisa Claim maupun mengisi Action.</p>
                    <div class="mt-5 flex justify-center gap-3">
                        <button type="button" @click="promptOpen = false" class="btn btn-outline min-h-11 px-6">NO</button>
                        <button type="button" @click="openStart()" class="btn btn-primary min-h-11 px-6">YES</button>
                    </div>
                </div>
            </div>
            @endif

            <div x-show="startOpen" x-cloak x-transition.opacity.duration.150ms @keydown.escape.window="startOpen = false"
                class="fixed inset-0 z-50 flex items-center justify-center bg-neutral-900/40 p-4" role="dialog" aria-modal="true" aria-labelledby="predictive-start-title">
                <div @click.outside="startOpen = false" class="w-full max-w-sm rounded-card border border-border bg-surface p-6 shadow-popover">
                    <h3 id="predictive-start-title" class="text-lg font-semibold text-text">Start Action Predictive Activity</h3>
                    <form method="POST" action="{{ route('predictive.start') }}" class="mt-4 space-y-4" x-data="{ busy: false }" @submit="busy = true">
                        @csrf
                        <div>
                            <label for="predictive-start-at" class="form-label">Start Date &amp; Time</label>
                            <input id="predictive-start-at" type="datetime-local" name="started_at" x-model="startAt" required
                                class="input">
                        </div>
                        <div class="flex justify-end gap-2">
                            <button type="button" @click="startOpen = false" class="btn btn-ghost min-h-11">Cancel</button>
                            <button type="submit" x-bind:disabled="busy" class="btn btn-primary min-h-11">START</button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    </div>

    @include('partials.activity-conflict-modal')
@endsection
