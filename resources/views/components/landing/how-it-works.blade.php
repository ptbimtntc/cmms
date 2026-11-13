{{--
    The one deliberate dark section (RHYTHM 2's single break) and the one
    place the page explains the real PM workflow, step by step, instead of
    a generic "how it works" template. No scroll-triggered reveal: MOTION 1.
--}}
@php
    $steps = [
        ['scene' => 'schedule', 'title' => 'A schedule goes in', 'body' => 'PM schedules come from an Excel import or are entered one by one, then assigned to a PIC per area.'],
        ['scene' => 'checklist', 'title' => 'The PIC runs the PM', 'body' => 'Checklist, measurements, problems, and spareparts used are filled in on the spot. The system blocks closing the PM until every required field is in.'],
        ['scene' => 'scan', 'title' => 'Status updates itself', 'body' => 'A PM moves between five states on its own: open, in progress, finished, finished on time, or missed. A daily sweep flags anything past its due date.'],
        ['scene' => 'analysis', 'title' => 'The result is traceable', 'body' => 'Every PM shows up on the PM Status board, in machine history by QR, and as a PDF for that single visit.'],
    ];
@endphp

<section id="how-it-works" class="on-dark bg-blue-950 py-16 text-white lg:py-24">

    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">

        <div class="max-w-2xl">
            <p class="font-signage text-lg font-semibold text-blue-300">How it works</p>
            <h2 class="mt-2 font-signage text-3xl font-bold leading-tight sm:text-4xl">
                How a PM runs in FreeDOMS
            </h2>
        </div>

        <ol class="mt-12 grid gap-10 sm:grid-cols-2 lg:grid-cols-4 lg:gap-6">

            @foreach ($steps as $i => $step)
                <li class="min-w-0">

                    <div class="overflow-hidden rounded-card border border-blue-800">
                        <x-landing.story-frame :scene="$step['scene']" />
                    </div>

                    <div class="mt-5 flex items-baseline gap-3">
                        <span class="font-signage text-3xl font-bold leading-none text-blue-300" aria-hidden="true">
                            {{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}
                        </span>
                        <h3 class="font-signage text-xl font-bold">{{ $step['title'] }}</h3>
                    </div>

                    <p class="mt-3 text-blue-100">{{ $step['body'] }}</p>

                </li>
            @endforeach

        </ol>

    </div>

</section>
