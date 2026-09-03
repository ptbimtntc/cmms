<div class="bg-surface border border-border rounded-xl shadow-sm p-4">
    <div class="flex items-center justify-between mb-4">
        <div>
            <div class="text-sm font-medium text-text">Oil Audit</div>
            <div class="text-xs text-text-muted">WWD &middot; NDE/NDB machines</div>
        </div>
        <a href="{{ route('oil-audits.report') }}" class="text-xs font-medium text-primary hover:text-primary-hover">View Action &rarr;</a>
    </div>

    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <div class="rounded-lg border border-info-light bg-info-light p-3">
            <div class="text-xs font-semibold uppercase tracking-wide text-info">Audited Today</div>
            <div class="mt-1 text-2xl font-bold text-text">{{ $oilAudit['today'] }}</div>
        </div>
        <div class="rounded-lg border border-warning-light bg-warning-light p-3">
            <div class="text-xs font-semibold uppercase tracking-wide text-warning">Needs Follow-up</div>
            <div class="mt-1 text-2xl font-bold text-text">{{ $oilAudit['pending'] }}</div>
        </div>
        <div class="rounded-lg border border-danger-light bg-danger-light p-3">
            <div class="text-xs font-semibold uppercase tracking-wide text-danger">Critical Unresolved</div>
            <div class="mt-1 text-2xl font-bold text-text">{{ $oilAudit['critical'] }}</div>
        </div>
        <div class="rounded-lg border border-border bg-surface-muted p-3">
            <div class="text-xs font-semibold uppercase tracking-wide text-text-muted">Machines Audited</div>
            <div class="mt-1 text-2xl font-bold text-text">
                {{ $oilAudit['machines_audited'] }}<span class="text-sm font-normal text-text-muted">/{{ $oilAudit['total_machines'] }}</span>
            </div>
        </div>
    </div>
</div>
