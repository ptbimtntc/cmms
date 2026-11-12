@extends('layouts.app')

@section('title', 'Import result')

@section('content')
    @include('spareparts._tabs')

    @php
        $fullWidth = true;
        $rowTone = ['PROCESSED' => 'success', 'SKIPPED' => 'warning', 'FAILED' => 'danger'];
        $filters = [null => 'All', 'PROCESSED' => 'Processed', 'SKIPPED' => 'Skipped', 'FAILED' => 'Failed'];
    @endphp

    <div class="space-y-5">
        <x-ui.button variant="ghost" href="{{ route('sparepart-injection.index') }}" class="-ml-2">Back to history</x-ui.button>

        <x-ui.page-header title="Import result" description="{{ $import->filename }} · {{ $import->created_at->format('d M Y H:i') }} · {{ $import->user?->name ?? '-' }}" />

        @if (session('success'))
            <x-ui.alert tone="success">{{ session('success') }}</x-ui.alert>
        @endif

        {{-- Counts are words + numbers; colour only marks the rows that need a look (skipped, failed). --}}
        <dl class="card grid grid-cols-2 gap-px overflow-hidden bg-border md:grid-cols-5">
            @foreach ([
                ['Rows read', $import->total_rows, 'text-text'],
                ['New transactions processed', $import->processed_rows, 'text-text'],
                ['Usage added', $import->usage_added, 'text-text'],
                ['Skipped (duplicate / usage exists)', $import->skipped_rows, $import->skipped_rows > 0 ? 'text-warning-text' : 'text-text'],
                ['Failed / invalid', $import->failed_rows, $import->failed_rows > 0 ? 'text-danger' : 'text-text'],
            ] as [$label, $value, $color])
                <div @class(['flex flex-col-reverse justify-end gap-1 bg-surface p-4', 'col-span-2 md:col-span-1' => $loop->last])>
                    <dt class="text-xs text-text-muted">{{ $label }}</dt>
                    <dd class="font-signage text-3xl font-semibold tabular-nums {{ $color }}">{{ $value }}</dd>
                </div>
            @endforeach
        </dl>

        <nav class="flex flex-wrap gap-2" aria-label="Filter rows by status">
            @foreach ($filters as $key => $label)
                {{-- PHP turns the null key of "All" into '', so compare as strings. --}}
                @php($active = (string) $status === (string) $key)
                <a href="{{ route('sparepart-injection.show', array_filter(['import' => $import, 'status' => $key])) }}" @if ($active) aria-current="page" @endif
                    @class([
                        'inline-flex min-h-11 items-center rounded-full border px-4 text-sm font-semibold transition-colors lg:min-h-9',
                        'border-primary bg-primary-light text-primary-hover' => $active,
                        'border-border-strong bg-surface text-text-muted hover:bg-surface-muted hover:text-text' => ! $active,
                    ])>{{ $label }}</a>
            @endforeach
        </nav>

        @if ($rows->isEmpty())
            <x-ui.empty-state title="No rows." :description="$status ? 'No row has this status in this import.' : null" />
        @endif

        {{-- Desktop table. Keep this list first: infinite scroll matches lists by key and position. --}}
        <div @class(['card hidden overflow-hidden', 'md:block' => $rows->isNotEmpty()])>
            <div class="relative overflow-x-auto">
                <table class="data-table min-w-full">
                    <thead>
                        <tr>
                            <th scope="col" class="text-right">#</th>
                            <th scope="col">Month</th>
                            <th scope="col">Order Number</th>
                            <th scope="col">SAP Number</th>
                            <th scope="col" class="text-right">Qty</th>
                            <th scope="col">Status</th>
                            <th scope="col">Reason</th>
                        </tr>
                    </thead>
                    <tbody data-infinite-list="rows">
                        @foreach ($rows as $row)
                            <tr>
                                <td class="text-right tabular-nums text-text-muted">{{ $row->row_number }}</td>
                                <td class="whitespace-nowrap">{{ $row->monthLabel() }}</td>
                                <td class="whitespace-nowrap font-semibold text-text">{{ $row->order_number }}</td>
                                <td class="whitespace-nowrap">{{ $row->sap_number }}</td>
                                <td class="text-right tabular-nums">{{ $row->qty }}</td>
                                <td><x-ui.badge :tone="$rowTone[$row->status] ?? 'neutral'">{{ $row->status }}</x-ui.badge></td>
                                <td class="min-w-56">{{ $row->reason }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Phone cards (below md). Second list with the same key. --}}
        <div class="space-y-3 md:hidden" data-infinite-list="rows">
            @foreach ($rows as $row)
                <article class="card min-w-0 p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h2 class="break-words text-base font-semibold text-text">{{ $row->order_number ?: '-' }}</h2>
                            <p class="text-sm text-text-muted">Row {{ $row->row_number }} · {{ $row->monthLabel() }}</p>
                        </div>
                        <x-ui.badge :tone="$rowTone[$row->status] ?? 'neutral'">{{ $row->status }}</x-ui.badge>
                    </div>
                    <dl class="mt-3 grid grid-cols-2 gap-x-4 gap-y-2 border-t border-border pt-3 text-sm">
                        <div><dt class="text-xs text-text-muted">SAP Number</dt><dd class="break-all font-medium text-text">{{ $row->sap_number ?: '-' }}</dd></div>
                        <div><dt class="text-xs text-text-muted">Qty</dt><dd class="font-medium tabular-nums text-text">{{ $row->qty }}</dd></div>
                        @if ($row->reason)
                            <div class="col-span-2"><dt class="text-xs text-text-muted">Reason</dt><dd class="text-text">{{ $row->reason }}</dd></div>
                        @endif
                    </dl>
                </article>
            @endforeach
        </div>

        <x-infinite-scroll :paginator="$rows" key="rows" />
    </div>
@endsection
