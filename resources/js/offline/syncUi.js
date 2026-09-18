/**
 * FreeDOMS offline-first — global Sync Now / status widget (Phase 1, Task
 * 9A).
 *
 * Registers a single Alpine store (`$store.sync`) the topbar partial
 * (resources/views/partials/sync-status.blade.php) binds to, following the
 * same `document.addEventListener('alpine:init', ...)` pattern the sidebar
 * store already uses (resources/views/partials/sidebar.blade.php). This
 * file only reads from/calls into syncEngine.js — it holds no queue-
 * processing or business logic of its own (section 5/17: one engine, one
 * source of truth; the widget is just a view over it).
 */
import * as SyncQueue from './queue.js';
import * as SyncEngine from './syncEngine.js';

const STATUS_LABELS = {
    offline: 'Offline',
    syncing: 'Syncing...',
    failed: 'Sync issue',
    conflict: 'Conflict',
    pending: 'Pending',
    synced: 'Synced',
};

document.addEventListener('alpine:init', () => {
    Alpine.store('sync', {
        status: 'offline',
        summary: { pending: 0, syncing: 0, failed: 0, conflict: 0, synced: 0 },
        running: false,
        panelOpen: false,
        failedOperations: [],
        conflictOperations: [],
        unsubscribe: null,

        init() {
            this.refresh();
            this.unsubscribe = SyncEngine.subscribeToSyncChanges(() => this.refresh());
        },

        async refresh() {
            const [status, summary, failedOperations, conflictOperations] = await Promise.all([
                SyncEngine.getSyncStatus(),
                SyncEngine.getSyncSummary(),
                SyncQueue.listByStatus(SyncQueue.QueueStatus.FAILED),
                SyncQueue.listByStatus(SyncQueue.QueueStatus.CONFLICT),
            ]);

            this.status = status;
            this.summary = summary;
            this.running = SyncEngine.isSyncRunning();
            this.failedOperations = failedOperations;
            this.conflictOperations = conflictOperations;
        },

        get label() {
            const issues = this.summary.failed + this.summary.conflict;

            if (issues > 0 && this.status !== 'syncing') {
                return `${issues} sync issue${issues > 1 ? 's' : ''}`;
            }

            const waiting = this.summary.pending + this.summary.syncing;

            if (this.status === 'pending' && waiting > 0) {
                return `${waiting} pending`;
            }

            return STATUS_LABELS[this.status] ?? this.status;
        },

        async syncNow() {
            await SyncEngine.syncPendingOperations();
        },

        async retry(operationUuid) {
            try {
                await SyncEngine.retryOperation(operationUuid);
            } catch (error) {
                console.warn('[offline/syncUi] retry failed', error);
            } finally {
                await this.refresh();
            }
        },

        isManuallyRetryable(operation) {
            return SyncEngine.isManuallyRetryable(operation);
        },

        labelFor(transactionType) {
            return SyncEngine.TRANSACTION_LABELS[transactionType] ?? transactionType;
        },
    });
});
