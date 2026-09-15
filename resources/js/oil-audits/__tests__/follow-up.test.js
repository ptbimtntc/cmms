// @vitest-environment jsdom
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import * as Queue from '../../offline/queue.js';
import * as Drafts from '../../offline/drafts.js';
import { getLocalOilAuditFollowUpOverlay } from '../../offline/masterData.js';
import { resetTestDatabase } from '../../offline/__tests__/helpers.js';
import { initFollowUpForms } from '../follow-up.js';

const PROBLEM_OPTIONS = ['Bocor Oli', 'Baut Kendor', 'Level Rendah'];
const FINDING_OPTIONS = ['Kapstan 1', 'Kapstan 2', 'Kapstan 3'];

function optionsHtml(options, selected) {
    return options.map((opt) => `<option value="${opt}"${opt === selected ? ' selected' : ''}>${opt}</option>`).join('');
}

function problemRowHtml({ problem = '', finding = '' } = {}) {
    return `
        <div class="js-problem-row">
            <label class="js-problem-label">Problem #1</label>
            <select name="problems[0][problem]">
                <option value="">Pilih problem</option>
                ${optionsHtml(PROBLEM_OPTIONS, problem)}
            </select>
            <button type="button" class="js-remove-problem" hidden>Hapus</button>
            <div class="js-finding-list">
                <div class="js-finding-row">
                    <label class="js-finding-label">Finding #1</label>
                    <select name="problems[0][findings][0][finding]">
                        <option value="">Pilih finding</option>
                        ${optionsHtml(FINDING_OPTIONS, finding)}
                    </select>
                    <button type="button" class="js-remove-finding" hidden>Hapus</button>
                </div>
            </div>
            <button type="button" class="js-add-finding">+ Add Finding</button>
        </div>
    `;
}

function formHtml({ oilAuditId, mode = 'create' }) {
    return `
        <form class="js-followup-form" data-followup-form="${oilAuditId}" ${mode === 'edit' ? 'hidden' : ''}>
            ${mode === 'edit' ? '<input type="hidden" name="_method" value="PUT">' : ''}
            <input type="hidden" name="_followup_audit" value="${oilAuditId}">
            <div class="js-followup-banner hidden"></div>
            <div class="js-problem-list">${problemRowHtml()}</div>
            <div class="js-dup-warning" hidden></div>
            <button type="button" class="js-add-problem">+ Add Problem</button>
            <textarea name="action_taken"></textarea>
            <button type="submit" class="js-followup-submit">Simpan</button>
        </form>
    `;
}

function pageHtml(forms) {
    return `
        <div id="followup-config"
            data-finding-options='${JSON.stringify(FINDING_OPTIONS)}'
            data-generic-finding-problems='${JSON.stringify([])}'
            data-generic-finding='${JSON.stringify('')}'
            hidden></div>

        <template id="tpl-followup-problem-row">${problemRowHtml()}</template>
        <template id="tpl-followup-finding-row">
            <div class="js-finding-row">
                <label class="js-finding-label">Finding</label>
                <select name="">
                    <option value="">Pilih finding</option>
                    ${optionsHtml(FINDING_OPTIONS)}
                </select>
                <button type="button" class="js-remove-finding" hidden>Hapus</button>
            </div>
        </template>

        ${forms.map(formHtml).join('\n')}
    `;
}

beforeEach(async () => {
    await resetTestDatabase();
    document.head.innerHTML = '<meta name="csrf-token" content="test-token">';
    document.body.innerHTML = '';
});

afterEach(() => {
    vi.unstubAllGlobals();
});

describe('initFollowUpForms — dynamic row add/remove (unchanged from the original inline script)', () => {
    it('Add Problem / Add Finding append rows and re-index names densely', async () => {
        document.body.innerHTML = pageHtml([{ oilAuditId: 1, mode: 'create' }]);
        await initFollowUpForms();

        const form = document.querySelector('.js-followup-form');

        form.querySelector('.js-add-problem').click();

        const problemRows = form.querySelectorAll('.js-problem-row');
        expect(problemRows).toHaveLength(2);
        expect(problemRows[0].querySelector('select').name).toBe('problems[0][problem]');
        expect(problemRows[1].querySelector('select').name).toBe('problems[1][problem]');

        problemRows[0].querySelector('.js-add-finding').click();
        const findingRows = problemRows[0].querySelectorAll('.js-finding-row');
        expect(findingRows).toHaveLength(2);
        expect(findingRows[0].querySelector('select').name).toBe('problems[0][findings][0][finding]');
        expect(findingRows[1].querySelector('select').name).toBe('problems[0][findings][1][finding]');
    });

    it('does not allow removing the last remaining problem/finding row', async () => {
        document.body.innerHTML = pageHtml([{ oilAuditId: 1, mode: 'create' }]);
        await initFollowUpForms();

        const form = document.querySelector('.js-followup-form');

        form.querySelector('.js-remove-problem').click();

        expect(form.querySelectorAll('.js-problem-row')).toHaveLength(1);
    });
});

describe('initFollowUpForms — draft restore (Task 8)', () => {
    it('offers a "Load Draft" banner when a draft exists for this oil_audit_id, and does nothing for a form with no draft', async () => {
        await Drafts.saveDraft({
            draftType: 'OIL_AUDIT_FOLLOW_UP',
            referenceId: 42,
            payload: { oil_audit_id: 42, problems: [{ problem: 'Bocor Oli', findings: [{ finding: 'Kapstan 1' }] }], action_taken: 'x' },
            userId: null,
        });

        document.body.innerHTML = pageHtml([
            { oilAuditId: 42, mode: 'create' },
            { oilAuditId: 99, mode: 'create' },
        ]);
        await initFollowUpForms();

        const draftForm = document.querySelector('[data-followup-form="42"]');
        const otherForm = document.querySelector('[data-followup-form="99"]');

        expect(draftForm.querySelector('.js-followup-banner').classList.contains('hidden')).toBe(false);
        expect(draftForm.querySelector('.js-followup-draft-restore')).not.toBeNull();
        expect(otherForm.querySelector('.js-followup-banner').classList.contains('hidden')).toBe(true);
    });

    it('restores multiple problems, each with multiple findings, WITHOUT duplicate rows and with correctly re-indexed names', async () => {
        await Drafts.saveDraft({
            draftType: 'OIL_AUDIT_FOLLOW_UP',
            referenceId: 42,
            payload: {
                oil_audit_id: 42,
                problems: [
                    { problem: 'Bocor Oli', findings: [{ finding: 'Kapstan 1' }, { finding: 'Kapstan 2' }] },
                    { problem: 'Baut Kendor', findings: [{ finding: 'Kapstan 3' }] },
                ],
                action_taken: 'Ganti seal dan kencangkan baut.',
            },
            userId: null,
        });

        document.body.innerHTML = pageHtml([{ oilAuditId: 42, mode: 'create' }]);
        await initFollowUpForms();

        const form = document.querySelector('.js-followup-form');

        form.querySelector('.js-followup-draft-restore').click();

        const problemRows = form.querySelectorAll('.js-problem-row');
        expect(problemRows).toHaveLength(2);

        expect(problemRows[0].querySelector('select').name).toBe('problems[0][problem]');
        expect(problemRows[0].querySelector('select').value).toBe('Bocor Oli');
        const firstFindingRows = problemRows[0].querySelectorAll('.js-finding-row');
        expect(firstFindingRows).toHaveLength(2);
        expect(firstFindingRows[0].querySelector('select').name).toBe('problems[0][findings][0][finding]');
        expect(firstFindingRows[0].querySelector('select').value).toBe('Kapstan 1');
        expect(firstFindingRows[1].querySelector('select').value).toBe('Kapstan 2');

        expect(problemRows[1].querySelector('select').name).toBe('problems[1][problem]');
        expect(problemRows[1].querySelector('select').value).toBe('Baut Kendor');
        const secondFindingRows = problemRows[1].querySelectorAll('.js-finding-row');
        expect(secondFindingRows).toHaveLength(1);
        expect(secondFindingRows[0].querySelector('select').name).toBe('problems[1][findings][0][finding]');
        expect(secondFindingRows[0].querySelector('select').value).toBe('Kapstan 3');

        expect(form.querySelector('textarea[name="action_taken"]').value).toBe('Ganti seal dan kencangkan baut.');

        // Restoring must never leave the dup-warning showing / submit
        // disabled for genuinely non-duplicate restored data.
        expect(form.querySelector('.js-dup-warning').hidden).toBe(true);
        expect(form.querySelector('.js-followup-submit').disabled).toBe(false);

        // The banner is dismissed once the draft is loaded.
        expect(form.querySelector('.js-followup-banner').classList.contains('hidden')).toBe(true);
    });

    it('shows a "saved offline, pending sync" banner instead of a draft-restore offer when this audit already has a saved-offline overlay', async () => {
        const { setLocalOilAuditFollowUpOverlay } = await import('../../offline/masterData.js');

        await setLocalOilAuditFollowUpOverlay(42, {
            local_status: 'saved_offline',
            pending_operation_uuid: 'abc-123',
            local_updated_at: new Date().toISOString(),
        });

        document.body.innerHTML = pageHtml([{ oilAuditId: 42, mode: 'create' }]);
        await initFollowUpForms();

        const form = document.querySelector('.js-followup-form');
        const banner = form.querySelector('.js-followup-banner');

        expect(banner.classList.contains('hidden')).toBe(false);
        expect(banner.innerHTML).toContain('disimpan secara offline');
        expect(form.querySelector('.js-followup-draft-restore')).toBeNull();
    });
});

describe('initFollowUpForms — submit decision (online vs offline) and multi-form pages', () => {
    it('online: submits the form natively and does not call saveOffline (no queue entry created)', async () => {
        document.body.innerHTML = pageHtml([{ oilAuditId: 42, mode: 'create' }]);
        await initFollowUpForms();

        const form = document.querySelector('.js-followup-form');
        form.querySelector('select').value = 'Bocor Oli';
        form.querySelectorAll('select')[1].value = 'Kapstan 1';
        form.querySelector('textarea[name="action_taken"]').value = 'Ganti seal.';

        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: true }));
        const submitSpy = vi.fn();
        form.submit = submitSpy;

        form.dispatchEvent(new window.Event('submit', { bubbles: true, cancelable: true }));

        await vi.waitFor(() => expect(submitSpy).toHaveBeenCalledTimes(1));

        expect(await Queue.listAll()).toHaveLength(0);
    });

    it('offline: queues exactly one OIL_AUDIT_FOLLOW_UP_SAVE operation and never calls form.submit()', async () => {
        document.body.innerHTML = pageHtml([{ oilAuditId: 42, mode: 'create' }]);
        await initFollowUpForms();

        const form = document.querySelector('.js-followup-form');
        form.querySelector('select').value = 'Bocor Oli';
        form.querySelectorAll('select')[1].value = 'Kapstan 1';
        form.querySelector('textarea[name="action_taken"]').value = 'Ganti seal.';

        vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new TypeError('Network request failed')));
        const submitSpy = vi.fn();
        form.submit = submitSpy;

        form.dispatchEvent(new window.Event('submit', { bubbles: true, cancelable: true }));

        await vi.waitFor(async () => {
            expect(await Queue.listAll()).toHaveLength(1);
        });

        expect(submitSpy).not.toHaveBeenCalled();

        const queued = (await Queue.listAll())[0];
        expect(queued.transaction_type).toBe('OIL_AUDIT_FOLLOW_UP_SAVE');
        expect(queued.payload.oil_audit_id).toBe(42);
        expect(queued.expected_state).toEqual({ follow_up_exists: false });
    });

    it('sends follow_up_exists: true for an edit-mode (@method PUT) form', async () => {
        document.body.innerHTML = pageHtml([{ oilAuditId: 42, mode: 'edit' }]);
        await initFollowUpForms();

        const form = document.querySelector('.js-followup-form');
        form.hidden = false;
        form.querySelector('select').value = 'Bocor Oli';
        form.querySelectorAll('select')[1].value = 'Kapstan 1';
        form.querySelector('textarea[name="action_taken"]').value = 'Ganti seal.';

        vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new TypeError('Network request failed')));
        form.submit = vi.fn();

        form.dispatchEvent(new window.Event('submit', { bubbles: true, cancelable: true }));

        await vi.waitFor(async () => {
            expect(await Queue.listAll()).toHaveLength(1);
        });

        const queued = (await Queue.listAll())[0];
        expect(queued.expected_state).toEqual({ follow_up_exists: true });
    });

    it('wires EVERY .js-followup-form on the page independently (multiple audits needing follow-up on one page)', async () => {
        document.body.innerHTML = pageHtml([
            { oilAuditId: 1, mode: 'create' },
            { oilAuditId: 2, mode: 'create' },
        ]);
        await initFollowUpForms();

        const forms = document.querySelectorAll('.js-followup-form');
        expect(forms).toHaveLength(2);

        forms.forEach((form) => {
            form.querySelector('select').value = 'Bocor Oli';
            form.querySelectorAll('select')[1].value = 'Kapstan 1';
            form.querySelector('textarea[name="action_taken"]').value = 'Ganti seal.';
            form.submit = vi.fn();
        });

        vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new TypeError('Network request failed')));

        forms[0].dispatchEvent(new window.Event('submit', { bubbles: true, cancelable: true }));
        forms[1].dispatchEvent(new window.Event('submit', { bubbles: true, cancelable: true }));

        await vi.waitFor(async () => {
            expect(await Queue.listAll()).toHaveLength(2);
        });

        const auditIds = (await Queue.listAll()).map((op) => op.payload.oil_audit_id).sort();
        expect(auditIds).toEqual([1, 2]);
    });

    it('a duplicate problem/finding disables submit — the offline path is never reached while it is disabled', async () => {
        document.body.innerHTML = pageHtml([{ oilAuditId: 42, mode: 'create' }]);
        await initFollowUpForms();

        const form = document.querySelector('.js-followup-form');

        form.querySelector('.js-add-problem').click();
        // Set both problem rows to the SAME value — a duplicate.
        const problemRows = form.querySelectorAll('.js-problem-row');
        problemRows[0].querySelector('select').value = 'Bocor Oli';
        problemRows[0].querySelector('select').dispatchEvent(new window.Event('change', { bubbles: true }));
        problemRows[1].querySelector('select').value = 'Bocor Oli';
        problemRows[1].querySelector('select').dispatchEvent(new window.Event('change', { bubbles: true }));

        expect(form.querySelector('.js-followup-submit').disabled).toBe(true);
        expect(form.querySelector('.js-dup-warning').hidden).toBe(false);

        vi.stubGlobal('fetch', vi.fn());
        form.submit = vi.fn();

        form.dispatchEvent(new window.Event('submit', { bubbles: true, cancelable: true }));

        // Give any (incorrect) async path a chance to run before asserting
        // nothing happened.
        await new Promise((resolve) => setTimeout(resolve, 10));

        expect(form.submit).not.toHaveBeenCalled();
        expect(await Queue.listAll()).toHaveLength(0);
    });
});

describe('initFollowUpForms — edit/view toggle (unchanged from the original inline script)', () => {
    it('toggle button shows/hides the view and the edit form, and re-indexes the edit form when it becomes visible', async () => {
        document.body.innerHTML = `
            <div data-followup-view="7"></div>
            <button type="button" class="js-followup-edit-toggle" data-target="7">Edit</button>
            ${pageHtml([{ oilAuditId: 7, mode: 'edit' }])}
        `;
        await initFollowUpForms();

        const view = document.querySelector('[data-followup-view="7"]');
        const form = document.querySelector('[data-followup-form="7"]');

        expect(form.hidden).toBe(true);

        document.querySelector('.js-followup-edit-toggle').click();

        expect(view.hidden).toBe(true);
        expect(form.hidden).toBe(false);
    });
});
