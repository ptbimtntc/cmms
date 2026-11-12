{{--
    Focal point of the dashboard: PM closing for the current month.
    The gradient bar is the logo-bar motif (DESIGN.md); the tick marks the
    closing target so the gap to target reads without a second chart.
    Params: percent_value, target, actual, remaining, targetPercent.
--}}
@php
    $percent = (float) ($percent_value ?? 0);
    $barWidth = min(max($percent, 0), 100);
    $targetPercent = (int) ($targetPercent ?? 0);
@endphp

<section class="card p-5 sm:p-6" aria-labelledby="pm-closing-heading">
    <div class="flex flex-col gap-6 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h2 id="pm-closing-heading" class="text-sm font-medium text-text-muted">PM closing, {{ now()->format('F Y') }}</h2>
            <p class="mt-1 font-signage text-6xl font-semibold leading-none text-text sm:text-7xl">{{ $percent_value ?? 0 }}%</p>
        </div>

        <dl class="grid grid-cols-3 gap-x-6 text-sm sm:text-right">
            <div>
                <dt class="text-text-muted">Target</dt>
                <dd class="mt-0.5 font-signage text-2xl font-semibold text-text">{{ $target ?? 0 }}</dd>
            </div>
            <div>
                <dt class="text-text-muted">Actual</dt>
                <dd class="mt-0.5 font-signage text-2xl font-semibold text-text">{{ $actual ?? 0 }}</dd>
            </div>
            <div>
                <dt class="text-text-muted">Remaining</dt>
                <dd class="mt-0.5 font-signage text-2xl font-semibold text-text">{{ $remaining ?? 0 }}</dd>
            </div>
        </dl>
    </div>

    <div class="relative mt-6">
        <div class="h-3 overflow-hidden rounded-full bg-surface-muted"
            role="progressbar" aria-label="PM closing this month"
            aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ (int) round($barWidth) }}">
            <div class="h-full rounded-full bg-linear-to-r from-brand-from to-brand-to motion-safe:transition-[width] motion-safe:duration-500"
                style="width: {{ $barWidth }}%"></div>
        </div>
        @if ($targetPercent > 0)
            <div class="pointer-events-none absolute -top-1 h-5 w-0.5 rounded-full bg-text" style="left: calc({{ $targetPercent }}% - 1px)" aria-hidden="true"></div>
        @endif
    </div>

    <p class="mt-3 text-xs text-text-muted">
        @if (($target ?? 0) > 0)
            Finished PM out of PM planned this month. Target line at {{ $targetPercent }}%.
        @else
            No PM planned for this month yet. Closing starts counting once PM is scheduled.
        @endif
    </p>
</section>
