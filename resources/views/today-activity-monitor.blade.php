@extends('layouts.guest')

@section('content')
    <div id="monitor"
        data-poll-url="{{ route('monitor.data') }}"
        data-poll-interval="60000"
        data-auto-refresh-default="on"
        class="fixed inset-0 flex flex-col overflow-hidden bg-slate-950 text-slate-100">

        {{-- ---------- Header ---------- --}}
        <header class="flex shrink-0 items-center justify-between border-b border-slate-800 px-6 py-3">
            <div>
                <h1 class="text-lg font-bold uppercase tracking-[0.3em] text-slate-200">Today's Activity</h1>
                <p class="mt-0.5 text-xs uppercase tracking-widest text-slate-500">
                    {{ now()->translatedFormat('l, d F Y') }}
                </p>
            </div>
            <div class="text-right">
                <div id="monitor-clock" class="text-3xl font-bold tabular-nums leading-none text-white">--:--</div>
                <p class="mt-1 text-xs uppercase tracking-widest text-slate-500">
                    <span id="monitor-count" class="font-bold text-emerald-400">{{ count($active) }}</span>
                    / <span id="monitor-total">{{ $totalPics }}</span> active
                </p>
            </div>
        </header>

        {{-- ---------- Board (the only part auto-refresh replaces) ---------- --}}
        <main class="min-h-0 flex-1 p-4">
            <div id="monitor-board" class="h-full">
                @include('partials.monitor-board', ['active' => $active])
            </div>
        </main>

        {{-- ---------- Footer ---------- --}}
        <footer class="flex shrink-0 items-center justify-between gap-4 border-t border-slate-800 px-6 py-2">
            <p id="monitor-notstarted" class="min-w-0 flex-1 truncate text-xs uppercase tracking-widest text-slate-500">
                @include('partials.monitor-notstarted', ['notStarted' => $notStarted, 'totalPics' => $totalPics])
            </p>

            @if ($isAdmin)
                <div class="flex shrink-0 items-center gap-2 text-xs uppercase tracking-widest text-slate-400">
                    <span>Auto Refresh</span>
                    <button type="button" id="auto-refresh-toggle" role="switch" aria-checked="true"
                        class="relative inline-flex h-5 w-9 items-center rounded-full bg-slate-700 transition-colors">
                        <span id="auto-refresh-knob"
                            class="inline-block h-4 w-4 translate-x-0.5 rounded-full bg-white transition-transform"></span>
                    </button>
                    <span id="auto-refresh-state" class="w-7 font-bold text-slate-300">ON</span>
                </div>
            @endif
        </footer>
    </div>

    <script>
        (function () {
            const root = document.getElementById('monitor');

            // --- Live clock (independent of auto-refresh; keeps the board visibly alive) ---
            const clock = document.getElementById('monitor-clock');
            function tick () {
                const d = new Date();
                clock.textContent = String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0');
            }
            tick();
            setInterval(tick, 15000);

            // --- Auto refresh (lightweight data poll, NOT a full page reload) ---
            // Default ON, fixed 60s interval (never faster). The ON/OFF control
            // is ADMIN-only; the choice is stored per-browser so a display an
            // ADMIN set up keeps refreshing with nobody logged in on that screen.
            const KEY = 'freedoms.monitor.autoRefresh';
            const DEFAULT_ON = root.dataset.autoRefreshDefault === 'on';
            const POLL_MS = Math.max(60000, parseInt(root.dataset.pollInterval, 10) || 60000);
            const DATA_URL = root.dataset.pollUrl;

            const board = document.getElementById('monitor-board');
            const notStartedEl = document.getElementById('monitor-notstarted');
            const countEl = document.getElementById('monitor-count');
            const totalEl = document.getElementById('monitor-total');

            let timer = null;

            function readState () {
                try {
                    const v = localStorage.getItem(KEY);
                    return v === null ? DEFAULT_ON : v === 'on';
                } catch (e) { return DEFAULT_ON; }
            }
            function writeState (on) {
                try { localStorage.setItem(KEY, on ? 'on' : 'off'); } catch (e) {}
            }

            function esc (value) {
                const d = document.createElement('div');
                d.textContent = value == null ? '' : String(value);
                return d.innerHTML;
            }

            function cardHTML (item) {
                const photo = item.photo
                    ? `<img src="${esc(item.photo)}" alt="${esc(item.name)}" class="h-12 w-12 shrink-0 rounded-xl object-cover ring-2 ring-slate-700 sm:h-16 sm:w-16 lg:h-20 lg:w-20">`
                    : `<div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-slate-800 text-lg font-bold text-slate-300 ring-2 ring-slate-700 sm:h-16 sm:w-16 sm:text-xl lg:h-20 lg:w-20 lg:text-2xl">${esc(item.initials)}</div>`;
                const location = item.location
                    ? `<div class="w-full truncate font-mono text-[11px] text-slate-300 sm:text-sm">${esc(item.location)}</div>`
                    : '';
                return `<article class="flex min-h-0 flex-col items-center justify-center gap-1 overflow-hidden rounded-2xl bg-slate-900 p-2 text-center leading-tight ring-1 ring-slate-800 sm:p-3">
                    ${photo}
                    <div class="w-full truncate text-sm font-bold uppercase tracking-wide text-white sm:text-base lg:text-lg">${esc(item.name)}</div>
                    <div class="w-full truncate text-[10px] font-semibold uppercase tracking-[0.15em] text-sky-400 sm:text-xs">${esc(item.activity)}</div>
                    ${location}
                    <div class="mt-0.5 shrink-0 text-sm font-bold tabular-nums text-emerald-400 sm:text-base lg:text-lg">${esc(item.startTime)}</div>
                </article>`;
            }

            function render (data) {
                if (!Array.isArray(data.active) || data.active.length === 0) {
                    board.innerHTML = `<div class="flex h-full items-center justify-center px-6">
                        <p class="text-center text-4xl font-black uppercase tracking-[0.2em] text-slate-700 sm:text-5xl">No Active Activity</p>
                    </div>`;
                } else {
                    board.innerHTML = `<div class="grid h-full auto-rows-fr grid-cols-2 gap-2 sm:grid-cols-3 sm:gap-3 lg:grid-cols-5">
                        ${data.active.map(cardHTML).join('')}
                    </div>`;
                }

                const notStarted = Array.isArray(data.notStarted) ? data.notStarted : [];
                if (notStarted.length === 0 && data.totalPics > 0) {
                    notStartedEl.textContent = 'All PIC are active';
                } else if (notStarted.length > 0) {
                    notStartedEl.innerHTML = 'Not started: <span class="text-slate-300">' + esc(notStarted.join(', ')) + '</span>';
                } else {
                    notStartedEl.textContent = '';
                }

                if (countEl) countEl.textContent = data.activeCount ?? notStarted.length;
                if (totalEl && data.totalPics != null) totalEl.textContent = data.totalPics;
            }

            async function poll () {
                if (document.hidden) return; // don't hit the server for a screen nobody is looking at
                try {
                    const res = await fetch(DATA_URL, { headers: { 'Accept': 'application/json' }, cache: 'no-store' });
                    if (res.ok) render(await res.json());
                } catch (e) { /* keep the last good board on a transient failure */ }
            }

            function start () {
                stop();
                timer = setInterval(poll, POLL_MS);
            }
            function stop () {
                if (timer) { clearInterval(timer); timer = null; }
            }

            // Resume with a fresh pull the moment the screen becomes visible again.
            document.addEventListener('visibilitychange', function () {
                if (!document.hidden && readState()) poll();
            });

            @if ($isAdmin)
                const toggle = document.getElementById('auto-refresh-toggle');
                const knob = document.getElementById('auto-refresh-knob');
                const stateLabel = document.getElementById('auto-refresh-state');
                function renderToggle () {
                    const on = readState();
                    toggle.setAttribute('aria-checked', on ? 'true' : 'false');
                    toggle.classList.toggle('bg-emerald-500', on);
                    toggle.classList.toggle('bg-slate-700', !on);
                    knob.classList.toggle('translate-x-4', on);
                    knob.classList.toggle('translate-x-0.5', !on);
                    stateLabel.textContent = on ? 'ON' : 'OFF';
                }
                toggle.addEventListener('click', function () {
                    const on = !readState();
                    writeState(on);
                    renderToggle();
                    if (on) { poll(); start(); } else { stop(); }
                });
                renderToggle();
            @endif

            if (readState()) {
                start();
            }
        })();
    </script>
@endsection
