{{-- Ranked horizontal bars, one series, so one colour for every bar. Params: title, items, emptyText. --}}
<section class="card p-5">
    <h3 class="text-sm font-semibold text-text">{{ $title }}</h3>

    <ol class="mt-4 space-y-3">
        @forelse($items as $it)
            <li>
                <div class="mb-1 flex justify-between gap-3 text-sm">
                    <span class="truncate text-text-muted">{{ $it['label'] }}</span>
                    <span class="font-semibold text-text">{{ $it['value'] }}</span>
                </div>
                <div class="h-2 rounded-full bg-surface-muted" aria-hidden="true">
                    <div class="h-2 rounded-full bg-chart-1" style="width: {{ $it['percent'] }}%"></div>
                </div>
            </li>
        @empty
            <li class="text-sm text-text-muted">No data available. {{ $emptyText ?? '' }}</li>
        @endforelse
    </ol>
</section>
