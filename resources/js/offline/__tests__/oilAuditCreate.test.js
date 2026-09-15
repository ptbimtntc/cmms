// @vitest-environment jsdom
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import * as Queue from '../queue.js';
import * as Drafts from '../drafts.js';
import { putMasterData } from '../masterData.js';
import {
    MACHINE_CATEGORY,
    OilAuditBlockedError,
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

        const second = await saveOffline({ machineId: 5, condition: 'PANTAU' });

        expect(second.operation_uuid).not.toBe(first.operation_uuid);
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
