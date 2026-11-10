{{--
    Say why the list is empty and give the one action that fills it.
    <x-ui.empty-state title="Belum ada jadwal PM bulan ini"
        description="Jadwal muncul di sini setelah dibuat dari menu PM Schedule.">
        <x-ui.button href="{{ route('pm-schedules.create') }}">Buat jadwal</x-ui.button>
    </x-ui.empty-state>
    tone="error" switches to the error variant (role=alert).
--}}
@props([
    'title',
    'description' => null,
    'tone' => 'empty',
])

@php $isError = $tone === 'error'; @endphp

<div {{ $attributes->merge(['class' => 'flex flex-col items-center gap-3 rounded-card border border-dashed px-6 py-10 text-center ' . ($isError ? 'border-danger/40 bg-danger-light' : 'border-border-strong bg-surface-sunken')]) }}
     @if ($isError) role="alert" @endif>
    <p @class(['text-base font-semibold', 'text-danger-hover' => $isError, 'text-text' => ! $isError])>{{ $title }}</p>

    @if ($description)
        <p class="max-w-md text-sm text-text-muted">{{ $description }}</p>
    @endif

    @if (trim($slot) !== '')
        <div class="mt-1 flex flex-wrap justify-center gap-2">{{ $slot }}</div>
    @endif
</div>
