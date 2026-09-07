{{--
    Server-side render of the monitor board. The auto-refresh poll rebuilds
    the equivalent markup in JS (cardHTML/render in today-activity-monitor),
    so keep the two in sync.

    @param iterable $active  list of ['pic' => User, 'activity' => ActiveActivity]
--}}
@if (count($active) === 0)
    <div class="flex h-full items-center justify-center px-6">
        <p class="text-center text-4xl font-black uppercase tracking-[0.2em] text-slate-700 sm:text-5xl">
            No Active Activity
        </p>
    </div>
@else
    <div class="grid h-full auto-rows-fr grid-cols-2 gap-2 sm:grid-cols-3 sm:gap-3 lg:grid-cols-5">
        @foreach ($active as $entry)
            @php($pic = $entry['pic'])
            @php($activity = $entry['activity'])
            <article
                class="flex min-h-0 flex-col items-center justify-center gap-1 overflow-hidden rounded-2xl bg-slate-900 p-2 text-center leading-tight ring-1 ring-slate-800 sm:p-3">
                @if ($pic->photo_url)
                    <img src="{{ $pic->photo_url }}" alt="{{ $pic->name }}"
                        class="h-12 w-12 shrink-0 rounded-xl object-cover ring-2 ring-slate-700 sm:h-16 sm:w-16 lg:h-20 lg:w-20">
                @else
                    <div
                        class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-slate-800 text-lg font-bold text-slate-300 ring-2 ring-slate-700 sm:h-16 sm:w-16 sm:text-xl lg:h-20 lg:w-20 lg:text-2xl">
                        {{ $pic->initials() }}
                    </div>
                @endif

                <div class="w-full truncate text-sm font-bold uppercase tracking-wide text-white sm:text-base lg:text-lg">
                    {{ $pic->name }}
                </div>

                <div class="w-full truncate text-[10px] font-semibold uppercase tracking-[0.15em] text-sky-400 sm:text-xs">
                    {{ $activity->displayLabel() }}
                </div>

                @if ($activity->locationLabel())
                    <div class="w-full truncate font-mono text-[11px] text-slate-300 sm:text-sm">
                        {{ $activity->locationLabel() }}
                    </div>
                @endif

                <div class="mt-0.5 shrink-0 text-sm font-bold tabular-nums text-emerald-400 sm:text-base lg:text-lg">
                    {{ $activity->startedAt->format('H:i') }}
                </div>
            </article>
        @endforeach
    </div>
@endif
