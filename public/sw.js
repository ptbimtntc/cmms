/**
 * FreeDOMS offline-first — Service Worker (Phase 1, Task 3).
 *
 * Scope is deliberately narrow — app shell / static assets ONLY. This
 * file must never become the place transaction data lives; the sync
 * queue, drafts, and cached master data all live in IndexedDB
 * (resources/js/offline/*.js), never here (Task 3 section 3):
 *
 *   Service Worker -> app shell / static assets
 *   IndexedDB      -> application data / drafts / queue
 *
 * Strategy (kept intentionally simple — section 21):
 *  - Built, content-hashed assets under /build/ (immutable — a rebuild
 *    always produces a new filename) are cached opportunistically,
 *    cache-first: once fetched, served from cache from then on.
 *  - Page navigations are network-first: a user who is online always
 *    gets the current, real (and possibly user-specific) page from the
 *    server. Only when that fetch fails (offline) do we fall back to the
 *    precached, static /offline page.
 *  - Nothing else is cached. In particular, dynamic Laravel responses
 *    (PM/Oil Audit pages and their data, /api/sync, any authenticated
 *    JSON) are always fetched from the network, never stored here — see
 *    section 5/23. This also means a dynamic page's own HTML is NEVER
 *    cached, so there is no risk of one user's cached page data being
 *    served to a different user sharing the same device offline.
 *  - Cross-origin requests (font/CDN scripts this app already loads) are
 *    left completely untouched — this Service Worker does not intercept
 *    or cache them.
 *
 * Bump CACHE_VERSION on any change to what gets precached; activate()
 * below removes every old freedoms-* cache automatically, so a stale
 * deploy's cache never lingers (section 21).
 */

const CACHE_VERSION = 'v1';
const SHELL_CACHE = `freedoms-shell-${CACHE_VERSION}`;
const RUNTIME_CACHE = `freedoms-runtime-${CACHE_VERSION}`;

// Static, non-hashed, publicly reachable files only — nothing here
// requires auth or contains user data, so it is always safe to precache.
const SHELL_URLS = [
    '/offline',
    '/manifest.webmanifest',
    '/FreeDOMS.ico',
    '/FreeDOMS.svg',
    '/favicon.ico',
    '/favicon.svg',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(SHELL_CACHE)
            .then((cache) => cache.addAll(SHELL_URLS))
            .catch((error) => {
                // Never let a single missing asset block installation —
                // an offline fallback that's merely missing an icon is far
                // better than no Service Worker at all.
                console.warn('[sw] precache failed', error);
            })
    );
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((names) => Promise.all(
            names
                .filter((name) => name.startsWith('freedoms-') && name !== SHELL_CACHE && name !== RUNTIME_CACHE)
                .map((name) => caches.delete(name))
        )).then(() => self.clients.claim())
    );
});

async function cacheFirst(request) {
    const cache = await caches.open(RUNTIME_CACHE);
    const cached = await cache.match(request);

    if (cached) {
        return cached;
    }

    const response = await fetch(request);

    if (response.ok) {
        cache.put(request, response.clone());
    }

    return response;
}

async function networkFirstNavigation(request) {
    try {
        return await fetch(request);
    } catch (error) {
        const cache = await caches.open(SHELL_CACHE);
        const offline = await cache.match('/offline');

        if (offline) {
            return offline;
        }

        throw error;
    }
}

self.addEventListener('fetch', (event) => {
    const { request } = event;

    // Never intercept non-GET requests (form posts, /api/sync, ...) — the
    // Cache API doesn't support them anyway, and they must always reach
    // the network untouched.
    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);

    // Cross-origin (font CDN, Alpine.js CDN, tom-select CDN, ...) is left
    // completely alone — not this Service Worker's concern.
    if (url.origin !== self.location.origin) {
        return;
    }

    // Vite dev server requests never reach here in practice (different
    // origin/port), but skip anything HMR-shaped defensively too.
    if (url.pathname.startsWith('/@vite') || url.pathname.startsWith('/@id') || url.search.includes('hot-update')) {
        return;
    }

    if (url.pathname.startsWith('/build/')) {
        event.respondWith(cacheFirst(request));

        return;
    }

    if (request.mode === 'navigate') {
        event.respondWith(networkFirstNavigation(request));
    }

    // Everything else (dynamic Laravel pages, JSON, /api/sync, ...): no
    // respondWith() at all — falls through to the browser's normal
    // network fetch, completely untouched by this Service Worker.
});
