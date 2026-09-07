{{--
    Right 25% — Maintenance Status:
      · Active PIC       N ACTIVE / M NOT STARTED / K INACTIVE
      · Activity Distribution  donut (CURRENTLY ACTIVE only)
      · Area Status      WWD/BUL  x ACTIVE / y AVAILABLE
      · Not Started      list
      · Inactive         list (compact empty state)
    JS (renderStatus / render in today-activity-monitor) patches every part
    on each 60s poll. Keep the source palette in sync.

    @param array $distribution     ['PM'=>int, ...]
    @param array $manualBreakdown  [['name'=>string,'count'=>int], ...]
    @param array $active
    @param int   $totalPics
    @param array $notStarted
    @param array $inactive         [['name'=>string,'reason'=>string], ...]
    @param array $area             ['WWD'=>['active'=>int,'available'=>int], 'BUL'=>...]
    @param array $counts           ['active'=>int,'notStarted'=>int,'inactive'=>int]
    @param bool  $isAdmin
--}}
@php
    $fixedOrder = ['PM', 'GREASING', 'OIL_AUDIT', 'OIL_AUDIT_ACTION'];
    $srcLabels = [
        'PM' => 'PM', 'GREASING' => 'Greasing',
        'OIL_AUDIT' => 'Oil Audit', 'OIL_AUDIT_ACTION' => 'Oil Audit Action',
    ];
    $srcHex = [
        'PM' => '#2563eb', 'GREASING' => '#f59e0b', 'OIL_AUDIT' => '#10b981',
        'OIL_AUDIT_ACTION' => '#0891b2', 'MANUAL' => '#8b5cf6',
    ];
@endphp

<div class="flex h-full flex-col gap-3 p-3">
    <div class="shrink-0 text-xs font-bold uppercase tracking-[0.3em] text-slate-400">Maintenance Status</div>

    {{-- 1 — Active PIC --}}
    <div class="shrink-0 text-[10px] font-semibold uppercase tracking-[0.25em] text-slate-400">Active PIC</div>
    <div class="grid shrink-0 grid-cols-3 gap-2">
        <div class="rounded-xl border border-slate-200 bg-white p-2 text-center shadow-sm">
            <div id="stat-active" class="text-2xl font-black tabular-nums leading-none text-emerald-600">{{ $counts['active'] }}</div>
            <div class="mt-1 text-[9px] font-semibold uppercase tracking-wider text-slate-400">Active</div>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-2 text-center shadow-sm">
            <div id="stat-notstarted" class="text-2xl font-black tabular-nums leading-none text-slate-800">{{ $counts['notStarted'] }}</div>
            <div class="mt-1 text-[9px] font-semibold uppercase tracking-wider text-slate-400">Not Started</div>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-2 text-center shadow-sm">
            <div id="stat-inactive" class="text-2xl font-black tabular-nums leading-none text-slate-400">{{ $counts['inactive'] }}</div>
            <div class="mt-1 text-[9px] font-semibold uppercase tracking-wider text-slate-400">Inactive</div>
        </div>
    </div>

    {{-- 2 — Activity Distribution --}}
    <div class="shrink-0 rounded-2xl border border-slate-200 bg-white p-3 shadow-sm">
        <div class="text-[10px] font-semibold uppercase tracking-[0.25em] text-slate-400">Activity Distribution</div>
        <div class="mt-2 flex items-center gap-3">
            <div id="monitor-donut" data-dist='@json($distribution)' class="shrink-0"></div>
            <div id="monitor-legend" data-manual='@json($manualBreakdown)' class="min-w-0 flex-1 space-y-1">
                @foreach ($fixedOrder as $k)
                    <div class="flex items-center justify-between gap-2 text-xs">
                        <span class="flex min-w-0 items-center gap-2">
                            <span class="h-2.5 w-2.5 shrink-0 rounded-full" style="background: {{ $srcHex[$k] }}"></span>
                            <span class="truncate uppercase tracking-wider text-slate-600">{{ $srcLabels[$k] }}</span>
                        </span>
                        <span class="shrink-0 tabular-nums font-bold text-slate-900">{{ $distribution[$k] ?? 0 }}</span>
                    </div>
                @endforeach
                @foreach ($manualBreakdown as $m)
                    <div class="flex items-center justify-between gap-2 text-xs">
                        <span class="flex min-w-0 items-center gap-2">
                            <span class="h-2.5 w-2.5 shrink-0 rounded-full" style="background: {{ $srcHex['MANUAL'] }}"></span>
                            <span class="truncate uppercase tracking-wider text-slate-600">{{ $m['name'] }}</span>
                        </span>
                        <span class="shrink-0 tabular-nums font-bold text-slate-900">{{ $m['count'] }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- 3 — Area Status --}}
    <div class="shrink-0 rounded-2xl border border-slate-200 bg-white p-3 shadow-sm">
        <div class="text-[10px] font-semibold uppercase tracking-[0.25em] text-slate-400">Area Status</div>
        <div id="monitor-area" class="mt-2 space-y-1">
            @include('partials.monitor-area', ['area' => $area])
        </div>
    </div>

    {{-- 4 + 5 — Not Started + Inactive (share the remaining height) --}}
    <div class="flex min-h-0 flex-1 flex-col gap-3">
        <div class="flex min-h-0 flex-1 flex-col rounded-2xl border border-slate-200 bg-white p-3 shadow-sm">
            <div class="shrink-0 text-[10px] font-semibold uppercase tracking-[0.25em] text-slate-400">Not Started</div>
            <p id="monitor-notstarted"
                class="mt-1.5 min-h-0 flex-1 overflow-hidden text-sm font-semibold uppercase leading-relaxed tracking-wide text-slate-700">
                @include('partials.monitor-notstarted', ['notStarted' => $notStarted, 'totalPics' => $totalPics])
            </p>
        </div>

        <div class="flex min-h-0 flex-col rounded-2xl border border-slate-200 bg-white p-3 shadow-sm {{ count($inactive) ? 'flex-1' : 'shrink-0' }}">
            <div class="shrink-0 text-[10px] font-semibold uppercase tracking-[0.25em] text-slate-400">Inactive</div>
            <div id="monitor-inactive" class="mt-1.5 min-h-0 flex-1 space-y-1 overflow-hidden text-xs">
                @include('partials.monitor-inactive', ['inactive' => $inactive])
            </div>
        </div>
    </div>

    @if ($isAdmin)
        <div class="flex shrink-0 items-center justify-between gap-2 text-[10px] font-semibold uppercase tracking-[0.2em] text-slate-500">
            <span>Auto Refresh</span>
            <span class="flex items-center gap-2">
                <button type="button" id="auto-refresh-toggle" role="switch" aria-checked="true"
                    class="relative inline-flex h-5 w-9 items-center rounded-full bg-slate-200 transition-colors">
                    <span id="auto-refresh-knob"
                        class="inline-block h-4 w-4 translate-x-0.5 rounded-full bg-white shadow transition-transform"></span>
                </button>
                <span id="auto-refresh-state" class="w-7 font-bold text-slate-600">ON</span>
            </span>
        </div>
    @endif
</div>
