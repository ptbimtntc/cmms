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
        <x-ui.page-header title="Oil Audit Action" description="Every WWD oil audit record, one row per audit, for follow-up monitoring.">
            <x-slot:actions>
                <x-ui.button variant="outline" href="{{ route('reports.oil-audit') }}">View Report</x-ui.button>
                <x-ui.button href="{{ route('oil-audits.scan') }}">Start an audit with QR</x-ui.button>
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
                        <h2 id="pending-heading" class="font-semibold text-text">Open follow-ups <span class="font-normal text-text-muted">({{ $summary['pending'] }})</span></h2>
                        <p class="mt-0.5 text-sm text-warning-text">Oil conditions that need an action come first.</p>
                    </div>
                    <a href="{{ route('oil-audits.report', ['follow_up' => 1]) }}" class="inline-flex min-h-11 items-center text-sm font-semibold text-primary underline-offset-2 hover:underline lg:min-h-0">See all</a>
                </div>
                <div class="grid grid-cols-1 gap-2 md:grid-cols-2">
                    @foreach ($pendingAudits as $audit)
                        @php($colors = $audit->conditionColor())
                        <a href="{{ $historyUrl($audit) }}" class="flex min-w-0 items-center justify-between gap-3 rounded-control border border-border bg-surface px-3 py-3 transition-colors hover:border-warning">
                            <div class="min-w-0">
                                <p class="truncate font-semibold text-text">{{ $audit->machine_number }}</p>
                                <p class="truncate text-xs text-text-muted">Checked {{ $audit->audited_at->format('d M Y, H:i') }} · {{ $audit->audited_by_name }}</p>
                            </div>
                            <span class="shrink-0 wrap-anywhere rounded-full px-2.5 py-1 text-xs font-bold {{ $colors['badge'] }}">{{ $audit->conditionLabel() }}</span>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- Machines due an oil refill: the same rule as the reminder on the audit entry page. --}}
        @if ($refillDue->isNotEmpty())
            {{-- Collapsed by default so a long list does not push the table down. --}}
            <section class="card px-4 py-1 sm:px-5" aria-labelledby="refill-heading" x-data="{ open: false }">
                <h2 id="refill-heading">
                    <button type="button" class="flex min-h-11 w-full items-center justify-between gap-3 text-left font-semibold text-text"
                        @click="open = !open" x-bind:aria-expanded="open.toString()" aria-expanded="false" aria-controls="refill-list">
                        <span>Oil refill due <span class="font-normal text-text-muted">({{ $refillDue->count() }})</span></span>
                        <svg class="size-4 shrink-0 text-text-muted transition-transform" :class="open ? '' : '-rotate-90'" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path d="M5 7.5h10L10 13z" />
                        </svg>
                    </button>
                </h2>
                <div id="refill-list" class="pb-4" x-show="open" x-cloak>
                <p class="mb-3 text-sm text-text-muted">Pantau {{ \App\Models\OilAudit::PANTAU_REFILL_REMINDER_AT }} times or more in a row with no action. Refill the oil, then record the action.</p>
                <div class="grid grid-cols-1 gap-2 md:grid-cols-2">
                    @foreach ($refillDue as $machine)
                        <a href="{{ $historyUrl($machine) }}" class="flex min-w-0 items-center justify-between gap-3 rounded-control border border-border bg-surface px-3 py-3 transition-colors hover:border-border-strong">
                            <div class="min-w-0">
                                <p class="truncate font-semibold text-text">{{ $machine->machine_number }}</p>
                                <p class="truncate text-xs text-text-muted">Last checked {{ \Carbon\Carbon::parse($machine->last_audited_at)->format('d M Y') }}</p>
                            </div>
                            <x-ui.badge tone="info" class="shrink-0">Pantau &times;{{ $machine->streak }}</x-ui.badge>
                        </a>
                    @endforeach
                </div>
                </div>
            </section>
        @endif

        {{-- Summary of the filtered records. --}}
        <dl class="card grid grid-cols-3 divide-border p-4 sm:divide-x">
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
        </dl>

        {{-- Avg. Action Duration per condition. Includes PANTAU (oil-refill-only
             actions), unlike the Total Finding / Findings With Action Date stats
             above. Conditions with no completed action yet are omitted, not
             shown as '-'. --}}
        <section class="card p-4 sm:p-5" aria-labelledby="oa-duration-heading">
            <div class="mb-3">
                <h2 id="oa-duration-heading" class="font-semibold text-text">Avg. Action Duration by Condition</h2>
                <p class="mt-0.5 text-xs text-text-muted">Average days between the audit and its recorded action.</p>
            </div>
            @if ($summary['action_duration_by_condition']->isEmpty())
                <p class="text-sm text-text-muted">No completed action in this filter scope yet.</p>
            @else
                <dl class="flex gap-3 overflow-x-auto pb-1">
                    @foreach ($summary['action_duration_by_condition'] as $row)
                        <div class="min-w-[140px] flex-1 rounded-control border border-border px-3 py-2">
                            <dt class="text-xs text-text-muted">{{ $row['label'] }}</dt>
                            <dd class="font-signage text-xl font-semibold text-text">{{ $row['value'] }} days</dd>
                        </div>
                    @endforeach
                </dl>
            @endif
        </section>

        {{-- Filters: plain GET, same parameter names. Grouped by what each field filters on:
             General (the audit record itself), Audit (audit date and auditor, `pic`) and
             Action (follow-up date and its PIC, `action_pic`). On phones the form folds away
             behind a toggle (open when a filter is active); from md it is always shown. --}}
        @php($activeFilters = collect(request()->only(['search', 'condition', 'finding_status', 'date_from', 'date_to', 'year', 'month', 'pic', 'action_date_from', 'action_date_to', 'action_year', 'action_month', 'action_pic']))->filter(fn ($v) => filled($v))->count())
        @php($groupRow = 'grid gap-2 py-3 first:pt-0 last:pb-0 lg:grid-cols-[4.5rem_1fr] lg:items-end lg:gap-4')
        @php($groupLabel = 'text-xs font-semibold text-text lg:pb-2.5')
        @php($fieldLabel = 'text-xs font-medium text-text-muted')
        @php($fields = 'flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end')
        {{-- Below md the Audit and Action groups start collapsed behind a disclosure
             button, unless one of their own filters is active; from md they are always open. --}}
        @php($auditOpen = collect(request()->only(['date_from', 'date_to', 'year', 'month', 'pic']))->filter(fn ($v) => filled($v))->isNotEmpty())
        @php($actionOpen = collect(request()->only(['action_date_from', 'action_date_to', 'action_year', 'action_month', 'action_pic']))->filter(fn ($v) => filled($v))->isNotEmpty())
        <div x-data="{ open: {{ $activeFilters ? 'true' : 'false' }} }" class="space-y-3">
        <x-ui.button variant="outline" class="w-full md:hidden" @click="open = !open" x-bind:aria-expanded="open.toString()" aria-controls="oa-filter-form">
            <span x-text="open ? 'Hide filters' : 'Show filters'">{{ $activeFilters ? 'Hide filters' : 'Show filters' }}</span>
            @if ($activeFilters)
                <x-ui.badge tone="primary">{{ $activeFilters }} active</x-ui.badge>
            @endif
        </x-ui.button>
        <form id="oa-filter-form" method="GET" class="card divide-y divide-border p-4 md:!block" x-show="open" x-cloak>
            <div role="group" aria-labelledby="oa-group-general" class="{{ $groupRow }}">
                <span id="oa-group-general" class="{{ $groupLabel }}">General</span>
                <div class="{{ $fields }}">
                    <div class="grid gap-1.5 sm:min-w-64 sm:flex-1">
                        <label for="oa-search" class="{{ $fieldLabel }}">Search</label>
                        <input id="oa-search" name="search" value="{{ request('search') }}" placeholder="Machine number, type, or finding" class="input lg:min-h-9">
                    </div>
                    <div class="grid gap-1.5">
                        <span class="{{ $fieldLabel }}">Condition</span>
                        <x-checkbox-dropdown name="condition" label="Condition" all-label="All conditions"
                            :options="\App\Models\OilAudit::CONDITION_LABELS"
                            :selected="collect((array) request('condition'))->filter(fn ($c) => is_string($c) && array_key_exists($c, \App\Models\OilAudit::CONDITION_LABELS))->values()->all()" />
                    </div>
                    <div class="grid gap-1.5 sm:w-48">
                        <label for="oa-finding-status" class="{{ $fieldLabel }}">Finding status</label>
                        <select id="oa-finding-status" name="finding_status" class="select lg:min-h-9">
                            <option value="">All finding statuses</option>
                            <option value="NO_FINDING" @selected(request('finding_status') === 'NO_FINDING')>No Finding</option>
                            <option value="OPEN" @selected(request('finding_status') === 'OPEN')>Open</option>
                            <option value="COMPLETED" @selected(request('finding_status') === 'COMPLETED')>Completed</option>
                        </select>
                    </div>
                </div>
            </div>

            <div role="group" aria-labelledby="oa-group-audit" class="{{ $groupRow }}" x-data="{ open: {{ $auditOpen ? 'true' : 'false' }} }">
                <div class="flex items-center justify-between lg:pb-2.5">
                    <span id="oa-group-audit" class="text-xs font-semibold text-text">Audit</span>
                    <button type="button" class="-my-3 -mr-3 grid size-11 place-items-center text-text-muted md:hidden" @click="open = !open"
                        x-bind:aria-expanded="open.toString()" aria-controls="oa-fields-audit" aria-label="Audit filters">
                        <svg class="size-4 transition-transform" :class="open ? '' : '-rotate-90'" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path d="M5 7.5h10L10 13z" />
                        </svg>
                    </button>
                </div>
                <div id="oa-fields-audit" class="{{ $fields }} md:!flex" x-show="open">
                    <div class="grid gap-1.5 sm:w-40">
                        <label for="oa-from" class="{{ $fieldLabel }}">From</label>
                        <input id="oa-from" type="date" name="date_from" value="{{ request('date_from') }}" class="input lg:min-h-9">
                    </div>
                    <div class="grid gap-1.5 sm:w-40">
                        <label for="oa-to" class="{{ $fieldLabel }}">To</label>
                        <input id="oa-to" type="date" name="date_to" value="{{ request('date_to') }}" class="input lg:min-h-9">
                    </div>
                    <div class="grid gap-1.5 sm:w-32">
                        <label for="oa-year" class="{{ $fieldLabel }}">Year</label>
                        <select id="oa-year" name="year" class="select lg:min-h-9">
                            <option value="">All years</option>
                            @foreach (range(now()->year, now()->year - 5) as $y)
                                <option value="{{ $y }}" @selected((string) request('year') === (string) $y)>{{ $y }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="grid gap-1.5">
                        <span class="{{ $fieldLabel }}">Month</span>
                        <x-checkbox-dropdown name="month" label="Month" all-label="All months"
                            :options="collect(range(1, 12))->mapWithKeys(fn ($m) => [$m => \Carbon\Carbon::create(null, $m, 1)->format('F')])->all()"
                            :selected="collect((array) request('month'))->filter(fn ($m) => is_scalar($m) && ctype_digit((string) $m) && $m >= 1 && $m <= 12)->map(fn ($m) => (int) $m)->unique()->values()->all()" />
                    </div>
                    <div class="grid gap-1.5 sm:w-48">
                        <label for="oa-pic" class="{{ $fieldLabel }}">Auditor</label>
                        <select id="oa-pic" name="pic" class="select lg:min-h-9">
                            <option value="">All auditors</option>
                            @foreach ($pics as $p)
                                <option value="{{ $p }}" @selected(request('pic') === $p)>{{ $p }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            <div role="group" aria-labelledby="oa-group-action" class="{{ $groupRow }}" x-data="{ open: {{ $actionOpen ? 'true' : 'false' }} }">
                <div class="flex items-center justify-between lg:pb-2.5">
                    <span id="oa-group-action" class="text-xs font-semibold text-text">Action</span>
                    <button type="button" class="-my-3 -mr-3 grid size-11 place-items-center text-text-muted md:hidden" @click="open = !open"
                        x-bind:aria-expanded="open.toString()" aria-controls="oa-fields-action" aria-label="Action filters">
                        <svg class="size-4 transition-transform" :class="open ? '' : '-rotate-90'" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path d="M5 7.5h10L10 13z" />
                        </svg>
                    </button>
                </div>
                {{-- Filter/Reset sit outside the collapsible fields so they stay reachable on phones. --}}
                <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                <div id="oa-fields-action" class="{{ $fields }} sm:flex-1 md:!flex" x-show="open">
                    <div class="grid gap-1.5 sm:w-40">
                        <label for="oa-action-from" class="{{ $fieldLabel }}">From</label>
                        <input id="oa-action-from" type="date" name="action_date_from" value="{{ request('action_date_from') }}" class="input lg:min-h-9">
                    </div>
                    <div class="grid gap-1.5 sm:w-40">
                        <label for="oa-action-to" class="{{ $fieldLabel }}">To</label>
                        <input id="oa-action-to" type="date" name="action_date_to" value="{{ request('action_date_to') }}" class="input lg:min-h-9">
                    </div>
                    <div class="grid gap-1.5 sm:w-32">
                        <label for="oa-action-year" class="{{ $fieldLabel }}">Year</label>
                        <select id="oa-action-year" name="action_year" class="select lg:min-h-9">
                            <option value="">All years</option>
                            @foreach (range(now()->year, now()->year - 5) as $y)
                                <option value="{{ $y }}" @selected((string) request('action_year') === (string) $y)>{{ $y }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="grid gap-1.5">
                        <span class="{{ $fieldLabel }}">Month</span>
                        <x-checkbox-dropdown name="action_month" label="Month" all-label="All months"
                            :options="collect(range(1, 12))->mapWithKeys(fn ($m) => [$m => \Carbon\Carbon::create(null, $m, 1)->format('F')])->all()"
                            :selected="collect((array) request('action_month'))->filter(fn ($m) => is_scalar($m) && ctype_digit((string) $m) && $m >= 1 && $m <= 12)->map(fn ($m) => (int) $m)->unique()->values()->all()" />
                    </div>
                    <div class="grid gap-1.5 sm:w-48">
                        <label for="oa-action-pic" class="{{ $fieldLabel }}">PIC</label>
                        <select id="oa-action-pic" name="action_pic" class="select lg:min-h-9">
                            <option value="">All PICs</option>
                            @foreach ($actionPics as $p)
                                <option value="{{ $p }}" @selected(request('action_pic') === $p)>{{ $p }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="flex shrink-0 gap-2 sm:ml-auto">
                    <x-ui.button type="submit" class="flex-1 sm:flex-none lg:min-h-9">Filter</x-ui.button>
                    <x-ui.button variant="ghost" href="{{ route('oil-audits.report') }}" class="lg:min-h-9">Reset</x-ui.button>
                </div>
                </div>
            </div>
        </form>
        </div>

        @if ($audits->isEmpty())
            <x-ui.empty-state title="No audit matches these filters."
                description="Loosen the filters, or reset them to see every WWD oil audit.">
                <x-ui.button variant="outline" href="{{ route('oil-audits.report') }}">Reset filters</x-ui.button>
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
                                <th scope="col">Auditor</th>
                                <th scope="col">Condition</th>
                                <th scope="col">Finding</th>
                                <th scope="col">Follow-up Status</th>
                                <th scope="col">Action PIC</th>
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
                                    <td class="text-text-muted">{{ $audit->followUp?->pic_name ?: '-' }}</td>
                                    <td class="sticky right-0 whitespace-nowrap bg-surface shadow-[-10px_0_10px_-10px_rgb(15_23_42/0.25)]">
                                        <div class="flex items-center justify-end gap-2">
                                            <x-ui.button size="sm" variant="outline" href="{{ $historyUrl($audit) }}">View</x-ui.button>
                                            @if ($canDeleteAudit)
                                                <form method="POST" action="{{ route('oil-audits.destroy', $audit) }}" onsubmit="return confirm('Delete the oil audit for machine {{ $audit->machine_number }} ({{ $audit->audited_at->format('d-m-Y') }})? Its follow-up is deleted too.')">
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
                <p id="oil-audit-action-mobile-end" class="hidden py-4 text-center text-sm text-text-muted">Everything is loaded.</p>
                <div id="oil-audit-action-mobile-error" class="hidden py-4 text-center text-sm text-danger" role="alert">
                    Could not load the next batch.
                    <button type="button" id="oil-audit-action-mobile-retry" class="inline-flex min-h-11 items-center font-semibold underline">Try again</button>
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
                <p class="mt-1 text-sm text-text-muted">The start time is recorded as today's activity.</p>
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
