<section class="card p-5" aria-labelledby="area-closing-heading">
    <h2 id="area-closing-heading" class="text-sm font-semibold text-text">Closing by area</h2>
    <p class="text-xs text-text-muted">This month</p>

    <div class="mt-4 space-y-4">
        @forelse($areas as $area)
            <div>
                <div class="mb-1.5 flex justify-between gap-3 text-sm">
                    <span class="text-text-muted">{{ $area['name'] }}</span>
                    <span class="font-semibold text-text">{{ $area['percent'] }}%</span>
                </div>
                <div class="h-2 rounded-full bg-surface-muted" aria-hidden="true">
                    <div class="h-2 rounded-full bg-chart-1" style="width: {{ min($area['percent'], 100) }}%"></div>
                </div>
            </div>
        @empty
            <p class="text-sm text-text-muted">No data available. Closing per area appears once PM is planned this month.</p>
        @endforelse
    </div>
</section>
