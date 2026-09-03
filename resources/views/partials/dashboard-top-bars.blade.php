<div class="bg-surface border border-border rounded-xl shadow-sm p-4">
    <div class="flex items-center justify-between mb-4">
        <div class="text-sm font-medium text-text">{{ $title }}</div>
        @if(isset($filters))
            <div class="text-sm text-text-muted">{!! $filters !!}</div>
        @endif
    </div>

    <div class="space-y-3">
        @forelse($items as $it)
            <div>
                <div class="flex justify-between text-sm text-text-muted mb-1">
                    <div class="truncate">{{ $it['label'] }}</div>
                    <div class="font-medium text-text">{{ $it['value'] }}</div>
                </div>
                <div class="w-full bg-surface-muted rounded-full h-3">
                    <div class="h-3 bg-primary rounded-full" style="width: {{ $it['percent'] }}%"></div>
                </div>
            </div>
        @empty
            <p class="text-sm text-text-disabled">No data available</p>
        @endforelse
    </div>
</div>
