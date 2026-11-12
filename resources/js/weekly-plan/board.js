import Sortable from 'sortablejs';

/*
 * Weekly PM planner board. State comes from #weekly-plan-data (built by
 * PlanBoardService::state()); every change re-renders the board and saves
 * the placed machines through PUT /weekly-plan/plans/{id}/items.
 *
 * Drag and drop is the fast path; the "Hari" select on every card does the
 * same move for keyboard and small-screen users.
 */

const SAVE_DELAY_MS = 600;

export function escapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#39;');
}

/** Card key: the PM Schedule id. */
export function cardKey(card) {
    return String(card.pm_schedule_id);
}

/**
 * Moves a card to a day (or back to the pool with day = null) at a position.
 * Returns a new { items, pool } pair; the input is not mutated.
 */
export function moveCard(state, key, day, index = null) {
    const all = [...state.items, ...state.pool];
    const card = all.find((c) => cardKey(c) === key);

    if (!card) {
        return { items: state.items, pool: state.pool };
    }

    const moved = { ...card, day };

    // A card leaving the pool keeps the PIC/construction it already carried
    // (PM Schedule PIC, last week's note). A card going back to the pool keeps
    // them too, so dragging it out again does not lose the typing.
    let items = state.items.filter((c) => cardKey(c) !== key);
    let pool = state.pool.filter((c) => cardKey(c) !== key);

    if (day === null) {
        pool = insertAt(pool, moved, index ?? dueIndex(pool, moved));
    } else {
        const sameDay = items.filter((c) => c.day === day);
        const target = index === null ? sameDay.length : Math.min(index, sameDay.length);
        const before = sameDay[target];
        const at = before ? items.indexOf(before) : lastIndexOfDay(items, day) + 1 || items.length;
        items = [...items.slice(0, at), moved, ...items.slice(at)];
    }

    return { items, pool };
}

const dueKey = (c) => `${c.due_date ?? c.plan_date ?? '9999-12-31'}|${c.machine_number}`;

/** Position that keeps the pool in due-date order, like the server sends it. */
function dueIndex(pool, card) {
    const i = pool.findIndex((c) => dueKey(c) > dueKey(card));
    return i === -1 ? pool.length : i;
}

function insertAt(list, card, index) {
    if (index === null || index >= list.length) {
        return [...list, card];
    }

    return [...list.slice(0, index), card, ...list.slice(index)];
}

function lastIndexOfDay(items, day) {
    for (let i = items.length - 1; i >= 0; i--) {
        if (items[i].day === day) return i;
    }

    return -1;
}

/**
 * Sends every placed card back to the pool, keeping the PIC and construction
 * already typed on it. Pool order follows due date like the server does.
 */
export function resetCards(state) {
    const due = (c) => c.due_date ?? c.plan_date ?? '9999-12-31';
    const pool = [...state.items.map((c) => ({ ...c, day: null })), ...state.pool]
        .sort((a, b) => due(a).localeCompare(due(b)) || String(a.machine_number).localeCompare(String(b.machine_number)));

    return { items: [], pool };
}

/** Payload for PUT items: days in order, cards in their on-screen order. */
export function buildPayload(state) {
    const items = [];

    for (const day of state.days) {
        for (const card of state.items) {
            if (card.day === day.date && card.pm_schedule_id !== null) {
                items.push({
                    pm_schedule_id: card.pm_schedule_id,
                    day: card.day,
                    pic: card.pic || null,
                    construction_note: card.construction_note || null,
                });
            }
        }
    }

    return { items };
}

/**
 * Copies rich HTML (keeps bold headings and alignment when pasted into
 * Outlook/Gmail) plus a plain-text version. Falls back to a selection copy
 * when the async Clipboard API is unavailable, e.g. the CMMS opened over
 * plain http on the plant network.
 */
export async function copyRich(html, text, env = globalThis) {
    const nav = env.navigator;

    if (nav?.clipboard?.write && env.ClipboardItem && env.isSecureContext !== false) {
        try {
            await nav.clipboard.write([
                new env.ClipboardItem({
                    'text/html': new Blob([html], { type: 'text/html' }),
                    'text/plain': new Blob([text], { type: 'text/plain' }),
                }),
            ]);

            return true;
        } catch {
            // fall through to the selection copy
        }
    }

    const doc = env.document;
    const holder = doc.createElement('div');
    holder.contentEditable = 'true';
    holder.style.position = 'fixed';
    holder.style.left = '-9999px';
    holder.innerHTML = html;
    doc.body.appendChild(holder);

    const range = doc.createRange();
    range.selectNodeContents(holder);
    const selection = env.getSelection();
    selection.removeAllRanges();
    selection.addRange(range);

    let ok = false;
    try {
        ok = doc.execCommand('copy');
    } catch {
        ok = false;
    }

    selection.removeAllRanges();
    holder.remove();

    return ok;
}

function formatShortDate(iso) {
    if (!iso) return '';
    const [y, m, d] = iso.split('-');
    return `${d}/${m}/${y.slice(2)}`;
}

export function initWeeklyPlan(root) {
    const dataEl = document.getElementById('weekly-plan-data');
    if (!root || !dataEl) return;

    const initial = JSON.parse(dataEl.textContent);
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

    const els = {
        board: root.querySelector('[data-board]'),
        boardError: root.querySelector('[data-board-error]'),
        saveStatus: root.querySelector('[data-save-status]'),
        autofill: root.querySelector('[data-autofill]'),
        reset: root.querySelector('[data-reset]'),
        picSync: root.querySelector('[data-pic-sync]'),
        picSyncStatus: root.querySelector('[data-pic-sync-status]'),
        email: root.querySelector('[data-email-panel]'),
        realization: root.querySelector('[data-realization]'),
    };

    let state = initial.state;
    let outputs = { email: initial.email, revision: initial.revision, realization: initial.realization, sent_at: initial.sent_at, pic_sync: initial.pic_sync };
    let poolFilter = '';
    let dueOnly = false;
    let saveTimer = null;
    let dirty = false;
    let currentSave = null;
    let sortables = [];
    const dayErrors = {};

    async function request(url, method, body) {
        const response = await fetch(url, {
            method,
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrf,
            },
            body: body ? JSON.stringify(body) : undefined,
        });

        const json = await response.json().catch(() => ({}));

        if (!response.ok) {
            const firstError = json.errors ? Object.values(json.errors)[0]?.[0] : null;
            throw new Error(firstError || json.message || `Server menjawab ${response.status}.`);
        }

        return json;
    }

    function setStatus(text) {
        els.saveStatus.textContent = text;
    }

    function showError(message) {
        if (!message) {
            els.boardError.hidden = true;
            els.boardError.innerHTML = '';
            return;
        }

        els.boardError.hidden = false;
        els.boardError.innerHTML = `${escapeHtml(message)} <button type="button" class="link ml-2 font-semibold underline" data-retry-save>Coba simpan lagi</button>`;
    }

    function scheduleSave() {
        dirty = true;
        setStatus('Ada perubahan, menyimpan...');
        clearTimeout(saveTimer);
        saveTimer = setTimeout(() => { saveTimer = null; save(); }, SAVE_DELAY_MS);
    }

    async function sendSave() {
        dirty = false;
        setStatus('Menyimpan...');

        try {
            const result = await request(root.dataset.saveUrl, 'PUT', buildPayload(state));
            outputs = result;
            showError(null);
            const released = result.pic_released > 0
                ? `, PIC ${result.pic_released} PM di PM Schedule dikembalikan`
                : '';
            setStatus(dirty ? 'Ada perubahan, menyimpan...' : `Tersimpan${released}`);
            renderEmail();
        } catch (error) {
            dirty = true;
            setStatus('Gagal simpan');
            showError(`Perubahan belum tersimpan: ${error.message}`);
            throw error;
        }
    }

    /** Saves now; one request at a time, so the last arrangement always wins. */
    async function save() {
        while (currentSave) {
            await currentSave.catch(() => {});
        }

        if (!dirty) return;

        currentSave = sendSave().finally(() => { currentSave = null; });

        return currentSave.catch(() => {});
    }

    /** Waits until nothing is pending; throws when the last save failed. */
    async function flush() {
        clearTimeout(saveTimer);
        saveTimer = null;
        await save();

        if (dirty) {
            throw new Error('perubahan terakhir belum tersimpan.');
        }
    }

    function applyMove(key, day, index = null, focusAfter = false) {
        const next = moveCard(state, key, day, index);
        state = { ...state, ...next };
        renderBoard();
        scheduleSave();

        if (focusAfter) {
            root.querySelector(`[data-card="${CSS.escape(key)}"] [data-field="day"]`)?.focus();
        }
    }

    function updateCard(key, field, value) {
        state = {
            ...state,
            items: state.items.map((c) => (cardKey(c) === key ? { ...c, [field]: value } : c)),
        };

        if (field === 'pic') {
            // Re-render only this card's warning, keeping focus where it is.
            const cardEl = root.querySelector(`[data-card="${CSS.escape(key)}"]`);
            const card = state.items.find((c) => cardKey(c) === key);
            const warning = cardEl?.querySelector('[data-pic-warning]');
            if (warning && card) warning.outerHTML = picWarning(card);
            renderDayHeaders();
        }

        scheduleSave();
    }

    function unavailableReason(day, pic) {
        return state.unavailable?.[day]?.[pic] ?? null;
    }

    function picWarning(card) {
        const reason = card.pic ? unavailableReason(card.day, card.pic) : null;

        if (reason) {
            return `<p data-pic-warning class="text-xs font-medium text-danger">${escapeHtml(card.pic)} tidak masuk hari ini (${escapeHtml(reason)}).</p>`;
        }

        if (!card.pic) {
            return '<p data-pic-warning class="text-xs text-text-muted">PIC belum dipilih.</p>';
        }

        return '<span data-pic-warning hidden></span>';
    }

    function badges(card) {
        const parts = [];
        if (card.carried_over) parts.push('<span class="badge badge-warning whitespace-normal">Minggu lalu belum selesai</span>');
        if (card.overdue) parts.push('<span class="badge badge-danger">Overdue</span>');
        return parts.join(' ');
    }

    function dayOptions(card) {
        const options = [`<option value="" ${card.day === null ? 'selected' : ''}>Belum dijadwalkan</option>`];

        for (const day of state.days) {
            options.push(`<option value="${day.date}" ${card.day === day.date ? 'selected' : ''}>${escapeHtml(day.name)}</option>`);
        }

        return options.join('');
    }

    function picOptions(card) {
        const options = ['<option value="">Pilih PIC</option>'];
        const names = [...state.pics];

        if (card.pic && !names.includes(card.pic)) {
            names.unshift(card.pic);
        }

        for (const name of names) {
            const reason = unavailableReason(card.day, name);
            const label = reason ? `${name} (${reason})` : name;
            options.push(`<option value="${escapeHtml(name)}" ${card.pic === name ? 'selected' : ''}>${escapeHtml(label)}</option>`);
        }

        return options.join('');
    }

    function cardHtml(card) {
        const key = cardKey(card);
        const placed = card.day !== null;
        const due = card.due_date ? `Due ${formatShortDate(card.due_date)}` : card.plan_date ? `Plan ${formatShortDate(card.plan_date)}` : '';
        const fieldId = `wp-${key}`;
        const label = 'text-xs font-medium text-text-muted';
        const control = 'w-full min-w-0 min-h-11 py-1 text-sm lg:min-h-0';

        // White card with a stronger edge and a light-blue head on a grey
        // column, so neighbouring machines in one day read as separate items.
        // The small shadow marks the card as the thing you can pick up and drag.
        return `
            <article data-card="${escapeHtml(key)}" class="@container ${placed ? 'min-w-0' : 'w-64 shrink-0'} overflow-hidden rounded-lg border border-border-strong bg-surface shadow-sm">
                <div class="flex items-start gap-2 border-b border-border bg-primary-light px-3 py-2">
                    <span class="wp-handle mt-1 cursor-grab select-none text-primary-hover" aria-hidden="true" title="Seret">
                        <svg width="12" height="18" viewBox="0 0 14 20" fill="currentColor"><circle cx="4" cy="4" r="1.6"/><circle cx="10" cy="4" r="1.6"/><circle cx="4" cy="10" r="1.6"/><circle cx="10" cy="10" r="1.6"/><circle cx="4" cy="16" r="1.6"/><circle cx="10" cy="16" r="1.6"/></svg>
                    </span>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-baseline gap-x-2">
                            <span class="break-all text-base font-bold text-primary-active">${escapeHtml(card.machine_number)}</span>
                            <span class="text-sm font-medium text-text">${escapeHtml(card.machine_type)}</span>
                        </div>
                        <div class="mt-0.5 flex flex-wrap items-center gap-1.5">
                            <span class="text-xs text-text-muted">${escapeHtml(due)}</span>
                            ${badges(card)}
                        </div>
                    </div>
                    ${placed ? `
                    <button type="button" data-unschedule aria-label="Kembalikan ${escapeHtml(card.machine_number)} ke Belum dijadwalkan" title="Kembalikan ke Belum dijadwalkan"
                        class="-mr-1 -mt-0.5 inline-flex min-h-11 min-w-11 shrink-0 cursor-pointer items-center justify-center rounded-md text-text-muted transition-colors hover:bg-danger-light hover:text-danger focus-visible:outline-2 focus-visible:outline-primary active:bg-danger-light lg:min-h-8 lg:min-w-8">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>
                    </button>` : ''}
                </div>
                <div class="grid grid-cols-1 gap-y-1 px-3 py-2.5 @[16rem]:grid-cols-[4.75rem_minmax(0,1fr)] @[16rem]:items-center @[16rem]:gap-x-2 @[16rem]:gap-y-1.5">
                    <label class="${label}" for="${fieldId}-day">Hari</label>
                    <select id="${fieldId}-day" data-field="day" class="select ${control}">${dayOptions(card)}</select>
                    ${placed ? `
                    <label class="${label}" for="${fieldId}-pic">PIC</label>
                    <select id="${fieldId}-pic" data-field="pic" class="select ${control}">${picOptions(card)}</select>
                    <div class="@[16rem]:col-span-2">${picWarning(card)}</div>
                    <label class="${label}" for="${fieldId}-note">Konstruksi</label>
                    <input id="${fieldId}-note" data-field="construction_note" type="text" maxlength="100" value="${escapeHtml(card.construction_note ?? '')}" placeholder="mis. 0.225 ST" class="input ${control}">` : ''}
                </div>
            </article>`;
    }

    function filteredPool() {
        const q = poolFilter.trim().toLowerCase();

        return state.pool.filter((c) => {
            if (dueOnly && !(c.due_this_week || c.carried_over)) return false;
            if (!q) return true;
            return `${c.machine_number} ${c.machine_type}`.toLowerCase().includes(q);
        });
    }

    /** Client check of a day's type mix; null when it can be saved. */
    function dayConfigError(day) {
        const total = day.types.reduce((sum, row) => sum + row.count, 0);

        if (total > day.limit) {
            return `Kombinasi tipe berjumlah ${total} mesin, melebihi maks. mesin ${day.limit}. Belum disimpan.`;
        }

        const chosen = day.types.map((row) => row.type).filter(Boolean);
        const duplicate = chosen.find((type, i) => chosen.indexOf(type) !== i);

        return duplicate ? `Tipe ${duplicate} dipilih dua kali. Belum disimpan.` : null;
    }

    function typeRowsHtml(day) {
        const max = state.max_day_limit ?? 6;

        return day.types.map((row, index) => {
            const typeOptions = [`<option value="" ${row.type === '' ? 'selected' : ''}>Pilih tipe</option>`]
                .concat((state.machine_types ?? []).map((type) =>
                    `<option value="${escapeHtml(type)}" ${type === row.type ? 'selected' : ''}>${escapeHtml(type)}</option>`))
                .join('');
            const countOptions = Array.from({ length: max }, (_, n) =>
                `<option value="${n + 1}" ${n + 1 === row.count ? 'selected' : ''}>${n + 1}</option>`).join('');
            const placed = row.type ? state.items.filter((c) => c.day === day.date && c.type_family === row.type).length : null;
            const name = row.type || `baris ${index + 1}`;

            return `
                <div class="grid gap-1 rounded-md border border-border bg-surface p-1.5">
                    <select data-type-field="type" data-date="${day.date}" data-index="${index}" aria-label="Tipe mesin ${escapeHtml(name)}" class="select min-h-11 w-full min-w-0 py-0.5 text-xs lg:min-h-0">${typeOptions}</select>
                    <div class="flex items-center gap-1.5">
                        <select data-type-field="count" data-date="${day.date}" data-index="${index}" aria-label="Jumlah mesin ${escapeHtml(name)}" class="select min-h-11 w-14 shrink-0 py-0.5 text-xs lg:min-h-0">${countOptions}</select>
                        <span class="min-w-0 flex-1 text-[11px] text-text-muted">${placed !== null ? `terisi ${placed}` : 'mesin'}</span>
                        <button type="button" data-type-remove data-date="${day.date}" data-index="${index}" aria-label="Hapus tipe ${escapeHtml(name)}" class="btn btn-ghost min-h-11 min-w-11 shrink-0 px-0 py-0 text-base leading-none lg:min-h-7 lg:min-w-7">&times;</button>
                    </div>
                </div>`;
        }).join('');
    }

    function dayHeader(day) {
        const cards = state.items.filter((c) => c.day === day.date);
        const off = Object.entries(state.unavailable?.[day.date] ?? {});
        const offText = off.length
            ? `<p class="text-xs text-danger">Tidak masuk: ${off.map(([n, r]) => `${escapeHtml(n)} (${escapeHtml(r)})`).join(', ')}</p>`
            : '';
        const over = cards.length > day.limit;
        const count = over
            ? `<p class="text-xs font-medium text-warning-hover">${cards.length} mesin, lewat batas ${day.limit}</p>`
            : `<p class="text-xs text-text-muted">${cards.length} mesin</p>`;
        const options = Array.from({ length: (state.max_day_limit ?? 6) + 1 }, (_, n) =>
            `<option value="${n}" ${n === day.limit ? 'selected' : ''}>${n}</option>`).join('');
        const error = dayErrors[day.date];

        return `
            <h3 class="font-semibold text-text">${escapeHtml(day.label)}</h3>
            <div class="mt-1 flex flex-wrap items-center justify-between gap-2">
                ${count}
                <label class="flex items-center gap-1.5 whitespace-nowrap text-xs font-medium text-text-muted">
                    Maks. mesin
                    <select data-day-limit="${day.date}" class="select min-h-11 w-16 shrink-0 py-0.5 text-xs lg:min-h-0">${options}</select>
                </label>
            </div>
            <div class="mt-2 grid gap-1.5">
                <p class="text-xs font-medium text-text-muted">Kombinasi tipe</p>
                ${typeRowsHtml(day)}
                ${error ? `<p class="text-xs font-medium text-danger" role="alert">${escapeHtml(error)}</p>` : ''}
                <button type="button" data-type-add="${day.date}" class="link min-h-11 justify-self-start text-xs font-semibold disabled:cursor-not-allowed disabled:text-text-disabled lg:min-h-0" ${day.limit === 0 ? 'disabled' : ''}>+ Tambah tipe</button>
            </div>
            ${offText}`;
    }

    function updateDay(date, change) {
        state = { ...state, days: state.days.map((d) => (d.date === date ? change(d) : d)) };
    }

    /** Validates and saves a day's limit + type mix; an invalid mix stays on screen, unsaved. */
    async function saveDayConfig(date) {
        const day = state.days.find((d) => d.date === date);
        const error = dayConfigError(day);
        dayErrors[date] = error;
        renderDayHeaders();

        if (error) {
            setStatus(`Pengaturan ${day.name} belum disimpan`);
            return;
        }

        try {
            await request(root.dataset.dayLimitsUrl, 'PUT', {
                limits: { [date]: day.limit },
                types: { [date]: day.types.filter((row) => row.type) },
            });
            setStatus(`Pengaturan ${day.name} tersimpan`);
        } catch (error) {
            dayErrors[date] = error.message;
            renderDayHeaders();
        }
    }

    /** True when every day's limit and type mix equals the area default. */
    function daysMatchDefaults() {
        const defaults = state.day_defaults ?? [];
        const norm = (types) => JSON.stringify(types.filter((t) => t.type).map((t) => [t.type, t.count]));

        return state.days.every((day, i) => defaults[i]
            && day.limit === defaults[i].limit
            && norm(day.types) === norm(defaults[i].types));
    }

    function renderDefaultsBar() {
        const bar = root.querySelector('[data-defaults-bar]');
        if (!bar) return;

        const area = escapeHtml(state.plan.area);
        const matches = daysMatchDefaults();
        const hadFocus = bar.contains(document.activeElement);

        bar.innerHTML = `
            <p class="text-text-muted">${matches
                ? `Maks. mesin dan kombinasi tipe sesuai default ${area}.`
                : `Maks. mesin atau kombinasi tipe minggu ini sudah diubah dari default ${area}.`}</p>
            <button type="button" data-day-defaults class="btn btn-outline btn-sm min-h-11 lg:min-h-0" ${matches ? 'disabled' : ''}>Kembalikan ke default ${area}</button>`;

        if (hadFocus) bar.querySelector('[data-day-defaults]')?.focus();
    }

    async function restoreDayDefaults(button) {
        button.disabled = true;

        try {
            await flush();
            const result = await request(root.dataset.dayDefaultsUrl, 'POST');
            state = result.state;
            outputs = result;
            Object.keys(dayErrors).forEach((k) => delete dayErrors[k]);
            renderBoard();
            renderEmail();
            setStatus(`Maks. mesin dan kombinasi tipe kembali ke default ${state.plan.area}`);
        } catch (error) {
            showError(`Gagal mengembalikan pengaturan default: ${error.message}`);
            button.disabled = false;
        }
    }

    /** Re-renders the day headers, putting focus back on the control that had it. */
    function renderDayHeaders() {
        const active = document.activeElement;
        let refocus = null;

        if (active?.closest?.('[data-day-header]')) {
            if (active.dataset.dayLimit) refocus = `[data-day-limit="${active.dataset.dayLimit}"]`;
            else if (active.dataset.typeAdd) refocus = `[data-type-add="${active.dataset.typeAdd}"]`;
            else if (active.dataset.typeField) refocus = `[data-type-field="${active.dataset.typeField}"][data-date="${active.dataset.date}"][data-index="${active.dataset.index}"]`;
        }

        for (const day of state.days) {
            const header = root.querySelector(`[data-day-header="${day.date}"]`);
            if (header) header.innerHTML = dayHeader(day);
        }

        if (refocus) root.querySelector(refocus)?.focus();
        renderDefaultsBar();
    }

    function renderBoard() {
        sortables.forEach((s) => s.destroy());
        sortables = [];

        const pool = filteredPool();
        const nothingAtAll = state.pool.length === 0 && state.items.length === 0;

        if (nothingAtAll) {
            els.board.innerHTML = `
                <div class="rounded-xl border border-dashed border-border-strong p-6 text-sm text-text-muted">
                    Tidak ada PM Schedule ${escapeHtml(state.plan.area)} yang masih terbuka sampai akhir bulan ini.
                    Import atau tambah rencana PM dulu di <a class="link" href="${escapeHtml(root.dataset.pmSchedulesUrl)}">PM Schedule</a>, lalu buka halaman ini lagi.
                </div>`;
            els.autofill.disabled = true;
            els.reset.disabled = true;
            return;
        }

        els.autofill.disabled = false;
        els.reset.disabled = state.items.length === 0;

        const noPics = state.pics.length === 0
            ? `<div class="alert alert-warning">Belum ada user dengan role PIC di area ${escapeHtml(state.plan.area)}, jadi PIC belum bisa dipilih.</div>`
            : '';

        const poolEmptyText = state.pool.length === 0
            ? 'Semua mesin sudah dijadwalkan.'
            : 'Tidak ada mesin yang cocok dengan pencarian.';

        els.board.innerHTML = `
            ${noPics}
            <section class="min-w-0 rounded-xl bg-surface-muted p-3">
                <div class="mb-3 flex flex-wrap items-end gap-x-4 gap-y-2">
                    <div class="mr-auto">
                        <h3 class="font-semibold text-text">Belum dijadwalkan</h3>
                        <p class="text-xs text-text-muted">${state.pool.length} PM terbuka belum masuk jadwal. Geser ke samping untuk melihat semuanya.</p>
                    </div>
                    <label class="grid w-full gap-1 text-xs font-medium text-text-muted sm:w-64">
                        Cari mesin
                        <input type="search" data-pool-filter value="${escapeHtml(poolFilter)}" placeholder="Nomor atau tipe" class="input w-full min-w-0 min-h-11 py-1 text-sm lg:min-h-0">
                    </label>
                    <label class="flex min-h-11 items-center gap-2 text-sm text-text lg:min-h-9">
                        <input type="checkbox" data-due-only class="checkbox" ${dueOnly ? 'checked' : ''}>
                        Hanya yang due sampai Jumat
                    </label>
                </div>
                <div data-list="" role="region" aria-label="Mesin belum dijadwalkan" tabindex="0" class="flex min-h-24 items-start gap-3 overflow-x-auto pb-2">
                    ${pool.map(cardHtml).join('') || `<p class="p-2 text-sm text-text-muted">${poolEmptyText}</p>`}
                </div>
            </section>
            <div data-defaults-bar class="flex min-h-11 flex-wrap items-center justify-between gap-2 text-sm"></div>
            <div class="grid min-w-0 gap-3 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
                ${state.days.map((day) => {
                    const cards = state.items.filter((c) => c.day === day.date);
                    return `
                        <div class="flex min-w-0 flex-col rounded-xl bg-surface-muted p-3">
                            <div data-day-header="${day.date}" class="mb-3 border-b border-border-strong pb-2">${dayHeader(day)}</div>
                            <div data-list="${day.date}" class="grid min-h-24 flex-1 auto-rows-max content-start gap-3">
                                ${cards.map(cardHtml).join('') || '<p data-empty class="rounded-lg border border-dashed border-border-strong bg-surface p-3 text-xs text-text-muted">Seret mesin ke sini, atau pilih hari ini di kartu mesin.</p>'}
                            </div>
                        </div>`;
                }).join('')}
            </div>`;

        renderDefaultsBar();

        els.board.querySelectorAll('[data-list]').forEach((list) => {
            sortables.push(Sortable.create(list, {
                group: 'weekly-plan',
                handle: '.wp-handle',
                draggable: '[data-card]',
                animation: 120,
                onEnd: (event) => {
                    const key = event.item.dataset.card;
                    const day = event.to.dataset.list || null;

                    // Order inside the pool has no meaning; put the DOM back.
                    if (day === null && event.from === event.to) {
                        renderBoard();
                        return;
                    }

                    const index = [...event.to.querySelectorAll('[data-card]')].indexOf(event.item);
                    applyMove(key, day, day === null ? null : index);
                },
            }));
        });
    }

    function outputBlock({ title, description, html, copyLabel, attr }) {
        return `
            <div class="grid gap-2">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h3 class="font-semibold text-text">${title}</h3>
                        ${description ? `<p class="text-sm text-text-muted">${description}</p>` : ''}
                    </div>
                    <button type="button" class="btn btn-outline min-h-11 lg:min-h-0" ${attr}>${copyLabel}</button>
                </div>
                <div class="overflow-x-auto rounded-xl border border-border bg-surface-sunken p-3 text-sm text-text">${html}</div>
            </div>`;
    }

    function renderEmail() {
        const { email, revision, sent_at: sentAt } = outputs;

        if (!email?.text) {
            els.email.innerHTML = `
                <h2 class="text-base font-semibold text-text">Teks email</h2>
                <p class="mt-1 text-sm text-text-muted">Teks email muncul di sini setelah ada mesin yang ditaruh di salah satu hari.</p>`;
        } else {
            const sentLine = sentAt
                ? `Ditandai terkirim ${escapeHtml(sentAt)}. Kalau jadwal berubah lagi, kirim email revisi di bawah.`
                : 'Setelah email dikirim, tandai di sini supaya perubahan berikutnya bisa dikirim sebagai revisi.';

            let revisionHtml = '';

            if (revision) {
                revisionHtml = revision.count > 0
                    ? outputBlock({
                        title: `Email revisi (${revision.count} perubahan)`,
                        description: 'Hanya berisi perubahan sejak terakhir dikirim, lalu jadwal terbaru lengkap.',
                        html: revision.html,
                        copyLabel: 'Copy email revisi',
                        attr: 'data-copy="revision"',
                    })
                    : '<p class="text-sm text-text-muted">Belum ada perubahan sejak email terakhir ditandai terkirim.</p>';
            }

            els.email.innerHTML = `
                <div class="grid gap-5">
                    ${outputBlock({
                        title: 'Teks email jadwal',
                        description: 'Copy lalu paste ke body email. Judul hari tetap tebal dan baris tetap sejajar.',
                        html: email.html,
                        copyLabel: 'Copy ke email',
                        attr: 'data-copy="email"',
                    })}
                    <div class="flex flex-wrap items-center gap-3 border-t border-border pt-4">
                        <button type="button" class="btn btn-outline min-h-11 lg:min-h-0" data-mark-sent>${sentAt ? 'Tandai terkirim lagi' : 'Tandai sudah dikirim'}</button>
                        <p class="text-sm text-text-muted">${sentLine}</p>
                    </div>
                    ${revisionHtml}
                </div>`;
        }

        renderRealization();
        renderPicSync();
    }

    function renderPicSync() {
        const sync = outputs.pic_sync ?? { pending: 0, applied_at: null };
        const when = sync.applied_at ? ` Terakhir diterapkan ${escapeHtml(sync.applied_at)}.` : '';

        if (sync.pending > 0) {
            els.picSyncStatus.className = 'font-medium text-warning-hover sm:text-right';
            els.picSyncStatus.innerHTML = `${sync.pending} perubahan PIC belum diterapkan ke PM Schedule.${when}`;
        } else {
            els.picSyncStatus.className = 'text-text-muted sm:text-right';
            els.picSyncStatus.innerHTML = sync.applied_at
                ? `PIC di PM Schedule sudah sama dengan jadwal ini.${when}`
                : 'PIC di jadwal ini belum diterapkan ke PM Schedule. Teknisi baru melihat PM-nya setelah PIC diterapkan.';
        }
    }

    /** Confirmation text listing what "Terapkan PIC" is about to change. */
    function picSyncMessage(preview) {
        const sample = (kind) => preview.actions.filter((a) => a.kind === kind).slice(0, 3)
            .map((a) => `${a.machine}: ${a.from ?? 'kosong'} jadi ${a.to ?? 'kosong'}`).join('; ');
        const lines = [];

        if (preview.set) lines.push(`- ${preview.set} PM diisi PIC dari jadwal ini`);
        if (preview.replace) lines.push(`- ${preview.replace} PM yang sudah punya PIC lain akan diganti (${sample('replace')})`);
        if (preview.restore) lines.push(`- ${preview.restore} PM yang keluar dari jadwal dikembalikan ke PIC sebelumnya (${sample('restore')})`);
        if (preview.locked) lines.push(`- ${preview.locked} PM dilewati karena sudah dikerjakan atau selesai`);
        if (preview.changed) lines.push(`- ${preview.changed} PM dilewati karena PIC-nya sudah diubah manual di PM Schedule`);

        return `Terapkan PIC ke PM Schedule?\n\n${lines.join('\n')}`;
    }

    async function runPicSync() {
        els.picSync.disabled = true;

        try {
            await flush();
            const preview = await request(root.dataset.picSyncUrl, 'GET');

            if (preview.pending === 0) {
                setStatus(preview.locked || preview.changed
                    ? 'Tidak ada PIC yang bisa diterapkan, sisanya sedang dikerjakan atau diubah manual'
                    : 'PIC di PM Schedule sudah sama dengan jadwal ini');
                return;
            }

            if (!window.confirm(picSyncMessage(preview))) return;

            els.picSync.setAttribute('aria-busy', 'true');
            setStatus('Menerapkan PIC ke PM Schedule...');
            const result = await request(root.dataset.picSyncUrl, 'POST');
            outputs = result;
            renderEmail();
            const r = result.result;
            setStatus(`PIC diterapkan: ${r.set + r.replace} diisi, ${r.restore} dikembalikan`);
        } catch (error) {
            showError(`Gagal menerapkan PIC: ${error.message}`);
        } finally {
            els.picSync.disabled = false;
            els.picSync.removeAttribute('aria-busy');
        }
    }

    function renderRealization() {
        const r = outputs.realization;

        if (!r) {
            els.realization.hidden = true;
            els.realization.innerHTML = '';
            return;
        }

        els.realization.hidden = false;
        els.realization.innerHTML = `
            <details>
                <summary class="flex min-h-11 cursor-pointer flex-wrap items-center gap-x-3 gap-y-1 lg:min-h-0">
                    <span class="font-semibold text-text">Realisasi minggu lalu (${escapeHtml(r.week)})</span>
                    <span class="text-sm text-text-muted">${r.total - r.open.length} dari ${r.total} mesin selesai, ${r.open.length} belum</span>
                </summary>
                <div class="mt-3 grid gap-3">
                    ${r.open.length ? '<p class="text-sm text-text-muted">Mesin yang belum selesai sudah ada di kolom Belum dijadwalkan dengan tanda "Minggu lalu belum selesai".</p>' : ''}
                    ${outputBlock({ title: 'Ringkasan untuk email', description: '', html: r.html, copyLabel: 'Copy ringkasan', attr: 'data-copy="realization"' })}
                </div>
            </details>`;
    }

    async function handleCopy(button) {
        const kind = button.dataset.copy;
        const source = kind === 'revision' ? outputs.revision : kind === 'realization' ? outputs.realization : outputs.email;
        const label = button.textContent;

        const ok = await copyRich(source.html, source.text);
        button.textContent = ok ? 'Tersalin' : 'Gagal copy, blok teksnya manual';
        setTimeout(() => { button.textContent = label; }, 2000);
    }

    async function runAutofill() {
        els.autofill.disabled = true;
        els.autofill.setAttribute('aria-busy', 'true');
        setStatus('Mengisi jadwal otomatis...');
        try {
            await flush();
            const result = await request(root.dataset.autofillUrl, 'POST');
            state = result.state;
            outputs = result;
            showError(null);
            const left = result.left > 0 ? `, ${result.left} tidak muat karena batas per hari sudah penuh` : '';
            setStatus(result.placed > 0 || result.left > 0
                ? `${result.placed} mesin ditempatkan${left}`
                : 'Tidak ada mesin due minggu ini yang belum dijadwalkan');
            renderBoard();
            renderEmail();
        } catch (error) {
            showError(`Isi otomatis gagal: ${error.message}`);
        } finally {
            els.autofill.disabled = false;
            els.autofill.removeAttribute('aria-busy');
        }
    }

    async function markSent(button) {
        button.disabled = true;

        try {
            await flush();
            const result = await request(root.dataset.markSentUrl, 'POST');
            state = result.state;
            outputs = result;
            renderEmail();
        } catch (error) {
            showError(`Gagal menandai terkirim: ${error.message}`);
            button.disabled = false;
        }
    }

    root.addEventListener('change', (event) => {
        const target = event.target;
        const cardEl = target.closest('[data-card]');

        if (target.matches('[data-day-limit]')) {
            updateDay(target.dataset.dayLimit, (d) => ({ ...d, limit: Number(target.value) }));
            saveDayConfig(target.dataset.dayLimit);
            return;
        }

        if (target.matches('[data-type-field]')) {
            const { date, index, typeField } = target.dataset;
            const value = typeField === 'count' ? Number(target.value) : target.value;
            updateDay(date, (d) => ({ ...d, types: d.types.map((row, i) => (i === Number(index) ? { ...row, [typeField]: value } : row)) }));
            saveDayConfig(date);
            return;
        }

        if (target.matches('[data-due-only]')) {
            dueOnly = target.checked;
            renderBoard();
            return;
        }

        if (!cardEl) return;

        const key = cardEl.dataset.card;
        const field = target.dataset.field;

        if (field === 'day') {
            applyMove(key, target.value || null, null, true);
        } else if (field === 'pic') {
            updateCard(key, 'pic', target.value || null);
        }
    });

    root.addEventListener('input', (event) => {
        const target = event.target;

        if (target.matches('[data-pool-filter]')) {
            poolFilter = target.value;
            const pos = target.selectionStart;
            renderBoard();
            const input = root.querySelector('[data-pool-filter]');
            input?.focus();
            input?.setSelectionRange(pos, pos);
            return;
        }

        if (target.dataset.field === 'construction_note') {
            updateCard(target.closest('[data-card]').dataset.card, 'construction_note', target.value);
        }
    });

    root.addEventListener('click', (event) => {
        const defaultsButton = event.target.closest('[data-day-defaults]');
        if (defaultsButton) {
            restoreDayDefaults(defaultsButton);
            return;
        }

        const unschedule = event.target.closest('[data-unschedule]');
        if (unschedule) {
            applyMove(unschedule.closest('[data-card]').dataset.card, null, null, true);
            return;
        }

        const copy = event.target.closest('[data-copy]');
        if (copy) handleCopy(copy);

        const addType = event.target.closest('[data-type-add]');
        if (addType) {
            const date = addType.dataset.typeAdd;
            updateDay(date, (d) => ({ ...d, types: [...d.types, { type: '', count: 1 }] }));
            renderDayHeaders();
            const rows = root.querySelectorAll(`[data-type-field="type"][data-date="${date}"]`);
            rows[rows.length - 1]?.focus();
            saveDayConfig(date);
        }

        const removeType = event.target.closest('[data-type-remove]');
        if (removeType) {
            const { date, index } = removeType.dataset;
            updateDay(date, (d) => ({ ...d, types: d.types.filter((_, i) => i !== Number(index)) }));
            renderDayHeaders();
            root.querySelector(`[data-type-add="${date}"]`)?.focus();
            saveDayConfig(date);
        }

        if (event.target.closest('[data-mark-sent]')) markSent(event.target.closest('[data-mark-sent]'));
        if (event.target.closest('[data-retry-save]')) {
            dirty = true;
            save();
        }
    });

    // Keyboard scrolling for the horizontal "Belum dijadwalkan" strip when the
    // strip itself (not a control inside a card) has focus.
    root.addEventListener('keydown', (event) => {
        const strip = event.target;
        if (!strip.matches?.('[data-list=""]')) return;

        const step = (strip.querySelector('[data-card]')?.offsetWidth ?? 256) + 12;
        const moves = { ArrowRight: step, ArrowLeft: -step, End: strip.scrollWidth, Home: -strip.scrollWidth };

        if (event.key in moves) {
            event.preventDefault();
            strip.scrollBy({ left: moves[event.key] });
        }
    });

    els.autofill.addEventListener('click', runAutofill);
    els.picSync.addEventListener('click', runPicSync);

    els.reset.addEventListener('click', async () => {
        const count = state.items.length;
        if (count === 0) return;

        const ok = window.confirm(`Kosongkan ${count} mesin dari Senin sampai Jumat? Semua kembali ke kolom Belum dijadwalkan. Batas mesin per hari tidak berubah.`);
        if (!ok) return;

        state = { ...state, ...resetCards(state) };
        renderBoard();
        dirty = true;

        try {
            await flush();
            setStatus(`Jadwal dikosongkan, ${count} mesin kembali ke Belum dijadwalkan`);
        } catch (error) {
            showError(`Gagal mengosongkan jadwal: ${error.message}`);
        }
    });

    window.addEventListener('beforeunload', (event) => {
        if (dirty || currentSave) {
            event.preventDefault();
        }
    });

    renderBoard();
    renderEmail();
}

const root = typeof document !== 'undefined' ? document.getElementById('weekly-plan') : null;
if (root) initWeeklyPlan(root);
