{{-- Also rendered alone as JSON for "load next month"; keep it self-contained. --}}
@foreach ($schedules as $schedule)
    <tr>
        <td>@include('pm-status-board._status-cell')</td>
        <td class="whitespace-nowrap">{{ $schedule['month_label'] }}</td>
        <td class="whitespace-nowrap font-semibold text-text">{{ $schedule['machine_number'] }}</td>
        <td class="whitespace-nowrap">{{ $schedule['plan_date'] }}</td>
        <td class="text-center">
            {{-- bg-slate-100 (within ±14) and bg-red-100 (outside) are checked by tests. --}}
            <span class="inline-block rounded-full px-3 py-1 text-sm font-semibold tabular-nums {{ $schedule['is_gap_day_out_of_range'] ? 'bg-red-100 text-red-800' : 'bg-slate-100 text-slate-800' }}">{{ $schedule['gap_day'] > 0 ? '+' : '' }}{{ $schedule['gap_day'] }}</span>
        </td>
        <td class="whitespace-nowrap">{{ $schedule['actual_date'] ?? '-' }}</td>
    </tr>
@endforeach
