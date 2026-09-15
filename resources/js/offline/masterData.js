/**
 * FreeDOMS offline-first — master-data cache foundation (Phase 1, Task 3).
 *
 * Storage SHAPE only — this module does not fetch or auto-download
 * anything (Task 3 section 16 explicitly forbids that). A later task
 * decides which categories to download, when to refresh them, and which
 * ones are safe offline; this file just gives that future code somewhere
 * to put/read cached records without touching IndexedDB directly.
 *
 * Two stores are used (see db.js):
 *  - MASTER_DATA: generic reference data, one row per (category, id) —
 *    machines, measurement_parameters, checklist_items, problem
 *    categories/findings, spareparts, PIC lists, etc. (Task 3 section 16's
 *    example categories). `cache_key` is `${category}:${id}` so every
 *    category shares one store instead of needing a new object store (and
 *    a schema version bump) for each new category a later task adds.
 *  - PM_SCHEDULES: kept separate because it needs PIC/area-scoped
 *    queries a generic keyed cache doesn't support well.
 */

import { OfflineStorage, STORES } from './db.js';

function cacheKey(category, id) {
    return `${category}:${id}`;
}

/**
 * @param {string} category e.g. "machines", "spareparts"
 * @param {string|number} id server-side id of the cached record
 * @param {object} data the record itself
 */
export function putMasterData(category, id, data) {
    return OfflineStorage.put(STORES.MASTER_DATA, { cache_key: cacheKey(category, id), category, id, data });
}

export async function getMasterData(category, id) {
    const record = await OfflineStorage.get(STORES.MASTER_DATA, cacheKey(category, id));

    return record?.data;
}

export async function getMasterDataByCategory(category) {
    const records = await OfflineStorage.getAll(STORES.MASTER_DATA, { indexName: 'by_category', query: category });

    return records.map((record) => record.data);
}

/**
 * Removes every cached record in one category (e.g. before writing a
 * fresh download) without touching any other category's cache.
 */
export function clearMasterDataCategory(category) {
    return OfflineStorage.transaction(STORES.MASTER_DATA, 'readwrite', (tx) => {
        const index = tx.objectStore(STORES.MASTER_DATA).index('by_category');
        const request = index.openCursor(IDBKeyRange.only(category));

        request.onsuccess = () => {
            const cursor = request.result;

            if (cursor) {
                cursor.delete();
                cursor.continue();
            }
        };
    });
}

export function putPmSchedule(record) {
    if (record?.id === undefined || record?.id === null) {
        throw new Error('putPmSchedule() requires a record with an id.');
    }

    return OfflineStorage.put(STORES.PM_SCHEDULES, record);
}

export function getPmSchedule(id) {
    return OfflineStorage.get(STORES.PM_SCHEDULES, id);
}

export function getPmSchedulesByPic(pic) {
    return OfflineStorage.getAll(STORES.PM_SCHEDULES, { indexName: 'by_pic', query: pic });
}

export function clearPmSchedules() {
    return OfflineStorage.clear(STORES.PM_SCHEDULES);
}

/**
 * "Local PM state" (Task 4 section 8) — a small, offline-originated
 * OVERLAY on top of whatever PM_SCHEDULES record this device has cached
 * (which may not have one at all yet; a later task decides when full PM
 * records get downloaded). Every field is prefixed `local_` (plus
 * `pending_operation_uuid`) precisely so it can never collide with, or be
 * silently overwritten by, real server fields (`status`, `actual_date`,
 * `start_time`, ...) once a later task starts caching those too.
 *
 * This is intentionally NOT the sync queue: the queue (queue.js) is the
 * transient list of operations still to be sent; this overlay is what the
 * PM Schedule page reads to keep showing "Started (offline)" for a PM
 * across reloads, independently of the queue entry's own lifecycle.
 *
 * @param {string|number} id
 * @param {object} overlay fields to merge onto the existing record (if any)
 */
export async function setLocalPmOverlay(id, overlay) {
    const existing = (await OfflineStorage.get(STORES.PM_SCHEDULES, id)) ?? { id };
    const updated = { ...existing, ...overlay, id };

    await OfflineStorage.put(STORES.PM_SCHEDULES, updated);

    return updated;
}

export async function getLocalPmOverlay(id) {
    const record = await OfflineStorage.get(STORES.PM_SCHEDULES, id);

    if (!record?.local_status) {
        return null;
    }

    return record;
}

/**
 * Removes only the overlay fields, leaving any cached server record (a
 * later task's concern) intact.
 */
export async function clearLocalPmOverlay(id) {
    const existing = await OfflineStorage.get(STORES.PM_SCHEDULES, id);

    if (!existing) {
        return;
    }

    const { local_status, local_actual_date, local_start_time, pending_operation_uuid, local_updated_at, ...rest } = existing;

    await OfflineStorage.put(STORES.PM_SCHEDULES, rest);
}

/**
 * Same idea as setLocalPmOverlay/getLocalPmOverlay/clearLocalPmOverlay
 * above, but for PM_SAVE (Task 5) — kept as a SEPARATE field namespace
 * (`local_save_*` / `pending_save_operation_uuid`, never touching
 * `local_status` etc.) rather than extending the Task 4 fields, so a PM
 * can independently show "started offline" AND "saved offline, waiting
 * for sync" at once (a PIC can offline-Start a PM, then also offline-Save
 * its Fill PM form, before either one has synced), and so Task 4's
 * existing behavior/tests are untouched.
 */
export async function setLocalPmSaveOverlay(id, overlay) {
    const existing = (await OfflineStorage.get(STORES.PM_SCHEDULES, id)) ?? { id };
    const updated = { ...existing, ...overlay, id };

    await OfflineStorage.put(STORES.PM_SCHEDULES, updated);

    return updated;
}

export async function getLocalPmSaveOverlay(id) {
    const record = await OfflineStorage.get(STORES.PM_SCHEDULES, id);

    if (!record?.local_save_status) {
        return null;
    }

    return record;
}

export async function clearLocalPmSaveOverlay(id) {
    const existing = await OfflineStorage.get(STORES.PM_SCHEDULES, id);

    if (!existing) {
        return;
    }

    const { local_save_status, pending_save_operation_uuid, local_save_updated_at, ...rest } = existing;

    await OfflineStorage.put(STORES.PM_SCHEDULES, rest);
}

/**
 * Same idea again, but for PM_CHECKLIST_SAVE (Task 6) — its own separate
 * field namespace (`local_checklist_*` / `pending_checklist_operation_uuid`),
 * so a PM can independently show "started offline", "saved offline", AND
 * "checklist saved offline" overlays at once without any of them
 * colliding, and Task 4/5's existing fields/tests stay untouched.
 */
export async function setLocalPmChecklistOverlay(id, overlay) {
    const existing = (await OfflineStorage.get(STORES.PM_SCHEDULES, id)) ?? { id };
    const updated = { ...existing, ...overlay, id };

    await OfflineStorage.put(STORES.PM_SCHEDULES, updated);

    return updated;
}

export async function getLocalPmChecklistOverlay(id) {
    const record = await OfflineStorage.get(STORES.PM_SCHEDULES, id);

    if (!record?.local_checklist_status) {
        return null;
    }

    return record;
}

export async function clearLocalPmChecklistOverlay(id) {
    const existing = await OfflineStorage.get(STORES.PM_SCHEDULES, id);

    if (!existing) {
        return;
    }

    const { local_checklist_status, pending_checklist_operation_uuid, local_checklist_updated_at, ...rest } = existing;

    await OfflineStorage.put(STORES.PM_SCHEDULES, rest);
}

/**
 * Same "local overlay" idea again, but for Oil Audit Follow Up (Task 8) —
 * kept in the generic MASTER_DATA store (via putMasterData/getMasterData)
 * rather than PM_SCHEDULES, since it is keyed by oil_audit_id, not a PM
 * Schedule id, and Oil Audits have no dedicated object store (Task 7
 * deliberately didn't add one — see machine cache via "machines" category).
 * This reuses that same generic cache instead of adding a new IndexedDB
 * object store just for this overlay (Task 8 section 20 forbids schema
 * changes unless truly required).
 */
const OIL_AUDIT_FOLLOW_UP_OVERLAY_CATEGORY = 'oil_audit_follow_up_local_status';

export async function setLocalOilAuditFollowUpOverlay(oilAuditId, overlay) {
    const existing = (await getMasterData(OIL_AUDIT_FOLLOW_UP_OVERLAY_CATEGORY, oilAuditId)) ?? {};
    const updated = { ...existing, ...overlay, oil_audit_id: oilAuditId };

    await putMasterData(OIL_AUDIT_FOLLOW_UP_OVERLAY_CATEGORY, oilAuditId, updated);

    return updated;
}

export async function getLocalOilAuditFollowUpOverlay(oilAuditId) {
    const record = await getMasterData(OIL_AUDIT_FOLLOW_UP_OVERLAY_CATEGORY, oilAuditId);

    if (!record?.local_status) {
        return null;
    }

    return record;
}

export async function clearLocalOilAuditFollowUpOverlay(oilAuditId) {
    const existing = await getMasterData(OIL_AUDIT_FOLLOW_UP_OVERLAY_CATEGORY, oilAuditId);

    if (!existing) {
        return;
    }

    const { local_status, pending_operation_uuid, local_updated_at, ...rest } = existing;

    await putMasterData(OIL_AUDIT_FOLLOW_UP_OVERLAY_CATEGORY, oilAuditId, rest);
}

/**
 * Small bookkeeping key/value pair — e.g. recording when a given category
 * was last downloaded, so a later task can decide whether to refresh it.
 */
export function setSyncMetadata(key, value) {
    return OfflineStorage.put(STORES.SYNC_METADATA, { key, value, updated_at: new Date().toISOString() });
}

export async function getSyncMetadata(key) {
    const record = await OfflineStorage.get(STORES.SYNC_METADATA, key);

    return record?.value;
}
