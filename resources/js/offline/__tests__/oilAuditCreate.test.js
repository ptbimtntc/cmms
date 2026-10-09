// @vitest-environment jsdom
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import * as Queue from '../queue.js';
import * as Drafts from '../drafts.js';
import * as SyncEngine from '../syncEngine.js';
import { putMasterData } from '../masterData.js';
import {
    MACHINE_CATEGORY,
    OilAuditBlockedError,
    OilAuditDuplicateError,
    businessDate,
    discardMachineDraft,
    findExistingAudit,
    restoreMachineDraft,
    seedAuditedToday,
    selectMachineDraft,
    OilAuditValidationError,
    findCachedMachineByNumber,
    processQueuedOperation,
    saveOffline,
    validatePayloadLocally,
} from '../oilAuditCreate.js';
import { resetTestDatabase } from './helpers.js';

beforeEach(async () => {
    await resetTestDatabase();
    document.head.innerHTML = '<meta name="csrf-token" content="test-token">';
});

afterEach(() => {
    vi.unstubAllGlobals();
});

describe('findCachedMachineByNumber', () => {
    it('finds a machine cached under the "machines" category by exact machine_number', async () => {
        await putMasterData(MACHINE_CATEGORY, 5, { id: 5, machine_number: '10001', machine_type: 'NDE', area: 'WWD' });

        const machine = await findCachedMachineByNumber('10001');

        expect(machine).toEqual({ id: 5, machine_number: '10001', machine_type: 'NDE', area: 'WWD' });
    });

    it('trims whitespace before matching (scan/manual input can include stray whitespace)', async () => {
        await putMasterData(MACHINE_CATEGORY, 5, { id: 5, machine_number: '10001', machine_type: 'NDE', area: 'WWD' });

        expect(await findCachedMachineByNumber('  10001  ')).toBeTruthy();
    });

    it('returns undefined for a machine number not in the local cache — never invents one', async () => {
        await putMasterData(MACHINE_CATEGORY, 5, { id: 5, machine_number: '10001', machine_type: 'NDE', area: 'WWD' });

        expect(await findCachedMachineByNumber('99999')).toBeUndefined();
    });

    it('returns undefined for an empty/blank identifier', async () => {
        expect(await findCachedMachineByNumber('')).toBeUndefined();
        expect(await findCachedMachineByNumber('   ')).toBeUndefined();
    });
});

describe('validatePayloadLocally', () => {
    it('passes when both machine and condition are chosen', () => {
        expect(() => validatePayloadLocally({ machineId: 5, condition: 'OKE' })).not.toThrow();
    });

    it('rejects a missing machineId', () => {
        expect(() => validatePayloadLocally({ machineId: null, condition: 'OKE' })).toThrow(OilAuditValidationError);
    });

    it('rejects a missing condition', () => {
        expect(() => validatePayloadLocally({ machineId: 5, condition: '' })).toThrow(OilAuditValidationError);
    });
});

describe('saveOffline', () => {
    it('makes NO network request', async () => {
        const fetchSpy = vi.fn();

        vi.stubGlobal('fetch', fetchSpy);

        await saveOffline({ machineId: 5, condition: 'OKE' });

        expect(fetchSpy).not.toHaveBeenCalled();
    });

    it('enqueues exactly ONE OIL_AUDIT_CREATE operation with the minimal payload', async () => {
        const record = await saveOffline({ machineId: 5, condition: 'KRITIS' });

        expect(record.transaction_type).toBe('OIL_AUDIT_CREATE');
        expect(record.payload).toEqual({ machine_id: 5, condition: 'KRITIS' });
        expect(record.status).toBe(Queue.QueueStatus.PENDING);

        expect(await Queue.listAll()).toHaveLength(1);
    });

    it('never fabricates an expected_state (insert-only, no meaningful prior state)', async () => {
        const record = await saveOffline({ machineId: 5, condition: 'OKE' });

        expect(record.expected_state).toEqual({});
    });

    it('never includes user_id/pic/area in the payload — the server resolves those itself', async () => {
        const record = await saveOffline({ machineId: 5, condition: 'OKE' });

        expect(record.payload).not.toHaveProperty('user_id');
        expect(record.payload).not.toHaveProperty('pic');
        expect(record.payload).not.toHaveProperty('area');
    });

    it('throws OilAuditValidationError and enqueues nothing for an incomplete payload', async () => {
        await expect(saveOffline({ machineId: null, condition: 'OKE' })).rejects.toBeInstanceOf(OilAuditValidationError);

        expect(await Queue.listAll()).toHaveLength(0);
    });

    it('a double-tap Save (two rapid calls for the SAME machine) does not create a duplicate queue operation for a different logical save', async () => {
        // Each call generates its own operation_uuid by design — this test
        // documents that saveOffline() itself does not dedupe identical
        // rapid calls (that guard lives in the UI layer, same as Task 4's
        // submit-button disable pattern); it only proves that a genuinely
        // blocked (different-machine) second save is prevented locally,
        // exercised in the "blocks" test below.
        const first = await saveOffline({ machineId: 5, condition: 'OKE' });

        await Queue.markSynced(first.operation_uuid);

        // Task 10: even once synced, the same machine cannot be audited a
        // second time on the same business date.
        await expect(saveOffline({ machineId: 5, condition: 'PANTAU' })).rejects.toBeInstanceOf(OilAuditDuplicateError);

        expect(await Queue.listAll()).toHaveLength(1);
    });

    it('blocks a second offline audit for a DIFFERENT machine while one is still pending', async () => {
        await saveOffline({ machineId: 5, condition: 'OKE' });

        await expect(saveOffline({ machineId: 6, condition: 'OKE' })).rejects.toBeInstanceOf(OilAuditBlockedError);

        expect(await Queue.listAll()).toHaveLength(1);
    });

    it('does not block once the earlier operation is no longer pending/syncing', async () => {
        const first = await saveOffline({ machineId: 5, condition: 'OKE' });

        await Queue.markSynced(first.operation_uuid);

        await expect(saveOffline({ machineId: 6, condition: 'OKE' })).resolves.toBeTruthy();
    });

    it('survives a simulated page reload (connection reset) — queue durability', async () => {
        const record = await saveOffline({ machineId: 5, condition: 'OKE' });

        const { _resetConnectionForTests } = await import('../db.js');

        _resetConnectionForTests();

        const stillThere = await Queue.getByUuid(record.operation_uuid);

        expect(stillThere.payload).toEqual({ machine_id: 5, condition: 'OKE' });
    });
});

describe('machine-selection draft (Task 7 section 8)', () => {
    it('a draft can be created, read back, and survives a simulated reload', async () => {
        const draft = await Drafts.saveDraft({
            draftType: 'OIL_AUDIT',
            referenceId: 5,
            payload: { machine_id: 5, machine_number: '10001', machine_type: 'NDE', area: 'WWD' },
            userId: null,
        });

        const { _resetConnectionForTests } = await import('../db.js');

        _resetConnectionForTests();

        const found = await Drafts.getDraftByReference({ userId: null, draftType: 'OIL_AUDIT', referenceId: 5 });

        expect(found.draft_id).toBe(draft.draft_id);
        expect(found.payload.machine_number).toBe('10001');
    });

    it('user_id: null never causes a DataError (Task 5 regression guarded again here)', async () => {
        await expect(Drafts.saveDraft({
            draftType: 'OIL_AUDIT', referenceId: 5, payload: { machine_id: 5 }, userId: null,
        })).resolves.toBeTruthy();
    });
});

describe('processQueuedOperation', () => {
    it('on "processed": marks synced and deletes the matching machine-selection draft', async () => {
        await Drafts.saveDraft({ draftType: 'OIL_AUDIT', referenceId: 5, payload: { machine_id: 5 }, userId: null });
        const record = await saveOffline({ machineId: 5, condition: 'OKE' });

        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ json: async () => ({ status: 'processed', subject_id: 99 }) }));

        await processQueuedOperation(record.operation_uuid);

        expect((await Queue.getByUuid(record.operation_uuid)).status).toBe(Queue.QueueStatus.SYNCED);
        expect(await Drafts.getDraftByReference({ userId: null, draftType: 'OIL_AUDIT', referenceId: 5 })).toBeUndefined();
    });

    it('on "validation_failed": marks failed, keeps the operation retryable', async () => {
        const record = await saveOffline({ machineId: 5, condition: 'OKE' });

        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({
            json: async () => ({ status: 'validation_failed', errors: { machine_id: ['Machine tidak ditemukan.'] } }),
        }));

        await processQueuedOperation(record.operation_uuid);

        const stored = await Queue.getByUuid(record.operation_uuid);

        expect(stored.status).toBe(Queue.QueueStatus.FAILED);
        expect(stored).toBeTruthy();
    });

    it('on "forbidden": marks failed with the server message', async () => {
        const record = await saveOffline({ machineId: 5, condition: 'OKE' });

        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({
            json: async () => ({ status: 'forbidden', message: 'Role Anda tidak diizinkan untuk operasi ini.' }),
        }));

        await processQueuedOperation(record.operation_uuid);

        expect((await Queue.getByUuid(record.operation_uuid)).last_error).toContain('diizinkan');
    });

    it('on a network failure during sync: marks failed, keeps the operation, and rethrows', async () => {
        const record = await saveOffline({ machineId: 5, condition: 'OKE' });

        vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new TypeError('Failed to fetch')));

        await expect(processQueuedOperation(record.operation_uuid)).rejects.toThrow();

        expect((await Queue.getByUuid(record.operation_uuid)).status).toBe(Queue.QueueStatus.FAILED);
    });

    it('sends the exact envelope shape to /api/sync', async () => {
        const record = await saveOffline({ machineId: 7, condition: 'KRITIS' });
        const fetchSpy = vi.fn().mockResolvedValue({ json: async () => ({ status: 'processed' }) });

        vi.stubGlobal('fetch', fetchSpy);

        await processQueuedOperation(record.operation_uuid);

        const sentBody = JSON.parse(fetchSpy.mock.calls[0][1].body);

        expect(sentBody).toEqual({
            operation_uuid: record.operation_uuid,
            transaction_type: 'OIL_AUDIT_CREATE',
            payload: { machine_id: 7, condition: 'KRITIS' },
            expected_state: {},
        });
    });
});

describe('one machine-selection draft per user', () => {
    const m1 = { id: 1, machine_number: '3001' };
    const m2 = { id: 2, machine_number: '3002' };

    it('selecting a machine replaces the same user\'s earlier draft but never another user\'s', async () => {
        await Drafts.saveDraft({ draftType: 'OIL_AUDIT', referenceId: 9, payload: { id: 9 }, userId: 'other' });

        await selectMachineDraft(m1, 'me');
        await selectMachineDraft(m2, 'me');

        const mine = await Drafts.listDraftsByType('me', 'OIL_AUDIT');

        expect(mine.map((d) => d.reference_id)).toEqual([2]);
        expect(await Drafts.listDraftsByType('other', 'OIL_AUDIT')).toHaveLength(1);
    });

    it('discarding the draft does not touch queued transactions or other users\' drafts', async () => {
        await Drafts.saveDraft({ draftType: 'OIL_AUDIT', referenceId: 9, payload: { id: 9 }, userId: 'other' });
        await selectMachineDraft(m1, 'me');
        const queued = await saveOffline({ machineId: 1, condition: 'OKE' });

        await discardMachineDraft('me');

        expect(await Drafts.listDraftsByType('me', 'OIL_AUDIT')).toHaveLength(0);
        expect(await Drafts.listDraftsByType('other', 'OIL_AUDIT')).toHaveLength(1);
        expect(await Queue.getByUuid(queued.operation_uuid)).toBeTruthy();
    });

    it('restore returns the latest draft, and nothing once discarded', async () => {
        await selectMachineDraft(m1, 'me');
        expect((await restoreMachineDraft('me')).payload).toEqual(m1);

        await discardMachineDraft('me');
        expect(await restoreMachineDraft('me')).toBeNull();
    });

    it('restore drops a stale draft whose machine was already audited today', async () => {
        await selectMachineDraft(m1, 'me');
        await seedAuditedToday({ date: businessDate(), machine_ids: [1] });

        expect(await restoreMachineDraft('me')).toBeNull();
        expect(await Drafts.listDraftsByType('me', 'OIL_AUDIT')).toHaveLength(0);
    });
});

describe('one audit per machine per business date (offline guard)', () => {
    afterEach(() => {
        vi.useRealTimers();
    });

    it('businessDate uses Asia/Jakarta: 17:30Z on the 8th is already the 9th in Jakarta', () => {
        expect(businessDate('2026-10-08T16:59:00Z')).toBe('2026-10-08');
        expect(businessDate('2026-10-08T17:00:00Z')).toBe('2026-10-09');
    });

    it('a pending queued audit for the same machine/date blocks a second one and leaves the first untouched', async () => {
        vi.useFakeTimers({ toFake: ['Date'] });
        vi.setSystemTime(new Date('2026-10-08T03:00:00Z'));

        const first = await saveOffline({ machineId: 1, condition: 'OKE' });

        await expect(saveOffline({ machineId: 1, condition: 'KRITIS' })).rejects.toBeInstanceOf(OilAuditDuplicateError);

        const all = await Queue.listAll();

        expect(all).toHaveLength(1);
        expect(all[0]).toMatchObject({ operation_uuid: first.operation_uuid, payload: { machine_id: 1, condition: 'OKE' } });
    });

    it.each(['syncing', 'failed', 'conflict', 'synced'])('a %s audit for the same machine/date also blocks', async (status) => {
        vi.useFakeTimers({ toFake: ['Date'] });
        vi.setSystemTime(new Date('2026-10-08T03:00:00Z'));
        const first = await saveOffline({ machineId: 1, condition: 'OKE' });
        const store = { syncing: Queue.markSyncing, failed: (u) => Queue.markFailed(u, 'x'), conflict: (u) => Queue.markConflict(u, { status: 'conflict' }), synced: (u) => Queue.markSynced(u, {}) };

        await store[status](first.operation_uuid);

        await expect(saveOffline({ machineId: 1, condition: 'OKE' })).rejects.toBeInstanceOf(OilAuditDuplicateError);
    });

    it('a server-rejected duplicate (duplicate_audit) represents no audit and does not itself block', async () => {
        vi.useFakeTimers({ toFake: ['Date'] });
        vi.setSystemTime(new Date('2026-10-08T03:00:00Z'));
        const first = await saveOffline({ machineId: 1, condition: 'OKE' });

        await Queue.markConflict(first.operation_uuid, { status: 'duplicate_audit', message: 'dup' });

        expect(await findExistingAudit(1)).toBeNull();
    });

    it('same machine on the next day, and another machine on the same day, are allowed', async () => {
        vi.useFakeTimers({ toFake: ['Date'] });
        vi.setSystemTime(new Date('2026-10-08T03:00:00Z'));
        const first = await saveOffline({ machineId: 1, condition: 'OKE' });
        await Queue.markSynced(first.operation_uuid, {});

        const second = await saveOffline({ machineId: 2, condition: 'OKE' });
        await Queue.markSynced(second.operation_uuid, {});

        vi.setSystemTime(new Date('2026-10-09T03:00:00Z'));
        await expect(saveOffline({ machineId: 1, condition: 'OKE' })).resolves.toBeTruthy();
    });

    it('the cached server snapshot ("audited today" by another device) blocks, only for that date', async () => {
        vi.useFakeTimers({ toFake: ['Date'] });
        vi.setSystemTime(new Date('2026-10-08T03:00:00Z'));
        await seedAuditedToday({ date: '2026-10-08', machine_ids: [1] });

        await expect(saveOffline({ machineId: 1, condition: 'OKE' })).rejects.toBeInstanceOf(OilAuditDuplicateError);

        vi.setSystemTime(new Date('2026-10-09T03:00:00Z'));
        await expect(saveOffline({ machineId: 1, condition: 'OKE' })).resolves.toBeTruthy();
    });
});

describe('server duplicate rejection during sync', () => {
    it('duplicate_audit is kept as a non-retryable conflict with the server message — not failed, not deleted', async () => {
        const record = await saveOffline({ machineId: 5, condition: 'OKE' });

        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({
            json: async () => ({ status: 'duplicate_audit', message: 'Mesin 5 sudah memiliki audit oli untuk hari ini.' }),
        }));

        await processQueuedOperation(record.operation_uuid);

        const stored = await Queue.getByUuid(record.operation_uuid);

        expect(stored.status).toBe(Queue.QueueStatus.CONFLICT);
        expect(stored.result.message).toContain('sudah memiliki audit oli');
    });

    it('is not auto-retried: a second drain sends nothing, and a network error stays retryable with the same uuid/payload', async () => {
        const dup = await saveOffline({ machineId: 5, condition: 'OKE' });
        const fetchSpy = vi.fn().mockResolvedValue({ json: async () => ({ status: 'duplicate_audit', message: 'dup' }) });

        vi.stubGlobal('fetch', fetchSpy);
        await SyncEngine.syncPendingOperations();
        await SyncEngine.syncPendingOperations();
        expect(fetchSpy).toHaveBeenCalledTimes(1);
        expect((await Queue.getByUuid(dup.operation_uuid)).status).toBe(Queue.QueueStatus.CONFLICT);

        // independent audit with a network failure stays failed/retryable, same uuid + payload
        const other = await saveOffline({ machineId: 6, condition: 'PANTAU' });

        vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new TypeError('Failed to fetch')));
        await SyncEngine.syncPendingOperations();

        const stored = await Queue.getByUuid(other.operation_uuid);

        expect(stored).toMatchObject({ status: 'failed', last_error_status: 'network_error', payload: { machine_id: 6, condition: 'PANTAU' } });
    });

    it('one duplicate does not stop an independent audit in the same drain', async () => {
        const dup = await saveOffline({ machineId: 5, condition: 'OKE' });
        const ok = await saveOffline({ machineId: 5, condition: 'OKE' }).catch(() => null);

        expect(ok).toBeNull(); // blocked locally; use a distinct machine instead
        const other = await Queue.enqueue({ transactionType: 'OIL_AUDIT_CREATE', payload: { machine_id: 6, condition: 'OKE' }, userId: null });

        vi.stubGlobal('fetch', vi.fn().mockImplementation(async (url, init) => {
            const { operation_uuid: uuid } = JSON.parse(init.body);

            return { json: async () => (uuid === dup.operation_uuid ? { status: 'duplicate_audit', message: 'dup' } : { status: 'processed', subject_id: 1 }) };
        }));

        await SyncEngine.syncPendingOperations();

        expect((await Queue.getByUuid(dup.operation_uuid)).status).toBe('conflict');
        expect((await Queue.getByUuid(other.operation_uuid)).status).toBe('synced');
    });
});
