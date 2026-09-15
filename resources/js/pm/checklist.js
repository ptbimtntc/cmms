/**
 * PM Checklist form wiring — Task 6 (FreeDOMS offline-first: Implement
 * Offline PM Checklist Save). Adds an online-vs-offline decision on
 * submit (online submits exactly as before — native form POST,
 * untouched) and local draft autosave/restore using the storage
 * abstraction from Task 3/5.
 *
 * Deliberately a separate transaction/queue/draft from PM_SAVE (Task 5) —
 * "Save Checklist" only ever submits THIS form (PM_CHECKLIST_SAVE); Fill
 * PM's own form/queue/draft is completely untouched by this file.
 *
 * The checklist form has no dynamic add/remove rows, no TomSelect, and no
 * Livewire component (every row comes straight from the machine's fixed
 * MachineChecklist master list, rendered once by the server) — unlike PM
 * Save's form, that makes a full draft restore (every checkbox + remark)
 * safe here; see Task 6 section 7 / the Task 6 report for why this is
 * deliberately NOT attempted for PM_SAVE's dynamic rows.
 */
import { PmChecklist } from '../offline/index.js';
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
 * Every checklist row's fields are plain checkboxes + a hidden remarks
 * input, keyed by the SAME machine_checklist_id the draft itself stored
 * (matching by id rather than by row index, in case the master checklist
 * list itself changed between when the draft was made and now).
 */
function restoreChecklistsIntoForm(form, checklists) {
    (checklists ?? []).forEach((item) => {
        const rowInputs = form.querySelectorAll(`input[name^="checklists["][name$="[machine_checklist_id]"]`);

        for (const idInput of rowInputs) {
            if (String(idInput.value) !== String(item.machine_checklist_id)) {
                continue;
            }

            const match = idInput.name.match(/^checklists\[(\d+)\]/);

            if (!match) {
                continue;
            }

            const index = match[1];

            ['clean', 'check', 'lubrication', 'replace'].forEach((field) => {
                const checkbox = form.querySelector(`input[type="checkbox"][name="checklists[${index}][${field}]"]`);

                if (checkbox) {
                    checkbox.checked = item[field] === 'YES';
                }
            });

            const remarksInput = form.querySelector(`#remark-${index}`);

            if (remarksInput && item.remarks !== undefined) {
                remarksInput.value = item.remarks;

                const remarkButton = form.querySelector(`.remark-btn[data-index="${index}"]`);

                if (remarkButton) {
                    remarkButton.textContent = item.remarks ? '📝' : '✏️';
                    remarkButton.classList.toggle('text-green-600', Boolean(item.remarks));
                }
            }

            break;
        }
    });
}

function initPmChecklistForm() {
    const form = document.getElementById('pm-checklist-form');
    const pmData = document.getElementById('pm-checklist-data');
    const banner = document.getElementById('pm-checklist-draft-banner');

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
        const overlay = await PmChecklist.getLocalPmChecklistOverlay(pmScheduleId).catch(() => null);

        if (overlay?.local_checklist_status === 'saved_offline') {
            showBanner(
                `Checklist PM ini sudah <strong>disimpan secara offline</strong> pada ${formatTimestamp(overlay.local_checklist_updated_at)} `
                + 'dan sedang menunggu sinkronisasi ke server.'
            );

            return;
        }

        const draft = await Drafts.getDraftByReference({ userId, draftType: PmChecklist.DRAFT_TYPE, referenceId: pmScheduleId }).catch(() => null);

        if (!draft) {
            return;
        }

        showBanner(
            `Ditemukan draft checklist tersimpan dari sesi sebelumnya (${formatTimestamp(draft.updated_at)}). `
            + '<button type="button" id="pm-checklist-draft-restore" class="underline font-medium">Muat Draft</button>'
            + ' &middot; '
            + '<button type="button" id="pm-checklist-draft-dismiss" class="underline">Abaikan</button>'
        );

        document.getElementById('pm-checklist-draft-restore')?.addEventListener('click', () => {
            restoreChecklistsIntoForm(form, draft.payload?.checklists ?? []);
            hideBanner();
        });
        document.getElementById('pm-checklist-draft-dismiss')?.addEventListener('click', hideBanner);
    }

    const autosaveDraft = debounce(() => {
        const checklists = PmChecklist.buildChecklistsFromForm(form);

        Drafts.saveDraft({ draftType: PmChecklist.DRAFT_TYPE, referenceId: pmScheduleId, payload: { checklists }, userId }).catch((error) => {
            console.warn('[pm/checklist] autosave draft failed', error);
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
                // has. The draft is cleared optimistically here, same
                // reasoning as pm/save.js: a server-side rejection still
                // re-renders the checklist with the submitted values via
                // Laravel's own redirect-with-old-input behaviour
                // (unchanged, pre-existing), so there is nothing left for
                // the draft to protect once this request is on its way.
                await Drafts.deleteDraft(
                    (await Drafts.getDraftByReference({ userId, draftType: PmChecklist.DRAFT_TYPE, referenceId: pmScheduleId }))?.draft_id
                ).catch(() => {});
                form.submit();

                return;
            }

            const checklists = PmChecklist.buildChecklistsFromForm(form);

            await PmChecklist.saveOffline({ pmScheduleId, checklists, expectedStatus });
            hideBanner();
            showBanner(
                'Checklist berhasil <strong>disimpan secara offline</strong> dan akan disinkronkan ke server saat '
                + 'perangkat kembali online.'
            );
        } catch (error) {
            window.alert(error?.message || 'Checklist tidak dapat disimpan secara offline.');
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
    document.addEventListener('DOMContentLoaded', initPmChecklistForm);
} else {
    initPmChecklistForm();
}
