@extends('layouts.app')

@php
    // Wide data table: use the full content width instead of max-w-7xl.
    $contentMaxWidth = 'max-w-full';
    $fullWidth = true;
    $canDeleteAudit = auth()->user()->isAdmin() || auth()->user()->isKoordinator();
    $historyUrl = fn ($audit) => route('oil-audits.history', [$audit->machine_number, 'from' => 'action', 'return' => request()->getQueryString() ?? '']);
@endphp

@section('title', 'Oil Audit Action')

@section('content')
    <div class="space-y-5">
        <x-ui.page-header title="Oil Audit Action" description="Setiap record audit oli area WWD, satu baris per audit, untuk monitoring tindak lanjut.">
            <x-slot:actions>
                <x-ui.button href="{{ route('oil-audits.scan') }}">Mulai Audit dengan QR</x-ui.button>
            </x-slot:actions>
        </x-ui.page-header>

        @if (session('success'))
            <x-ui.alert tone="success">{{ session('success') }}</x-ui.alert>
        @endif

        {{-- Open follow-ups first: the work this page exists for. --}}
        @if ($pendingAudits->isNotEmpty())
            <section class="rounded-card border border-warning/40 bg-warning-light p-4 sm:p-5" aria-labelledby="pending-heading">
                <div class="mb-3 flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 id="pending-heading" class="font-semibold text-text">Follow up belum selesai <span class="font-normal text-text-muted">({{ $summary['pending'] }})</span></h2>
                        <p class="mt-0.5 text-sm text-warning-text">Prioritaskan kondisi oli yang membutuhkan action.</p>
                    </div>
                    <a href="{{ route('oil-audits.report', ['follow_up' => 1]) }}" class="inline-flex min-h-11 items-center text-sm font-semibold text-primary underline-offset-2 hover:underline lg:min-h-0">Lihat semua</a>
                </div>
                <div class="grid grid-cols-1 gap-2 md:grid-cols-2">
                    @foreach ($pendingAudits as $audit)
                        @php($colors = $audit->conditionColor())
                        <a href="{{ $historyUrl($audit) }}" class="flex min-w-0 items-center justify-between gap-3 rounded-control border border-border bg-surface px-3 py-3 transition-colors hover:border-warning">
                            <div class="min-w-0">
                                <p class="truncate font-semibold text-text">{{ $audit->machine_number }}</p>
                                <p class="truncate text-xs text-text-muted">Dicek {{ $audit->audited_at->format('d M Y, H:i') }} · {{ $audit->audited_by_name }}</p>
                            </div>
                            <span class="shrink-0 wrap-anywhere rounded-full px-2.5 py-1 text-xs font-bold {{ $colors['badge'] }}">{{ $audit->conditionLabel() }}</span>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- Summary of the filtered records. --}}
        <dl class="card grid grid-cols-2 divide-border p-4 sm:grid-cols-4 sm:divide-x">
            <div class="px-1 py-2 sm:px-4">
                <dt class="text-xs text-text-muted">Total Audit Records</dt>
                <dd class="font-signage text-2xl font-semibold text-text">{{ $summary['total_audit'] }}</dd>
            </div>
            <div class="px-1 py-2 sm:px-4">
                <dt class="text-xs text-text-muted">Total Finding</dt>
                <dd class="font-signage text-2xl font-semibold text-danger">{{ $summary['total_finding'] }}</dd>
            </div>
            <div class="px-1 py-2 sm:px-4">
                <dt class="text-xs text-text-muted">Findings With Action Date</dt>
                <dd class="font-signage text-2xl font-semibold text-success">{{ $summary['findings_with_action'] }}</dd>
            </div>
            <div class="px-1 py-2 sm:px-4">
                <dt class="text-xs text-text-muted">Avg. Action Duration</dt>
                <dd class="font-signage text-2xl font-semibold text-text">{{ $summary['average_action_duration'] !== null ? $summary['average_action_duration'].' days' : '-' }}</dd>
            </div>
        </dl>

        {{-- Filters: plain GET, same parameter names. Multi-selects on the last row.
             On phones the eight fields fold away behind a toggle (open when a filter is active),
             so the list is not pushed three screens down; from md they are always shown. --}}
        @php($activeFilters = collect(request()->only(['search', 'date_from', 'date_to', 'year', 'pic', 'finding_status', 'month', 'condition']))->filter(fn ($v) => filled($v))->count())
        <div x-data="{ open: {{ $activeFilters ? 'true' : 'false' }} }" class="space-y-3">
        <x-ui.button variant="outline" class="w-full md:hidden" @click="open = !open" x-bind:aria-expanded="open.toString()" aria-controls="oa-filter-form">
            <span x-text="open ? 'Sembunyikan filter' : 'Tampilkan filter'">{{ $activeFilters ? 'Sembunyikan filter' : 'Tampilkan filter' }}</span>
            @if ($activeFilters)
                <x-ui.badge tone="primary">{{ $activeFilters }} aktif</x-ui.badge>
            @endif
        </x-ui.button>
        <form id="oa-filter-form" method="GET" class="card space-y-3 p-4 md:!block" x-show="open" x-cloak>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-[minmax(14rem,2fr)_repeat(4,minmax(0,1fr))]">
                <div class="grid gap-1.5 sm:col-span-2 lg:col-span-1">
                    <label for="oa-search" class="text-xs font-medium text-text-muted">Cari</label>
                    <input id="oa-search" name="search" value="{{ request('search') }}" placeholder="Nomor mesin, tipe, atau temuan" class="input lg:min-h-9">
                </div>
                <div class="grid gap-1.5">
                    <label for="oa-from" class="text-xs font-medium text-text-muted">Tanggal audit dari</label>
                    <input id="oa-from" type="date" name="date_from" value="{{ request('date_from') }}" class="input lg:min-h-9">
                </div>
                <div class="grid gap-1.5">
                    <label for="oa-to" class="text-xs font-medium text-text-muted">Sampai</label>
                    <input id="oa-to" type="date" name="date_to" value="{{ request('date_to') }}" class="input lg:min-h-9">
                </div>
                <div class="grid gap-1.5">
                    <label for="oa-year" class="text-xs font-medium text-text-muted">Tahun</label>
                    <select id="oa-year" name="year" class="select lg:min-h-9">
                        <option value="">Semua tahun</option>
                        @foreach (range(now()->year, now()->year - 5) as $y)
                            <option value="{{ $y }}" @selected((string) request('year') === (string) $y)>{{ $y }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="grid gap-1.5">
                    <label for="oa-pic" class="text-xs font-medium text-text-muted">PIC</label>
                    <select id="oa-pic" name="pic" class="select lg:min-h-9">
                        <option value="">Semua PIC</option>
                        @foreach ($pics as $p)
                            <option value="{{ $p }}" @selected(request('pic') === $p)>{{ $p }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end">
                <div class="grid gap-1.5 sm:w-52">
                    <label for="oa-finding-status" class="text-xs font-medium text-text-muted">Status temuan</label>
                    <select id="oa-finding-status" name="finding_status" class="select lg:min-h-9">
                        <option value="">Semua status temuan</option>
                        <option value="NO_FINDING" @selected(request('finding_status') === 'NO_FINDING')>No Finding</option>
                        <option value="OPEN" @selected(request('finding_status') === 'OPEN')>Open</option>
                        <option value="COMPLETED" @selected(request('finding_status') === 'COMPLETED')>Completed</option>
                    </select>
                </div>
                <div class="grid gap-1.5">
                    <span class="text-xs font-medium text-text-muted">Bulan</span>
                    <x-checkbox-dropdown name="month" label="Bulan" all-label="Semua bulan"
                        :options="collect(range(1, 12))->mapWithKeys(fn ($m) => [$m => \Carbon\Carbon::create(null, $m, 1)->format('F')])->all()"
                        :selected="collect((array) request('month'))->filter(fn ($m) => is_scalar($m) && ctype_digit((string) $m) && $m >= 1 && $m <= 12)->map(fn ($m) => (int) $m)->unique()->values()->all()" />
                </div>
                <div class="grid gap-1.5">
                    <span class="text-xs font-medium text-text-muted">Kondisi</span>
                    <x-checkbox-dropdown name="condition" label="Kondisi" all-label="Semua kondisi"
                        :options="\App\Models\OilAudit::CONDITION_LABELS"
                        :selected="collect((array) request('condition'))->filter(fn ($c) => is_string($c) && array_key_exists($c, \App\Models\OilAudit::CONDITION_LABELS))->values()->all()" />
                </div>
                <div class="flex gap-2 sm:ml-auto">
                    <x-ui.button type="submit" class="flex-1 sm:flex-none lg:min-h-9">Filter</x-ui.button>
                    <x-ui.button variant="ghost" href="{{ route('oil-audits.report') }}" class="lg:min-h-9">Reset</x-ui.button>
                </div>
            </div>
        </form>
        </div>

        @if ($audits->isEmpty())
            <x-ui.empty-state title="Tidak ada audit yang sesuai dengan filter."
                description="Longgarkan filter, atau reset untuk melihat semua audit oli area WWD.">
                <x-ui.button variant="outline" href="{{ route('oil-audits.report') }}">Reset filter</x-ui.button>
            </x-ui.empty-state>
        @else
            {{-- Desktop table --}}
            <div class="card hidden overflow-hidden md:block">
                <div class="relative overflow-x-auto">
                    <table class="data-table min-w-full">
                        <thead>
                            <tr>
                                <th scope="col">Machine</th>
                                <th scope="col">Audit Date</th>
                                <th scope="col">PIC</th>
                                <th scope="col">Condition</th>
                                <th scope="col">Finding</th>
                                <th scope="col">Follow-up Status</th>
                                {{-- Actions stay pinned on the right when the table scrolls sideways. --}}
                                <th scope="col" class="sticky right-0 bg-surface-sunken"><span class="sr-only">Action</span></th>
                            </tr>
                        </thead>
                        <tbody data-infinite-list="audits">
                            @foreach ($audits as $audit)
                                @php($colors = $audit->conditionColor())
                                <tr>
                                    <td class="whitespace-nowrap">
                                        <div class="font-semibold text-text">{{ $audit->machine_number }}</div>
                                        <div class="text-xs text-text-muted">{{ $audit->machine_type }} · {{ $audit->area }}</div>
                                    </td>
                                    <td class="whitespace-nowrap">{{ $audit->audited_at->format('d-m-Y') }}</td>
                                    <td class="text-text-muted">{{ $audit->audited_by_name ?: '-' }}</td>
                                    <td><span class="whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-semibold {{ $colors['badge'] }}">{{ $audit->conditionLabel() }}</span></td>
                                    <td class="text-text-muted">
                                        @if (! $audit->needsFollowUp())
                                            No Finding
                                        @elseif ($audit->followUp)
                                            <span class="text-text">{{ $audit->followUp->problems->pluck('problem')->implode(', ') ?: $audit->conditionLabel() }}</span>
                                        @else
                                            <span class="text-text">{{ $audit->conditionLabel() }}</span> <span class="text-warning-text">(Pending)</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if (! $audit->needsFollowUp())
                                            <span class="text-text-muted">-</span>
                                        @elseif ($audit->followUp)
                                            <x-ui.badge tone="success">Completed</x-ui.badge>
                                        @else
                                            <x-ui.badge tone="warning">Open</x-ui.badge>
                                        @endif
                                    </td>
                                    <td class="sticky right-0 whitespace-nowrap bg-surface shadow-[-10px_0_10px_-10px_rgb(15_23_42/0.25)]">
                                        <div class="flex items-center justify-end gap-2">
                                            <x-ui.button size="sm" variant="outline" href="{{ $historyUrl($audit) }}">View</x-ui.button>
                                            @if ($canDeleteAudit)
                                                <form method="POST" action="{{ route('oil-audits.destroy', $audit) }}" onsubmit="return confirm('Hapus temuan audit oli mesin {{ $audit->machine_number }} ({{ $audit->audited_at->format('d-m-Y') }})? Tindak lanjutnya juga akan terhapus.')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <x-ui.button type="submit" size="sm" variant="ghost" class="text-danger hover:bg-danger-light hover:text-danger-hover">Delete</x-ui.button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Phone: one card per audit, with its own infinite scroll (resources/js/oil-audits/action.js). --}}
            <div id="oil-audit-action-mobile-list" class="space-y-3 md:hidden"
                data-load-more-url="{{ route('oil-audits.report.more', request()->except('page')) }}"
                data-has-more="{{ $audits->hasMorePages() ? '1' : '0' }}"
                data-next-page="{{ $audits->currentPage() + 1 }}">
                @foreach ($audits as $audit)
                    @include('oil-audits.partials.action-mobile-card', ['audit' => $audit, 'canDeleteAudit' => $canDeleteAudit])
                @endforeach
            </div>

            <div class="md:hidden">
                <div id="oil-audit-action-mobile-sentinel" aria-hidden="true"></div>
                <p id="oil-audit-action-mobile-loading" class="hidden py-4 text-center text-sm text-text-muted">Memuat data…</p>
                <p id="oil-audit-action-mobile-end" class="hidden py-4 text-center text-sm text-text-muted">Semua data sudah ditampilkan.</p>
                <div id="oil-audit-action-mobile-error" class="hidden py-4 text-center text-sm text-danger" role="alert">
                    Gagal memuat data berikutnya.
                    <button type="button" id="oil-audit-action-mobile-retry" class="inline-flex min-h-11 items-center font-semibold underline">Coba lagi</button>
                </div>
            </div>

            {{-- Desktop: shared infinite scroll. Each sentinel is display:none on the other breakpoint. --}}
            <div class="hidden md:block"><x-infinite-scroll :paginator="$audits" key="audits" /></div>
        @endif
    </div>

    @if ($promptStart ?? false)
        {{-- Daily Start prompt: PIC only, once per business day. NO (or Escape) dismisses it;
             the Action / follow-up workflow is never blocked. --}}
        <div id="oil-audit-action-daily-prompt" data-ui-modal role="dialog" aria-modal="true" aria-labelledby="oil-audit-action-daily-prompt-title"
            class="fixed inset-0 z-50 flex items-center justify-center bg-neutral-900/40 p-4">
            <div class="w-full max-w-sm rounded-card border border-border bg-surface p-6 text-center shadow-popover">
                <h2 id="oil-audit-action-daily-prompt-title" class="text-lg font-semibold text-text">DO YOU WANT TO START OIL AUDIT ACTION?</h2>
                <p class="mt-1 text-sm text-text-muted">Waktu mulai dicatat sebagai aktivitas hari ini.</p>
                <div class="mt-5 flex justify-center gap-2">
                    <x-ui.button variant="outline" id="oil-audit-action-prompt-no" class="min-w-24">NO</x-ui.button>
                    <x-ui.button id="oil-audit-action-prompt-yes" class="min-w-24">YES</x-ui.button>
                </div>
            </div>
        </div>

        <x-ui.modal id="oil-audit-action-start-modal" title="Start Oil Audit Action Activity" size="sm">
            <form method="POST" action="{{ route('oil-audits.report.start-daily') }}" class="mt-5 space-y-4">
                @csrf
                <x-ui.field label="Start date & time" for="oil-audit-action-start-input" required>
                    <input type="datetime-local" name="started_at" id="oil-audit-action-start-input" required class="input">
                </x-ui.field>
                <div class="flex justify-end gap-2 pt-2">
                    <x-ui.button variant="ghost" id="oil-audit-action-start-cancel">Cancel</x-ui.button>
                    <x-ui.button type="submit">START</x-ui.button>
                </div>
            </form>
        </x-ui.modal>

        <script>
            (function () {
                const prompt = document.getElementById('oil-audit-action-daily-prompt');
                const startModal = document.getElementById('oil-audit-action-start-modal');
                const input = document.getElementById('oil-audit-action-start-input');

                function nowLocal() {
                    const d = new Date();
                    d.setMinutes(d.getMinutes() - d.getTimezoneOffset());
                    return d.toISOString().slice(0, 16);
                }

                function show(el) { el.classList.remove('hidden'); el.classList.add('flex'); }
                function hide(el) { el.classList.add('hidden'); el.classList.remove('flex'); }

                document.getElementById('oil-audit-action-prompt-no').addEventListener('click', function () {
                    hide(prompt);
                });

                document.getElementById('oil-audit-action-prompt-yes').addEventListener('click', function () {
                    hide(prompt);
                    input.value = nowLocal();
                    show(startModal);
                });

                document.getElementById('oil-audit-action-start-cancel').addEventListener('click', function () {
                    hide(startModal);
                    show(prompt);
                });
            })();
        </script>
    @endif

    @include('partials.activity-conflict-modal')
@endsection
