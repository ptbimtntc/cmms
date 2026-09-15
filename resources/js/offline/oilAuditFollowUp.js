/**
 * FreeDOMS offline-first — Oil Audit Follow Up Save offline (Phase 1,
 * Task 8).
 *
 * Reuses the exact same server-side business rule as the online endpoint
 * (OilAuditFollowUpService, via /api/sync's OIL_AUDIT_FOLLOW_UP_SAVE
 * handler — see Task 2) by sending the same aggregate shape
 * OilAuditController::storeFollowUp()/updateFollowUp() already post
 * (problems[] with nested findings[], action_taken). Nothing here
 * re-implements the business logic — including deciding CREATE vs UPDATE:
 * that decision is made entirely server-side, from the audit's ACTUAL
 * current follow_up state at sync time (SyncOperationController::
 * processOilAuditFollowUpSave()), never from anything this module sends.
 * This module only tells the server what the DEVICE believed that state
 * was via expected_state.follow_up_exists, so a genuine conflict (the
 * audit already got a follow-up from another device/user, or an edited
 * one was deleted) is detected rather than silently overwritten.
 *
 * The whole follow-up — header/action + every problem + every finding —
 * is always ONE aggregate operation/queue entry, exactly like the online
 * request (Task 8 section 2): there is no PROBLEM_CREATE/FINDING_CREATE.
 */

import { generateUuid } from './uuid.js';
import { currentUserId } from './scope.js';
import * as SyncQueue from './queue.js';
import * as Drafts from './drafts.js';
import {
    clearLocalOilAuditFollowUpOverlay,
    getLocalOilAuditFollowUpOverlay,
    setLocalOilAuditFollowUpOverlay,
} from './masterData.js';
import { sendQueuedOperation } from './sync.js';
import { denseObjectToArray, parseFormToNestedObject } from './formParser.js';

export const TRANSACTION_TYPE = 'OIL_AUDIT_FOLLOW_UP_SAVE';
export const DRAFT_TYPE = 'OIL_AUDIT_FOLLOW_UP';

export class OilAuditFollowUpValidationError extends Error {
    constructor(message, errors = {}) {
        super(message);
        this.name = 'OilAuditFollowUpValidationError';
        this.errors = errors;
    }
}

export class OilAuditFollowUpBlockedError extends Error {
    constructor(message) {
        super(message);
        this.name = 'OilAuditFollowUpBlockedError';
    }
}

/**
 * Builds { problems, actionTaken } from the follow-up <form> — the same
 * field whitelist OilAuditFollowUpService::rules() reads, never the whole
 * form (the hidden `_followup_audit` marker field, `_token`, `_method`
 * are never part of this). Unlike PM Save's dynamic rows, this form's own
 * JS (oil-audits/follow-up.js, extracted from the pre-existing inline
 * script) always re-indexes problems[]/findings[] densely after every
 * add/remove, so it is always safe to convert straight to real arrays via
 * denseObjectToArray() here.
 */
export function buildFollowUpFromForm(form) {
    const parsed = parseFormToNestedObject(new FormData(form));
    const problemRows = denseObjectToArray(parsed.problems);

    const problems = problemRows.map((row) => ({
        problem: row?.problem ?? '',
        findings: denseObjectToArray(row?.findings).map((f) => ({ finding: f?.finding ?? '' })),
    }));

    return { problems, actionTaken: parsed.action_taken ?? '' };
}

function isBlank(value) {
    return value === undefined || value === null || String(value).trim() === '';
}

/**
 * Minimal, honest local sanity check — mirrors only the structural rules
 * that are safe to check without the server's PROBLEM_OPTIONS/
 * FINDING_OPTIONS enum tables (Task 8 section 12): every problem row has
 * a non-blank problem and at least one non-blank finding, and
 * action_taken is filled. It deliberately does NOT re-validate the
 * problem/finding VALUES against OilAudit::PROBLEM_OPTIONS/
 * FINDING_OPTIONS — the <select> dropdowns already constrain that
 * client-side, and the server unconditionally re-validates it in full via
 * OilAuditFollowUpService::validate() regardless.
 */
export function validatePayloadLocally({ oilAuditId, problems, actionTaken }) {
    const errors = {};

    if (oilAuditId === undefined || oilAuditId === null || oilAuditId === '') {
        errors.oil_audit_id = 'Oil Audit tidak diketahui.';
    }

    if (!Array.isArray(problems) || problems.length === 0) {
        errors.problems = 'Minimal satu problem wajib diisi.';
    } else {
        problems.forEach((problem, index) => {
            if (isBlank(problem?.problem)) {
                errors[`problems.${index}.problem`] = 'Problem wajib dipilih.';
            }

            if (!Array.isArray(problem?.findings) || problem.findings.every((f) => isBlank(f?.finding))) {
                errors[`problems.${index}.findings`] = 'Minimal satu finding wajib dipilih untuk problem ini.';
            }
        });
    }

    if (isBlank(actionTaken)) {
        errors.action_taken = 'Tindakan yang dilakukan wajib diisi.';
    }

    if (Object.keys(errors).length > 0) {
        throw new OilAuditFollowUpValidationError('Data tindak lanjut belum lengkap/valid.', errors);
    }
}

/**
 * Same local "only one unsynced operation of this kind per PIC at a time"
 * sanity guard as every other offline feature (Task 4-7) — a device-side
 * safeguard only, never a re-implementation of any server rule.
 */
async function findBlockingLocalOperation(userId, oilAuditId) {
    const [pending, syncing] = await Promise.all([
        SyncQueue.listByStatus(SyncQueue.QueueStatus.PENDING),
        SyncQueue.listByStatus(SyncQueue.QueueStatus.SYNCING),
    ]);

    return [...pending, ...syncing].find((operation) => (
        operation.transaction_type === TRANSACTION_TYPE
        && operation.user_id === userId
        && operation.payload?.oil_audit_id !== oilAuditId
    ));
}

/**
 * Queues an OIL_AUDIT_FOLLOW_UP_SAVE operation entirely locally — NO
 * network request is made. Throws OilAuditFollowUpValidationError if the
 * payload fails the local sanity check, or OilAuditFollowUpBlockedError
 * if this PIC already has a different follow-up save still
 * pending/syncing locally. Returns the queue record.
 *
 * @param {{
 *   oilAuditId: number,
 *   problems: Array<{problem: string, findings: Array<{finding: string}>}>,
 *   actionTaken: string,
 *   followUpExists: boolean,
 * }} params `followUpExists` is what the DEVICE believes right now (e.g.
 *   the create form was shown because no follow-up existed when this
 *   page loaded, or the edit form because one did) — sent as
 *   expected_state so the server can detect if that has changed since.
 */
export async function saveOffline({ oilAuditId, problems, actionTaken, followUpExists }) {
    validatePayloadLocally({ oilAuditId, problems, actionTaken });

    const userId = currentUserId();
    const blocking = await findBlockingLocalOperation(userId, oilAuditId);

    if (blocking) {
        throw new OilAuditFollowUpBlockedError(
            `Tindak lanjut untuk audit lain (ID ${blocking.payload?.oil_audit_id}) masih menunggu sinkronisasi. Selesaikan/sinkronkan itu dahulu sebelum menyimpan tindak lanjut lain secara offline.`
        );
    }

    const operationUuid = generateUuid();

    const record = await SyncQueue.enqueue({
        operationUuid,
        transactionType: TRANSACTION_TYPE,
        payload: { oil_audit_id: oilAuditId, problems, action_taken: actionTaken },
        expectedState: { follow_up_exists: Boolean(followUpExists) },
        userId,
    });

    await setLocalOilAuditFollowUpOverlay(oilAuditId, {
        local_status: 'saved_offline',
        pending_operation_uuid: operationUuid,
        local_updated_at: new Date().toISOString(),
    });

    return record;
}

export { getLocalOilAuditFollowUpOverlay };

/**
 * Sends one already-queued OIL_AUDIT_FOLLOW_UP_SAVE operation to
 * /api/sync via the shared sync.js plumbing, then — only on processed/
 * already_processed — deletes the matching draft, if any (Task 8 section
 * 25: a draft is only safe to discard once the operation it became has
 * actually been confirmed by the server).
 *
 * Deliberately NOT wired up to anything automatic — same as Task 4-7.
 *
 * @param {string} operationUuid
 */
export async function processQueuedOperation(operationUuid) {
    const { entry, body } = await sendQueuedOperation(operationUuid);

    if (
        (body.status === 'processed' || body.status === 'already_processed')
        && entry.transaction_type === TRANSACTION_TYPE
        && entry.payload?.oil_audit_id !== undefined
    ) {
        const oilAuditId = entry.payload.oil_audit_id;

        await clearLocalOilAuditFollowUpOverlay(oilAuditId);

        const draft = await Drafts.getDraftByReference({
            userId: entry.user_id,
            draftType: DRAFT_TYPE,
            referenceId: oilAuditId,
        });

        if (draft) {
            await Drafts.deleteDraft(draft.draft_id);
        }
    }

    return body;
}
