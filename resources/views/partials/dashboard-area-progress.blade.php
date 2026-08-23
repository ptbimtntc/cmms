<div class="bg-white rounded-xl shadow-sm p-4">
    <div class="text-sm font-medium text-gray-700 mb-4">Completion by Area</div>
    <div class="space-y-3">
        @forelse($areas as $area)
            <div>
                <div class="flex justify-between text-sm text-gray-600 mb-1">
                    <div>{{ $area['name'] }}</div>
                    <div class="font-medium text-gray-800">{{ $area['percent'] }}%</div>
                </div>
                <div class="w-full bg-gray-100 rounded-full h-3">
                    <div class="h-3 bg-indigo-500 rounded-full" style="width: {{ $area['percent'] }}%"></div>
                </div>
            </div>
        @empty
            <p class="text-sm text-gray-400">No data available</p>
        @endforelse
    </div>
</div>