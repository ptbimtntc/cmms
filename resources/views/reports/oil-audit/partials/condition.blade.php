{{-- Latest audit condition. Colours come from OilAudit::conditionColor(); no audit reads "Belum Audit". --}}
@if ($audit)
    <span class="badge shrink-0 {{ $audit->conditionColor()['badge'] }}">{{ $audit->conditionLabel() }}</span>
@else
    <x-ui.badge class="shrink-0">Not Audited</x-ui.badge>
@endif
