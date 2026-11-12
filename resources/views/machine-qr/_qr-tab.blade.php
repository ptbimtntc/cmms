{{--
    Selection lives in Alpine (persisted in sessionStorage) as a set of machine
    ids, independent of the current page / search / area filter, so it is never
    lost when the list is re-filtered. "Select all" asks the server for every
    id matching the current filter (area-scoped), not just the visible page.
--}}
<div x-data="machineQr(@js([
        'idsUrl' => route('machine-qr.ids', request()->only('search', 'area')),
        'countUrl' => route('machine-qr.count'),
        'total' => $machines->total(),
    ]))" x-init="init()" class="space-y-5">
    <div class="card flex flex-col gap-3 p-4 lg:flex-row lg:items-end lg:justify-between">
        <div class="flex flex-wrap items-center gap-2">
            <button type="button" @click="selectAllFiltered()" :disabled="busy" class="btn btn-outline btn-sm min-h-11 lg:min-h-9">Select all filtered ({{ $machines->total() }})</button>
            <button type="button" @click="clear()" class="btn btn-ghost btn-sm min-h-11 lg:min-h-9">Clear</button>
            <span class="text-sm text-text-muted" aria-live="polite"><span class="font-semibold text-text" x-text="selected.length"></span> selected</span>
            <span x-show="busy" class="text-xs text-text-muted">Loading…</span>
        </div>
        <div class="flex flex-col gap-2 sm:flex-row sm:items-end">
            <form method="POST" action="{{ route('machine-qr.export') }}" @submit="confirmSelected($event)">
                @csrf
                <input type="hidden" name="mode" value="selected">
                <template x-for="id in selected" :key="id"><input type="hidden" name="ids[]" :value="id"></template>
                <button :disabled="selected.length === 0" class="btn btn-primary min-h-11 w-full lg:min-h-9">Export Selected to Word</button>
            </form>
            <form method="POST" action="{{ route('machine-qr.export') }}" class="flex gap-2" @submit="confirmArea($event)">
                @csrf
                <input type="hidden" name="mode" value="area">
                <label for="mq-export-area" class="sr-only">Export by area</label>
                <select id="mq-export-area" name="area" x-model="exportArea" @change="loadAreaCount()" required class="select min-w-0 lg:min-h-9">
                    <option value="">Export by Area…</option>
                    @foreach ($areas as $a)
                        <option value="{{ $a }}">{{ $a }}</option>
                    @endforeach
                </select>
                <button :disabled="!exportArea || areaCount === 0" class="btn btn-outline min-h-11 whitespace-nowrap lg:min-h-9">
                    Export Area<span x-show="exportArea && areaCount !== null" x-text="' (' + areaCount + ')'"></span>
                </button>
            </form>
        </div>
    </div>

    @if ($machines->isEmpty())
        <x-ui.empty-state title="No machines found." />
    @endif

    {{-- Desktop table. Keep this list first: infinite scroll matches lists by key and position. --}}
    <div @class(['card hidden overflow-hidden', 'md:block' => $machines->isNotEmpty()])>
        <div class="relative overflow-x-auto">
            <table class="data-table min-w-full">
                <thead>
                    <tr>
                        <th scope="col" class="w-10"><input type="checkbox" :checked="allLoadedSelected" @change="togglePage($event.target.checked)" class="h-4 w-4 rounded border-border-strong text-primary" aria-label="Select all loaded"></th>
                        <th scope="col">Machine Number</th>
                        <th scope="col">Machine Name</th>
                        <th scope="col">Area</th>
                        <th scope="col">QR Code</th>
                        <th scope="col"><span class="sr-only">Action</span></th>
                    </tr>
                </thead>
                <tbody data-infinite-list="machines">
                    @foreach ($machines as $machine)
                        <tr>
                            <td><input type="checkbox" value="{{ $machine->id }}" :checked="has({{ $machine->id }})" @change="toggle({{ $machine->id }}, $event.target.checked)" class="h-4 w-4 rounded border-border-strong text-primary" aria-label="Select {{ $machine->machine_number }}"></td>
                            <td class="whitespace-nowrap font-semibold text-text">{{ $machine->machine_number }}</td>
                            <td>{{ $machine->machine_type ?? '-' }}</td>
                            <td>{{ $machine->area }}</td>
                            <td>
                                <button type="button" @click="preview = {{ $machine->id }}; previewName = @js($machine->machine_number)" class="block rounded-control focus-visible:outline-2 focus-visible:outline-primary" aria-label="Preview QR {{ $machine->machine_number }}">
                                    <img src="{{ route('machine-qr.preview', $machine) }}" loading="lazy" alt="QR {{ $machine->machine_number }}" class="h-16 w-16 rounded-control border border-border bg-white">
                                </button>
                            </td>
                            <td>
                                <div class="flex justify-end gap-2">
                                    <button type="button" @click="preview = {{ $machine->id }}; previewName = @js($machine->machine_number)" class="btn btn-ghost btn-sm min-h-11 lg:min-h-9">Preview</button>
                                    <x-ui.button size="sm" variant="outline" href="{{ route('machine-qr.download', $machine) }}">Download</x-ui.button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- Phone cards (below md). Second list with the same key. Checkboxes here carry no value on purpose: loadedIds() reads the table's. --}}
    <div class="space-y-3 md:hidden" data-infinite-list="machines">
        @foreach ($machines as $machine)
            <article class="card flex min-w-0 items-center gap-3 p-3">
                <label class="flex min-h-11 min-w-11 items-center justify-center">
                    <input type="checkbox" :checked="has({{ $machine->id }})" @change="toggle({{ $machine->id }}, $event.target.checked)" class="h-5 w-5 rounded border-border-strong text-primary" aria-label="Select {{ $machine->machine_number }}">
                </label>
                <button type="button" @click="preview = {{ $machine->id }}; previewName = @js($machine->machine_number)" class="shrink-0" aria-label="Preview QR {{ $machine->machine_number }}">
                    <img src="{{ route('machine-qr.preview', $machine) }}" loading="lazy" alt="QR {{ $machine->machine_number }}" class="h-16 w-16 rounded-control border border-border bg-white">
                </button>
                <div class="min-w-0 flex-1">
                    <h2 class="truncate font-semibold text-text">{{ $machine->machine_number }}</h2>
                    <p class="truncate text-sm text-text-muted">{{ $machine->machine_type ?? '-' }} · {{ $machine->area }}</p>
                    <a href="{{ route('machine-qr.download', $machine) }}" class="inline-flex min-h-11 items-center text-sm font-semibold text-primary underline-offset-2 hover:underline">Download PNG</a>
                </div>
            </article>
        @endforeach
    </div>

    <x-infinite-scroll :paginator="$machines" key="machines" />

    {{-- Preview modal --}}
    <div x-show="preview" x-cloak @keydown.escape.window="preview = null" class="fixed inset-0 z-50 flex items-center justify-center bg-neutral-900/40 p-4" role="dialog" aria-modal="true" aria-labelledby="mq-preview-title">
        <div @click.outside="preview = null" class="w-full max-w-sm rounded-card border border-border bg-surface p-5 shadow-popover">
            <div class="mb-3 flex items-center justify-between gap-3">
                <h3 id="mq-preview-title" class="text-lg font-semibold text-text" x-text="previewName"></h3>
                <button type="button" @click="preview = null" class="btn btn-ghost min-h-11 min-w-11 px-0" aria-label="Close">✕</button>
            </div>
            <img :src="preview ? '{{ url('machine-qr') }}/' + preview + '/preview' : ''" alt="QR preview" class="mx-auto h-64 w-64 rounded-control border border-border bg-white p-4">
            <p class="mt-2 text-center text-xs text-text-muted">Prints at exactly 2 × 2 cm. Keep a white margin of at least 2 mm around it.</p>
            <a :href="preview ? '{{ url('machine-qr') }}/' + preview + '/download' : '#'" class="btn btn-primary mt-3 min-h-11 w-full">Download PNG (2 × 2 cm)</a>
        </div>
    </div>
</div>
<script>
    function machineQr(cfg) {
        const KEY = 'machine-qr-selected';
        return {
            selected: [], preview: null, previewName: '', busy: false,
            exportArea: '', areaCount: null,
            init() {
                try { this.selected = JSON.parse(sessionStorage.getItem(KEY) || '[]'); } catch (e) { this.selected = []; }
            },
            save() { try { sessionStorage.setItem(KEY, JSON.stringify(this.selected)); } catch (e) {} },
            has(id) { return this.selected.includes(id); },
            toggle(id, on) {
                this.selected = on ? [...new Set([...this.selected, id])] : this.selected.filter(i => i !== id);
                this.save();
            },
            // rows are loaded progressively (infinite scroll), so "page" = every row loaded so far
            allLoadedSelected: false,
            loadedIds() { return Array.from(document.querySelectorAll('table input[type=checkbox][value]')).map(c => Number(c.value)); },
            togglePage(on) {
                const ids = this.loadedIds();
                this.allLoadedSelected = on;
                this.selected = on
                    ? [...new Set([...this.selected, ...ids])]
                    : this.selected.filter(i => !ids.includes(i));
                this.save();
                this.$nextTick(() => document.querySelectorAll('input[type=checkbox][value]').forEach(c => c.checked = this.has(Number(c.value))));
            },
            clear() { this.selected = []; this.allLoadedSelected = false; this.save(); document.querySelectorAll('input[type=checkbox]').forEach(c => c.checked = false); },
            async selectAllFiltered() {
                this.busy = true;
                try {
                    const r = await fetch(cfg.idsUrl, { headers: { Accept: 'application/json' } });
                    const d = await r.json();
                    this.selected = [...new Set([...this.selected, ...d.ids])];
                    this.save();
                    document.querySelectorAll('input[type=checkbox][value]').forEach(c => c.checked = this.has(Number(c.value)));
                } catch (e) { alert('Could not load the filtered machines. Please try again.'); }
                this.busy = false;
            },
            async loadAreaCount() {
                this.areaCount = null;
                if (!this.exportArea) return;
                const r = await fetch(cfg.countUrl + '?mode=area&area=' + encodeURIComponent(this.exportArea), { headers: { Accept: 'application/json' } });
                this.areaCount = (await r.json()).count;
            },
            confirmSelected(e) {
                if (!confirm('Export ' + this.selected.length + ' machine QR label(s) to Word?')) e.preventDefault();
            },
            confirmArea(e) {
                if (!confirm('Export ' + this.areaCount + ' machine QR label(s) from area ' + this.exportArea + ' to Word?')) e.preventDefault();
            },
        };
    }
</script>
