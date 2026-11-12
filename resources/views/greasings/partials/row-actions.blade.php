{{--
    Per-row actions on the Greasing list, shared by the desktop table and the phone cards.
    Param: greasing.
    Keep: .greasing-start-btn + data-id/data-group (start modal script in index), the execute
    link text directly inside the <a> (a test reads it), and the START / STARTED wording.
--}}
@php
    $user = auth()->user();
    $canManage = $user->isAdmin() || $user->isKoordinator();
    $isDone = in_array($greasing->status, ['FINISH', 'FINISH ON TIME'], true);
@endphp

<div class="flex flex-wrap items-center justify-end gap-2">
    @if ($user->isPic() && ! $isDone)
        @if ($greasing->start_time)
            <span class="inline-flex min-h-11 items-center gap-1.5 rounded-control bg-info-light px-3 text-xs font-semibold text-info-hover lg:min-h-9"
                title="Greasing activity started">
                STARTED
                <span class="font-normal">{{ $greasing->start_time->format('d M Y H:i') }}</span>
            </span>
        @else
            <x-ui.button size="sm" class="greasing-start-btn"
                data-id="{{ $greasing->id }}" data-group="{{ $greasing->group->name ?? '' }}">START</x-ui.button>
        @endif
    @endif

    @unless ($user->isSupervisor())
        <x-ui.button size="sm" variant="success" href="{{ route('greasings.execute', ['greasing' => $greasing->id, 'page' => request('page')]) }}">{{ ! $user->isAdmin() && $greasing->status !== 'OPEN' ? 'Edit' : 'Execute' }}</x-ui.button>
    @endunless

    @if ($canManage)
        <x-ui.button size="sm" variant="outline" href="{{ route('greasings.edit', ['greasing' => $greasing->id, 'page' => request('page')]) }}">Edit</x-ui.button>
        <form method="POST" action="{{ route('greasings.destroy', $greasing->id) }}"
            onsubmit="return confirm('Delete greasing schedule?')">
            @csrf
            @method('DELETE')
            <x-ui.button type="submit" size="sm" variant="ghost" class="text-danger hover:bg-danger-light hover:text-danger-hover">Delete</x-ui.button>
        </form>
    @endif
</div>
