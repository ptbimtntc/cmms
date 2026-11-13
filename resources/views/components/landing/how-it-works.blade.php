{{-- Storyboard: each step gets an illustrated frame; frames open in sequence when the strip scrolls into view. --}}
@php
    $steps = [
        ['scene' => 'schedule', 'title' => 'Jadwalkan PM', 'body' => 'Koordinator mengunggah jadwal Preventive Maintenance dan melakukan assignment PIC sesuai area.'],
        ['scene' => 'checklist', 'title' => 'Lakukan PM', 'body' => 'PIC mengisi checklist digital, measurement, problem, sparepart, dan hasil inspeksi langsung melalui sistem.'],
        ['scene' => 'scan', 'title' => 'Scan QR Mesin', 'body' => 'Cukup scan QR Code pada mesin untuk melihat histori maintenance tanpa harus mencari dokumen.'],
        ['scene' => 'analysis', 'title' => 'Analisa & Evaluasi', 'body' => 'Seluruh histori maintenance tersimpan sehingga mempermudah analisa problem, evaluasi PM, dan pengambilan keputusan.'],
    ];
@endphp

<section id="how-it-works" class="on-dark bg-blue-950 py-16 text-white lg:py-28">

    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">

        <div class="grid gap-6 lg:grid-cols-[1fr_1fr] lg:items-end lg:gap-16">

            <div>
                <p class="font-signage text-lg font-semibold text-blue-300">How It Works</p>

                <h2 class="mt-2 font-signage text-3xl font-bold leading-tight sm:text-4xl lg:text-5xl">
                    Maintenance Menjadi Lebih Mudah Dalam 4 Langkah
                </h2>
            </div>

            <p class="max-w-xl text-lg text-blue-100 lg:justify-self-end">
                Seluruh proses Preventive Maintenance terdigitalisasi mulai dari
                penjadwalan hingga histori mesin hanya dalam satu platform.
            </p>

        </div>

        <ol class="mt-12 grid gap-10 sm:grid-cols-2 lg:mt-16 lg:grid-cols-4 lg:gap-6" data-reveal>

            @foreach ($steps as $i => $step)
                <li class="min-w-0">

                    <div class="frame overflow-hidden rounded-md border-2 border-blue-700" style="--d: {{ $i * 0.18 }}s">
                        <x-landing.story-frame :scene="$step['scene']" />
                    </div>

                    <div class="mt-5 flex items-baseline gap-3">
                        <span class="font-signage text-4xl font-bold leading-none text-blue-300" aria-hidden="true">
                            {{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}
                        </span>
                        <div>
                            <p class="text-sm font-medium text-blue-200">Step {{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}</p>
                            <h3 class="font-signage text-2xl font-bold">{{ $step['title'] }}</h3>
                        </div>
                    </div>

                    <p class="mt-3 text-blue-100">{{ $step['body'] }}</p>

                </li>
            @endforeach

        </ol>

    </div>

</section>
