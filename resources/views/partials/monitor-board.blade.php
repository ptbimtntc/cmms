{{--
    Left 75% — active-activity structured grid. #monitor-treemap always
    fills the whole area, no matter how many activities there are: the
    board is grouped into rows via a deterministic rows x cols table keyed
    off the activity count (1->1x1, 2->2x1, 3-4->2x2, 5-6->3x2, 7-9->3x3,
    10-12->4x3, 13-16->4x4, ...), then laid out with plain CSS flexbox —
    every row gets equal height, and every tile in a row shares that row's
    width equally. An incomplete last row (e.g. 11 -> rows of [4, 4, 3])
    still flexes to the FULL row width, so there is never any blank space.
    This is mirrored in JS (gridForCount()/rowSizesForCount() in
    today-activity-monitor.blade.php) for the poll-rebuilt board — both
    must stay in sync.

    Typography/photo size is NOT set here or by JS — every `.tile-*` class
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
    $srcAccent = [
        'PM' => 'border-blue-600', 'GREASING' => 'border-amber-500',
        'OIL_AUDIT' => 'border-emerald-500', 'OIL_AUDIT_ACTION' => 'border-cyan-600',
        'MANUAL' => 'border-violet-500',
    ];
    $srcText = [
        'PM' => 'text-blue-600', 'GREASING' => 'text-amber-600',
        'OIL_AUDIT' => 'text-emerald-600', 'OIL_AUDIT_ACTION' => 'text-cyan-700',
        'MANUAL' => 'text-violet-600',
    ];

    // Deterministic rows x cols for N equal-weight tiles — see the JS
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
        <p class="text-center text-4xl font-black uppercase tracking-[0.2em] text-slate-300 sm:text-5xl">
            No Active Activity
        </p>
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
                        class="monitor-tile flex-1 min-w-0 overflow-hidden rounded-2xl border border-slate-200 border-t-4 {{ $srcAccent[$src] ?? 'border-t-slate-300' }} bg-white shadow-sm">
                        <div class="tile-inner flex h-full w-full flex-col items-center justify-center overflow-hidden text-center leading-tight">
                            @if ($pic->photo_url)
                                <img src="{{ $pic->photo_url }}" alt="{{ $pic->name }}"
                                    class="tile-photo shrink-0 rounded-xl object-cover ring-2 ring-slate-200">
                            @else
                                <div class="tile-photo flex shrink-0 items-center justify-center rounded-xl bg-slate-100 font-bold text-slate-500 ring-2 ring-slate-200">
                                    {{ $pic->initials() }}
                                </div>
                            @endif

                            <div class="tile-name w-full truncate pt-1 font-bold uppercase tracking-wide text-slate-900">
                                {{ $pic->name }}
                            </div>

                            <div class="w-full">
                                <div class="tile-activity-cap font-semibold uppercase tracking-[0.2em] text-slate-400">Activity</div>
                                <div class="tile-activity truncate font-semibold uppercase tracking-[0.12em] {{ $srcText[$src] ?? 'text-blue-600' }}">
                                    {{ $activity->displayLabel() }}
                                </div>
                            </div>

                            @if ($activity->locationLabel())
                                <div class="w-full">
                                    <div class="tile-location-cap font-semibold uppercase tracking-[0.2em] text-slate-400">Location</div>
                                    <div class="tile-location truncate font-mono font-semibold text-slate-600">
                                        {{ $activity->locationLabel() }}
                                    </div>
                                </div>
                            @endif

                            <div class="w-full">
                                <div class="tile-started-cap font-semibold uppercase tracking-[0.2em] text-slate-400">Started</div>
                                <div class="tile-started font-bold tabular-nums text-emerald-600">
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
