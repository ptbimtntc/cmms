{{-- Tab "Temuan Blower Mati": every dead-blower finding from Ganti Filter Panel, OPEN first. --}}
@php($canReopen = auth()->user()->isAdmin() || auth()->user()->isKoordinator())

<form method="GET" class="card flex flex-col gap-3 p-4 sm:flex-row sm:flex-wrap sm:items-end">
    <input type="hidden" name="tab" value="{{ $tab }}">
    <div class="grid gap-1.5 sm:w-64">
        <label for="spmf-search" class="text-xs font-medium text-text-muted">Cari</label>
        <input id="spmf-search" type="text" name="search" value="{{ request('search') }}" placeholder="Nomor mesin" class="input lg:min-h-9">
    </div>
    <div class="grid gap-1.5 sm:w-48">
        <label for="spmf-row" class="text-xs font-medium text-text-muted">Row</label>
        <select id="spmf-row" name="row_id" class="select lg:min-h-9">
            <option value="">Semua Row</option>
            @foreach ($rows as $r)
                <option value="{{ $r->id }}" @selected(request('row_id') == $r->id)>{{ $r->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="grid gap-1.5">
        <span class="text-xs font-medium text-text-muted">Status</span>
        <x-checkbox-dropdown name="status" label="Status"
            :options="['OPEN' => 'OPEN', 'COMPLETED' => 'COMPLETED']"
            :selected="(array) request('status', [])" />
    </div>
    <div class="flex gap-2 sm:ml-auto">
        <x-ui.button type="submit" class="flex-1 sm:flex-none lg:min-h-9">Filter</x-ui.button>
        <x-ui.button variant="ghost" href="{{ route('small-pm.index', ['tab' => $tab]) }}" class="lg:min-h-9">Reset</x-ui.button>
    </div>
</form>

<p class="text-sm text-text-muted">Temuan dicatat dengan mencentang mesin di halaman Execute jadwal Ganti Filter Panel. Isi tindakan di sini setelah blower ditangani.</p>

@if ($findings->isEmpty())
    <x-ui.empty-state
        :title="request()->hasAny(['search', 'row_id', 'status']) ? 'Tidak ada temuan yang sesuai filter.' : 'Belum ada temuan blower filter mati.'"
        :description="request()->hasAny(['search', 'row_id', 'status']) ? 'Longgarkan filter, atau reset untuk melihat semua temuan.' : 'Temuan muncul di sini begitu mesin dicentang saat Execute Ganti Filter Panel.'" />
@endif

<div class="space-y-3" data-infinite-list="small-pm-findings">
    @foreach ($findings as $finding)
        @php($schedule = $finding->schedule)
        @php($open = $finding->status === 'OPEN')
        <article class="card min-w-0 p-4 sm:p-5">
            <div class="flex items-start justify-between gap-3">
                <dl class="grid min-w-0 flex-1 grid-cols-2 gap-x-4 gap-y-3 text-sm sm:grid-cols-4">
                    <div>
                        <dt class="text-xs text-text-muted">Mesin</dt>
                        <dd class="font-semibold text-text">{{ $finding->machine->machine_number ?? '-' }}</dd>
                        <dd class="text-xs text-text-muted">{{ $finding->machine->machine_type ?? '' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-text-muted">Row</dt>
                        <dd class="text-text">{{ $schedule->row->name ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-text-muted">Ditemukan</dt>
                        <dd class="text-text">{{ $schedule->action_date?->format('d M Y') ?? $finding->created_at->format('d M Y') }}</dd>
                        @unless (auth()->user()->isSupervisor())
                            <dd><a href="{{ route('small-pm.execute', $schedule) }}" class="inline-flex min-h-11 items-center text-xs font-medium text-primary underline-offset-2 hover:underline lg:min-h-0">Buka jadwal</a></dd>
                        @endunless
                    </div>
                    <div>
                        <dt class="text-xs text-text-muted">Tindakan</dt>
                        <dd class="break-words text-text">{{ $finding->action ?? '-' }}</dd>
                        @if ($finding->action_date)
                            <dd class="text-xs text-text-muted">{{ $finding->action_date->format('d M Y') }}</dd>
                        @endif
                    </div>
                </dl>
                <x-ui.badge :tone="$open ? 'warning' : 'success'" class="shrink-0">{{ $finding->status }}</x-ui.badge>
            </div>

            @if ($open && ! auth()->user()->isSupervisor())
                <form action="{{ route('small-pm.findings.update', [$schedule, $finding]) }}" method="POST" class="mt-4 flex flex-col gap-2 border-t border-border pt-4 sm:flex-row sm:items-end">
                    @csrf
                    @method('PATCH')
                    <div class="grid flex-1 gap-1.5">
                        <label for="fa-{{ $finding->id }}" class="text-xs font-medium text-text-muted">Tindakan</label>
                        <input id="fa-{{ $finding->id }}" type="text" name="action" required maxlength="2000" placeholder="Misalnya blower diganti baru" class="input lg:min-h-9">
                    </div>
                    <div class="grid gap-1.5">
                        <label for="fd-{{ $finding->id }}" class="text-xs font-medium text-text-muted">Tanggal</label>
                        <input id="fd-{{ $finding->id }}" type="date" name="action_date" value="{{ now()->toDateString() }}" class="input sm:w-44 lg:min-h-9">
                    </div>
                    <x-ui.button type="submit" variant="outline" class="lg:min-h-9">Tutup temuan</x-ui.button>
                </form>
            @elseif (! $open && $canReopen)
                <form action="{{ route('small-pm.findings.update', [$schedule, $finding]) }}" method="POST" class="mt-4 border-t border-border pt-4">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="status" value="OPEN">
                    <x-ui.button type="submit" variant="ghost" size="sm">Buka lagi</x-ui.button>
                </form>
            @endif
        </article>
    @endforeach
</div>

<x-infinite-scroll :paginator="$findings" key="small-pm-findings" />
