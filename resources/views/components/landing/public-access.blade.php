{{--
    Everything reachable without an account, in one place, so a visitor
    does not have to guess which public page they need. Machine History
    gets the larger first row because it is the only one with a real
    control (the search), not because it leads anywhere more important
    than PM Status, which the hero already covers (R-14).
--}}
<section class="bg-surface py-14 lg:py-20">

    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">

        <h2 class="font-signage text-2xl font-bold text-text sm:text-3xl">Open without an account</h2>
        <p class="mt-2 max-w-2xl text-text-muted">
            Beyond PM Status above, these pages are open to anyone on the floor, no sign-in required.
        </p>

        <div class="mt-8 divide-y divide-border border-y border-border">

            {{-- Machine history: the real search, moved here from the old QR demo section. --}}
            <div class="grid gap-5 py-8 lg:grid-cols-[0.9fr_1.1fr] lg:items-center lg:gap-10">
                <div>
                    <h3 class="font-signage text-xl font-bold text-text">Machine history</h3>
                    <p class="mt-1 text-text-muted">
                        Look up a machine's maintenance history by its number, or scan the QR tag
                        mounted on the machine itself.
                    </p>
                </div>

                <form action="{{ route('machine-history.index') }}" method="GET" role="search" class="flex flex-col gap-3 sm:flex-row sm:items-end">
                    <x-ui.field label="Machine number" for="machine-search" class="min-w-0 flex-1">
                        <input id="machine-search" name="search" type="text" class="input" inputmode="numeric"
                            autocomplete="off" placeholder="e.g. 30001">
                    </x-ui.field>
                    <div class="flex gap-2">
                        <x-ui.button type="submit" size="lg">Search</x-ui.button>
                        <x-ui.button href="{{ route('qr.scan') }}" variant="outline" size="lg">
                            <x-landing.icon name="qr" class="h-5 w-5" />
                            <span class="sr-only sm:not-sr-only">Scan</span>
                        </x-ui.button>
                    </div>
                </form>
            </div>

            <a href="{{ route('monitor') }}"
                class="flex items-center justify-between gap-4 py-6 hover:bg-surface-muted">
                <div>
                    <h3 class="font-signage text-lg font-semibold text-text">Monitor</h3>
                    <p class="mt-1 text-sm text-text-muted">Today's activity across every PIC, built for a wall display.</p>
                </div>
                <span class="shrink-0 text-sm font-semibold text-primary">Open &rarr;</span>
            </a>

            <a href="{{ route('dashboard-guest') }}"
                class="flex items-center justify-between gap-4 py-6 hover:bg-surface-muted">
                <div>
                    <h3 class="font-signage text-lg font-semibold text-text">Dashboard preview</h3>
                    <p class="mt-1 text-sm text-text-muted">The same figures as above, with a short guide on using them.</p>
                </div>
                <span class="shrink-0 text-sm font-semibold text-primary">Open &rarr;</span>
            </a>

        </div>

    </div>

</section>
