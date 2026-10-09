/**
 * FreeDOMS offline-first — Oil Audit Create offline (Phase 1, Task 7).
 *
 * Reuses the exact same server-side business rule as the online endpoint
 * (OilAuditCreateService, via /api/sync's OIL_AUDIT_CREATE handler — see
 * Task 2) by sending the same minimal payload the online entry form
 * already posts (machine_id, condition). Nothing here re-implements
 * OilAuditCreateService's logic, and — critically (Task 7 section 20) —
 * nothing here builds the machine_number/machine_type/area SNAPSHOT that
 * ends up on the OilAudit row: that snapshot is taken from the server's
 * OWN Machine record inside OilAuditCreateService::create(), exactly like
 * online. This module's cached machine data is used ONLY for offline
 * UX (showing the machine, letting the device build a payload) — never
 * sent as the snapshot itself.
 *
 * OIL_AUDIT_CREATE is insert-only (a fresh audit record every time), so —
 * deliberately, per Task 7 section 12 — there is no expected_state/
 * conflict mechanism here to force: the only thing that must never
 * duplicate an operation is operation_uuid, already guaranteed by
 * sync_operations (Task 2).
 */

import { generateUuid } from './uuid.js';
import { currentUserId } from './scope.js';
import * as SyncQueue from './queue.js';
import * as Drafts from './drafts.js';
import { clearMasterDataCategory, getMasterData, getMasterDataByCategory, putMasterData } from './masterData.js';
import { sendQueuedOperation } from './sync.js';

export const TRANSACTION_TYPE = 'OIL_AUDIT_CREATE';
export const DRAFT_TYPE = 'OIL_AUDIT';
export const MACHINE_CATEGORY = 'machines';
export const AUDITED_CATEGORY = 'oil_audited_dates';
export const BUSINESS_TIMEZONE = 'Asia/Jakarta';

export class OilAuditValidationError extends Error {
    constructor(message, errors = {}) {
        super(message);
        this.name = 'OilAuditValidationError';
        this.errors = errors;
    }
}

export class OilAuditBlockedError extends Error {
    constructor(message) {
        super(message);
        this.name = 'OilAuditBlockedError';
    }
}

export class OilAuditDuplicateError extends Error {
    constructor(message) {
        super(message);
        this.name = 'OilAuditDuplicateError';
    }
}

/**
 * Business calendar date (YYYY-MM-DD) in Asia/Jakarta — the same day the
 * server's audit_date uses. Only formats; never shifts the instant.
 *
 * @param {Date|string|number} [instant]
 */
export function businessDate(instant = new Date()) {
    return new Intl.DateTimeFormat('en-CA', {
        timeZone: BUSINESS_TIMEZONE,
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    }).format(new Date(instant));
}

/**
 * Seeds the "already audited today" cache from the scan page's embedded
 * server data ({date, machine_ids}); replaces the previous snapshot. Best
 * effort only — the server stays the authority at sync time.
 */
export async function seedAuditedToday(data) {
    await clearMasterDataCategory(AUDITED_CATEGORY);

    if (!data?.date || !Array.isArray(data.machine_ids)) {
        return;
    }

    await Promise.all(data.machine_ids.map((machineId) => putMasterData(
        AUDITED_CATEGORY,
        `${machineId}:${data.date}`,
        { machine_id: machineId, date: data.date }
    )));
}

/**
 * Whether this machine is already known to have an audit for today's
 * business date: from the server snapshot cache, or from a local
 * OIL_AUDIT_CREATE queue entry (pending/syncing/failed/conflict/synced)
 * created today. A conflict that is a server duplicate rejection does NOT
 * count — it represents no audit. Returns a description of where it was
 * found, or null.
 */
export async function findExistingAudit(machineId, now = new Date()) {
    const today = businessDate(now);

    if (await getMasterData(AUDITED_CATEGORY, `${machineId}:${today}`)) {
        return { source: 'cache' };
    }

    const queued = (await SyncQueue.listAll()).find((operation) => (
        operation.transaction_type === TRANSACTION_TYPE
        && String(operation.payload?.machine_id) === String(machineId)
        && businessDate(operation.created_at) === today
        && operation.result?.status !== 'duplicate_audit'
    ));

    return queued ? { source: 'queue', operation: queued } : null;
}

function duplicateMessage(machineNumber) {
    return `Mesin ${machineNumber} sudah memiliki audit oli untuk hari ini. Satu mesin hanya dapat diaudit satu kali per hari.`;
}

/**
 * Throws OilAuditDuplicateError if the machine already has an audit today.
 * Never touches existing queue entries.
 */
export async function assertNotAlreadyAudited(machine, now = new Date()) {
    if (await findExistingAudit(machine.id, now)) {
        throw new OilAuditDuplicateError(duplicateMessage(machine.machine_number ?? machine.id));
    }
}

/**
 * A PIC has at most one unfinished machine-selection draft: choosing a
 * machine replaces any earlier one (this user's only).
 */
export async function selectMachineDraft(machine, userId = currentUserId()) {
    await Drafts.deleteDraftsByType(userId, DRAFT_TYPE);

    return Drafts.saveDraft({ draftType: DRAFT_TYPE, referenceId: machine.id, payload: machine, userId });
}

/**
 * Drops this user's unfinished machine-selection draft(s) (Scan mesin
 * lain / audit queued). Never throws and never touches the sync queue — a
 * failure here must not affect an audit that is already queued.
 */
export async function discardMachineDraft(userId = currentUserId()) {
    try {
        await Drafts.deleteDraftsByType(userId, DRAFT_TYPE);
    } catch {
        // Best effort by design.
    }
}

/**
 * The machine to restore after a reload, or null. A draft whose machine
 * has meanwhile been audited today is stale: it is deleted, not restored.
 */
export async function restoreMachineDraft(userId = currentUserId(), now = new Date()) {
    const drafts = await Drafts.listDraftsByType(userId, DRAFT_TYPE).catch(() => []);
    const latest = [...drafts].sort((a, b) => (a.updated_at < b.updated_at ? 1 : -1))[0];

    if (!latest?.payload) {
        return null;
    }

    if (await findExistingAudit(latest.payload.id, now)) {
        await discardMachineDraft(userId);

        return null;
    }

    return latest;
}

/**
 * Looks up a machine by its (trimmed) machine_number in the local cache
 * populated from the scan page's embedded machine list (Task 7 section
 * 5/6) — no network request, works fully offline. Returns undefined if
 * the machine isn't cached (Task 7 section 6: "Machine data unavailable
 * offline" — never invented, never assumed valid just because the
 * scanned string LOOKS like a machine number).
 *
 * @param {string} machineNumber
 */
export async function findCachedMachineByNumber(machineNumber) {
    const needle = String(machineNumber ?? '').trim();

    if (!needle) {
        return undefined;
    }

    const machines = await getMasterDataByCategory(MACHINE_CATEGORY);

    return machines.find((machine) => String(machine.machine_number).trim() === needle);
}

/**
 * Minimal, honest local sanity check — NOT a mirror of
 * OilAuditCreateService::rules()'s full validation (which includes
 * `exists:machines,id` and scope checks only the server can truly
 * authoritatively answer — Task 7 section 10: "if local validation
 * cannot be sure of something, don't guess, let the server be the
 * authority"). This only catches the obviously-wrong local cases: no
 * machine chosen, or an empty condition.
 */
export function validatePayloadLocally({ machineId, condition }) {
    const errors = {};

    if (machineId === undefined || machineId === null || machineId === '') {
        errors.machine_id = 'Mesin belum dipilih.';
    }

    if (!condition) {
        errors.condition = 'Kondisi oli belum dipilih.';
    }

    if (Object.keys(errors).length > 0) {
        throw new OilAuditValidationError('Data audit oli belum lengkap.', errors);
    }
}

/**
 * Same local "only one unsynced operation of this kind per PIC at a time"
 * sanity guard as PM_START/PM_SAVE/PM_CHECKLIST_SAVE (Task 4/5/6) — a
 * device-side safeguard only, never a re-implementation of any server
 * rule (Oil Audit Create has no server-side "one active" business rule to
 * mirror in the first place; this purely prevents a device from silently
 * piling up several unsynced audits for different machines before any of
 * them has even reached the server).
 */
async function findBlockingLocalOperation(userId, machineId) {
    const [pending, syncing] = await Promise.all([
        SyncQueue.listByStatus(SyncQueue.QueueStatus.PENDING),
        SyncQueue.listByStatus(SyncQueue.QueueStatus.SYNCING),
    ]);

    return [...pending, ...syncing].find((operation) => (
        operation.transaction_type === TRANSACTION_TYPE
        && operation.user_id === userId
        && operation.payload?.machine_id !== machineId
    ));
}

/**
 * Queues an OIL_AUDIT_CREATE operation entirely locally — NO network
 * request is made (Task 7 section 7). Throws OilAuditValidationError if
 * the payload fails the local sanity check, or OilAuditBlockedError if
 * this PIC already has a different OIL_AUDIT_CREATE still pending/syncing
 * locally. Returns the queue record.
 *
 * @param {{ machineId: number, condition: string }} params
 */
export async function saveOffline({ machineId, condition }) {
    validatePayloadLocally({ machineId, condition });

    const cachedMachine = (await getMasterData(MACHINE_CATEGORY, machineId)) ?? { id: machineId };

    await assertNotAlreadyAudited(cachedMachine);

    const userId = currentUserId();
    const blocking = await findBlockingLocalOperation(userId, machineId);

    if (blocking) {
        throw new OilAuditBlockedError(
            `Audit oli untuk mesin lain (ID ${blocking.payload?.machine_id}) masih menunggu sinkronisasi. Selesaikan/sinkronkan itu dahulu sebelum membuat audit baru secara offline.`
        );
    }

    const operationUuid = generateUuid();

    return SyncQueue.enqueue({
        operationUuid,
        transactionType: TRANSACTION_TYPE,
        payload: { machine_id: machineId, condition },
        // No expected_state (section 12): OIL_AUDIT_CREATE always inserts
        // a brand new row — there is no meaningful prior server state to
        // compare against, so none is fabricated here.
        expectedState: {},
        userId,
    });
}

/**
 * Sends one already-queued OIL_AUDIT_CREATE operation to /api/sync via
 * the shared sync.js plumbing, then — only on processed/already_processed
 * — deletes the matching machine-selection draft, if any (Task 7 section
 * 8/9: a draft is only safe to discard once the operation it became has
 * actually been confirmed by the server).
 *
 * Deliberately NOT wired up to anything automatic — same as Task 4/5/6.
 *
 * @param {string} operationUuid
 */
export async function processQueuedOperation(operationUuid) {
    const { entry, body } = await sendQueuedOperation(operationUuid);

    if (
        (body.status === 'processed' || body.status === 'already_processed')
        && entry.transaction_type === TRANSACTION_TYPE
        && entry.payload?.machine_id !== undefined
    ) {
        const draft = await Drafts.getDraftByReference({
            userId: entry.user_id,
            draftType: DRAFT_TYPE,
            referenceId: entry.payload.machine_id,
        });

        if (draft) {
            await Drafts.deleteDraft(draft.draft_id);
        }
    }

    return body;
}
