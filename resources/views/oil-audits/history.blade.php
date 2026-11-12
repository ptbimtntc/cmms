@extends('layouts.app')

@php
    $hideSidebar = true;
    $hideTopbar = true;
    $fullWidth = true;
    $contentMaxWidth = 'max-w-4xl';
@endphp

@section('title', 'Riwayat Audit Oli')

@section('content')
    @php
        $latestColors = $latestAudit?->conditionColor();
        // Bar height grows with severity, so the strip reads as a trend even without colour.
        $barHeights = [
            'OKE' => 'h-7',
            'PANTAU' => 'h-10',
            'OLI_KERUH' => 'h-12',
            'HAMPIR_GARIS' => 'h-14',
            'GLASS_BUREM' => 'h-16',
            'PAS_GARIS' => 'h-18',
            'KRITIS' => 'h-20',
        ];
        $canDeleteFollowUp = auth()->user()->isAdmin() || auth()->user()->isKoordinator();
        $viewOnly = auth()->user()->isSupervisor();
    @endphp

    <div class="space-y-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <x-ui.button variant="ghost" href="{{ $backUrl }}" class="-ml-2">{{ $backLabel }}</x-ui.button>
            <x-brand-logo :framed="false" class="h-6 w-auto" aria-hidden="true" />
        </div>

        @if (session('success'))
            <x-ui.alert tone="success">{{ session('success') }}</x-ui.alert>
        @endif
        @if (session('warning'))
            <x-ui.alert tone="warning">{{ session('warning') }}</x-ui.alert>
        @endif

        <section class="card overflow-hidden">
            <div class="flex flex-col gap-4 border-b border-border p-5 sm:flex-row sm:items-start sm:justify-between sm:p-6">
                <div>
                    <p class="text-sm font-medium text-text-muted">Riwayat audit oli</p>
                    <h1 class="mt-1 font-signage text-4xl font-semibold leading-none text-text">{{ $machine->machine_number }}</h1>
                    <p class="mt-2 text-sm text-text-muted">{{ $machine->machine_type }} · {{ $machine->area }}@if ($machine->description) · {{ $machine->description }}@endif</p>
                </div>
                @if ($latestAudit)
                    <div class="w-full rounded-card px-4 py-3 text-center sm:w-auto {{ $latestColors['badge'] }}">
                        <p class="text-xs font-medium opacity-90">Kondisi terakhir</p>
                        <p class="mt-0.5 text-lg font-bold">{{ $latestAudit->conditionLabel() }}</p>
                    </div>
                @else
                    <div class="w-full rounded-card bg-surface-muted px-4 py-3 text-center text-text-muted sm:w-auto">
                        <p class="text-xs">Kondisi terakhir</p>
                        <p class="mt-0.5 text-lg font-bold text-text">Belum ada audit</p>
                    </div>
                @endif
            </div>

            <div class="p-5 sm:p-6">
                <div class="mb-3 flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-sm font-semibold text-text">Riwayat kondisi</h2>
                        <p class="mt-0.5 text-xs text-text-muted">Kiri adalah pengecekan lama, kanan adalah yang terbaru. Makin tinggi batang, makin buruk kondisinya.</p>
                    </div>
                    <span class="shrink-0 text-xs font-medium text-text-muted">{{ $machine->oilAudits()->count() }} audit</span>
                </div>

                @if ($recentAudits->isNotEmpty())
                    <div class="overflow-x-auto pb-2">
                        <ol class="flex min-w-[320px] items-end justify-end gap-1.5 border-b border-border pt-4 sm:min-w-[520px]" aria-label="Riwayat kondisi oli">
                            @foreach ($recentAudits as $audit)
                                @php($colors = $audit->conditionColor())
                                {{-- max-w keeps bars slim when there are only a few audits; newest stays on the right. --}}
                                <li class="group flex min-w-0 max-w-14 flex-1 flex-col items-center justify-end">
                                    <span class="mb-2 hidden rounded-control bg-neutral-900 px-2 py-1 text-[11px] font-semibold text-white group-hover:block" aria-hidden="true">{{ $audit->conditionLabel() }}</span>
                                    <div class="min-h-7 w-full rounded-t-lg {{ $barHeights[$audit->condition] ?? 'h-7' }} {{ $colors['bar'] }}" title="{{ $audit->conditionLabel() }}" aria-hidden="true"></div>
                                    <p class="mt-2 whitespace-nowrap text-[11px] text-text-muted">{{ $audit->audited_at->format('d M') }}<span class="sr-only">: {{ $audit->conditionLabel() }}</span></p>
                                </li>
                            @endforeach
                        </ol>
                    </div>
                @else
                    <x-ui.empty-state title="Belum ada riwayat audit oli untuk mesin ini." description="Riwayat terisi setiap kali mesin ini di-scan di Audit Oli." />
                @endif
            </div>
        </section>

        <section aria-labelledby="detail-heading">
            <div class="mb-4">
                <h2 id="detail-heading" class="font-signage text-2xl font-semibold text-text">Detail pengecekan</h2>
                <p class="mt-1 text-sm text-text-muted">Setiap kondisi yang perlu action ditampilkan jelas agar tidak terlewat.</p>
            </div>

            {{-- Timeline: the dot repeats the condition colour; the badge says it in words. --}}
            <div class="relative ml-2 border-l-2 border-border pl-6 sm:ml-3" data-infinite-list="audits">
                @forelse ($audits as $audit)
                    @php($colors = $audit->conditionColor())
                    <article class="relative mb-4">
                        <span class="absolute -left-[33px] top-5 h-4 w-4 rounded-full border-2 border-surface {{ $colors['dot'] }}" aria-hidden="true"></span>
                        <div class="card p-4 sm:p-5">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="rounded-full px-2.5 py-1 text-xs font-bold {{ $colors['badge'] }}">{{ $audit->conditionLabel() }}</span>
                                        <time class="text-sm font-semibold text-text">{{ $audit->audited_at->format('d M Y, H:i') }}</time>
                                    </div>
                                    <p class="mt-2 text-sm text-text-muted">Dicek oleh <span class="font-semibold text-text">{{ $audit->audited_by_name }}</span></p>
                                </div>
                                @if (! $audit->needsFollowUp())
                                    <x-ui.badge>Tidak perlu tindak lanjut</x-ui.badge>
                                @elseif ($audit->followUp)
                                    <x-ui.badge tone="success">Tindak lanjut selesai</x-ui.badge>
                                @else
                                    <x-ui.badge tone="warning">Action PIC diperlukan</x-ui.badge>
                                @endif
                            </div>

                            @if ($audit->needsFollowUp())
                                @php($fu = $audit->followUp)
                                @php($fuIsOld = old('_followup_audit') !== null && (int) old('_followup_audit') === $audit->id)

                                @if ($fu)
                                    <div class="mt-4 rounded-control border border-success/30 bg-success-light p-4" data-followup-view="{{ $audit->id }}">
                                        <div class="grid gap-4 text-sm sm:grid-cols-2">
                                            <div>
                                                <p class="text-xs font-semibold text-success-hover">Problem &amp; finding</p>
                                                <ul class="mt-2 space-y-2">
                                                    @forelse ($fu->problems as $problem)
                                                        <li class="font-medium text-text">
                                                            {{ $problem->problem }}
                                                            @if ($problem->findings->isNotEmpty())
                                                                <ul class="mt-1 list-disc space-y-0.5 pl-5 text-sm font-normal text-text-muted">
                                                                    @foreach ($problem->findings as $finding)
                                                                        <li>{{ $finding->finding }}</li>
                                                                    @endforeach
                                                                </ul>
                                                            @endif
                                                        </li>
                                                    @empty
                                                        <li class="font-medium text-text">{{ $fu->problem }}</li>
                                                    @endforelse
                                                </ul>
                                            </div>
                                            <div>
                                                <p class="text-xs font-semibold text-success-hover">Tindakan dilakukan</p>
                                                <p class="mt-1 whitespace-pre-line text-text">{{ $fu->action_taken }}</p>
                                            </div>
                                        </div>
                                        <div class="mt-3 flex flex-wrap items-center justify-between gap-2 border-t border-success/20 pt-3">
                                            <p class="text-sm text-text-muted">Ditindaklanjuti oleh <span class="font-semibold text-text">{{ $fu->pic_name }}</span> · {{ $fu->actioned_at->format('d M Y, H:i') }}</p>
                                            <div class="flex flex-wrap gap-2">
                                                @unless ($viewOnly)
                                                    <button type="button" class="js-followup-edit-toggle btn btn-outline btn-sm min-h-11 lg:min-h-9" data-target="{{ $audit->id }}">Edit tindak lanjut</button>
                                                @endunless
                                                @if ($canDeleteFollowUp)
                                                    <form method="POST" action="{{ route('oil-audits.follow-up.destroy', $audit) }}" onsubmit="return confirm('Hapus tindak lanjut ini beserta seluruh problem &amp; finding-nya?');">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button class="btn btn-ghost btn-sm min-h-11 text-danger hover:bg-danger-light hover:text-danger-hover lg:min-h-9">Hapus</button>
                                                    </form>
                                                @endif
                                            </div>
                                        </div>
                                    </div>

                                    <form method="POST" action="{{ route('oil-audits.follow-up.update', $audit) }}" class="js-followup-form mt-4 rounded-control border border-warning/40 bg-warning-light p-4" data-followup-form="{{ $audit->id }}" {{ $fuIsOld && ! $viewOnly ? '' : 'hidden' }}>
                                        @csrf
                                        @method('PUT')
                                        @include('oil-audits.partials.follow-up-fields', ['mode' => 'edit'])
                                    </form>
                                @else
                                    <form method="POST" action="{{ route('oil-audits.follow-up.store', $audit) }}" @class(['js-followup-form mt-4 rounded-control border border-warning/40 bg-warning-light p-4', 'pointer-events-none opacity-50 grayscale' => $viewOnly]) data-followup-form="{{ $audit->id }}">
                                        @csrf
                                        <fieldset @disabled($viewOnly) class="min-w-0">
                                            @include('oil-audits.partials.follow-up-fields', ['mode' => 'create'])
                                        </fieldset>
                                    </form>
                                    @if ($canDeleteFollowUp)
                                        <form id="delete-audit-{{ $audit->id }}" method="POST" action="{{ route('oil-audits.destroy', $audit) }}" hidden>
                                            @csrf
                                            @method('DELETE')
                                        </form>
                                    @endif
                                @endif
                            @endif
                        </div>
                    </article>
                @empty
                    <x-ui.empty-state title="Belum ada audit oli untuk mesin ini." description="Audit pertama muncul di sini setelah mesin di-scan di Audit Oli." />
                @endforelse
            </div>

            <div class="mt-5"><x-infinite-scroll :paginator="$audits" key="audits" /></div>
        </section>
    </div>

    @include('oil-audits.partials.follow-up-scripts')
@endsection
