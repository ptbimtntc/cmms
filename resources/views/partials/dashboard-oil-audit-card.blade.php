<div class="bg-white rounded-xl shadow-sm p-4">
    <div class="flex items-center justify-between mb-4">
        <div>
            <div class="text-sm font-medium text-gray-700">Oil Audit</div>
            <div class="text-xs text-gray-400">WWD &middot; NDE/NDB machines</div>
        </div>
        <a href="{{ route('oil-audits.report') }}" class="text-xs font-medium text-indigo-600 hover:text-indigo-700">View Report &rarr;</a>
    </div>

    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <div class="rounded-lg border border-sky-100 bg-sky-50 p-3">
            <div class="text-xs font-semibold uppercase tracking-wide text-sky-700">Audited Today</div>
            <div class="mt-1 text-2xl font-bold text-gray-800">{{ $oilAudit['today'] }}</div>
        </div>
        <div class="rounded-lg border border-orange-100 bg-orange-50 p-3">
            <div class="text-xs font-semibold uppercase tracking-wide text-orange-700">Needs Follow-up</div>
            <div class="mt-1 text-2xl font-bold text-gray-800">{{ $oilAudit['pending'] }}</div>
        </div>
        <div class="rounded-lg border border-red-100 bg-red-50 p-3">
            <div class="text-xs font-semibold uppercase tracking-wide text-red-700">Critical Unresolved</div>
            <div class="mt-1 text-2xl font-bold text-gray-800">{{ $oilAudit['critical'] }}</div>
        </div>
        <div class="rounded-lg border border-gray-100 bg-gray-50 p-3">
            <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Machines Audited</div>
            <div class="mt-1 text-2xl font-bold text-gray-800">
                {{ $oilAudit['machines_audited'] }}<span class="text-sm font-normal text-gray-400">/{{ $oilAudit['total_machines'] }}</span>
            </div>
        </div>
    </div>
</div>
