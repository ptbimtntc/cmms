{{--
    FreeDOMS logo, drawn from the brand SVG supplied by the owner.
    framed: keep the soft slate frame (use on dark or busy backgrounds).
    mono:   single colour via currentColor (use on photos or brand-coloured fills).
    Default sizing is by height; width follows the 2.16:1 aspect ratio.
--}}
@props([
    'framed' => true,
    'mono' => false,
    'label' => 'FreeDOMS',
])

@php
    $uid = 'bl' . substr(md5(uniqid('', true)), 0, 8);
    $ink = $mono ? 'currentColor' : '#1E293B';
    $viewBox = $framed ? '37 47 1006 406' : '103 97 834 306';
    $fill = fn (string $name) => $mono ? 'currentColor' : "url(#{$uid}-{$name})";
@endphp

<svg {{ $attributes->merge(['class' => 'h-8 w-auto']) }}
     viewBox="{{ $viewBox }}" fill="none" xmlns="http://www.w3.org/2000/svg"
     @unless ($attributes->has('aria-hidden')) role="img" aria-label="{{ $label }}" @endunless>
    @unless ($mono)
        <defs>
            <linearGradient id="{{ $uid }}-people" x1="560" y1="0" x2="937" y2="0" gradientUnits="userSpaceOnUse">
                <stop offset="0%" stop-color="#0EA5E9" />
                <stop offset="100%" stop-color="#10B981" />
            </linearGradient>
            <linearGradient id="{{ $uid }}-process" x1="560" y1="0" x2="905" y2="0" gradientUnits="userSpaceOnUse">
                <stop offset="0%" stop-color="#0284C7" />
                <stop offset="100%" stop-color="#059669" />
            </linearGradient>
            <linearGradient id="{{ $uid }}-tech" x1="560" y1="0" x2="937" y2="0" gradientUnits="userSpaceOnUse">
                <stop offset="0%" stop-color="#1E40AF" />
                <stop offset="100%" stop-color="#047857" />
            </linearGradient>
        </defs>
    @endunless

    @if ($framed)
        <rect x="40" y="50" width="1000" height="400" rx="68"
              fill="{{ $mono ? 'none' : '#F8FAFC' }}" stroke="{{ $mono ? 'currentColor' : '#CBD5E1' }}" stroke-width="6" />
    @endif

    <g stroke="{{ $ink }}" stroke-width="63" stroke-linecap="round" stroke-linejoin="round">
        <path d="M134.5 371.5V128.5H278.5" />
        <path d="M134.5 252.5H224.5" />
        <path d="M357.5 371.5V128.5H446C483.279 128.5 513.5 158.721 513.5 196C513.5 233.279 483.279 263.5 446 263.5H357.5" />
        <path d="M437 263.5L514.5 371.5" />
    </g>

    {{-- Two "E"s, three bars each: people, process, technology. --}}
    @foreach ([560, 754] as $x)
        <rect x="{{ $x }}" y="97" width="183" height="63" rx="31.5" fill="{{ $fill('people') }}" />
        <rect x="{{ $x }}" y="221" width="150" height="63" rx="31.5" fill="{{ $fill('process') }}" />
        <rect x="{{ $x }}" y="340" width="183" height="63" rx="31.5" fill="{{ $fill('tech') }}" />
    @endforeach
</svg>
