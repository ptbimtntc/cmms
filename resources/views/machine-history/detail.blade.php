@extends('layouts.app')

@php
    $fullWidth = true;
    $contentMaxWidth = 'max-w-5xl';

    // Back goes to wherever this detail page was opened from: Report PM
    // (preserving its filters/pagination via return_url) when the View
    // button there set from=report-pm, otherwise the existing Machine
    // History per-machine page (unchanged default behavior).
    $cameFromReportPm = request()->query('from') === 'report-pm';
    $reportPmReturnUrl = request()->query('return_url');

    $backUrl = $cameFromReportPm && $reportPmReturnUrl && str_starts_with($reportPmReturnUrl, route('reports.pm'))
        ? $reportPmReturnUrl
        : ($cameFromReportPm ? route('reports.pm') : route('machine-history.show', $pmSchedule->machine_number));

    // Opened via Machine History that was opened from Report Machine: Back
    // returns to that Machine History page, keeping the report context.
    if (request()->query('from') === 'report-machine') {
        $reportMachineReturnUrl = request()->query('return_url');
        $backUrl = route('machine-history.show', [
            'machine_history' => $pmSchedule->machine_number,
            'from' => 'report-machine',
            'return_url' => is_string($reportMachineReturnUrl) && str_starts_with($reportMachineReturnUrl, route('reports.machine'))
                ? $reportMachineReturnUrl
                : route('reports.machine'),
        ]);
    }
@endphp

@section('content')
    @php
        $hm = fn ($minutes) => $minutes ? floor($minutes / 60).' Hours '.($minutes % 60).' Minutes' : '-';
        $sessions = $pmSchedule->workSessions && $pmSchedule->workSessions->isNotEmpty()
            ? $pmSchedule->workSessions->sortBy(fn ($ws) => ($ws->actual_date ?? '').' '.($ws->start_time ?? '00:00'))->values()
            : collect();
        $severityTone = fn ($severity) => match ($severity) { 'High' => 'danger', 'Medium' => 'warning', default => 'success' };
    @endphp

    <div class="space-y-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <x-ui.button variant="ghost" href="{{ $backUrl }}" class="-ml-2">Back</x-ui.button>
            <button type="button" onclick="exportPmSchedulePdf(this, '{{ route('pm-schedules.pdf', $pmSchedule->id) }}')" class="btn btn-outline min-h-11">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v12m0 0l-4-4m4 4l4-4M4 20h16" />
                </svg>
                Export PDF
            </button>
        </div>

        {{-- PM Information --}}
        <section class="card overflow-hidden">
            <div class="flex items-start justify-between gap-3 border-b border-border p-5 sm:p-6">
                <div class="min-w-0">
                    <p class="text-sm font-medium text-text-muted">PM Record</p>
                    <h1 class="mt-1 break-words font-signage text-4xl font-semibold leading-none text-text">{{ $machine->machine_number }}</h1>
                    <p class="mt-2 break-words text-sm text-text-muted">Order {{ $pmSchedule->order_number }}</p>
                </div>
                @include('pm-schedules.partials.status-badge', ['status' => $pmSchedule->status])
            </div>

            <div class="space-y-5 p-5 sm:p-6">
                <h2 class="sr-only">PM Information</h2>
                <dl class="grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs text-text-muted">PIC PM</dt>
                        <dd class="mt-0.5 font-semibold text-text">{{ $pmSchedule->pic ?: '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-text-muted">Execution</dt>
                        @if ($sessions->isNotEmpty())
                            @foreach ($sessions as $ws)
                                <dd class="mt-0.5">
                                    <span class="font-semibold text-text">Day {{ $loop->iteration }} · {{ \Carbon\Carbon::parse($ws->actual_date)->format('d-m-Y') }}</span>
                                    <span class="block text-xs text-text-muted">Start {{ $ws->start_time ?? '-' }} · End {{ $ws->end_time ?? '-' }} · {{ $hm($ws->duration) }}</span>
                                </dd>
                            @endforeach
                            <dd class="mt-1.5 border-t border-border pt-1.5 text-sm font-semibold text-text">Total Duration: {{ $hm($pmSchedule->workSessions->sum('duration')) }}</dd>
                        @else
                            <dd class="mt-0.5">
                                <span class="font-semibold text-text">{{ $pmSchedule->actual_date ? \Carbon\Carbon::parse($pmSchedule->actual_date)->format('d-m-Y') : '-' }}</span>
                                <span class="block text-xs text-text-muted">Start {{ $pmSchedule->start_time ?? '-' }} · End {{ $pmSchedule->end_time ?? '-' }} · Duration {{ $hm($pmSchedule->duration) }}</span>
                            </dd>
                        @endif
                    </div>
                </dl>

                <dl class="grid grid-cols-2 gap-x-6 gap-y-4 border-t border-border pt-5 sm:grid-cols-4">
                    @if ($pmSchedule->requiresOilChange())
                        <div>
                            <dt class="text-xs text-text-muted">Oil Change</dt>
                            <dd class="mt-0.5 font-semibold text-text">{{ $pmSchedule->oil_change ?: '-' }}</dd>
                        </div>
                    @endif
                    <div>
                        <dt class="text-xs text-text-muted">Greasing</dt>
                        <dd class="mt-0.5 font-semibold text-text">{{ $pmSchedule->greasing ?: '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-text-muted">WO ZSBP</dt>
                        <dd class="mt-0.5 font-semibold text-text">{{ $pmSchedule->wo_zsbp ?: '-' }}</dd>
                    </div>
                    @if ($pmSchedule->isGearboxApplicable())
                        <div>
                            <dt class="text-xs text-text-muted">Gearbox Problem</dt>
                            <dd class="mt-0.5 font-semibold text-text">{{ $pmSchedule->gearbox_problem ?: '-' }}</dd>
                        </div>
                    @endif
                </dl>

                <div class="border-t border-border pt-5">
                    <h3 class="text-xs text-text-muted">Remarks</h3>
                    <p class="mt-0.5 whitespace-pre-line break-words text-text">{{ $pmSchedule->remarks ?: '-' }}</p>
                </div>
            </div>
        </section>

        {{-- PM Problems --}}
        <section class="card p-5 sm:p-6" aria-labelledby="pmr-problems">
            <h2 id="pmr-problems" class="text-lg font-semibold text-text">PM Problems</h2>
            @if ($pmProblems->isEmpty())
                <p class="mt-2 text-sm text-text-muted">No problem found during this PM.</p>
            @else
                <div class="relative mt-4 hidden overflow-x-auto rounded-control border border-border md:block">
                    <table class="data-table min-w-full">
                        <thead>
                            <tr>
                                <th scope="col">Problem</th>
                                <th scope="col">Finding</th>
                                <th scope="col">Severity</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($pmProblems as $problem)
                                <tr>
                                    <td class="font-medium text-text">{{ $problem->machineProblem->problem ?? '-' }}</td>
                                    <td>{{ $problem->machineProblemFinding->finding ?? '-' }}</td>
                                    <td><x-ui.badge :tone="$severityTone($problem->severity)">{{ $problem->severity }}</x-ui.badge></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <ul class="mt-4 space-y-3 md:hidden">
                    @foreach ($pmProblems as $problem)
                        <li class="rounded-control border border-border p-4">
                            <div class="flex items-start justify-between gap-3">
                                <p class="min-w-0 break-words font-semibold text-text">{{ $problem->machineProblem->problem ?? '-' }}</p>
                                <x-ui.badge :tone="$severityTone($problem->severity)">{{ $problem->severity }}</x-ui.badge>
                            </div>
                            <p class="mt-2 text-xs text-text-muted">Finding</p>
                            <p class="break-words text-sm text-text">{{ $problem->machineProblemFinding->finding ?? '-' }}</p>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        {{-- Measurements --}}
        <section class="card p-5 sm:p-6" aria-labelledby="pmr-measurements">
            <h2 id="pmr-measurements" class="text-lg font-semibold text-text">Measurements</h2>
            @if ($pmMeasurements->isEmpty())
                <p class="mt-2 text-sm text-text-muted">No measurement recorded during this PM.</p>
            @else
                <div class="relative mt-4 hidden overflow-x-auto rounded-control border border-border md:block">
                    <table class="data-table min-w-full">
                        <thead>
                            <tr>
                                <th scope="col">Measurement Item</th>
                                <th scope="col">Standard</th>
                                <th scope="col">Result</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($pmMeasurements as $measurement)
                                <tr>
                                    <td>{{ $measurement->measurement_item }}</td>
                                    <td>{{ $measurement->standard ?: '-' }}</td>
                                    <td class="font-semibold text-text">{{ $measurement->measurement_value }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <ul class="mt-4 divide-y divide-border rounded-control border border-border md:hidden">
                    @foreach ($pmMeasurements as $measurement)
                        <li class="flex items-center justify-between gap-3 px-4 py-3">
                            <div class="min-w-0 text-sm">
                                <p class="break-words text-text">{{ $measurement->measurement_item }}</p>
                                <p class="text-xs text-text-muted">Std: {{ $measurement->standard ?: '-' }}</p>
                            </div>
                            <span class="shrink-0 font-semibold text-text">{{ $measurement->measurement_value }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        {{-- Sparepart Usage --}}
        <section class="card p-5 sm:p-6" aria-labelledby="pmr-spareparts">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <h2 id="pmr-spareparts" class="text-lg font-semibold text-text">Sparepart Usage</h2>
                <div class="text-right">
                    <p class="text-xs text-text-muted">Total Cost</p>
                    <p class="font-signage text-2xl font-semibold tabular-nums text-text">USD {{ number_format($totalCost, 2) }}</p>
                </div>
            </div>

            @if ($pmSpareparts->isEmpty())
                <p class="mt-2 text-sm text-text-muted">No spareparts used during this PM.</p>
            @else
                <div class="relative mt-4 hidden overflow-x-auto rounded-control border border-border md:block">
                    <table class="data-table min-w-full">
                        <thead>
                            <tr>
                                <th scope="col">Material Number</th>
                                <th scope="col">Description</th>
                                <th scope="col" class="text-right">Qty</th>
                                <th scope="col">Unit</th>
                                <th scope="col" class="text-right">Price</th>
                                <th scope="col" class="text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($pmSpareparts as $item)
                                @php($price = $item->sparepart->price ?? 0)
                                @php($qty = $item->qty ?? 0)
                                <tr>
                                    <td class="whitespace-nowrap font-medium text-text">{{ $item->sparepart->material_number }}</td>
                                    <td>{{ $item->sparepart->description }}</td>
                                    <td class="text-right tabular-nums">{{ $qty }}</td>
                                    <td>{{ $item->sparepart->unit }}</td>
                                    <td class="whitespace-nowrap text-right tabular-nums">USD {{ number_format($price, 2) }}</td>
                                    <td class="whitespace-nowrap text-right font-semibold tabular-nums text-text">USD {{ number_format($qty * $price, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="border-t-2 border-border bg-surface-sunken">
                                <th scope="row" colspan="5" class="px-4 py-3 text-right font-semibold text-text">Total Cost</th>
                                <td class="whitespace-nowrap px-4 py-3 text-right font-semibold tabular-nums text-text">USD {{ number_format($totalCost, 2) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <ul class="mt-4 space-y-3 md:hidden">
                    @foreach ($pmSpareparts as $item)
                        @php($price = $item->sparepart->price ?? 0)
                        @php($qty = $item->qty ?? 0)
                        <li class="rounded-control border border-border p-4">
                            <p class="font-semibold text-text">{{ $item->sparepart->material_number }}</p>
                            <p class="break-words text-sm text-text-muted">{{ $item->sparepart->description }}</p>
                            <dl class="mt-3 grid grid-cols-3 gap-x-3 border-t border-border pt-3 text-sm">
                                <div><dt class="text-xs text-text-muted">Qty</dt><dd class="font-medium text-text">{{ $qty }} {{ $item->sparepart->unit }}</dd></div>
                                <div><dt class="text-xs text-text-muted">Price</dt><dd class="font-medium tabular-nums text-text">USD {{ number_format($price, 2) }}</dd></div>
                                <div class="text-right"><dt class="text-xs text-text-muted">Total</dt><dd class="font-semibold tabular-nums text-text">USD {{ number_format($qty * $price, 2) }}</dd></div>
                            </dl>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        {{-- PM Checklist: one collapsible group per section. --}}
        <section class="card p-5 sm:p-6" aria-labelledby="pmr-checklist">
            <h2 id="pmr-checklist" class="text-lg font-semibold text-text">PM Checklist</h2>
            <div class="mt-4 space-y-3">
                @foreach ($checklists as $section => $items)
                    <div x-data="{ open: true }" class="overflow-hidden rounded-control border border-border">
                        <button type="button" @click="open = !open" :aria-expanded="open.toString()"
                            class="flex min-h-12 w-full items-center justify-between gap-3 bg-surface-sunken px-4 text-left transition-colors hover:bg-surface-muted">
                            <span class="flex min-w-0 items-center gap-2">
                                <span class="font-semibold text-text">{{ $section }}</span>
                                <x-ui.badge>{{ $items->count() }} Items</x-ui.badge>
                            </span>
                            <svg class="h-5 w-5 shrink-0 text-text-muted transition-transform" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>

                        <div x-show="open" class="border-t border-border">
                            <div class="relative hidden overflow-x-auto md:block">
                                <table class="data-table min-w-full">
                                    <thead>
                                        <tr>
                                            <th scope="col">Checklist Item</th>
                                            <th scope="col">Result</th>
                                            <th scope="col">Remarks</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($items as $item)
                                            <tr>
                                                <td class="text-text">{{ $item->machineChecklist->checklist_item }}</td>
                                                <td>
                                                    <div class="flex flex-wrap gap-1.5">
                                                        {{-- Badge colours come from PMChecklist::results. --}}
                                                        @forelse ($item->results as $result)
                                                            <span class="badge {{ $result['badge'] }}">{{ $result['text'] }}</span>
                                                        @empty
                                                            <span class="text-text-muted">-</span>
                                                        @endforelse
                                                    </div>
                                                </td>
                                                <td>{{ $item->remarks ?: '-' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            <ul class="divide-y divide-border md:hidden">
                                @foreach ($items as $item)
                                    <li class="p-4">
                                        <p class="break-words text-sm font-medium text-text">{{ $item->machineChecklist->checklist_item }}</p>
                                        <div class="mt-2 flex flex-wrap gap-1.5">
                                            @forelse ($item->results as $result)
                                                <span class="badge {{ $result['badge'] }}">{{ $result['text'] }}</span>
                                            @empty
                                                <span class="text-xs text-text-muted">-</span>
                                            @endforelse
                                        </div>
                                        <p class="mt-2 text-xs"><span class="text-text-muted">Remarks: </span><span class="text-text">{{ $item->remarks ?: '-' }}</span></p>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    </div>

    <script>
        async function exportPmSchedulePdf(button, url) {
            const originalHtml = button.innerHTML;
            button.disabled = true;
            button.textContent = 'Generating...';

            try {
                const response = await fetch(url, {
                    credentials: 'same-origin',
                    headers: { 'Accept': 'application/json' },
                });

                if (!response.ok) {
                    if (response.redirected) {
                        window.location.href = response.url;
                        return;
                    }
                    throw new Error('PDF generation failed');
                }

                const data = await response.json();
                const byteChars = atob(data.content);
                const byteNumbers = new Array(byteChars.length);
                for (let i = 0; i < byteChars.length; i++) {
                    byteNumbers[i] = byteChars.charCodeAt(i);
                }
                const blob = new Blob([new Uint8Array(byteNumbers)], { type: 'application/pdf' });

                const blobUrl = window.URL.createObjectURL(blob);
                const link = document.createElement('a');
                link.href = blobUrl;
                link.download = data.filename || 'PM_Record.pdf';
                document.body.appendChild(link);
                link.click();
                link.remove();
                window.URL.revokeObjectURL(blobUrl);
            } catch (error) {
                alert('Failed to export PDF. Please try again.');
            } finally {
                button.disabled = false;
                button.innerHTML = originalHtml;
            }
        }
    </script>
@endsection
