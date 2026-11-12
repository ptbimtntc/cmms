@extends('layouts.app')

@php($fullWidth = true)

@section('title', 'Planning PM Mingguan')

@section('content')
    <div id="weekly-plan"
        data-save-url="{{ route('weekly-plan.items.save', $plan) }}"
        data-day-limits-url="{{ route('weekly-plan.day-limits.save', $plan) }}"
        data-autofill-url="{{ route('weekly-plan.autofill', $plan) }}"
        data-day-defaults-url="{{ route('weekly-plan.day-defaults.restore', $plan) }}"
        data-pic-sync-url="{{ route('weekly-plan.pic-sync.apply', $plan) }}"
        data-mark-sent-url="{{ route('weekly-plan.mark-sent', $plan) }}"
        data-pm-schedules-url="{{ route('pm-schedules.index') }}"
        class="space-y-5">

        <x-ui.page-header title="Planning PM {{ $areaModel->name }}" description="Minggu {{ $weekLabel }}. Jadwal ini terpisah dari PM Schedule: plan date dan PIC di sana tidak berubah.">
            <x-slot:actions>
                <x-ui.button variant="outline" href="{{ route('weekly-plan.show', ['area' => $areaModel->slug, 'week' => $prevWeek]) }}" class="flex-1 sm:flex-none lg:min-h-9">Minggu sebelumnya</x-ui.button>
                <x-ui.button variant="outline" href="{{ route('weekly-plan.show', ['area' => $areaModel->slug, 'week' => $nextWeek]) }}" class="flex-1 sm:flex-none lg:min-h-9">Minggu berikutnya</x-ui.button>
            </x-slot:actions>
        </x-ui.page-header>

        {{-- Area + any date in the week; the server snaps it to that week's Monday. --}}
        <form method="GET" action="{{ route('weekly-plan.index') }}" class="card flex flex-col gap-3 p-4 sm:flex-row sm:flex-wrap sm:items-end">
            <div class="grid gap-1.5 sm:w-40">
                <label for="wp-area" class="text-xs font-medium text-text-muted">Area</label>
                <select id="wp-area" name="area" class="select lg:min-h-9">
                    @foreach ($areas as $a)
                        <option value="{{ $a->slug }}" @selected($a->id === $areaModel->id)>{{ $a->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="grid gap-1.5 sm:w-56">
                <label for="wp-week" class="text-xs font-medium text-text-muted">Minggu (pilih tanggal mana saja)</label>
                <input id="wp-week" type="date" name="week" value="{{ $plan->week_start->toDateString() }}" class="input lg:min-h-9">
            </div>
            <x-ui.button type="submit" class="lg:min-h-9">Buka</x-ui.button>
        </form>

        {{-- Last week's realization (only when last week had a plan) --}}
        <section data-realization hidden class="card p-4 sm:p-5"></section>

        {{-- Board --}}
        <section class="card p-4 sm:p-5">
            {{-- Buttons keep a fixed order and label; changing text (save result,
                 PIC status) lives on its own line below so the buttons never move. --}}
            <div class="flex flex-col gap-3 xl:flex-row xl:items-start xl:justify-between">
                <div class="min-w-0 xl:max-w-xl">
                    <h2 class="text-base font-semibold text-text">Susun jadwal</h2>
                    <p class="text-sm text-text-muted">Seret kartu mesin ke hari, atau pilih hari langsung di kartunya. Isi otomatis mengikuti batas mesin per hari di judul tiap kolom.</p>
                </div>
                <div class="grid gap-2 sm:flex sm:shrink-0 sm:flex-wrap sm:items-center sm:gap-3 xl:justify-end">
                    <button type="button" class="btn btn-outline min-h-11 whitespace-normal sm:whitespace-nowrap lg:min-h-9" data-pic-sync>Terapkan PIC ke PM Schedule</button>
                    <button type="button" class="btn btn-outline min-h-11 whitespace-normal sm:whitespace-nowrap lg:min-h-9" data-reset>Kosongkan semua hari</button>
                    <button type="button" class="btn btn-primary min-h-11 whitespace-normal sm:whitespace-nowrap lg:min-h-9" data-autofill>Isi otomatis mesin yang due minggu ini</button>
                </div>
            </div>

            <div class="mt-3 mb-4 grid min-h-10 gap-1 border-t border-border pt-3 text-sm sm:grid-cols-2 sm:gap-4">
                <p class="text-text-muted" data-save-status role="status" aria-live="polite"></p>
                <p data-pic-sync-status class="text-text-muted sm:text-right" aria-live="polite"></p>
            </div>
            <div data-board-error hidden class="alert alert-danger mb-4"></div>
            <div data-board class="grid gap-4"></div>
        </section>

        {{-- Email output --}}
        <section class="card p-4 sm:p-5" data-email-panel></section>
    </div>

    <script type="application/json" id="weekly-plan-data">@json($payload)</script>
@endsection
