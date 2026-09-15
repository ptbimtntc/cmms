/**
 * Oil Audit entry page wiring — Task 7 (FreeDOMS offline-first). This page
 * is only ever reached by an ONLINE navigation (see scan.js — the entry
 * page itself is dynamic per machine and never precached), but the device
 * can still go offline between loading this page and tapping a condition
 * button, so each of the 7 one-tap condition forms gets the same
 * online-vs-offline submit decision as Task 4/5/6.
 *
 * Online: submits exactly as before (native form POST, untouched).
 * Offline: enqueues OIL_AUDIT_CREATE locally and shows feedback on this
 * same page instead of redirecting (a redirect back to /oil-audits/scan
 * would itself require a request that cannot succeed offline).
 */
import { OilAuditCreate } from '../offline/index.js';
import { probeServerReachable } from '../offline/network.js';

function initEntryPage() {
    const forms = document.querySelectorAll('form[data-condition-form]');

    if (forms.length === 0) {
        return;
    }

    let feedback = document.getElementById('oil-audit-entry-feedback');

    if (!feedback) {
        feedback = document.createElement('div');
        feedback.id = 'oil-audit-entry-feedback';
        feedback.className = 'mb-5 hidden rounded-2xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800';
        forms[0].closest('.rounded-3xl')?.prepend(feedback);
    }

    function showFeedback(html) {
        feedback.innerHTML = html;
        feedback.classList.remove('hidden');
    }

    let submitting = false;

    forms.forEach((form) => {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();

            if (submitting) {
                return;
            }

            submitting = true;
            forms.forEach((f) => f.querySelector('button[type="submit"]')?.setAttribute('disabled', 'disabled'));

            try {
                const online = await probeServerReachable();

                if (online) {
                    form.submit();

                    return;
                }

                const machineId = Number(form.querySelector('input[name="machine_id"]')?.value);
                const condition = form.querySelector('input[name="condition"]')?.value;

                await OilAuditCreate.saveOffline({ machineId, condition });

                showFeedback(
                    'Audit oli berhasil <strong>disimpan secara offline</strong> dan akan disinkronkan ke server saat '
                    + 'perangkat kembali online. Anda dapat kembali ke scan untuk mesin berikutnya.'
                );
            } catch (error) {
                window.alert(error?.message || 'Audit oli tidak dapat disimpan secara offline.');
            } finally {
                submitting = false;
                forms.forEach((f) => f.querySelector('button[type="submit"]')?.removeAttribute('disabled'));
            }
        });
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initEntryPage);
} else {
    initEntryPage();
}
