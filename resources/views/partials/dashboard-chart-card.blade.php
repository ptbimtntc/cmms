<div class="bg-surface border border-border rounded-xl shadow-sm p-4 flex flex-col {{ $height ?? 'h-56 md:h-64' }}">
    <div class="flex items-start justify-between mb-1">
        <div>
            <div class="text-sm font-medium text-text">{{ $title }}</div>
            @if(isset($subtitle))
                <div class="text-xs text-text-muted">{{ $subtitle }}</div>
            @endif
        </div>
        @if(isset($filters))
            <div class="space-x-2 text-sm text-text-muted">
                {!! $filters !!}
            </div>
        @endif
    </div>
    <div class="flex-1 min-h-0 mt-3">
        <canvas id="{{ $id }}" class="block h-full w-full"></canvas>
    </div>
</div>
