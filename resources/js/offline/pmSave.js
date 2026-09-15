/**
 * FreeDOMS offline-first — PM Save offline (Phase 1, Task 5).
 *
 * Reuses the exact same server-side business rule as the online endpoint
 * (PMScheduleSaveService, via /api/sync's PM_SAVE handler — see Task 2)
 * by sending the SAME field whitelist PMScheduleController::update() /
 * SyncOperationController::processPmSave() already use. Nothing here
 * re-implements PM Save's business logic; this module only builds the
 * aggregate payload from the Fill PM form, decides where it goes (online
 * request vs. local queue), and keeps a small local view of "this PM was
 * saved offline" until that operation is actually synced.
 *
 * Deliberately does NOT touch PM Checklist (PM_CHECKLIST_SAVE) — that
 * stays a separate step/request/task, exactly like the online flow.
 */

import { generateUuid } from './uuid.js';
import { currentUserId } from './scope.js';
import * as SyncQueue from './queue.js';
import * as Drafts from './drafts.js';
import { clearLocalPmSaveOverlay, getLocalPmSaveOverlay, setLocalPmSaveOverlay } from './masterData.js';
import { sendQueuedOperation } from './sync.js';
import { parseFormToNestedObject } from './formParser.js';

// Re-exported for backward compatibility — the implementation moved to
// formParser.js in Task 8 (shared by every offline feature with a
// dynamic-rows form, not just PM Save), but this import path keeps
// working for any existing caller/test.
export { parseFormToNestedObject };

export const TRANSACTION_TYPE = 'PM_SAVE';
export const DRAFT_TYPE = 'PM';

/**
 * The exact field whitelist PMScheduleSaveService::save() reads (see
 * PMScheduleController::update()'s $request->only([...]) and
 * SyncOperationController::processPmSave()'s identical list) —
 * deliberately NOT the whole form (Task 5 section 5): anything else the
 * <form> happens to contain (_token, _method, the read-only
 * completion_date_display field, ...) is never part of this payload.
 * PM Checklist fields are never part of this list either — that's a
 * separate transaction type (section 9).
 */
export const PM_SAVE_FIELDS = [
    'order_number', 'pic', 'oil_change', 'greasing', 'wo_zsbp', 'remarks',
    'sessions', 'actual_date', 'start_time', 'end_time',
    'measurements', 'problems', 'spareparts',
];

export class PmSaveValidationError extends Error {
    constructor(message, errors = {}) {
        super(message);
        this.name = 'PmSaveValidationError';
        this.errors = errors;
    }
}

export class PmSaveBlockedError extends Error {
    constructor(message) {
        super(message);
        this.name = 'PmSaveBlockedError';
    }
}

/**
 * Builds the PM_SAVE payload from the Fill PM <form> — parses it, then
 * keeps only PM_SAVE_FIELDS. `new FormData(form)` already reflects
 * whichever measurement inputs currently have a live `name` attribute
 * (pm/edit.js's syncMeasurementInput() toggles `name`/`data-name` between
 * the desktop table row and the mobile card for the same item depending
 * on viewport width), so this does not need to know about that toggle
 * itself.
 */
export function buildPayloadFromForm(form) {
    const parsed = parseFormToNestedObject(new FormData(form));
    const payload = {};

    for (const field of PM_SAVE_FIELDS) {
        if (Object.prototype.hasOwnProperty.call(parsed, field)) {
            payload[field] = parsed[field];
        }
    }

    return payload;
}

function isBlank(value) {
    return value === undefined || value === null || String(value).trim() === '';
}

/**
 * Mirrors (does NOT replace) the required-field checks from
 * PMScheduleSaveService::rules() that are meaningful to check
 * client-side, for fast, honest "this will definitely fail" feedback
 * before the operation even reaches the queue. This is explicitly NOT a
 * security boundary (Task 5 section 11) — the server unconditionally
 * re-validates the exact same rules via PMScheduleSaveService::rules()
 * regardless of what this function decides.
 *
 * @param {object} payload
 * @returns {void} throws PmSaveValidationError if invalid.
 */
export function validatePayloadLocally(payload) {
    const errors = {};

    if (isBlank(payload.order_number)) {
        errors.order_number = 'Order Number wajib diisi.';
    }

    if (isBlank(payload.pic)) {
        errors.pic = 'PIC wajib diisi.';
    }

    if (Object.prototype.hasOwnProperty.call(payload, 'sessions')) {
        Object.entries(payload.sessions ?? {}).forEach(([key, session]) => {
            if (isBlank(session?.actual_date)) {
                errors[`sessions.${key}.actual_date`] = 'Tanggal sesi wajib diisi.';
            }
            if (isBlank(session?.start_time)) {
                errors[`sessions.${key}.start_time`] = 'Jam mulai sesi wajib diisi.';
            }
        });
    } else {
        if (isBlank(payload.actual_date)) {
            errors.actual_date = 'Tanggal pelaksanaan wajib diisi.';
        }
        if (isBlank(payload.start_time)) {
            errors.start_time = 'Jam mulai wajib diisi.';
        }
    }

    // Mirrors PMScheduleSaveService::rules()'s 'spareparts.*.qty' =>
    // 'nullable|integer|min:1' — only checked when a sparepart was
    // actually picked for that row.
    Object.entries(payload.spareparts ?? {}).forEach(([key, item]) => {
        if (isBlank(item?.sparepart_id)) {
            return;
        }

        const qty = Number(item?.qty);

        if (!Number.isInteger(qty) || qty < 1) {
            errors[`spareparts.${key}.qty`] = 'Qty sparepart harus berupa angka bulat minimal 1.';
        }
    });

    if (Object.keys(errors).length > 0) {
        throw new PmSaveValidationError('Data PM belum lengkap/valid untuk disimpan.', errors);
    }
}

/**
 * Only one PM_SAVE (or PM_START — the two operate on the same aggregate
 * "is this device already mid-transaction for a DIFFERENT PM" concern) is
 * allowed to sit unsynced locally per PIC at a time, for the same reason
 * as Task 4 section 15's PM_START guard: it prevents the obvious case of
 * a device silently accumulating unrelated offline transactions for
 * several different PM Schedules before any of them has even reached the
 * server. This is NOT the server's full business-rule authority (that
 * stays in PMScheduleSaveService / the activity-conflict rule) — it is
 * only a local, device-side sanity check.
 */
async function findBlockingLocalOperation(userId, pmScheduleId) {
    const [pending, syncing] = await Promise.all([
        SyncQueue.listByStatus(SyncQueue.QueueStatus.PENDING),
        SyncQueue.listByStatus(SyncQueue.QueueStatus.SYNCING),
    ]);

    return [...pending, ...syncing].find((operation) => (
        operation.transaction_type === TRANSACTION_TYPE
        && operation.user_id === userId
        && operation.payload?.pm_schedule_id !== pmScheduleId
    ));
}

/**
 * Queues a PM_SAVE operation entirely locally — NO network request is
 * made. Throws PmSaveValidationError if the payload fails the local
 * mirror-checks, or PmSaveBlockedError if this PIC already has a
 * different PM_SAVE pending/syncing locally. Returns the queue record.
 *
 * @param {{ pmScheduleId: number, payload: object, expectedStatus: string }} params
 */
export async function saveOffline({ pmScheduleId, payload, expectedStatus }) {
    validatePayloadLocally(payload);

    const userId = currentUserId();
    const blocking = await findBlockingLocalOperation(userId, pmScheduleId);

    if (blocking) {
        throw new PmSaveBlockedError(
            `PM Schedule #${blocking.payload?.pm_schedule_id} masih menunggu sinkronisasi. Selesaikan/sinkronkan itu dahulu sebelum menyimpan PM lain secara offline.`
        );
    }

    const operationUuid = generateUuid();

    const record = await SyncQueue.enqueue({
        operationUuid,
        transactionType: TRANSACTION_TYPE,
        payload: { pm_schedule_id: pmScheduleId, ...payload },
        // Minimal expected_state (Task 5 section 14): what this device
        // believes the PM's status is right now, so the server can detect
        // if it changed since (SyncOperationController::processPmSave()
        // compares this against the PM's ACTUAL current status, never
        // just updated_at).
        expectedState: { status: expectedStatus },
        userId,
    });

    await setLocalPmSaveOverlay(pmScheduleId, {
        local_save_status: 'saved_offline',
        pending_save_operation_uuid: operationUuid,
        local_save_updated_at: new Date().toISOString(),
    });

    return record;
}

export { getLocalPmSaveOverlay };

/**
 * Sends one already-queued PM_SAVE operation to /api/sync via the shared
 * sync.js plumbing, then — only on processed/already_processed — clears
 * the local "saved offline" overlay AND the matching draft (Task 5
 * section 10: a draft is only safe to discard once the operation it
 * became has actually been confirmed by the server, never just because it
 * was queued).
 *
 * Deliberately NOT wired up to anything automatic (no setInterval, no
 * "online" listener) — see Task 4 section 17, unchanged in Task 5.
 *
 * @param {string} operationUuid
 */
export async function processQueuedOperation(operationUuid) {
    const { entry, body } = await sendQueuedOperation(operationUuid);

    if (
        (body.status === 'processed' || body.status === 'already_processed')
        && entry.transaction_type === TRANSACTION_TYPE
        && entry.payload?.pm_schedule_id !== undefined
    ) {
        const pmScheduleId = entry.payload.pm_schedule_id;

        await clearLocalPmSaveOverlay(pmScheduleId);

        const draft = await Drafts.getDraftByReference({
            userId: entry.user_id,
            draftType: DRAFT_TYPE,
            referenceId: pmScheduleId,
        });

        if (draft) {
            await Drafts.deleteDraft(draft.draft_id);
        }
    }

    return body;
}
