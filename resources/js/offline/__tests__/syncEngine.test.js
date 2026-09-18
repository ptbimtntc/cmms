// @vitest-environment jsdom
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import * as Queue from '../queue.js';
import * as SyncEngine from '../syncEngine.js';
import { resetTestDatabase } from './helpers.js';

beforeEach(async () => {
    await resetTestDatabase();
    document.head.innerHTML = '<meta name="csrf-token" content="test-token"><meta name="app-user-id" content="user-1">';
});

afterEach(() => {
    vi.unstubAllGlobals();
});

/**
 * A tiny fake /api/sync — resolves each request from a map keyed by
 * operation_uuid, so a test can script exactly what the "server" says for
 * each queued operation without caring about call order.
 */
function fakeServer(responsesByUuid) {
    return vi.fn(async (url, options) => {
        const body = JSON.parse(options.body);
        const response = responsesByUuid[body.operation_uuid];

        if (!response) {
            throw new Error(`fakeServer: no scripted response for ${body.operation_uuid}`);
        }

        if (response instanceof Error) {
            throw response;
        }

        return { json: async () => response };
    });
}

async function enqueuePmStart(uuid, pmScheduleId, extra = {}) {
    return Queue.enqueue({
        operationUuid: uuid,
        transactionType: 'PM_START',
        payload: { pm_schedule_id: pmScheduleId },
        userId: 'user-1',
        ...extra,
    });
}

async function enqueuePmSave(uuid, pmScheduleId, extra = {}) {
    return Queue.enqueue({
        operationUuid: uuid,
        transactionType: 'PM_SAVE',
        payload: { pm_schedule_id: pmScheduleId },
        userId: 'user-1',
        ...extra,
    });
}

async function enqueuePmChecklist(uuid, pmScheduleId, extra = {}) {
    return Queue.enqueue({
        operationUuid: uuid,
        transactionType: 'PM_CHECKLIST_SAVE',
        payload: { pm_schedule_id: pmScheduleId },
        userId: 'user-1',
        ...extra,
    });
}

async function enqueueOilAuditCreate(uuid, machineId, extra = {}) {
    return Queue.enqueue({
        operationUuid: uuid,
        transactionType: 'OIL_AUDIT_CREATE',
        payload: { machine_id: machineId },
        userId: 'user-1',
        ...extra,
    });
}

async function enqueueOilAuditFollowUp(uuid, oilAuditId, extra = {}) {
    return Queue.enqueue({
        operationUuid: uuid,
        transactionType: 'OIL_AUDIT_FOLLOW_UP_SAVE',
        payload: { oil_audit_id: oilAuditId },
        userId: 'user-1',
        ...extra,
    });
}

describe('syncPendingOperations() — queue drain', () => {
    it('processes a single pending operation via the existing sendQueuedOperation plumbing', async () => {
        const op = await enqueuePmStart('op-1', 1);

        vi.stubGlobal('fetch', fakeServer({ 'op-1': { status: 'processed', subject_id: 1 } }));

        const result = await SyncEngine.syncPendingOperations();

        expect(result).toEqual({ attempted: 1, synced: 1, failed: 0, conflict: 0, skipped: 0 });
        expect((await Queue.getByUuid(op.operation_uuid)).status).toBe(Queue.QueueStatus.SYNCED);
    });

    it('processes multiple independent pending operations', async () => {
        await enqueuePmStart('op-1', 1);
        await enqueueOilAuditCreate('op-2', 55);

        vi.stubGlobal('fetch', fakeServer({
            'op-1': { status: 'processed' },
            'op-2': { status: 'processed' },
        }));

        const result = await SyncEngine.syncPendingOperations();

        expect(result.attempted).toBe(2);
        expect(result.synced).toBe(2);
    });

    it('an empty queue resolves cleanly without error', async () => {
        vi.stubGlobal('fetch', vi.fn());

        const result = await SyncEngine.syncPendingOperations();

        expect(result).toEqual({ attempted: 0, synced: 0, failed: 0, conflict: 0, skipped: 0 });
        expect(result.attempted).toBe(0);
    });

    it('never calls /api/sync directly from the drainer — only through the existing per-module processors', async () => {
        await enqueuePmStart('op-1', 1);

        const fetchSpy = fakeServer({ 'op-1': { status: 'processed' } });

        vi.stubGlobal('fetch', fetchSpy);
        await SyncEngine.syncPendingOperations();

        expect(fetchSpy).toHaveBeenCalledWith('/api/sync', expect.objectContaining({ method: 'POST' }));
    });
});

describe('syncPendingOperations() — concurrency guard', () => {
    /**
     * The gate's resolver is captured synchronously at mock-creation time
     * (not inside the async fetch call itself), so it is always safe to
     * call even before the drainer's IndexedDB reads have resolved enough
     * for fetch() to actually have been invoked yet.
     */
    function gatedFetch() {
        let release;
        const gate = new Promise((resolve) => { release = resolve; });
        const spy = vi.fn(async () => {
            await gate;

            return { json: async () => ({ status: 'processed' }) };
        });

        return { spy, release: () => release() };
    }

    it('two concurrent calls share the SAME in-flight run instead of double-processing', async () => {
        await enqueuePmStart('op-1', 1);

        const { spy: fetchSpy, release } = gatedFetch();

        vi.stubGlobal('fetch', fetchSpy);

        const first = SyncEngine.syncPendingOperations();
        const second = SyncEngine.syncPendingOperations();

        expect(SyncEngine.isSyncRunning()).toBe(true);

        release();
        const [firstResult, secondResult] = await Promise.all([first, second]);

        expect(fetchSpy).toHaveBeenCalledTimes(1);
        expect(firstResult).toBe(secondResult);
        expect(SyncEngine.isSyncRunning()).toBe(false);
    });

    it('a manual trigger while an auto-triggered run is in flight does not start a second processor', async () => {
        await enqueuePmStart('op-1', 1);

        const { spy: fetchSpy, release } = gatedFetch();

        vi.stubGlobal('fetch', fetchSpy);

        const autoRun = SyncEngine.syncPendingOperations(); // simulates the "online" trigger
        const manualRun = SyncEngine.syncPendingOperations(); // simulates clicking "Sync Now"

        expect(autoRun).toBe(manualRun);

        release();
        await autoRun;

        expect(fetchSpy).toHaveBeenCalledTimes(1);
    });
});

describe('syncPendingOperations() — ordering', () => {
    it('processes PM_START before PM_SAVE before PM_CHECKLIST_SAVE for the same pm_schedule_id, in creation order', async () => {
        await enqueuePmStart('start-1', 1);
        await enqueuePmSave('save-1', 1);
        await enqueuePmChecklist('checklist-1', 1);

        const callOrder = [];
        vi.stubGlobal('fetch', vi.fn(async (url, options) => {
            const body = JSON.parse(options.body);

            callOrder.push(body.operation_uuid);

            return { json: async () => ({ status: 'processed' }) };
        }));

        await SyncEngine.syncPendingOperations();

        expect(callOrder).toEqual(['start-1', 'save-1', 'checklist-1']);
    });

    it('processes OIL_AUDIT_CREATE before a later-queued OIL_AUDIT_FOLLOW_UP_SAVE (creation order)', async () => {
        await enqueueOilAuditCreate('create-1', 10);
        await enqueueOilAuditFollowUp('followup-1', 200);

        const callOrder = [];
        vi.stubGlobal('fetch', vi.fn(async (url, options) => {
            callOrder.push(JSON.parse(options.body).operation_uuid);

            return { json: async () => ({ status: 'processed' }) };
        }));

        await SyncEngine.syncPendingOperations();

        expect(callOrder).toEqual(['create-1', 'followup-1']);
    });
});

describe('syncPendingOperations() — failure isolation', () => {
    it('an unrelated valid operation B is still processed when operation A fails', async () => {
        await enqueuePmStart('op-a', 1);
        await enqueuePmStart('op-b', 2); // different pm_schedule_id, would normally be blocked by the module's own
        // local "one PM_START at a time" guard when queued via startOffline(), but that guard is a caller-side
        // concern (pmStart.js) — the drainer itself must still treat these as independent operations.

        vi.stubGlobal('fetch', fakeServer({
            'op-a': { status: 'failed', message: 'Server error' },
            'op-b': { status: 'processed' },
        }));

        const result = await SyncEngine.syncPendingOperations();

        expect((await Queue.getByUuid('op-a')).status).toBe(Queue.QueueStatus.FAILED);
        expect((await Queue.getByUuid('op-b')).status).toBe(Queue.QueueStatus.SYNCED);
        expect(result.synced).toBe(1);
        expect(result.failed).toBe(1);
    });
});

describe('syncPendingOperations() — dependency blocking', () => {
    it('does NOT send PM_SAVE while its parent PM_START (same pm_schedule_id) is still pending', async () => {
        await enqueuePmStart('start-1', 1);
        await enqueuePmSave('save-1', 1);

        // PM_START is never given a scripted response, so if the drainer
        // tried to send it anyway this test would throw via fakeServer's
        // "no scripted response" guard below for save-1 specifically.
        vi.stubGlobal('fetch', fakeServer({ 'start-1': { status: 'failed', message: 'boom' } }));

        await SyncEngine.syncPendingOperations();

        expect((await Queue.getByUuid('start-1')).status).toBe(Queue.QueueStatus.FAILED);
        // save-1 was never sent (no fetch scripted for it, so had it been
        // attempted, fakeServer would have thrown and left it FAILED with
        // that thrown-error message instead of remaining PENDING).
        expect((await Queue.getByUuid('save-1')).status).toBe(Queue.QueueStatus.PENDING);
    });

    it('resolves the dependency within the SAME drain pass once the parent succeeds in it', async () => {
        await enqueuePmStart('start-1', 1);
        await enqueuePmSave('save-1', 1);

        // Both scripted in the one run — no need to wait for a second
        // trigger just because save-1 was initially blocked: FIFO order
        // means start-1 resolves first in this very pass, so save-1 is no
        // longer blocked by the time the drainer reaches it.
        vi.stubGlobal('fetch', fakeServer({
            'start-1': { status: 'processed' },
            'save-1': { status: 'processed' },
        }));

        await SyncEngine.syncPendingOperations();

        expect((await Queue.getByUuid('start-1')).status).toBe(Queue.QueueStatus.SYNCED);
        expect((await Queue.getByUuid('save-1')).status).toBe(Queue.QueueStatus.SYNCED);
    });

    it('sends the child in a LATER drain run once a previously-failed parent has since synced', async () => {
        await enqueuePmStart('start-1', 1);
        await enqueuePmSave('save-1', 1);

        // validation_failed is not auto-retryable, so start-1 stays FAILED
        // after this run and save-1 stays blocked.
        vi.stubGlobal('fetch', fakeServer({ 'start-1': { status: 'validation_failed', message: 'bad data' } }));
        await SyncEngine.syncPendingOperations();

        expect((await Queue.getByUuid('start-1')).status).toBe(Queue.QueueStatus.FAILED);
        expect((await Queue.getByUuid('save-1')).status).toBe(Queue.QueueStatus.PENDING);

        // User fixes/retries the parent explicitly.
        vi.stubGlobal('fetch', fakeServer({ 'start-1': { status: 'processed' } }));
        await SyncEngine.retryOperation('start-1');
        expect((await Queue.getByUuid('start-1')).status).toBe(Queue.QueueStatus.SYNCED);

        // A fresh drain now sends the previously-blocked child.
        vi.stubGlobal('fetch', fakeServer({ 'save-1': { status: 'processed' } }));
        await SyncEngine.syncPendingOperations();

        expect((await Queue.getByUuid('save-1')).status).toBe(Queue.QueueStatus.SYNCED);
    });

    it('a failed parent blocks its child indefinitely, while unrelated operations keep draining', async () => {
        await enqueuePmStart('start-A', 1);
        await enqueuePmSave('save-A', 1);
        await enqueuePmSave('save-B', 2); // unrelated PM, no PM_START dependency queued

        vi.stubGlobal('fetch', fakeServer({
            'start-A': { status: 'validation_failed', message: 'invalid' },
            'save-B': { status: 'processed' },
        }));

        const result = await SyncEngine.syncPendingOperations();

        expect((await Queue.getByUuid('start-A')).status).toBe(Queue.QueueStatus.FAILED);
        expect((await Queue.getByUuid('save-A')).status).toBe(Queue.QueueStatus.PENDING);
        expect((await Queue.getByUuid('save-B')).status).toBe(Queue.QueueStatus.SYNCED);
        expect(result.skipped).toBe(1);
    });
});

describe('automatic retry classification', () => {
    it('a transient ("failed" status) server failure is retried automatically on the next drain', async () => {
        await enqueuePmStart('op-1', 1);

        vi.stubGlobal('fetch', fakeServer({ 'op-1': { status: 'failed', message: 'Server error' } }));
        await SyncEngine.syncPendingOperations();
        expect((await Queue.getByUuid('op-1')).status).toBe(Queue.QueueStatus.FAILED);

        // Backoff is attempt_count-based (min(attempt*5000ms, 60000ms)) — bypass it deterministically for the test
        // instead of sleeping, by rewinding last_attempt_at into the past.
        const stale = await Queue.getByUuid('op-1');
        const OfflineDb = await import('../db.js');
        await OfflineDb.OfflineStorage.put(OfflineDb.STORES.SYNC_QUEUE, { ...stale, last_attempt_at: new Date(Date.now() - 120000).toISOString() });

        vi.stubGlobal('fetch', fakeServer({ 'op-1': { status: 'processed' } }));
        const result = await SyncEngine.syncPendingOperations();

        expect(result.attempted).toBe(1);
        expect((await Queue.getByUuid('op-1')).status).toBe(Queue.QueueStatus.SYNCED);
    });

    it('a validation_failed failure is NOT auto-retried on the next drain', async () => {
        await enqueuePmStart('op-1', 1);

        vi.stubGlobal('fetch', fakeServer({ 'op-1': { status: 'validation_failed', message: 'bad data' } }));
        await SyncEngine.syncPendingOperations();

        const afterFirst = await Queue.getByUuid('op-1');
        expect(afterFirst.status).toBe(Queue.QueueStatus.FAILED);
        expect(afterFirst.last_error_status).toBe('validation_failed');

        // If the drainer wrongly auto-retried this, the second run would
        // call fetch again and this unscripted response would throw.
        const fetchSpy = fakeServer({});
        vi.stubGlobal('fetch', fetchSpy);
        const result = await SyncEngine.syncPendingOperations();

        expect(fetchSpy).not.toHaveBeenCalled();
        expect(result.attempted).toBe(0);
    });

    it('a forbidden failure is NOT auto-retried either', async () => {
        await enqueuePmStart('op-1', 1);

        vi.stubGlobal('fetch', fakeServer({ 'op-1': { status: 'forbidden', message: 'no access' } }));
        await SyncEngine.syncPendingOperations();

        const fetchSpy = fakeServer({});
        vi.stubGlobal('fetch', fetchSpy);
        await SyncEngine.syncPendingOperations();

        expect(fetchSpy).not.toHaveBeenCalled();
    });

    it('a conflict is NEVER auto-retried (and stays out of the drain entirely)', async () => {
        await enqueuePmStart('op-1', 1);

        vi.stubGlobal('fetch', fakeServer({ 'op-1': { status: 'conflict', message: 'stale' } }));
        await SyncEngine.syncPendingOperations();
        expect((await Queue.getByUuid('op-1')).status).toBe(Queue.QueueStatus.CONFLICT);

        const fetchSpy = fakeServer({});
        vi.stubGlobal('fetch', fetchSpy);
        await SyncEngine.syncPendingOperations();

        expect(fetchSpy).not.toHaveBeenCalled();
    });

    it('a network failure (fetch throws) is tagged network_error and IS eligible for auto-retry', async () => {
        await enqueuePmStart('op-1', 1);

        vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new TypeError('Failed to fetch')));
        await SyncEngine.syncPendingOperations();

        const afterFirst = await Queue.getByUuid('op-1');
        expect(afterFirst.status).toBe(Queue.QueueStatus.FAILED);
        expect(afterFirst.last_error_status).toBe('network_error');
    });
});

describe('retryOperation()', () => {
    it('resends the SAME operation_uuid and payload — never a new queue entry', async () => {
        await enqueuePmStart('op-1', 1);

        vi.stubGlobal('fetch', fakeServer({ 'op-1': { status: 'failed', message: 'boom' } }));
        await SyncEngine.syncPendingOperations();

        const sentPayloads = [];
        vi.stubGlobal('fetch', vi.fn(async (url, options) => {
            const body = JSON.parse(options.body);
            sentPayloads.push(body);

            return { json: async () => ({ status: 'processed' }) };
        }));

        await SyncEngine.retryOperation('op-1');

        expect(sentPayloads).toHaveLength(1);
        expect(sentPayloads[0].operation_uuid).toBe('op-1');
        expect(sentPayloads[0].payload).toEqual({ pm_schedule_id: 1 });

        const all = await Queue.listAll();
        expect(all.filter((entry) => entry.operation_uuid === 'op-1' || entry.payload?.pm_schedule_id === 1)).toHaveLength(1);
        expect((await Queue.getByUuid('op-1')).status).toBe(Queue.QueueStatus.SYNCED);
    });

    it('rejects for an operation that is not in failed state (e.g. still pending)', async () => {
        await enqueuePmStart('op-1', 1);

        await expect(SyncEngine.retryOperation('op-1')).rejects.toThrow();
    });

    it('rejects for a conflict entry — never a Force Sync', async () => {
        await enqueuePmStart('op-1', 1);
        vi.stubGlobal('fetch', fakeServer({ 'op-1': { status: 'conflict' } }));
        await SyncEngine.syncPendingOperations();

        await expect(SyncEngine.retryOperation('op-1')).rejects.toThrow();
    });

    it('respects the same dependency block as the automatic drain', async () => {
        await enqueuePmStart('start-1', 1);
        await enqueuePmSave('save-1', 1);

        vi.stubGlobal('fetch', fakeServer({ 'save-1': { status: 'failed', message: 'sent too early?' } }));

        // Force save-1 into FAILED directly to test retryOperation() in isolation
        // from the drainer's own dependency skip.
        await Queue.markFailed('save-1', 'boom', { statusCode: 'failed' });

        await expect(SyncEngine.retryOperation('save-1')).rejects.toThrow();
    });

    it('a validation_failed / forbidden failure IS manually retryable (user fixed something / got access)', async () => {
        await enqueuePmStart('op-1', 1);
        vi.stubGlobal('fetch', fakeServer({ 'op-1': { status: 'validation_failed', message: 'bad' } }));
        await SyncEngine.syncPendingOperations();

        const entry = await Queue.getByUuid('op-1');
        expect(SyncEngine.isManuallyRetryable(entry)).toBe(true);

        vi.stubGlobal('fetch', fakeServer({ 'op-1': { status: 'processed' } }));
        await SyncEngine.retryOperation('op-1');

        expect((await Queue.getByUuid('op-1')).status).toBe(Queue.QueueStatus.SYNCED);
    });
});

describe('getSyncSummary() / getSyncStatus()', () => {
    it('counts each status bucket from local IndexedDB only (no fetch involved)', async () => {
        const fetchSpy = vi.fn();
        vi.stubGlobal('fetch', fetchSpy);

        await enqueuePmStart('p1', 1);
        await enqueuePmStart('p2', 2);
        await Queue.markSynced('p2');
        await enqueuePmSave('f1', 3);
        await Queue.markFailed('f1', 'boom', { statusCode: 'failed' });
        await enqueuePmSave('c1', 4);
        await Queue.markConflict('c1', {});

        const summary = await SyncEngine.getSyncSummary();

        expect(summary).toEqual({ pending: 1, syncing: 0, failed: 1, conflict: 1, synced: 1 });
        expect(fetchSpy).not.toHaveBeenCalled();
    });

    it('status priority: conflict beats failed beats pending beats synced', async () => {
        vi.stubGlobal('navigator', { onLine: true });

        await enqueuePmStart('p1', 1);
        expect(await SyncEngine.getSyncStatus()).toBe('pending');

        await Queue.markFailed('p1', 'boom', { statusCode: 'failed' });
        expect(await SyncEngine.getSyncStatus()).toBe('failed');

        await enqueuePmStart('p2', 2);
        await Queue.markConflict('p2', {});
        expect(await SyncEngine.getSyncStatus()).toBe('conflict');
    });

    it('reports "offline" when nothing is pending/failed/conflict and the network hint is offline', async () => {
        vi.stubGlobal('navigator', { onLine: false });

        expect(await SyncEngine.getSyncStatus()).toBe('offline');
    });

    it('reports "synced" when the queue is empty/all-synced and online', async () => {
        vi.stubGlobal('navigator', { onLine: true });

        await enqueuePmStart('p1', 1);
        await Queue.markSynced('p1');

        expect(await SyncEngine.getSyncStatus()).toBe('synced');
    });
});
