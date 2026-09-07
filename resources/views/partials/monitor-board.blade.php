{{--
    Left 75% — active-activity cards. The auto-refresh poll rebuilds the
    equivalent markup in JS (cardHTML/render in today-activity-monitor); keep
    the two in sync, including the source palette.

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
    <div class="grid content-start gap-3 grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
        @foreach ($active as $entry)
            @php($pic = $entry['pic'])
            @php($activity = $entry['activity'])
            @php($src = $activity->source)
            <article
                class="flex min-h-[11rem] flex-col items-center justify-center gap-1 overflow-hidden rounded-2xl border border-slate-200 border-t-4 {{ $srcAccent[$src] ?? 'border-t-slate-300' }} bg-white p-3 text-center leading-tight shadow-sm">
                @if ($pic->photo_url)
                    <img src="{{ $pic->photo_url }}" alt="{{ $pic->name }}"
                        class="h-16 w-16 shrink-0 rounded-xl object-cover ring-2 ring-slate-200 2xl:h-20 2xl:w-20">
                @else
                    <div
                        class="flex h-16 w-16 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-xl font-bold text-slate-500 ring-2 ring-slate-200 2xl:h-20 2xl:w-20 2xl:text-2xl">
                        {{ $pic->initials() }}
                    </div>
                @endif

                {{-- Name — no caption --}}
                <div class="w-full truncate pt-1 text-base font-bold uppercase tracking-wide text-slate-900 2xl:text-lg">
                    {{ $pic->name }}
                </div>

                {{-- Activity (a bit smaller than the name) --}}
                <div class="w-full">
                    <div class="text-[9px] font-semibold uppercase tracking-[0.2em] text-slate-400">Activity</div>
                    <div class="truncate text-sm font-semibold uppercase tracking-[0.12em] {{ $srcText[$src] ?? 'text-blue-600' }} 2xl:text-base">
                        {{ $activity->displayLabel() }}
                    </div>
                </div>

                {{-- Location (same size as the name) --}}
                @if ($activity->locationLabel())
                    <div class="w-full">
                        <div class="text-[9px] font-semibold uppercase tracking-[0.2em] text-slate-400">Location</div>
                        <div class="truncate font-mono text-base font-semibold text-slate-600 2xl:text-lg">
                            {{ $activity->locationLabel() }}
                        </div>
                    </div>
                @endif

                {{-- Started (small) --}}
                <div class="w-full">
                    <div class="text-[9px] font-semibold uppercase tracking-[0.2em] text-slate-400">Started</div>
                    <div class="text-xs font-bold tabular-nums text-emerald-600 2xl:text-sm">
                        {{ $activity->startedAt->format('H:i') }}
                    </div>
                </div>
            </article>
        @endforeach
    </div>
@endif
