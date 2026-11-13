@props([
    'uid' => 'scene',
    'fit' => 'xMaxYMid slice',
    'toggle' => true,
    'still' => false,
    // Auth (layouts/auth/freedoms.blade.php) keeps the original Indonesian
    // labels unchanged; the landing hero passes English ones instead so the
    // page stays one language throughout.
    'pauseLabel' => 'Jeda animasi',
    'playLabel' => 'Putar animasi',
])

@php
    // Wet wire drawing line: the WWD area this system maintains. Four running machines, one flagged for PM (amber stack light).
    $machines = [
        ['x' => 640, 'lit' => 'green'],
        ['x' => 860, 'lit' => 'green'],
        ['x' => 1080, 'lit' => 'amber'],
        ['x' => 1300, 'lit' => 'green'],
    ];
    $lamps = [730, 950, 1170, 1390];

    // Deterministic QR-like modules for the asset tag (decorative, not a scannable code).
    $qr = [];
    for ($r = 0; $r < 13; $r++) {
        for ($c = 0; $c < 13; $c++) {
            $inFinder = ($c < 4 && $r < 4) || ($c > 8 && $r < 4) || ($c < 4 && $r > 8);
            if (! $inFinder && (($c * 7 + $r * 13 + $c * $r) % 5) < 2) {
                $qr[] = [1430 + $c * 10, 660 + $r * 10];
            }
        }
    }
@endphp

@php
    // Only default to relative when the caller has not positioned the scene; .relative is emitted after .absolute and would win.
    $positioned = preg_match('/(^|\s)(absolute|fixed)(\s|$)/', (string) $attributes->get('class'));
@endphp

<div {{ $attributes->merge(['class' => 'factory-scene overflow-hidden bg-blue-950' . ($positioned ? '' : ' relative') . ($still ? ' is-still' : '')]) }} data-scene>

    <svg viewBox="0 0 1600 900" preserveAspectRatio="{{ $fit }}" class="absolute inset-0 h-full w-full" role="img"
        aria-label="Illustration of a wet wire drawing machine line under work lamps, with a QR tag mounted on the nearest machine">

        <defs>
            <linearGradient id="{{ $uid }}-hall" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0" stop-color="#0a2e66" />
                <stop offset="0.75" stop-color="#061c3f" />
            </linearGradient>
            <linearGradient id="{{ $uid }}-glass" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0" stop-color="#6fa5e2" stop-opacity="0.5" />
                <stop offset="1" stop-color="#1e6ecb" stop-opacity="0.06" />
            </linearGradient>
            <linearGradient id="{{ $uid }}-cone" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0" stop-color="#fbd98c" stop-opacity="0.5" />
                <stop offset="1" stop-color="#f59e0b" stop-opacity="0" />
            </linearGradient>
            <linearGradient id="{{ $uid }}-floor" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0" stop-color="#0b3b85" />
                <stop offset="1" stop-color="#061c3f" />
            </linearGradient>
            <linearGradient id="{{ $uid }}-cab" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0" stop-color="#0d47a1" />
                <stop offset="1" stop-color="#0a2e66" />
            </linearGradient>
            <radialGradient id="{{ $uid }}-pool" cx="0.5" cy="0.5" r="0.5">
                <stop offset="0" stop-color="#f9c453" stop-opacity="0.35" />
                <stop offset="1" stop-color="#f9c453" stop-opacity="0" />
            </radialGradient>
            <pattern id="{{ $uid }}-hazard" width="24" height="24" patternUnits="userSpaceOnUse"
                patternTransform="rotate(45)">
                <rect width="24" height="24" fill="#061c3f" />
                <rect width="12" height="24" fill="#f59e0b" />
            </pattern>
        </defs>

        {{-- Back wall: clerestory glazing, roof truss, steel columns --}}
        <g data-depth="0.06">
            <rect x="0" y="-200" width="1600" height="1300" fill="url(#{{ $uid }}-hall)" />
            <rect x="0" y="-200" width="1600" height="290" fill="#061c3f" />
            <polyline points="0,80 100,30 200,80 300,30 400,80 500,30 600,80 700,30 800,80 900,30 1000,80 1100,30 1200,80 1300,30 1400,80 1500,30 1600,80"
                fill="none" stroke="#0b3b85" stroke-width="6" />
            <line x1="0" y1="80" x2="1600" y2="80" stroke="#0b3b85" stroke-width="6" />
            @for ($i = 0; $i < 8; $i++)
                <g>
                    <rect x="{{ $i * 200 + 24 }}" y="100" width="152" height="120" fill="url(#{{ $uid }}-glass)" />
                    <line x1="{{ $i * 200 + 75 }}" y1="100" x2="{{ $i * 200 + 75 }}" y2="220" stroke="#061c3f" stroke-width="4" />
                    <line x1="{{ $i * 200 + 125 }}" y1="100" x2="{{ $i * 200 + 125 }}" y2="220" stroke="#061c3f" stroke-width="4" />
                </g>
            @endfor
            @foreach ([0, 400, 800, 1200] as $cx)
                <rect x="{{ $cx + 2 }}" y="80" width="26" height="650" fill="#0b3b85" />
                <rect x="{{ $cx + 2 }}" y="80" width="5" height="650" fill="#1e6ecb" opacity="0.5" />
            @endforeach
        </g>

        {{-- Far line, kept dark behind the headline --}}
        <g data-depth="0.12">
            @foreach ([60, 200, 340, 480] as $fx)
                <rect x="{{ $fx }}" y="610" width="110" height="110" fill="#0a2e66" />
                <circle cx="{{ $fx + 55 }}" cy="655" r="28" fill="none" stroke="#0b3b85" stroke-width="4" />
                <circle cx="{{ $fx + 95 }}" cy="624" r="4" fill="#3b9040" />
            @endforeach
        </g>

        {{-- Main line: lamps, machines, capstans, running wire --}}
        <g data-depth="0.2">
            @foreach ($lamps as $i => $lx)
                <g class="lamp" style="--d: {{ 0.15 + $i * 0.22 }}s">
                    <polygon points="{{ $lx - 24 }},244 {{ $lx + 24 }},244 {{ $lx + 160 }},722 {{ $lx - 160 }},722"
                        fill="url(#{{ $uid }}-cone)" />
                    <ellipse cx="{{ $lx }}" cy="724" rx="170" ry="20" fill="url(#{{ $uid }}-pool)" />
                    <ellipse cx="{{ $lx }}" cy="242" rx="16" ry="5" fill="#fbd98c" />
                </g>
                <line x1="{{ $lx }}" y1="80" x2="{{ $lx }}" y2="222" stroke="#0b3b85" stroke-width="3" />
                <polygon points="{{ $lx - 30 }},242 {{ $lx + 30 }},242 {{ $lx + 16 }},222 {{ $lx - 16 }},222" fill="#0d47a1" />
            @endforeach

            <rect x="0" y="720" width="1600" height="200" fill="url(#{{ $uid }}-floor)" />
            <rect x="560" y="744" width="1040" height="12" fill="url(#{{ $uid }}-hazard)" opacity="0.85" />

            @foreach ($machines as $m)
                @php $x = $m['x']; @endphp
                <rect x="{{ $x }}" y="500" width="180" height="220" rx="4" fill="url(#{{ $uid }}-cab)" stroke="#1e6ecb" stroke-width="2" />
                <rect x="{{ $x + 18 }}" y="512" width="56" height="26" fill="#061c3f" />
                <line x1="{{ $x + 24 }}" y1="525" x2="{{ $x + 60 }}" y2="525" stroke="#3f86d8" stroke-width="3" />
                @for ($v = 0; $v < 4; $v++)
                    <line x1="{{ $x + 30 }}" y1="{{ 680 + $v * 8 }}" x2="{{ $x + 150 }}" y2="{{ 680 + $v * 8 }}" stroke="#061c3f" stroke-width="3" />
                @endfor

                {{-- Stack light: lit lamp marks the machine state --}}
                <rect x="{{ $x + 148 }}" y="466" width="4" height="34" fill="#3f86d8" />
                <rect x="{{ $x + 143 }}" y="430" width="14" height="12" fill="#c62828" opacity="{{ $m['lit'] === 'red' ? 1 : 0.25 }}" />
                <rect x="{{ $x + 143 }}" y="442" width="14" height="12" fill="#f59e0b" opacity="{{ $m['lit'] === 'amber' ? 1 : 0.25 }}"
                    @if ($m['lit'] === 'amber') class="beacon" @endif />
                <rect x="{{ $x + 143 }}" y="454" width="14" height="12" fill="#52a857" opacity="{{ $m['lit'] === 'green' ? 1 : 0.25 }}" />

                <g class="spin">
                    <circle cx="{{ $x + 90 }}" cy="600" r="52" fill="#0b3b85" stroke="#6fa5e2" stroke-width="4" />
                    <line x1="{{ $x + 90 }}" y1="552" x2="{{ $x + 90 }}" y2="648" stroke="#3f86d8" stroke-width="5" />
                    <line x1="{{ $x + 48 }}" y1="576" x2="{{ $x + 132 }}" y2="624" stroke="#3f86d8" stroke-width="5" />
                    <line x1="{{ $x + 48 }}" y1="624" x2="{{ $x + 132 }}" y2="576" stroke="#3f86d8" stroke-width="5" />
                    <circle cx="{{ $x + 90 }}" cy="600" r="13" fill="#cfe1f6" />
                </g>
            @endforeach

            <line x1="560" y1="548" x2="1600" y2="548" stroke="#9fc3ec" stroke-width="2.5" />
            <line class="wire-run" x1="560" y1="548" x2="1600" y2="548" stroke="#fbd98c" stroke-width="2.5"
                stroke-dasharray="6 22" />
        </g>

        {{-- Foreground machine with nameplate and the QR asset tag --}}
        <g data-depth="0.32">
            <rect x="1110" y="620" width="560" height="320" rx="6" fill="url(#{{ $uid }}-cab)" stroke="#3f86d8" stroke-width="3" />
            <line x1="1114" y1="628" x2="1600" y2="628" stroke="#6fa5e2" stroke-width="3" opacity="0.6" />
            <rect x="1160" y="664" width="200" height="60" rx="3" fill="#e8f1fb" />
            <text x="1176" y="703" font-family="'Barlow Semi Condensed', sans-serif" font-weight="700" font-size="30" fill="#061c3f">WWD</text>
            <text x="1250" y="702" font-family="ui-monospace, monospace" font-size="22" fill="#0d47a1">#30001</text>
            <rect x="1160" y="740" width="200" height="8" fill="#061c3f" />
            <rect x="1160" y="756" width="140" height="8" fill="#061c3f" />
            <rect x="1110" y="880" width="560" height="22" fill="url(#{{ $uid }}-hazard)" />

            <rect x="1420" y="650" width="150" height="150" rx="6" fill="#ffffff" />
            @foreach ([[1430, 660], [1520, 660], [1430, 750]] as [$fx, $fy])
                <rect x="{{ $fx }}" y="{{ $fy }}" width="40" height="40" fill="#061c3f" />
                <rect x="{{ $fx + 6 }}" y="{{ $fy + 6 }}" width="28" height="28" fill="#ffffff" />
                <rect x="{{ $fx + 12 }}" y="{{ $fy + 12 }}" width="16" height="16" fill="#061c3f" />
            @endforeach
            @foreach ($qr as [$qx, $qy])
                <rect x="{{ $qx }}" y="{{ $qy }}" width="10" height="10" fill="#061c3f" />
            @endforeach

            <path class="reticle" d="M1402 668 V632 H1438 M1552 632 H1588 V668 M1588 782 V818 H1552 M1438 818 H1402 V782"
                fill="none" stroke="#f59e0b" stroke-width="6" stroke-linecap="square" />
            <rect class="scanline" x="1412" y="652" width="166" height="4" fill="#f59e0b" />
        </g>

    </svg>

    {{ $slot }}

    @if ($toggle)
        {{-- Ambient motion runs past 5 seconds, so it gets a pause control (R-39). --}}
        <button type="button" data-scene-toggle aria-pressed="false"
            data-pause-label="{{ $pauseLabel }}" data-play-label="{{ $playLabel }}"
            class="scene-toggle absolute bottom-3 right-3 z-20 inline-flex min-h-11 items-center gap-2 rounded-md border border-blue-300/70 bg-blue-950/85 px-3 text-sm font-semibold text-white hover:bg-blue-900">
            <svg viewBox="0 0 24 24" class="h-4 w-4" fill="currentColor" aria-hidden="true">
                <path data-icon-pause d="M6 5h4v14H6zM14 5h4v14h-4z" />
                <path data-icon-play d="M7 5l12 7-12 7z" style="display:none" />
            </svg>
            <span data-scene-label>{{ $pauseLabel }}</span>
        </button>
    @endif

</div>

@once
    <script>
        (() => {
            const KEY = 'freedoms-scene-paused';
            const reduce = window.matchMedia('(prefers-reduced-motion: reduce)');
            let paused = false;
            try { paused = localStorage.getItem(KEY) === '1'; } catch (e) {}

            const apply = () => {
                document.querySelectorAll('[data-scene]').forEach((s) => s.classList.toggle('is-paused', paused));
                document.querySelectorAll('[data-scene-toggle]').forEach((b) => {
                    b.setAttribute('aria-pressed', String(paused));
                    b.querySelector('[data-scene-label]').textContent = paused ? b.dataset.playLabel : b.dataset.pauseLabel;
                    b.querySelector('[data-icon-pause]').style.display = paused ? 'none' : '';
                    b.querySelector('[data-icon-play]').style.display = paused ? '' : 'none';
                });
            };

            // A still scene (no pause control, uid="cta" style usage) declares
            // zero motion; scroll-linked parallax would contradict that, so
            // only scenes that can be paused ever get a transform here.
            const parallax = () => {
                if (reduce.matches) return;
                document.querySelectorAll('[data-scene]:not(.is-still)').forEach((s) => {
                    const top = s.getBoundingClientRect().top;
                    if (top > window.innerHeight || top < -s.offsetHeight) return;
                    s.querySelectorAll('[data-depth]').forEach((g) => {
                        g.style.transform = `translateY(${(-top * parseFloat(g.dataset.depth)).toFixed(1)}px)`;
                    });
                });
            };

            const init = () => {
                document.querySelectorAll('[data-scene-toggle]:not([data-ready])').forEach((b) => {
                    b.dataset.ready = '1';
                    b.addEventListener('click', () => {
                        paused = !paused;
                        try { localStorage.setItem(KEY, paused ? '1' : '0'); } catch (e) {}
                        apply();
                    });
                });
                apply();
                parallax();
            };

            let ticking = false;
            window.addEventListener('scroll', () => {
                if (ticking) return;
                ticking = true;
                requestAnimationFrame(() => { parallax(); ticking = false; });
            }, { passive: true });

            document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', init) : init();
            document.addEventListener('livewire:navigated', init);
        })();
    </script>
@endonce
