{{--
    Row actions, same set as Greasing: START (PIC), Execute, Edit / Delete (admin, koordinator).
    $mobile stretches the buttons across the phone card. Keep .small-pm-start-btn + data-url/data-row.
--}}
@php
    $user = auth()->user();
    $grow = $mobile ? 'flex-1' : '';
@endphp
<div @class(['flex flex-wrap items-center gap-2', 'justify-end' => ! $mobile, 'w-full' => $mobile])>
    @if ($user->isPic() && ! $s->isDone())
        @if ($s->start_time)
            <span class="{{ $grow }} inline-flex min-h-11 items-center justify-center gap-1.5 rounded-control bg-info-light px-3 text-xs font-semibold text-info-hover lg:min-h-9">
                STARTED <span class="font-normal">{{ $s->start_time->format('d M Y H:i') }}</span>
            </span>
        @else
            <x-ui.button size="sm" class="small-pm-start-btn {{ $grow }}"
                data-url="{{ route('small-pm.start', $s) }}" data-row="{{ $s->row?->name }}">START</x-ui.button>
        @endif
    @endif
    @unless ($user->isSupervisor())
        <x-ui.button size="sm" variant="success" class="{{ $grow }}" href="{{ route('small-pm.execute', ['schedule' => $s->id, 'page' => request('page')]) }}">{{ $s->isDone() ? 'Lihat' : 'Execute' }}</x-ui.button>
    @endunless
    @if ($canManage)
        <x-ui.button size="sm" variant="outline" class="{{ $grow }}" href="{{ route('small-pm.edit', ['schedule' => $s->id, 'page' => request('page')]) }}">Edit</x-ui.button>
        <form method="POST" action="{{ route('small-pm.destroy', $s) }}" class="{{ $grow }}" onsubmit="return confirm('Hapus jadwal Small PM?')">
            @csrf
            @method('DELETE')
            <x-ui.button type="submit" size="sm" variant="ghost" class="w-full text-danger hover:bg-danger-light hover:text-danger-hover">Delete</x-ui.button>
        </form>
    @endif
</div>
