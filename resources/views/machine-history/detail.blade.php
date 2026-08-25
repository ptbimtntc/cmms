@extends('layouts.app')

@section('content')

    {{-- ============ Header ============ --}}
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold">
                PM Record
            </h1>
            <p class="text-slate-500">
                {{ $machine->machine_number }}
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <button type="button" onclick="exportPmSchedulePdf(this, '{{ route('pm-schedules.pdf', $pmSchedule->id) }}')"
                class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-white hover:bg-blue-700 disabled:opacity-60">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 4v12m0 0l-4-4m4 4l4-4M4 20h16" />
                </svg>
                Export PDF
            </button>
            <a href="{{ route('machine-history.show', $pmSchedule->machine_number) }}"
                class="inline-flex w-fit items-center rounded-lg bg-slate-700 px-4 py-2 text-white">
                ← Back
            </a>
        </div>
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

    {{-- ============ PM Information ============ --}}
    <div class="mb-6 rounded-2xl border bg-white shadow-sm p-6">
        <h3 class="mb-6 text-lg font-semibold">
            PM Information
        </h3>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div>
                <label class="mb-1 block text-xs uppercase text-gray-500">Order Number</label>
                <div class="mt-1 rounded-lg border bg-slate-50 p-3 font-medium text-sm">
                    {{ $pmSchedule->order_number }}
                </div>
            </div>

            <div>
                <label class="mb-1 block text-xs uppercase text-gray-500">Execution</label>

                @if ($pmSchedule->workSessions && $pmSchedule->workSessions->isNotEmpty())
                    <div class="mt-1 rounded-lg border bg-slate-50 p-3">
                        @foreach ($pmSchedule->workSessions->sortBy(function($ws){ return ($ws->actual_date ?? '') . ' ' . ($ws->start_time ?? '00:00'); }) as $ws)
                            <div class="mb-3">
                                <div class="text-sm font-semibold">Day {{ $loop->iteration }} — {{ \Carbon\Carbon::parse($ws->actual_date)->format('d-m-Y') }}</div>
                                <div class="text-xs text-slate-500">Start: {{ $ws->start_time ?? '-' }} &middot; End: {{ $ws->end_time ?? '-' }} &middot; Duration: {{ $ws->duration ? floor($ws->duration/60) . ' Hours ' . ($ws->duration % 60) . ' Minutes' : '-' }}</div>
                            </div>
                        @endforeach

                        <div class="mt-3 border-t pt-2 text-sm font-semibold">Total Duration: @php $total = $pmSchedule->workSessions->sum('duration'); $h = floor($total/60); $m = $total % 60; echo $total ? "{$h} Hours {$m} Minutes" : '-'; @endphp</div>
                    </div>
                @else
                    <div class="mt-1 rounded-lg border bg-slate-50 p-3 font-medium text-sm">
                        {{ $pmSchedule->actual_date ? \Carbon\Carbon::parse($pmSchedule->actual_date)->format('d-m-Y') : '-' }}
                        <div class="text-xs text-slate-500 mt-1">Start: {{ $pmSchedule->start_time ?? '-' }} &middot; End: {{ $pmSchedule->end_time ?? '-' }} &middot; Duration: {{ $pmSchedule->duration ? floor($pmSchedule->duration/60) . ' Hours ' . ($pmSchedule->duration % 60) . ' Minutes' : '-' }}</div>
                    </div>
                @endif

            </div>

            <div>
                <label class="mb-1 block text-xs uppercase text-gray-500">PIC PM</label>
                <div class="mt-1 rounded-lg border bg-slate-50 p-3 font-medium text-sm">
                    {{ $pmSchedule->pic }}
                </div>
            </div>
        </div>

        <div class="mt-6 grid grid-cols-1 gap-4 md:grid-cols-3">
            @if ($pmSchedule->requiresOilChange())
                <div>
                    <label class="mb-2 block text-sm font-medium">Oil Change</label>
                    <div class="mt-1 rounded-lg border bg-slate-50 p-3 font-semibold">
                        {{ $pmSchedule->oil_change }}
                    </div>
                </div>
            @endif

            <div>
                <label class="mb-2 block text-sm font-medium">Greasing</label>
                <div class="mt-1 rounded-lg border bg-slate-50 p-3 font-semibold">
                    {{ $pmSchedule->greasing }}
                </div>
            </div>

            <div>
                <label class="mb-2 block text-sm font-medium">WO ZSBP</label>
                <div class="mt-1 rounded-lg border bg-slate-50 p-3 font-semibold">
                    {{ $pmSchedule->wo_zsbp }}
                </div>
            </div>

            @if ($pmSchedule->isGearboxApplicable())
                <div>
                    <label class="mb-2 block text-sm font-medium">Gearbox Problem</label>
                    <div class="mt-1 rounded-lg border bg-slate-50 p-3 font-semibold">
                        {{ $pmSchedule->gearbox_problem }}
                    </div>
                </div>
            @endif
        </div>

        <div class="mt-5 gap-3">
            <label class="mb-2 block text-sm font-medium">Remarks</label>
            <div class="mt-1 rounded-lg border bg-slate-50 p-3 font-semibold">
                {{ $pmSchedule->remarks }}
            </div>
        </div>
    </div>

    {{-- ============ PM Problems ============ --}}
    <div class="mb-6 rounded-xl bg-white p-6 shadow">
        <h2 class="mb-4 text-lg font-semibold">
            PM Problems
        </h2>

        @if ($pmProblems->isEmpty())
            <div class="italic text-slate-500">
                No problem found during this PM.
            </div>
        @else
            {{-- Desktop table --}}
            <div class="hidden overflow-x-auto md:block">
                <table class="min-w-full rounded-lg border border-slate-200">
                    <thead class="bg-slate-100">
                        <tr>
                            <th class="px-4 py-3 text-left">Problem</th>
                            <th class="px-4 py-3 text-left">Finding</th>
                            <th class="px-4 py-3 text-center">Severity</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($pmProblems as $problem)
                            @php
                                $color = match ($problem->severity) {
                                    'High' => 'bg-red-100 text-red-700',
                                    'Medium' => 'bg-yellow-100 text-yellow-700',
                                    default => 'bg-green-100 text-green-700',
                                };
                            @endphp
                            <tr class="border-t">
                                <td class="px-4 py-3">{{ $problem->machineProblem->problem ?? '-' }}</td>
                                <td class="px-4 py-3">{{ $problem->machineProblemFinding->finding ?? '-' }}</td>
                                <td class="px-4 py-3 text-center">
                                    <span class="rounded-full px-2 py-1 text-xs font-semibold {{ $color }}">
                                        {{ $problem->severity }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Mobile cards --}}
            <div class="space-y-3 md:hidden">
                @foreach ($pmProblems as $problem)
                    @php
                        $color = match ($problem->severity) {
                            'High' => 'bg-red-100 text-red-700',
                            'Medium' => 'bg-yellow-100 text-yellow-700',
                            default => 'bg-green-100 text-green-700',
                        };
                    @endphp
                    <div class="rounded-lg border border-slate-200 p-4">
                        <div class="mb-2 flex items-start justify-between gap-2">
                            <div class="text-sm font-semibold text-slate-800">
                                {{ $problem->machineProblem->problem ?? '-' }}
                            </div>
                            <span class="shrink-0 rounded-full px-2 py-1 text-xs font-semibold {{ $color }}">
                                {{ $problem->severity }}
                            </span>
                        </div>
                        <div class="text-xs text-slate-500">Finding</div>
                        <div class="text-sm text-slate-700">
                            {{ $problem->machineProblemFinding->finding ?? '-' }}
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- ============ PM Measurement ============ --}}
    <div class="mb-6 rounded-lg bg-white p-6 shadow">
        <h2 class="mb-4 text-lg font-semibold">
            Measurements
        </h2>

        {{-- Desktop table --}}
        <div class="hidden overflow-x-auto md:block">
            <table class="min-w-full border">
                <thead class="bg-slate-100">
                    <tr>
                        <th class="px-4 py-3 text-left">Measurement Item</th>
                        <th class="px-4 py-3 text-left">Result</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($pmMeasurements as $measurement)
                        <tr class="border-t">
                            <td class="px-4 py-3">{{ $measurement->measurement_item }}</td>
                            <td class="px-4 py-3 font-semibold">{{ $measurement->measurement_value }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Mobile list --}}
        <div class="divide-y divide-slate-100 rounded-lg border border-slate-200 md:hidden">
            @foreach ($pmMeasurements as $measurement)
                <div class="flex items-center justify-between gap-3 px-4 py-3">
                    <span class="text-sm text-slate-600">{{ $measurement->measurement_item }}</span>
                    <span class="text-sm font-semibold text-slate-800">{{ $measurement->measurement_value }}</span>
                </div>
            @endforeach
        </div>
    </div>

    {{-- ============ Sparepart Usage ============ --}}
    <div class="mb-6 rounded-lg bg-white p-6 shadow">
        <div class="mb-4 flex items-center justify-between">
            <h2 class="text-lg font-semibold">
                Sparepart Usage
            </h2>

            <div class="text-right">
                <p class="text-sm text-slate-500">Total Cost</p>
                <p class="text-xl font-bold text-emerald-600">
                    USD {{ number_format($totalCost, 2) }}
                </p>
            </div>
        </div>

        @if ($pmSpareparts->isEmpty())
            <div class="rounded-lg border border-dashed border-slate-300 py-8 text-center text-slate-500">
                No spareparts used during this PM.
            </div>
        @else
            {{-- Desktop table --}}
            <div class="hidden overflow-x-auto md:block">
                <table class="min-w-full rounded-lg border border-slate-200">
                    <thead class="bg-slate-100">
                        <tr>
                            <th class="px-4 py-3 text-left">Material Number</th>
                            <th class="px-4 py-3 text-left">Description</th>
                            <th class="px-4 py-3 text-center">Qty</th>
                            <th class="px-4 py-3 text-center">Unit</th>
                            <th class="px-4 py-3 text-right">Price</th>
                            <th class="px-4 py-3 text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($pmSpareparts as $item)
                            @php
                                $price = $item->sparepart->price ?? 0;
                                $qty = $item->qty ?? 0;
                                $lineTotal = $qty * $price;
                            @endphp
                            <tr class="border-t hover:bg-slate-50">
                                <td class="px-4 py-3 font-medium">{{ $item->sparepart->material_number }}</td>
                                <td class="px-4 py-3">{{ $item->sparepart->description }}</td>
                                <td class="px-4 py-3 text-center">{{ $qty }}</td>
                                <td class="px-4 py-3 text-center">{{ $item->sparepart->unit }}</td>
                                <td class="px-4 py-3 text-right">USD {{ number_format($price, 2) }}</td>
                                <td class="px-4 py-3 text-right font-semibold">USD {{ number_format($lineTotal, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="border-t-2 bg-slate-50">
                        <tr>
                            <td colspan="5" class="px-4 py-3 text-right font-bold">Total Cost</td>
                            <td class="px-4 py-3 text-right text-lg font-bold text-emerald-600">
                                USD {{ number_format($totalCost, 2) }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            {{-- Mobile cards --}}
            <div class="space-y-3 md:hidden">
                @foreach ($pmSpareparts as $item)
                    @php
                        $price = $item->sparepart->price ?? 0;
                        $qty = $item->qty ?? 0;
                        $lineTotal = $qty * $price;
                    @endphp
                    <div class="rounded-lg border border-slate-200 p-4">
                        <div class="mb-2">
                            <div class="text-sm font-semibold text-slate-800">
                                {{ $item->sparepart->material_number }}
                            </div>
                            <div class="text-xs text-slate-500">
                                {{ $item->sparepart->description }}
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-y-2 border-t border-slate-100 pt-3 text-xs">
                            <div>
                                <div class="text-slate-400">Qty / Unit</div>
                                <div class="font-medium text-slate-700">{{ $qty }} {{ $item->sparepart->unit }}
                                </div>
                            </div>
                            <div>
                                <div class="text-slate-400">Price</div>
                                <div class="font-medium text-slate-700">USD {{ number_format($price, 2) }}</div>
                            </div>
                        </div>

                        <div class="mt-3 flex items-center justify-between border-t border-slate-100 pt-3">
                            <span class="text-xs text-slate-400">Total</span>
                            <span class="font-semibold text-emerald-600">USD {{ number_format($lineTotal, 2) }}</span>
                        </div>
                    </div>
                @endforeach

                <div class="flex items-center justify-between rounded-lg bg-slate-50 px-4 py-3">
                    <span class="font-bold text-slate-800">Total Cost</span>
                    <span class="text-lg font-bold text-emerald-600">USD {{ number_format($totalCost, 2) }}</span>
                </div>
            </div>
        @endif
    </div>

    {{-- ============ PM Checklist ============ --}}
    <div class="rounded-xl bg-white p-6 shadow">
        <h2 class="mb-5 text-xl font-semibold">
            PM Checklist
        </h2>

        @foreach ($checklists as $section => $items)
            <div x-data="{ open: true }" class="mb-4 overflow-hidden rounded-xl border">

                <button @click="open=!open"
                    class="flex w-full items-center justify-between bg-slate-100 px-5 py-4 transition hover:bg-slate-200">

                    <div class="flex items-center gap-3">
                        <span class="font-semibold">{{ $section }}</span>
                        <span class="rounded-full bg-slate-300 px-2 py-1 text-xs">
                            {{ $items->count() }} Items
                        </span>
                    </div>

                    <svg class="h-5 w-5 transition" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>

                <div x-show="open" x-transition class="border-t border-slate-200">

                    {{-- Desktop table --}}
                    <div class="hidden overflow-x-auto md:block">
                        <table class="w-full">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="px-4 py-3 text-left">Checklist Item</th>
                                    <th class="px-4 py-3 text-center">Result</th>
                                    <th class="px-4 py-3 text-left">Remarks</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($items as $item)
                                    <tr class="border-t hover:bg-slate-50">
                                        <td class="px-4 py-3">
                                            {{ $item->machineChecklist->checklist_item }}
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="flex flex-wrap justify-center gap-2">
                                                @forelse($item->results as $result)
                                                    <span
                                                        class="rounded-full px-2 py-1 text-xs font-semibold {{ $result['badge'] }}">
                                                        {{ $result['text'] }}
                                                    </span>
                                                @empty
                                                    <span class="text-slate-400">-</span>
                                                @endforelse
                                            </div>
                                        </td>
                                        <td class="px-4 py-3">
                                            {{ $item->remarks ?: '-' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    {{-- Mobile cards --}}
                    <div class="space-y-3 p-3 md:hidden">
                        @foreach ($items as $item)
                            <div class="rounded-lg border border-slate-200 p-3">
                                <div class="mb-2 text-sm font-medium text-slate-800">
                                    {{ $item->machineChecklist->checklist_item }}
                                </div>

                                <div class="mb-2 flex flex-wrap gap-2">
                                    @forelse($item->results as $result)
                                        <span class="rounded-full px-2 py-1 text-xs font-semibold {{ $result['badge'] }}">
                                            {{ $result['text'] }}
                                        </span>
                                    @empty
                                        <span class="text-xs text-slate-400">-</span>
                                    @endforelse
                                </div>

                                <div class="border-t border-slate-100 pt-2 text-xs">
                                    <span class="text-slate-400">Remarks: </span>
                                    <span class="text-slate-600">{{ $item->remarks ?: '-' }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>

                </div>
            </div>
        @endforeach

    </div>

    <link href="https://cdn.jsdelivr.net/npm/tom-select/dist/css/tom-select.css" rel="stylesheet">
@endsection
