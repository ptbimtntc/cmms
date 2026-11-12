@php($rate = $overview['closing_rate'])

{{-- Filters: plain GET, every field labelled. --}}
<form method="GET" action="{{ route('predictive.index') }}" class="card grid grid-cols-1 gap-3 p-4 sm:grid-cols-2 lg:grid-cols-6 lg:items-end">
    <input type="hidden" name="tab" value="dashboard">
    <div class="grid gap-1.5">
        <label for="pd-from" class="text-xs font-medium text-text-muted">Dari tanggal WO</label>
        <input id="pd-from" type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="input lg:min-h-9">
    </div>
    <div class="grid gap-1.5">
        <label for="pd-to" class="text-xs font-medium text-text-muted">Sampai tanggal WO</label>
        <input id="pd-to" type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="input lg:min-h-9">
    </div>
    <div class="grid gap-1.5">
        <label for="pd-category" class="text-xs font-medium text-text-muted">Kategori</label>
        <select id="pd-category" name="category" class="select lg:min-h-9">
            <option value="">Semua</option>
            @foreach (\App\Models\PredictiveWorkOrder::CATEGORIES as $key => $label)
                <option value="{{ $key }}" @selected(($filters['category'] ?? '') === $key)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    @if ($areaOptions->isNotEmpty())
        <div class="grid gap-1.5">
            <label for="pd-area" class="text-xs font-medium text-text-muted">Area</label>
            <select id="pd-area" name="area" class="select lg:min-h-9">
                <option value="">Semua area</option>
                @foreach ($areaOptions as $a)
                    <option value="{{ $a }}" @selected(($filters['area'] ?? '') === $a)>{{ $a }}</option>
                @endforeach
            </select>
        </div>
    @endif
    <div class="grid gap-1.5">
        <label for="pd-type" class="text-xs font-medium text-text-muted">Machine Type</label>
        <select id="pd-type" name="machine_type" class="select lg:min-h-9">
            <option value="">Semua tipe</option>
            @foreach ($machineTypes as $t)
                <option value="{{ $t }}" @selected(($filters['machine_type'] ?? '') === $t)>{{ $t }}</option>
            @endforeach
        </select>
    </div>
    <div class="grid gap-1.5">
        <label for="pd-pic" class="text-xs font-medium text-text-muted">PIC</label>
        <select id="pd-pic" name="pic" class="select lg:min-h-9">
            <option value="">Semua PIC</option>
            @foreach ($pics as $p)
                <option value="{{ $p->id }}" @selected((string) ($filters['pic'] ?? '') === (string) $p->id)>{{ $p->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="flex gap-2 sm:col-span-2 lg:col-span-6 lg:justify-end">
        <x-ui.button type="submit" class="flex-1 lg:min-h-9 lg:flex-none">Terapkan filter</x-ui.button>
        <x-ui.button variant="ghost" href="{{ route('predictive.index', ['tab' => 'dashboard']) }}" class="lg:min-h-9">Reset</x-ui.button>
    </div>
</form>

<p class="-mt-2 text-xs text-text-muted">
    Periode memakai tanggal WO. Filter PIC mencakup WO yang sedang di-claim atau pernah diberi Action oleh PIC tersebut.
</p>

{{-- A. WO Overview. Closing Rate is the focal figure and the only brand gradient here; the rest are supporting counts. --}}
<section class="grid grid-cols-1 gap-4 lg:grid-cols-5" aria-label="WO Overview">
    <div class="card p-5 lg:col-span-2">
        <h2 class="text-sm font-medium text-text-muted">Closing Rate</h2>
        <p class="mt-1 font-signage text-5xl font-semibold leading-none tabular-nums text-text">{{ rtrim(rtrim(number_format($rate, 1, ',', '.'), '0'), ',') }}%</p>
        <div class="mt-4 h-2.5 overflow-hidden rounded-full bg-surface-muted" role="progressbar" aria-valuenow="{{ $rate }}" aria-valuemin="0" aria-valuemax="100" aria-label="Closing Rate">
            <div class="h-full rounded-full bg-linear-to-r from-brand-from to-brand-to" style="width: {{ $rate }}%"></div>
        </div>
        <p class="mt-2 text-xs text-text-muted">{{ $overview['closed'] }} dari {{ $overview['total'] }} WO berstatus CLOSED. WO yang di-reopen dihitung OPEN.</p>
    </div>
    <dl class="card grid grid-cols-3 divide-x divide-border lg:col-span-3">
        <div class="flex min-w-0 flex-col justify-center px-3 py-5 sm:px-5">
            <dt class="text-sm text-text-muted">Total WO</dt>
            <dd class="mt-1 font-signage text-3xl font-semibold tabular-nums text-text">{{ $overview['total'] }}</dd>
        </div>
        <div class="flex min-w-0 flex-col justify-center px-3 py-5 sm:px-5">
            <dt class="text-sm text-warning-text">Total OPEN</dt>
            <dd class="mt-1 font-signage text-3xl font-semibold tabular-nums text-warning-text">{{ $overview['open'] }}</dd>
        </div>
        <div class="flex min-w-0 flex-col justify-center px-3 py-5 sm:px-5">
            <dt class="text-sm text-success-hover">Total CLOSED</dt>
            <dd class="mt-1 font-signage text-3xl font-semibold tabular-nums text-success-hover">{{ $overview['closed'] }}</dd>
        </div>
    </dl>
</section>

<div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
    @foreach ([['Finding Summary', 'Finding apa yang paling sering muncul?', $findings], ['Problem Summary', 'Problem apa yang paling sering muncul?', $problems]] as [$title, $question, $rows])
        @php($max = max(1, (int) ($rows->max('total') ?? 0)))
        <section class="card p-5" aria-label="{{ $title }}">
            <h2 class="font-semibold text-text">{{ $title }}</h2>
            <p class="mt-0.5 text-sm text-text-muted">{{ $question }} Dari seluruh histori Action, termasuk setelah Reopen.</p>
            @if ($rows->isEmpty())
                <x-ui.empty-state class="mt-4" title="Belum ada Action pada WO yang sesuai filter." />
            @else
                <ol class="mt-4 space-y-3">
                    @foreach ($rows as $row)
                        <li>
                            <div class="flex items-baseline justify-between gap-3 text-sm">
                                <span class="min-w-0 break-words text-text">{{ $row->label }}</span>
                                <span class="shrink-0 font-semibold tabular-nums text-text">{{ $row->total }}x</span>
                            </div>
                            <div class="mt-1 h-2 overflow-hidden rounded-full bg-surface-muted"><div class="h-full rounded-full bg-chart-1" style="width: {{ round($row->total / $max * 100) }}%"></div></div>
                        </li>
                    @endforeach
                </ol>
            @endif
        </section>
    @endforeach
</div>
