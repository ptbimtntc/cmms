/**
 * Task 3 section 17/25 — a device can be shared by more than one user.
 * These tests exercise queue.js and drafts.js together to prove records
 * scoped to different user_id values never bleed into each other's
 * listings, even though they live in the SAME IndexedDB database/store.
 */
import { beforeEach, describe, expect, it } from 'vitest';
import * as Drafts from '../drafts.js';
import * as Queue from '../queue.js';
import { resetTestDatabase } from './helpers.js';

beforeEach(async () => {
    await resetTestDatabase();
});

describe('drafts scope isolation between users sharing a device', () => {
    it('two users can each have their own draft for the SAME draft_type + reference_id without colliding', async () => {
        await Drafts.saveDraft({ draftType: 'PM', referenceId: 101, payload: { note: 'from A' }, userId: 'user-A' });
        await Drafts.saveDraft({ draftType: 'PM', referenceId: 101, payload: { note: 'from B' }, userId: 'user-B' });

        const draftA = await Drafts.getDraftByReference({ userId: 'user-A', draftType: 'PM', referenceId: 101 });
        const draftB = await Drafts.getDraftByReference({ userId: 'user-B', draftType: 'PM', referenceId: 101 });

        expect(draftA.payload).toEqual({ note: 'from A' });
        expect(draftB.payload).toEqual({ note: 'from B' });
        expect(draftA.draft_id).not.toBe(draftB.draft_id);
    });

    it('listDraftsByType() for user A never includes user B\'s drafts', async () => {
        await Drafts.saveDraft({ draftType: 'PM', referenceId: 1, payload: {}, userId: 'user-A' });
        await Drafts.saveDraft({ draftType: 'PM', referenceId: 2, payload: {}, userId: 'user-A' });
        await Drafts.saveDraft({ draftType: 'PM', referenceId: 3, payload: {}, userId: 'user-B' });

        const aDrafts = await Drafts.listDraftsByType('user-A', 'PM');

        expect(aDrafts).toHaveLength(2);
        expect(aDrafts.every((d) => d.user_id === 'user-A')).toBe(true);
    });
});

describe('sync queue scope by user', () => {
    it('listByUser() only returns operations queued by that user', async () => {
        await Queue.enqueue({ operationUuid: 'a1', transactionType: 'PM_SAVE', payload: {}, userId: 'user-A' });
        await Queue.enqueue({ operationUuid: 'a2', transactionType: 'PM_SAVE', payload: {}, userId: 'user-A' });
        await Queue.enqueue({ operationUuid: 'b1', transactionType: 'PM_SAVE', payload: {}, userId: 'user-B' });

        const aOps = await Queue.listByUser('user-A');
        const bOps = await Queue.listByUser('user-B');

        expect(aOps.map((o) => o.operation_uuid).sort()).toEqual(['a1', 'a2']);
        expect(bOps.map((o) => o.operation_uuid)).toEqual(['b1']);
    });

    it('user_id is never guessed — a queue record with no userId given stores user_id: null rather than an arbitrary owner', async () => {
        const record = await Queue.enqueue({ operationUuid: 'anon-1', transactionType: 'PM_SAVE', payload: {} });

        expect(record.user_id).toBeNull();
    });
});
