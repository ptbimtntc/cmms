/**
 * FreeDOMS offline-first — sync queue foundation (Phase 1, Task 3).
 *
 * Local, durable queue of operations that will eventually be POSTed to
 * `/api/sync` (Task 2) — one record per `operation_uuid`. This task only
 * builds the queue itself: nothing in this file sends a network request,
 * and nothing calls it automatically yet (see Task 3 section 19 — no
 * setInterval, no background sync, no "on online" auto-retry). A later
 * task drains this queue.
 *
 * Backed by IndexedDB via db.js (STORES.SYNC_QUEUE) — never JS memory or
 * a `window` variable — so a queued operation survives page reload, tab
 * close, and browser/device restart (Task 3 section 11).
 */

import { OfflineStorage, OfflineStorageError, STORES } from './db.js';
import { generateUuid } from './uuid.js';

export const QueueStatus = Object.freeze({
    PENDING: 'pending',
    SYNCING: 'syncing',
    SYNCED: 'synced',
    FAILED: 'failed',
    CONFLICT: 'conflict',
});

/**
 * Adds a new operation to the queue, OR returns the existing queued
 * operation unchanged if `operationUuid` is already present — enqueue()
 * is itself idempotent, so a caller that (for any reason) calls it twice
 * for the same logical operation can never produce two queue entries
 * (Task 3 section 13). This does NOT create a new operation_uuid on
 * retry — the caller decides the uuid up front and keeps reusing it
 * (section 10); pass one in, or omit it to have one generated here for a
 * brand new operation.
 *
 * @param {{
 *   operationUuid?: string,
 *   transactionType: string,
 *   payload: object,
 *   expectedState?: object,
 *   userId: string|null,
 * }} operation
 * @returns {Promise<object>} the stored queue record (existing or new).
 */
export async function enqueue({ operationUuid, transactionType, payload, expectedState = {}, userId = null }) {
    if (!transactionType) {
        throw new OfflineStorageError('enqueue() requires a transactionType.');
    }

    const uuid = operationUuid || generateUuid();
    const now = new Date().toISOString();

    const record = {
        operation_uuid: uuid,
        transaction_type: transactionType,
        payload,
        expected_state: expectedState,
        status: QueueStatus.PENDING,
        attempt_count: 0,
        created_at: now,
        updated_at: now,
        last_attempt_at: null,
        last_error: null,
        user_id: userId,
    };

    // One transaction: try to `add()` (fails on a duplicate key instead of
    // silently overwriting, unlike put()); on a duplicate, read back and
    // return the ALREADY queued record instead of erroring out — calling
    // enqueue() twice for the same operation_uuid is expected to be a
    // harmless no-op, not a crash (section 13).
    return OfflineStorage.transaction(STORES.SYNC_QUEUE, 'readwrite', (tx) => {
        const store = tx.objectStore(STORES.SYNC_QUEUE);
        const addRequest = store.add(record);

        addRequest.onerror = (event) => {
            if (addRequest.error?.name === 'ConstraintError') {
                // Expected path for a duplicate operation_uuid.
                // preventDefault() stops this request's error from
                // aborting the transaction; stopPropagation() is ALSO
                // required, or the (unaborted) error event still bubbles
                // up to tx.onerror and rejects the outer promise anyway —
                // both are needed to truly swallow it and fall through to
                // re-reading the existing record below.
                event.preventDefault();
                event.stopPropagation();
            }
        };
    }).then(async () => {
        const existing = await OfflineStorage.get(STORES.SYNC_QUEUE, uuid);

        return existing ?? record;
    });
}

export function getByUuid(operationUuid) {
    return OfflineStorage.get(STORES.SYNC_QUEUE, operationUuid);
}

export function listByStatus(status) {
    return OfflineStorage.getAll(STORES.SYNC_QUEUE, { indexName: 'by_status', query: status });
}

/**
 * Queries the `by_user` index for a real userId, but falls back to
 * scanning + filtering in JS when userId is null/undefined — an
 * IndexedDB index query for a null key throws `DataError` (null/undefined
 * are not valid IndexedDB key values), which would otherwise make this
 * throw for the legitimate "no authenticated user known" case instead of
 * simply returning that scope's operations.
 */
export function listByUser(userId) {
    if (userId === null || userId === undefined) {
        return listAll().then((all) => all.filter((operation) => operation.user_id === userId));
    }

    return OfflineStorage.getAll(STORES.SYNC_QUEUE, { indexName: 'by_user', query: userId });
}

export function listAll() {
    return OfflineStorage.getAll(STORES.SYNC_QUEUE);
}

async function patch(operationUuid, changes) {
    const existing = await OfflineStorage.get(STORES.SYNC_QUEUE, operationUuid);

    if (!existing) {
        throw new OfflineStorageError(`No queued operation with operation_uuid ${operationUuid}.`);
    }

    const updated = { ...existing, ...changes, updated_at: new Date().toISOString() };

    await OfflineStorage.put(STORES.SYNC_QUEUE, updated);

    return updated;
}

/**
 * Marks an operation as currently being sent. Bumps attempt_count and
 * last_attempt_at — the record is NEVER deleted here (section 12: an
 * entry must not disappear before the server has actually acknowledged
 * it).
 */
export async function markSyncing(operationUuid) {
    const existing = await OfflineStorage.get(STORES.SYNC_QUEUE, operationUuid);
    const attemptCount = (existing?.attempt_count ?? 0) + 1;

    return patch(operationUuid, {
        status: QueueStatus.SYNCING,
        attempt_count: attemptCount,
        last_attempt_at: new Date().toISOString(),
    });
}

/**
 * Marks an operation as successfully processed (the /api/sync response
 * was `processed` or `already_processed` — Task 2). Removing the entry
 * once synced is left to the caller (a later task's queue-draining logic)
 * — this module only records the outcome.
 */
export function markSynced(operationUuid, result = {}) {
    return patch(operationUuid, { status: QueueStatus.SYNCED, last_error: null, result });
}

export function markFailed(operationUuid, errorMessage) {
    return patch(operationUuid, { status: QueueStatus.FAILED, last_error: String(errorMessage ?? 'Unknown error') });
}

export function markConflict(operationUuid, details = {}) {
    return patch(operationUuid, { status: QueueStatus.CONFLICT, last_error: null, result: details });
}

/**
 * Removes a queue entry. Intentionally NOT called anywhere in Task 3 —
 * kept as an explicit, separate action for a later task's post-ack
 * cleanup, so nothing here can accidentally drop an operation that hasn't
 * been confirmed by the server yet.
 */
export function remove(operationUuid) {
    return OfflineStorage.delete(STORES.SYNC_QUEUE, operationUuid);
}
