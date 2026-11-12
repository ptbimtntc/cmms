<section class="card p-5" aria-labelledby="oil-audit-heading">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h2 id="oil-audit-heading" class="text-sm font-semibold text-text">Oil Audit</h2>
            <p class="text-xs text-text-muted">WWD &middot; NDE/NDB machines</p>
        </div>
        <a href="{{ route('oil-audits.report') }}" class="inline-flex min-h-11 items-center text-sm font-medium text-primary hover:text-primary-hover hover:underline lg:min-h-0">Open Action Audit Oli</a>
    </div>

    {{-- Numbers stay in ink; the coloured dot names the state, the label says it in words. --}}
    <dl class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
        @foreach ([
            ['label' => 'Audited Today', 'value' => $oilAudit['today'], 'dot' => 'bg-info'],
            ['label' => 'Needs Follow-up', 'value' => $oilAudit['pending'], 'dot' => 'bg-warning'],
            ['label' => 'Critical Unresolved', 'value' => $oilAudit['critical'], 'dot' => 'bg-danger'],
        ] as $tile)
            <div class="rounded-control border border-border p-3">
                <dt class="flex items-center gap-2 text-xs font-medium text-text-muted">
                    <span class="h-2 w-2 shrink-0 rounded-full {{ $tile['dot'] }}" aria-hidden="true"></span>{{ $tile['label'] }}
                </dt>
                <dd class="mt-1 font-signage text-3xl font-semibold text-text">{{ $tile['value'] }}</dd>
            </div>
        @endforeach
        <div class="rounded-control border border-border p-3">
            <dt class="text-xs font-medium text-text-muted">Machines Audited</dt>
            <dd class="mt-1 font-signage text-3xl font-semibold text-text">
                {{ $oilAudit['machines_audited'] }}<span class="text-base font-normal text-text-muted">/{{ $oilAudit['total_machines'] }}</span>
            </dd>
        </div>
    </dl>
</section>
