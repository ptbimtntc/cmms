{{--
    <x-ui.alert tone="success">Jadwal tersimpan.</x-ui.alert>
    danger uses role=alert so screen readers announce it right away.
--}}
@props([
    'tone' => 'info',
    'title' => null,
])

@php
    $tones = ['success', 'warning', 'danger', 'info'];
    $tone = in_array($tone, $tones, true) ? $tone : 'info';
@endphp

<div {{ $attributes->merge(['class' => "alert alert-{$tone}"]) }} role="{{ $tone === 'danger' ? 'alert' : 'status' }}">
    @if ($title)
        <p class="font-semibold">{{ $title }}</p>
    @endif
    <div @class(['mt-0.5' => $title])>{{ $slot }}</div>
</div>
