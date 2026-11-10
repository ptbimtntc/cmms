{{--
    <x-ui.page-header title="Jadwal PM" description="Mesin yang dijadwalkan bulan ini.">
        <x-slot:actions><x-ui.button href="...">Tambah jadwal</x-ui.button></x-slot:actions>
    </x-ui.page-header>
--}}
@props([
    'title',
    'description' => null,
])

<header {{ $attributes->merge(['class' => 'mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between']) }}>
    <div class="min-w-0">
        <h1 class="font-signage text-2xl font-semibold leading-tight text-text sm:text-3xl">{{ $title }}</h1>
        @if ($description)
            <p class="mt-1 max-w-prose text-sm text-text-muted sm:text-base">{{ $description }}</p>
        @endif
    </div>

    @isset($actions)
        <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
    @endisset
</header>
