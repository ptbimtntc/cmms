<div class="bg-surface border border-border rounded-xl shadow-sm p-4 overflow-x-auto">
    <div class="text-sm font-medium text-text mb-4">Today's PM Schedule</div>

    <table class="w-full text-left text-sm">
        <thead class="text-text-muted">
            <tr>
                <th class="pb-2">Machine Number</th>
                <th class="pb-2">Area</th>
                <th class="pb-2">PIC</th>
                <th class="pb-2">Due Date</th>
                <th class="pb-2">Status</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-border text-text">
            @forelse($schedules as $s)
                <tr class="h-12">
                    <td class="py-2">{{ $s['machine'] }}</td>
                    <td class="py-2">{{ $s['area'] }}</td>
                    <td class="py-2">{{ $s['pic'] }}</td>
                    <td class="py-2">{{ $s['due_date'] }}</td>
                    <td class="py-2">
                        @if($s['status'] == 'Completed')
                            <span class="badge badge-success">Completed</span>
                        @elseif($s['status'] == 'Pending')
                            <span class="badge badge-warning">Pending</span>
                        @else
                            <span class="badge badge-danger">Overdue</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="py-4 text-center text-text-disabled">No data available</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
