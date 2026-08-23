<div class="bg-white rounded-xl shadow-sm p-4">
    <div class="flex items-start justify-between mb-1">
        <div>
            <div class="text-sm font-medium text-gray-700">{{ $title }}</div>
            @if(isset($subtitle))
                <div class="text-xs text-gray-400">{{ $subtitle }}</div>
            @endif
        </div>
        @if(isset($filters))
            <div class="space-x-2 text-sm text-gray-500">
                {!! $filters !!}
            </div>
        @endif
    </div>
    <div class="{{ $height ?? 'h-56 md:h-64' }} mt-3">
        <canvas id="{{ $id }}"></canvas>
    </div>
</div>