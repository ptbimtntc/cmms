@extends('layouts.app')

@section('title', 'Machine History')

@php
    $fullWidth = true;

    // Opened from Report Machine's View button (from=report-machine): Back
    // returns to that report with its filters (return_url, only trusted
    // when it points at the report itself), and the context is carried on
    // to the PM detail pages. Otherwise unchanged default behavior.
    $cameFromReportMachine = auth()->check() && request()->query('from') === 'report-machine';
    $reportMachineReturnUrl = request()->query('return_url');
    $reportMachineReturnUrl = is_string($reportMachineReturnUrl) && str_starts_with($reportMachineReturnUrl, route('reports.machine'))
        ? $reportMachineReturnUrl
        : route('reports.machine');
    $detailContext = $cameFromReportMachine
        ? ['from' => 'report-machine', 'return_url' => $reportMachineReturnUrl]
        : [];

    // Opened directly from Machine History's own list (not from a report):
    // Back returns to that list at the same page/filters it was opened
    // from (return_url), instead of always resetting to page 1.
    $listReturnUrl = request()->query('return_url');
    $listReturnUrl = is_string($listReturnUrl) && str_starts_with($listReturnUrl, route('machine-history.index'))
        ? $listReturnUrl
        : route('machine-history.index');
@endphp

@section('content')
    @php
        $backRoute = auth()->check() && auth()->user()->role !== 'guest'
            ? ($cameFromReportMachine ? $reportMachineReturnUrl : $listReturnUrl)
            : route('qr.scan');
        $isHourCycle = $machine->pm_cycle_unit && strtolower($machine->pm_cycle_unit) === 'hour';
        $shownLastPm = $lastPmOverride ? $lastPmOverride->override_date : $lastPm;
    @endphp

    <div class="space-y-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <x-ui.button variant="ghost" href="{{ $backRoute }}" class="-ml-2">Kembali</x-ui.button>
            @guest
                <x-brand-logo :framed="false" class="h-6 w-auto" aria-hidden="true" />
            @endguest
        </div>

        <section class="card overflow-hidden">
            <div class="border-b border-border p-5 sm:p-6">
                <p class="text-sm font-medium text-text-muted">Machine History</p>
                <h1 class="mt-1 break-words font-signage text-4xl font-semibold leading-none text-text">{{ $machine->machine_number }}</h1>
                <p class="mt-2 text-sm text-text-muted">{{ $machine->machine_type }} · {{ $machine->area }}</p>
            </div>
            {{-- Next PM is what people open this page for; Last PM explains it. --}}
            <dl class="grid grid-cols-2 gap-px bg-border sm:grid-cols-3">
                <div class="bg-surface p-4 sm:p-5">
                    <dt class="text-xs text-text-muted">PM Cycle</dt>
                    <dd class="mt-1 font-signage text-2xl font-semibold text-text">{{ $machine->pm_cycle_unit ? $machine->pm_cycle_value.' '.strtoupper($machine->pm_cycle_unit) : '-' }}</dd>
                </div>
                <div class="bg-surface p-4 sm:p-5">
                    <dt class="text-xs text-text-muted">Last PM</dt>
                    <dd class="mt-1 font-signage text-2xl font-semibold tabular-nums text-text">{{ $shownLastPm ? $shownLastPm->format('d-m-Y') : '-' }}</dd>
                    @if ($lastPmOverride)
                        <dd class="mt-1"><x-ui.badge tone="warning">Manual Override</x-ui.badge></dd>
                    @endif
                </div>
                <div class="col-span-2 bg-primary-light p-4 sm:col-span-1 sm:p-5">
                    <dt class="text-xs font-medium text-primary-hover">Next PM</dt>
                    <dd class="mt-1 font-signage text-2xl font-semibold tabular-nums text-text">{{ $nextPm ? $nextPm->format('d-m-Y') : '-' }}</dd>
                    @if ($isHourCycle)
                        <dd class="mt-1 text-xs text-text-muted">Estimasi</dd>
                    @endif
                </div>
            </dl>
        </section>

        @if ($isHourCycle)
            <x-ui.alert tone="info">Note: Next PM akan akurat jika mesin running non stop</x-ui.alert>
        @endif

        <section aria-labelledby="mh-pm-heading" class="space-y-3">
            <h2 id="mh-pm-heading" class="font-signage text-2xl font-semibold text-text">Riwayat PM</h2>

            @if ($pmHistories->isEmpty())
                <x-ui.empty-state title="No PM history found." description="PM yang dikerjakan untuk mesin ini akan tercatat di sini." />
            @endif

            {{-- Desktop table. Keep this list first: infinite scroll matches lists by key and position. --}}
            <div @class(['card hidden overflow-hidden', 'md:block' => $pmHistories->isNotEmpty()])>
                <div class="relative overflow-x-auto">
                    <table class="data-table min-w-full">
                        <thead>
                            <tr>
                                <th scope="col">Actual Date</th>
                                <th scope="col">Order Number</th>
                                <th scope="col">PIC</th>
                                <th scope="col">Duration</th>
                                <th scope="col">Status</th>
                                <th scope="col"><span class="sr-only">Action</span></th>
                            </tr>
                        </thead>
                        <tbody data-infinite-list="pmHistories">
                            @foreach ($pmHistories as $pm)
                                <tr>
                                    <td class="whitespace-nowrap font-semibold text-text">{{ \Carbon\Carbon::parse($pm->actual_date)->format('d-m-Y') }}</td>
                                    <td class="whitespace-nowrap">{{ $pm->order_number }}</td>
                                    <td>{{ $pm->pic ? \Illuminate\Support\Str::title(strtolower($pm->pic)) : '-' }}</td>
                                    <td class="whitespace-nowrap">{{ $pm->duration_formatted ?: '-' }}</td>
                                    <td>@include('pm-schedules.partials.status-badge', ['status' => $pm->status])</td>
                                    <td class="text-right">
                                        <x-ui.button size="sm" variant="outline" href="{{ route('machine-history.detail', ['machineNumber' => $machine->machine_number, 'pmSchedule' => $pm->id] + $detailContext) }}">Detail</x-ui.button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Phone cards (below md). Second list with the same key. --}}
            <div class="space-y-3 md:hidden" data-infinite-list="pmHistories">
                @foreach ($pmHistories as $pm)
                    <article class="card min-w-0 p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <h3 class="text-base font-semibold text-text">{{ \Carbon\Carbon::parse($pm->actual_date)->format('d-m-Y') }}</h3>
                                <p class="break-words text-sm text-text-muted">Order #{{ $pm->order_number }}</p>
                            </div>
                            @include('pm-schedules.partials.status-badge', ['status' => $pm->status])
                        </div>
                        <dl class="mt-3 grid grid-cols-2 gap-x-4 gap-y-2 border-t border-border pt-3 text-sm">
                            <div>
                                <dt class="text-xs text-text-muted">PIC</dt>
                                <dd class="font-medium text-text">{{ $pm->pic ?: '-' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-text-muted">Duration</dt>
                                <dd class="font-medium text-text">{{ $pm->duration_formatted ?: '-' }}</dd>
                            </div>
                        </dl>
                        <x-ui.button variant="outline" class="mt-3 w-full" href="{{ route('machine-history.detail', ['machineNumber' => $machine->machine_number, 'pmSchedule' => $pm->id] + $detailContext) }}">Detail</x-ui.button>
                    </article>
                @endforeach
            </div>

            <x-infinite-scroll :paginator="$pmHistories" key="pmHistories" />
        </section>
    </div>
@endsection
