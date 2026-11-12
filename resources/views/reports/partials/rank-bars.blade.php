{{--
    Ranked horizontal bars (one hue, magnitude only). Each row names itself and shows its value,
    so the bar is never the only carrier of the number.
    $rows: list of ['label' => string, 'value' => string, 'percent' => float (0-100 bar length), 'href' => ?string, 'badge' => ?string]
    $numbered: bool, prefix a rank number.
--}}
<ol class="space-y-3">
    @foreach ($rows as $row)
        <li>
            @if (! empty($row['href']))
                <a href="{{ $row['href'] }}" class="-mx-2 block rounded-control px-2 py-1 transition-colors hover:bg-surface-muted">
            @endif
            <div class="mb-1 flex items-baseline justify-between gap-3 text-sm">
                <span class="flex min-w-0 items-baseline gap-2">
                    @if (! empty($numbered))
                        <span class="w-5 shrink-0 text-right text-xs tabular-nums text-text-muted">{{ $loop->iteration }}</span>
                    @endif
                    <span class="min-w-0 break-words text-text">{{ $row['label'] }}</span>
                    @if (! empty($row['badge']))
                        <x-ui.badge tone="danger" class="shrink-0">{{ $row['badge'] }}</x-ui.badge>
                    @endif
                </span>
                <span class="shrink-0 font-semibold tabular-nums text-text">{{ $row['value'] }}</span>
            </div>
            <div class="h-2 overflow-hidden rounded-full bg-surface-muted @if (! empty($numbered)) ml-7 @endif">
                <div class="h-full rounded-full bg-chart-1" style="width: {{ (float) $row['percent'] > 0 ? max(min((float) $row['percent'], 100), 2) : 0 }}%"></div>
            </div>
            @if (! empty($row['href']))
                </a>
            @endif
        </li>
    @endforeach
</ol>
