<div x-data="lastPmOverride({
        storeUrl: @js(route('machine-qr.overrides.store')),
        lookupUrl: @js(route('machine-qr.machines')),
        historyBase: @js(url('machine-qr/overrides')),
        resetBase: @js(url('machine-qr/overrides')),
        csrf: @js(csrf_token()),
        keep: @js(request()->only('search', 'area', 'status')),
        old: @js(['machine_id' => old('machine_id'), 'override_date' => old('override_date'), 'reason' => old('reason')]),
    })" x-init="init()" class="space-y-5">
    <div class="flex justify-end">
        <button type="button" @click="openForm(null)" class="btn btn-primary min-h-11 lg:min-h-9">New Override</button>
    </div>
    @php
        $fmt = fn ($d) => $d ? \Carbon\Carbon::parse($d)->format('d-m-Y') : '-';
        $rowOf = fn ($machine) => [
            'id' => $machine->id, 'machine_number' => $machine->machine_number, 'machine_name' => $machine->machine_type,
            'area' => $machine->area, 'system_last_pm' => $machine->system_last_pm,
            'override_date' => $machine->lastPmOverride?->override_date?->toDateString(), 'reason' => $machine->lastPmOverride?->reason,
        ];
        $btn = 'btn btn-sm min-h-11 lg:min-h-9';
    @endphp

    @if ($machines->isEmpty())
        <x-ui.empty-state title="No machines found." />
    @endif

    {{-- Desktop table. Keep this list first: infinite scroll matches lists by key and position. --}}
    <div @class(['card hidden overflow-hidden', 'md:block' => $machines->isNotEmpty()])>
        <div class="relative overflow-x-auto">
            <table class="data-table min-w-full">
                <thead>
                    <tr>
                        <th scope="col">Machine Number</th>
                        <th scope="col">Machine Name</th>
                        <th scope="col">Area</th>
                        <th scope="col">System Last PM</th>
                        <th scope="col">Override Date</th>
                        <th scope="col">Status</th>
                        <th scope="col" class="sticky right-0 bg-surface-sunken"><span class="sr-only">Action</span></th>
                    </tr>
                </thead>
                <tbody data-infinite-list="machines">
                    @foreach ($machines as $machine)
                        @php($ov = $machine->lastPmOverride)
                        @php($row = $rowOf($machine))
                        <tr>
                            <td class="whitespace-nowrap font-semibold text-text">{{ $machine->machine_number }}</td>
                            <td>{{ $machine->machine_type ?? '-' }}</td>
                            <td>{{ $machine->area }}</td>
                            <td class="whitespace-nowrap">{{ $fmt($machine->system_last_pm) }}</td>
                            <td class="whitespace-nowrap">{{ $ov ? $ov->override_date->format('d-m-Y') : '-' }}</td>
                            <td><x-ui.badge :tone="$ov ? 'warning' : 'neutral'">{{ $ov ? 'Overridden' : 'Normal' }}</x-ui.badge></td>
                            <td class="sticky right-0 bg-surface shadow-[-10px_0_10px_-10px_rgb(15_23_42/0.25)]">
                                <div class="flex flex-nowrap justify-end gap-2">
                                    @if ($machine->system_last_pm)
                                        <button type="button" @click='openForm(@json($row))' class="{{ $btn }} btn-outline">{{ $ov ? 'Edit' : 'Override' }}</button>
                                    @else
                                        <span class="self-center text-xs text-text-muted" title="No System Last PM">No PM yet</span>
                                    @endif
                                    @if ($ov)
                                        <button type="button" @click='askReset(@json($row))' class="{{ $btn }} btn-ghost text-danger hover:bg-danger-light hover:text-danger-hover">Reset</button>
                                    @endif
                                    <button type="button" @click="showHistory({{ $machine->id }}, @js($machine->machine_number))" class="{{ $btn }} btn-ghost">History</button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- Phone cards (below md). Second list with the same key. --}}
    <div class="space-y-3 md:hidden" data-infinite-list="machines">
        @foreach ($machines as $machine)
            @php($ov = $machine->lastPmOverride)
            @php($row = $rowOf($machine))
            <article class="card min-w-0 p-4">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <h2 class="truncate text-base font-semibold text-text">{{ $machine->machine_number }}</h2>
                        <p class="truncate text-sm text-text-muted">{{ $machine->machine_type ?? '-' }} · {{ $machine->area }}</p>
                    </div>
                    <x-ui.badge :tone="$ov ? 'warning' : 'neutral'" class="shrink-0">{{ $ov ? 'Overridden' : 'Normal' }}</x-ui.badge>
                </div>
                <dl class="mt-3 grid grid-cols-2 gap-x-4 border-t border-border pt-3 text-sm">
                    <div><dt class="text-xs text-text-muted">System Last PM</dt><dd class="font-medium text-text">{{ $fmt($machine->system_last_pm) }}</dd></div>
                    <div><dt class="text-xs text-text-muted">Override Date</dt><dd class="font-medium text-text">{{ $ov ? $ov->override_date->format('d-m-Y') : '-' }}</dd></div>
                </dl>
                <div class="mt-3 flex flex-wrap gap-2">
                    @if ($machine->system_last_pm)
                        <button type="button" @click='openForm(@json($row))' class="{{ $btn }} btn-outline flex-1">{{ $ov ? 'Edit' : 'Override' }}</button>
                    @endif
                    @if ($ov)
                        <button type="button" @click='askReset(@json($row))' class="{{ $btn }} btn-ghost flex-1 text-danger hover:bg-danger-light hover:text-danger-hover">Reset</button>
                    @endif
                    <button type="button" @click="showHistory({{ $machine->id }}, @js($machine->machine_number))" class="{{ $btn }} btn-ghost flex-1">History</button>
                </div>
            </article>
        @endforeach
    </div>

    <x-infinite-scroll :paginator="$machines" key="machines" />

    {{-- Override form modal --}}
    <div x-show="form.open" x-cloak @keydown.escape.window="form.open = false" class="fixed inset-0 z-50 flex items-center justify-center bg-neutral-900/40 p-4" role="dialog" aria-modal="true" aria-labelledby="ov-form-title">
        <form method="POST" :action="storeUrl" @submit="confirmSave($event)" class="max-h-[90dvh] w-full max-w-md space-y-4 overflow-y-auto rounded-card border border-border bg-surface p-5 shadow-popover sm:p-6">
            @csrf
            <template x-for="(v, k) in keep" :key="k"><input type="hidden" :name="k" :value="v"></template>
            <input type="hidden" name="machine_id" :value="form.machine?.id ?? ''">
            <h3 id="ov-form-title" class="text-lg font-semibold text-text" x-text="form.editing ? 'Edit Last PM Override' : 'New Last PM Override'"></h3>

            <div class="grid gap-1.5">
                <span class="form-label mb-0" id="ov-machine-label">Machine Number</span>
                <template x-if="form.machine">
                    <div class="flex min-h-11 items-center justify-between gap-2 rounded-control border border-border bg-surface-sunken px-3 text-sm">
                        <span class="min-w-0"><span class="font-semibold text-text" x-text="form.machine.machine_number"></span> <span class="text-text-muted" x-text="'· ' + (form.machine.machine_name ?? '') + ' · ' + form.machine.area"></span></span>
                        <button type="button" x-show="!form.editing" @click="form.machine = null" class="btn btn-ghost btn-sm min-h-9 shrink-0">Change</button>
                    </div>
                </template>
                <div x-show="!form.machine" class="relative">
                    <input type="search" x-model="lookup.q" @input.debounce.300ms="search()" placeholder="Type machine number / name" aria-labelledby="ov-machine-label" class="input">
                    <ul class="mt-1 max-h-40 overflow-y-auto rounded-control border border-border bg-surface py-1 text-sm shadow-popover" x-show="lookup.results.length">
                        <template x-for="m in lookup.results" :key="m.id">
                            <li><button type="button" @click="pick(m)" class="flex min-h-11 w-full items-center justify-between gap-3 px-3 text-left hover:bg-primary-light">
                                <span class="text-text" x-text="m.machine_number + ' · ' + (m.machine_name ?? '')"></span>
                                <span class="text-xs" :class="m.system_last_pm ? 'text-text-muted' : 'text-danger'" x-text="m.system_last_pm ? m.area : 'no PM yet'"></span>
                            </button></li>
                        </template>
                    </ul>
                </div>
            </div>

            <div class="grid gap-1.5">
                <label for="ov-system" class="form-label mb-0">System Last PM</label>
                <input id="ov-system" type="text" readonly :value="form.machine?.system_last_pm ? fmt(form.machine.system_last_pm) : '-'" class="input bg-surface-sunken text-text-muted">
                <p x-show="form.machine && !form.machine.system_last_pm" class="form-error">This machine has no System Last PM yet, so it cannot be overridden.</p>
            </div>
            <div class="grid gap-1.5">
                <label for="ov-date" class="form-label mb-0">Override Date</label>
                <input id="ov-date" type="date" name="override_date" x-model="form.override_date" required class="input">
            </div>
            <div class="grid gap-1.5">
                <label for="ov-reason" class="form-label mb-0">Reason</label>
                <textarea id="ov-reason" name="reason" x-model="form.reason" required maxlength="500" rows="3" class="textarea"></textarea>
            </div>
            <p class="form-hint">Only the Last PM shown in Machine History changes. PM transactions, Next PM and reports are not affected.</p>
            <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <button type="button" @click="form.open = false" class="btn btn-ghost min-h-11">Cancel</button>
                <button :disabled="!form.machine || !form.machine.system_last_pm" class="btn btn-primary min-h-11">Save</button>
            </div>
        </form>
    </div>

    {{-- Reset confirm --}}
    <div x-show="resetting" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-neutral-900/40 p-4" role="dialog" aria-modal="true" aria-labelledby="ov-reset-title">
        <form method="POST" :action="resetBase + '/' + (resetting?.id ?? '')" class="w-full max-w-sm rounded-card border border-border bg-surface p-5 shadow-popover sm:p-6">
            @csrf @method('DELETE')
            <template x-for="(v, k) in keep" :key="k"><input type="hidden" :name="k" :value="v"></template>
            <h3 id="ov-reset-title" class="text-lg font-semibold text-text">Reset override?</h3>
            <p class="mt-2 text-sm text-text-muted">Machine History for <span class="font-semibold text-text" x-text="resetting?.machine_number"></span> will show the System Last PM again. The change is kept in the history log.</p>
            <div class="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <button type="button" @click="resetting = null" class="btn btn-ghost min-h-11">Cancel</button>
                <button class="btn btn-danger min-h-11">Reset</button>
            </div>
        </form>
    </div>

    {{-- History modal --}}
    <div x-show="history.open" x-cloak @keydown.escape.window="history.open = false" class="fixed inset-0 z-50 flex items-center justify-center bg-neutral-900/40 p-4" role="dialog" aria-modal="true" aria-labelledby="ov-history-title">
        <div @click.outside="history.open = false" class="max-h-[90dvh] w-full max-w-2xl overflow-y-auto rounded-card border border-border bg-surface p-5 shadow-popover sm:p-6">
            <div class="mb-3 flex items-center justify-between gap-3">
                <h3 id="ov-history-title" class="text-lg font-semibold text-text">Override History · <span x-text="history.machine"></span></h3>
                <button type="button" @click="history.open = false" class="btn btn-ghost min-h-11 min-w-11 px-0" aria-label="Close">✕</button>
            </div>
            <p x-show="history.loading" class="text-sm text-text-muted">Loading…</p>
            <p x-show="!history.loading && history.logs.length === 0" class="text-sm text-text-muted">No changes recorded.</p>
            <ul class="space-y-2">
                <template x-for="l in history.logs" :key="l.at + l.action">
                    <li class="rounded-control border border-border p-3 text-sm">
                        <div class="flex flex-wrap justify-between gap-2">
                            <span class="font-semibold" :class="l.action === 'RESET' ? 'text-danger' : 'text-primary-hover'" x-text="l.action"></span>
                            <span class="text-xs text-text-muted" x-text="l.at + ' · ' + l.by"></span>
                        </div>
                        <div class="mt-1 tabular-nums text-text" x-text="(l.previous_date ?? '-') + '  →  ' + (l.new_date ?? '-')"></div>
                        <div class="mt-1 text-xs text-text-muted" x-show="l.new_reason" x-text="'Reason: ' + l.new_reason"></div>
                        <div class="mt-1 text-xs text-text-muted" x-show="l.previous_reason" x-text="'Previous reason: ' + l.previous_reason"></div>
                    </li>
                </template>
            </ul>
        </div>
    </div>
</div>
<script>
    function lastPmOverride(cfg) {
        return {
            storeUrl: cfg.storeUrl, resetBase: cfg.resetBase, keep: cfg.keep,
            form: { open: false, editing: false, machine: null, override_date: '', reason: '' },
            lookup: { q: '', results: [] }, resetting: null,
            history: { open: false, loading: false, machine: '', logs: [] },
            init() {
                // re-open the form with the user's input when the server rejected it
                if (cfg.old.machine_id) {
                    this.form = { open: true, editing: false, machine: null, override_date: cfg.old.override_date || '', reason: cfg.old.reason || '' };
                }
            },
            fmt(d) { const [y, m, day] = d.split('-'); return `${day}-${m}-${y}`; },
            openForm(row) {
                this.lookup = { q: '', results: [] };
                this.form = row
                    ? { open: true, editing: !!row.override_date, machine: row, override_date: row.override_date || '', reason: row.reason || '' }
                    : { open: true, editing: false, machine: null, override_date: '', reason: '' };
            },
            async search() {
                if (this.lookup.q.trim().length < 1) { this.lookup.results = []; return; }
                const r = await fetch(cfg.lookupUrl + '?search=' + encodeURIComponent(this.lookup.q), { headers: { Accept: 'application/json' } });
                this.lookup.results = await r.json();
            },
            pick(m) { this.form.machine = m; this.form.editing = false; this.lookup.results = []; },
            confirmSave(e) {
                const m = this.form.machine;
                if (!m || !confirm(`Save Last PM override for ${m.machine_number} → ${this.fmt(this.form.override_date)}?`)) e.preventDefault();
            },
            askReset(row) { this.resetting = row; },
            async showHistory(id, number) {
                this.history = { open: true, loading: true, machine: number, logs: [] };
                try {
                    const r = await fetch(cfg.historyBase + '/' + id + '/history', { headers: { Accept: 'application/json' } });
                    this.history.logs = (await r.json()).logs;
                } catch (e) { this.history.logs = []; }
                this.history.loading = false;
            },
        };
    }
</script>
