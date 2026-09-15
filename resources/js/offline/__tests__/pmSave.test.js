// @vitest-environment jsdom
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import * as Queue from '../queue.js';
import * as Drafts from '../drafts.js';
import { getLocalPmSaveOverlay } from '../masterData.js';
import {
    PmSaveBlockedError,
    PmSaveValidationError,
    buildPayloadFromForm,
    parseFormToNestedObject,
    processQueuedOperation,
    saveOffline,
    validatePayloadLocally,
} from '../pmSave.js';
import { resetTestDatabase } from './helpers.js';

beforeEach(async () => {
    await resetTestDatabase();
    document.head.innerHTML = '<meta name="csrf-token" content="test-token">';
});

afterEach(() => {
    vi.unstubAllGlobals();
});

describe('parseFormToNestedObject', () => {
    it('parses flat fields as top-level keys', () => {
        const fd = new FormData();

        fd.append('order_number', 'ORD-1');
        fd.append('pic', 'Budi');

        expect(parseFormToNestedObject(fd)).toEqual({ order_number: 'ORD-1', pic: 'Budi' });
    });

    it('parses bracketed array-of-object fields into nested objects', () => {
        const fd = new FormData();

        fd.append('measurements[0][machine_measurement_id]', '5');
        fd.append('measurements[0][measurement_value]', '3.2');
        fd.append('measurements[1][machine_measurement_id]', '6');
        fd.append('measurements[1][measurement_value]', '4.1');

        expect(parseFormToNestedObject(fd)).toEqual({
            measurements: {
                0: { machine_measurement_id: '5', measurement_value: '3.2' },
                1: { machine_measurement_id: '6', measurement_value: '4.1' },
            },
        });
    });

    it('keeps a gap (a removed middle row) as a sparse-but-valid object, never losing the surviving rows', () => {
        // Mirrors pm/edit.js's removeProblem(): removing row 1 out of
        // [0,1,2] leaves DOM fields named problems[0][...] and
        // problems[2][...] with no [1] at all.
        const fd = new FormData();

        fd.append('problems[0][problem]', '10');
        fd.append('problems[2][problem]', '12');

        const parsed = parseFormToNestedObject(fd);

        expect(parsed.problems).toEqual({ 0: { problem: '10' }, 2: { problem: '12' } });
        // Critically: this must NOT be a JS Array — JSON.stringify would
        // turn the missing index 1 into `null` in the payload.
        expect(Array.isArray(parsed.problems)).toBe(false);
        expect(JSON.parse(JSON.stringify(parsed)).problems).toEqual({ 0: { problem: '10' }, 2: { problem: '12' } });
    });
});

describe('buildPayloadFromForm', () => {
    function makeForm(fields) {
        const form = document.createElement('form');

        Object.entries(fields).forEach(([name, value]) => {
            const input = document.createElement('input');

            input.name = name;
            input.value = value;
            form.appendChild(input);
        });

        return form;
    }

    it('only includes the PM_SAVE whitelist, dropping _token/_method/display-only fields', () => {
        const form = makeForm({
            _token: 'abc',
            _method: 'PUT',
            order_number: 'ORD-1',
            pic: 'Budi',
            completion_date_display: '2026-09-14',
            actual_date: '2026-09-14',
            start_time: '08:00',
        });

        const payload = buildPayloadFromForm(form);

        expect(payload).toEqual({
            order_number: 'ORD-1',
            pic: 'Budi',
            actual_date: '2026-09-14',
            start_time: '08:00',
        });
        expect(payload).not.toHaveProperty('_token');
        expect(payload).not.toHaveProperty('_method');
        expect(payload).not.toHaveProperty('completion_date_display');
    });

    it('never includes checklist fields, even if present on the form', () => {
        const form = makeForm({
            order_number: 'ORD-1',
            pic: 'Budi',
            actual_date: '2026-09-14',
            start_time: '08:00',
            'checklists[0][machine_checklist_id]': '1',
            'checklists[0][clean]': 'YES',
        });

        const payload = buildPayloadFromForm(form);

        expect(payload).not.toHaveProperty('checklists');
    });

    it('aggregates measurements/problems/spareparts/sessions into ONE payload object', () => {
        const form = makeForm({
            order_number: 'ORD-1',
            pic: 'Budi',
            'sessions[0][actual_date]': '2026-09-14',
            'sessions[0][start_time]': '08:00',
            'sessions[0][end_time]': '10:00',
            'measurements[0][machine_measurement_id]': '5',
            'measurements[0][measurement_value]': '3.2',
            'problems[0][problem]': '10',
            'problems[0][severity]': 'Low',
            'spareparts[0][sparepart_id]': '7',
            'spareparts[0][qty]': '2',
        });

        const payload = buildPayloadFromForm(form);

        expect(payload.sessions).toEqual({ 0: { actual_date: '2026-09-14', start_time: '08:00', end_time: '10:00' } });
        expect(payload.measurements).toEqual({ 0: { machine_measurement_id: '5', measurement_value: '3.2' } });
        expect(payload.problems).toEqual({ 0: { problem: '10', severity: 'Low' } });
        expect(payload.spareparts).toEqual({ 0: { sparepart_id: '7', qty: '2' } });
    });
});

describe('validatePayloadLocally', () => {
    it('passes for a complete legacy single-day payload', () => {
        expect(() => validatePayloadLocally({
            order_number: 'ORD-1', pic: 'Budi', actual_date: '2026-09-14', start_time: '08:00',
        })).not.toThrow();
    });

    it('passes for a complete multi-day (sessions) payload', () => {
        expect(() => validatePayloadLocally({
            order_number: 'ORD-1', pic: 'Budi',
            sessions: { 0: { actual_date: '2026-09-14', start_time: '08:00' } },
        })).not.toThrow();
    });

    it('rejects a missing order_number/pic', () => {
        try {
            validatePayloadLocally({ actual_date: '2026-09-14', start_time: '08:00' });
            expect.unreachable();
        } catch (error) {
            expect(error).toBeInstanceOf(PmSaveValidationError);
            expect(error.errors).toHaveProperty('order_number');
            expect(error.errors).toHaveProperty('pic');
        }
    });

    it('rejects a session row missing actual_date/start_time', () => {
        expect(() => validatePayloadLocally({
            order_number: 'ORD-1', pic: 'Budi', sessions: { 0: { actual_date: '', start_time: '' } },
        })).toThrow(PmSaveValidationError);
    });

    it('rejects a sparepart row with a chosen sparepart but an invalid qty', () => {
        expect(() => validatePayloadLocally({
            order_number: 'ORD-1', pic: 'Budi', actual_date: '2026-09-14', start_time: '08:00',
            spareparts: { 0: { sparepart_id: '7', qty: '0' } },
        })).toThrow(PmSaveValidationError);
    });

    it('does not require qty when no sparepart was chosen for that row', () => {
        expect(() => validatePayloadLocally({
            order_number: 'ORD-1', pic: 'Budi', actual_date: '2026-09-14', start_time: '08:00',
            spareparts: { 0: { sparepart_id: '', qty: '' } },
        })).not.toThrow();
    });
});

describe('saveOffline', () => {
    const validPayload = { order_number: 'ORD-1', pic: 'Budi', actual_date: '2026-09-14', start_time: '08:00' };

    it('makes NO network request', async () => {
        const fetchSpy = vi.fn();

        vi.stubGlobal('fetch', fetchSpy);

        await saveOffline({ pmScheduleId: 1, payload: validPayload, expectedStatus: 'IN_PROGRESS' });

        expect(fetchSpy).not.toHaveBeenCalled();
    });

    it('enqueues ONE PM_SAVE operation with the aggregate payload, not one per child record', async () => {
        const payload = {
            ...validPayload,
            measurements: { 0: { machine_measurement_id: '5', measurement_value: '3.2' }, 1: { machine_measurement_id: '6', measurement_value: '4.1' } },
            problems: { 0: { problem: '10', severity: 'Low' } },
            spareparts: { 0: { sparepart_id: '7', qty: '2' } },
        };

        const record = await saveOffline({ pmScheduleId: 42, payload, expectedStatus: 'IN_PROGRESS' });

        expect(record.transaction_type).toBe('PM_SAVE');
        expect(record.payload.pm_schedule_id).toBe(42);
        expect(record.payload.measurements).toHaveProperty('1');
        expect(record.payload.problems).toHaveProperty('0');
        expect(record.payload.spareparts).toHaveProperty('0');
        expect(record.expected_state).toEqual({ status: 'IN_PROGRESS' });

        const all = await Queue.listAll();

        expect(all).toHaveLength(1);
    });

    it('never includes a "checklists" key in the enqueued payload', async () => {
        const record = await saveOffline({ pmScheduleId: 1, payload: validPayload, expectedStatus: 'OPEN' });

        expect(record.payload).not.toHaveProperty('checklists');
    });

    it('throws PmSaveValidationError and does NOT enqueue anything for an invalid payload', async () => {
        await expect(saveOffline({ pmScheduleId: 1, payload: { order_number: '' }, expectedStatus: 'OPEN' }))
            .rejects.toBeInstanceOf(PmSaveValidationError);

        expect(await Queue.listAll()).toHaveLength(0);
    });

    it('stores a local "saved offline" overlay', async () => {
        await saveOffline({ pmScheduleId: 42, payload: validPayload, expectedStatus: 'IN_PROGRESS' });

        const overlay = await getLocalPmSaveOverlay(42);

        expect(overlay.local_save_status).toBe('saved_offline');
        expect(overlay.pending_save_operation_uuid).toBeTruthy();
    });

    it('blocks a second offline Save for a DIFFERENT PM while one is still pending', async () => {
        await saveOffline({ pmScheduleId: 1, payload: validPayload, expectedStatus: 'OPEN' });

        await expect(saveOffline({ pmScheduleId: 2, payload: validPayload, expectedStatus: 'OPEN' }))
            .rejects.toBeInstanceOf(PmSaveBlockedError);

        expect(await Queue.listAll()).toHaveLength(1);
    });

    it('does not block once the earlier PM_SAVE is no longer pending/syncing', async () => {
        const first = await saveOffline({ pmScheduleId: 1, payload: validPayload, expectedStatus: 'OPEN' });

        await Queue.markSynced(first.operation_uuid);

        await expect(saveOffline({ pmScheduleId: 2, payload: validPayload, expectedStatus: 'OPEN' })).resolves.toBeTruthy();
    });
});

describe('processQueuedOperation', () => {
    const validPayload = { order_number: 'ORD-1', pic: 'Budi', actual_date: '2026-09-14', start_time: '08:00' };

    it('on "processed": marks synced, clears the local overlay, and deletes the matching draft', async () => {
        await Drafts.saveDraft({ draftType: 'PM', referenceId: 42, payload: validPayload, userId: null });
        const record = await saveOffline({ pmScheduleId: 42, payload: validPayload, expectedStatus: 'OPEN' });

        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ json: async () => ({ status: 'processed', subject_id: 42 }) }));

        await processQueuedOperation(record.operation_uuid);

        expect((await Queue.getByUuid(record.operation_uuid)).status).toBe(Queue.QueueStatus.SYNCED);
        expect(await getLocalPmSaveOverlay(42)).toBeNull();
        expect(await Drafts.getDraftByReference({ userId: null, draftType: 'PM', referenceId: 42 })).toBeUndefined();
    });

    it('on "conflict": marks conflict, keeps the overlay AND the draft (never force-resolved)', async () => {
        await Drafts.saveDraft({ draftType: 'PM', referenceId: 42, payload: validPayload, userId: null });
        const record = await saveOffline({ pmScheduleId: 42, payload: validPayload, expectedStatus: 'OPEN' });

        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ json: async () => ({ status: 'conflict', message: 'Server state has changed.' }) }));

        await processQueuedOperation(record.operation_uuid);

        expect((await Queue.getByUuid(record.operation_uuid)).status).toBe(Queue.QueueStatus.CONFLICT);
        expect(await getLocalPmSaveOverlay(42)).not.toBeNull();
        expect(await Drafts.getDraftByReference({ userId: null, draftType: 'PM', referenceId: 42 })).not.toBeUndefined();
    });

    it('sends the exact envelope shape to /api/sync', async () => {
        const record = await saveOffline({ pmScheduleId: 7, payload: validPayload, expectedStatus: 'OPEN' });
        const fetchSpy = vi.fn().mockResolvedValue({ json: async () => ({ status: 'processed' }) });

        vi.stubGlobal('fetch', fetchSpy);

        await processQueuedOperation(record.operation_uuid);

        const sentBody = JSON.parse(fetchSpy.mock.calls[0][1].body);

        expect(sentBody).toEqual({
            operation_uuid: record.operation_uuid,
            transaction_type: 'PM_SAVE',
            payload: record.payload,
            expected_state: record.expected_state,
        });
    });
});
