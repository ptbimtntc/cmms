{{-- OPEN waits on a PIC, CLOSED has an Action. A reopened WO is OPEN again. --}}
<x-ui.badge :tone="$wo->isOpen() ? 'warning' : 'success'" class="shrink-0">{{ $wo->isOpen() ? 'OPEN' : 'CLOSED' }}</x-ui.badge>
