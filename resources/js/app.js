import './pm/edit';
import './checklist/index';
import './pm/start';
import './pm/save';
import './pm/checklist';
import './oil-audits/scan';
import './oil-audits/entry';
import './oil-audits/follow-up';

import './offline/syncUi.js';

import { registerServiceWorker } from './offline/sw-register.js';
import * as FreeDOMSOffline from './offline/index.js';
import { currentUserId } from './offline/scope.js';

// Task 3 foundation: registers the Service Worker (app shell / static asset
// caching) and exposes the offline storage API. Task 9A adds the actual
// sync engine bootstrap below — everything else here (drafts, caching, ...)
// still only runs when a PM/Oil Audit page explicitly asks for it.
registerServiceWorker();
window.FreeDOMSOffline = FreeDOMSOffline;

// FreeDOMS offline-first — sync engine bootstrap (Phase 1, Task 9A).
// Central app.js is the ONLY place this is called from — never a per-page
// Blade script (section 12) — so it runs once per app load regardless of
// which page the user landed on. initAutoSync() itself decides whether to
// actually fire a sync (network hint online at start, "online"/tab-visible
// events later) — this call only wires that up. Guarded to a logged-in
// session (app.blade.php AND guest.blade.php both load this same bundle) —
// there is never a queue to drain on the login page, and /api/sync sits
// behind 'auth' anyway.
if (currentUserId() !== null) {
    FreeDOMSOffline.SyncEngine.initAutoSync();
}
