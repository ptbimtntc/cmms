/**
 * PM Start modal — Task 4 (FreeDOMS offline-first: Implement Offline PM
 * Start). Extracted from the inline <script> that used to live in
 * pm-schedules/index.blade.php (open/close modal, submit) and extended
 * with an online-vs-offline decision: online submits exactly as before
 * (native form POST — untouched, so redirect/flash messages/the
 * activity-conflict modal all keep working exactly as they did); offline
 * (or when the server turns out to be unreachable) enqueues a PM_START
 * operation via resources/js/offline/pmStart.js instead of sending
 * anything to the server.
 */
import { PmStart } from '../offline/index.js';

function nowLocal() {
    const d = new Date();

    d.setMinutes(d.getMinutes() - d.getTimezoneOffset());

    return d.toISOString().slice(0, 16);
}

/**
 * Turns an already-rendered START button into a disabled, clearly
 * labelled "started offline, waiting for sync" indicator — WITHOUT
 * replacing it with new markup, so no layout/structure changes (Task 4
 * section 22). Both the desktop table row and the mobile card render
 * their own `.pm-start-btn` for the same PM, so this updates every match.
 */
function markStartedOffline(pmScheduleId) {
    document.querySelectorAll(`.pm-start-btn[data-id="${pmScheduleId}"]`).forEach((btn) => {
        btn.disabled = true;
        btn.textContent = 'STARTED (Offline)';
        btn.title = 'Waiting for sync';
        btn.classList.remove('pm-start-btn', 'bg-blue-600', 'hover:bg-blue-700', 'cursor-pointer');
        btn.classList.add('bg-amber-500', 'cursor-not-allowed', 'opacity-90');
    });
}

/**
 * On page load, re-applies markStartedOffline() for any PM that already
 * has a local "started offline" overlay from a previous visit (Task 4
 * section 16: the queue — and this local state — must survive reload /
 * browser close+reopen). Only ever affects buttons the server itself
 * still rendered as START (i.e. the server doesn't know yet); once the
 * server DOES know (after a future sync), it stops rendering the button
 * at all, so there is nothing left here to override.
 */
async function restoreOfflineStartedButtons() {
    const buttons = [...document.querySelectorAll('.pm-start-btn')];

    await Promise.all(buttons.map(async (btn) => {
        const id = Number(btn.dataset.id);
        const overlay = await PmStart.getLocalPmOverlay(id).catch(() => null);

        if (overlay?.local_status === 'started_offline') {
            markStartedOffline(id);
        }
    }));
}

function initPmStartModal() {
    const modal = document.getElementById('pm-start-modal');

    if (!modal) {
        return;
    }

    const form = document.getElementById('pm-start-form');
    const input = document.getElementById('pm-start-input');
    const machineLabel = document.getElementById('pm-start-machine');
    const baseAction = form.dataset.baseAction;

    let currentPmId = null;
    let currentPmStatus = null;

    function openModal(btn) {
        currentPmId = btn.dataset.id;
        currentPmStatus = btn.dataset.status;
        form.action = `${baseAction}/${currentPmId}/start`;
        input.value = nowLocal();
        machineLabel.textContent = btn.dataset.machine ? `Machine: ${btn.dataset.machine}` : '';
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    function closeModal() {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }

    document.querySelectorAll('.pm-start-btn').forEach((btn) => {
        btn.addEventListener('click', () => openModal(btn));
    });

    document.getElementById('pm-start-cancel').addEventListener('click', closeModal);
    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            closeModal();
        }
    });

    const submitButton = form.querySelector('button[type="submit"]');
    let submitting = false;

    form.addEventListener('submit', async (e) => {
        // Always prevented first (synchronously) so the async
        // online-reachability probe below can never race a native submit
        // that already started (Task 4 section 6).
        e.preventDefault();

        // Guards against a fast double-click queuing the SAME PM twice
        // locally before the first probe/enqueue even finishes — the only
        // duplicate-Start scenario the local guard in pmStart.js's
        // startOffline() (different-PM only) does not itself catch.
        if (submitting) {
            return;
        }

        submitting = true;
        if (submitButton) {
            submitButton.disabled = true;
        }

        const pmScheduleId = currentPmId;
        const expectedStatus = currentPmStatus;
        const startedAtLocal = input.value;

        try {
            const online = await PmStart.probeServerReachable();

            if (online) {
                // Server is actually reachable right now — submit exactly
                // as this form always has. form.submit() bypasses the
                // 'submit' event entirely, so this can't re-enter this
                // handler, and the page navigates away regardless — no
                // need to re-enable the button.
                form.submit();

                return;
            }

            await PmStart.startOffline({
                pmScheduleId: Number(pmScheduleId),
                startedAtLocal,
                expectedStatus,
            });
            closeModal();
            markStartedOffline(pmScheduleId);
        } catch (error) {
            closeModal();
            // A deliberately plain, non-blocking-redesign feedback path
            // (section 22/7) — this is not a "Network Error", it's a
            // specific local rule (e.g. another PM_START already queued).
            window.alert(error?.message || 'PM Start tidak dapat disimpan secara offline.');
        } finally {
            submitting = false;
            if (submitButton) {
                submitButton.disabled = false;
            }
        }
    });

    restoreOfflineStartedButtons();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initPmStartModal);
} else {
    initPmStartModal();
}
