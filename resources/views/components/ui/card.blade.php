{{--
    <x-ui.card title="Today's schedule">
        <x-slot:actions><x-ui.button size="sm" variant="outline">See all</x-ui.button></x-slot:actions>
        ...
    </x-ui.card>
    padded=false lets a table run edge to edge inside the card.
--}}
@props([
    'title' => null,
    'description' => null,
    'padded' => true,
])

<section {{ $attributes->merge(['class' => 'card overflow-hidden']) }}>
    @if ($title || isset($actions))
        <header class="flex flex-wrap items-start justify-between gap-3 border-b border-border px-4 py-3 sm:px-5">
            <div class="min-w-0">
                @if ($title)
                    <h2 class="text-base font-semibold text-text">{{ $title }}</h2>
                @endif
                @if ($description)
                    <p class="mt-0.5 text-sm text-text-muted">{{ $description }}</p>
                @endif
            </div>
            @isset($actions)
                <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
            @endisset
        </header>
    @endif

    <div @class(['p-4 sm:p-5' => $padded])>
        {{ $slot }}
    </div>

    @isset($footer)
        <footer class="card-footer">{{ $footer }}</footer>
    @endisset
</section>
