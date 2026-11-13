{{-- Features: sticky heading on the left, three feature rows on the right. QR gets the dark plate because it is where every field task begins. --}}
<section id="features" class="bg-white py-16 lg:py-24">

    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">

        <div class="grid gap-10 lg:grid-cols-[0.8fr_1.2fr] lg:gap-20">

            <div class="lg:sticky lg:top-28 lg:self-start">

                <p class="font-signage text-lg font-semibold text-blue-700">Features</p>

                <h2 class="mt-2 font-signage text-3xl font-bold leading-tight text-blue-950 sm:text-4xl lg:text-5xl">
                    Semua Kebutuhan Maintenance Dalam Satu Platform
                </h2>

                <p class="mt-5 text-lg text-slate-700">
                    FreeDOMS dirancang untuk membantu seluruh proses Preventive Maintenance,
                    mulai dari penjadwalan hingga histori mesin.
                </p>

            </div>

            <div class="min-w-0 space-y-6">

                {{-- Feature 1 --}}
                <article class="grid gap-5 border-b-2 border-slate-200 pb-8 sm:grid-cols-[auto_1fr] sm:gap-6">

                    <span class="flex h-14 w-14 items-center justify-center rounded-md bg-blue-50 text-blue-700">
                        <x-landing.icon name="calendar" class="h-8 w-8" />
                    </span>

                    <div>
                        <h3 class="font-signage text-2xl font-bold text-blue-950 sm:text-3xl">
                            Preventive Maintenance Scheduling
                        </h3>

                        <p class="mt-3 leading-7 text-slate-700">
                            Upload jadwal PM, lakukan assignment PIC sesuai area,
                            monitor status pekerjaan, serta pantau progress PM
                            secara real-time.
                        </p>

                        <ul class="mt-5 flex flex-wrap gap-x-6 gap-y-2 font-medium text-slate-800">
                            <li class="flex items-center gap-2"><span class="h-2 w-2 bg-blue-600" aria-hidden="true"></span>Assignment PIC</li>
                            <li class="flex items-center gap-2"><span class="h-2 w-2 bg-blue-600" aria-hidden="true"></span>PM Progress</li>
                            <li class="flex items-center gap-2"><span class="h-2 w-2 bg-blue-600" aria-hidden="true"></span>Monthly Schedule</li>
                        </ul>
                    </div>

                </article>

                {{-- Feature 2 --}}
                <article class="grid gap-5 border-b-2 border-slate-200 pb-8 sm:grid-cols-[auto_1fr] sm:gap-6">

                    <span class="flex h-14 w-14 items-center justify-center rounded-md bg-blue-50 text-blue-700">
                        <x-landing.icon name="clipboard" class="h-8 w-8" />
                    </span>

                    <div>
                        <h3 class="font-signage text-2xl font-bold text-blue-950 sm:text-3xl">Digital Checklist</h3>

                        <p class="mt-3 leading-7 text-slate-700">
                            Seluruh checklist maintenance, measurement,
                            problem, dan sparepart terdokumentasi secara digital.
                        </p>

                        <ul class="mt-5 grid gap-2 font-medium text-slate-800 sm:grid-cols-2">
                            <li class="flex items-center gap-2"><span class="h-2 w-2 bg-blue-600" aria-hidden="true"></span>Checklist Per Section</li>
                            <li class="flex items-center gap-2"><span class="h-2 w-2 bg-blue-600" aria-hidden="true"></span>Measurement</li>
                            <li class="flex items-center gap-2"><span class="h-2 w-2 bg-blue-600" aria-hidden="true"></span>Problem Analysis</li>
                            <li class="flex items-center gap-2"><span class="h-2 w-2 bg-blue-600" aria-hidden="true"></span>Sparepart Used</li>
                        </ul>
                    </div>

                </article>

                {{-- Feature 3: flagship, dark plate --}}
                <article class="on-dark grid gap-5 rounded-lg bg-blue-950 p-6 text-white sm:grid-cols-[auto_1fr] sm:gap-6 sm:p-8">

                    <span class="flex h-14 w-14 items-center justify-center rounded-md bg-white text-blue-900">
                        <x-landing.icon name="qr" class="h-8 w-8" />
                    </span>

                    <div>
                        <h3 class="font-signage text-2xl font-bold sm:text-3xl">QR Machine History</h3>

                        <p class="mt-3 leading-7 text-blue-100">
                            Scan QR Code pada mesin untuk langsung melihat
                            histori maintenance, checklist, problem,
                            measurement, hingga sparepart yang pernah diganti.
                        </p>

                        <ul class="mt-5 flex flex-wrap gap-x-6 gap-y-2 font-medium">
                            <li class="flex items-center gap-2"><span class="h-2 w-2 bg-blue-300" aria-hidden="true"></span>Scan QR</li>
                            <li class="flex items-center gap-2"><span class="h-2 w-2 bg-blue-300" aria-hidden="true"></span>Machine History</li>
                            <li class="flex items-center gap-2"><span class="h-2 w-2 bg-blue-300" aria-hidden="true"></span>Maintenance Detail</li>
                        </ul>

                        <a href="#scan-demo"
                            class="mt-6 inline-flex min-h-11 items-center font-semibold text-blue-300 underline underline-offset-4 hover:text-blue-200">
                            Lihat contoh riwayat mesin
                        </a>
                    </div>

                </article>

            </div>

        </div>

    </div>

</section>
