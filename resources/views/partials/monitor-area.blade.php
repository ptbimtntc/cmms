{{--
    Per-area active / available counts. Mirrored in JS (renderArea in
    today-activity-monitor). "available" = area PICs that are NOT inactive.

    @param array $area  ['WWD'=>['active'=>int,'available'=>int], 'BUL'=>...]
--}}
@foreach (['WWD', 'BUL'] as $k)
    <div class="flex items-center justify-between gap-2 text-xs">
        <span class="font-bold uppercase tracking-wider text-slate-500">{{ $k }}</span>
        <span class="tabular-nums text-slate-700">
            <span class="font-bold text-emerald-600">{{ $area[$k]['active'] ?? 0 }}</span> ACTIVE
            <span class="text-slate-300">/</span>
            <span class="font-bold text-slate-900">{{ $area[$k]['available'] ?? 0 }}</span> AVAILABLE
        </span>
    </div>
@endforeach
