{{--
    Per-area active / available counts. Mirrored in JS (areaHTML in
    today-activity-monitor). "available" = area PICs that are NOT inactive.

    Only shows the area(s) actually in scope: when the Area filter narrows
    the board to one area, $area only has that one key (see
    TodayActivityMonitorController::board()), so only that row renders here
   , no misleading "0 ACTIVE / 0 AVAILABLE" row for the excluded area.

    @param array $area  [areaName => ['active'=>int,'available'=>int], ...], one row
    per active Area (see App\Models\Area), or just the filtered one.
--}}
@foreach ($area as $k => $stats)
    <div class="flex items-center justify-between gap-2 text-sm">
        <span class="font-semibold text-text">{{ $k }}</span>
        <span class="tabular-nums text-text-muted">
            <span class="font-semibold text-text">{{ $stats['active'] ?? 0 }}</span> active
            <span aria-hidden="true">/</span>
            <span class="font-semibold text-text">{{ $stats['available'] ?? 0 }}</span> available
        </span>
    </div>
@endforeach
