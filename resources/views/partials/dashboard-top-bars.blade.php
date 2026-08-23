<div class="bg-white rounded-xl shadow-sm p-4">
    <div class="flex items-center justify-between mb-4">
        <div class="text-sm font-medium text-gray-700">{{ $title }}</div>
        @if(isset($filters))
            <div class="text-sm text-gray-500">{!! $filters !!}</div>
        @endif
    </div>

    <div class="space-y-3">
        @forelse($items as $it)
            <div>
                <div class="flex justify-between text-sm text-gray-600 mb-1">
                    <div class="truncate">{{ $it['label'] }}</div>
                    <div class="font-medium text-gray-800">{{ $it['value'] }}</div>
                </div>
                <div class="w-full bg-gray-100 rounded-full h-3">
                    <div class="h-3 bg-indigo-500 rounded-full" style="width: {{ $it['percent'] }}%"></div>
                </div>
            </div>
        @empty
            <p class="text-sm text-gray-400">No data available</p>
        @endforelse
    </div>
</div>