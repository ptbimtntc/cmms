{{--
    One identity block, one link column. Every link below has a real
    destination (R-24); the old four-column Features list pointed all
    four entries at the same #features anchor, which this replaces.
--}}
@php
    $areas = null;

    try {
        $areas = \App\Models\Area::active()->orderBy('name')->get(['name', 'slug']);
    } catch (\Throwable $e) {
        report($e);
    }
@endphp

<footer class="on-dark border-t border-blue-800 bg-blue-950 text-blue-100">

    <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">

        <div class="grid gap-10 lg:grid-cols-[1.3fr_1fr] lg:gap-16">

            <div>
                <p class="font-signage text-2xl font-bold text-white">FreeDOMS</p>
                <p class="mt-3 max-w-md leading-7">
                    Maintenance-Free Digital Optimization Management System: helping the
                    maintenance team run Preventive Maintenance digitally, quickly, and with
                    full documentation.
                </p>
            </div>

            <nav aria-label="Quick access">
                <h2 class="font-signage text-lg font-semibold text-white">Quick access</h2>
                <ul class="mt-3">
                    @if ($areas)
                        @foreach ($areas as $area)
                            <li>
                                <a href="{{ route('pm-status.show', $area->slug) }}"
                                    class="flex min-h-11 items-center hover:text-white hover:underline">
                                    PM Status, {{ $area->name }}
                                </a>
                            </li>
                        @endforeach
                    @endif
                    <li><a href="{{ route('machine-history.index') }}" class="flex min-h-11 items-center hover:text-white hover:underline">Machine history</a></li>
                    <li><a href="{{ route('monitor') }}" class="flex min-h-11 items-center hover:text-white hover:underline">Monitor</a></li>
                    <li><a href="{{ route('qr.scan') }}" class="flex min-h-11 items-center hover:text-white hover:underline">Scan a machine</a></li>
                    <li><a href="{{ route('login') }}" class="flex min-h-11 items-center hover:text-white hover:underline">Sign in</a></li>
                </ul>
            </nav>

        </div>

        <div class="mt-10 border-t border-blue-800 pt-6 text-sm">
            <p>&copy; {{ date('Y') }} FreeDOMS.</p>
        </div>

    </div>

</footer>
