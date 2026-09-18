/**
 * FreeDOMS offline-first — Phase 1, Task 3 foundation entry point.
 *
 * Single import surface for the offline storage foundation. Importing
 * this module does NOT activate any offline workflow for PM or Oil
 * Audit — it only makes the storage/queue/draft/master-data APIs and the
 * Service Worker registration available. See docs/tasks for what each
 * piece is (and isn't) responsible for.
 */
export { DB_NAME, DB_VERSION, STORES, OfflineStorage, OfflineStorageError, openDatabase, isIndexedDbAvailable } from './db.js';
export { generateUuid } from './uuid.js';
export { currentUserId } from './scope.js';
export { csrfToken } from './csrf.js';
export { isOnline, onNetworkChange } from './network.js';
export * as SyncQueue from './queue.js';
export * as Drafts from './drafts.js';
export * as MasterDataCache from './masterData.js';
export * as PmStart from './pmStart.js';
export * as PmSave from './pmSave.js';
export * as PmChecklist from './pmChecklist.js';
export * as OilAuditCreate from './oilAuditCreate.js';
export * as OilAuditFollowUp from './oilAuditFollowUp.js';
export * as SyncEngine from './syncEngine.js';
export { registerServiceWorker } from './sw-register.js';
