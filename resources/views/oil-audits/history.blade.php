@extends('layouts.app')

@php
    $hideSidebar = true;
    $hideTopbar = true;
@endphp

@section('title', 'Riwayat Audit Oli')

@section('content')
    @php
        $latestColors = $latestAudit?->conditionColor();
        $barHeights = [
            'OKE' => 'h-7',
            'PANTAU' => 'h-10',
            'OLI_KERUH' => 'h-12',
            'HAMPIR_GARIS' => 'h-14',
            'GLASS_BUREM' => 'h-16',
            'PAS_GARIS' => 'h-18',
            'KRITIS' => 'h-20',
        ];
    @endphp

    <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <a href="{{ $backUrl }}" class="inline-flex items-center gap-2 text-sm font-semibold text-slate-600 transition hover:text-slate-900"><span aria-hidden="true"></span> {{ $backLabel }}</a>
    </div>

    @if (session('success'))
        <div class="mb-5 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">{{ session('success') }}</div>
    @endif
    @if (session('warning'))
        <div class="mb-5 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-800">{{ session('warning') }}</div>
    @endif

    <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-col gap-4 border-b border-slate-100 p-5 sm:flex-row sm:items-start sm:justify-between sm:p-7">
            <div class="min-w-0">
                <h1 class="font-mono text-3xl font-bold tracking-tight text-slate-950">{{ $machine->machine_number }}</h1>
                <p class="mt-1 text-sm text-slate-600">{{ $machine->machine_type }} · {{ $machine->area }}@if($machine->description) · {{ $machine->description }}@endif</p>
            </div>
            @if ($latestAudit)
                <div class="w-full rounded-2xl px-4 py-3 text-center sm:w-auto {{ $latestColors['badge'] }}">
                    <p class="text-xs font-medium opacity-90">Kondisi terakhir</p>
                    <p class="mt-0.5 text-lg font-bold">{{ $latestAudit->conditionLabel() }}</p>
                </div>
            @else
                <div class="w-full rounded-2xl bg-slate-100 px-4 py-3 text-center text-slate-600 sm:w-auto">
                    <p class="text-xs">Kondisi terakhir</p>
                    <p class="mt-0.5 text-lg font-bold">Belum ada audit</p>
                </div>
            @endif
        </div>

        <div class="p-5 sm:p-7">
            <div class="mb-3 flex items-center justify-between gap-3">
                <div>
                    <h2 class="text-sm font-bold uppercase tracking-wide text-slate-700">Riwayat kondisi</h2>
                    <p class="mt-1 text-xs text-slate-500">Kiri adalah pengecekan lama, kanan adalah yang terbaru.</p>
                </div>
                <span class="text-xs font-medium text-slate-400">{{ $machine->oilAudits()->count() }} audit</span>
            </div>

            @if ($recentAudits->isNotEmpty())
                <div class="overflow-x-auto pb-2">
                    <div class="flex min-w-[320px] items-end gap-1.5 border-b border-slate-200 pt-4 sm:min-w-[520px]">
                        @foreach ($recentAudits as $audit)
                            @php($colors = $audit->conditionColor())
                            <div class="group flex min-w-0 flex-1 flex-col items-center justify-end">
                                <span class="mb-2 hidden rounded-md bg-slate-900 px-2 py-1 text-[11px] font-semibold text-white shadow-sm group-hover:block">{{ $audit->conditionLabel() }}</span>
                                <div class="w-full min-h-7 rounded-t-xl {{ $barHeights[$audit->condition] ?? 'h-7' }} {{ $colors['bar'] }}" title="{{ $audit->conditionLabel() }}"></div>
                                <p class="mt-2 whitespace-nowrap text-[10px] text-slate-500">{{ $audit->audited_at->format('d M') }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>
            @else
                <div class="rounded-2xl bg-slate-50 px-4 py-8 text-center text-sm text-slate-500">Belum ada riwayat audit oli untuk mesin ini.</div>
            @endif
        </div>
    </section>

    <section class="mt-6">
        <div class="mb-4">
            <h2 class="text-xl font-bold text-slate-900">Detail pengecekan</h2>
            <p class="mt-1 text-sm text-slate-500">Setiap kondisi yang perlu action ditampilkan jelas agar tidak terlewat.</p>
        </div>

        <div class="relative ml-3 border-l border-slate-200 pl-7 sm:ml-4">
            @forelse ($audits as $audit)
                @php($colors = $audit->conditionColor())
                <article class="relative mb-4">
                    <span class="absolute -left-[37px] top-4 h-4 w-4 rounded-full border-2 border-white shadow-sm {{ $colors['dot'] }}"></span>
                    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="rounded-full px-2.5 py-1 text-xs font-bold {{ $colors['badge'] }}">{{ $audit->conditionLabel() }}</span>
                                    <time class="text-sm font-bold text-slate-900">{{ $audit->audited_at->format('d M Y, H:i') }}</time>
                                </div>
                                <p class="mt-2 text-sm text-slate-600">Dicek oleh <span class="font-semibold text-slate-800">{{ $audit->audited_by_name }}</span></p>
                            </div>
                            @if (!$audit->needsFollowUp())
                                <span class="w-fit rounded-lg bg-slate-50 px-3 py-2 text-xs font-medium text-slate-500">Tidak perlu tindak lanjut</span>
                            @elseif ($audit->followUp)
                                <span class="w-fit rounded-lg bg-emerald-50 px-3 py-2 text-xs font-bold text-emerald-700">✓ Tindak lanjut selesai</span>
                            @else
                                <span class="w-fit rounded-lg bg-orange-50 px-3 py-2 text-xs font-bold text-orange-700">Action PIC diperlukan</span>
                            @endif
                        </div>

                        @if ($audit->needsFollowUp())
                            @php($fu = $audit->followUp)
                            @php($fuIsOld = old('_followup_audit') !== null && (int) old('_followup_audit') === $audit->id)
                            @php($canDeleteFollowUp = in_array(auth()->user()->role, ['ADMIN', 'KOORDINATOR WWD'], true))

                            @if ($fu)
                                <div class="mt-4 rounded-xl border border-emerald-100 bg-emerald-50/70 p-4" data-followup-view="{{ $audit->id }}">
                                    <div class="grid gap-3 text-sm sm:grid-cols-2">
                                        <div>
                                            <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Problem &amp; finding</p>
                                            <ul class="mt-2 space-y-2">
                                                @forelse ($fu->problems as $problem)
                                                    <li class="font-medium text-slate-800">
                                                        <div class="flex items-start gap-2">
                                                            <span class="mt-0.5 text-emerald-600">•</span>
                                                            <span>{{ $problem->problem }}</span>
                                                        </div>
                                                        @if ($problem->findings->isNotEmpty())
                                                            <ul class="mt-1 ml-5 space-y-0.5 text-xs font-normal text-slate-600">
                                                                @foreach ($problem->findings as $finding)
                                                                    <li>– {{ $finding->finding }}</li>
                                                                @endforeach
                                                            </ul>
                                                        @endif
                                                    </li>
                                                @empty
                                                    <li class="font-medium text-slate-800">{{ $fu->problem }}</li>
                                                @endforelse
                                            </ul>
                                        </div>
                                        <div>
                                            <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Tindakan dilakukan</p>
                                            <p class="mt-1 whitespace-pre-line text-slate-700">{{ $fu->action_taken }}</p>
                                        </div>
                                    </div>
                                    <div class="mt-3 flex flex-wrap items-center justify-between gap-2 border-t border-emerald-100 pt-3">
                                        <p class="text-xs text-slate-600">Ditindaklanjuti oleh <span class="font-semibold text-slate-800">{{ $fu->pic_name }}</span> · {{ $fu->actioned_at->format('d M Y, H:i') }}</p>
                                        <div class="flex flex-wrap gap-2">
                                            <button type="button" class="js-followup-edit-toggle rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-50" data-target="{{ $audit->id }}">Edit tindak lanjut</button>
                                            @if ($canDeleteFollowUp)
                                                <form method="POST" action="{{ route('oil-audits.follow-up.destroy', $audit) }}" onsubmit="return confirm('Hapus tindak lanjut ini beserta seluruh problem &amp; finding-nya?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button class="rounded-lg border border-red-200 bg-white px-3 py-1.5 text-xs font-semibold text-red-600 transition hover:bg-red-50">Hapus</button>
                                                </form>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                <form method="POST" action="{{ route('oil-audits.follow-up.update', $audit) }}" class="js-followup-form mt-4 rounded-xl border border-orange-200 bg-orange-50/70 p-4" data-followup-form="{{ $audit->id }}" {{ $fuIsOld ? '' : 'hidden' }}>
                                    @csrf
                                    @method('PUT')
                                    @include('oil-audits.partials.follow-up-fields', ['mode' => 'edit'])
                                </form>
                            @else
                                <form method="POST" action="{{ route('oil-audits.follow-up.store', $audit) }}" class="js-followup-form mt-4 rounded-xl border border-orange-200 bg-orange-50/70 p-4" data-followup-form="{{ $audit->id }}">
                                    @csrf
                                    @include('oil-audits.partials.follow-up-fields', ['mode' => 'create'])
                                </form>
                            @endif
                        @endif
                    </div>
                </article>
            @empty
                <div class="rounded-2xl border border-dashed border-slate-300 bg-white py-10 text-center text-sm text-slate-500">Belum ada audit oli untuk mesin ini.</div>
            @endforelse
        </div>

        <div class="mt-5">{{ $audits->links() }}</div>
    </section>

    @include('oil-audits.partials.follow-up-scripts')
@endsection
