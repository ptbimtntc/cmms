import { beforeEach, describe, expect, it } from 'vitest';
import * as Drafts from '../drafts.js';
import { resetTestDatabase } from './helpers.js';

beforeEach(async () => {
    await resetTestDatabase();
});

describe('saveDraft', () => {
    it('creates a new draft', async () => {
        const draft = await Drafts.saveDraft({
            draftType: 'PM',
            referenceId: 101,
            payload: { remarks: 'in progress' },
            userId: 'user-1',
        });

        expect(draft.draft_id).toBeTruthy();
        expect(draft.draft_type).toBe('PM');
        expect(draft.reference_id).toBe(101);

        const stored = await Drafts.getDraft(draft.draft_id);

        expect(stored.payload).toEqual({ remarks: 'in progress' });
    });

    it('saving again for the SAME (user, type, reference) updates the existing draft instead of creating a new one', async () => {
        const first = await Drafts.saveDraft({ draftType: 'PM', referenceId: 101, payload: { step: 1 }, userId: 'user-1' });
        const second = await Drafts.saveDraft({ draftType: 'PM', referenceId: 101, payload: { step: 2 }, userId: 'user-1' });

        expect(second.draft_id).toBe(first.draft_id);

        const all = await Drafts.listDraftsByType('user-1', 'PM');

        expect(all).toHaveLength(1);
        expect(all[0].payload).toEqual({ step: 2 });
    });

    it('repeated autosave-style saves never pile up duplicate drafts', async () => {
        for (let i = 0; i < 5; i++) {
            await Drafts.saveDraft({ draftType: 'PM_CHECKLIST', referenceId: 7, payload: { i }, userId: 'user-1' });
        }

        const all = await Drafts.listDraftsByType('user-1', 'PM_CHECKLIST');

        expect(all).toHaveLength(1);
        expect(all[0].payload).toEqual({ i: 4 });
    });

    it('different reference_id values never overwrite each other', async () => {
        await Drafts.saveDraft({ draftType: 'PM', referenceId: 1, payload: { a: 1 }, userId: 'user-1' });
        await Drafts.saveDraft({ draftType: 'PM', referenceId: 2, payload: { a: 2 }, userId: 'user-1' });

        const all = await Drafts.listDraftsByType('user-1', 'PM');

        expect(all).toHaveLength(2);
    });

    it('different draft_type values for the same reference_id never overwrite each other', async () => {
        await Drafts.saveDraft({ draftType: 'PM', referenceId: 1, payload: { a: 'pm' }, userId: 'user-1' });
        await Drafts.saveDraft({ draftType: 'PM_CHECKLIST', referenceId: 1, payload: { a: 'checklist' }, userId: 'user-1' });

        const pm = await Drafts.getDraftByReference({ userId: 'user-1', draftType: 'PM', referenceId: 1 });
        const checklist = await Drafts.getDraftByReference({ userId: 'user-1', draftType: 'PM_CHECKLIST', referenceId: 1 });

        expect(pm.payload).toEqual({ a: 'pm' });
        expect(checklist.payload).toEqual({ a: 'checklist' });
    });
});

describe('deleteDraft', () => {
    it('removes the draft', async () => {
        const draft = await Drafts.saveDraft({ draftType: 'PM', referenceId: 1, payload: {}, userId: 'user-1' });

        await Drafts.deleteDraft(draft.draft_id);

        const stored = await Drafts.getDraft(draft.draft_id);

        expect(stored).toBeUndefined();
    });
});

describe('a null userId (no authenticated user known) never crashes draft lookups', () => {
    it('saveDraft + getDraftByReference work with userId: null, without throwing a DataError', async () => {
        const draft = await Drafts.saveDraft({ draftType: 'PM', referenceId: 101, payload: { a: 1 }, userId: null });

        expect(draft.user_id).toBeNull();

        const found = await Drafts.getDraftByReference({ userId: null, draftType: 'PM', referenceId: 101 });

        expect(found?.draft_id).toBe(draft.draft_id);
    });

    it('a repeat saveDraft() with userId: null updates in place rather than throwing or duplicating', async () => {
        const first = await Drafts.saveDraft({ draftType: 'PM', referenceId: 101, payload: { step: 1 }, userId: null });
        const second = await Drafts.saveDraft({ draftType: 'PM', referenceId: 101, payload: { step: 2 }, userId: null });

        expect(second.draft_id).toBe(first.draft_id);
        expect((await Drafts.listDraftsByType(null, 'PM'))).toHaveLength(1);
    });
});

describe('durability', () => {
    it('a draft survives a simulated page reload (connection reset)', async () => {
        const draft = await Drafts.saveDraft({ draftType: 'PM', referenceId: 55, payload: { x: 'y' }, userId: 'user-1' });

        const { _resetConnectionForTests } = await import('../db.js');

        _resetConnectionForTests();

        const stillThere = await Drafts.getDraft(draft.draft_id);

        expect(stillThere.payload).toEqual({ x: 'y' });
    });
});
