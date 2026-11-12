@php $result = session($resultKey ?? 'predictive_import_result'); @endphp
<section class="card p-4 sm:p-5 {{ $class ?? '' }}" aria-label="Hasil import">
    @if ($result['fatal'])
        <p class="text-sm font-medium text-danger" role="alert">{{ $result['fatal'] }}</p>
    @else
        <h2 class="text-sm font-semibold text-text">Hasil import</h2>
        <dl class="mt-2 grid grid-cols-3 gap-2 text-center">
            <div class="rounded-control bg-success-light px-2 py-2"><dt class="text-xs text-success-hover">Success</dt><dd class="font-signage text-2xl font-semibold text-success-hover">{{ $result['success'] }}</dd></div>
            <div class="rounded-control bg-danger-light px-2 py-2"><dt class="text-xs text-danger-hover">Failed</dt><dd class="font-signage text-2xl font-semibold text-danger-hover">{{ $result['failed'] }}</dd></div>
            <div class="rounded-control bg-warning-light px-2 py-2"><dt class="text-xs text-warning-text">Skipped</dt><dd class="font-signage text-2xl font-semibold text-warning-text">{{ $result['skipped'] }}</dd></div>
        </dl>
        @if ($result['details'])
            <details class="group mt-3">
                <summary class="flex min-h-11 cursor-pointer list-none items-center text-sm font-semibold text-primary underline-offset-2 hover:underline">
                    <span class="group-open:hidden">Lihat penyebab per baris ({{ count($result['details']) + $result['hidden'] }})</span>
                    <span class="hidden group-open:inline">Sembunyikan detail</span>
                </summary>
                <div class="relative overflow-x-auto rounded-control border border-border">
                    <table class="data-table min-w-full">
                        <thead>
                            <tr><th scope="col">Baris</th><th scope="col">{{ $labelHeader ?? 'Order Number' }}</th><th scope="col">Hasil</th><th scope="col">Penyebab</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($result['details'] as $d)
                                <tr class="align-top">
                                    <td class="whitespace-nowrap">{{ $d['row'] }}</td>
                                    <td class="font-medium text-text">{{ ($d['label'] ?? $d['order_number'] ?? '') ?: '-' }}</td>
                                    <td>
                                        <x-ui.badge :tone="$d['result'] === 'failed' ? 'danger' : 'warning'">{{ ucfirst($d['result']) }}</x-ui.badge>
                                    </td>
                                    <td class="min-w-56">{{ $d['reason'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($result['hidden'] > 0)
                    <p class="mt-2 text-xs text-text-muted">{{ $result['hidden'] }} baris lain tidak ditampilkan (batas {{ ($maxDetail ?? \App\Imports\PredictiveWorkOrderImport::MAX_DETAIL_ROWS) }} baris).</p>
                @endif
            </details>
        @endif
    @endif
</section>
