// @vitest-environment jsdom
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { resetTestDatabase } from '../../offline/__tests__/helpers.js';
import * as Queue from '../../offline/queue.js';
import * as Drafts from '../../offline/drafts.js';

const MACHINES = [
    { id: 1, machine_number: '3001', machine_type: 'NDE', area: 'WWD' },
    { id: 2, machine_number: '3002', machine_type: 'NDE', area: 'WWD' },
];
const CONDITIONS = { OKE: 'Oke', PANTAU: 'Pantau' };

function renderScanPage(userId = '7') {
    document.head.innerHTML = `<meta name="csrf-token" content="t"><meta name="app-user-id" content="${userId}">`;
    document.body.innerHTML = `
        <div id="oil-audit-scan-data" data-machines='${JSON.stringify(MACHINES)}' data-conditions='${JSON.stringify(CONDITIONS)}'></div>
        <div id="oil-audit-offline-feedback" class="hidden"></div>
        <div id="oil-audit-scanner-view">
            <p id="scanner-status"></p>
            <button id="start-scanner" type="button"></button>
            <form id="manual-machine-form" action="/oil-audits/entry/__machine__"><input id="manual-machine-number"></form>
        </div>
        <div id="oil-audit-offline-panel" class="hidden">
            <button id="oil-audit-offline-rescan" type="button"></button>
            <div id="oil-audit-offline-machine-info"></div>
            <div id="oil-audit-offline-condition-buttons"></div>
        </div>`;
}

async function loadScanPage(userId) {
    renderScanPage(userId);
    vi.resetModules();
    await import('../scan.js');
    // seeding the machine cache + draft restore are async
    await vi.waitFor(async () => {
        const { OilAuditCreate } = await import('../../offline/index.js');
        expect(await OilAuditCreate.findCachedMachineByNumber('3001')).toBeTruthy();
    });
}

async function scanMachine(number) {
    document.getElementById('manual-machine-number').value = number;
    document.getElementById('manual-machine-form').dispatchEvent(new Event('submit', { cancelable: true }));
    await vi.waitFor(() => expect(document.getElementById('oil-audit-offline-panel').classList.contains('hidden')).toBe(false));
}

const panelMachine = () => document.querySelector('#oil-audit-offline-machine-info h2')?.textContent;
const clickCondition = (label) => [...document.querySelectorAll('#oil-audit-offline-condition-buttons button')].find((b) => b.textContent === label).click();
const drafts = () => Drafts.listDraftsByType('7', 'OIL_AUDIT');

beforeEach(async () => {
    await resetTestDatabase();
    Object.defineProperty(navigator, 'onLine', { value: false, configurable: true });
    vi.stubGlobal('alert', vi.fn());
});

afterEach(() => {
    vi.unstubAllGlobals();
    Object.defineProperty(navigator, 'onLine', { value: true, configurable: true });
});

describe('Oil Audit scan page — offline machine-selection draft', () => {
    it('Scan mesin lain deletes the old draft: 3001 -> rescan -> 3002 PANTAU leaves no 3001 draft, even after reload', async () => {
        await loadScanPage();
        await scanMachine('3001');
        expect((await drafts()).map((d) => d.reference_id)).toEqual([1]);

        document.getElementById('oil-audit-offline-rescan').click();
        await vi.waitFor(async () => expect(await drafts()).toHaveLength(0));

        await scanMachine('3002');
        clickCondition('Pantau');
        await vi.waitFor(async () => expect(await Queue.listAll()).toHaveLength(1));

        expect((await Queue.listAll())[0].payload).toEqual({ machine_id: 2, condition: 'PANTAU' });
        expect(await drafts()).toHaveLength(0);

        await loadScanPage();
        await new Promise((resolve) => { setTimeout(resolve, 50); });
        expect(document.getElementById('oil-audit-offline-panel').classList.contains('hidden')).toBe(true);
    });

    it('reload before choosing a condition restores the (not cancelled) draft', async () => {
        await loadScanPage();
        await scanMachine('3001');

        await loadScanPage();
        await vi.waitFor(() => expect(panelMachine()).toBe('3001'));
    });

    it('a cancelled draft is not restored on reload', async () => {
        await loadScanPage();
        await scanMachine('3001');
        document.getElementById('oil-audit-offline-rescan').click();
        await vi.waitFor(async () => expect(await drafts()).toHaveLength(0));

        await loadScanPage();
        await new Promise((resolve) => { setTimeout(resolve, 50); });
        expect(document.getElementById('oil-audit-offline-panel').classList.contains('hidden')).toBe(true);
    });

    it('choosing a condition queues the audit and clears the draft, leaving the queued transaction intact', async () => {
        await loadScanPage();
        await scanMachine('3001');
        clickCondition('Oke');

        await vi.waitFor(async () => expect(await Queue.listAll()).toHaveLength(1));
        await vi.waitFor(async () => expect(await drafts()).toHaveLength(0));
        expect((await Queue.listAll())[0].status).toBe('pending');
    });

    it('a machine already audited today (queued) is refused: no panel, no draft, no second queue entry', async () => {
        await loadScanPage();
        await scanMachine('3001');
        clickCondition('Oke');
        await vi.waitFor(async () => expect(await Queue.listAll()).toHaveLength(1));

        document.getElementById('manual-machine-number').value = '3001';
        document.getElementById('manual-machine-form').dispatchEvent(new Event('submit', { cancelable: true }));
        await vi.waitFor(() => expect(window.alert).toHaveBeenCalledWith(expect.stringContaining('Mesin 3001 sudah memiliki audit oli untuk hari ini')));

        expect(await drafts()).toHaveLength(0);
        expect(await Queue.listAll()).toHaveLength(1);
    });
});
