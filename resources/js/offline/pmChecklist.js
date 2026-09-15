/**
 * FreeDOMS offline-first — PM Checklist Save offline (Phase 1, Task 6).
 *
 * Reuses the exact same server-side business rule as the online endpoint
 * (PMChecklistSaveService, via /api/sync's PM_CHECKLIST_SAVE handler —
 * see Task 2) by sending the same field shape
 * PMScheduleController::saveChecklist() already posts. Nothing here
 * re-implements the checklist business logic (actual_date-from-session,
 * status recalculation, ...) — that stays entirely server-side in
 * PMChecklistSaveService, run only once the operation is actually synced.
 *
 * Deliberately kept a SEPARATE transaction_type/queue-entry/draft/overlay
 * from PM_SAVE (Task 5) and PM_START (Task 4) — the checklist page is
 * still its own step/request, exactly like the online flow.
 */

import { generateUuid } from './uuid.js';
import { currentUserId } from './scope.js';
import * as SyncQueue from './queue.js';
import * as Drafts from './drafts.js';
import { clearLocalPmChecklistOverlay, getLocalPmChecklistOverlay, setLocalPmChecklistOverlay } from './masterData.js';
import { sendQueuedOperation } from './sync.js';
import { parseFormToNestedObject } from './formParser.js';

// Re-exported for backward compatibility — the implementation moved to
// formParser.js in Task 8 (shared by every offline feature with a
// dynamic-rows form), but this import path keeps working for any existing
// caller/test.
export { parseFormToNestedObject };

export const TRANSACTION_TYPE = 'PM_CHECKLIST_SAVE';
export const DRAFT_TYPE = 'PM_CHECKLIST';

/** The only checklist item fields PMChecklistSaveService::save() reads. */
const CHECKLIST_ITEM_FIELDS = ['machine_checklist_id', 'clean', 'check', 'lubrication', 'replace', 'remarks'];

export class PmChecklistValidationError extends Error {
    constructor(message, errors = {}) {
        super(message);
        this.name = 'PmChecklistValidationError';
        this.errors = errors;
    }
}

export class PmChecklistBlockedError extends Error {
    constructor(message) {
        super(message);
        this.name = 'PmChecklistBlockedError';
    }
}

/**
 * Builds the PM_CHECKLIST_SAVE payload's `checklists` array from the
 * checklist <form> — the same field whitelist PMChecklistSaveService::save()
 * reads (machine_checklist_id, clean, check, lubrication, replace,
 * remarks), never a raw dump of the form (Task 6 section 4). Converted to
 * a real array (checklist rows are never sparse — see above) since that
 * is the shape SyncOperationController::processPmChecklistSave() casts
 * `payload['checklists']` through either way.
 */
export function buildChecklistsFromForm(form) {
    const parsed = parseFormToNestedObject(new FormData(form));
    const rows = parsed.checklists ?? {};

    return Object.keys(rows)
        .sort((a, b) => Number(a) - Number(b))
        .map((key) => {
            const row = rows[key];
            const item = {};

            for (const field of CHECKLIST_ITEM_FIELDS) {
                if (Object.prototype.hasOwnProperty.call(row, field)) {
                    item[field] = row[field];
                }
            }

            return item;
        });
}

/**
 * Minimal, honest local sanity check — NOT a mirror of
 * PMChecklistSaveService::completionErrors() (that depends on PM Schedule
 * fields — oil_change/greasing/wo_zsbp/remarks/problems/measurements/
 * spareparts — the checklist page itself does not render, so this device
 * cannot evaluate that rule client-side without guessing). This is
 * explicitly NOT a security boundary (Task 6 section 4/Task 5 section
 * 11): the server unconditionally re-runs completionErrors() and the
 * full save() via PMChecklistSaveService regardless of what this decides.
 */
export function validateChecklistsLocally(checklists) {
    const errors = {};

    if (!Array.isArray(checklists) || checklists.length === 0) {
        errors.checklists = 'Checklist belum diisi.';
    } else {
        checklists.forEach((item, index) => {
            if (item?.machine_checklist_id === undefined || item?.machine_checklist_id === null || item.machine_checklist_id === '') {
                errors[`checklists.${index}.machine_checklist_id`] = 'Checklist item tidak valid.';
            }
        });
    }

    if (Object.keys(errors).length > 0) {
        throw new PmChecklistValidationError('Data checklist belum valid untuk disimpan.', errors);
    }
}

/**
 * Same local "only one unsynced operation of this kind per PIC at a time"
 * sanity guard as PM_START (Task 4 section 15) / PM_SAVE (Task 5) — not a
 * re-implementation of any server business rule, just a device-side
 * safeguard against silently piling up unrelated offline transactions.
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
 * Queues a PM_CHECKLIST_SAVE operation entirely locally — NO network
 * request is made (Task 6 section 8). Throws PmChecklistValidationError
 * if the checklist rows fail the local sanity check, or
 * PmChecklistBlockedError if this PIC already has a different
 * PM_CHECKLIST_SAVE pending/syncing locally. Returns the queue record.
 *
 * @param {{ pmScheduleId: number, checklists: object[], expectedStatus: string }} params
 */
export async function saveOffline({ pmScheduleId, checklists, expectedStatus }) {
    validateChecklistsLocally(checklists);

    const userId = currentUserId();
    const blocking = await findBlockingLocalOperation(userId, pmScheduleId);

    if (blocking) {
        throw new PmChecklistBlockedError(
            `PM Schedule #${blocking.payload?.pm_schedule_id} masih menunggu sinkronisasi checklist. Selesaikan/sinkronkan itu dahulu sebelum menyimpan checklist PM lain secara offline.`
        );
    }

    const operationUuid = generateUuid();

    const record = await SyncQueue.enqueue({
        operationUuid,
        transactionType: TRANSACTION_TYPE,
        payload: { pm_schedule_id: pmScheduleId, checklists },
        // Minimal expected_state (Task 6 section 12) — mirrors exactly what
        // SyncOperationController::processPmChecklistSave() actually
        // compares (the PM's current status), never just updated_at.
        expectedState: { status: expectedStatus },
        userId,
    });

    await setLocalPmChecklistOverlay(pmScheduleId, {
        local_checklist_status: 'saved_offline',
        pending_checklist_operation_uuid: operationUuid,
        local_checklist_updated_at: new Date().toISOString(),
    });

    return record;
}

export { getLocalPmChecklistOverlay };

/**
 * Sends one already-queued PM_CHECKLIST_SAVE operation to /api/sync via
 * the shared sync.js plumbing, then — only on processed/already_processed
 * — clears the local "saved offline" overlay AND the matching draft
 * (Task 6 section 16: a draft is only safe to discard once the operation
 * it became has actually been confirmed by the server).
 *
 * Deliberately NOT wired up to anything automatic (no setInterval, no
 * "online" listener) — same as Task 4/5.
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

        await clearLocalPmChecklistOverlay(pmScheduleId);

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
