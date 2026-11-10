{{--
    Scroll container so a wide table never pushes the page sideways on a phone.
    <x-ui.table>
        <thead>...</thead>
        <tbody>...</tbody>
    </x-ui.table>
    caption: optional visible caption, also names the table for screen readers.
--}}
@props([
    'caption' => null,
])

<div {{ $attributes->merge(['class' => 'overflow-x-auto rounded-card border border-border bg-surface']) }}>
    <table class="data-table min-w-full">
        @if ($caption)
            <caption class="px-4 py-3 text-left text-sm font-semibold text-text">{{ $caption }}</caption>
        @endif
        {{ $slot }}
    </table>
</div>
