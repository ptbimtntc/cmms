@extends('layouts.app')

@section('title', 'Sparepart Injection')

@php($fullWidth = true)

@section('content')
    @include('spareparts._tabs')

    <div class="space-y-5">
        <x-ui.page-header title="Sparepart Injection" description="Import warehouse issues (Month, SAP Number, Qty, Order Number) into the Sparepart Usage of the matching PM Schedule." />

        @if (session('success'))
            <x-ui.alert tone="success">{{ session('success') }}</x-ui.alert>
        @endif
        @if ($errors->has('file'))
            <x-ui.alert tone="danger">{{ $errors->first('file') }}</x-ui.alert>
        @endif

        @if ($canImport)
            <form action="{{ route('sparepart-injection.store') }}" method="POST" enctype="multipart/form-data" class="card space-y-3 p-4 sm:p-5"
                x-data="{ busy: false }" @submit="busy = true">
                @csrf
                <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                    <div class="grid min-w-0 flex-1 gap-1.5">
                        <label for="si-file" class="text-xs font-medium text-text-muted">File (.xlsx, .xls, .csv)</label>
                        <input id="si-file" type="file" name="file" accept=".xlsx,.xls,.csv" required aria-describedby="si-file-hint"
                            class="block min-h-11 w-full rounded-control border border-border-strong bg-surface text-sm text-text file:mr-3 file:min-h-11 file:border-0 file:bg-surface-muted file:px-3 file:text-sm file:font-semibold file:text-text">
                    </div>
                    <x-ui.button type="submit" x-bind:disabled="busy">Upload &amp; Import</x-ui.button>
                </div>
                <p id="si-file-hint" class="form-hint">
                    Columns: Month (month name, e.g. Oktober / October), SAP Number, Qty, Order Number. Qty of rows with the same
                    Order Number + SAP Number is summed; spareparts already recorded on the PM are skipped.
                </p>
            </form>
        @else
            <x-ui.alert tone="info">Read-only view.</x-ui.alert>
        @endif

        <section aria-labelledby="si-history" class="space-y-3">
            <h2 id="si-history" class="font-signage text-2xl font-semibold text-text">Import history</h2>

            @if ($imports->isEmpty())
                <x-ui.empty-state title="No imports yet." :description="$canImport ? 'Upload a warehouse issue file above to add its usage to the matching PMs.' : null" />
            @endif

            {{-- Desktop table. Keep this list first: infinite scroll matches lists by key and position. --}}
            <div @class(['card hidden overflow-hidden', 'md:block' => $imports->isNotEmpty()])>
                <div class="relative overflow-x-auto">
                    <table class="data-table min-w-full">
                        <thead>
                            <tr>
                                <th scope="col">Date</th>
                                <th scope="col">File</th>
                                <th scope="col">By</th>
                                <th scope="col" class="text-right">Rows</th>
                                <th scope="col" class="text-right">Processed</th>
                                <th scope="col" class="text-right">Usage added</th>
                                <th scope="col" class="text-right">Skipped</th>
                                <th scope="col" class="text-right">Failed</th>
                                <th scope="col"><span class="sr-only">Action</span></th>
                            </tr>
                        </thead>
                        <tbody data-infinite-list="imports">
                            @foreach ($imports as $import)
                                <tr>
                                    <td class="whitespace-nowrap">{{ $import->created_at->format('d M Y H:i') }}</td>
                                    <td class="min-w-40 break-all font-medium text-text">{{ $import->filename }}</td>
                                    <td>{{ $import->user?->name ?? '-' }}</td>
                                    <td class="text-right tabular-nums">{{ $import->total_rows }}</td>
                                    <td class="text-right font-semibold tabular-nums text-text">{{ $import->processed_rows }}</td>
                                    <td class="text-right tabular-nums">{{ $import->usage_added }}</td>
                                    <td @class(['text-right tabular-nums', 'font-semibold text-warning-text' => $import->skipped_rows > 0])>{{ $import->skipped_rows }}</td>
                                    <td @class(['text-right tabular-nums', 'font-semibold text-danger' => $import->failed_rows > 0])>{{ $import->failed_rows }}</td>
                                    <td class="text-right"><x-ui.button size="sm" variant="outline" href="{{ route('sparepart-injection.show', $import) }}">Detail</x-ui.button></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Phone cards (below md). Second list with the same key. --}}
            <div class="space-y-3 md:hidden" data-infinite-list="imports">
                @foreach ($imports as $import)
                    <article class="card min-w-0 p-4">
                        <h3 class="break-all text-base font-semibold text-text">{{ $import->filename }}</h3>
                        <p class="text-sm text-text-muted">{{ $import->created_at->format('d M Y H:i') }} · {{ $import->user?->name ?? '-' }}</p>
                        <dl class="mt-3 grid grid-cols-3 gap-x-3 gap-y-2 border-t border-border pt-3 text-sm">
                            <div><dt class="text-xs text-text-muted">Rows</dt><dd class="font-medium tabular-nums text-text">{{ $import->total_rows }}</dd></div>
                            <div><dt class="text-xs text-text-muted">Processed</dt><dd class="font-semibold tabular-nums text-text">{{ $import->processed_rows }}</dd></div>
                            <div><dt class="text-xs text-text-muted">Usage added</dt><dd class="font-medium tabular-nums text-text">{{ $import->usage_added }}</dd></div>
                            <div><dt class="text-xs text-text-muted">Skipped</dt><dd @class(['tabular-nums', 'font-semibold text-warning-text' => $import->skipped_rows > 0, 'font-medium text-text' => ! $import->skipped_rows])>{{ $import->skipped_rows }}</dd></div>
                            <div><dt class="text-xs text-text-muted">Failed</dt><dd @class(['tabular-nums', 'font-semibold text-danger' => $import->failed_rows > 0, 'font-medium text-text' => ! $import->failed_rows])>{{ $import->failed_rows }}</dd></div>
                        </dl>
                        <x-ui.button variant="outline" class="mt-3 w-full" href="{{ route('sparepart-injection.show', $import) }}">Detail</x-ui.button>
                    </article>
                @endforeach
            </div>

            <x-infinite-scroll :paginator="$imports" key="imports" />
        </section>
    </div>
@endsection
