/**
 * Oil Audit Follow Up form wiring — Task 8 (FreeDOMS offline-first:
 * Implement Offline Oil Audit Follow Up Save).
 *
 * Every function up to wireForm()'s dynamic-row helpers (newProblemRow,
 * newFindingRow, reindex, syncFindingOptions, checkDuplicates, the
 * add/remove-row click handler, the edit/view toggle) is the SAME logic
 * that used to live inline in follow-up-scripts.blade.php, moved here
 * unchanged (Task 8 explicitly forbids building a second dynamic-form
 * system — see the module docblock in offline/oilAuditFollowUp.js).
 * `history.blade.php` can render MANY of these forms on one page (one per
 * audit needing follow-up), so every helper below operates on one `form`
 * passed in, and initFollowUpForms() wires all of them.
 *
 * Task 8 additions on top of that unchanged logic: draft autosave/restore
 * per form, and an online-vs-offline decision on submit. Draft RESTORE
 * deliberately reuses the exact same newProblemRow()/newFindingRow()/
 * reindex() functions the Add Problem/Add Finding buttons use (see
 * restoreFollowUpIntoForm) — not a parallel DOM-building path — so a
 * restored draft can never produce a row shape the manual add-row flow
 * couldn't also produce. There is no TomSelect or other stateful widget on
 * this form (both selects are plain native <select> elements), so unlike
 * PM Save's sparepart rows, restoring every row here is structurally safe
 * to verify without a real browser: jsdom fully supports <template>/
 * cloneNode/<select> value assignment, and this file's test suite exercises
 * restoreFollowUpIntoForm() directly against that jsdom DOM.
 */
import { OilAuditFollowUp } from '../offline/index.js';
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

function readConfig() {
    const el = document.getElementById('followup-config');

    const parseJson = (raw, fallback) => {
        try {
            return raw ? JSON.parse(raw) : fallback;
        } catch {
            return fallback;
        }
    };

    return {
        findingOptions: parseJson(el?.dataset.findingOptions, []),
        genericFindingProblems: parseJson(el?.dataset.genericFindingProblems, []),
        genericFinding: parseJson(el?.dataset.genericFinding, ''),
    };
}

export function initFollowUpForms() {
    const problemTpl = document.getElementById('tpl-followup-problem-row');
    const findingTpl = document.getElementById('tpl-followup-finding-row');
    const forms = document.querySelectorAll('.js-followup-form');

    if (!problemTpl || !findingTpl || forms.length === 0) {
        return;
    }

    const { findingOptions: FINDING_OPTIONS, genericFindingProblems: GENERIC_FINDING_PROBLEMS, genericFinding: GENERIC_FINDING } = readConfig();

    function findingOptionsFor(problemValue) {
        return GENERIC_FINDING_PROBLEMS.includes(problemValue) ? [GENERIC_FINDING] : FINDING_OPTIONS;
    }

    // Rebuild every Finding <select> in a problem row so its options match
    // the currently selected Problem. Keeps the current value if it is
    // still valid, otherwise clears it. Unchanged from the original inline
    // script.
    function syncFindingOptions(problemRow) {
        const problemValue = (problemRow.querySelector('select')?.value || '').trim();
        const options = findingOptionsFor(problemValue);

        problemRow.querySelectorAll('.js-finding-row select').forEach((select) => {
            const current = select.value;
            const keep = options.includes(current) ? current : '';

            select.innerHTML =
                '<option value="">Pilih finding</option>' +
                options.map((opt) => {
                    const escaped = opt.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;');
                    return '<option value="' + escaped + '"' + (opt === keep ? ' selected' : '') + '>' + escaped + '</option>';
                }).join('');

            select.value = keep;
        });
    }

    function newFindingRow() {
        return findingTpl.content.firstElementChild.cloneNode(true);
    }

    function newProblemRow() {
        const row = problemTpl.content.firstElementChild.cloneNode(true);
        row.querySelector('.js-finding-list').appendChild(newFindingRow());
        return row;
    }

    function reindex(form) {
        const problemRows = form.querySelectorAll('.js-problem-row');

        problemRows.forEach((problemRow, pi) => {
            problemRow.querySelector('.js-problem-label').textContent = 'Problem #' + (pi + 1);

            const select = problemRow.querySelector('select');
            select.setAttribute('name', 'problems[' + pi + '][problem]');

            const findingRows = problemRow.querySelectorAll('.js-finding-row');
            findingRows.forEach((findingRow, fi) => {
                findingRow.querySelector('.js-finding-label').textContent = 'Finding #' + (fi + 1);
                findingRow.querySelector('select').setAttribute(
                    'name', 'problems[' + pi + '][findings][' + fi + '][finding]'
                );
                findingRow.querySelector('.js-remove-finding').hidden = findingRows.length <= 1;
            });

            syncFindingOptions(problemRow);
            problemRow.querySelector('.js-remove-problem').hidden = problemRows.length <= 1;
        });

        checkDuplicates(form);
    }

    // Two rules, both surfaced inside this audit card (never at the top of
    // the page) and both blocking submit while unresolved. Unchanged from
    // the original inline script.
    function checkDuplicates(form) {
        const messages = [];

        const problemSeen = new Set();
        const problemDupes = new Set();
        form.querySelectorAll('.js-problem-row').forEach((problemRow) => {
            const value = (problemRow.querySelector('select')?.value || '').trim();
            if (!value) return;
            if (problemSeen.has(value)) problemDupes.add(value);
            problemSeen.add(value);
        });
        problemDupes.forEach((value) => {
            messages.push('Problem "' + value + '" dipilih lebih dari sekali.');
        });

        form.querySelectorAll('.js-problem-row').forEach((problemRow, pi) => {
            const seen = new Set();
            const dupes = new Set();

            problemRow.querySelectorAll('.js-finding-row select').forEach((select) => {
                const value = select.value.trim();
                if (!value) return;
                if (seen.has(value)) {
                    dupes.add(value);
                }
                seen.add(value);
            });

            dupes.forEach((value) => {
                messages.push('Problem #' + (pi + 1) + ': finding "' + value + '" terisi lebih dari sekali.');
            });
        });

        const warning = form.querySelector('.js-dup-warning');
        const submit = form.querySelector('.js-followup-submit');

        if (messages.length) {
            if (warning) {
                warning.textContent = messages.join(' ');
                warning.hidden = false;
            }
            if (submit) submit.disabled = true;
        } else {
            if (warning) {
                warning.textContent = '';
                warning.hidden = true;
            }
            if (submit) submit.disabled = false;
        }
    }

    function oilAuditIdFor(form) {
        return Number(form.dataset.followupForm);
    }

    // The create form has no @method override (real POST); the update form
    // has @method('PUT'), which Blade renders as this hidden input. Reading
    // it back is how this device knows, right now, whether it believes a
    // follow-up already exists for this audit — sent as expected_state so
    // the server can detect if that has changed since (Task 8 section 11).
    function followUpExistsFor(form) {
        return Boolean(form.querySelector('input[name="_method"][value="PUT"]'));
    }

    function getBanner(form) {
        return form.querySelector('.js-followup-banner');
    }

    function showBanner(form, html) {
        const banner = getBanner(form);

        if (!banner) {
            return;
        }

        banner.innerHTML = html;
        banner.classList.remove('hidden');
    }

    function hideBanner(form) {
        getBanner(form)?.classList.add('hidden');
    }

    /**
     * Rebuilds a form's problem/finding rows from draft data using the
     * SAME row generator (newProblemRow/newFindingRow) and the SAME
     * renumbering pass (reindex) the manual Add Problem/Add Finding
     * buttons use — see module docblock. Always leaves at least one
     * problem row with at least one finding row (never zero rows), exactly
     * like every other entry point into this form.
     */
    function restoreFollowUpIntoForm(form, problems, actionTaken) {
        const list = form.querySelector('.js-problem-list');

        if (!list) {
            return;
        }

        const rows = problems && problems.length ? problems : [{ problem: '', findings: [{ finding: '' }] }];

        list.innerHTML = '';
        rows.forEach((problemData) => {
            const row = newProblemRow();
            const findingList = row.querySelector('.js-finding-list');

            findingList.innerHTML = '';
            const findings = problemData?.findings?.length ? problemData.findings : [{ finding: '' }];
            findings.forEach(() => findingList.appendChild(newFindingRow()));

            list.appendChild(row);
        });

        reindex(form);

        form.querySelectorAll('.js-problem-row').forEach((problemRow, pi) => {
            const problemData = rows[pi];

            if (!problemData) {
                return;
            }

            const problemSelect = problemRow.querySelector('select');
            if (problemSelect) {
                problemSelect.value = problemData.problem ?? '';
            }

            // Rebuild finding options for whichever Problem was just
            // restored BEFORE setting finding values, exactly like a user
            // picking the Problem first would trigger.
            syncFindingOptions(problemRow);

            const findings = problemData.findings?.length ? problemData.findings : [{ finding: '' }];
            problemRow.querySelectorAll('.js-finding-row select').forEach((findingSelect, fi) => {
                findingSelect.value = findings[fi]?.finding ?? '';
            });
        });

        const actionTextarea = form.querySelector('textarea[name="action_taken"]');
        if (actionTextarea && actionTaken !== undefined) {
            actionTextarea.value = actionTaken;
        }

        checkDuplicates(form);
    }

    async function offerDraftRestore(form) {
        const oilAuditId = oilAuditIdFor(form);
        const userId = currentUserId();

        const overlay = await OilAuditFollowUp.getLocalOilAuditFollowUpOverlay(oilAuditId).catch(() => null);

        if (overlay?.local_status === 'saved_offline') {
            showBanner(
                form,
                `Tindak lanjut ini sudah <strong>disimpan secara offline</strong> pada ${formatTimestamp(overlay.local_updated_at)} `
                + 'dan sedang menunggu sinkronisasi ke server.'
            );

            return;
        }

        const draft = await Drafts.getDraftByReference({
            userId,
            draftType: OilAuditFollowUp.DRAFT_TYPE,
            referenceId: oilAuditId,
        }).catch(() => null);

        if (!draft) {
            return;
        }

        showBanner(
            form,
            `Ditemukan draft tindak lanjut tersimpan dari sesi sebelumnya (${formatTimestamp(draft.updated_at)}). `
            + '<button type="button" class="js-followup-draft-restore underline font-medium">Muat Draft</button>'
            + ' &middot; '
            + '<button type="button" class="js-followup-draft-dismiss underline">Abaikan</button>'
        );

        getBanner(form)?.querySelector('.js-followup-draft-restore')?.addEventListener('click', () => {
            restoreFollowUpIntoForm(form, draft.payload?.problems ?? [], draft.payload?.action_taken ?? '');
            hideBanner(form);
        });
        getBanner(form)?.querySelector('.js-followup-draft-dismiss')?.addEventListener('click', () => hideBanner(form));
    }

    function wireForm(form) {
        reindex(form);

        form.addEventListener('click', (event) => {
            const target = event.target;

            if (target.closest('.js-add-problem')) {
                form.querySelector('.js-problem-list').appendChild(newProblemRow());
                reindex(form);
            } else if (target.closest('.js-add-finding')) {
                const findingList = target.closest('.js-problem-row').querySelector('.js-finding-list');
                findingList.appendChild(newFindingRow());
                reindex(form);
            } else if (target.closest('.js-remove-problem')) {
                if (form.querySelectorAll('.js-problem-row').length <= 1) return;
                target.closest('.js-problem-row').remove();
                reindex(form);
            } else if (target.closest('.js-remove-finding')) {
                const problemRow = target.closest('.js-problem-row');
                if (problemRow.querySelectorAll('.js-finding-row').length <= 1) return;
                target.closest('.js-finding-row').remove();
                reindex(form);
            }
        });

        form.addEventListener('change', (event) => {
            const problemRow = event.target.closest?.('.js-problem-row');

            if (problemRow && event.target === problemRow.querySelector('select')) {
                syncFindingOptions(problemRow);
            }

            checkDuplicates(form);
        });

        const autosaveDraft = debounce(() => {
            const oilAuditId = oilAuditIdFor(form);
            const { problems, actionTaken } = OilAuditFollowUp.buildFollowUpFromForm(form);

            Drafts.saveDraft({
                draftType: OilAuditFollowUp.DRAFT_TYPE,
                referenceId: oilAuditId,
                payload: { oil_audit_id: oilAuditId, problems, action_taken: actionTaken },
                userId: currentUserId(),
            }).catch((error) => {
                console.warn('[oil-audits/follow-up] autosave draft failed', error);
            });
        }, AUTOSAVE_DEBOUNCE_MS);

        form.addEventListener('input', autosaveDraft);
        form.addEventListener('change', autosaveDraft);

        let submitting = false;

        form.addEventListener('submit', async (event) => {
            event.preventDefault();

            checkDuplicates(form);
            if (form.querySelector('.js-followup-submit')?.disabled) {
                return;
            }

            if (submitting) {
                return;
            }

            submitting = true;
            const submitButton = form.querySelector('.js-followup-submit');
            if (submitButton) {
                submitButton.disabled = true;
            }

            const oilAuditId = oilAuditIdFor(form);
            const userId = currentUserId();

            try {
                const online = await probeServerReachable();

                if (online) {
                    // Server reachable — submit exactly as this form
                    // always has. The draft is cleared optimistically
                    // here, same reasoning as pm/checklist.js: a
                    // server-side rejection still re-renders this page
                    // with the submitted values via Laravel's own
                    // redirect-with-old-input behaviour (unchanged,
                    // pre-existing — see follow-up-fields.blade.php's
                    // $fuIsOld/old() handling), so there is nothing left
                    // for the draft to protect once this request is on
                    // its way.
                    await Drafts.deleteDraft(
                        (await Drafts.getDraftByReference({ userId, draftType: OilAuditFollowUp.DRAFT_TYPE, referenceId: oilAuditId }))?.draft_id
                    ).catch(() => {});
                    form.submit();

                    return;
                }

                const { problems, actionTaken } = OilAuditFollowUp.buildFollowUpFromForm(form);

                await OilAuditFollowUp.saveOffline({
                    oilAuditId,
                    problems,
                    actionTaken,
                    followUpExists: followUpExistsFor(form),
                });

                hideBanner(form);
                showBanner(
                    form,
                    'Tindak lanjut berhasil <strong>disimpan secara offline</strong> dan akan disinkronkan ke server saat '
                    + 'perangkat kembali online.'
                );
            } catch (error) {
                window.alert(error?.message || 'Tindak lanjut tidak dapat disimpan secara offline.');
            } finally {
                submitting = false;
                if (submitButton) {
                    submitButton.disabled = false;
                }
            }
        });

        return offerDraftRestore(form);
    }

    // Returns a promise that resolves once every form's draft-restore
    // offer has been checked — fire-and-forget for the real page (the
    // bottom of this file never awaits it), but useful for tests to await
    // deterministically instead of guessing a timeout.
    const restored = Promise.all(Array.from(forms).map(wireForm));

    // Toggle the read-only view <-> edit form for an existing follow-up —
    // unchanged from the original inline script.
    document.querySelectorAll('.js-followup-edit-toggle').forEach((button) => {
        button.addEventListener('click', () => {
            const id = button.dataset.target;
            const view = document.querySelector('[data-followup-view="' + id + '"]');
            const editForm = document.querySelector('[data-followup-form="' + id + '"]');
            if (view) view.hidden = !view.hidden;
            if (editForm) {
                editForm.hidden = !editForm.hidden;
                if (!editForm.hidden) reindex(editForm);
            }
        });
    });

    return restored;
}

if (typeof document !== 'undefined') {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initFollowUpForms);
    } else {
        initFollowUpForms();
    }
}
