<div class="bg-surface border border-border rounded-xl shadow-sm p-4">
    <div class="text-sm font-medium text-text mb-4">Completion by Area</div>
    <div class="space-y-3">
        @forelse($areas as $area)
            <div>
                <div class="flex justify-between text-sm text-text-muted mb-1">
                    <div>{{ $area['name'] }}</div>
                    <div class="font-medium text-text">{{ $area['percent'] }}%</div>
                </div>
                <div class="w-full bg-surface-muted rounded-full h-3">
                    <div class="h-3 bg-primary rounded-full" style="width: {{ $area['percent'] }}%"></div>
                </div>
            </div>
        @empty
            <p class="text-sm text-text-disabled">No data available</p>
        @endforelse
    </div>
</div>
