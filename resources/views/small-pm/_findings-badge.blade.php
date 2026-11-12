{{-- Dead blower filter count for one schedule (Ganti Filter Panel). --}}
@if ($s->findings_count > 0)
    <span class="inline-flex flex-wrap items-center gap-1.5">
        <x-ui.badge>{{ $s->findings_count }} mesin</x-ui.badge>
        @if ($s->open_findings_count > 0)
            <x-ui.badge tone="warning">{{ $s->open_findings_count }} open</x-ui.badge>
        @endif
    </span>
@elseif ($s->action_date)
    <span class="text-sm text-text-muted">Semua normal</span>
@else
    <span class="text-sm text-text-muted">-</span>
@endif
