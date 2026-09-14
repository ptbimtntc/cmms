/**
 * FreeDOMS offline-first — PM Start offline (Phase 1, Task 4).
 *
 * Reuses the exact same server-side business rule as the online endpoint
 * (PMStartService, via /api/sync's PM_START handler — see Task 2) by
 * sending the SAME payload shape the online form already posts
 * (pm_schedule_id, started_at, confirm_end_start). Nothing here
 * re-implements PM Start's business logic; this module only decides
 * WHERE the operation goes (straight to the server, or into the local
 * queue) and keeps a small local view of "this PM was started offline"
 * until that operation is actually synced.
 */

import { generateUuid } from './uuid.js';
import { currentUserId } from './scope.js';
import { csrfToken } from './csrf.js';
import * as SyncQueue from './queue.js';
import { clearLocalPmOverlay, getLocalPmOverlay, setLocalPmOverlay } from './masterData.js';

export const TRANSACTION_TYPE = 'PM_START';

export class PmStartBlockedError extends Error {
    constructor(message) {
        super(message);
        this.name = 'PmStartBlockedError';
    }
}

/**
 * navigator.onLine is only ever a network HINT, never proof the server is
 * reachable (Task 4 section 6 / Task 3 section 18) — a device can be on a
 * Wi-Fi network with no real internet, or the server itself can be down.
 * This probes Laravel's own built-in, unauthenticated, side-effect-free
 * health route (`health: '/up'` in bootstrap/app.php) with a short
 * timeout so a genuinely offline device doesn't hang the UI waiting for
 * one.
 *
 * @param {number} timeoutMs
 * @returns {Promise<boolean>}
 */
export async function probeServerReachable(timeoutMs = 3000) {
    if (typeof navigator !== 'undefined' && navigator.onLine === false) {
        // The browser itself is confident there is no network — skip the
        // round-trip entirely.
        return false;
    }

    if (typeof fetch === 'undefined') {
        return false;
    }

    try {
        const response = await fetch('/up', {
            method: 'GET',
            cache: 'no-store',
            signal: typeof AbortSignal !== 'undefined' && AbortSignal.timeout
                ? AbortSignal.timeout(timeoutMs)
                : undefined,
        });

        return response.ok;
    } catch {
        // Any thrown error here (network failure, DNS failure, timeout) —
        // NOT an HTTP error status, fetch() only throws for those — means
        // the server could not actually be reached.
        return false;
    }
}

/**
 * Minimal local guard against the most obvious case of Task 4 section 15
 * ("PM A -> Start offline, PM B -> Start offline, PM C -> Start
 * offline"): a PIC who already has a PM_START still sitting in the queue
 * (not yet synced) is blocked from queuing a SECOND one for a different
 * PM Schedule. This is deliberately NOT a re-implementation of the
 * server's full one-active-activity-per-PIC rule (that stays entirely in
 * PMStartService/ActiveActivityResolver, which is authoritative and is
 * re-checked at sync time regardless — see section 14/20); it only
 * prevents the obviously-wrong local case a device can detect on its own
 * without knowing about Greasing/Oil Audit/etc. state.
 */
async function findBlockingLocalPmStart(userId, pmScheduleId) {
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
 * Queues a PM_START operation entirely locally — NO network request is
 * made (Task 4 section 9). Returns the queue record. Throws
 * PmStartBlockedError (without touching the queue) if this PIC already
 * has a different PM_START still pending/syncing locally.
 *
 * @param {{
 *   pmScheduleId: number,
 *   startedAtLocal: string,
 *   expectedStatus: string,
 * }} params `startedAtLocal` is the raw `datetime-local` input value
 *   (e.g. "2026-09-14T08:30") — the SAME string shape and semantics the
 *   online form has always posted as `started_at`, deliberately not
 *   reformatted or timezone-converted here (section 5): the server
 *   already parses that string against the application's own
 *   Asia/Jakarta timezone, exactly as it does for the online request.
 */
export async function startOffline({ pmScheduleId, startedAtLocal, expectedStatus }) {
    const userId = currentUserId();

    const blocking = await findBlockingLocalPmStart(userId, pmScheduleId);

    if (blocking) {
        throw new PmStartBlockedError(
            `PM Schedule #${blocking.payload?.pm_schedule_id} masih menunggu sinkronisasi. Selesaikan/sinkronkan itu dahulu sebelum memulai PM lain secara offline.`
        );
    }

    const operationUuid = generateUuid();

    const record = await SyncQueue.enqueue({
        operationUuid,
        transactionType: TRANSACTION_TYPE,
        payload: {
            pm_schedule_id: pmScheduleId,
            started_at: startedAtLocal,
            confirm_end_start: false,
        },
        // Minimal expected_state (Task 4 section 11): what this device
        // believes the PM's state is right now, so the server can detect
        // if it changed since (see SyncOperationController::processPmStart).
        expectedState: {
            status: expectedStatus,
            is_started: false,
        },
        userId,
    });

    await setLocalPmOverlay(pmScheduleId, {
        local_status: 'started_offline',
        local_actual_date: startedAtLocal.slice(0, 10),
        local_start_time: startedAtLocal.slice(11, 16),
        pending_operation_uuid: operationUuid,
        local_updated_at: new Date().toISOString(),
    });

    return record;
}

export { getLocalPmOverlay };

/**
 * Sends ONE already-queued operation to /api/sync and updates its queue
 * status (and, for a successful PM_START, clears the local overlay) based
 * on the response — see Task 2 for the response contract
 * (processed/already_processed/conflict/payload_mismatch/
 * validation_failed/forbidden/failed/in_progress).
 *
 * Deliberately generic (works for any transaction_type, not just
 * PM_START) and NOT wired up to anything automatic — no setInterval, no
 * "online" event listener calls this. It exists so a later task's actual
 * sync engine has something ready to call; Task 4 only proves it works
 * (see the test suite) and leaves invoking it to that later task (section
 * 17).
 *
 * @param {string} operationUuid
 */
export async function processQueuedOperation(operationUuid) {
    const entry = await SyncQueue.getByUuid(operationUuid);

    if (!entry) {
        throw new Error(`No queued operation with operation_uuid ${operationUuid}.`);
    }

    await SyncQueue.markSyncing(operationUuid);

    let body;

    try {
        const response = await fetch('/api/sync', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken() ?? '',
            },
            body: JSON.stringify({
                operation_uuid: entry.operation_uuid,
                transaction_type: entry.transaction_type,
                payload: entry.payload,
                expected_state: entry.expected_state,
            }),
        });

        body = await response.json();
    } catch (error) {
        // Network failure DURING the sync attempt itself — the operation
        // must stay retryable, never be dropped (section 9/16).
        await SyncQueue.markFailed(operationUuid, String(error?.message ?? error));
        throw error;
    }

    switch (body.status) {
        case 'processed':
        case 'already_processed':
            await SyncQueue.markSynced(operationUuid, body);

            if (entry.transaction_type === TRANSACTION_TYPE && entry.payload?.pm_schedule_id !== undefined) {
                await clearLocalPmOverlay(entry.payload.pm_schedule_id);
            }

            return body;

        // A conflict is never force-resolved here (section 19) — it is
        // recorded so a later task's UI can show and let the user resolve
        // it.
        case 'conflict':
        case 'payload_mismatch':
            await SyncQueue.markConflict(operationUuid, body);

            return body;

        default:
            // validation_failed / forbidden / failed / in_progress / any
            // unexpected status — recorded as failed-but-retained, never
            // silently dropped.
            await SyncQueue.markFailed(operationUuid, body.message ?? body.status ?? 'Unknown sync error');

            return body;
    }
}
