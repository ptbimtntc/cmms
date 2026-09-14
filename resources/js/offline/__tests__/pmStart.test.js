// @vitest-environment jsdom
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import * as Queue from '../queue.js';
import { getLocalPmOverlay } from '../masterData.js';
import { PmStartBlockedError, processQueuedOperation, probeServerReachable, startOffline } from '../pmStart.js';
import { resetTestDatabase } from './helpers.js';

beforeEach(async () => {
    await resetTestDatabase();
    document.head.innerHTML = '<meta name="csrf-token" content="test-token">';
});

afterEach(() => {
    vi.unstubAllGlobals();
});

describe('probeServerReachable', () => {
    it('returns false immediately when navigator.onLine is false, without calling fetch', async () => {
        vi.stubGlobal('navigator', { onLine: false });
        const fetchSpy = vi.fn();

        vi.stubGlobal('fetch', fetchSpy);

        const result = await probeServerReachable();

        expect(result).toBe(false);
        expect(fetchSpy).not.toHaveBeenCalled();
    });

    it('returns true when navigator.onLine is true AND /up responds ok', async () => {
        vi.stubGlobal('navigator', { onLine: true });
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: true }));

        expect(await probeServerReachable()).toBe(true);
    });

    it('returns false when navigator.onLine is true but the request throws (server unreachable despite a "connected" network)', async () => {
        vi.stubGlobal('navigator', { onLine: true });
        vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new TypeError('Failed to fetch')));

        expect(await probeServerReachable()).toBe(false);
    });

    it('returns false when the server responds but with a non-ok status', async () => {
        vi.stubGlobal('navigator', { onLine: true });
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: false, status: 503 }));

        expect(await probeServerReachable()).toBe(false);
    });
});

describe('startOffline', () => {
    it('makes NO network request at all', async () => {
        const fetchSpy = vi.fn();

        vi.stubGlobal('fetch', fetchSpy);

        await startOffline({ pmScheduleId: 1, startedAtLocal: '2026-09-14T08:30', expectedStatus: 'OPEN' });

        expect(fetchSpy).not.toHaveBeenCalled();
    });

    it('enqueues a PM_START operation with the expected payload shape', async () => {
        const record = await startOffline({ pmScheduleId: 42, startedAtLocal: '2026-09-14T08:30', expectedStatus: 'OPEN' });

        expect(record.transaction_type).toBe('PM_START');
        expect(record.status).toBe(Queue.QueueStatus.PENDING);
        expect(record.payload).toEqual({
            pm_schedule_id: 42,
            started_at: '2026-09-14T08:30',
            confirm_end_start: false,
        });
        expect(record.expected_state).toEqual({ status: 'OPEN', is_started: false });
    });

    it('stores a local PM overlay so the UI can show "started offline" after this resolves', async () => {
        await startOffline({ pmScheduleId: 42, startedAtLocal: '2026-09-14T08:30', expectedStatus: 'OPEN' });

        const overlay = await getLocalPmOverlay(42);

        expect(overlay.local_status).toBe('started_offline');
        expect(overlay.local_actual_date).toBe('2026-09-14');
        expect(overlay.local_start_time).toBe('08:30');
        expect(overlay.pending_operation_uuid).toBeTruthy();
    });

    it('blocks a second offline Start for a DIFFERENT PM while one is still pending', async () => {
        await startOffline({ pmScheduleId: 1, startedAtLocal: '2026-09-14T08:00', expectedStatus: 'OPEN' });

        await expect(
            startOffline({ pmScheduleId: 2, startedAtLocal: '2026-09-14T08:05', expectedStatus: 'OPEN' })
        ).rejects.toBeInstanceOf(PmStartBlockedError);

        const all = await Queue.listAll();

        expect(all).toHaveLength(1);
        expect(all[0].payload.pm_schedule_id).toBe(1);
    });

    it('does NOT block once the earlier operation is no longer pending/syncing (e.g. already synced)', async () => {
        const first = await startOffline({ pmScheduleId: 1, startedAtLocal: '2026-09-14T08:00', expectedStatus: 'OPEN' });

        await Queue.markSynced(first.operation_uuid);

        await expect(
            startOffline({ pmScheduleId: 2, startedAtLocal: '2026-09-14T08:05', expectedStatus: 'OPEN' })
        ).resolves.toBeTruthy();
    });

    it('does not block a different USER on the same shared device', async () => {
        // Simulate two different users by scoping via distinct userId — see
        // scope.js; startOffline() itself reads currentUserId() from the
        // DOM, but the blocking check is exercised directly here through
        // the queue records to prove the isolation independent of the DOM.
        await Queue.enqueue({ operationUuid: 'other-user-op', transactionType: 'PM_START', payload: { pm_schedule_id: 99 }, userId: 'user-B' });

        // No app-user-id meta tag -> currentUserId() is null for THIS call.
        await expect(
            startOffline({ pmScheduleId: 1, startedAtLocal: '2026-09-14T08:00', expectedStatus: 'OPEN' })
        ).resolves.toBeTruthy();
    });
});

describe('processQueuedOperation', () => {
    async function queuedPmStart(pmScheduleId = 1) {
        return startOffline({ pmScheduleId, startedAtLocal: '2026-09-14T08:00', expectedStatus: 'OPEN' });
    }

    it('marks the operation syncing before sending the request', async () => {
        const record = await queuedPmStart();
        let statusDuringFetch = null;

        vi.stubGlobal('fetch', vi.fn(async () => {
            statusDuringFetch = (await Queue.getByUuid(record.operation_uuid)).status;

            return { json: async () => ({ status: 'processed', subject_id: 1 }) };
        }));

        await processQueuedOperation(record.operation_uuid);

        expect(statusDuringFetch).toBe(Queue.QueueStatus.SYNCING);
    });

    it('sends the exact envelope shape to /api/sync', async () => {
        const record = await queuedPmStart(7);
        const fetchSpy = vi.fn().mockResolvedValue({ json: async () => ({ status: 'processed' }) });

        vi.stubGlobal('fetch', fetchSpy);

        await processQueuedOperation(record.operation_uuid);

        expect(fetchSpy).toHaveBeenCalledWith('/api/sync', expect.objectContaining({
            method: 'POST',
            headers: expect.objectContaining({ 'X-CSRF-TOKEN': 'test-token' }),
        }));

        const sentBody = JSON.parse(fetchSpy.mock.calls[0][1].body);

        expect(sentBody).toEqual({
            operation_uuid: record.operation_uuid,
            transaction_type: 'PM_START',
            payload: record.payload,
            expected_state: record.expected_state,
        });
    });

    it('on "processed": marks synced and clears the local PM overlay', async () => {
        const record = await queuedPmStart(7);

        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ json: async () => ({ status: 'processed', subject_id: 7 }) }));

        await processQueuedOperation(record.operation_uuid);

        const stored = await Queue.getByUuid(record.operation_uuid);

        expect(stored.status).toBe(Queue.QueueStatus.SYNCED);
        expect(await getLocalPmOverlay(7)).toBeNull();
    });

    it('on "already_processed": also marks synced (a retry response is treated as success)', async () => {
        const record = await queuedPmStart(7);

        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ json: async () => ({ status: 'already_processed' }) }));

        await processQueuedOperation(record.operation_uuid);

        expect((await Queue.getByUuid(record.operation_uuid)).status).toBe(Queue.QueueStatus.SYNCED);
    });

    it('on "conflict": marks conflict and does NOT clear the local overlay (never force-resolved)', async () => {
        const record = await queuedPmStart(7);

        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({
            json: async () => ({ status: 'conflict', message: 'Server state has changed.' }),
        }));

        await processQueuedOperation(record.operation_uuid);

        const stored = await Queue.getByUuid(record.operation_uuid);

        expect(stored.status).toBe(Queue.QueueStatus.CONFLICT);
        expect(await getLocalPmOverlay(7)).not.toBeNull();
    });

    it('on "payload_mismatch": marks conflict', async () => {
        const record = await queuedPmStart(7);

        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ json: async () => ({ status: 'payload_mismatch' }) }));

        await processQueuedOperation(record.operation_uuid);

        expect((await Queue.getByUuid(record.operation_uuid)).status).toBe(Queue.QueueStatus.CONFLICT);
    });

    it('on "forbidden": marks failed with the server message, keeping the entry (never dropped)', async () => {
        const record = await queuedPmStart(7);

        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({
            json: async () => ({ status: 'forbidden', message: 'Anda tidak memiliki akses ke PM Schedule ini.' }),
        }));

        await processQueuedOperation(record.operation_uuid);

        const stored = await Queue.getByUuid(record.operation_uuid);

        expect(stored.status).toBe(Queue.QueueStatus.FAILED);
        expect(stored.last_error).toContain('akses');
    });

    it('on a network failure during sync: marks failed, keeps the operation, and rethrows', async () => {
        const record = await queuedPmStart(7);

        vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new TypeError('Failed to fetch')));

        await expect(processQueuedOperation(record.operation_uuid)).rejects.toThrow();

        const stored = await Queue.getByUuid(record.operation_uuid);

        expect(stored.status).toBe(Queue.QueueStatus.FAILED);
        expect(stored).toBeTruthy(); // still in the queue, not dropped
    });
});
