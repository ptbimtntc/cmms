@extends('layouts.app')

@section('title', 'Oil Audit Action')

@section('content')
    <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-sky-700">Audit Oli</p>
            <h1 class="mt-1 text-2xl font-bold text-slate-900">Oil Audit Action</h1>
            <p class="mt-1 text-sm text-slate-500">Setiap record audit oli area WWD, satu baris per audit, untuk monitoring tindak lanjut.</p>
        </div>
        <a href="{{ route('oil-audits.scan') }}" class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-sky-600 px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-sky-700 lg:w-fit">
            <span class="text-lg leading-none">⌁</span> Mulai Audit dengan QR
        </a>
    </div>

    @if (session('success'))
        <div class="mb-5 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">{{ session('success') }}</div>
    @endif

    {{-- ============ Summary (audit-centric) ============ --}}
    <div class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="text-xs uppercase tracking-wide text-slate-400">Total Audit Records</div>
            <div class="mt-1 text-xl font-bold text-slate-800">{{ $summary['total_audit'] }}</div>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="text-xs uppercase tracking-wide text-slate-400">Total Finding</div>
            <div class="mt-1 text-xl font-bold text-rose-600">{{ $summary['total_finding'] }}</div>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="text-xs uppercase tracking-wide text-slate-400">Findings With Action Date</div>
            <div class="mt-1 text-xl font-bold text-emerald-600">{{ $summary['findings_with_action'] }}</div>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="text-xs uppercase tracking-wide text-slate-400">Avg. Action Duration</div>
            <div class="mt-1 text-xl font-bold text-sky-600">
                {{ $summary['average_action_duration'] !== null ? $summary['average_action_duration'].' days' : '-' }}
            </div>
        </div>
    </div>

    @if ($pendingAudits->isNotEmpty())
        <section class="mb-6 rounded-2xl border border-orange-200 bg-orange-50/60 p-4 sm:p-5">
            <div class="mb-3 flex items-center justify-between gap-3">
                <div>
                    <h2 class="font-bold text-slate-900">Follow up belum selesai <span class="text-slate-500">({{ $summary['pending'] }})</span></h2>
                    <p class="mt-0.5 text-xs text-slate-600">Prioritaskan kondisi oli yang membutuhkan action.</p>
                </div>
                <a href="{{ route('oil-audits.report', ['follow_up' => 1]) }}" class="text-sm font-semibold text-orange-700 hover:text-orange-900">Lihat semua</a>
            </div>
            <div class="grid gap-2 md:grid-cols-2">
                @foreach ($pendingAudits as $audit)
                    @php($colors = $audit->conditionColor())
                    <a href="{{ route('oil-audits.history', $audit->machine_number) }}" class="flex items-center justify-between gap-3 rounded-xl border border-white bg-white px-3 py-3 transition hover:border-orange-300 hover:shadow-sm">
                        <div class="min-w-0">
                            <p class="font-mono text-sm font-bold text-slate-900">{{ $audit->machine_number }}</p>
                            <p class="truncate text-xs text-slate-500">Dicek {{ $audit->audited_at->format('d M Y, H:i') }} · {{ $audit->audited_by_name }}</p>
                        </div>
                        <span class="shrink-0 rounded-full px-2.5 py-1 text-xs font-bold {{ $colors['badge'] }}">{{ $audit->conditionLabel() }}</span>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    <form method="GET" class="mb-5 flex flex-wrap gap-2 rounded-2xl border border-slate-200 bg-slate-50 p-3">
        <input name="search" value="{{ request('search') }}" placeholder="Cari nomor mesin, tipe, atau temuan" class="w-full min-w-0 rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-100 sm:min-w-[220px] sm:flex-1">
        <select name="area" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700 sm:w-auto">
            <option value="">Semua area</option>
            @foreach ($areas as $a)
                <option value="{{ $a }}" @selected(request('area') === $a)>{{ $a }}</option>
            @endforeach
        </select>
        <select name="machine_type" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700 sm:w-auto">
            <option value="">Semua tipe mesin</option>
            @foreach ($machineTypes as $type)
                <option value="{{ $type }}" @selected(request('machine_type') === $type)>{{ $type }}</option>
            @endforeach
        </select>
        <select name="year" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700 sm:w-auto">
            <option value="">Semua tahun</option>
            @foreach (range(now()->year, now()->year - 5) as $y)
                <option value="{{ $y }}" @selected((string) request('year') === (string) $y)>{{ $y }}</option>
            @endforeach
        </select>
        <select name="month" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700 sm:w-auto">
            <option value="">Semua bulan</option>
            @foreach (range(1, 12) as $m)
                <option value="{{ $m }}" @selected((string) request('month') === (string) $m)>{{ \Carbon\Carbon::create(null, $m, 1)->format('F') }}</option>
            @endforeach
        </select>
        <select name="condition" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700 sm:w-auto">
            <option value="">Semua kondisi</option>
            @foreach (\App\Models\OilAudit::CONDITION_LABELS as $value => $label)
                <option value="{{ $value }}" @selected(request('condition') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <select name="finding_status" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700 sm:w-auto">
            <option value="">Semua status temuan</option>
            <option value="NO_FINDING" @selected(request('finding_status') === 'NO_FINDING')>No Finding</option>
            <option value="OPEN" @selected(request('finding_status') === 'OPEN')>Open</option>
            <option value="COMPLETED" @selected(request('finding_status') === 'COMPLETED')>Completed</option>
        </select>
        <select name="pic" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700 sm:w-auto">
            <option value="">Semua PIC</option>
            @foreach ($pics as $p)
                <option value="{{ $p }}" @selected(request('pic') === $p)>{{ $p }}</option>
            @endforeach
        </select>
        <button class="flex-1 rounded-xl bg-slate-800 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-700 sm:flex-none">Filter</button>
        <a href="{{ route('oil-audits.report') }}" class="flex-1 rounded-xl px-3 py-2.5 text-center text-sm font-semibold text-slate-500 hover:text-slate-900 sm:flex-none">Reset</a>
    </form>

    {{-- Desktop: tabel audit-centric --}}
    @if ($audits->isEmpty())
        <div class="rounded-2xl border border-dashed border-slate-300 bg-white py-12 text-center text-sm text-slate-500">
            Tidak ada audit yang sesuai dengan filter.
        </div>
    @else
        <div class="hidden overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm md:block">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Audit Date</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Machine Number</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Machine Type</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Area</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">PIC</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Condition</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Finding</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Follow-up Status</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider text-slate-500">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @foreach ($audits as $audit)
                            @php($colors = $audit->conditionColor())
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-3 text-slate-600">{{ $audit->audited_at->format('d-m-Y') }}</td>
                                <td class="px-4 py-3 font-medium text-slate-800">{{ $audit->machine_number }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $audit->machine_type }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $audit->area }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $audit->audited_by_name ?: '-' }}</td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $colors['badge'] }}">
                                        {{ $audit->conditionLabel() }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-slate-600">
                                    @if (! $audit->needsFollowUp())
                                        <span class="text-slate-400">No Finding</span>
                                    @elseif ($audit->followUp)
                                        {{ $audit->followUp->problems->pluck('problem')->implode(', ') ?: $audit->conditionLabel() }}
                                    @else
                                        {{ $audit->conditionLabel() }} <span class="text-amber-600">(Pending)</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    @if (! $audit->needsFollowUp())
                                        <span class="text-slate-400">-</span>
                                    @elseif ($audit->followUp)
                                        <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700">Completed</span>
                                    @else
                                        <span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-700">Open</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <a href="{{ route('oil-audits.history', $audit->machine_number) }}" class="rounded bg-blue-600 px-3 py-1 text-xs font-medium text-white hover:bg-blue-700">View</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Mobile: kartu per audit --}}
        <div class="space-y-3 md:hidden">
            @foreach ($audits as $audit)
                @php($colors = $audit->conditionColor())
                <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="font-mono text-base font-bold text-slate-900">{{ $audit->machine_number }}</p>
                            <p class="mt-0.5 truncate text-xs text-slate-500">{{ $audit->machine_type }} · {{ $audit->area }} · {{ $audit->audited_at->format('d M Y, H:i') }}</p>
                        </div>
                        <span class="shrink-0 rounded-full px-2.5 py-1 text-xs font-bold {{ $colors['badge'] }}">{{ $audit->conditionLabel() }}</span>
                    </div>

                    <div class="mt-3 grid grid-cols-2 gap-x-3 gap-y-2 border-t border-slate-100 pt-3 text-xs">
                        <div>
                            <p class="text-slate-400">PIC</p>
                            <p class="mt-0.5 truncate font-medium text-slate-700">{{ $audit->audited_by_name ?: '-' }}</p>
                        </div>
                        <div>
                            <p class="text-slate-400">Finding</p>
                            <p class="mt-0.5 font-medium text-slate-700">
                                @if (! $audit->needsFollowUp())
                                    No Finding
                                @elseif ($audit->followUp)
                                    {{ $audit->followUp->problems->pluck('problem')->implode(', ') ?: $audit->conditionLabel() }}
                                @else
                                    {{ $audit->conditionLabel() }} (Pending)
                                @endif
                            </p>
                        </div>
                    </div>

                    <div class="mt-3 flex items-center justify-between gap-3 border-t border-slate-100 pt-3">
                        @if ($audit->needsFollowUp() && ! $audit->followUp)
                            <span class="text-xs font-semibold text-orange-700">Follow up diperlukan</span>
                        @elseif ($audit->followUp)
                            <span class="text-xs font-semibold text-emerald-700">✓ Action selesai</span>
                        @else
                            <span class="text-xs text-slate-400">&nbsp;</span>
                        @endif
                        <a href="{{ route('oil-audits.history', $audit->machine_number) }}" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 transition hover:border-slate-400 hover:bg-slate-50">Buka riwayat</a>
                    </div>
                </article>
            @endforeach
        </div>

        <div class="mt-5">{{ $audits->links() }}</div>
    @endif
@endsection
