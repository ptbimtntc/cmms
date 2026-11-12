{{--
    Right 25%, Maintenance Status:
      · Active PIC       N ACTIVE / M NOT STARTED / K INACTIVE
      · Activity Distribution  donut (CURRENTLY ACTIVE only)
      · Area Status      one row per active Area  x ACTIVE / y AVAILABLE
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
    @param array $area             [areaName => ['active'=>int,'available'=>int], ...]
    @param array $counts           ['active'=>int,'notStarted'=>int,'inactive'=>int]
    @param bool  $isAdmin
--}}
@php
    $fixedOrder = ['PM', 'GREASING', 'SMALL_PM', 'OIL_AUDIT', 'OIL_AUDIT_ACTION', 'PREDICTIVE'];
    $srcLabels = [
        'PM' => 'PM', 'GREASING' => 'Greasing', 'SMALL_PM' => 'Small PM',
        'OIL_AUDIT' => 'Oil Audit', 'OIL_AUDIT_ACTION' => 'Oil Audit Action',
        'PREDICTIVE' => 'Action Predictive',
    ];
    // Same --src-* palette as the tiles (defined on #monitor in today-activity-monitor).
    $srcColor = fn (string $k): string => "var(--src-{$k})";
    // Manual activities don't share one "Manual" color, each DISTINCT
    // activity NAME gets its own deterministic color (same name always maps
    // to the same hue; different names always differ). Mirrored in JS
    // (manualColor() in today-activity-monitor.blade.php), which repaints
    // this on load anyway, this is only the pre-JS SSR fallback.
    // Same hash as manualColor() in the page script (31-multiplier over UTF-16 code units, unsigned
    // 32-bit), so the server paint, the poll repaint, the tile accent and the legend all agree.
    $manualColor = function (string $name): string {
        $hash = 0;
        foreach (unpack('n*', mb_convert_encoding($name, 'UTF-16BE', 'UTF-8')) ?: [] as $unit) {
            $hash = ($hash * 31 + $unit) & 0xFFFFFFFF;
        }

        return sprintf('hsl(%d, 65%%, 45%%)', $hash % 360);
    };
@endphp

@php($caption = 'text-sm font-semibold text-text-muted')
<div class="flex h-full flex-col gap-3 p-4">
    <h2 class="shrink-0 font-signage text-xl font-semibold text-text">Maintenance Status</h2>

    {{-- 1. Active PIC --}}
    <section class="shrink-0" aria-labelledby="monitor-active-pic">
        <h3 id="monitor-active-pic" class="{{ $caption }}">Active PIC</h3>
        <dl class="mt-2 grid grid-cols-3 gap-2">
            <div class="rounded-card border border-border bg-surface flex flex-col-reverse px-2 py-3 text-center">
                <dt class="mt-1 text-xs font-medium text-text-muted">Active</dt>
                <dd id="stat-active" class="font-signage text-4xl font-semibold tabular-nums leading-none text-success-hover">{{ $counts['active'] }}</dd>
            </div>
            <div class="rounded-card border border-border bg-surface flex flex-col-reverse px-2 py-3 text-center">
                <dt class="mt-1 text-xs font-medium text-text-muted">Not Started</dt>
                <dd id="stat-notstarted" class="font-signage text-4xl font-semibold tabular-nums leading-none text-text">{{ $counts['notStarted'] }}</dd>
            </div>
            <div class="rounded-card border border-border bg-surface flex flex-col-reverse px-2 py-3 text-center">
                <dt class="mt-1 text-xs font-medium text-text-muted">Inactive</dt>
                <dd id="stat-inactive" class="font-signage text-4xl font-semibold tabular-nums leading-none text-text-muted">{{ $counts['inactive'] }}</dd>
            </div>
        </dl>
    </section>

    {{-- 2. Activity Distribution --}}
    <section class="shrink-0 rounded-card border border-border bg-surface p-3">
        <h3 class="{{ $caption }}">Activity Distribution</h3>
        <div class="mt-2 flex items-center gap-3">
            <div id="monitor-donut" data-dist='@json($distribution)' class="shrink-0"></div>
            <div id="monitor-legend" data-manual='@json($manualBreakdown)' class="min-w-0 flex-1 space-y-1.5">
                @foreach ($fixedOrder as $k)
                    @continue(($distribution[$k] ?? 0) === 0)
                    <div class="flex items-center justify-between gap-2 text-sm">
                        <span class="flex min-w-0 items-center gap-2">
                            <span class="h-3 w-3 shrink-0 rounded-full" style="background: {{ $srcColor($k) }}"></span>
                            <span class="truncate text-text">{{ $srcLabels[$k] }}</span>
                        </span>
                        <span class="shrink-0 font-semibold tabular-nums text-text">{{ $distribution[$k] }}</span>
                    </div>
                @endforeach
                @foreach ($manualBreakdown as $m)
                    <div class="flex items-center justify-between gap-2 text-sm">
                        <span class="flex min-w-0 items-center gap-2">
                            <span class="h-3 w-3 shrink-0 rounded-full" style="background: {{ $manualColor($m['name']) }}"></span>
                            <span class="truncate text-text">{{ $m['name'] }}</span>
                        </span>
                        <span class="shrink-0 font-semibold tabular-nums text-text">{{ $m['count'] }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- 3. Area Status --}}
    <section class="shrink-0 rounded-card border border-border bg-surface p-3">
        <h3 class="{{ $caption }}">Area Status</h3>
        <div id="monitor-area" class="mt-2 space-y-1">
            @include('partials.monitor-area', ['area' => $area])
        </div>
    </section>

    {{-- 4 + 5. Not Started + Inactive (share the remaining height) --}}
    <div class="flex min-h-0 flex-1 flex-col gap-3">
        <section class="flex min-h-0 flex-1 flex-col rounded-card border border-border bg-surface p-3">
            <h3 class="shrink-0 {{ $caption }}">Not Started</h3>
            <p id="monitor-notstarted"
                class="mt-1.5 min-h-0 flex-1 overflow-hidden text-base font-semibold leading-relaxed text-text">
                @include('partials.monitor-notstarted', ['notStarted' => $notStarted, 'totalPics' => $totalPics])
            </p>
        </section>

        {{-- data-monitor-inactive-box: the poll grows this box only when someone is inactive. --}}
        <section data-monitor-inactive-box class="flex min-h-0 flex-col rounded-card border border-border bg-surface p-3 {{ count($inactive) ? 'flex-1' : 'shrink-0' }}">
            <h3 class="shrink-0 {{ $caption }}">Inactive</h3>
            <div id="monitor-inactive" class="mt-1.5 min-h-0 flex-1 space-y-1 overflow-hidden text-sm">
                @include('partials.monitor-inactive', ['inactive' => $inactive])
            </div>
        </section>
    </div>

    @if ($isAdmin)
        <div class="flex shrink-0 items-center justify-between gap-2 text-sm font-medium text-text-muted">
            <span id="auto-refresh-label">Auto Refresh</span>
            <span class="flex items-center gap-2">
                <button type="button" id="auto-refresh-toggle" role="switch" aria-checked="true" aria-labelledby="auto-refresh-label"
                    class="relative inline-flex h-5 w-9 items-center rounded-full bg-border-strong transition-colors">
                    <span id="auto-refresh-knob"
                        class="inline-block h-4 w-4 translate-x-0.5 rounded-full bg-white shadow transition-transform"></span>
                </button>
                <span id="auto-refresh-state" class="w-7 font-semibold text-text">ON</span>
            </span>
        </div>
    @endif
</div>
