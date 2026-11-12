{{--
    Status badge + optional view-only override, shared by the desktop row and the phone card.
    resources/js/pm-status/override.js toggles bg-green-100/text-green-800 (CLOSED) and
    bg-orange-100/text-orange-800 (OPEN) on [data-pm-status-badge]; keep those classes.
--}}
<div class="flex flex-wrap items-center gap-1.5"
    data-pm-status-cell
    data-pm-status-id="{{ $schedule['id'] }}"
    data-pm-status-original="{{ $schedule['status'] }}">
    <span data-pm-status-badge
        class="badge {{ $schedule['status'] === 'CLOSED' ? 'bg-green-100 text-green-800' : 'bg-orange-100 text-orange-800' }}">{{ $schedule['status'] }}</span>
    @if ($canOverrideStatus)
        <span data-pm-status-override-tag class="hidden text-xs text-text-muted"
            title="Tampilan status ini diubah manual, data mesin asli tidak berubah.">(manual)</span>
        <button type="button" data-pm-status-toggle
            class="btn btn-ghost btn-sm min-h-11 px-2 lg:min-h-8"
            title="Override tampilan status (tidak mengubah data mesin asli)">Ubah</button>
    @endif
</div>
