{{-- Machine lookup on the left (works: submits to the machine history search), a labelled sample record on the right. --}}
<section id="scan-demo" class="bg-white py-16 lg:py-24">

    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">

        <div class="grid items-start gap-12 lg:grid-cols-[1fr_1fr] lg:gap-20">

            {{-- Lookup --}}
            <div class="min-w-0" x-data="{ q: '' }">

                <p class="font-signage text-lg font-semibold text-blue-700">Machine History</p>

                <h2 class="mt-2 font-signage text-3xl font-bold leading-tight text-blue-950 sm:text-4xl lg:text-5xl">
                    Cukup Scan QR Code,
                    Semua Informasi Mesin Langsung Tersedia.
                </h2>

                <p class="mt-6 text-lg leading-8 text-slate-700">
                    Tidak perlu membuka dokumen atau mencari histori maintenance
                    secara manual. Scan QR yang terpasang pada mesin, lalu seluruh
                    informasi maintenance akan langsung ditampilkan.
                </p>

                <a href="{{ route('qr.scan') }}"
                    class="mt-8 inline-flex min-h-14 w-full items-center justify-center gap-3 rounded-md bg-primary px-8 text-lg font-semibold text-white hover:bg-primary-hover active:bg-primary-active sm:w-auto">
                    <x-landing.icon name="qr" class="h-6 w-6" />
                    Scan Mesin
                </a>

                <form action="{{ route('machine-history.index') }}" method="GET" role="search"
                    class="mt-8 border-t-2 border-slate-200 pt-6">

                    <label for="machine-search" class="font-semibold text-slate-800">
                        Atau cari dengan nomor mesin
                    </label>

                    <div class="mt-2 flex">
                        <input id="machine-search" name="search" type="text" x-model="q" x-ref="q"
                            placeholder="Masukkan Nomor Mesin" autocomplete="off" inputmode="numeric"
                            class="min-h-12 w-full min-w-0 rounded-l-md border-2 border-r-0 border-slate-400 px-4 text-base text-slate-900 placeholder:text-slate-600">

                        <button type="submit"
                            class="min-h-12 rounded-r-md bg-blue-950 px-6 font-semibold text-white hover:bg-blue-800 active:bg-blue-900">
                            Cari
                        </button>
                    </div>

                </form>

                <div class="mt-5 flex flex-wrap items-center gap-2">

                    <span class="text-sm text-slate-700">Contoh:</span>

                    <button type="button" @click="q = '30001'; $refs.q.focus()"
                        class="min-h-11 rounded-md border-2 border-slate-300 px-4 font-mono text-sm font-medium text-slate-800 hover:border-blue-600 hover:text-blue-700">
                        30001
                    </button>

                    <button type="button" @click="q = '9004'; $refs.q.focus()"
                        class="min-h-11 rounded-md border-2 border-slate-300 px-4 font-mono text-sm font-medium text-slate-800 hover:border-blue-600 hover:text-blue-700">
                        9004
                    </button>

                </div>

            </div>

            {{-- Sample machine record --}}
            <div class="min-w-0">

                <div class="overflow-hidden rounded-lg border-2 border-blue-950 bg-white">

                    <div class="flex items-center justify-between gap-3 bg-blue-950 px-5 py-4 text-white sm:px-6">
                        <h3 class="font-signage text-xl font-bold">Machine History Preview</h3>
                        <span class="text-sm text-blue-100">Data contoh</span>
                    </div>

                    <div class="p-5 sm:p-6">

                        <div class="flex items-start justify-between gap-4">

                            <div class="min-w-0">
                                <p class="font-signage text-3xl font-bold text-blue-950">
                                    Machine <span class="font-mono text-2xl">#30001</span>
                                </p>
                                <p class="text-slate-700">Wet Wire Drawing Machine</p>
                            </div>

                            <span class="flex shrink-0 items-center gap-2 text-sm font-semibold text-green-800">
                                <span class="h-2.5 w-2.5 bg-green-600" aria-hidden="true"></span>
                                ACTIVE
                            </span>

                        </div>

                        <dl class="mt-6 grid grid-cols-2 gap-px bg-slate-200">

                            <div class="bg-white py-3 pr-3">
                                <dt class="text-sm text-slate-700">Last PM</dt>
                                <dd class="mt-1 font-semibold text-blue-950">29 Jul 2026</dd>
                            </div>

                            <div class="bg-white py-3 pl-4">
                                <dt class="text-sm text-slate-700">Next PM</dt>
                                <dd class="mt-1 font-semibold text-blue-950">29 Aug 2026</dd>
                            </div>

                            <div class="bg-white py-3 pr-3">
                                <dt class="text-sm text-slate-700">Problems</dt>
                                <dd class="mt-1 font-semibold text-red-700">2 Findings</dd>
                            </div>

                            <div class="bg-white py-3 pl-4">
                                <dt class="text-sm text-slate-700">Spareparts</dt>
                                <dd class="mt-1 font-semibold text-blue-700">3 Items</dd>
                            </div>

                        </dl>

                        <div class="mt-6 border-t-2 border-blue-950 pt-4">

                            <h4 class="font-signage text-lg font-semibold text-blue-950">Recent Maintenance</h4>

                            <ul class="mt-2 divide-y divide-slate-200 text-[15px]">
                                <li class="flex justify-between gap-3 py-2.5">
                                    <span>Bearing Inspection</span>
                                    <span class="text-slate-700">29 Jul</span>
                                </li>
                                <li class="flex justify-between gap-3 py-2.5">
                                    <span>Greasing</span>
                                    <span class="text-slate-700">29 Jul</span>
                                </li>
                                <li class="flex justify-between gap-3 py-2.5">
                                    <span>Belt Replacement</span>
                                    <span class="text-slate-700">29 Jul</span>
                                </li>
                            </ul>

                        </div>

                        <a href="{{ route('machine-history.index') }}"
                            class="mt-6 inline-flex min-h-12 w-full items-center justify-center rounded-md bg-blue-600 font-semibold text-white hover:bg-blue-700 active:bg-blue-800">
                            View Machine History
                        </a>

                    </div>

                </div>

            </div>

        </div>

    </div>

</section>
