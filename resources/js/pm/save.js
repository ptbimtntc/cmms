/**
 * Fill PM (PM Save) form wiring — Task 5 (FreeDOMS offline-first: Implement
 * Offline PM Save). Adds an online-vs-offline decision on submit (online
 * submits exactly as before — native form POST, untouched) and local
 * draft autosave/restore using the storage abstraction from Task 3.
 *
 * Deliberately does NOT touch PM Checklist — Fill PM's "Lihat Checklist"
 * button only ever submits THIS form (PM_SAVE); the checklist page/step
 * stays a separate, unmodified request.
 */
import { PmSave } from '../offline/index.js';
import * as Drafts from '../offline/drafts.js';
import { currentUserId } from '../offline/scope.js';
import { probeServerReachable } from '../offline/network.js';

const AUTOSAVE_DEBOUNCE_MS = 1000;

function debounce(fn, delayMs) {
    let timer = null;

    return (...args) => {
        clearTimeout(timer);
        timer = setTimeout(() => fn(...args), delayMs);
    };
}

function formatTimestamp(iso) {
    if (!iso) {
        return '';
    }

    try {
        return new Date(iso).toLocaleString();
    } catch {
        return iso;
    }
}

/**
 * Restores the fields that are safe to write back into the DOM directly
 * (plain <select>/<textarea>/<input> elements, never a TomSelect-enhanced
 * one, and never rows this script would need to dynamically add/remove —
 * see the Task 5 report for why problems[]/spareparts[]/extra sessions
 * are intentionally NOT auto-restored here).
 */
function restoreSafeFieldsFromDraft(form, payload) {
    const setValue = (name, value) => {
        const field = form.elements.namedItem(name);

        if (field && value !== undefined) {
            field.value = value;
        }
    };

    setValue('oil_change', payload.oil_change);
    setValue('greasing', payload.greasing);
    setValue('wo_zsbp', payload.wo_zsbp);
    setValue('remarks', payload.remarks);

    const picField = form.elements.namedItem('pic');

    if (picField && !picField.disabled && payload.pic !== undefined) {
        picField.value = payload.pic;
    }

    Object.entries(payload.measurements ?? {}).forEach(([index, measurement]) => {
        setValue(`measurements[${index}][measurement_value]`, measurement?.measurement_value);
    });

    const firstSession = (payload.sessions ?? {})[0];

    if (firstSession) {
        setValue('sessions[0][actual_date]', firstSession.actual_date);
        setValue('sessions[0][start_time]', firstSession.start_time);
        setValue('sessions[0][end_time]', firstSession.end_time);
    } else {
        setValue('actual_date', payload.actual_date);
        setValue('start_time', payload.start_time);
        setValue('end_time', payload.end_time);
    }
}

function countExtras(payload) {
    const sessionCount = Object.keys(payload.sessions ?? {}).length;
    const problemCount = Object.keys(payload.problems ?? {}).length;
    const sparepartCount = Object.keys(payload.spareparts ?? {}).length;

    return { extraSessions: Math.max(sessionCount - 1, 0), problemCount, sparepartCount };
}

function initPmSaveForm() {
    const form = document.getElementById('pm-save-form');
    const pmData = document.getElementById('pm-data');
    const banner = document.getElementById('pm-draft-banner');

    if (!form || !pmData) {
        return;
    }

    const pmScheduleId = Number(pmData.dataset.pmScheduleId);
    const expectedStatus = pmData.dataset.pmStatus;
    const userId = currentUserId();

    function showBanner(html) {
        if (!banner) {
            return;
        }

        banner.innerHTML = html;
        banner.classList.remove('hidden');
    }

    function hideBanner() {
        banner?.classList.add('hidden');
    }

    async function offerDraftRestore() {
        const overlay = await PmSave.getLocalPmSaveOverlay(pmScheduleId).catch(() => null);

        if (overlay?.local_save_status === 'saved_offline') {
            showBanner(
                `PM ini sudah <strong>disimpan secara offline</strong> pada ${formatTimestamp(overlay.local_save_updated_at)} `
                + 'dan sedang menunggu sinkronisasi ke server.'
            );

            return;
        }

        const draft = await Drafts.getDraftByReference({ userId, draftType: PmSave.DRAFT_TYPE, referenceId: pmScheduleId }).catch(() => null);

        if (!draft) {
            return;
        }

        const { extraSessions, problemCount, sparepartCount } = countExtras(draft.payload ?? {});
        const extrasNote = (extraSessions || problemCount || sparepartCount)
            ? ` Draft juga memiliki ${extraSessions} hari kerja tambahan, ${problemCount} problem, dan ${sparepartCount} `
              + 'sparepart yang perlu diisi ulang secara manual (tidak dapat dipulihkan otomatis).'
            : '';

        showBanner(
            `Ditemukan draft tersimpan dari sesi sebelumnya (${formatTimestamp(draft.updated_at)}). `
            + '<button type="button" id="pm-draft-restore" class="underline font-medium">Muat Draft</button>'
            + ' &middot; '
            + '<button type="button" id="pm-draft-dismiss" class="underline">Abaikan</button>'
            + extrasNote
        );

        document.getElementById('pm-draft-restore')?.addEventListener('click', () => {
            restoreSafeFieldsFromDraft(form, draft.payload ?? {});
            hideBanner();
        });
        document.getElementById('pm-draft-dismiss')?.addEventListener('click', hideBanner);
    }

    const autosaveDraft = debounce(() => {
        const payload = PmSave.buildPayloadFromForm(form);

        Drafts.saveDraft({ draftType: PmSave.DRAFT_TYPE, referenceId: pmScheduleId, payload, userId }).catch((error) => {
            console.warn('[pm/save] autosave draft failed', error);
        });
    }, AUTOSAVE_DEBOUNCE_MS);

    form.addEventListener('input', autosaveDraft);
    form.addEventListener('change', autosaveDraft);

    const submitButton = form.querySelector('button[type="submit"]');
    let submitting = false;

    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        if (submitting) {
            return;
        }

        submitting = true;
        if (submitButton) {
            submitButton.disabled = true;
        }

        try {
            const online = await probeServerReachable();

            if (online) {
                // Server reachable — submit exactly as this form always
                // has. The draft is cleared optimistically: Laravel's
                // own `old()`-backed validation-error redirect already
                // preserves the user's input if the server rejects it
                // (unchanged, pre-existing behavior), so there is nothing
                // left for the draft to protect once this request is on
                // its way.
                await Drafts.deleteDraft(
                    (await Drafts.getDraftByReference({ userId, draftType: PmSave.DRAFT_TYPE, referenceId: pmScheduleId }))?.draft_id
                ).catch(() => {});
                form.submit();

                return;
            }

            const payload = PmSave.buildPayloadFromForm(form);

            await PmSave.saveOffline({ pmScheduleId, payload, expectedStatus });
            hideBanner();
            showBanner(
                'PM berhasil <strong>disimpan secara offline</strong> dan akan disinkronkan ke server saat perangkat '
                + 'kembali online. Checklist belum tersedia sampai proses sinkronisasi selesai.'
            );
        } catch (error) {
            window.alert(error?.message || 'PM Save tidak dapat disimpan secara offline.');
        } finally {
            submitting = false;
            if (submitButton) {
                submitButton.disabled = false;
            }
        }
    });

    offerDraftRestore();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initPmSaveForm);
} else {
    initPmSaveForm();
}
