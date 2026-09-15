/**
 * Oil Audit scan page wiring — Task 7 (FreeDOMS offline-first: Implement
 * Offline Oil Audit Create). Extracted from the inline <script> that used
 * to live in oil-audits/scan.blade.php (QR/manual machine lookup) and
 * extended with an offline path:
 *
 *  ONLINE  (or reachability unknown but the probe succeeds): behaves
 *          EXACTLY as before — window.location.assign() to the real
 *          entry page. Nothing about this path changed.
 *
 *  OFFLINE: a fresh server navigation is not possible (the entry page is
 *           dynamic per machine number and the Service Worker never
 *           caches dynamic pages — Task 3), so instead this looks the
 *           scanned/typed machine number up in the local machine cache
 *           (seeded from #oil-audit-scan-data below on page load) and, if
 *           found, shows an inline entry panel on THIS page — never a
 *           network request, never a machine invented from an
 *           unrecognized number (Task 7 section 6).
 */
import { OilAuditCreate, MasterDataCache, Drafts } from '../offline/index.js';
import { currentUserId } from '../offline/scope.js';
import { probeServerReachable } from '../offline/network.js';

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
 * Seeds the local machine cache from the page's own embedded data — no
 * extra request, since the scan page already had this list server-side
 * (Task 7 section 5). Refreshed every time this page is loaded ONLINE, so
 * the cache never drifts far from the server; the server stays
 * authoritative regardless (OilAuditCreateService re-resolves the Machine
 * row itself at sync time either way — see oilAuditCreate.js's docblock).
 */
async function seedMachineCache(machines) {
    if (!Array.isArray(machines) || machines.length === 0) {
        return;
    }

    await MasterDataCache.clearMasterDataCategory(OilAuditCreate.MACHINE_CATEGORY);

    await Promise.all(machines.map((machine) => MasterDataCache.putMasterData(
        OilAuditCreate.MACHINE_CATEGORY,
        machine.id,
        machine
    )));
}

function initScanPage() {
    const dataEl = document.getElementById('oil-audit-scan-data');
    const scannerView = document.getElementById('oil-audit-scanner-view');
    const offlinePanel = document.getElementById('oil-audit-offline-panel');
    const feedback = document.getElementById('oil-audit-offline-feedback');

    if (!dataEl || !scannerView || !offlinePanel) {
        return;
    }

    let machines = [];
    let conditions = {};

    try {
        machines = JSON.parse(dataEl.dataset.machines || '[]');
        conditions = JSON.parse(dataEl.dataset.conditions || '{}');
    } catch {
        machines = [];
        conditions = {};
    }

    seedMachineCache(machines);

    const userId = currentUserId();

    function showFeedback(html) {
        if (!feedback) {
            return;
        }

        feedback.innerHTML = html;
        feedback.classList.remove('hidden');
    }

    function hideFeedback() {
        feedback?.classList.add('hidden');
    }

    function showScannerView() {
        scannerView.classList.remove('hidden');
        offlinePanel.classList.add('hidden');
    }

    function showOfflinePanel(machine) {
        scannerView.classList.add('hidden');
        offlinePanel.classList.remove('hidden');

        const info = document.getElementById('oil-audit-offline-machine-info');

        if (info) {
            info.innerHTML = `
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-sky-700">Mesin terdeteksi (offline)</p>
                <h2 class="mt-1 font-mono text-3xl font-bold tracking-tight text-slate-950">${machine.machine_number}</h2>
                <p class="mt-1 text-sm text-slate-600">${machine.machine_type} &middot; ${machine.area}</p>
            `;
        }

        const buttonsContainer = document.getElementById('oil-audit-offline-condition-buttons');

        if (buttonsContainer) {
            buttonsContainer.innerHTML = '';

            Object.entries(conditions).forEach(([key, label]) => {
                const button = document.createElement('button');

                button.type = 'button';
                button.textContent = label;
                button.className = 'flex min-h-20 w-full flex-col items-center justify-center rounded-2xl border-2 border-slate-200 bg-slate-50 p-4 text-center text-sm font-bold text-slate-800 transition hover:border-sky-500 hover:bg-sky-50 focus:outline-none focus:ring-4 focus:ring-sky-200';
                button.addEventListener('click', () => submitOfflineAudit(machine, key, button));
                buttonsContainer.appendChild(button);
            });
        }
    }

    async function submitOfflineAudit(machine, condition, button) {
        const allButtons = document.querySelectorAll('#oil-audit-offline-condition-buttons button');

        allButtons.forEach((btn) => { btn.disabled = true; });
        button.classList.add('opacity-60');

        try {
            await OilAuditCreate.saveOffline({ machineId: machine.id, condition });

            await Drafts.deleteDraft(
                (await Drafts.getDraftByReference({ userId, draftType: OilAuditCreate.DRAFT_TYPE, referenceId: machine.id }))?.draft_id
            ).catch(() => {});

            showFeedback(
                `Audit oli mesin <strong>${machine.machine_number}</strong> berhasil <strong>disimpan secara offline</strong> `
                + 'dan akan disinkronkan ke server saat perangkat kembali online. Siap scan mesin berikutnya.'
            );
            showScannerView();
        } catch (error) {
            window.alert(error?.message || 'Audit oli tidak dapat disimpan secara offline.');
            allButtons.forEach((btn) => { btn.disabled = false; });
            button.classList.remove('opacity-60');
        }
    }

    document.getElementById('oil-audit-offline-rescan')?.addEventListener('click', () => {
        showScannerView();
    });

    async function openMachine(machineNumberRaw, statusEl) {
        const value = (machineNumberRaw ?? '').trim();

        if (!value) {
            if (statusEl) {
                statusEl.textContent = 'Nomor mesin pada QR tidak ditemukan.';
            }

            return;
        }

        const online = await probeServerReachable();

        if (online) {
            // Server reachable — navigate exactly as this page always has.
            const entryUrl = document.getElementById('manual-machine-form')?.action;

            window.location.assign(
                (entryUrl ?? '/oil-audits/entry/__machine__').replace('__machine__', encodeURIComponent(value))
            );

            return;
        }

        const machine = await OilAuditCreate.findCachedMachineByNumber(value);

        if (!machine) {
            if (statusEl) {
                statusEl.textContent = `Data mesin "${value}" tidak tersedia offline. Sambungkan ke internet untuk mesin yang belum pernah dimuat.`;
            }

            window.alert('Machine data unavailable offline.');

            return;
        }

        await Drafts.saveDraft({
            draftType: OilAuditCreate.DRAFT_TYPE,
            referenceId: machine.id,
            payload: machine,
            userId,
        }).catch(() => {});

        hideFeedback();
        showOfflinePanel(machine);
    }

    const startButton = document.getElementById('start-scanner');
    const status = document.getElementById('scanner-status');
    const manualForm = document.getElementById('manual-machine-form');
    let scanner;
    let isScanning = false;

    const startScanner = async () => {
        if (isScanning || typeof Html5Qrcode === 'undefined') {
            return;
        }

        scanner = new Html5Qrcode('oil-audit-reader');
        isScanning = true;
        startButton.disabled = true;
        startButton.classList.add('cursor-not-allowed', 'opacity-60');
        status.textContent = 'Kamera aktif. Posisikan QR code di dalam area pemindaian.';

        try {
            await scanner.start(
                { facingMode: 'environment' },
                { fps: 12, qrbox: { width: 250, height: 250 } },
                async (decodedText) => {
                    if (!isScanning) return;

                    isScanning = false;
                    status.textContent = 'QR terbaca. Membuka data mesin...';
                    await scanner.stop();
                    openMachine(decodedText, status);
                },
                () => {}
            );
        } catch {
            isScanning = false;
            startButton.disabled = false;
            startButton.classList.remove('cursor-not-allowed', 'opacity-60');
            status.textContent = 'Kamera belum dapat digunakan. Periksa izin kamera atau gunakan input manual.';
        }
    };

    startButton?.addEventListener('click', startScanner);

    manualForm?.addEventListener('submit', (event) => {
        event.preventDefault();
        openMachine(document.getElementById('manual-machine-number')?.value, status);
    });

    if (dataEl.dataset.autostartScanner === 'true') {
        window.setTimeout(startScanner, 250);
    }

    // Restore an in-progress machine selection (Task 7 section 9): if the
    // device picked a machine offline, then reloaded/reopened before
    // choosing a condition, show it again instead of forcing a re-scan.
    (async () => {
        const drafts = await Drafts.listDraftsByType(userId, OilAuditCreate.DRAFT_TYPE).catch(() => []);
        const latest = drafts.sort((a, b) => (a.updated_at < b.updated_at ? 1 : -1))[0];

        if (latest?.payload) {
            showFeedback(
                `Draft mesin <strong>${latest.payload.machine_number}</strong> dari sesi sebelumnya (${formatTimestamp(latest.updated_at)}) ditemukan.`
            );
            showOfflinePanel(latest.payload);
        }
    })();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initScanPage);
} else {
    initScanPage();
}
