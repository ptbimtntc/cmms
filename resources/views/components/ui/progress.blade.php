{{--
    Identity motif: the rounded bar from the logo's "E", filled with the brand
    gradient. Use it for real completion values only (PM done vs planned).
    <x-ui.progress :value="$done" :max="$planned" label="PM selesai bulan ini" />
    The gradient carries no text; the number sits beside the bar.
--}}
@props([
    'value' => 0,
    'max' => 100,
    'label',
    'showValue' => true,
])

@php
    $max = max((float) $max, 1);
    $percent = (int) round(min(max((float) $value, 0), $max) / $max * 100);
@endphp

<div {{ $attributes->merge(['class' => 'grid gap-1.5']) }}>
    <div class="flex items-baseline justify-between gap-3 text-sm">
        <span class="text-text-muted">{{ $label }}</span>
        @if ($showValue)
            <span class="font-signage text-base font-semibold tabular-nums text-text">{{ $percent }}%</span>
        @endif
    </div>
    <div class="h-2.5 overflow-hidden rounded-full bg-surface-muted"
         role="progressbar" aria-label="{{ $label }}"
         aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $percent }}">
        <div class="h-full rounded-full bg-linear-to-r from-brand-from to-brand-to motion-safe:transition-[width] motion-safe:duration-500"
             style="width: {{ $percent }}%"></div>
    </div>
</div>
