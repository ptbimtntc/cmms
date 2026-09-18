/**
 * FreeDOMS offline-first — sync engine / queue drainer (Phase 1, Task 9A).
 *
 * Orchestrates WHEN and IN WHAT ORDER the queue built by queue.js gets sent,
 * by calling each transaction module's own `processQueuedOperation()`
 * (pmStart.js / pmSave.js / pmChecklist.js / oilAuditCreate.js /
 * oilAuditFollowUp.js) — which each wrap the shared sync.js#
 * sendQueuedOperation() with their own post-success overlay/draft cleanup
 * (Task 4-8). This module knows NOTHING about PM/Oil Audit business rules
 * or what a successful sync should clean up locally — it only decides
 * which already-queued operation to send next, and never talks to
 * /api/sync directly.
 *
 * Single entry point for both automatic and manual sync — see
 * syncPendingOperations() — so "auto sync on network return" and the
 * "Sync Now" button can never run two processors at once (section 5/16).
 */

import * as SyncQueue from './queue.js';
import { isOnline, onNetworkChange } from './network.js';
import { getSyncMetadata, setSyncMetadata } from './masterData.js';
import * as PmStart from './pmStart.js';
import * as PmSave from './pmSave.js';
import * as PmChecklist from './pmChecklist.js';
import * as OilAuditCreate from './oilAuditCreate.js';
import * as OilAuditFollowUp from './oilAuditFollowUp.js';

/**
 * transaction_type -> that module's own processQueuedOperation(), which
 * already does markSyncing -> POST /api/sync -> markSynced/Failed/Conflict
 * (via sync.js) THEN the module's own post-success overlay/draft cleanup
 * (section 30/36 — never reimplemented here).
 */
const PROCESSORS = {
    [PmStart.TRANSACTION_TYPE]: PmStart.processQueuedOperation,
    [PmSave.TRANSACTION_TYPE]: PmSave.processQueuedOperation,
    [PmChecklist.TRANSACTION_TYPE]: PmChecklist.processQueuedOperation,
    [OilAuditCreate.TRANSACTION_TYPE]: OilAuditCreate.processQueuedOperation,
    [OilAuditFollowUp.TRANSACTION_TYPE]: OilAuditFollowUp.processQueuedOperation,
};

export const TRANSACTION_LABELS = {
    [PmStart.TRANSACTION_TYPE]: 'PM Start',
    [PmSave.TRANSACTION_TYPE]: 'PM Save',
    [PmChecklist.TRANSACTION_TYPE]: 'PM Checklist',
    [OilAuditCreate.TRANSACTION_TYPE]: 'Oil Audit',
    [OilAuditFollowUp.TRANSACTION_TYPE]: 'Oil Audit Follow Up',
};

/**
 * A "failed" queue entry is only picked up by the AUTOMATIC drain when its
 * last_error_status (set by sync.js/queue.js, Task 9A) is one of these —
 * i.e. it looks transient (network hiccup, server 500, or the server telling
 * us its own concurrent request was still mid-flight). validation_failed /
 * forbidden / any unrecognised status are left alone by auto-sync (section
 * 4/24) — a human needs to look at those; see isManuallyRetryable() for the
 * (more permissive) rule the "Retry" button uses instead.
 */
const AUTO_RETRYABLE_FAILURE_CODES = new Set(['network_error', 'failed', 'in_progress']);

/**
 * PM_START -> PM_SAVE -> PM_CHECKLIST_SAVE all share pm_schedule_id, so a
 * child is only safe to send once no unresolved (pending/syncing/failed/
 * conflict) operation of its parent type exists for the SAME schedule AND
 * SAME user (section 7/8). OIL_AUDIT_FOLLOW_UP_SAVE is deliberately left
 * out here — its payload has no field that safely correlates it back to a
 * locally-queued OIL_AUDIT_CREATE (the create is insert-only and never
 * learns its server-assigned oil_audit_id until it has already synced, so a
 * device can never build a follow-up payload that actually references an
 * unsynced local create in the first place). Guessing a correlation there
 * would risk blocking an unrelated, independent follow-up — section 9 says
 * not to guess, so plain creation-order (see selectProcessable()) is all
 * that applies to that pair.
 */
const DEPENDENCIES = {
    [PmSave.TRANSACTION_TYPE]: { parentType: PmStart.TRANSACTION_TYPE, key: 'pm_schedule_id' },
    [PmChecklist.TRANSACTION_TYPE]: { parentType: PmSave.TRANSACTION_TYPE, key: 'pm_schedule_id' },
};

const UNRESOLVED_STATUSES = new Set([
    SyncQueue.QueueStatus.PENDING,
    SyncQueue.QueueStatus.SYNCING,
    SyncQueue.QueueStatus.FAILED,
    SyncQueue.QueueStatus.CONFLICT,
]);

function isBlockedByDependency(operation, allOperations) {
    const dependency = DEPENDENCIES[operation.transaction_type];

    if (!dependency) {
        return false;
    }

    const referenceValue = operation.payload?.[dependency.key];

    if (referenceValue === undefined) {
        return false;
    }

    return allOperations.some((candidate) => (
        candidate.operation_uuid !== operation.operation_uuid
        && candidate.transaction_type === dependency.parentType
        && candidate.user_id === operation.user_id
        && candidate.payload?.[dependency.key] === referenceValue
        && UNRESOLVED_STATUSES.has(candidate.status)
    ));
}

/**
 * Simple linear backoff (section 24) — never "retry 100x instantly": the
 * more attempts already made, the longer auto-sync waits before trying that
 * same entry again. A manual "Retry" click (retryOperation()) intentionally
 * ignores this — it is one explicit user action, not a loop.
 */
function backoffElapsedFor(operation) {
    if (!operation.last_attempt_at) {
        return true;
    }

    const attempts = operation.attempt_count || 1;
    const backoffMs = Math.min(attempts * 5000, 60000);

    return Date.now() - new Date(operation.last_attempt_at).getTime() >= backoffMs;
}

function isAutoRetryableFailure(operation) {
    return AUTO_RETRYABLE_FAILURE_CODES.has(operation.last_error_status);
}

/**
 * Any operation a manual "Retry" button may act on — every FAILED entry,
 * regardless of last_error_status. A conflict (including payload_mismatch,
 * which sync.js already records as a conflict) is never retryable here —
 * section 23: no Force Sync, ever.
 */
export function isManuallyRetryable(operation) {
    return operation?.status === SyncQueue.QueueStatus.FAILED;
}

/**
 * Candidates for one automatic/manual drain pass, safely ordered:
 * creation-order (FIFO, section 9) first, PENDING and FAILED-but-safe-to-
 * retry entries only. `syncing` entries are also picked up — see the
 * comment on runDrain() for why that is safe rather than a double-send
 * risk.
 */
function selectProcessable(allOperations) {
    return allOperations
        .filter((operation) => (
            operation.status === SyncQueue.QueueStatus.PENDING
            || operation.status === SyncQueue.QueueStatus.SYNCING
            || (
                operation.status === SyncQueue.QueueStatus.FAILED
                && isAutoRetryableFailure(operation)
                && backoffElapsedFor(operation)
            )
        ))
        .sort((a, b) => (
            new Date(a.created_at) - new Date(b.created_at)
            || (a.sequence ?? 0) - (b.sequence ?? 0)
        ));
}

const listeners = new Set();

function notify() {
    listeners.forEach((listener) => {
        try {
            listener();
        } catch (error) {
            console.error('[offline/syncEngine] listener threw', error);
        }
    });
}

/**
 * Subscribes to "something about sync state may have changed" (queue
 * drained an item, a sync run started/finished, network came back, ...).
 * The callback receives no payload on purpose — callers (e.g. the topbar
 * Sync Now widget) are expected to re-read getSyncSummary()/getSyncStatus()
 * themselves, which always reflects the current IndexedDB state rather than
 * a possibly-stale snapshot handed over at notify time.
 *
 * @param {() => void} callback
 * @returns {() => void} unsubscribe
 */
export function subscribeToSyncChanges(callback) {
    listeners.add(callback);

    return () => listeners.delete(callback);
}

let activeSyncPromise = null;

export function isSyncRunning() {
    return activeSyncPromise !== null;
}

/**
 * The actual drain — never call this directly; go through
 * syncPendingOperations() so concurrent callers share the one in-flight
 * run (section 5).
 *
 * A `syncing` entry found here (section 6) can only be a leftover from a
 * PREVIOUS page load that never got to update it (tab/browser closed
 * mid-request) — a second run within THIS page is impossible because
 * syncPendingOperations() below only ever has one drain in flight at a
 * time. Re-sending it is safe even in the (out of scope, section 6 says
 * not to over-engineer cross-tab locking) case where another tab really is
 * mid-request: /api/sync's operation_uuid uniqueness means the second
 * request just gets back "in_progress" or "already_processed", never a
 * duplicate write.
 */
async function runDrain() {
    const result = { attempted: 0, synced: 0, failed: 0, conflict: 0, skipped: 0 };

    await setSyncMetadata('last_sync_started_at', new Date().toISOString());
    notify();

    try {
        const queued = await SyncQueue.listAll();
        const candidates = selectProcessable(queued);

        for (const candidate of candidates) {
            // Re-read fresh — an earlier iteration in this same loop may
            // have changed what this operation (or its dependency) looks
            // like since `queued` was fetched.
            // eslint-disable-next-line no-await-in-loop
            const current = await SyncQueue.getByUuid(candidate.operation_uuid);

            if (!current || (current.status !== SyncQueue.QueueStatus.PENDING && current.status !== SyncQueue.QueueStatus.SYNCING && current.status !== SyncQueue.QueueStatus.FAILED)) {
                continue;
            }

            // eslint-disable-next-line no-await-in-loop
            const allOperations = await SyncQueue.listAll();

            if (isBlockedByDependency(current, allOperations)) {
                result.skipped += 1;
                continue;
            }

            const processor = PROCESSORS[current.transaction_type];

            if (!processor) {
                // Unknown/unsupported transaction_type — never guess at
                // how to send it (section 9); leave it queued for a human.
                result.skipped += 1;
                continue;
            }

            result.attempted += 1;

            try {
                // eslint-disable-next-line no-await-in-loop
                await processor(current.operation_uuid);
            } catch {
                // The processor (via sync.js) has already recorded this as
                // failed and kept the entry — nothing more to do here
                // except keep draining the rest of the queue (section 8:
                // one failure must never stop unrelated operations).
            }

            // eslint-disable-next-line no-await-in-loop
            const after = await SyncQueue.getByUuid(current.operation_uuid);

            if (after?.status === SyncQueue.QueueStatus.SYNCED) {
                result.synced += 1;
            } else if (after?.status === SyncQueue.QueueStatus.CONFLICT) {
                result.conflict += 1;
            } else {
                result.failed += 1;
            }

            notify();
        }

        await setSyncMetadata('last_sync_completed_at', new Date().toISOString());

        if (result.failed === 0 && result.conflict === 0) {
            await setSyncMetadata('last_sync_success_at', new Date().toISOString());
        }
    } finally {
        notify();
    }

    return result;
}

/**
 * The ONE queue drainer — auto sync (app start / network back online /
 * visible tab) and the manual "Sync Now" button both call this exact same
 * function (section 16). A second call while a drain is already running
 * returns the SAME in-flight promise instead of starting another processor
 * (section 5) — this is the actual concurrency guard, not a disabled UI
 * button.
 *
 * @returns {Promise<{attempted:number, synced:number, failed:number, conflict:number, skipped:number}>}
 */
export function syncPendingOperations() {
    if (activeSyncPromise) {
        return activeSyncPromise;
    }

    activeSyncPromise = runDrain().finally(() => {
        activeSyncPromise = null;
        notify();
    });

    notify();

    return activeSyncPromise;
}

/**
 * Explicit, single-shot retry of ONE failed operation — same operation_uuid,
 * same payload (queue.js never rewrites either), never a new queue entry
 * (section 22). Unlike the automatic drain, this works for ANY failed entry
 * (see isManuallyRetryable()) since it is one deliberate user action, not a
 * loop — but a conflict is still never touched here (no Force Sync,
 * section 23), and a still-unresolved parent dependency still blocks it
 * (never send a child out of order just because a human clicked retry).
 *
 * @param {string} operationUuid
 */
export async function retryOperation(operationUuid) {
    const entry = await SyncQueue.getByUuid(operationUuid);

    if (!entry) {
        throw new Error(`No queued operation with operation_uuid ${operationUuid}.`);
    }

    if (!isManuallyRetryable(entry)) {
        throw new Error('Only a failed operation can be retried.');
    }

    const allOperations = await SyncQueue.listAll();

    if (isBlockedByDependency(entry, allOperations)) {
        throw new Error('Operasi induk (parent) masih menunggu sinkronisasi. Coba lagi setelah itu selesai.');
    }

    const processor = PROCESSORS[entry.transaction_type];

    if (!processor) {
        throw new Error(`Unknown transaction_type: ${entry.transaction_type}`);
    }

    try {
        return await processor(operationUuid);
    } finally {
        notify();
    }
}

/**
 * Local-only counts (section 19) — never queries the server.
 */
export async function getSyncSummary() {
    const all = await SyncQueue.listAll();
    const counts = { pending: 0, syncing: 0, failed: 0, conflict: 0, synced: 0 };

    all.forEach((operation) => {
        counts[operation.status] = (counts[operation.status] ?? 0) + 1;
    });

    return counts;
}

/**
 * Single source of truth for the global status badge (section 17/18) — the
 * priority order (conflict > failed > syncing > offline > pending > synced)
 * mirrors the spec exactly so e.g. "online but 1 failed" never reads as a
 * plain "Online".
 */
export async function getSyncStatus() {
    const summary = await getSyncSummary();

    if (summary.conflict > 0) {
        return 'conflict';
    }

    if (summary.failed > 0) {
        return 'failed';
    }

    if (isSyncRunning()) {
        return 'syncing';
    }

    if (!isOnline()) {
        return 'offline';
    }

    if (summary.pending > 0 || summary.syncing > 0) {
        return 'pending';
    }

    return 'synced';
}

export async function getSyncMetadataSummary() {
    const [startedAt, completedAt, successAt] = await Promise.all([
        getSyncMetadata('last_sync_started_at'),
        getSyncMetadata('last_sync_completed_at'),
        getSyncMetadata('last_sync_success_at'),
    ]);

    return {
        last_sync_started_at: startedAt ?? null,
        last_sync_completed_at: completedAt ?? null,
        last_sync_success_at: successAt ?? null,
    };
}

/**
 * Wires automatic sync (section 12/13/14) — call ONCE from app.js's
 * bootstrap, never from individual PM/Oil Audit Blade pages (section 12).
 * Deliberately no polling/setInterval: only three triggers ever call
 * syncPendingOperations() automatically — app start (if the network hint
 * says online), the browser's "online" event, and the tab becoming visible
 * again (both still going through the very same
 * concurrency-guarded entry point as the manual button).
 */
export function initAutoSync() {
    if (isOnline()) {
        syncPendingOperations();
    }

    onNetworkChange((online) => {
        notify();

        if (online) {
            syncPendingOperations();
        }
    });

    if (typeof document !== 'undefined' && typeof document.addEventListener === 'function') {
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible' && isOnline()) {
                syncPendingOperations();
            }
        });
    }
}
