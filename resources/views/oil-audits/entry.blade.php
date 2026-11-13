@extends('layouts.app')

@section('title', 'Oil Audit Entry')

@php
    $fullWidth = true;
    $viewOnly = auth()->user()->isSupervisor();
    $last = $machine->latestOilAudit;

    // Condition scale, mildest to worst. Colours carry the severity (they are status, not
    // decoration) and match the "last check" card so a result reads at a glance.
    $conditions = [
        ['key' => 'OKE', 'number' => '1', 'label' => 'Oke', 'detail' => 'Safe', 'class' => 'border-emerald-200 bg-emerald-50 text-emerald-800 hover:border-emerald-500 hover:bg-emerald-100 focus-visible:ring-emerald-300'],
        ['key' => 'PANTAU', 'number' => '2', 'label' => 'Pantau', 'detail' => 'Check regularly', 'class' => 'border-amber-200 bg-amber-50 text-amber-800 hover:border-amber-500 hover:bg-amber-100 focus-visible:ring-amber-300'],
        ['key' => 'HAMPIR_GARIS', 'number' => '3', 'label' => 'Hampir Garis', 'detail' => 'Needs action', 'class' => 'border-orange-200 bg-orange-50 text-orange-800 hover:border-orange-500 hover:bg-orange-100 focus-visible:ring-orange-300'],
        ['key' => 'PAS_GARIS', 'number' => '4', 'label' => 'Pas Garis', 'detail' => 'Needs action', 'class' => 'border-rose-200 bg-rose-50 text-rose-800 hover:border-rose-500 hover:bg-rose-100 focus-visible:ring-rose-300'],
        ['key' => 'KRITIS', 'number' => '5', 'label' => 'Kritis', 'detail' => 'Action now', 'class' => 'border-red-300 bg-red-50 text-red-900 hover:border-red-600 hover:bg-red-100 focus-visible:ring-red-300'],
        ['key' => 'OLI_KERUH', 'number' => '6', 'label' => 'Oli Hitam', 'detail' => 'Check oil quality', 'class' => 'border-amber-200 bg-amber-50 text-amber-800 hover:border-amber-500 hover:bg-amber-100 focus-visible:ring-amber-300'],
        ['key' => 'GLASS_BUREM', 'number' => '7', 'label' => 'LG Burem', 'detail' => 'Needs follow-up', 'class' => 'border-rose-200 bg-rose-50 text-rose-800 hover:border-rose-500 hover:bg-rose-100 focus-visible:ring-rose-300'],
    ];

    $lastTone = $last ? match ($last->condition) {
        'OKE' => 'border-emerald-200 bg-emerald-50 text-emerald-800',
        'PANTAU', 'OLI_KERUH' => 'border-amber-200 bg-amber-50 text-amber-800',
        'HAMPIR_GARIS' => 'border-orange-200 bg-orange-50 text-orange-800',
        'PAS_GARIS', 'GLASS_BUREM' => 'border-rose-200 bg-rose-50 text-rose-800',
        'KRITIS' => 'border-red-300 bg-red-50 text-red-900',
        default => 'border-border bg-surface text-text',
    } : null;
@endphp

@section('content')
    <div class="mx-auto max-w-4xl space-y-5">
        <x-ui.button variant="ghost" href="{{ route('oil-audits.scan') }}" class="-ml-2">Back to scan</x-ui.button>

        <section class="card flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:justify-between sm:p-6" aria-label="Machine">
            <div>
                <p class="text-sm font-medium text-text-muted">Machine detected</p>
                <h1 class="mt-1 font-signage text-4xl font-semibold leading-none text-text">{{ $machine->machine_number }}</h1>
                <p class="mt-2 text-sm text-text-muted">{{ $machine->machine_type }} · {{ $machine->area }}@if ($machine->description) · {{ $machine->description }}@endif</p>
            </div>
            <div class="rounded-control bg-surface-sunken px-4 py-3 text-sm">
                <span class="block font-semibold text-text">{{ auth()->user()->name }}</span>
                <span class="block text-xs text-text-muted">Auditing now</span>
            </div>
        </section>

        @if ($last)
            <section class="rounded-card border p-5 sm:p-6 {{ $lastTone }}" aria-label="Last check">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <p class="text-sm font-medium opacity-80">Last check</p>
                        <p class="mt-1 text-lg font-semibold">{{ $last->conditionLabel() }}</p>
                        <p class="mt-1 text-sm opacity-80">{{ $last->audited_at->format('d M Y, H:i') }} · by {{ $last->audited_by_name }}</p>
                    </div>
                    <div class="flex flex-col items-start gap-2 sm:items-end">
                        @if ($last->needsFollowUp() && $last->followUp)
                            <x-ui.badge tone="success">Followed up</x-ui.badge>
                        @elseif ($last->needsFollowUp())
                            <x-ui.badge tone="warning">Awaiting follow-up</x-ui.badge>
                        @endif
                        <span class="text-xs opacity-80">{{ $last->machine_type }} · {{ $last->area }}</span>
                    </div>
                </div>
            </section>
        @endif

        @if ($errors->any())
            <x-ui.alert tone="danger">{{ $errors->first() }}</x-ui.alert>
        @endif

        <section class="card p-5 sm:p-6" aria-labelledby="condition-heading">
            @if ($viewOnly)
                <x-ui.alert tone="info" class="mb-4">View only. A Supervisor cannot save a check result.</x-ui.alert>
            @endif
            <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h2 id="condition-heading" class="font-signage text-2xl font-semibold text-text">How does the oil look?</h2>
                    <p class="mt-1 text-sm text-text-muted">Pick one condition to save the audit right away. Press number keys <span class="font-semibold text-text">1 to 7</span> to go faster.</p>
                </div>
                <span class="text-xs text-text-muted">Saved automatically with the current time</span>
            </div>

            <div @class(['mt-6 grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-7', 'pointer-events-none opacity-50 grayscale' => $viewOnly]) id="condition-buttons" @if ($viewOnly) aria-disabled="true" @endif>
                @foreach ($conditions as $condition)
                    <form method="POST" action="{{ route('oil-audits.store') }}" data-condition-form="{{ $condition['number'] }}" @class(['h-full', 'col-span-2 sm:col-span-1' => $loop->last])>
                        @csrf
                        <input type="hidden" name="machine_id" value="{{ $machine->id }}">
                        <input type="hidden" name="condition" value="{{ $condition['key'] }}">
                        <button type="submit" @disabled($viewOnly)
                            aria-keyshortcuts="{{ $condition['number'] }}"
                            class="flex h-full min-h-28 w-full flex-col items-start rounded-card border-2 p-4 text-left transition-colors focus:outline-none focus-visible:ring-4 {{ $condition['class'] }}">
                            <span class="flex h-7 w-7 items-center justify-center rounded-full bg-white/90 text-xs font-bold shadow-card" aria-hidden="true">{{ $condition['number'] }}</span>
                            <span class="mt-3 text-base font-bold">{{ $condition['label'] }}</span>
                            <span class="mt-1 text-xs font-medium opacity-80">{{ $condition['detail'] }}</span>
                        </button>
                    </form>
                @endforeach
            </div>
        </section>
    </div>

    @unless ($viewOnly)
        <script>
            document.addEventListener('keydown', (event) => {
                if (['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement.tagName)) return;

                const form = document.querySelector(`[data-condition-form="${event.key}"]`);
                if (form) form.requestSubmit();
            });
        </script>
    @endunless
@endsection
