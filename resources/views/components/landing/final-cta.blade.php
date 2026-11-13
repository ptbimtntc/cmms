{{--
    One CTA, restated: the same PM Status action from the hero, for anyone
    who scrolled this far without acting. No second set of four buttons.
--}}
@php
    $areas = null;

    try {
        $areas = \App\Models\Area::active()->orderBy('name')->get(['name', 'slug']);
    } catch (\Throwable $e) {
        report($e);
    }
@endphp

<section class="bg-background py-16 lg:py-20">

    <div class="mx-auto max-w-3xl px-4 text-center sm:px-6 lg:px-8">

        <h2 class="font-signage text-3xl font-bold text-text sm:text-4xl">
            Check this month's PM status
        </h2>

        <p class="mx-auto mt-4 max-w-xl text-lg text-text-muted">
            Pick an area to see what is open, what is overdue, and the gap day for every machine.
        </p>

        @if ($areas && $areas->isNotEmpty())
            <div class="mt-8 flex flex-wrap justify-center gap-3">
                @foreach ($areas as $area)
                    <x-ui.button href="{{ route('pm-status.show', $area->slug) }}" size="lg">
                        {{ $area->name }}
                    </x-ui.button>
                @endforeach
            </div>
        @else
            <div class="mt-8">
                <x-ui.button href="{{ route('dashboard-guest') }}" size="lg" variant="outline">
                    Open the dashboard instead
                </x-ui.button>
            </div>
        @endif

        <a href="{{ route('login') }}" class="link mt-6 inline-flex min-h-11 items-center text-[15px] font-semibold">
            Sign in to FreeDOMS
        </a>

    </div>

</section>
