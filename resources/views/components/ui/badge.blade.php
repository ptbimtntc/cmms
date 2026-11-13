{{--
    Status label only (Done, Overdue, Waiting). Not for decoration.
    <x-ui.badge tone="danger">Terlambat</x-ui.badge>
--}}
@props([
    'tone' => 'neutral',
])

@php
    $tones = ['primary', 'success', 'warning', 'danger', 'info', 'neutral'];
    $tone = in_array($tone, $tones, true) ? $tone : 'neutral';
@endphp

<span {{ $attributes->merge(['class' => "badge badge-{$tone}"]) }}>{{ $slot }}</span>
