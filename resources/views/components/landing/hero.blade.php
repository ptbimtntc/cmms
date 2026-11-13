{{--
    Revision 2: back to the cinematic hero at the user's request (animation
    restored, four actions restored), translated to English to match the
    rest of the landing page. This is a deliberate, confirmed override of
    the MOTION 1 / single-CTA direction recorded for the rest of the page;
    see DESIGN.md "Landing page" for the written override (R-37). The
    illustrated WWD line fills the frame on desktop; on a phone it becomes
    a banner above the copy.
--}}
<section class="on-dark relative bg-blue-950 text-white lg:min-h-[calc(100svh-4rem)]">

    <x-landing.factory-scene uid="hero" class="aspect-[4/3] sm:aspect-[16/9] lg:absolute lg:inset-0 lg:aspect-auto"
        pause-label="Pause animation" play-label="Play animation">
        <div class="scene-scrim pointer-events-none absolute inset-0 hidden lg:block"></div>
        <div class="scene-fade pointer-events-none absolute inset-x-0 bottom-0 h-1/3 lg:hidden"></div>
    </x-landing.factory-scene>

    <div
        class="pointer-events-none relative z-10 mx-auto max-w-7xl px-4 pb-14 pt-2 sm:px-6 lg:flex lg:min-h-[calc(100svh-4rem)] lg:items-center lg:px-8 lg:py-24">

        <div class="pointer-events-auto max-w-xl">

            {{-- The headline renders immediately; only the supporting copy follows the lamps in. --}}
            <h1 class="font-signage text-[clamp(2.5rem,6vw,4.25rem)] font-bold leading-[1.02] tracking-tight">
                Manage
                <span class="text-blue-300">Preventive Maintenance</span>
                Faster, More Accurate, Paperless.
            </h1>

            <p class="cine-in mt-6 text-lg leading-8 text-blue-100" style="--d: 0.2s">
                A digital preventive maintenance system. Covers PM scheduling, inspection,
                checklists, sparepart replacement, measurements, Small PM, and machine
                history, all in one platform.
            </p>

            <div class="cine-in mt-9 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center" style="--d: 0.35s">

                <a href="{{ route('qr.scan') }}"
                    class="inline-flex min-h-14 items-center justify-center gap-3 rounded-md bg-white px-7 text-lg font-semibold text-blue-900 hover:bg-blue-50 active:bg-blue-100">
                    <x-landing.icon name="qr" class="h-6 w-6" />
                    Scan a Machine
                </a>

                <a href="{{ route('dashboard-guest') }}"
                    class="inline-flex min-h-14 items-center justify-center rounded-md border-2 border-white/80 px-6 text-lg font-semibold text-white hover:bg-white hover:text-blue-950">
                    View Dashboard
                </a>

                <a href="{{ route('monitor') }}"
                    class="inline-flex min-h-14 items-center justify-center rounded-md border-2 border-white/80 px-6 text-lg font-semibold text-white hover:bg-white hover:text-blue-950">
                    View Monitor
                </a>

                <a href="{{ route('login') }}"
                    class="inline-flex min-h-11 items-center justify-center px-2 font-semibold text-blue-200 underline underline-offset-4 hover:text-white">
                    Sign in
                </a>

            </div>

            <ul class="cine-in mt-10 flex flex-wrap gap-x-8 gap-y-2 border-t border-blue-800 pt-5 text-[15px] font-medium text-blue-100"
                style="--d: 0.5s">
                <li>Paperless</li>
                <li>QR Machine History</li>
                <li>Real-time Monitoring</li>
            </ul>

        </div>

    </div>

</section>
