/**
 * FreeDOMS offline-first — UUID helper (Phase 1, Task 3).
 *
 * Every sync queue operation needs an operation_uuid that stays IDENTICAL
 * across retries (see docs/tasks Task 3 section 10) — this is the same
 * idempotency key `sync_operations.operation_uuid` (Task 2) is built
 * around. It must never be a timestamp, a simple counter, or an array
 * index: those are not safe against collisions across devices/sessions.
 *
 * Uses the browser's native crypto.randomUUID() (RFC 4122 v4, available in
 * every secure context / modern browser) when present, with a
 * spec-compliant fallback for older / non-secure-context environments so
 * this never throws.
 */
export function generateUuid() {
    if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
        return crypto.randomUUID();
    }

    // RFC 4122 v4 fallback using Math.random(). Not cryptographically
    // strong, but only used when crypto.randomUUID is unavailable — still
    // unique enough to serve as an idempotency key, which only needs to
    // avoid collisions, not resist prediction.
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
        const r = (Math.random() * 16) | 0;
        const v = c === 'x' ? r : (r & 0x3) | 0x8;

        return v.toString(16);
    });
}
