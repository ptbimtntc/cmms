/**
 * FreeDOMS offline-first — Service Worker registration (Phase 1, Task 3).
 *
 * Deliberately a no-op during `vite dev`: registering a Service Worker in
 * development would risk serving stale bundles while debugging (Task 3
 * section 21 — "Service Worker jangan sampai membuat debugging
 * development menjadi kacau"). `import.meta.env.DEV` is Vite's own
 * built-in flag, true only for `npm run dev`, false for a built
 * (`npm run build`) production bundle — no extra config needed.
 *
 * Registration failures (unsupported browser, insecure context, blocked
 * by the user's browser settings, ...) are logged and swallowed — a
 * missing Service Worker must never break the online app (Task 3 section
 * 22).
 */
export function registerServiceWorker() {
    if (import.meta.env.DEV) {
        return;
    }

    if (typeof navigator === 'undefined' || !('serviceWorker' in navigator)) {
        return;
    }

    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch((error) => {
            console.warn('[offline/sw-register] Service Worker registration failed.', error);
        });
    });
}
