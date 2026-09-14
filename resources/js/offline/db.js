/**
 * FreeDOMS offline-first — IndexedDB storage abstraction (Phase 1, Task 3).
 *
 * Single source of truth for the local database schema and the generic
 * put/get/delete/getAll/clear/transaction primitives every offline feature
 * (queue, drafts, master-data cache — built in the sibling modules) is
 * built on. No feature module, and definitely no Blade/inline <script>,
 * should call `indexedDB.open(...)` / `objectStore(...)` directly — see
 * docs/tasks Task 3 section 7:
 *
 *   PM Offline Logic -> Offline Storage API -> IndexedDB   (this file)
 *
 * NOT:
 *
 *   PM Blade -> indexedDB.open(...) -> objectStore(...)
 *
 * This module only provides the storage primitive. It does NOT activate
 * any PM / Oil Audit offline workflow — see queue.js / drafts.js /
 * masterData.js for the (still inert, foundation-only) feature modules
 * that use it.
 */

export const DB_NAME = 'freedoms-offline';

/**
 * Bump this when the object store / index shape changes. onupgradeneeded
 * below MUST stay additive (create missing stores/indexes, never delete or
 * recreate an existing one) so an upgrade never wipes data a previous
 * version already wrote — see Task 3 section 8.
 */
export const DB_VERSION = 1;

/**
 * Logical stores this task's foundation provides (Task 3 section 5):
 * master-data cache, a dedicated PM-schedule cache (queried differently
 * from generic master data — by assigned PIC/area), local drafts, the
 * sync queue, and a small key/value store for sync bookkeeping (e.g. "when
 * was master data last refreshed").
 */
export const STORES = {
    MASTER_DATA: 'master_data',
    PM_SCHEDULES: 'pm_schedules',
    DRAFTS: 'drafts',
    SYNC_QUEUE: 'sync_queue',
    SYNC_METADATA: 'sync_metadata',
};

/**
 * Thrown/rejected-with for every storage failure (IndexedDB unavailable,
 * quota exceeded, blocked upgrade, transaction abort, ...) so callers can
 * catch one specific error type instead of guessing at IndexedDB's own
 * exception shapes — see Task 3 section 22 (storage failures must never
 * crash the app; they must surface an understandable error to the caller).
 */
export class OfflineStorageError extends Error {
    constructor(message, cause) {
        super(message);
        this.name = 'OfflineStorageError';
        if (cause) {
            this.cause = cause;
        }
    }
}

export function isIndexedDbAvailable() {
    return typeof indexedDB !== 'undefined' && indexedDB !== null;
}

/**
 * Creates every object store / index this version of the schema needs, but
 * ONLY if it doesn't already exist. This is what makes upgrades additive:
 * a future DB_VERSION bump that needs a new store/index just adds another
 * `if (!db.objectStoreNames.contains(...))` branch here — existing stores
 * and the data in them are never touched.
 */
function upgradeSchema(db) {
    if (!db.objectStoreNames.contains(STORES.SYNC_QUEUE)) {
        const queue = db.createObjectStore(STORES.SYNC_QUEUE, { keyPath: 'operation_uuid' });
        queue.createIndex('by_status', 'status', { unique: false });
        queue.createIndex('by_created_at', 'created_at', { unique: false });
        queue.createIndex('by_transaction_type', 'transaction_type', { unique: false });
        queue.createIndex('by_user', 'user_id', { unique: false });
    }

    if (!db.objectStoreNames.contains(STORES.DRAFTS)) {
        const drafts = db.createObjectStore(STORES.DRAFTS, { keyPath: 'draft_id' });
        drafts.createIndex('by_type', 'draft_type', { unique: false });
        drafts.createIndex('by_reference', 'reference_id', { unique: false });
        drafts.createIndex('by_user', 'user_id', { unique: false });
        // A device is shared, but a given user only ever has ONE draft per
        // (draft_type, reference_id) — see Task 3 section 15 ("don't create
        // a duplicate draft on every autosave"). Composite unique index
        // lets save() detect "update this one" vs "create a new one"
        // without a separate existence query.
        drafts.createIndex('by_user_type_reference', ['user_id', 'draft_type', 'reference_id'], { unique: true });
    }

    if (!db.objectStoreNames.contains(STORES.MASTER_DATA)) {
        // cache_key = `${category}:${id}` (see masterData.js) — a single
        // generic store for every "reference data" category (machines,
        // measurement parameters, checklist items, problem categories/
        // findings, spareparts, ...) rather than one object store per
        // category, since Task 3 only prepares the cache shape and does
        // not yet decide which categories get downloaded.
        const masterData = db.createObjectStore(STORES.MASTER_DATA, { keyPath: 'cache_key' });
        masterData.createIndex('by_category', 'category', { unique: false });
    }

    if (!db.objectStoreNames.contains(STORES.PM_SCHEDULES)) {
        // Kept separate from MASTER_DATA (per Task 3 section 5's example
        // store list) because it will need PIC/area-scoped queries later,
        // unlike generic reference data.
        const pmSchedules = db.createObjectStore(STORES.PM_SCHEDULES, { keyPath: 'id' });
        pmSchedules.createIndex('by_pic', 'pic', { unique: false });
        pmSchedules.createIndex('by_area', 'area', { unique: false });
        pmSchedules.createIndex('by_status', 'status', { unique: false });
    }

    if (!db.objectStoreNames.contains(STORES.SYNC_METADATA)) {
        // Small generic key/value store, e.g. { key: 'master_data:machines:last_refreshed_at', value: ... }.
        db.createObjectStore(STORES.SYNC_METADATA, { keyPath: 'key' });
    }
}

let dbPromise = null;

/**
 * Opens (or reuses the already-open) database connection. Safe to call
 * repeatedly — every caller shares the same underlying connection instead
 * of re-opening one per operation.
 *
 * @returns {Promise<IDBDatabase>}
 */
export function openDatabase() {
    if (!isIndexedDbAvailable()) {
        return Promise.reject(new OfflineStorageError('IndexedDB is not available in this browser/context.'));
    }

    if (dbPromise) {
        return dbPromise;
    }

    dbPromise = new Promise((resolve, reject) => {
        let request;

        try {
            request = indexedDB.open(DB_NAME, DB_VERSION);
        } catch (error) {
            reject(new OfflineStorageError('Failed to open the offline database.', error));

            return;
        }

        request.onupgradeneeded = (event) => {
            try {
                upgradeSchema(event.target.result);
            } catch (error) {
                // Let onerror below reject the promise; nothing else to do
                // here besides not letting this throw crash the event loop.
                console.error('[offline/db] schema upgrade failed', error);
            }
        };

        request.onblocked = () => {
            console.warn('[offline/db] database upgrade is blocked by another open tab/connection.');
        };

        request.onsuccess = () => {
            const db = request.result;

            // If another tab upgrades the schema later, this connection
            // becomes stale — close it so the NEXT openDatabase() call
            // re-opens a fresh one instead of operating on a closed db.
            db.onversionchange = () => {
                db.close();
                dbPromise = null;
            };

            resolve(db);
        };

        request.onerror = () => {
            dbPromise = null;
            reject(new OfflineStorageError('Failed to open the offline database.', request.error));
        };
    });

    return dbPromise;
}

function wrapRequest(request, errorMessage) {
    return new Promise((resolve, reject) => {
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(new OfflineStorageError(errorMessage, request.error));
    });
}

/**
 * `db.transaction(storeName, mode)` throws SYNCHRONOUSLY (not via
 * request.onerror) when storeName doesn't exist, or when a version-change
 * closed the connection out from under us — every OfflineStorage method
 * must go through this instead of calling db.transaction() directly, or
 * that throw escapes as a raw DOMException instead of an
 * OfflineStorageError (see Task 3 section 22: storage failures must
 * surface as an understandable error to the caller, never an uncaught
 * exception).
 */
function openTx(db, storeNames, mode, errorMessage) {
    try {
        return db.transaction(storeNames, mode);
    } catch (error) {
        throw new OfflineStorageError(errorMessage, error);
    }
}

/**
 * Generic storage primitives. Every method opens (or reuses) the shared
 * connection, runs one short-lived transaction, and resolves/rejects a
 * Promise — callers never see a raw IDBRequest/IDBTransaction.
 */
export const OfflineStorage = {
    /**
     * Insert or fully replace a record. `value` must contain the store's
     * keyPath field (e.g. operation_uuid, draft_id, cache_key, id).
     */
    async put(storeName, value) {
        const db = await openDatabase();
        const tx = openTx(db, storeName, 'readwrite', `put(${storeName}) failed to start a transaction.`);

        return new Promise((resolve, reject) => {
            const request = tx.objectStore(storeName).put(value);

            tx.onabort = () => reject(new OfflineStorageError(`put(${storeName}) transaction aborted.`, tx.error));
            request.onerror = () => reject(new OfflineStorageError(`put(${storeName}) failed.`, request.error));
            request.onsuccess = () => resolve(request.result);
        });
    },

    async get(storeName, key) {
        const db = await openDatabase();
        const tx = openTx(db, storeName, 'readonly', `get(${storeName}) failed to start a transaction.`);

        return wrapRequest(tx.objectStore(storeName).get(key), `get(${storeName}) failed.`);
    },

    async delete(storeName, key) {
        const db = await openDatabase();
        const tx = openTx(db, storeName, 'readwrite', `delete(${storeName}) failed to start a transaction.`);

        return wrapRequest(tx.objectStore(storeName).delete(key), `delete(${storeName}) failed.`);
    },

    /**
     * @param {string} storeName
     * @param {{indexName?: string, query?: IDBValidKey|IDBKeyRange}} [options]
     */
    async getAll(storeName, options = {}) {
        const db = await openDatabase();
        const tx = openTx(db, storeName, 'readonly', `getAll(${storeName}) failed to start a transaction.`);
        const store = tx.objectStore(storeName);
        const source = options.indexName ? store.index(options.indexName) : store;

        return wrapRequest(source.getAll(options.query), `getAll(${storeName}) failed.`);
    },

    async clear(storeName) {
        const db = await openDatabase();
        const tx = openTx(db, storeName, 'readwrite', `clear(${storeName}) failed to start a transaction.`);

        return wrapRequest(tx.objectStore(storeName).clear(), `clear(${storeName}) failed.`);
    },

    async count(storeName, options = {}) {
        const db = await openDatabase();
        const tx = openTx(db, storeName, 'readonly', `count(${storeName}) failed to start a transaction.`);
        const store = tx.objectStore(storeName);
        const source = options.indexName ? store.index(options.indexName) : store;

        return wrapRequest(source.count(options.query), `count(${storeName}) failed.`);
    },

    /**
     * Escape hatch for operations that must run multiple reads/writes
     * atomically in ONE IndexedDB transaction (e.g. "check no other record
     * has this unique field, then insert" — see queue.js's duplicate
     * operation_uuid guard). `run` receives the raw IDBTransaction; the
     * promise resolves with whatever `run` returns once the transaction
     * completes, or rejects if the transaction aborts/errors.
     *
     * @param {string|string[]} storeNames
     * @param {IDBTransactionMode} mode
     * @param {(tx: IDBTransaction) => any} run
     */
    async transaction(storeNames, mode, run) {
        const db = await openDatabase();

        return new Promise((resolve, reject) => {
            let tx;

            try {
                tx = db.transaction(storeNames, mode);
            } catch (error) {
                reject(new OfflineStorageError('Failed to start transaction.', error));

                return;
            }

            let result;

            try {
                result = run(tx);
            } catch (error) {
                try {
                    tx.abort();
                } catch {
                    // already aborted/finished — nothing to do.
                }
                reject(new OfflineStorageError('Transaction callback threw.', error));

                return;
            }

            tx.oncomplete = () => resolve(result);
            tx.onabort = () => reject(new OfflineStorageError('Transaction aborted.', tx.error));
            tx.onerror = () => reject(new OfflineStorageError('Transaction failed.', tx.error));
        });
    },
};

/** Test-only: forces the next openDatabase() call to re-open a connection. */
export function _resetConnectionForTests() {
    dbPromise = null;
}
