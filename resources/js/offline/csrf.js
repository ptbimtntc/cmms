/**
 * Reads the CSRF token from the <meta name="csrf-token"> tag every layout
 * already renders (partials/head.blade.php, layouts/app.blade.php,
 * layouts/guest.blade.php) — the same token Blade's @csrf directive
 * embeds into regular forms, so a fetch()-based request (Task 4's
 * /api/sync calls) is authorized exactly like a normal form submission.
 */
export function csrfToken() {
    if (typeof document === 'undefined') {
        return null;
    }

    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? null;
}
