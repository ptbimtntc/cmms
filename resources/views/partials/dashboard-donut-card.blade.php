<div class="bg-surface border border-border rounded-xl shadow-sm p-4 flex flex-col {{ $height ?? '' }}">
    <div class="flex items-start justify-between mb-1 gap-2 flex-wrap">
        <div>
            <div class="text-sm font-medium text-text">{{ $title }}</div>
            @if(isset($subtitle))
                <div class="text-xs text-text-muted">{{ $subtitle }}</div>
            @endif
        </div>
        @if(isset($filters))
            <div class="space-x-1.5 text-xs text-text-muted [&_select]:text-xs [&_select]:px-1.5 [&_select]:py-1">
                {!! $filters !!}
            </div>
        @endif
    </div>
    <div class="flex flex-1 flex-col sm:flex-row items-center justify-center gap-6 mt-2">
        <div class="relative shrink-0" style="width: {{ $size ?? 208 }}px; height: {{ $size ?? 208 }}px;">
            <canvas id="{{ $id }}" width="{{ $size ?? 208 }}" height="{{ $size ?? 208 }}" class="max-w-full"></canvas>
        </div>
        @if(isset($legend))
            <div class="w-full max-w-40 space-y-1">
                @foreach($legend as $item)
                    <div class="flex items-center justify-between text-xs">
                        <div class="flex items-center gap-1.5 text-text-muted truncate">
                            <span class="h-2 w-2 rounded-full shrink-0" style="background-color: {{ $item['color'] }}"></span>
                            <span class="truncate">{{ $item['label'] }}</span>
                        </div>
                        <div class="text-text font-medium shrink-0 pl-1">{{ $item['value'] }} <span class="text-text-muted font-normal">({{ $item['percent'] }}%)</span></div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
