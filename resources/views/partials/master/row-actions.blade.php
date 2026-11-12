{{--
    View / Edit / Delete for one master row. Params: view (url), edit (url), delete (url), confirm (text), mobile (bool, stretch across a card).
    Either action may be omitted.
--}}
@php($grow = ($mobile ?? false) ? 'flex-1' : '')
<div @class(['flex flex-wrap items-center gap-2', 'justify-end' => ! ($mobile ?? false), 'w-full' => $mobile ?? false])>
    @isset($view)
        <x-ui.button size="sm" variant="ghost" class="{{ $grow }}" href="{{ $view }}">View</x-ui.button>
    @endisset
    @isset($edit)
        <x-ui.button size="sm" variant="outline" class="{{ $grow }}" href="{{ $edit }}">Edit</x-ui.button>
    @endisset
    @isset($delete)
        <form method="POST" action="{{ $delete }}" class="{{ $grow }}" onsubmit="return confirm(@js($confirm ?? 'Delete this item?'))">
            @csrf
            @method('DELETE')
            <x-ui.button type="submit" size="sm" variant="ghost" class="w-full text-danger hover:bg-danger-light hover:text-danger-hover">Delete</x-ui.button>
        </form>
    @endisset
</div>
