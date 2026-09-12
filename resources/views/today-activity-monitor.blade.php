@extends('layouts.guest')

@section('content')
    <style>
        /* Adaptive typography for the structured-grid tiles — sized from
           each tile's OWN measured dimensions (CSS container queries), not
           one fixed font size for every tile. A huge tile (1-2 activities)
           renders visibly bigger text/photo; a small tile (13-16
           activities) shrinks to fit without overflowing. min(...cqw,...cqh)
           uses the tile's shorter side so neither a very wide-short nor a
           narrow-tall tile overflows. Shared by the SSR markup
           (partials/monitor-board.blade.php) and the JS-rebuilt markup
           (cardHTML() below) — both use the same `.tile-*` class names, so
           this is the single place sizing lives. */
        .monitor-tile { container-type: size; }
        .tile-inner {
            padding: clamp(8px, min(4cqw, 4cqh), 32px);
            gap: clamp(3px, min(1.4cqw, 1.4cqh), 14px);
        }
        .tile-photo {
            width: clamp(40px, min(24cqw, 24cqh), 240px);
            height: clamp(40px, min(24cqw, 24cqh), 240px);
            font-size: clamp(14px, min(9cqw, 9cqh), 72px);
        }
        .tile-name { font-size: clamp(16px, min(7.5cqw, 7.5cqh), 72px); }
        .tile-activity { font-size: clamp(13px, min(6.2cqw, 6.2cqh), 58px); }
        .tile-location { font-size: clamp(15px, min(7.2cqw, 7.2cqh), 68px); }
        .tile-started { font-size: clamp(12px, min(5.6cqw, 5.6cqh), 48px); }
        .tile-activity-cap, .tile-location-cap, .tile-started-cap {
            font-size: clamp(9px, min(3.4cqw, 3.4cqh), 18px);
        }
    </style>

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
                <label class="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wider text-slate-500">
                    Area:
                    <select id="monitor-area-filter"
                        class="rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-xs font-semibold uppercase tracking-wider text-slate-700 focus:border-blue-500 focus:outline-none">
                        <option value="ALL" @selected($selectedArea === 'ALL')>ALL</option>
                        <option value="WWD" @selected($selectedArea === 'WWD')>WWD</option>
                        <option value="BUL" @selected($selectedArea === 'BUL')>BUL</option>
                    </select>
                </label>
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
            const LEGEND_FIXED = ['PM', 'GREASING', 'OIL_AUDIT', 'OIL_AUDIT_ACTION'];       // Manual shown by name
            const SRC_LABEL = { PM: 'PM', GREASING: 'Greasing', OIL_AUDIT: 'Oil Audit', OIL_AUDIT_ACTION: 'Oil Audit Action', MANUAL: 'Manual' };
            const SRC_HEX = { PM: '#2563eb', GREASING: '#f59e0b', OIL_AUDIT: '#10b981', OIL_AUDIT_ACTION: '#0891b2', MANUAL: '#8b5cf6' };
            const SRC_ACCENT = { PM: 'border-blue-600', GREASING: 'border-amber-500', OIL_AUDIT: 'border-emerald-500', OIL_AUDIT_ACTION: 'border-cyan-600', MANUAL: 'border-violet-500' };
            const SRC_TEXT = { PM: 'text-blue-600', GREASING: 'text-amber-600', OIL_AUDIT: 'text-emerald-600', OIL_AUDIT_ACTION: 'text-cyan-700', MANUAL: 'text-violet-600' };

            // Manual activities don't share one "Manual" color — each DISTINCT
            // activity NAME gets its own deterministic color (same name always
            // maps to the same hue; different names always differ), so the
            // donut/legend distinguish "PM" from "Assembling" instead of both
            // just reading as generic violet "Manual". Mirrored in PHP
            // (partials/monitor-status.blade.php) for the pre-JS SSR paint.
            function manualColor (name) {
                const str = String(name || '');
                let hash = 0;
                for (let i = 0; i < str.length; i++) {
                    hash = (hash * 31 + str.charCodeAt(i)) >>> 0;
                }
                return `hsl(${hash % 360}, 65%, 45%)`;
            }

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

            // --- Area filter (ALL / WWD / BUL) — client-side only, no full
            // page reload. poll() always reads the CURRENT selection, so it
            // stays applied through every subsequent 60s auto-refresh tick. ---
            const areaFilterEl = document.getElementById('monitor-area-filter');
            function currentArea () {
                const v = areaFilterEl ? areaFilterEl.value : 'ALL';
                return v === 'WWD' || v === 'BUL' ? v : 'ALL';
            }
            function dataUrl () {
                const a = currentArea();
                return a === 'ALL' ? DATA_URL : (DATA_URL + '?area=' + encodeURIComponent(a));
            }

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

            // ---------- Left: active-activity structured grid ----------
            // The board is grouped into rows via gridForCount()/rowSizesForCount()
            // below (mirrored in PHP — see partials/monitor-board.blade.php)
            // and laid out with plain CSS flexbox: an equal-height row per
            // entry in the row-size list, each row's tiles sharing its width
            // equally (a short last row is redistributed across the FULL
            // width instead of leaving blank space). No JS geometry pass is
            // needed — flexbox reflows on resize on its own. Tile markup
            // here must keep the same `.tile-*` class hooks as
            // partials/monitor-board.blade.php.
            function cardHTML (item) {
                const accent = SRC_ACCENT[item.source] || 'border-t-slate-300';
                const label = SRC_TEXT[item.source] || 'text-blue-600';
                // Sizing (photo/text) is NOT inline here — it comes entirely
                // from the adaptive `.tile-*` CSS (container queries) above,
                // driven by whatever box flexbox gives this tile.
                const photo = item.photo
                    ? `<img src="${esc(item.photo)}" alt="${esc(item.name)}" class="tile-photo shrink-0 rounded-xl object-cover ring-2 ring-slate-200">`
                    : `<div class="tile-photo flex shrink-0 items-center justify-center rounded-xl bg-slate-100 font-bold text-slate-500 ring-2 ring-slate-200">${esc(item.initials)}</div>`;
                const location = item.location
                    ? `<div class="w-full">
                        <div class="tile-location-cap font-semibold uppercase tracking-[0.2em] text-slate-400">Location</div>
                        <div class="tile-location truncate font-mono font-semibold text-slate-600">${esc(item.location)}</div>
                    </div>`
                    : '';
                return `<article class="monitor-tile flex-1 min-w-0 overflow-hidden rounded-2xl border border-slate-200 border-t-4 ${accent} bg-white shadow-sm">
                    <div class="tile-inner flex h-full w-full flex-col items-center justify-center overflow-hidden text-center leading-tight">
                        ${photo}
                        <div class="tile-name w-full truncate pt-1 font-bold uppercase tracking-wide text-slate-900">${esc(item.name)}</div>
                        <div class="w-full">
                            <div class="tile-activity-cap font-semibold uppercase tracking-[0.2em] text-slate-400">Activity</div>
                            <div class="tile-activity truncate font-semibold uppercase tracking-[0.12em] ${label}">${esc(item.activity)}</div>
                        </div>
                        ${location}
                        <div class="w-full">
                            <div class="tile-started-cap font-semibold uppercase tracking-[0.2em] text-slate-400">Started</div>
                            <div class="tile-started font-bold tabular-nums text-emerald-600">${esc(item.startTime)}</div>
                        </div>
                    </div>
                </article>`;
            }

            // Deterministic rows x cols for N equal-weight tiles, matching:
            // 1->1x1, 2->2x1, 3-4->2x2, 5-6->3x2, 7-9->3x3, 10-12->4x3,
            // 13-16->4x4, 17-20->5x4, ... (stepped capacity table — each
            // step alternates "add a column" / "add a row"). Whatever N is,
            // rows*cols is the smallest such capacity >= N, so the grid is
            // never over- or under-sized for the count.
            function gridForCount (n) {
                if (n <= 0) return { rows: 0, cols: 0 };
                let k = 1;
                while (true) {
                    const half = Math.floor(k / 2);
                    const rows = (k % 2 === 1) ? half + 1 : half;
                    const cols = half + 1;
                    if (rows * cols >= n) return { rows: rows, cols: cols };
                    k++;
                }
            }

            // Splits N into per-row tile counts for that grid. Every row but
            // the last has exactly `cols` tiles; the last row takes whatever
            // remains (which may be less than `cols`) — that row's tiles
            // then flex to share its FULL width, so an incomplete last row
            // still fills the board edge-to-edge instead of leaving blank
            // space (e.g. 11 -> [4, 4, 3]).
            function rowSizesForCount (n) {
                if (n <= 0) return [];
                const g = gridForCount(n);
                const sizes = [];
                let remaining = n;
                for (let r = 0; r < g.rows; r++) {
                    const take = (r === g.rows - 1) ? remaining : g.cols;
                    sizes.push(take);
                    remaining -= take;
                }
                return sizes;
            }

            function boardHTML (active) {
                const sizes = rowSizesForCount(active.length);
                let idx = 0;
                const rows = sizes.map(function (count) {
                    const tiles = active.slice(idx, idx + count).map(cardHTML).join('');
                    idx += count;
                    return `<div class="tile-row flex min-h-0 flex-1 gap-2">${tiles}</div>`;
                }).join('');
                return `<div id="monitor-treemap" class="flex h-full w-full flex-col gap-2">${rows}</div>`;
            }

            // ---------- Right: donut + legend ----------
            // Slices = the 4 fixed sources PLUS one slice per DISTINCT manual
            // activity name (not one lumped "Manual" slice), each colored by
            // manualColor() — so the ring visually matches the legend below.
            function donutSVG (dist, manualBreakdown) {
                manualBreakdown = Array.isArray(manualBreakdown) ? manualBreakdown : [];
                const slices = LEGEND_FIXED
                    .map(k => ({ color: SRC_HEX[k], value: dist[k] || 0 }))
                    .concat(manualBreakdown.map(m => ({ color: manualColor(m.name), value: m.count })));
                const total = slices.reduce((s, sl) => s + sl.value, 0);
                const r = 42, c = 2 * Math.PI * r;
                let segs = '', offset = 0;
                if (total === 0) {
                    segs = `<circle cx="60" cy="60" r="${r}" fill="none" stroke="#e2e8f0" stroke-width="16"/>`;
                } else {
                    slices.forEach(function (sl) {
                        if (!sl.value) return;
                        const len = c * sl.value / total;
                        segs += `<circle cx="60" cy="60" r="${r}" fill="none" stroke="${sl.color}" stroke-width="16" stroke-linecap="butt" stroke-dasharray="${len} ${c - len}" stroke-dashoffset="${-offset}" transform="rotate(-90 60 60)"/>`;
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
                let html = LEGEND_FIXED
                    .filter(k => (dist[k] || 0) > 0)
                    .map(k => legendRow(SRC_HEX[k], SRC_LABEL[k], dist[k]))
                    .join('');
                (Array.isArray(manualBreakdown) ? manualBreakdown : []).forEach(function (m) {
                    html += legendRow(manualColor(m.name), m.name, m.count);
                });
                return html;
            }
            function areaHTML (area) {
                area = area || {};
                // Only the area(s) the server actually returned (i.e. the
                // one(s) in scope for the current Area filter) — see
                // partials/monitor-area.blade.php.
                return Object.keys(area).map(function (k) {
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
                if (donutEl) donutEl.innerHTML = donutSVG(dist, manualBreakdown);
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
                    board.innerHTML = boardHTML(active);
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
                    const res = await fetch(dataUrl(), { headers: { 'Accept': 'application/json' }, cache: 'no-store' });
                    if (res.ok) render(await res.json());
                } catch (e) { /* keep the last good board on a transient failure */ }
            }

            function start () { stop(); timer = setInterval(poll, POLL_MS); }
            function stop () { if (timer) { clearInterval(timer); timer = null; } }

            document.addEventListener('visibilitychange', function () {
                if (!document.hidden && readState()) poll();
            });

            // Changing the filter re-fetches immediately (no page reload);
            // the interval above keeps using whatever is selected.
            if (areaFilterEl) {
                areaFilterEl.addEventListener('change', function () {
                    const a = currentArea();
                    try {
                        const url = new URL(window.location.href);
                        if (a === 'ALL') url.searchParams.delete('area'); else url.searchParams.set('area', a);
                        window.history.replaceState(null, '', url);
                    } catch (e) { /* URL API unavailable — filter still works without syncing the address bar */ }
                    poll();
                });
            }

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
