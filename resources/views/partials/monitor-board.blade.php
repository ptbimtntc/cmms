{{--
    Left 75%, active-activity structured grid. #monitor-treemap always
    fills the whole area, no matter how many activities there are: the
    board is grouped into rows via a deterministic rows x cols table keyed
    off the activity count (1->1x1, 2->2x1, 3-4->2x2, 5-6->3x2, 7-9->3x3,
    10-12->4x3, 13-16->4x4, ...), then laid out with plain CSS flexbox ,
    every row gets equal height, and every tile in a row shares that row's
    width equally. An incomplete last row (e.g. 11 -> rows of [4, 4, 3])
    still flexes to the FULL row width, so there is never any blank space.
    This is mirrored in JS (gridForCount()/rowSizesForCount() in
    today-activity-monitor.blade.php) for the poll-rebuilt board, both
    must stay in sync.

    Typography/photo size is NOT set here or by JS, every `.tile-*` class
    is sized by the adaptive CSS (container queries, see the <style> block
    in today-activity-monitor.blade.php) driven by each tile's OWN measured
    width/height, so a huge tile (1-2 activities) gets visibly bigger text
    and a small tile (13-16 activities) shrinks to fit without overflowing.

    This Blade markup is the structural + no-JS fallback; keep every
    `.tile-*` class hook and the overall structure in sync with the JS
    cardHTML()/boardHTML() templates, or the poll-refreshed board will look
    different from the first paint.

    @param iterable $active  list of ['pic' => User, 'activity' => ActiveActivity]
--}}
@php
    // The top accent colour comes from .src-{SOURCE} (the --src-* palette in today-activity-monitor).
    $knownSources = ['PM', 'GREASING', 'SMALL_PM', 'OIL_AUDIT', 'OIL_AUDIT_ACTION', 'PREDICTIVE', 'MANUAL'];
    // Same hash as manualColor() in the page script (31-multiplier over UTF-16 code units, unsigned
    // 32-bit), so the server paint, the poll repaint, the tile accent and the legend all agree.
    $manualColor = function (string $name): string {
        $hash = 0;
        foreach (unpack('n*', mb_convert_encoding($name, 'UTF-16BE', 'UTF-8')) ?: [] as $unit) {
            $hash = ($hash * 31 + $unit) & 0xFFFFFFFF;
        }

        return sprintf('hsl(%d, 65%%, 45%%)', $hash % 360);
    };

    // Deterministic rows x cols for N equal-weight tiles, see the JS
    // gridForCount() docblock in today-activity-monitor.blade.php for the
    // full explanation of the stepped-capacity table this walks.
    $monitorGridForCount = function (int $n): array {
        if ($n <= 0) {
            return [0, 0];
        }
        $k = 1;
        while (true) {
            $half = intdiv($k, 2);
            $rows = ($k % 2 === 1) ? $half + 1 : $half;
            $cols = $half + 1;
            if ($rows * $cols >= $n) {
                return [$rows, $cols];
            }
            $k++;
        }
    };

    $activeList = collect($active)->values();
    $count = $activeList->count();
    [$gridRows, $gridCols] = $monitorGridForCount($count);

    $rows = [];
    $remaining = $count;
    $idx = 0;
    for ($r = 0; $r < $gridRows; $r++) {
        $take = ($r === $gridRows - 1) ? $remaining : $gridCols;
        $rows[] = $activeList->slice($idx, $take)->values();
        $idx += $take;
        $remaining -= $take;
    }
@endphp

@if ($count === 0)
    <div class="flex h-full items-center justify-center px-6">
        <p class="text-center font-signage text-5xl font-semibold text-text-muted">No Active Activity</p>
    </div>
@else
    <div id="monitor-treemap" class="flex h-full w-full flex-col gap-2">
        @foreach ($rows as $rowItems)
            <div class="tile-row flex min-h-0 flex-1 gap-2">
                @foreach ($rowItems as $entry)
                    @php($pic = $entry['pic'])
                    @php($activity = $entry['activity'])
                    @php($src = $activity->source)
                    <article
                        @if ($src === 'MANUAL') style="--src: {{ $manualColor($activity->displayLabel()) }}" @endif
                        class="monitor-tile {{ in_array($src, $knownSources, true) ? 'src-'.$src : '' }} flex-1 min-w-0 overflow-hidden rounded-card border border-border bg-surface shadow-sm">
                        <div class="tile-inner flex h-full w-full flex-col items-center justify-center overflow-hidden text-center leading-tight">
                            @if ($pic->photo_url)
                                <img src="{{ $pic->photo_url }}" alt="{{ $pic->name }}"
                                    class="tile-photo shrink-0 rounded-xl object-cover ring-2 ring-border">
                            @else
                                <div class="tile-photo flex shrink-0 items-center justify-center rounded-xl bg-surface-muted font-semibold text-text-muted ring-2 ring-border">
                                    {{ $pic->initials() }}
                                </div>
                            @endif

                            <div class="tile-name w-full truncate pt-1 font-semibold text-text">
                                {{ $pic->name }}
                            </div>

                            <div class="w-full">
                                <div class="tile-activity-cap font-medium text-text-muted">Activity</div>
                                <div class="tile-activity truncate font-semibold text-text">
                                    {{ $activity->displayLabel() }}
                                </div>
                            </div>

                            @if ($activity->locationLabel())
                                <div class="w-full">
                                    <div class="tile-location-cap font-medium text-text-muted">Location</div>
                                    <div class="tile-location truncate font-mono font-semibold text-text">
                                        {{ $activity->locationLabel() }}
                                    </div>
                                </div>
                            @endif

                            <div class="w-full">
                                <div class="tile-started-cap font-medium text-text-muted">Started</div>
                                <div class="tile-started font-signage font-semibold tabular-nums text-text">
                                    {{ $activity->startedAt->format('H:i') }}
                                </div>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        @endforeach
    </div>
@endif
