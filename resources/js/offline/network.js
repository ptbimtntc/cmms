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

/**
 * navigator.onLine is only ever a network HINT, never proof the server is
 * reachable — a device can be on a Wi-Fi network with no real internet, or
 * the server itself can be down (Task 4 section 6). This probes Laravel's
 * own built-in, unauthenticated, side-effect-free health route
 * (`health: '/up'` in bootstrap/app.php) with a short timeout so a
 * genuinely offline device doesn't hang the UI waiting for one.
 *
 * Originally written for PM Start (Task 4); moved here (Task 5) since it
 * is a generic network-reachability check every offline feature — PM
 * Save included — needs, not something specific to PM_START. Still
 * re-exported from pmStart.js for backward compatibility.
 *
 * @param {number} timeoutMs
 * @returns {Promise<boolean>}
 */
export async function probeServerReachable(timeoutMs = 3000) {
    if (!isOnline()) {
        // The browser itself is confident there is no network — skip the
        // round-trip entirely.
        return false;
    }

    if (typeof fetch === 'undefined') {
        return false;
    }

    try {
        const response = await fetch('/up', {
            method: 'GET',
            cache: 'no-store',
            signal: typeof AbortSignal !== 'undefined' && AbortSignal.timeout
                ? AbortSignal.timeout(timeoutMs)
                : undefined,
        });

        return response.ok;
    } catch {
        // Any thrown error here (network failure, DNS failure, timeout) —
        // NOT an HTTP error status, fetch() only throws for those — means
        // the server could not actually be reached.
        return false;
    }
}
