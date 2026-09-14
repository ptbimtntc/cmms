import { DB_NAME, _resetConnectionForTests } from '../db.js';

/**
 * Deletes the (fake-indexeddb-backed) test database and forces db.js to
 * open a fresh connection on the next call — so schema upgrade
 * (onupgradeneeded) and store creation run again for every test, and
 * tests never see leftover data from a previous one.
 */
export function resetTestDatabase() {
    _resetConnectionForTests();

    return new Promise((resolve, reject) => {
        const request = indexedDB.deleteDatabase(DB_NAME);

        request.onsuccess = () => resolve();
        request.onerror = () => reject(request.error);
        request.onblocked = () => resolve();
    });
}
