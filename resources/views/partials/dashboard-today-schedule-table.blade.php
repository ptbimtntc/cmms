<div class="bg-white rounded-xl shadow-sm p-4 overflow-x-auto">
    <div class="text-sm font-medium text-gray-700 mb-4">Today's PM Schedule</div>

    <table class="w-full text-left text-sm">
        <thead class="text-gray-500">
            <tr>
                <th class="pb-2">Machine Number</th>
                <th class="pb-2">Area</th>
                <th class="pb-2">PIC</th>
                <th class="pb-2">Due Date</th>
                <th class="pb-2">Status</th>
            </tr>
        </thead>
        <tbody class="divide-y">
            @forelse($schedules as $s)
                <tr class="h-12">
                    <td class="py-2">{{ $s['machine'] }}</td>
                    <td class="py-2">{{ $s['area'] }}</td>
                    <td class="py-2">{{ $s['pic'] }}</td>
                    <td class="py-2">{{ $s['due_date'] }}</td>
                    <td class="py-2">
                        @if($s['status'] == 'Completed')
                            <span class="text-xs px-2 py-1 rounded-full bg-green-100 text-green-800">Completed</span>
                        @elseif($s['status'] == 'Pending')
                            <span class="text-xs px-2 py-1 rounded-full bg-yellow-100 text-yellow-800">Pending</span>
                        @else
                            <span class="text-xs px-2 py-1 rounded-full bg-red-100 text-red-800">Overdue</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="py-4 text-center text-gray-400">No data available</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>