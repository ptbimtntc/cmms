{{-- Closing shot: the same line from the hero, veiled so the copy reads; the hazard band marks the end of the page. --}}
<section class="on-dark relative bg-blue-950 text-white">

    <div class="hazard relative z-10 h-4" aria-hidden="true"></div>

    <x-landing.factory-scene uid="cta" :toggle="false" :still="true" class="absolute inset-0 top-4" aria-hidden="true">
        <div class="scene-veil pointer-events-none absolute inset-0"></div>
    </x-landing.factory-scene>

    <div class="relative z-10 mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8 lg:py-28">

        <div class="grid items-center gap-10 lg:grid-cols-[1.2fr_0.8fr] lg:gap-20">

            <div>

                <p class="font-signage text-lg font-semibold text-blue-300">Ready to Go Digital?</p>

                <h2 class="mt-2 font-signage text-4xl font-bold leading-[1.05] sm:text-5xl lg:text-6xl">
                    Siap Mengubah Cara Tim Maintenance Bekerja?
                </h2>

                <p class="mt-6 max-w-2xl text-lg leading-8 text-blue-100">
                    Mulai digitalisasi Preventive Maintenance sekarang.
                    Jadwalkan PM, isi checklist, scan QR mesin,
                    dan akses histori maintenance kapan saja.
                </p>

            </div>

            <div class="flex flex-col gap-3">

                <a href="{{ route('qr.scan') }}"
                    class="inline-flex min-h-14 items-center justify-center gap-3 rounded-md bg-white px-6 text-lg font-semibold text-blue-900 hover:bg-blue-50 active:bg-blue-100">
                    <x-landing.icon name="qr" class="h-6 w-6" />
                    Scan Mesin
                </a>

                <a href="{{ route('dashboard-guest') }}"
                    class="inline-flex min-h-14 items-center justify-center rounded-md border-2 border-white bg-blue-950/60 px-6 text-lg font-semibold text-white hover:bg-white hover:text-blue-950">
                    Lihat Dashboard
                </a>

                <a href="{{ route('monitor') }}"
                    class="inline-flex min-h-14 items-center justify-center rounded-md border-2 border-white bg-blue-950/60 px-6 text-lg font-semibold text-white hover:bg-white hover:text-blue-950">
                    Lihat Monitor
                </a>

                <a href="{{ route('login') }}"
                    class="inline-flex min-h-14 items-center justify-center rounded-md border-2 border-blue-300 bg-blue-950/60 px-6 text-lg font-semibold text-blue-100 hover:bg-blue-900">
                    Login FreeDOMS
                </a>

            </div>

        </div>

    </div>

</section>
