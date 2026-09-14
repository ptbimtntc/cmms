/**
 * FreeDOMS offline-first — local draft storage (Phase 1, Task 3).
 *
 * A draft is a form still being filled in — NOT final, NOT yet a
 * transaction (see Task 3 section 14). It is deliberately a separate
 * concept and a separate IndexedDB store from the sync queue (queue.js):
 * a draft only ever becomes a queue entry later (a future task's
 * concern), and nothing here writes to STORES.SYNC_QUEUE.
 *
 * `draft_type` is a free-form string a later task will use for values
 * like "PM", "PM_CHECKLIST", "OIL_AUDIT", "OIL_AUDIT_FOLLOW_UP" — this
 * module does not validate or special-case any of them, and nothing here
 * activates that workflow.
 */

import { OfflineStorage, OfflineStorageError, STORES } from './db.js';
import { generateUuid } from './uuid.js';

/**
 * Creates a new draft, or updates the existing one for the same
 * (userId, draftType, referenceId) — this is what makes repeated
 * autosave/manual-save calls update in place instead of piling up
 * duplicate drafts (Task 3 section 15). `referenceId` identifies what the
 * draft is FOR (e.g. a PM Schedule's server id); use any stable
 * placeholder string for a draft that has no server id yet.
 *
 * @param {{
 *   draftType: string,
 *   referenceId: string|number,
 *   payload: object,
 *   userId: string|null,
 * }} draft
 * @returns {Promise<object>} the stored draft record.
 */
export async function saveDraft({ draftType, referenceId, payload, userId = null }) {
    if (!draftType) {
        throw new OfflineStorageError('saveDraft() requires a draftType.');
    }

    if (referenceId === undefined || referenceId === null || referenceId === '') {
        throw new OfflineStorageError('saveDraft() requires a stable referenceId.');
    }

    const existing = await getDraftByReference({ userId, draftType, referenceId });
    const now = new Date().toISOString();

    const record = existing
        ? { ...existing, payload, updated_at: now }
        : {
            draft_id: generateUuid(),
            draft_type: draftType,
            reference_id: referenceId,
            payload,
            user_id: userId,
            created_at: now,
            updated_at: now,
        };

    await OfflineStorage.put(STORES.DRAFTS, record);

    return record;
}

export function getDraft(draftId) {
    return OfflineStorage.get(STORES.DRAFTS, draftId);
}

/**
 * @param {{userId: string|null, draftType: string, referenceId: string|number}} lookup
 * @returns {Promise<object|undefined>}
 */
export async function getDraftByReference({ userId = null, draftType, referenceId }) {
    const matches = await OfflineStorage.getAll(STORES.DRAFTS, {
        indexName: 'by_user_type_reference',
        query: [userId, draftType, referenceId],
    });

    return matches[0];
}

export function listDraftsByType(userId, draftType) {
    return OfflineStorage.getAll(STORES.DRAFTS, { indexName: 'by_type', query: draftType }).then(
        (drafts) => drafts.filter((draft) => draft.user_id === userId)
    );
}

export function deleteDraft(draftId) {
    return OfflineStorage.delete(STORES.DRAFTS, draftId);
}
