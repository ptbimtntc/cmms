<section class="py-24 bg-blue-600 relative overflow-hidden">

    <div class="absolute inset-0 opacity-10">

        <div class="absolute -top-20 -left-20 w-72 h-72 rounded-full bg-white"></div>

        <div class="absolute bottom-0 right-0 w-96 h-96 rounded-full bg-white"></div>

    </div>

    <div class="relative max-w-5xl mx-auto px-6 text-center text-white">

        <span class="uppercase tracking-widest text-blue-100 font-semibold">

            Ready to Go Digital?

        </span>

        <h2 class="mt-4 text-5xl font-extrabold leading-tight">

            Siap Mengubah Cara Tim
            <br>

            Maintenance Bekerja?

        </h2>

        <p class="mt-8 text-xl text-blue-100 leading-9 max-w-3xl mx-auto">

            Mulai digitalisasi Preventive Maintenance sekarang.
            Jadwalkan PM, isi checklist, scan QR mesin,
            dan akses histori maintenance kapan saja.

        </p>

        <div class="mt-12 flex flex-wrap justify-center gap-5">

            <a href="{{ route('dashboard-guest') }}"
                class="px-8 py-4 rounded-2xl bg-white text-blue-700 font-bold shadow-xl hover:scale-105 transition">

                📊 Lihat Dashboard

            </a>

            <a href="{{ route('monitor') }}"
                class="px-8 py-4 rounded-2xl border-2 border-white text-white font-bold hover:bg-white hover:text-blue-700 transition">

                🖥️ Lihat Monitor

            </a>

            <a href="{{ route('qr.scan') }}"
                class="px-8 py-4 rounded-2xl border-2 border-white text-white font-bold hover:bg-white hover:text-blue-700 transition">

                📷 Scan Mesin

            </a>

            <a href="{{ route('login') }}"
                class="px-8 py-4 rounded-2xl border-2 border-white text-white font-bold hover:bg-white hover:text-blue-700 transition">

                🔐 Login FreeDOMS

            </a>

        </div>

    </div>

</section>