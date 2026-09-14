/**
 * FreeDOMS offline-first — online/offline detection utility (Phase 1,
 * Task 3).
 *
 * `navigator.onLine` and the `online`/`offline` window events are only a
 * NETWORK hint — a laptop connected to Wi-Fi with no internet, or a
 * captive portal, still reports `navigator.onLine === true` — see Task 3
 * section 18. This module deliberately does NOT claim the server is
 * reachable; it only reports what the browser's network stack reports.
 * Actual server availability is (and, in a later task, will remain)
 * determined by the outcome of a real request (e.g. a sync attempt), not
 * by this flag.
 */
export function isOnline() {
    if (typeof navigator === 'undefined' || typeof navigator.onLine !== 'boolean') {
        // No signal available (e.g. very old browser) — assume online so
        // this never blocks functionality that was working before Task 3.
        return true;
    }

    return navigator.onLine;
}

/**
 * Subscribes to browser network-hint changes. Returns an unsubscribe
 * function.
 *
 * @param {(online: boolean) => void} callback
 */
export function onNetworkChange(callback) {
    if (typeof window === 'undefined') {
        return () => {};
    }

    const goOnline = () => callback(true);
    const goOffline = () => callback(false);

    window.addEventListener('online', goOnline);
    window.addEventListener('offline', goOffline);

    return () => {
        window.removeEventListener('online', goOnline);
        window.removeEventListener('offline', goOffline);
    };
}
