<div class="bg-surface border border-border rounded-xl shadow-sm p-6">
    <div class="flex items-center justify-between">
        <div>
            <div class="text-sm text-text-muted">PM Completion</div>
            <div class="mt-1 text-3xl font-bold text-text">{{ $percentage ?? '72%' }}</div>
        </div>
        <div class="text-right text-sm text-text-muted">
            <div>Target: <span class="text-text font-medium">{{ $target ?? 120 }}</span></div>
            <div>Actual: <span class="text-text font-medium">{{ $actual ?? 86 }}</span></div>
            <div>Remaining: <span class="text-text font-medium">{{ $remaining ?? 34 }}</span></div>
        </div>
    </div>

    <div class="mt-6">
        <div class="w-full bg-surface-muted rounded-full h-4 overflow-hidden">
            <div class="h-4 bg-success rounded-full" style="width: {{ $percent_value ?? '72' }}%"></div>
        </div>
    </div>
</div>
