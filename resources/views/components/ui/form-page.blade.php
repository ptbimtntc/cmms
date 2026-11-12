{{--
    Create / edit page: back link, header, one form card, Cancel + submit footer.
    The page still sets $fullWidth / $contentMaxWidth itself (layout variables).

    <x-ui.form-page title="Edit Area" :action="route('areas.update', $area)" method="PUT"
        :back="route('areas.index')" back-label="Kembali ke Area Management" submit="Save">
        ...fields...
    </x-ui.form-page>

    Extra attributes (x-data, enctype, id) land on the <form>.
--}}
@props([
    'title',
    'description' => null,
    'action',
    'method' => 'POST',
    'back',
    'backLabel' => 'Kembali',
    'submit' => 'Save',
    'cancel' => null,
    'cancelLabel' => 'Cancel',
])

<div class="space-y-5">
    <x-ui.button variant="ghost" href="{{ $back }}" class="-ml-2">{{ $backLabel }}</x-ui.button>
    <x-ui.page-header :title="$title" :description="$description" />

    <form action="{{ $action }}" method="POST" {{ $attributes->merge(['class' => 'card space-y-5 p-5 sm:p-6']) }}>
        @csrf
        @if (strtoupper($method) !== 'POST')
            @method($method)
        @endif

        {{ $slot }}

        <div class="flex flex-col-reverse gap-2 border-t border-border pt-5 sm:flex-row sm:justify-end">
            <x-ui.button variant="ghost" href="{{ $cancel ?? $back }}">{{ $cancelLabel }}</x-ui.button>
            <x-ui.button type="submit">{{ $submit }}</x-ui.button>
        </div>
    </form>
</div>
