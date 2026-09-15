/**
 * FreeDOMS offline-first — generic queued-operation sender (Phase 1, Task
 * 5 extraction from Task 4's pmStart.js, so PM_SAVE can reuse the exact
 * same /api/sync plumbing instead of a second copy of it).
 *
 * Knows NOTHING about what a PM_START or PM_SAVE (or any other
 * transaction_type) actually means — it only sends one already-queued
 * operation's envelope to /api/sync (see Task 2 for the request/response
 * contract) and updates its queue status accordingly. Any
 * transaction-type-specific follow-up (clearing a local PM overlay,
 * deleting a draft, ...) is the caller's job — see pmStart.js and
 * pmSave.js, which each wrap this with their own post-success behavior.
 *
 * Deliberately NOT wired up to anything automatic — no setInterval, no
 * "online" event listener calls this (Task 4 section 17 / Task 5 section
 * unchanged). It exists so a later task's actual sync engine has one
 * shared thing to call for every transaction type.
 */

import { csrfToken } from './csrf.js';
import * as SyncQueue from './queue.js';

/**
 * @param {string} operationUuid
 * @returns {Promise<{entry: object, body: object}>} the queue entry (as it
 *   was before this call) and the /api/sync response body.
 */
export async function sendQueuedOperation(operationUuid) {
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
        // must stay retryable, never be dropped.
        await SyncQueue.markFailed(operationUuid, String(error?.message ?? error));
        throw error;
    }

    switch (body.status) {
        case 'processed':
        case 'already_processed':
            await SyncQueue.markSynced(operationUuid, body);
            break;

        // A conflict is never force-resolved here — it is recorded so a
        // later task's UI can show and let the user resolve it.
        case 'conflict':
        case 'payload_mismatch':
            await SyncQueue.markConflict(operationUuid, body);
            break;

        default:
            // validation_failed / forbidden / failed / in_progress / any
            // unexpected status — recorded as failed-but-retained, never
            // silently dropped.
            await SyncQueue.markFailed(operationUuid, body.message ?? body.status ?? 'Unknown sync error');
            break;
    }

    return { entry, body };
}
