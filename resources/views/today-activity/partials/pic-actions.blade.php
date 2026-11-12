{{-- Availability actions for one PIC. Params: s (pic status row). --}}
<div class="flex flex-wrap items-center justify-end gap-2">
    @if ($s['status'] === 'NOT STARTED')
        <x-ui.button size="sm" variant="outline" class="pic-inactive-btn"
            data-id="{{ $s['pic']->id }}" data-name="{{ $s['pic']->name }}"
            data-reason="" data-notes="">Set Inactive</x-ui.button>
    @elseif ($s['status'] === 'INACTIVE')
        <x-ui.button size="sm" variant="ghost" class="pic-inactive-btn"
            data-id="{{ $s['pic']->id }}" data-name="{{ $s['pic']->name }}"
            data-reason="{{ $s['availability']->reason }}"
            data-notes="{{ $s['availability']->notes }}">Edit</x-ui.button>
        <form method="POST" action="{{ route('today-activity.inactive.clear', $s['availability']->id) }}">
            @csrf
            @method('DELETE')
            <x-ui.button type="submit" size="sm" variant="outline">Set Available</x-ui.button>
        </form>
    @endif
</div>
