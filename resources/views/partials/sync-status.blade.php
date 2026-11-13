{{-- FreeDOMS offline-first, global Sync Now / status widget (Phase 1, Task 9A).
     Reads/acts through window Alpine store `sync` (resources/js/offline/syncUi.js),
     which is only ever a view over resources/js/offline/syncEngine.js, the
     single engine auto sync and this button both use (section 16/17). --}}
<div class="relative" x-data="{}" @keydown.escape.window="$store.sync.panelOpen = false">
    <button
        type="button"
        @click="$store.sync.panelOpen = !$store.sync.panelOpen"
        :aria-expanded="$store.sync.panelOpen.toString()"
        class="flex min-h-11 items-center gap-2 rounded-full border border-border px-3 text-xs font-medium transition hover:bg-surface-muted active:bg-border lg:min-h-9"
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
        class="absolute right-0 z-40 mt-2 w-72 max-w-[calc(100vw-1.5rem)] rounded-card border border-border bg-surface py-3 shadow-popover"
    >
        <div class="flex items-center justify-between px-4 pb-2">
            <div class="text-sm font-medium text-text">Sync Status</div>
            <button
                type="button"
                @click="$store.sync.syncNow()"
                :disabled="$store.sync.running"
                class="inline-flex min-h-11 items-center rounded-control bg-primary px-3 text-xs font-semibold text-primary-contrast transition hover:bg-primary-hover disabled:opacity-50 lg:min-h-9"
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
                            class="mt-1 inline-flex min-h-11 items-center font-medium text-primary hover:underline lg:min-h-8"
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
                        <div class="text-text-muted" x-text="op.result?.status === 'duplicate_audit' ? op.result.message : 'The server data changed since this device last synced.'"></div>
                        <button
                            type="button"
                            x-show="$store.sync.canResolve(op)"
                            @click="$store.sync.startResolve(op)"
                            class="mt-1 inline-flex min-h-11 items-center font-medium text-primary hover:underline lg:min-h-8"
                        >Resolve</button>
                    </div>
                </template>
            </div>
        </template>

        <template x-if="$store.sync.failedOperations.length === 0 && $store.sync.conflictOperations.length === 0">
            <div class="border-t border-border px-4 pt-2 text-xs text-text-muted">No sync problems.</div>
        </template>
    </div>
</div>

{{-- Conflict resolver (PM Checklist): per-field "Mine" vs "Server" choice. --}}
<template x-teleport="body">
    <div x-data x-show="$store.sync.resolver.open" x-cloak
         @keydown.escape.window="$store.sync.resolver.open && $store.sync.closeResolve()"
         role="dialog" aria-modal="true" aria-label="Resolve a PM Checklist conflict"
         class="fixed inset-0 z-50 flex items-center justify-center bg-neutral-900/40 p-4">
        <div class="flex max-h-[90dvh] w-full max-w-2xl flex-col rounded-card border border-border bg-surface shadow-popover">
            <div class="flex items-center justify-between border-b border-border px-4 py-3">
                <div class="text-sm font-semibold text-text">Resolve conflict &middot; PM Checklist</div>
                <button type="button" @click="$store.sync.closeResolve()" class="-mr-2 flex h-11 w-11 items-center justify-center rounded-lg text-xl text-text-muted hover:bg-surface-muted hover:text-text" aria-label="Tutup">&times;</button>
            </div>

            <div class="overflow-y-auto px-4 py-3 text-xs">
                <div x-show="$store.sync.resolver.loading" class="text-text-muted">Fetching the latest data from the server...</div>
                <div x-show="$store.sync.resolver.error" class="mb-2 rounded-lg bg-danger-light px-2 py-1.5 text-danger" x-text="$store.sync.resolver.error"></div>

                {{-- Discard confirmation: shows what would be thrown away. --}}
                <template x-if="$store.sync.resolver.confirmDiscard">
                    <div>
                        <div class="mb-2 font-semibold text-danger">These local changes will be thrown away:</div>
                        <div x-show="$store.sync.resolver.diffs.length === 0" class="text-text-muted">No difference from the server.</div>
                        <template x-for="diff in $store.sync.resolver.diffs" :key="$store.sync.choiceKey(diff)">
                            <div class="mb-1 rounded-lg bg-surface-muted px-2 py-1">
                                <span class="font-medium text-text" x-text="$store.sync.itemLabel(diff.machine_checklist_id)"></span>
                                &middot; <span x-text="diff.field"></span>:
                                <span class="font-medium text-text" x-text="diff.local ?? '(empty)'"></span>
                            </div>
                        </template>
                        <div class="mt-3 flex gap-2">
                            <button type="button" @click="$store.sync.discardLocal()" class="rounded-lg bg-danger px-3 py-1 font-semibold text-white">Yes, discard the local changes</button>
                            <button type="button" @click="$store.sync.resolver.confirmDiscard = false" class="rounded-lg border border-border px-3 py-1">Cancel</button>
                        </div>
                    </div>
                </template>

                <template x-if="$store.sync.resolver.server && !$store.sync.resolver.confirmDiscard">
                    <div>
                        <div x-show="$store.sync.resolver.diffs.length === 0" class="mb-2 text-text-muted">
                            No field differs from the server. Resending will use the latest server data as the base.
                        </div>
                        <div x-show="$store.sync.resolver.diffs.length > 0" class="mb-2 flex gap-2">
                            <button type="button" @click="$store.sync.applyAll('local')" class="rounded-lg border border-border px-2 py-1 font-medium">Use mine for all</button>
                            <button type="button" @click="$store.sync.applyAll('server')" class="rounded-lg border border-border px-2 py-1 font-medium">Use the server for all</button>
                        </div>
                        <template x-for="diff in $store.sync.resolver.diffs" :key="$store.sync.choiceKey(diff)">
                            <div class="mb-2 rounded-lg border border-border px-2 py-1.5">
                                <div class="mb-1 font-medium text-text">
                                    <span x-text="$store.sync.itemLabel(diff.machine_checklist_id)"></span>
                                    &middot; <span class="uppercase" x-text="diff.field"></span>
                                </div>
                                <div class="grid grid-cols-2 gap-2">
                                    <label class="flex cursor-pointer items-center gap-2 rounded-lg px-2 py-1" :class="$store.sync.resolver.choices[$store.sync.choiceKey(diff)] === 'local' ? 'bg-primary/10' : 'bg-surface-muted'">
                                        <input type="radio" :name="$store.sync.choiceKey(diff)" @change="$store.sync.setChoice(diff, 'local')" :checked="$store.sync.resolver.choices[$store.sync.choiceKey(diff)] === 'local'">
                                        <span>Mine: <span class="font-medium text-text" x-text="diff.local ?? '(empty)'"></span></span>
                                    </label>
                                    <label class="flex cursor-pointer items-center gap-2 rounded-lg px-2 py-1" :class="$store.sync.resolver.choices[$store.sync.choiceKey(diff)] === 'server' ? 'bg-primary/10' : 'bg-surface-muted'">
                                        <input type="radio" :name="$store.sync.choiceKey(diff)" @change="$store.sync.setChoice(diff, 'server')" :checked="$store.sync.resolver.choices[$store.sync.choiceKey(diff)] === 'server'">
                                        <span>Server: <span class="font-medium text-text" x-text="diff.server ?? '(empty)'"></span></span>
                                    </label>
                                </div>
                            </div>
                        </template>
                    </div>
                </template>
            </div>

            <div class="flex items-center justify-between border-t border-border px-4 py-3" x-show="!$store.sync.resolver.confirmDiscard">
                <button type="button" @click="$store.sync.askDiscard()" class="text-xs text-danger hover:underline" x-show="$store.sync.resolver.server">Discard the local changes...</button>
                <button
                    type="button"
                    @click="$store.sync.submitResolve()"
                    :disabled="!$store.sync.canSubmitResolve"
                    class="ml-auto rounded-lg bg-primary px-3 py-1.5 text-xs font-semibold text-primary-contrast disabled:opacity-50"
                >
                    <span x-show="!$store.sync.resolver.submitting">Send the result</span>
                    <span x-show="$store.sync.resolver.submitting" x-cloak>Sending...</span>
                </button>
            </div>
        </div>
    </div>
</template>
