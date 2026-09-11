{{--
    Left 75% — active-activity TREEMAP. #monitor-treemap always fills the
    whole area, no matter how many activities there are: JS (layoutTiles /
    squarifiedTreemap in today-activity-monitor) measures the container and
    computes exact, gap-free tile rectangles (a squarified treemap — equal
    weight per activity).

    Typography/photo size is NOT set here or by JS — every `.tile-*` class
    is sized by the adaptive CSS (container queries, see the <style> block
    in today-activity-monitor.blade.php) driven by each tile's OWN measured
    width/height, so a huge tile (1-2 activities) gets visibly bigger text
    and a small tile (10-15 activities) shrinks to fit without overflowing.

    This Blade markup is the structural + no-JS fallback; keep every
    `.tile-*` class hook and the overall structure in sync with the JS
    cardHTML() template, or the poll-refreshed board will look different
    from the first paint.

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
@endphp

@if (count($active) === 0)
    <div class="flex h-full items-center justify-center px-6">
        <p class="text-center text-4xl font-black uppercase tracking-[0.2em] text-slate-300 sm:text-5xl">
            No Active Activity
        </p>
    </div>
@else
    {{-- relative + flex-wrap is only the no-JS fallback; JS switches every
         .monitor-tile to position:absolute with computed treemap rects. --}}
    <div id="monitor-treemap" class="relative flex h-full w-full flex-wrap content-start gap-2">
        @foreach ($active as $entry)
            @php($pic = $entry['pic'])
            @php($activity = $entry['activity'])
            @php($src = $activity->source)
            <article
                class="monitor-tile overflow-hidden rounded-2xl border border-slate-200 border-t-4 {{ $srcAccent[$src] ?? 'border-t-slate-300' }} bg-white shadow-sm"
                style="width:220px;height:180px;opacity:0;">
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
@endif
