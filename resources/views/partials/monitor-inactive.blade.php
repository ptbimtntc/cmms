{{--
    Inactive PIC list, "NAME: Reason". Compact empty state (a single muted
    line) when nobody is inactive. Mirrored in JS (renderInactive).

    @param array $inactive  [['name'=>string,'reason'=>string], ...]
--}}
@forelse ($inactive as $row)
    <div class="flex items-baseline justify-between gap-2">
        <span class="truncate font-semibold text-text">{{ $row['name'] }}</span>
        <span class="shrink-0 text-text-muted">{{ $row['reason'] }}</span>
    </div>
@empty
    <div class="text-text-muted">None</div>
@endforelse
