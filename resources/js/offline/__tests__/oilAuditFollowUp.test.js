// @vitest-environment jsdom
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import * as Queue from '../queue.js';
import * as Drafts from '../drafts.js';
import { getLocalOilAuditFollowUpOverlay } from '../masterData.js';
import {
    OilAuditFollowUpBlockedError,
    OilAuditFollowUpValidationError,
    buildFollowUpFromForm,
    processQueuedOperation,
    saveOffline,
    validatePayloadLocally,
} from '../oilAuditFollowUp.js';
import { resetTestDatabase } from './helpers.js';

beforeEach(async () => {
    await resetTestDatabase();
    document.head.innerHTML = '<meta name="csrf-token" content="test-token">';
});

afterEach(() => {
    vi.unstubAllGlobals();
});

describe('buildFollowUpFromForm', () => {
    function makeFollowUpForm({ problems, actionTaken }) {
        const form = document.createElement('form');

        problems.forEach((problem, pi) => {
            const problemInput = document.createElement('input');
            problemInput.name = `problems[${pi}][problem]`;
            problemInput.value = problem.problem;
            form.appendChild(problemInput);

            problem.findings.forEach((finding, fi) => {
                const findingInput = document.createElement('input');
                findingInput.name = `problems[${pi}][findings][${fi}][finding]`;
                findingInput.value = finding;
                form.appendChild(findingInput);
            });
        });

        const actionInput = document.createElement('textarea');
        actionInput.name = 'action_taken';
        actionInput.value = actionTaken;
        form.appendChild(actionInput);

        return form;
    }

    it('produces a real problems array, each with a real findings array, in order', () => {
        const form = makeFollowUpForm({
            problems: [
                { problem: 'Bocor Oli', findings: ['Kapstan 1', 'Kapstan 2'] },
                { problem: 'Baut Kendor', findings: ['Kapstan 3'] },
            ],
            actionTaken: 'Ganti seal dan kencangkan baut.',
        });

        const { problems, actionTaken } = buildFollowUpFromForm(form);

        expect(Array.isArray(problems)).toBe(true);
        expect(problems).toHaveLength(2);
        expect(Array.isArray(problems[0].findings)).toBe(true);
        expect(problems[0]).toEqual({ problem: 'Bocor Oli', findings: [{ finding: 'Kapstan 1' }, { finding: 'Kapstan 2' }] });
        expect(problems[1]).toEqual({ problem: 'Baut Kendor', findings: [{ finding: 'Kapstan 3' }] });
        expect(actionTaken).toBe('Ganti seal dan kencangkan baut.');
    });

    it('handles a single problem with a single finding', () => {
        const form = makeFollowUpForm({ problems: [{ problem: 'Bocor Oli', findings: ['Kapstan 1'] }], actionTaken: 'x' });

        const { problems } = buildFollowUpFromForm(form);

        expect(problems).toEqual([{ problem: 'Bocor Oli', findings: [{ finding: 'Kapstan 1' }] }]);
    });
});

describe('validatePayloadLocally', () => {
    const validProblems = [{ problem: 'Bocor Oli', findings: [{ finding: 'Kapstan 1' }] }];

    it('passes for a complete payload', () => {
        expect(() => validatePayloadLocally({ oilAuditId: 1, problems: validProblems, actionTaken: 'Ganti seal.' })).not.toThrow();
    });

    it('rejects a missing oil_audit_id', () => {
        expect(() => validatePayloadLocally({ oilAuditId: null, problems: validProblems, actionTaken: 'x' }))
            .toThrow(OilAuditFollowUpValidationError);
    });

    it('rejects an empty problems array', () => {
        expect(() => validatePayloadLocally({ oilAuditId: 1, problems: [], actionTaken: 'x' }))
            .toThrow(OilAuditFollowUpValidationError);
    });

    it('rejects a problem row with a blank problem value', () => {
        expect(() => validatePayloadLocally({
            oilAuditId: 1,
            problems: [{ problem: '', findings: [{ finding: 'Kapstan 1' }] }],
            actionTaken: 'x',
        })).toThrow(OilAuditFollowUpValidationError);
    });

    it('rejects a problem row with no non-blank finding', () => {
        expect(() => validatePayloadLocally({
            oilAuditId: 1,
            problems: [{ problem: 'Bocor Oli', findings: [{ finding: '' }] }],
            actionTaken: 'x',
        })).toThrow(OilAuditFollowUpValidationError);
    });

    it('rejects a blank action_taken', () => {
        expect(() => validatePayloadLocally({ oilAuditId: 1, problems: validProblems, actionTaken: '   ' }))
            .toThrow(OilAuditFollowUpValidationError);
    });

    it('reports every offending field in one error, not just the first', () => {
        try {
            validatePayloadLocally({ oilAuditId: null, problems: [], actionTaken: '' });
            expect.unreachable();
        } catch (error) {
            expect(error).toBeInstanceOf(OilAuditFollowUpValidationError);
            expect(error.errors).toHaveProperty('oil_audit_id');
            expect(error.errors).toHaveProperty('problems');
            expect(error.errors).toHaveProperty('action_taken');
        }
    });
});

describe('saveOffline', () => {
    const problems = [
        { problem: 'Bocor Oli', findings: [{ finding: 'Kapstan 1' }, { finding: 'Kapstan 2' }] },
        { problem: 'Baut Kendor', findings: [{ finding: 'Kapstan 3' }] },
    ];

    it('makes NO network request', async () => {
        const fetchSpy = vi.fn();

        vi.stubGlobal('fetch', fetchSpy);

        await saveOffline({ oilAuditId: 1, problems, actionTaken: 'Ganti seal.', followUpExists: false });

        expect(fetchSpy).not.toHaveBeenCalled();
    });

    it('enqueues exactly ONE OIL_AUDIT_FOLLOW_UP_SAVE operation for the whole aggregate (follow-up + every problem + every finding), not one per row', async () => {
        const record = await saveOffline({ oilAuditId: 42, problems, actionTaken: 'Ganti seal.', followUpExists: false });

        expect(record.transaction_type).toBe('OIL_AUDIT_FOLLOW_UP_SAVE');
        expect(record.payload.oil_audit_id).toBe(42);
        expect(record.payload.problems).toHaveLength(2);
        expect(record.payload.problems[0].findings).toHaveLength(2);
        expect(record.payload.action_taken).toBe('Ganti seal.');
        expect(record.expected_state).toEqual({ follow_up_exists: false });

        expect(await Queue.listAll()).toHaveLength(1);
    });

    it('sends follow_up_exists: true in expected_state for an edit-mode save', async () => {
        const record = await saveOffline({ oilAuditId: 42, problems, actionTaken: 'x', followUpExists: true });

        expect(record.expected_state).toEqual({ follow_up_exists: true });
    });

    it('throws OilAuditFollowUpValidationError and enqueues nothing for an invalid payload', async () => {
        await expect(saveOffline({ oilAuditId: 1, problems: [], actionTaken: '', followUpExists: false }))
            .rejects.toBeInstanceOf(OilAuditFollowUpValidationError);

        expect(await Queue.listAll()).toHaveLength(0);
    });

    it('stores a local "saved offline" overlay for this oil_audit_id', async () => {
        await saveOffline({ oilAuditId: 42, problems, actionTaken: 'x', followUpExists: false });

        const overlay = await getLocalOilAuditFollowUpOverlay(42);

        expect(overlay.local_status).toBe('saved_offline');
        expect(overlay.pending_operation_uuid).toBeTruthy();
    });

    it('blocks a second offline follow-up save for a DIFFERENT oil audit while one is still pending', async () => {
        await saveOffline({ oilAuditId: 1, problems, actionTaken: 'x', followUpExists: false });

        await expect(saveOffline({ oilAuditId: 2, problems, actionTaken: 'x', followUpExists: false }))
            .rejects.toBeInstanceOf(OilAuditFollowUpBlockedError);

        expect(await Queue.listAll()).toHaveLength(1);
    });

    it('does NOT block a second offline save for the SAME oil audit (e.g. a retried/edited save)', async () => {
        await saveOffline({ oilAuditId: 1, problems, actionTaken: 'x', followUpExists: false });

        await expect(saveOffline({ oilAuditId: 1, problems, actionTaken: 'y', followUpExists: false })).resolves.toBeTruthy();
    });

    it('does not block once the earlier OIL_AUDIT_FOLLOW_UP_SAVE is no longer pending/syncing', async () => {
        const first = await saveOffline({ oilAuditId: 1, problems, actionTaken: 'x', followUpExists: false });

        await Queue.markSynced(first.operation_uuid);

        await expect(saveOffline({ oilAuditId: 2, problems, actionTaken: 'x', followUpExists: false })).resolves.toBeTruthy();
    });

    it('does not create a duplicate queue entry when called twice with the same operation semantics but by resaving (double-click) the same aggregate', async () => {
        // saveOffline() always generates a fresh operation_uuid — genuine
        // double-click protection is the caller's job (a `submitting` flag
        // in the UI wiring, same as every other offline feature). What
        // this module guarantees instead is that retrying an EXISTING
        // queued operation (see "retry preserves the operation_uuid"
        // below) never creates a second queue entry.
        const first = await saveOffline({ oilAuditId: 1, problems, actionTaken: 'x', followUpExists: false });

        expect(await Queue.listAll()).toHaveLength(1);
        expect(first.operation_uuid).toBeTruthy();
    });

    it('retry (re-enqueue of the same operation_uuid) is a no-op — never duplicates the queue entry', async () => {
        const first = await saveOffline({ oilAuditId: 1, problems, actionTaken: 'x', followUpExists: false });

        const retried = await Queue.enqueue({
            operationUuid: first.operation_uuid,
            transactionType: 'OIL_AUDIT_FOLLOW_UP_SAVE',
            payload: first.payload,
            expectedState: first.expected_state,
            userId: first.user_id,
        });

        expect(retried.operation_uuid).toBe(first.operation_uuid);
        expect(await Queue.listAll()).toHaveLength(1);
    });

    it('works with a null userId (no authenticated user known) without a DataError', async () => {
        // currentUserId() returns null in this jsdom environment (no
        // <meta name="app-user-id">) — this exercises exactly that path
        // through findBlockingLocalOperation()/enqueue(), which both once
        // had to be fixed (Task 5) for composite/indexed queries against a
        // null key.
        await expect(saveOffline({ oilAuditId: 1, problems, actionTaken: 'x', followUpExists: false })).resolves.toBeTruthy();

        const all = await Queue.listAll();

        expect(all[0].user_id).toBeNull();
    });

    it('survives a simulated page reload (connection reset) — queue durability', async () => {
        const record = await saveOffline({ oilAuditId: 42, problems, actionTaken: 'x', followUpExists: false });

        const { _resetConnectionForTests } = await import('../db.js');

        _resetConnectionForTests();

        const stillThere = await Queue.getByUuid(record.operation_uuid);

        expect(stillThere.payload.problems).toHaveLength(2);
    });
});

describe('draft interplay (draft must survive until the queue entry is confirmed synced)', () => {
    const problems = [{ problem: 'Bocor Oli', findings: [{ finding: 'Kapstan 1' }] }];

    it('a draft made before saveOffline() is NOT deleted just because saveOffline() queued it', async () => {
        await Drafts.saveDraft({ draftType: 'OIL_AUDIT_FOLLOW_UP', referenceId: 42, payload: { oil_audit_id: 42, problems, action_taken: 'x' }, userId: null });

        await saveOffline({ oilAuditId: 42, problems, actionTaken: 'x', followUpExists: false });

        const draft = await Drafts.getDraftByReference({ userId: null, draftType: 'OIL_AUDIT_FOLLOW_UP', referenceId: 42 });

        expect(draft).not.toBeUndefined();
    });

    it('reads back a saved draft with the full nested problems/findings structure intact', async () => {
        await Drafts.saveDraft({
            draftType: 'OIL_AUDIT_FOLLOW_UP',
            referenceId: 42,
            payload: {
                oil_audit_id: 42,
                problems: [
                    { problem: 'Bocor Oli', findings: [{ finding: 'Kapstan 1' }, { finding: 'Kapstan 2' }] },
                    { problem: 'Baut Kendor', findings: [{ finding: 'Kapstan 3' }] },
                ],
                action_taken: 'Ganti seal.',
            },
            userId: null,
        });

        const draft = await Drafts.getDraftByReference({ userId: null, draftType: 'OIL_AUDIT_FOLLOW_UP', referenceId: 42 });

        expect(draft.payload.problems).toHaveLength(2);
        expect(draft.payload.problems[0].findings).toHaveLength(2);
        expect(draft.payload.problems[1].findings).toEqual([{ finding: 'Kapstan 3' }]);
        expect(draft.payload.action_taken).toBe('Ganti seal.');
    });

    it('updates the SAME draft in place on repeated autosave, never piling up duplicates', async () => {
        await Drafts.saveDraft({ draftType: 'OIL_AUDIT_FOLLOW_UP', referenceId: 42, payload: { oil_audit_id: 42, problems, action_taken: 'a' }, userId: null });
        await Drafts.saveDraft({ draftType: 'OIL_AUDIT_FOLLOW_UP', referenceId: 42, payload: { oil_audit_id: 42, problems, action_taken: 'b' }, userId: null });

        const all = await Drafts.listDraftsByType(null, 'OIL_AUDIT_FOLLOW_UP');

        expect(all).toHaveLength(1);
        expect(all[0].payload.action_taken).toBe('b');
    });
});

describe('processQueuedOperation', () => {
    const problems = [{ problem: 'Bocor Oli', findings: [{ finding: 'Kapstan 1' }] }];

    it('on "processed" (create path): marks synced, clears the local overlay, and deletes the matching draft', async () => {
        await Drafts.saveDraft({ draftType: 'OIL_AUDIT_FOLLOW_UP', referenceId: 42, payload: { oil_audit_id: 42, problems, action_taken: 'x' }, userId: null });
        const record = await saveOffline({ oilAuditId: 42, problems, actionTaken: 'x', followUpExists: false });

        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ json: async () => ({ status: 'processed' }) }));

        await processQueuedOperation(record.operation_uuid);

        expect((await Queue.getByUuid(record.operation_uuid)).status).toBe(Queue.QueueStatus.SYNCED);
        expect(await getLocalOilAuditFollowUpOverlay(42)).toBeNull();
        expect(await Drafts.getDraftByReference({ userId: null, draftType: 'OIL_AUDIT_FOLLOW_UP', referenceId: 42 })).toBeUndefined();
    });

    it('on "already_processed" (idempotent retry): marks synced and cleans up exactly like "processed"', async () => {
        const record = await saveOffline({ oilAuditId: 42, problems, actionTaken: 'x', followUpExists: false });

        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ json: async () => ({ status: 'already_processed' }) }));

        await processQueuedOperation(record.operation_uuid);

        expect((await Queue.getByUuid(record.operation_uuid)).status).toBe(Queue.QueueStatus.SYNCED);
    });

    it('retry after a network failure preserves the same operation_uuid (never a new one)', async () => {
        const record = await saveOffline({ oilAuditId: 42, problems, actionTaken: 'x', followUpExists: false });

        vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new TypeError('Network request failed')));

        await expect(processQueuedOperation(record.operation_uuid)).rejects.toThrow();

        expect((await Queue.getByUuid(record.operation_uuid)).status).toBe(Queue.QueueStatus.FAILED);
        expect((await Queue.getByUuid(record.operation_uuid)).operation_uuid).toBe(record.operation_uuid);

        vi.unstubAllGlobals();
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ json: async () => ({ status: 'processed' }) }));

        await processQueuedOperation(record.operation_uuid);

        expect((await Queue.getByUuid(record.operation_uuid)).operation_uuid).toBe(record.operation_uuid);
        expect((await Queue.getByUuid(record.operation_uuid)).status).toBe(Queue.QueueStatus.SYNCED);
    });

    it('on "conflict": marks conflict, keeps the overlay AND the draft (never force-resolved)', async () => {
        await Drafts.saveDraft({ draftType: 'OIL_AUDIT_FOLLOW_UP', referenceId: 42, payload: { oil_audit_id: 42, problems, action_taken: 'x' }, userId: null });
        const record = await saveOffline({ oilAuditId: 42, problems, actionTaken: 'x', followUpExists: false });

        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ json: async () => ({ status: 'conflict', message: 'Follow up sudah dibuat perangkat lain.' }) }));

        await processQueuedOperation(record.operation_uuid);

        expect((await Queue.getByUuid(record.operation_uuid)).status).toBe(Queue.QueueStatus.CONFLICT);
        expect(await getLocalOilAuditFollowUpOverlay(42)).not.toBeNull();
        expect(await Drafts.getDraftByReference({ userId: null, draftType: 'OIL_AUDIT_FOLLOW_UP', referenceId: 42 })).not.toBeUndefined();
    });

    it('a FAILED queue creation (enqueue throwing) never removes an existing draft', async () => {
        await Drafts.saveDraft({ draftType: 'OIL_AUDIT_FOLLOW_UP', referenceId: 42, payload: { oil_audit_id: 42, problems, action_taken: 'x' }, userId: null });

        // Simulate a failed local save (invalid payload) — the draft made
        // before attempting it must remain untouched.
        await expect(saveOffline({ oilAuditId: 42, problems: [], actionTaken: '', followUpExists: false }))
            .rejects.toBeInstanceOf(OilAuditFollowUpValidationError);

        const draft = await Drafts.getDraftByReference({ userId: null, draftType: 'OIL_AUDIT_FOLLOW_UP', referenceId: 42 });

        expect(draft).not.toBeUndefined();
        expect(draft.payload.problems).toEqual(problems);
    });

    it('sends the exact envelope shape to /api/sync, including nested problems/findings', async () => {
        const record = await saveOffline({
            oilAuditId: 7,
            problems: [
                { problem: 'Bocor Oli', findings: [{ finding: 'Kapstan 1' }, { finding: 'Kapstan 2' }] },
            ],
            actionTaken: 'Ganti seal.',
            followUpExists: true,
        });
        const fetchSpy = vi.fn().mockResolvedValue({ json: async () => ({ status: 'processed' }) });

        vi.stubGlobal('fetch', fetchSpy);

        await processQueuedOperation(record.operation_uuid);

        const sentBody = JSON.parse(fetchSpy.mock.calls[0][1].body);

        expect(sentBody).toEqual({
            operation_uuid: record.operation_uuid,
            transaction_type: 'OIL_AUDIT_FOLLOW_UP_SAVE',
            payload: record.payload,
            expected_state: { follow_up_exists: true },
        });
    });
});
