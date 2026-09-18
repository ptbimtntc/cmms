{{-- FreeDOMS offline-first — global Sync Now / status widget (Phase 1, Task 9A).
     Reads/acts through window Alpine store `sync` (resources/js/offline/syncUi.js),
     which is only ever a view over resources/js/offline/syncEngine.js — the
     single engine auto sync and this button both use (section 16/17). --}}
<div class="relative" x-data="{}">
    <button
        type="button"
        @click="$store.sync.panelOpen = !$store.sync.panelOpen"
        class="flex items-center gap-2 rounded-full border border-border px-3 py-1.5 text-xs font-medium transition hover:bg-surface-muted"
        :class="{
            'border-success/30 text-success': $store.sync.status === 'synced',
            'border-warning/30 text-warning': $store.sync.status === 'pending' || $store.sync.status === 'syncing',
            'border-danger/30 text-danger': $store.sync.status === 'failed' || $store.sync.status === 'conflict',
            'text-text-muted': $store.sync.status === 'offline',
        }"
        title="Sync status"
    >
        <span class="h-2 w-2 shrink-0 rounded-full bg-current" :class="{ 'animate-pulse': $store.sync.status === 'syncing' }"></span>
        <span x-text="$store.sync.label"></span>
    </button>

    <div
        x-show="$store.sync.panelOpen"
        x-cloak
        @click.outside="$store.sync.panelOpen = false"
        x-transition
        class="absolute right-0 z-40 mt-2 w-72 rounded-xl border border-border bg-surface py-3 shadow-lg"
    >
        <div class="flex items-center justify-between px-4 pb-2">
            <div class="text-sm font-medium text-text">Sync Status</div>
            <button
                type="button"
                @click="$store.sync.syncNow()"
                :disabled="$store.sync.running"
                class="rounded-lg bg-primary px-3 py-1 text-xs font-semibold text-primary-contrast transition disabled:opacity-50"
            >
                <span x-show="!$store.sync.running">Sync Now</span>
                <span x-show="$store.sync.running" x-cloak>Syncing...</span>
            </button>
        </div>

        <div class="grid grid-cols-2 gap-x-4 gap-y-1 border-t border-border px-4 pt-2 text-xs text-text-muted">
            <div>Pending: <span x-text="$store.sync.summary.pending + $store.sync.summary.syncing" class="font-medium text-text"></span></div>
            <div>Synced: <span x-text="$store.sync.summary.synced" class="font-medium text-text"></span></div>
            <div>Failed: <span x-text="$store.sync.summary.failed" class="font-medium text-text"></span></div>
            <div>Conflict: <span x-text="$store.sync.summary.conflict" class="font-medium text-text"></span></div>
        </div>

        <template x-if="$store.sync.failedOperations.length > 0">
            <div class="mt-2 max-h-40 overflow-y-auto border-t border-border px-4 pt-2">
                <div class="mb-1 text-xs font-semibold text-danger">
                    Sync failed &middot; <span x-text="$store.sync.failedOperations.length"></span> item(s)
                </div>
                <template x-for="op in $store.sync.failedOperations" :key="op.operation_uuid">
                    <div class="mb-2 rounded-lg bg-danger-light px-2 py-1.5 text-xs">
                        <div class="font-medium text-text" x-text="$store.sync.labelFor(op.transaction_type)"></div>
                        <div class="text-text-muted" x-text="op.last_error"></div>
                        <button
                            type="button"
                            x-show="$store.sync.isManuallyRetryable(op)"
                            @click="$store.sync.retry(op.operation_uuid)"
                            class="mt-1 font-medium text-primary hover:underline"
                        >Retry</button>
                    </div>
                </template>
            </div>
        </template>

        <template x-if="$store.sync.conflictOperations.length > 0">
            <div class="mt-2 max-h-40 overflow-y-auto border-t border-border px-4 pt-2">
                <div class="mb-1 text-xs font-semibold text-warning">
                    <span x-text="$store.sync.conflictOperations.length"></span> conflict(s) needs attention
                </div>
                <template x-for="op in $store.sync.conflictOperations" :key="op.operation_uuid">
                    <div class="mb-2 rounded-lg bg-warning-light px-2 py-1.5 text-xs">
                        <div class="font-medium text-text" x-text="$store.sync.labelFor(op.transaction_type)"></div>
                        <div class="text-text-muted">Data server sudah berubah sejak device terakhir sync.</div>
                    </div>
                </template>
            </div>
        </template>

        <template x-if="$store.sync.failedOperations.length === 0 && $store.sync.conflictOperations.length === 0">
            <div class="border-t border-border px-4 pt-2 text-xs text-text-muted">Tidak ada masalah sinkronisasi.</div>
        </template>
    </div>
</div>
