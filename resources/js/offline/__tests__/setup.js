// Vitest setup: polyfills IndexedDB (indexedDB + IDBKeyRange) in the Node
// test environment via fake-indexeddb, so db.js and everything built on
// it can be exercised with a REAL IndexedDB implementation rather than a
// hand-rolled mock.
import 'fake-indexeddb/auto';
