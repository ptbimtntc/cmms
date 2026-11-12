{{-- Canvas chart card. Params: title, subtitle, id, height, summary (screen-reader text for the canvas). --}}
<section class="card flex flex-col p-5 {{ $height ?? 'h-56 md:h-64' }}">
    <div>
        <h3 class="text-sm font-semibold text-text">{{ $title }}</h3>
        @isset($subtitle)
            <p class="text-xs text-text-muted">{{ $subtitle }}</p>
        @endisset
    </div>
    <div class="mt-3 min-h-0 flex-1">
        <canvas id="{{ $id }}" class="block h-full w-full" role="img" aria-label="{{ $summary ?? $title }}"></canvas>
    </div>
</section>
