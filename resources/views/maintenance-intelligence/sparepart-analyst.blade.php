@extends('layouts.app')

@php
    $hideSidebar = true;
    $hideTopbar = true;
    $fullWidth = true;
    $contentMaxWidth = 'max-w-[110rem]';

    $card = 'card p-5 sm:p-6';
    $th = 'whitespace-nowrap';
    $td = 'align-top';
    $input = 'input lg:min-h-9';
    $select = 'select lg:min-h-9';
    $n = fn ($v, $d = 0) => number_format((float) $v, $d, ',', '.');
    $levelClass = ['LIKELY' => 'badge-success', 'POSSIBLE' => 'badge-warning', 'RARE' => 'badge-neutral'];
    $trendLabel = ['INCREASING' => '↑ Naik', 'DECREASING' => '↓ Turun', 'STABLE' => '→ Stabil', 'UNKNOWN' => '-'];
    $q = $run?->summary['quality'] ?? null;
    $sections = ['overview' => 'Overview', 'machine' => 'Machine Forecast', 'demand' => 'Sparepart Demand', 'type' => 'Machine Type', 'history' => 'Historical Usage', 'evaluation' => 'Evaluation', 'quality' => 'Data Quality', 'recs' => 'Recommendations', 'runs' => 'History'];
@endphp

@section('content')
<div class="space-y-6">

    <div class="{{ $card }}">
        <div class="flex flex-wrap items-center gap-2 text-sm">
            <a href="{{ route('maintenance-intelligence.index') }}" class="btn btn-ghost -ml-2 min-h-11">Maintenance Intelligence Center</a>
            <a href="{{ route('home') }}" class="btn btn-ghost min-h-11">Landing Page</a>
        </div>
        <h1 class="mt-3 font-signage text-2xl font-semibold leading-tight text-text sm:text-3xl">AI Sparepart Analyst</h1>
        <p class="mt-1 text-sm text-text-muted">
            Forecast kebutuhan sparepart per mesin dari histori PM Sparepart Usage. Perhitungan dilakukan oleh engine statistik
            (versi {{ \App\Services\SparepartForecast\ForecastEngine::VERSION }}); penjelasan AI (Gemini) belum terintegrasi.
            Mandatory Sparepart dan Sparepart Injection tidak dipakai sebagai sumber.
        </p>

        @if (session('success'))<div class="alert alert-success mt-4" role="status">{{ session('success') }}</div>@endif
        @if (session('error'))<div class="alert alert-danger mt-4" role="alert">{{ session('error') }}</div>@endif
        @if ($errors->any())<div class="alert alert-danger mt-4" role="alert">{{ $errors->first() }}</div>@endif

        <form method="POST" action="{{ route('maintenance-intelligence.sparepart-analyst.run') }}" class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
            @csrf
            <div>
                <label class="text-xs font-medium text-text-muted">Periode</label>
                <select name="period_type" class="{{ $select }}">
                    <option value="MONTH">Bulanan</option>
                    <option value="WEEK">Mingguan</option>
                </select>
            </div>
            <div>
                <label class="text-xs font-medium text-text-muted">Tanggal dalam periode</label>
                <input type="date" name="period_date" value="{{ now()->toDateString() }}" class="{{ $input }}" required>
            </div>
            <div>
                <label class="text-xs font-medium text-text-muted">Area (scope analisis)</label>
                <select name="area" class="{{ $select }}"><option value="">Semua</option>@foreach ($areas as $a)<option>{{ $a }}</option>@endforeach</select>
            </div>
            <div>
                <label class="text-xs font-medium text-text-muted">Machine Type (scope analisis)</label>
                <select name="machine_type" class="{{ $select }}"><option value="">Semua</option>@foreach ($machineTypes as $t)<option>{{ $t }}</option>@endforeach</select>
            </div>
            <div class="flex items-end">
                <button class="btn btn-primary min-h-11 w-full">Jalankan Analisis</button>
            </div>
        </form>
    </div>

    @if (! $run)
        <div class="{{ $card }} text-sm text-text-muted">Belum ada analisis. Jalankan analisis pertama dari form di atas.</div>
    @elseif ($run->status !== 'COMPLETED')
        <div class="{{ $card }} text-sm text-danger">Run #{{ $run->id }} berstatus {{ $run->status }}. {{ $run->error }}</div>
    @else
        <nav class="flex gap-2 overflow-x-auto text-sm">
            @foreach ($sections as $id => $label)
                <a href="#{{ $id }}" class="inline-flex min-h-11 shrink-0 items-center rounded-full border border-border bg-surface px-4 font-medium text-text-muted hover:text-text lg:min-h-9">{{ $label }}</a>
            @endforeach
        </nav>

        {{-- Filters on the displayed run --}}
        <form method="GET" class="{{ $card }} grid gap-3 sm:grid-cols-2 lg:grid-cols-6">
            <input type="hidden" name="run" value="{{ $run->id }}">
            <div><label class="text-xs font-medium text-text-muted">Area</label>
                <select name="area" class="{{ $select }}"><option value="">Semua</option>@foreach ($areas as $a)<option @selected(($filters['area'] ?? '') === $a)>{{ $a }}</option>@endforeach</select></div>
            <div><label class="text-xs font-medium text-text-muted">Machine Type</label>
                <select name="machine_type" class="{{ $select }}"><option value="">Semua</option>@foreach ($machineTypes as $t)<option @selected(($filters['machine_type'] ?? '') === $t)>{{ $t }}</option>@endforeach</select></div>
            <div><label class="text-xs font-medium text-text-muted">Machine</label>
                <input name="machine" value="{{ $filters['machine'] ?? '' }}" placeholder="No. mesin" class="{{ $input }}"></div>
            <div class="lg:col-span-2"><label class="text-xs font-medium text-text-muted">Sparepart</label>
                <input name="sparepart" value="{{ $filters['sparepart'] ?? '' }}" placeholder="SAP number / deskripsi" class="{{ $input }}"></div>
            <div class="flex items-end gap-2">
                <button class="btn btn-primary min-h-11 flex-1">Filter</button>
                <a href="{{ route('maintenance-intelligence.sparepart-analyst.export', $run) }}" class="btn btn-outline min-h-11">Excel</a>
            </div>
        </form>

        {{-- 1. Overview --}}
        <section id="overview" class="{{ $card }}">
            <h2 class="text-lg font-semibold text-text">1. Forecast Overview</h2>
            <p class="mt-1 text-sm text-text-muted">
                Run #{{ $run->id }} · {{ $run->period_type === 'WEEK' ? 'Mingguan' : 'Bulanan' }}
                {{ $run->period_start->format('d M Y') }} – {{ $run->period_end->format('d M Y') }}
                · dianalisis {{ $run->finished_at?->format('d M Y H:i') }}
                · scope: {{ $run->scope_area ?? 'semua area' }} / {{ $run->scope_machine_type ?? 'semua tipe' }}
            </p>
            <div class="mt-4 grid grid-cols-2 gap-3 lg:grid-cols-5">
                @foreach ([
                    ['Mesin dalam scope', $q['machines_in_scope']],
                    ['Mesin dengan prediksi', $q['machines_sufficient']],
                    ['Insufficient Historical Data', $q['machines_insufficient']],
                    ['Item LIKELY (terfilter)', $totals['likely_items']],
                    ['Total qty rekomendasi (terfilter)', $totals['recommended']],
                ] as [$label, $value])
                    <div class="rounded-control border border-border p-4">
                        <p class="text-xs text-text-muted">{{ $label }}</p>
                        <p class="mt-1 text-2xl font-semibold text-text">{{ $n($value) }}</p>
                    </div>
                @endforeach
            </div>
            <p class="mt-3 text-xs text-text-muted">
                Rekomendasi hanya untuk sparepart berstatus LIKELY (probabilitas pakai ≥ {{ $run->parameters['likely_probability'] * 100 }}%).
                Jumlah PM adalah PM terjadwal (OPEN / IN_PROGRESS / MISSED) pada periode; PM yang belum dijadwalkan tidak diperkirakan.
            </p>
        </section>

        {{-- 2. Machine-based --}}
        <section id="machine" class="{{ $card }}">
            <h2 class="text-lg font-semibold text-text">2. Machine-Based Forecast</h2>
            <div class="relative mt-3 overflow-x-auto rounded-control border border-border">
                <table class="data-table min-w-full">
                    <thead><tr>
                        @foreach (['Mesin', 'Tipe', 'Sparepart', 'Dipakai', 'Avg qty', 'Prob.', 'PM', 'Estimasi', 'Rekomendasi', 'Level', 'Conf.', 'Dasar'] as $h)<th class="{{ $th }}">{{ $h }}</th>@endforeach
                    </tr></thead>
                    <tbody class="divide-y divide-border" data-infinite-list="forecast">
                    @forelse ($forecast as $r)
                        <tr>
                            <td class="{{ $td }} whitespace-nowrap font-medium">{{ $r->machine_number }}</td>
                            <td class="{{ $td }}">{{ $r->machine_type }}</td>
                            <td class="{{ $td }}"><span class="font-medium">{{ $r->material_number }}</span><br><span class="text-xs text-text-muted">{{ $r->description }}</span></td>
                            <td class="{{ $td }} whitespace-nowrap">{{ $r->usage_pm_count }}/{{ $r->pm_considered }} PM</td>
                            <td class="{{ $td }}">{{ $n($r->avg_qty, 2) }} {{ $r->unit }}</td>
                            <td class="{{ $td }}">{{ $n($r->probability * 100) }}%</td>
                            <td class="{{ $td }}">{{ $r->upcoming_pm_count }}</td>
                            <td class="{{ $td }}">{{ $n($r->expected_qty, 2) }}</td>
                            <td class="{{ $td }} font-semibold">{{ $r->recommended_qty }}</td>
                            <td class="{{ $td }}"><span class="badge {{ $levelClass[$r->level] }}">{{ $r->level }}</span></td>
                            <td class="{{ $td }}">{{ $r->confidence }}</td>
                            <td class="{{ $td }}"><details><summary class="cursor-pointer text-xs text-primary">Lihat</summary>
                                <ul class="mt-1 w-64 list-disc pl-4 text-xs text-text-muted">@foreach ($r->basis['lines'] ?? [] as $line)<li>{{ $line }}</li>@endforeach</ul></details></td>
                        </tr>
                    @empty
                        <tr><td colspan="12" class="{{ $td }} text-text-muted">Tidak ada hasil prediksi untuk filter ini.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-3"><x-infinite-scroll :paginator="$forecast" key="forecast" /></div>

            <h3 class="mt-6 text-sm font-semibold text-text">Mesin dengan Insufficient Historical Data</h3>
            <p class="text-xs text-text-muted">Tidak diberi angka prediksi dan tidak digantikan oleh Mandatory Sparepart maupun Injection.</p>
            <div class="relative mt-2 overflow-x-auto rounded-control border border-border">
                <table class="data-table min-w-full">
                    <thead><tr>@foreach (['Mesin', 'Tipe', 'Area', 'PM selesai', 'PM ada pencatatan', 'PM dikecualikan', 'Keterangan'] as $h)<th class="{{ $th }}">{{ $h }}</th>@endforeach</tr></thead>
                    <tbody class="divide-y divide-border" data-infinite-list="insufficient">
                    @forelse ($insufficient as $m)
                        <tr>
                            <td class="{{ $td }} font-medium">{{ $m->machine_number }}</td><td class="{{ $td }}">{{ $m->machine_type }}</td><td class="{{ $td }}">{{ $m->area }}</td>
                            <td class="{{ $td }}">{{ $m->finished_pm_count }}</td><td class="{{ $td }}">{{ $m->pm_with_usage_count }}</td><td class="{{ $td }}">{{ $m->excluded_pm_count }}</td>
                            <td class="{{ $td }}">{{ $m->note }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="{{ $td }} text-text-muted">Tidak ada.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-3"><x-infinite-scroll :paginator="$insufficient" key="insufficient" /></div>
        </section>

        {{-- 3. Sparepart demand --}}
        <section id="demand" class="{{ $card }}">
            <h2 class="text-lg font-semibold text-text">3. Sparepart Demand Summary</h2>
            <p class="mt-1 text-sm text-text-muted">Agregasi seluruh mesin pada filter ini, per sparepart (maks. 100 baris; lengkapnya di Excel).</p>
            <div class="relative mt-3 overflow-x-auto rounded-control border border-border">
                <table class="data-table min-w-full">
                    <thead><tr>@foreach (['SAP Number', 'Deskripsi', 'Unit', 'Mesin', 'Estimasi total', 'Rekomendasi total'] as $h)<th class="{{ $th }}">{{ $h }}</th>@endforeach</tr></thead>
                    <tbody class="divide-y divide-border">
                    @forelse ($bySparepart as $r)
                        <tr><td class="{{ $td }} font-medium">{{ $r->material_number }}</td><td class="{{ $td }}">{{ $r->description }}</td><td class="{{ $td }}">{{ $r->unit }}</td>
                            <td class="{{ $td }}">{{ $r->machines }}</td><td class="{{ $td }}">{{ $n($r->expected, 2) }}</td><td class="{{ $td }} font-semibold">{{ $n($r->recommended) }}</td></tr>
                    @empty
                        <tr><td colspan="6" class="{{ $td }} text-text-muted">Belum ada data.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        {{-- 4. Machine type / area --}}
        <section id="type" class="{{ $card }}">
            <h2 class="text-lg font-semibold text-text">4. Machine Type Analysis</h2>
            <div class="mt-3 grid gap-6 lg:grid-cols-2">
                <div class="relative overflow-x-auto rounded-control border border-border">
                    <table class="data-table min-w-full">
                        <thead><tr>@foreach (['Machine Type', 'Mesin (prediksi / insufficient)', 'PM terjadwal', 'Jenis sparepart', 'Estimasi', 'Rekomendasi'] as $h)<th class="{{ $th }}">{{ $h }}</th>@endforeach</tr></thead>
                        <tbody class="divide-y divide-border">
                        @foreach ($machineStatusByType as $type => $s)
                            @php $r = $byMachineType->firstWhere('machine_type', $type); @endphp
                            <tr><td class="{{ $td }} font-medium">{{ $type }}</td><td class="{{ $td }}">{{ $s->sufficient }} / {{ $s->insufficient }}</td><td class="{{ $td }}">{{ $s->upcoming }}</td>
                                <td class="{{ $td }}">{{ $r->parts ?? 0 }}</td><td class="{{ $td }}">{{ $n($r->expected ?? 0, 2) }}</td><td class="{{ $td }} font-semibold">{{ $n($r->recommended ?? 0) }}</td></tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="relative overflow-x-auto rounded-control border border-border">
                    <table class="data-table min-w-full">
                        <thead><tr>@foreach (['Area', 'Mesin', 'Estimasi', 'Rekomendasi'] as $h)<th class="{{ $th }}">{{ $h }}</th>@endforeach</tr></thead>
                        <tbody class="divide-y divide-border">
                        @forelse ($byArea as $r)
                            <tr><td class="{{ $td }} font-medium">{{ $r->area }}</td><td class="{{ $td }}">{{ $r->machines }}</td><td class="{{ $td }}">{{ $n($r->expected, 2) }}</td><td class="{{ $td }} font-semibold">{{ $n($r->recommended) }}</td></tr>
                        @empty
                            <tr><td colspan="4" class="{{ $td }} text-text-muted">Belum ada data.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        {{-- 5. Historical usage --}}
        <section id="history" class="{{ $card }}">
            <h2 class="text-lg font-semibold text-text">5. Historical Usage Analysis</h2>
            <p class="mt-1 text-sm text-text-muted">
                Histori {{ $run->history_from?->format('d M Y') ?? '-' }} – {{ $run->history_to?->format('d M Y') ?? '-' }}
                (window {{ $run->parameters['window'] }} PM terakhir per mesin). Frekuensi, total qty, dan tren per sparepart.
            </p>
            <div class="relative mt-3 overflow-x-auto rounded-control border border-border">
                <table class="data-table min-w-full">
                    <thead><tr>@foreach (['SAP Number', 'Deskripsi', 'Mesin yang pernah pakai', 'Total kejadian pakai', 'Total qty histori', 'Mesin tren naik', 'Mesin tren turun'] as $h)<th class="{{ $th }}">{{ $h }}</th>@endforeach</tr></thead>
                    <tbody class="divide-y divide-border">
                    @forelse ($bySparepart->sortByDesc('hist_qty')->take(30) as $r)
                        <tr><td class="{{ $td }} font-medium">{{ $r->material_number }}</td><td class="{{ $td }}">{{ $r->description }}</td><td class="{{ $td }}">{{ $r->machines }}</td>
                            <td class="{{ $td }}">{{ $r->usage_pm }}</td><td class="{{ $td }}">{{ $n($r->hist_qty) }} {{ $r->unit }}</td><td class="{{ $td }}">{{ $r->increasing }}</td><td class="{{ $td }}">{{ $r->decreasing }}</td></tr>
                    @empty
                        <tr><td colspan="7" class="{{ $td }} text-text-muted">Belum ada data.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <p class="mt-2 text-xs text-text-muted">Interval antar-pemakaian dan tren per mesin tersedia pada kolom "Dasar" (section 2) dan di export Excel.</p>
        </section>

        {{-- 6. Evaluation --}}
        <section id="evaluation" class="{{ $card }}">
            <h2 class="text-lg font-semibold text-text">6. Forecast Evaluation &amp; Accuracy</h2>
            @if ($evaluation)
                <p class="mt-1 text-sm text-text-muted">{{ $evaluation->split_method }}.</p>
                <div class="mt-4 grid gap-4 lg:grid-cols-3">
                    <div class="rounded-control border border-border p-4 text-sm text-text">
                        <h3 class="font-semibold text-text">Training data</h3>
                        <p>Baris penggunaan: {{ $n($evaluation->train_usage_rows) }}</p>
                        <p>PM: {{ $n($evaluation->train_pm_count) }} · Mesin: {{ $n($evaluation->train_machine_count) }} · Sparepart: {{ $n($evaluation->train_sparepart_count) }}</p>
                        <p>Rentang: {{ $evaluation->train_from?->format('d M Y') ?? '-' }} – {{ $evaluation->train_to?->format('d M Y') ?? '-' }}</p>
                    </div>
                    <div class="rounded-control border border-border p-4 text-sm text-text">
                        <h3 class="font-semibold text-text">Testing data</h3>
                        <p>Baris penggunaan: {{ $n($evaluation->test_usage_rows) }}</p>
                        <p>PM: {{ $n($evaluation->test_pm_count) }} · Mesin: {{ $n($evaluation->test_machine_count) }} · Item dibandingkan: {{ $n($evaluation->compared_items) }}</p>
                        <p>Rentang: {{ $evaluation->test_from?->format('d M Y') ?? '-' }} – {{ $evaluation->test_to?->format('d M Y') ?? '-' }}</p>
                        <p class="mt-1">Cukup untuk evaluasi: <strong>{{ $evaluation->status === 'AVAILABLE' ? 'Ya' : 'Tidak' }}</strong></p>
                    </div>
                    <div class="rounded-control border border-border p-4 text-sm text-text">
                        <h3 class="font-semibold text-text">Accuracy</h3>
                        @if ($evaluation->status === 'AVAILABLE')
                            <p>MAE: <strong>{{ $n($evaluation->mae, 2) }}</strong> unit / mesin-sparepart</p>
                            <p>WAPE: <strong>{{ $n($evaluation->wape * 100, 1) }}%</strong></p>
                            <p>Bias: <strong>{{ $n($evaluation->bias * 100, 1) }}%</strong></p>
                        @else
                            <p class="font-semibold text-warning-text">Accuracy Not Available — Insufficient Testing Data</p>
                            @foreach ($evaluation->notes ?? [] as $note)<p class="text-xs text-text-muted">{{ $note }}</p>@endforeach
                        @endif
                    </div>
                </div>
                <p class="mt-3 text-xs text-text-muted">
                    Syarat evaluasi: minimal {{ $run->parameters['min_test_pm'] }} PM testing dari minimal {{ $run->parameters['min_test_machines'] }} mesin.
                    Angka di atas dihitung dari data aktual saat run ini dijalankan, bukan diisi manual.
                </p>
            @else
                <p class="mt-2 text-sm text-text-muted">Evaluasi tidak tersedia untuk run ini.</p>
            @endif
        </section>

        {{-- 7. Data quality --}}
        <section id="quality" class="{{ $card }}">
            <h2 class="text-lg font-semibold text-text">7. Data Quality &amp; Model Notes</h2>
            <dl class="mt-3 grid gap-x-8 gap-y-2 text-sm sm:grid-cols-2 lg:grid-cols-3">
                @foreach ([
                    'PM selesai (mesin dalam scope)' => $q['finished_pm_total'],
                    'PM dipakai sebagai histori' => $q['finished_pm_used'],
                    'PM dipakai tanpa catatan sparepart' => $q['finished_pm_without_usage'],
                    'PM dikecualikan (memuat Injection)' => $q['finished_pm_excluded_warehouse'],
                    'PM selesai tanpa tanggal' => $q['finished_pm_without_date'],
                    'Baris penggunaan dipakai' => $q['usage_rows'],
                    'Jenis sparepart tercatat' => $q['distinct_spareparts'],
                    'Mesin tanpa histori PM selesai' => $q['machines_without_history'],
                    'Mesin cukup data tanpa PM terjadwal' => $q['machines_without_upcoming_pm'],
                ] as $label => $value)
                    <div class="flex justify-between border-b border-border py-1"><dt class="text-text-muted">{{ $label }}</dt><dd class="font-medium text-text">{{ $n($value) }}</dd></div>
                @endforeach
            </dl>
            <ul class="mt-4 list-disc space-y-1 pl-5 text-xs text-text-muted">
                <li>Algoritma: {{ $run->algorithm_version }}. Analisis: {{ $run->finished_at?->format('d M Y H:i') }}. Fingerprint data: <code>{{ \Illuminate\Support\Str::limit($run->data_fingerprint, 16, '…') }}</code>.</li>
                <li>Mesin butuh minimal {{ $run->parameters['min_pm'] }} PM selesai dengan minimal {{ $run->parameters['min_pm_with_usage'] }} PM yang punya catatan sparepart.</li>
                <li>PM selesai tanpa catatan sparepart dihitung sebagai "tidak memakai sparepart". Belum ada penanda bahwa pencatatan PM sudah lengkap, jadi pencatatan yang terlewat menurunkan probabilitas.</li>
                <li>Belum ada penanda pembeda sparepart yang benar-benar diganti dengan nilai default yang ikut tersimpan dari form.</li>
            </ul>
        </section>

        {{-- 8. Recommendations --}}
        <section id="recs" class="{{ $card }}">
            <h2 class="text-lg font-semibold text-text">8. Recommendations</h2>
            <p class="mt-1 text-sm text-text-muted">Dihasilkan otomatis dari angka engine (source: ENGINE). Penjelasan Gemini belum aktif.</p>
            <ul class="mt-3 space-y-2 text-sm">
                @forelse ($recommendations as $rec)
                    <li class="rounded-control border px-3 py-2 {{ $rec->severity === 'WARNING' ? 'border-warning/40 bg-warning-light text-warning-text' : 'border-border text-text' }}">{{ $rec->message }}</li>
                @empty
                    <li class="text-text-muted">Tidak ada rekomendasi.</li>
                @endforelse
            </ul>
        </section>
    @endif

    {{-- 9. History --}}
    <section id="runs" class="{{ $card }}">
        <h2 class="text-lg font-semibold text-text">9. Analysis History</h2>
        <div class="relative mt-3 overflow-x-auto rounded-control border border-border">
            <table class="data-table min-w-full">
                <thead><tr>@foreach (['Run', 'Waktu', 'Periode', 'Scope', 'Versi', 'Status', 'Oleh', ''] as $h)<th class="{{ $th }}">{{ $h }}</th>@endforeach</tr></thead>
                <tbody class="divide-y divide-border">
                @forelse ($runs as $r)
                    <tr class="{{ $run && $run->id === $r->id ? 'bg-primary-light' : '' }}">
                        <td class="{{ $td }}">#{{ $r->id }}</td><td class="{{ $td }}">{{ $r->created_at->format('d M Y H:i') }}</td>
                        <td class="{{ $td }}">{{ $r->period_type }} {{ $r->period_start->format('d M') }}–{{ $r->period_end->format('d M Y') }}</td>
                        <td class="{{ $td }}">{{ $r->scope_area ?? 'Semua' }} / {{ $r->scope_machine_type ?? 'Semua' }}</td>
                        <td class="{{ $td }}">{{ $r->algorithm_version }}</td><td class="{{ $td }}">{{ $r->status }}</td><td class="{{ $td }}">{{ $r->user?->name ?? '-' }}</td>
                        <td class="{{ $td }}"><a class="font-semibold text-primary underline-offset-2 hover:underline" href="{{ route('maintenance-intelligence.sparepart-analyst', ['run' => $r->id]) }}">Buka</a></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="{{ $td }} text-text-muted">Belum ada analisis.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <p class="mt-2 text-xs text-text-muted">Hasil lama tetap tersimpan; menjalankan analisis baru tidak menimpanya.</p>
    </section>
</div>
@endsection
