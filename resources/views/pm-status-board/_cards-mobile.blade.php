{{-- Also rendered alone as JSON for "load next month"; keep it self-contained. --}}
@foreach ($schedules as $schedule)
    <article class="card min-w-0 p-4">
        <div class="flex items-start justify-between gap-3">
            <h2 class="min-w-0 break-words text-base font-semibold text-text">{{ $schedule['machine_number'] }}</h2>
            <div class="flex shrink-0 flex-col items-end gap-1">
                <span class="text-xs text-text-muted">Gap Day</span>
                <span @class([
                    'rounded-full px-2.5 py-0.5 text-sm font-semibold tabular-nums',
                    'bg-red-100 text-red-800' => $schedule['is_gap_day_out_of_range'],
                    'bg-slate-100 text-slate-800' => ! $schedule['is_gap_day_out_of_range'],
                ])>{{ $schedule['gap_day'] > 0 ? '+' : '' }}{{ $schedule['gap_day'] }}</span>
            </div>
        </div>
        <div class="mt-2">@include('pm-status-board._status-cell')</div>

        <dl class="mt-3 grid grid-cols-2 gap-x-4 gap-y-2 border-t border-border pt-3 text-sm">
            <div>
                <dt class="text-xs text-text-muted">Bulan</dt>
                <dd class="font-medium text-text">{{ $schedule['month_label'] }}</dd>
            </div>
            <div>
                <dt class="text-xs text-text-muted">Plan Date</dt>
                <dd class="font-medium text-text">{{ $schedule['plan_date'] }}</dd>
            </div>
            <div class="col-span-2">
                <dt class="text-xs text-text-muted">Actual PM Date</dt>
                <dd class="font-medium text-text">{{ $schedule['actual_date'] ?? '-' }}</dd>
            </div>
        </dl>
    </article>
@endforeach
