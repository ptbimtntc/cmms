@extends('layouts.guest')

@section('title', "Today's Activity · FreeDOMS")

@section('content')
    <style>
        /* Adaptive typography for the structured-grid tiles, sized from
           each tile's OWN measured dimensions (CSS container queries), not
           one fixed font size for every tile. A huge tile (1-2 activities)
           renders visibly bigger text/photo; a small tile (13-16
           activities) shrinks to fit without overflowing. min(...cqw,...cqh)
           uses the tile's shorter side so neither a very wide-short nor a
           narrow-tall tile overflows. Shared by the SSR markup
           (partials/monitor-board.blade.php) and the JS-rebuilt markup
           (cardHTML() below), both use the same `.tile-*` class names, so
           this is the single place sizing lives. */
        .monitor-tile { container-type: size; border-top: 4px solid var(--src, var(--color-border-strong)); }

        /* Activity source colours: one categorical palette in a fixed order (PM first),
           validated for colour-blind separation. Tiles, donut and legend all read these,
           so a source is the same colour everywhere. Text never takes the source colour;
           the activity name is always written out. Manual activities keep their own
           per-name colour (manualColor() below) and are always named in the legend. */
        #monitor {
            --src-PM: #2a78d6;
            --src-GREASING: #eb6834;
            --src-SMALL_PM: #1baf7a;
            --src-OIL_AUDIT: #eda100;
            --src-OIL_AUDIT_ACTION: #e87ba4;
            --src-PREDICTIVE: #008300;
        }
        .src-PM { --src: var(--src-PM); }
        .src-GREASING { --src: var(--src-GREASING); }
        .src-SMALL_PM { --src: var(--src-SMALL_PM); }
        .src-OIL_AUDIT { --src: var(--src-OIL_AUDIT); }
        .src-OIL_AUDIT_ACTION { --src: var(--src-OIL_AUDIT_ACTION); }
        .src-PREDICTIVE { --src: var(--src-PREDICTIVE); }
        .src-MANUAL { --src: var(--color-border-strong); } /* fallback; tiles set their per-name colour inline */
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
        class="fixed inset-0 flex flex-col overflow-hidden bg-bg text-text">

        {{-- ---------- Header ---------- --}}
        <header class="flex shrink-0 items-center justify-between gap-4 border-b border-border bg-surface px-6 py-3">
            {{-- Logo doubles as "back to landing page" --}}
            <a href="{{ route('home') }}" class="flex min-w-0 items-center gap-4 transition-opacity hover:opacity-80" title="Back to landing page">
                <x-brand-logo :framed="false" class="h-9 w-auto shrink-0" aria-hidden="true" />
                <div class="min-w-0 border-l border-border pl-4">
                    <h1 class="truncate font-signage text-2xl font-semibold leading-tight text-text">Today's Activity</h1>
                    <p class="truncate text-sm text-text-muted">{{ now()->translatedFormat('l, d F Y') }}</p>
                </div>
            </a>

            <div class="flex shrink-0 items-center gap-5">
                <label class="flex items-center gap-2 text-sm font-medium text-text-muted">
                    Area
                    <select id="monitor-area-filter" class="select min-h-10 w-auto py-1.5 font-semibold">
                        <option value="ALL" @selected($selectedArea === 'ALL')>ALL</option>
                        @foreach ($areas as $areaOption)
                            <option value="{{ $areaOption }}" @selected($selectedArea === $areaOption)>{{ $areaOption }}</option>
                        @endforeach
                    </select>
                </label>
                <div id="monitor-clock" class="font-signage text-4xl font-semibold tabular-nums leading-none text-text">--:--</div>
                <a href="{{ route('home') }}" class="btn btn-outline min-h-10">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4" aria-hidden="true">
                        <path d="m15 18-6-6 6-6" />
                    </svg>
                    Landing Page
                </a>
            </div>
        </header>

        {{-- ---------- Body: 3 : 1 ---------- --}}
        <main class="flex min-h-0 flex-1">
            {{-- Left 75%: active cards (the part the poll replaces) --}}
            <section class="min-w-0 flex-[3] overflow-hidden p-4">
                <div id="monitor-board" class="h-full overflow-hidden">
                    @include('partials.monitor-board', ['active' => $active])
                </div>
            </section>

            {{-- Right 25%: maintenance status --}}
            <aside class="w-1/4 min-w-0 shrink-0 overflow-hidden border-l border-border bg-surface-sunken">
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

            // Source palette, keep in sync with partials/monitor-board + monitor-status.
            const LEGEND_FIXED = ['PM', 'GREASING', 'SMALL_PM', 'OIL_AUDIT', 'OIL_AUDIT_ACTION', 'PREDICTIVE'];       // Manual shown by name
            const SRC_LABEL = { PM: 'PM', GREASING: 'Greasing', SMALL_PM: 'Small PM', OIL_AUDIT: 'Oil Audit', OIL_AUDIT_ACTION: 'Oil Audit Action', PREDICTIVE: 'Action Predictive', MANUAL: 'Manual' };
            // Colours live in the --src-* custom properties on #monitor (see the <style> block).
            const srcColor = (k) => `var(--src-${k})`;
            const srcClass = (k) => (SRC_LABEL[k] ? `src-${k}` : '');

            // Manual activities don't share one "Manual" color, each DISTINCT
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

            // --- Area filter (ALL, or any active Area), client-side only,
            // no full page reload. poll() always reads the CURRENT
            // selection, so it stays applied through every subsequent 60s
            // auto-refresh tick. The select's options are rendered
            // server-side from the live Area list, so any value it can hold
            // is already valid, the server re-validates it anyway (see
            // TodayActivityMonitorController::sanitizeArea()). ---
            const areaFilterEl = document.getElementById('monitor-area-filter');
            function currentArea () {
                return areaFilterEl ? areaFilterEl.value : 'ALL';
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
            // below (mirrored in PHP, see partials/monitor-board.blade.php)
            // and laid out with plain CSS flexbox: an equal-height row per
            // entry in the row-size list, each row's tiles sharing its width
            // equally (a short last row is redistributed across the FULL
            // width instead of leaving blank space). No JS geometry pass is
            // needed, flexbox reflows on resize on its own. Tile markup
            // here must keep the same `.tile-*` class hooks as
            // partials/monitor-board.blade.php.
            function cardHTML (item) {
                // Sizing (photo/text) is NOT inline here: it comes entirely
                // from the adaptive `.tile-*` CSS (container queries) above,
                // driven by whatever box flexbox gives this tile.
                const photo = item.photo
                    ? `<img src="${esc(item.photo)}" alt="${esc(item.name)}" class="tile-photo shrink-0 rounded-xl object-cover ring-2 ring-border">`
                    : `<div class="tile-photo flex shrink-0 items-center justify-center rounded-xl bg-surface-muted font-semibold text-text-muted ring-2 ring-border">${esc(item.initials)}</div>`;
                const location = item.location
                    ? `<div class="w-full">
                        <div class="tile-location-cap font-medium text-text-muted">Location</div>
                        <div class="tile-location truncate font-mono font-semibold text-text">${esc(item.location)}</div>
                    </div>`
                    : '';
                // A manual activity's accent is its per-name colour, same as its legend dot.
                const accent = item.source === 'MANUAL' ? ` style="--src: ${manualColor(item.activity)}"` : '';
                return `<article${accent} class="monitor-tile ${srcClass(item.source)} flex-1 min-w-0 overflow-hidden rounded-card border border-border bg-surface shadow-sm">
                    <div class="tile-inner flex h-full w-full flex-col items-center justify-center overflow-hidden text-center leading-tight">
                        ${photo}
                        <div class="tile-name w-full truncate pt-1 font-semibold text-text">${esc(item.name)}</div>
                        <div class="w-full">
                            <div class="tile-activity-cap font-medium text-text-muted">Activity</div>
                            <div class="tile-activity truncate font-semibold text-text">${esc(item.activity)}</div>
                        </div>
                        ${location}
                        <div class="w-full">
                            <div class="tile-started-cap font-medium text-text-muted">Started</div>
                            <div class="tile-started font-signage font-semibold tabular-nums text-text">${esc(item.startTime)}</div>
                        </div>
                    </div>
                </article>`;
            }

            // Deterministic rows x cols for N equal-weight tiles, matching:
            // 1->1x1, 2->2x1, 3-4->2x2, 5-6->3x2, 7-9->3x3, 10-12->4x3,
            // 13-16->4x4, 17-20->5x4, ... (stepped capacity table, each
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
            // remains (which may be less than `cols`), that row's tiles
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
            // manualColor(), so the ring visually matches the legend below.
            function donutSVG (dist, manualBreakdown) {
                manualBreakdown = Array.isArray(manualBreakdown) ? manualBreakdown : [];
                const slices = LEGEND_FIXED
                    .map(k => ({ color: srcColor(k), value: dist[k] || 0 }))
                    .concat(manualBreakdown.map(m => ({ color: manualColor(m.name), value: m.count })));
                const total = slices.reduce((s, sl) => s + sl.value, 0);
                const r = 42, c = 2 * Math.PI * r;
                let segs = '', offset = 0;
                if (total === 0) {
                    segs = `<circle cx="60" cy="60" r="${r}" fill="none" style="stroke: var(--color-surface-muted)" stroke-width="16"/>`;
                } else {
                    slices.forEach(function (sl) {
                        if (!sl.value) return;
                        const len = c * sl.value / total;
                        // A 2px surface-coloured gap between slices keeps neighbours apart.
                        const gap = slices.filter(x => x.value).length > 1 ? 2 : 0;
                        segs += `<circle cx="60" cy="60" r="${r}" fill="none" style="stroke: ${sl.color}" stroke-width="16" stroke-linecap="butt" stroke-dasharray="${Math.max(len - gap, 0)} ${c - Math.max(len - gap, 0)}" stroke-dashoffset="${-offset}" transform="rotate(-90 60 60)"/>`;
                        offset += len;
                    });
                }
                return `<svg viewBox="0 0 120 120" class="h-32 w-32 2xl:h-36 2xl:w-36" role="img" aria-label="${total} aktivitas aktif">${segs}
                    <text x="60" y="60" text-anchor="middle" class="font-signage" style="fill: var(--color-text); font-size:28px; font-weight:600">${total}</text>
                    <text x="60" y="76" text-anchor="middle" style="fill: var(--color-text-muted); font-size:10px">active</text>
                </svg>`;
            }
            function legendRow (color, label, count) {
                return `<div class="flex items-center justify-between gap-2 text-sm">
                    <span class="flex min-w-0 items-center gap-2">
                        <span class="h-3 w-3 shrink-0 rounded-full" style="background: ${color}"></span>
                        <span class="truncate text-text">${esc(label)}</span>
                    </span>
                    <span class="shrink-0 font-semibold tabular-nums text-text">${count}</span>
                </div>`;
            }
            function legendHTML (dist, manualBreakdown) {
                let html = LEGEND_FIXED
                    .filter(k => (dist[k] || 0) > 0)
                    .map(k => legendRow(srcColor(k), SRC_LABEL[k], dist[k]))
                    .join('');
                (Array.isArray(manualBreakdown) ? manualBreakdown : []).forEach(function (m) {
                    html += legendRow(manualColor(m.name), m.name, m.count);
                });
                return html;
            }
            function areaHTML (area) {
                area = area || {};
                // Only the area(s) the server actually returned (i.e. the
                // one(s) in scope for the current Area filter), see
                // partials/monitor-area.blade.php.
                return Object.keys(area).map(function (k) {
                    const a = area[k] || { active: 0, available: 0 };
                    return `<div class="flex items-center justify-between gap-2 text-sm">
                        <span class="font-semibold text-text">${esc(k)}</span>
                        <span class="tabular-nums text-text-muted">
                            <span class="font-semibold text-text">${a.active || 0}</span> active
                            <span aria-hidden="true">/</span>
                            <span class="font-semibold text-text">${a.available || 0}</span> available
                        </span>
                    </div>`;
                }).join('');
            }
            function inactiveHTML (list) {
                list = Array.isArray(list) ? list : [];
                if (list.length === 0) return `<div class="text-text-muted">None</div>`;
                return list.map(function (r) {
                    return `<div class="flex items-baseline justify-between gap-2">
                        <span class="truncate font-semibold text-text">${esc(r.name)}</span>
                        <span class="shrink-0 text-text-muted">${esc(r.reason)}</span>
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
                        <p class="text-center font-signage text-5xl font-semibold text-text-muted">No Active Activity</p>
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
                    const box = inactiveEl.closest('[data-monitor-inactive-box]');
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
                    } catch (e) { /* URL API unavailable, filter still works without syncing the address bar */ }
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
                    toggle.classList.toggle('bg-primary', on);
                    toggle.classList.toggle('bg-border-strong', !on);
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
