/**
 * PM Status Board — status override toggle (view-only).
 *
 * Lets a viewer flip a row's displayed status between OPEN and CLOSED.
 * Purely cosmetic: the override lives only in this browser's localStorage,
 * keyed by PM schedule id, and is never sent to the server —
 * PMStatusBoardController keeps computing the real OPEN/CLOSED value from
 * PMSchedule.status on every request. A reload re-renders the true status
 * from the server, then this script re-applies whatever override is still
 * stored locally on top of it.
 *
 * The toggle button only exists in the DOM when the controller's
 * $canOverrideStatus is true (admin/koordinator). This script keys off that
 * same presence check: with no buttons rendered, it does nothing at all —
 * including skipping re-application of a stale localStorage override from
 * an earlier session on this browser — so a viewer without the permission
 * never sees an overridden status, only the real one.
 */

const STORAGE_KEY = 'pm_status_board_overrides_v1';

function readOverrides() {
    try {
        return JSON.parse(localStorage.getItem(STORAGE_KEY)) || {};
    } catch (e) {
        return {};
    }
}

function writeOverrides(overrides) {
    try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(overrides));
    } catch (e) {
        // localStorage unavailable (private mode, quota, ...) — override
        // still applies for this page view, it just won't persist/reload.
    }
}

function applyStatus(id, status, isOverridden) {
    document.querySelectorAll(`[data-pm-status-cell][data-pm-status-id="${id}"]`).forEach((cell) => {
        const badge = cell.querySelector('[data-pm-status-badge]');
        const tag = cell.querySelector('[data-pm-status-override-tag]');

        if (badge) {
            badge.textContent = status;
            badge.classList.toggle('bg-green-100', status === 'CLOSED');
            badge.classList.toggle('text-green-800', status === 'CLOSED');
            badge.classList.toggle('bg-orange-100', status === 'OPEN');
            badge.classList.toggle('text-orange-800', status === 'OPEN');
        }

        if (tag) {
            tag.classList.toggle('hidden', !isOverridden);
        }
    });
}

function initPmStatusOverride() {
    const toggleButtons = document.querySelectorAll('[data-pm-status-toggle]');

    if (!toggleButtons.length) {
        return;
    }

    const cells = document.querySelectorAll('[data-pm-status-cell]');
    const overrides = readOverrides();

    // Re-apply any override stored from a previous visit on top of the
    // fresh server-rendered status (desktop table + mobile card both carry
    // the same schedule id, so both get updated together).
    cells.forEach((cell) => {
        const id = cell.dataset.pmStatusId;
        if (Object.prototype.hasOwnProperty.call(overrides, id)) {
            applyStatus(id, overrides[id], true);
        }
    });

    toggleButtons.forEach((button) => {
        button.addEventListener('click', () => {
            const cell = button.closest('[data-pm-status-cell]');
            if (!cell) {
                return;
            }

            const id = cell.dataset.pmStatusId;
            const original = cell.dataset.pmStatusOriginal;
            const badge = cell.querySelector('[data-pm-status-badge]');
            const current = badge ? badge.textContent.trim() : original;
            const next = current === 'CLOSED' ? 'OPEN' : 'CLOSED';

            const currentOverrides = readOverrides();

            if (next === original) {
                delete currentOverrides[id];
            } else {
                currentOverrides[id] = next;
            }

            writeOverrides(currentOverrides);
            applyStatus(id, next, next !== original);
        });
    });
}

if (typeof document !== 'undefined') {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initPmStatusOverride);
    } else {
        initPmStatusOverride();
    }
}

export { initPmStatusOverride };
