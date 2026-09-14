/**
 * FreeDOMS offline-first — user/device scope helper (Phase 1, Task 3).
 *
 * A device (tablet/laptop used on-site) can be shared by more than one
 * PIC/user — see Task 3 section 17. Every scoped store (drafts, sync
 * queue) is keyed/filtered by a `user_id` that comes from the SERVER
 * (Laravel's authenticated user id), never from a display name or
 * anything typed client-side, so two users on the same device never read
 * or silently overwrite each other's local records.
 *
 * The id is exposed to the page via a <meta name="app-user-id"> tag
 * (partials/head.blade.php and layouts/app.blade.php) — the same pattern
 * this app already uses for the CSRF token — so this module only ever
 * reads it, it never invents or guesses one.
 */
export function currentUserId() {
    if (typeof document === 'undefined') {
        return null;
    }

    const meta = document.querySelector('meta[name="app-user-id"]');
    const value = meta?.getAttribute('content');

    return value ? value : null;
}
