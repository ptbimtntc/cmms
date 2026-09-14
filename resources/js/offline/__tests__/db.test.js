import { beforeEach, describe, expect, it } from 'vitest';
import { DB_NAME, DB_VERSION, OfflineStorage, OfflineStorageError, STORES, isIndexedDbAvailable, openDatabase } from '../db.js';
import { resetTestDatabase } from './helpers.js';

beforeEach(async () => {
    await resetTestDatabase();
});

describe('openDatabase', () => {
    it('reports IndexedDB as available in this (polyfilled) environment', () => {
        expect(isIndexedDbAvailable()).toBe(true);
    });

    it('creates the database with every expected object store', async () => {
        const db = await openDatabase();

        expect(db.name).toBe(DB_NAME);
        expect(db.version).toBe(DB_VERSION);
        Object.values(STORES).forEach((storeName) => {
            expect(db.objectStoreNames.contains(storeName)).toBe(true);
        });
    });

    it('can be opened again (reusing the connection) without error', async () => {
        const first = await openDatabase();
        const second = await openDatabase();

        // Same underlying connection object — openDatabase() does not
        // open a second, redundant connection.
        expect(second).toBe(first);
    });
});

describe('OfflineStorage put/get/delete/getAll/clear', () => {
    it('put() then get() returns the stored record', async () => {
        await OfflineStorage.put(STORES.SYNC_METADATA, { key: 'a', value: 1 });

        const record = await OfflineStorage.get(STORES.SYNC_METADATA, 'a');

        expect(record).toEqual({ key: 'a', value: 1 });
    });

    it('put() on an existing key updates (replaces) the record', async () => {
        await OfflineStorage.put(STORES.SYNC_METADATA, { key: 'a', value: 1 });
        await OfflineStorage.put(STORES.SYNC_METADATA, { key: 'a', value: 2 });

        const record = await OfflineStorage.get(STORES.SYNC_METADATA, 'a');

        expect(record.value).toBe(2);
    });

    it('delete() removes the record', async () => {
        await OfflineStorage.put(STORES.SYNC_METADATA, { key: 'a', value: 1 });
        await OfflineStorage.delete(STORES.SYNC_METADATA, 'a');

        const record = await OfflineStorage.get(STORES.SYNC_METADATA, 'a');

        expect(record).toBeUndefined();
    });

    it('getAll() returns every record in the store', async () => {
        await OfflineStorage.put(STORES.SYNC_METADATA, { key: 'a', value: 1 });
        await OfflineStorage.put(STORES.SYNC_METADATA, { key: 'b', value: 2 });

        const records = await OfflineStorage.getAll(STORES.SYNC_METADATA);

        expect(records).toHaveLength(2);
    });

    it('getAll() with an index query filters by that index', async () => {
        await OfflineStorage.put(STORES.MASTER_DATA, { cache_key: 'machines:1', category: 'machines', id: 1 });
        await OfflineStorage.put(STORES.MASTER_DATA, { cache_key: 'spareparts:1', category: 'spareparts', id: 1 });

        const machines = await OfflineStorage.getAll(STORES.MASTER_DATA, { indexName: 'by_category', query: 'machines' });

        expect(machines).toHaveLength(1);
        expect(machines[0].category).toBe('machines');
    });

    it('clear() empties the store', async () => {
        await OfflineStorage.put(STORES.SYNC_METADATA, { key: 'a', value: 1 });
        await OfflineStorage.clear(STORES.SYNC_METADATA);

        const records = await OfflineStorage.getAll(STORES.SYNC_METADATA);

        expect(records).toHaveLength(0);
    });

    it('rejects with an OfflineStorageError instead of throwing synchronously on a bad store name', async () => {
        await expect(OfflineStorage.get('not_a_real_store', 'x')).rejects.toBeInstanceOf(OfflineStorageError);
    });
});

describe('schema upgrades stay additive', () => {
    it('an upgrade to a later version does not delete existing stores or their data', async () => {
        // Seed data at the current schema version.
        await OfflineStorage.put(STORES.SYNC_METADATA, { key: 'kept', value: 'still here' });

        // Close the shared connection, then simulate a FUTURE migration
        // (DB_VERSION + 1) that adds a new store — mirroring exactly what
        // db.js's own upgradeSchema() does for a real future version:
        // only ever ADD, via objectStoreNames.contains() guards, never
        // recreate/delete an existing store.
        const current = await openDatabase();

        current.close();

        await new Promise((resolve, reject) => {
            const request = indexedDB.open(DB_NAME, DB_VERSION + 1);

            request.onupgradeneeded = (event) => {
                const db = event.target.result;

                if (!db.objectStoreNames.contains('future_store')) {
                    db.createObjectStore('future_store', { keyPath: 'id' });
                }
            };
            request.onsuccess = () => {
                request.result.close();
                resolve();
            };
            request.onerror = () => reject(request.error);
        });

        // Re-open through db.js at its own DB_VERSION... but since the
        // physical database is now at DB_VERSION + 1, db.js must still be
        // able to read the data that survived the upgrade. We verify this
        // by opening at the new version directly (what a real future
        // db.js revision would do) and confirming old data + old stores
        // are untouched, and the new store also exists.
        await new Promise((resolve, reject) => {
            const request = indexedDB.open(DB_NAME, DB_VERSION + 1);

            request.onsuccess = () => {
                const db = request.result;

                expect(db.objectStoreNames.contains(STORES.SYNC_METADATA)).toBe(true);
                expect(db.objectStoreNames.contains('future_store')).toBe(true);

                const tx = db.transaction(STORES.SYNC_METADATA, 'readonly');
                const getRequest = tx.objectStore(STORES.SYNC_METADATA).get('kept');

                getRequest.onsuccess = () => {
                    expect(getRequest.result).toEqual({ key: 'kept', value: 'still here' });
                    db.close();
                    resolve();
                };
                getRequest.onerror = () => reject(getRequest.error);
            };
            request.onerror = () => reject(request.error);
        });
    });
});
