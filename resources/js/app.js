import './pm/edit';
import './checklist/index';
import './pm/start';

import { registerServiceWorker } from './offline/sw-register.js';
import * as FreeDOMSOffline from './offline/index.js';

// Task 3 foundation only: registers the Service Worker (app shell / static
// asset caching) and exposes the offline storage API for later tasks to
// build PM/Oil Audit offline workflows on top of. Nothing here queues,
// drafts, or caches anything automatically.
registerServiceWorker();
window.FreeDOMSOffline = FreeDOMSOffline;
