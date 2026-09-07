@extends('layouts.guest')

@section('content')
    <div id="monitor"
        data-poll-url="{{ route('monitor.data') }}"
        data-poll-interval="60000"
        data-auto-refresh-default="on"
        class="fixed inset-0 flex flex-col overflow-hidden bg-slate-50 text-slate-800">

        {{-- ---------- Header ---------- --}}
        <header class="flex shrink-0 items-center justify-between gap-4 border-b border-slate-200 bg-white px-6 py-3">
            {{-- Logo doubles as "back to landing page" --}}
            <a href="{{ route('home') }}" class="flex min-w-0 items-center gap-3 transition hover:opacity-80"
                title="Back to landing page">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-blue-600 text-white shadow-sm">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5">
                        <path d="M22 12h-2.48a2 2 0 0 0-1.93 1.46l-2.35 8.36a.25.25 0 0 1-.48 0L9.24 2.18a.25.25 0 0 0-.48 0l-2.35 8.36A2 2 0 0 1 4.49 12H2" />
                    </svg>
                </span>
                <div class="min-w-0">
                    <h1 class="truncate text-lg font-bold uppercase tracking-[0.25em] text-slate-800">Today's Activity</h1>
                    <p class="mt-0.5 truncate text-xs uppercase tracking-widest text-slate-400">
                        FreeDOMS · {{ now()->translatedFormat('l, d F Y') }}
                    </p>
                </div>
            </a>

            <div class="flex shrink-0 items-center gap-4">
                <div id="monitor-clock" class="text-3xl font-bold tabular-nums leading-none text-slate-900">--:--</div>
                <a href="{{ route('home') }}"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold uppercase tracking-wider text-slate-600 transition hover:bg-slate-100 hover:text-slate-900">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="h-3.5 w-3.5">
                        <path d="m15 18-6-6 6-6" />
                    </svg>
                    Landing Page
                </a>
            </div>
        </header>

        {{-- ---------- Body: 3 : 1 ---------- --}}
        <main class="flex min-h-0 flex-1">
            {{-- Left 75% — active cards (the part the poll replaces) --}}
            <section class="min-w-0 flex-[3] overflow-hidden p-4">
                <div id="monitor-board" class="h-full overflow-hidden">
                    @include('partials.monitor-board', ['active' => $active])
                </div>
            </section>

            {{-- Right 25% — maintenance status --}}
            <aside class="w-1/4 min-w-0 shrink-0 overflow-hidden border-l border-slate-200 bg-slate-50">
                @include('partials.monitor-status', [
                    'distribution' => $distribution,
                    'manualBreakdown' => $manualBreakdown,
                    'active' => $active,
                    'totalPics' => $totalPics,
                    'notStarted' => $notStarted,
                    'inactive' => $inactive,
                    'area' => $area,
                    'counts' => $counts,
                    'isAdmin' => $isAdmin,
                ])
            </aside>
        </main>
    </div>

    <script>
        (function () {
            const root = document.getElementById('monitor');

            // Source palette — keep in sync with partials/monitor-board + monitor-status.
            const SRC_ORDER = ['PM', 'GREASING', 'OIL_AUDIT', 'OIL_AUDIT_ACTION', 'MANUAL']; // donut slice order
            const LEGEND_FIXED = ['PM', 'GREASING', 'OIL_AUDIT', 'OIL_AUDIT_ACTION'];       // Manual shown by name
            const SRC_LABEL = { PM: 'PM', GREASING: 'Greasing', OIL_AUDIT: 'Oil Audit', OIL_AUDIT_ACTION: 'Oil Audit Action', MANUAL: 'Manual' };
            const SRC_HEX = { PM: '#2563eb', GREASING: '#f59e0b', OIL_AUDIT: '#10b981', OIL_AUDIT_ACTION: '#0891b2', MANUAL: '#8b5cf6' };
            const SRC_ACCENT = { PM: 'border-blue-600', GREASING: 'border-amber-500', OIL_AUDIT: 'border-emerald-500', OIL_AUDIT_ACTION: 'border-cyan-600', MANUAL: 'border-violet-500' };
            const SRC_TEXT = { PM: 'text-blue-600', GREASING: 'text-amber-600', OIL_AUDIT: 'text-emerald-600', OIL_AUDIT_ACTION: 'text-cyan-700', MANUAL: 'text-violet-600' };

            // --- Live clock ---
            const clock = document.getElementById('monitor-clock');
            function tick () {
                const d = new Date();
                clock.textContent = String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0');
            }
            tick();
            setInterval(tick, 15000);

            // --- Auto refresh (lightweight data poll, NOT a full page reload) ---
            const KEY = 'freedoms.monitor.autoRefresh';
            const DEFAULT_ON = root.dataset.autoRefreshDefault === 'on';
            const POLL_MS = Math.max(60000, parseInt(root.dataset.pollInterval, 10) || 60000);
            const DATA_URL = root.dataset.pollUrl;

            const board = document.getElementById('monitor-board');
            const notStartedEl = document.getElementById('monitor-notstarted');
            const inactiveEl = document.getElementById('monitor-inactive');
            const areaEl = document.getElementById('monitor-area');
            const statActive = document.getElementById('stat-active');
            const statNotStarted = document.getElementById('stat-notstarted');
            const statInactive = document.getElementById('stat-inactive');
            const donutEl = document.getElementById('monitor-donut');
            const legendEl = document.getElementById('monitor-legend');

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

            // ---------- Left: active cards ----------
            function cardHTML (item) {
                const accent = SRC_ACCENT[item.source] || 'border-t-slate-300';
                const label = SRC_TEXT[item.source] || 'text-blue-600';
                const photo = item.photo
                    ? `<img src="${esc(item.photo)}" alt="${esc(item.name)}" class="h-16 w-16 shrink-0 rounded-xl object-cover ring-2 ring-slate-200 2xl:h-20 2xl:w-20">`
                    : `<div class="flex h-16 w-16 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-xl font-bold text-slate-500 ring-2 ring-slate-200 2xl:h-20 2xl:w-20 2xl:text-2xl">${esc(item.initials)}</div>`;
                const location = item.location
                    ? `<div class="w-full">
                        <div class="text-[9px] font-semibold uppercase tracking-[0.2em] text-slate-400">Location</div>
                        <div class="truncate font-mono text-base font-semibold text-slate-600 2xl:text-lg">${esc(item.location)}</div>
                    </div>`
                    : '';
                return `<article class="flex min-h-[11rem] flex-col items-center justify-center gap-1 overflow-hidden rounded-2xl border border-slate-200 border-t-4 ${accent} bg-white p-3 text-center leading-tight shadow-sm">
                    ${photo}
                    <div class="w-full truncate pt-1 text-base font-bold uppercase tracking-wide text-slate-900 2xl:text-lg">${esc(item.name)}</div>
                    <div class="w-full">
                        <div class="text-[9px] font-semibold uppercase tracking-[0.2em] text-slate-400">Activity</div>
                        <div class="truncate text-sm font-semibold uppercase tracking-[0.12em] ${label} 2xl:text-base">${esc(item.activity)}</div>
                    </div>
                    ${location}
                    <div class="w-full">
                        <div class="text-[9px] font-semibold uppercase tracking-[0.2em] text-slate-400">Started</div>
                        <div class="text-xs font-bold tabular-nums text-emerald-600 2xl:text-sm">${esc(item.startTime)}</div>
                    </div>
                </article>`;
            }

            // ---------- Right: donut + legend ----------
            function donutSVG (dist) {
                const total = SRC_ORDER.reduce((s, k) => s + (dist[k] || 0), 0);
                const r = 42, c = 2 * Math.PI * r;
                let segs = '', offset = 0;
                if (total === 0) {
                    segs = `<circle cx="60" cy="60" r="${r}" fill="none" stroke="#e2e8f0" stroke-width="16"/>`;
                } else {
                    SRC_ORDER.forEach(function (k) {
                        const v = dist[k] || 0;
                        if (!v) return;
                        const len = c * v / total;
                        segs += `<circle cx="60" cy="60" r="${r}" fill="none" stroke="${SRC_HEX[k]}" stroke-width="16" stroke-linecap="butt" stroke-dasharray="${len} ${c - len}" stroke-dashoffset="${-offset}" transform="rotate(-90 60 60)"/>`;
                        offset += len;
                    });
                }
                return `<svg viewBox="0 0 120 120" class="h-32 w-32 2xl:h-36 2xl:w-36">${segs}
                    <text x="60" y="57" text-anchor="middle" fill="#0f172a" style="font-size:24px;font-weight:800">${total}</text>
                    <text x="60" y="74" text-anchor="middle" fill="#94a3b8" style="font-size:9px;letter-spacing:2px">ACTIVE</text>
                </svg>`;
            }
            function legendRow (color, label, count) {
                return `<div class="flex items-center justify-between gap-2 text-xs">
                    <span class="flex min-w-0 items-center gap-2">
                        <span class="h-2.5 w-2.5 shrink-0 rounded-full" style="background:${color}"></span>
                        <span class="truncate uppercase tracking-wider text-slate-600">${esc(label)}</span>
                    </span>
                    <span class="shrink-0 tabular-nums font-bold text-slate-900">${count}</span>
                </div>`;
            }
            function legendHTML (dist, manualBreakdown) {
                let html = LEGEND_FIXED.map(k => legendRow(SRC_HEX[k], SRC_LABEL[k], dist[k] || 0)).join('');
                (Array.isArray(manualBreakdown) ? manualBreakdown : []).forEach(function (m) {
                    html += legendRow(SRC_HEX.MANUAL, m.name, m.count);
                });
                return html;
            }
            function areaHTML (area) {
                area = area || {};
                return ['WWD', 'BUL'].map(function (k) {
                    const a = area[k] || { active: 0, available: 0 };
                    return `<div class="flex items-center justify-between gap-2 text-xs">
                        <span class="font-bold uppercase tracking-wider text-slate-500">${k}</span>
                        <span class="tabular-nums text-slate-700">
                            <span class="font-bold text-emerald-600">${a.active || 0}</span> ACTIVE
                            <span class="text-slate-300">/</span>
                            <span class="font-bold text-slate-900">${a.available || 0}</span> AVAILABLE
                        </span>
                    </div>`;
                }).join('');
            }
            function inactiveHTML (list) {
                list = Array.isArray(list) ? list : [];
                if (list.length === 0) return `<div class="text-slate-400">None</div>`;
                return list.map(function (r) {
                    return `<div class="flex items-baseline justify-between gap-2">
                        <span class="truncate font-semibold uppercase tracking-wide text-slate-700">${esc(r.name)}</span>
                        <span class="shrink-0 text-slate-500">${esc(r.reason)}</span>
                    </div>`;
                }).join('');
            }
            function renderStatus (dist, manualBreakdown) {
                if (donutEl) donutEl.innerHTML = donutSVG(dist);
                if (legendEl) legendEl.innerHTML = legendHTML(dist, manualBreakdown);
            }

            // ---------- Full render ----------
            function render (data) {
                const active = Array.isArray(data.active) ? data.active : [];

                if (active.length === 0) {
                    board.innerHTML = `<div class="flex h-full items-center justify-center px-6">
                        <p class="text-center text-4xl font-black uppercase tracking-[0.2em] text-slate-300 sm:text-5xl">No Active Activity</p>
                    </div>`;
                } else {
                    board.innerHTML = `<div class="grid content-start gap-3 grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
                        ${active.map(cardHTML).join('')}
                    </div>`;
                }

                const notStarted = Array.isArray(data.notStarted) ? data.notStarted : [];
                if (notStarted.length === 0 && data.totalPics > 0) {
                    notStartedEl.textContent = 'ALL PIC ARE ACTIVE';
                } else {
                    notStartedEl.textContent = notStarted.join(' · ');
                }

                const counts = data.counts || {};
                if (statActive) statActive.textContent = counts.active ?? active.length;
                if (statNotStarted) statNotStarted.textContent = counts.notStarted ?? notStarted.length;
                if (statInactive) statInactive.textContent = counts.inactive ?? 0;

                if (areaEl) areaEl.innerHTML = areaHTML(data.area);

                const inactive = Array.isArray(data.inactive) ? data.inactive : [];
                if (inactiveEl) {
                    inactiveEl.innerHTML = inactiveHTML(inactive);
                    const box = inactiveEl.closest('div.rounded-2xl');
                    if (box) {
                        box.classList.toggle('flex-1', inactive.length > 0);
                        box.classList.toggle('shrink-0', inactive.length === 0);
                    }
                }

                renderStatus(data.distribution || {}, data.manualBreakdown || []);
            }

            async function poll () {
                if (document.hidden) return;
                try {
                    const res = await fetch(DATA_URL, { headers: { 'Accept': 'application/json' }, cache: 'no-store' });
                    if (res.ok) render(await res.json());
                } catch (e) { /* keep the last good board on a transient failure */ }
            }

            function start () { stop(); timer = setInterval(poll, POLL_MS); }
            function stop () { if (timer) { clearInterval(timer); timer = null; } }

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
                    toggle.classList.toggle('bg-blue-600', on);
                    toggle.classList.toggle('bg-slate-200', !on);
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

            // Draw the donut for the server-rendered state right away.
            try {
                renderStatus(
                    JSON.parse(donutEl?.dataset.dist || '{}'),
                    JSON.parse(legendEl?.dataset.manual || '[]')
                );
            } catch (e) { renderStatus({}, []); }

            if (readState()) { start(); }
        })();
    </script>
@endsection
