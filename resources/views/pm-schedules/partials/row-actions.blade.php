{{--
    Per-row actions on the PM Schedule list, by role. Shared by the desktop
    table and the phone cards so both always offer the same actions.
    Params: pm, role.
    Keep: .pm-start-btn + data-id/machine/status (resources/js/pm/start.js),
    the DELETE method field, and the "START" / "Fill PM" / "STARTED" wording (tests).
--}}
@php
    $editUrl = route('pm-schedules.edit', ['pm_schedule' => $pm->id, 'page' => request('page')]);
    $canManage = $role === 'ADMIN' || str_starts_with($role, 'KOORDINATOR');
@endphp

<div class="flex flex-wrap items-center justify-end gap-2">
    @if ($canManage)
        <x-ui.button size="sm" variant="outline" href="{{ $editUrl }}">Edit</x-ui.button>
        @if ($pm->status === 'IN_PROGRESS')
            <form action="{{ route('pm-schedules.revert-to-open', $pm->id) }}" method="POST"
                onsubmit="return confirm('Revert this PM back to Open?')">
                @csrf
                <x-ui.button type="submit" size="sm" variant="ghost">Revert to Open</x-ui.button>
            </form>
        @endif
        <form action="{{ route('pm-schedules.destroy', $pm->id) }}" method="POST"
            onsubmit="return confirm('Delete PM schedule for machine {{ $pm->machine_number }}? This cannot be undone.')">
            @csrf
            @method('DELETE')
            <x-ui.button type="submit" size="sm" variant="ghost" class="text-danger hover:bg-danger-light hover:text-danger-hover">Delete</x-ui.button>
        </form>
    @elseif (str_starts_with($role, 'PIC'))
        @if (in_array($pm->status, ['OPEN', 'MISSED', 'IN_PROGRESS']))
            @if ($pm->start_time)
                <span class="inline-flex min-h-11 items-center gap-1.5 rounded-control bg-info-light px-3 text-xs font-semibold text-info-hover lg:min-h-9"
                    title="PM activity started">
                    STARTED
                    <span class="font-normal">{{ optional($pm->startedAt())->format('d-m-Y H:i') }}</span>
                </span>
            @else
                <x-ui.button size="sm" class="pm-start-btn"
                    data-id="{{ $pm->id }}" data-machine="{{ $pm->machine_number }}"
                    data-status="{{ $pm->status }}">START</x-ui.button>
            @endif
            <x-ui.button size="sm" variant="success" href="{{ $editUrl }}">Fill PM</x-ui.button>
        @else
            <x-ui.button size="sm" variant="outline" href="{{ $editUrl }}">View</x-ui.button>
        @endif
    @endif
</div>
