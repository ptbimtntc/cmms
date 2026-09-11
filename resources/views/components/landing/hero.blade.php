<section class="relative overflow-hidden bg-gradient-to-b from-slate-50 to-white">

    <div class="max-w-7xl mx-auto px-6 py-24">

        <div class="grid lg:grid-cols-2 gap-16 items-center">

            {{-- LEFT --}}
            <div>

                <span
                    class="inline-flex items-center rounded-full bg-blue-100 text-blue-700 px-4 py-1 text-sm font-medium">

                    🚀 Digital Preventive Maintenance System

                </span>

                <h1 class="mt-6 text-5xl lg:text-6xl font-extrabold leading-tight tracking-tight text-slate-900">

                    Kelola
                    <span class="text-blue-600">

                        Preventive Maintenance

                    </span>

                    Lebih Cepat,
                    Lebih Akurat,
                    Tanpa Kertas.

                </h1>

                <p class="mt-6 text-lg leading-8 text-slate-600 max-w-xl">

                    Digitalisasikan seluruh aktivitas maintenance mulai dari
                    penjadwalan PM, inspeksi, checklist, penggantian sparepart,
                    measurement, small PM, hingga riwayat mesin dalam satu platform yang
                    mudah digunakan.

                </p>

                <div class="mt-10 flex flex-wrap gap-4">

                    <a href="{{ route('dashboard-guest') }}"
                        class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-7 py-4 font-semibold text-white shadow-lg hover:bg-blue-700 transition">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/>
                        </svg>
                        Lihat Dashboard

                    </a>

                    <a href="{{ route('monitor') }}"
                        class="inline-flex items-center gap-2 rounded-xl border-2 border-blue-600 px-7 py-4 font-semibold text-blue-600 shadow-lg hover:bg-blue-50 transition">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M22 12h-2.48a2 2 0 0 0-1.93 1.46l-2.35 8.36a.25.25 0 0 1-.48 0L9.24 2.18a.25.25 0 0 0-.48 0l-2.35 8.36A2 2 0 0 1 4.49 12H2" />
                        </svg>
                        Lihat Monitor

                    </a>

                    <a href="{{ route('qr.scan') }}"
                        class="inline-flex items-center gap-2 rounded-xl bg-slate-100 px-7 py-4 font-semibold text-slate-900 shadow-lg hover:bg-slate-200 transition">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor">

                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M4 7V4h3M20 7V4h-3M4 17v3h3M20 17v3h-3M8 8h2v2H8zM14 8h2v2h-2zM8 14h2v2H8zM14 14h2v2h-2z" />

                        </svg>
                        Scan Mesin

                    </a>

                    <a href="{{ route('login') }}"
                        class="inline-flex items-center rounded-xl border border-slate-300 px-7 py-4 font-semibold hover:bg-slate-100 transition">

                        Login

                    </a>

                </div>

                <div class="mt-10 flex flex-wrap gap-6 text-sm text-slate-600">

                    <div class="flex items-center gap-2">

                        ✅ Paperless

                    </div>

                    <div class="flex items-center gap-2">

                        ✅ QR Machine History

                    </div>

                    <div class="flex items-center gap-2">

                        ✅ Real-time Monitoring

                    </div>

                </div>

            </div>

            {{-- RIGHT --}}
            <div>

                <div class="rounded-3xl border border-slate-200 bg-white shadow-2xl overflow-hidden">

                    {{-- Header --}}

                    <div class="border-b bg-slate-50 px-6 py-4 flex items-center justify-between">

                        <h3 class="font-semibold">

                            Dashboard Preview

                        </h3>

                        <span class="rounded-full bg-green-100 text-green-700 text-xs px-3 py-1">

                            LIVE

                        </span>

                    </div>

                    {{-- Cards --}}

                    <div class="p-6 grid grid-cols-2 gap-4">

                        <div class="rounded-xl bg-blue-50 p-5">

                            <p class="text-sm text-slate-500">

                                Today's PM

                            </p>

                            <h2 class="text-3xl font-bold mt-2">

                                12

                            </h2>

                        </div>

                        <div class="rounded-xl bg-green-50 p-5">

                            <p class="text-sm text-slate-500">

                                Completed

                            </p>

                            <h2 class="text-3xl font-bold mt-2">

                                9

                            </h2>

                        </div>

                        <div class="rounded-xl bg-red-50 p-5">

                            <p class="text-sm text-slate-500">

                                Overdue

                            </p>

                            <h2 class="text-3xl font-bold mt-2">

                                3

                            </h2>

                        </div>

                        <div class="rounded-xl bg-yellow-50 p-5">

                            <p class="text-sm text-slate-500">

                                Machine History

                            </p>

                            <h2 class="text-3xl font-bold mt-2">

                                1,284

                            </h2>

                        </div>

                    </div>

                    {{-- Activity --}}

                    <div class="px-6 pb-6">

                        <div class="rounded-xl border p-4">

                            <h4 class="font-semibold mb-3">

                                Recent Activities

                            </h4>

                            <div class="space-y-3">

                                <div class="flex justify-between">

                                    <span>

                                        ✔ PM-30001 Completed

                                    </span>

                                    <span class="text-slate-400">

                                        10 min ago

                                    </span>

                                </div>

                                <div class="flex justify-between">

                                    <span>

                                        ✔ PM-30015 In Progress

                                    </span>

                                    <span class="text-slate-400">

                                        25 min ago

                                    </span>

                                </div>

                                <div class="flex justify-between">

                                    <span>

                                        ✔ PM-30021 Scheduled

                                    </span>

                                    <span class="text-slate-400">

                                        Today

                                    </span>

                                </div>

                            </div>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </div>

</section>
