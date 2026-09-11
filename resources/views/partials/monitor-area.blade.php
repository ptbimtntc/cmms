{{--
    Per-area active / available counts. Mirrored in JS (areaHTML in
    today-activity-monitor). "available" = area PICs that are NOT inactive.

    Only shows the area(s) actually in scope: when the Area filter narrows
    the board to one area, $area only has that one key (see
    TodayActivityMonitorController::board()), so only that row renders here
    — no misleading "0 ACTIVE / 0 AVAILABLE" row for the excluded area.

    @param array $area  ['WWD'=>['active'=>int,'available'=>int], ...] (WWD and/or BUL)
--}}
@foreach ($area as $k => $stats)
    <div class="flex items-center justify-between gap-2 text-xs">
        <span class="font-bold uppercase tracking-wider text-slate-500">{{ $k }}</span>
        <span class="tabular-nums text-slate-700">
            <span class="font-bold text-emerald-600">{{ $stats['active'] ?? 0 }}</span> ACTIVE
            <span class="text-slate-300">/</span>
            <span class="font-bold text-slate-900">{{ $stats['available'] ?? 0 }}</span> AVAILABLE
        </span>
    </div>
@endforeach
