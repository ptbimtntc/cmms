import { beforeEach, describe, expect, it } from 'vitest';
import * as Queue from '../queue.js';
import { resetTestDatabase } from './helpers.js';

beforeEach(async () => {
    await resetTestDatabase();
});

describe('enqueue', () => {
    it('stores a new operation with status pending and the given operation_uuid', async () => {
        const record = await Queue.enqueue({
            operationUuid: 'op-1',
            transactionType: 'PM_SAVE',
            payload: { pm_schedule_id: 1 },
            userId: 'user-1',
        });

        expect(record.operation_uuid).toBe('op-1');
        expect(record.status).toBe(Queue.QueueStatus.PENDING);
        expect(record.attempt_count).toBe(0);

        const stored = await Queue.getByUuid('op-1');

        expect(stored).toBeTruthy();
        expect(stored.payload).toEqual({ pm_schedule_id: 1 });
    });

    it('generates an operation_uuid when none is given', async () => {
        const record = await Queue.enqueue({ transactionType: 'OIL_AUDIT_CREATE', payload: {}, userId: 'user-1' });

        expect(record.operation_uuid).toBeTruthy();
        expect(typeof record.operation_uuid).toBe('string');
    });

    it('calling enqueue() twice with the SAME operation_uuid never creates a duplicate queue entry', async () => {
        await Queue.enqueue({ operationUuid: 'op-dup', transactionType: 'PM_SAVE', payload: { a: 1 }, userId: 'user-1' });
        const second = await Queue.enqueue({ operationUuid: 'op-dup', transactionType: 'PM_SAVE', payload: { a: 999 }, userId: 'user-1' });

        const all = await Queue.listAll();
        const matching = all.filter((entry) => entry.operation_uuid === 'op-dup');

        expect(matching).toHaveLength(1);
        // The ALREADY queued payload is returned/kept — a duplicate
        // enqueue() call does not silently overwrite it.
        expect(second.payload).toEqual({ a: 1 });
        expect(matching[0].payload).toEqual({ a: 1 });
    });

    it('two DIFFERENT operation_uuids never collide', async () => {
        await Queue.enqueue({ operationUuid: 'op-a', transactionType: 'PM_SAVE', payload: {}, userId: 'user-1' });
        await Queue.enqueue({ operationUuid: 'op-b', transactionType: 'PM_SAVE', payload: {}, userId: 'user-1' });

        const all = await Queue.listAll();

        expect(all).toHaveLength(2);
    });
});

describe('status transitions', () => {
    beforeEach(async () => {
        await Queue.enqueue({ operationUuid: 'op-status', transactionType: 'PM_START', payload: {}, userId: 'user-1' });
    });

    it('markSyncing() sets status=syncing, bumps attempt_count, and stamps last_attempt_at', async () => {
        const updated = await Queue.markSyncing('op-status');

        expect(updated.status).toBe(Queue.QueueStatus.SYNCING);
        expect(updated.attempt_count).toBe(1);
        expect(updated.last_attempt_at).toBeTruthy();
    });

    it('markSyncing() called again bumps attempt_count further (multiple retries)', async () => {
        await Queue.markSyncing('op-status');
        const second = await Queue.markSyncing('op-status');

        expect(second.attempt_count).toBe(2);
    });

    it('markSynced() sets status=synced and records the result', async () => {
        const updated = await Queue.markSynced('op-status', { subject_id: 42 });

        expect(updated.status).toBe(Queue.QueueStatus.SYNCED);
        expect(updated.result).toEqual({ subject_id: 42 });
    });

    it('markFailed() sets status=failed and stores the error message', async () => {
        const updated = await Queue.markFailed('op-status', 'Network request failed');

        expect(updated.status).toBe(Queue.QueueStatus.FAILED);
        expect(updated.last_error).toBe('Network request failed');
    });

    it('markConflict() sets status=conflict', async () => {
        const updated = await Queue.markConflict('op-status', { message: 'Server state has changed' });

        expect(updated.status).toBe(Queue.QueueStatus.CONFLICT);
    });

    it('the queue entry is NEVER removed by a status change alone — it must survive until an explicit remove()', async () => {
        await Queue.markSyncing('op-status');
        await Queue.markFailed('op-status', 'boom');

        const stillThere = await Queue.getByUuid('op-status');

        expect(stillThere).toBeTruthy();
        expect(stillThere.status).toBe(Queue.QueueStatus.FAILED);
    });
});

describe('listing', () => {
    it('listByStatus() only returns entries with that status', async () => {
        await Queue.enqueue({ operationUuid: 'p1', transactionType: 'PM_SAVE', payload: {}, userId: 'u1' });
        await Queue.enqueue({ operationUuid: 'p2', transactionType: 'PM_SAVE', payload: {}, userId: 'u1' });
        await Queue.markSynced('p2');

        const pending = await Queue.listByStatus(Queue.QueueStatus.PENDING);
        const synced = await Queue.listByStatus(Queue.QueueStatus.SYNCED);

        expect(pending.map((e) => e.operation_uuid)).toEqual(['p1']);
        expect(synced.map((e) => e.operation_uuid)).toEqual(['p2']);
    });
});

describe('durability across a simulated reload', () => {
    it('a queued operation is still readable after the shared connection is reset (simulating a page reload)', async () => {
        await Queue.enqueue({ operationUuid: 'durable-1', transactionType: 'PM_SAVE', payload: { x: 1 }, userId: 'u1' });

        // Do NOT reset the underlying fake-indexeddb database here — only
        // the module's cached connection, exactly like a real page
        // reload: a fresh JS context re-opens the SAME on-disk database.
        const { _resetConnectionForTests } = await import('../db.js');

        _resetConnectionForTests();

        const stillThere = await Queue.getByUuid('durable-1');

        expect(stillThere).toBeTruthy();
        expect(stillThere.payload).toEqual({ x: 1 });
    });
});
