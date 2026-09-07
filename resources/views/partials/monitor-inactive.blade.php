{{--
    Inactive PIC list — "NAME — Reason". Compact empty state (a single muted
    line) when nobody is inactive. Mirrored in JS (renderInactive).

    @param array $inactive  [['name'=>string,'reason'=>string], ...]
--}}
@forelse ($inactive as $row)
    <div class="flex items-baseline justify-between gap-2">
        <span class="truncate font-semibold uppercase tracking-wide text-slate-700">{{ $row['name'] }}</span>
        <span class="shrink-0 text-slate-500">{{ $row['reason'] }}</span>
    </div>
@empty
    <div class="text-slate-400">None</div>
@endforelse
