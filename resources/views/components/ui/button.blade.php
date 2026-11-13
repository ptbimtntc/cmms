{{--
    <x-ui.button>Save</x-ui.button>
    <x-ui.button variant="outline" href="{{ route('machines.index') }}">Kembali</x-ui.button>
    Renders <a> when href is given, <button> otherwise. Extra attributes
    (wire:*, x-*, data-*, name, value, form) pass straight through.
--}}
@props([
    'variant' => 'primary',
    'size' => 'md',
    'href' => null,
    'type' => 'button',
])

@php
    $variants = [
        'primary' => 'btn-primary',
        'success' => 'btn-success',
        'danger' => 'btn-danger',
        'outline' => 'btn-outline',
        'ghost' => 'btn-ghost',
    ];

    // 44px minimum touch target on phones and tablets; sm shrinks only from lg (pointer) up.
    $sizes = [
        'sm' => 'btn-sm min-h-11 lg:min-h-9',
        'md' => 'min-h-11',
        'lg' => 'btn-lg min-h-12',
    ];

    $classes = 'btn ' . ($variants[$variant] ?? $variants['primary']) . ' ' . ($sizes[$size] ?? $sizes['md']);
@endphp

@if ($href)
    {{-- href="{{ ... }}" on the component tag arrives already escaped; escaping again with
         {{ $href }} would turn & into &amp;amp; and break query strings. Escape without
         double-encoding instead, which is also safe for a raw :href binding. --}}
    <a href="{!! e($href, false) !!}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</button>
@endif
