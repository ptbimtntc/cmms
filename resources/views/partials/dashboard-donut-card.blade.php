{{--
    Status donut with a legend that carries every value, so the chart never
    relies on colour or hover alone. Params: title, subtitle, legend, colors
    (label => hex, overrides the legend's own colour), id, size, height, filters.
--}}
<section class="card flex flex-col p-5 {{ $height ?? '' }}">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h3 class="text-sm font-semibold text-text">{{ $title }}</h3>
            @isset($subtitle)
                <p class="text-xs text-text-muted">{{ $subtitle }}</p>
            @endisset
        </div>
        @isset($filters)
            <div class="flex flex-wrap gap-2">{!! $filters !!}</div>
        @endisset
    </div>

    {{-- Side by side only from xl: in the two-column grid the card is too narrow before that. --}}
    <div class="mt-3 flex flex-1 flex-col items-center justify-center gap-6 xl:flex-row">
        <div class="relative max-w-full shrink-0" style="width: {{ $size ?? 208 }}px; aspect-ratio: 1;">
            <canvas id="{{ $id }}" width="{{ $size ?? 208 }}" height="{{ $size ?? 208 }}" class="h-full w-full" role="img" aria-label="{{ $title }}, see legend for values"></canvas>
        </div>
        @isset($legend)
            <ul class="w-full max-w-64 space-y-1.5">
                @foreach ($legend as $item)
                    <li class="flex items-center justify-between gap-2 text-sm">
                        <span class="flex min-w-0 items-center gap-2 text-text-muted">
                            <span class="h-2.5 w-2.5 shrink-0 rounded-full" style="background-color: {{ $colors[$item['label']] ?? $item['color'] }}" aria-hidden="true"></span>
                            <span class="truncate">{{ $item['label'] }}</span>
                        </span>
                        <span class="shrink-0 font-semibold text-text">{{ $item['value'] }} <span class="font-normal text-text-muted">({{ $item['percent'] }}%)</span></span>
                    </li>
                @endforeach
            </ul>
        @endisset
    </div>
</section>
