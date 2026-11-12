@extends('layouts.app')

@section('title', 'Audit Oli')

@php($fullWidth = true)

@section('content')
    <div class="mx-auto max-w-3xl space-y-5" x-data="{ manualOpen: false }">
        <x-ui.page-header title="Audit Oli Cepat" description="Scan QR pada mesin area WWD, pilih kondisi oli, lalu lanjut ke mesin berikutnya." />

        @if (session('success'))
            <x-ui.alert tone="success">{{ session('success') }}</x-ui.alert>
        @endif
        @if (session('error'))
            <x-ui.alert tone="danger">{{ session('error') }}</x-ui.alert>
        @endif

        {{--
            Offline-first: minimal machine list + condition options for resources/js/oil-audits/scan.js
            to seed the offline machine cache and build the offline entry panel below. An online device
            never reads this data.
        --}}
        <div id="oil-audit-scan-data"
            data-machines='@json($offlineMachines ?? [])'
            data-conditions='@json($offlineConditions ?? [])'
            data-audited-today='@json($offlineAuditedToday ?? ['date' => null, 'machine_ids' => []])'
            data-autostart-scanner="{{ session('success') ? 'true' : 'false' }}"></div>

        <div id="oil-audit-offline-feedback" class="alert alert-info hidden" role="status"></div>

        <div id="oil-audit-scanner-view" class="space-y-4">
            {{-- Dark only where the camera picture is: it frames the live view, the rest of the page stays light. --}}
            <div class="overflow-hidden rounded-card border border-border bg-neutral-950">
                <div id="oil-audit-reader" class="min-h-[320px] bg-neutral-950 sm:min-h-[420px]"></div>
                <div class="border-t border-neutral-800 bg-neutral-900 p-4 text-center">
                    <p id="scanner-status" class="mb-3 text-sm text-neutral-300" aria-live="polite">Arahkan kamera ke QR code nomor mesin.</p>
                    <x-ui.button id="start-scanner" size="lg" class="focus-visible:outline-white">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 10l4.553 2.276A2 2 0 0 1 20.658 14l-4.553 2.276A2 2 0 0 1 13 14.484v-4.968A2 2 0 0 1 15 10z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 6h.01M3 12h.01M3 18h.01M21 6h.01M21 12h.01M21 18h.01" />
                        </svg>
                        Aktifkan Kamera
                    </x-ui.button>
                </div>
            </div>

            <div class="text-center">
                <button type="button" @click="manualOpen = !manualOpen" :aria-expanded="manualOpen.toString()" aria-controls="manual-machine-form"
                    class="inline-flex min-h-11 items-center rounded-control px-2 text-sm font-medium text-primary underline-offset-2 hover:underline">
                    QR tidak terbaca? Masukkan nomor mesin secara manual
                </button>
                <form x-show="manualOpen" x-cloak method="GET" action="{{ route('oil-audits.entry', ['machineNumber' => '__machine__']) }}"
                    class="mx-auto mt-2 flex max-w-md gap-2" id="manual-machine-form">
                    <label for="manual-machine-number" class="sr-only">Nomor mesin</label>
                    <input id="manual-machine-number" type="text" required autocomplete="off" placeholder="Contoh: 10001" class="input min-w-0 flex-1 uppercase">
                    <x-ui.button type="submit">Lanjut</x-ui.button>
                </form>
            </div>
        </div>

        {{--
            Offline-only inline entry panel: hidden unless resources/js/oil-audits/scan.js finds the
            device offline AND the machine is in the local cache. Built client-side; the online
            scan -> entry page navigation is untouched.
        --}}
        <div id="oil-audit-offline-panel" class="card hidden p-5 sm:p-7">
            <x-ui.button variant="ghost" size="sm" id="oil-audit-offline-rescan" class="mb-4 -ml-2">Scan mesin lain</x-ui.button>
            <div id="oil-audit-offline-machine-info"></div>
            <div class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-5" id="oil-audit-offline-condition-buttons"></div>
        </div>
    </div>

    <script src="https://unpkg.com/html5-qrcode" defer></script>

    @if ($promptStart ?? false)
        {{-- Daily Start prompt: PIC only, once per business day. NO (or Escape) simply dismisses it;
             the scan workflow is never blocked. --}}
        <div id="oil-audit-daily-prompt" data-ui-modal role="dialog" aria-modal="true" aria-labelledby="oil-audit-daily-prompt-title"
            class="fixed inset-0 z-50 flex items-center justify-center bg-neutral-900/40 p-4">
            <div class="w-full max-w-sm rounded-card border border-border bg-surface p-6 text-center shadow-popover">
                <h2 id="oil-audit-daily-prompt-title" class="text-lg font-semibold text-text">DO YOU WANT TO START OIL AUDIT?</h2>
                <p class="mt-1 text-sm text-text-muted">Waktu mulai dicatat sebagai aktivitas hari ini.</p>
                <div class="mt-5 flex justify-center gap-2">
                    <x-ui.button variant="outline" id="oil-audit-prompt-no" class="min-w-24">NO</x-ui.button>
                    <x-ui.button id="oil-audit-prompt-yes" class="min-w-24">YES</x-ui.button>
                </div>
            </div>
        </div>

        {{-- Start Time dialog, opened by YES. --}}
        <x-ui.modal id="oil-audit-start-modal" title="Start Oil Audit Activity" size="sm">
            <form method="POST" action="{{ route('oil-audits.start-daily') }}" class="mt-5 space-y-4">
                @csrf
                <x-ui.field label="Start date & time" for="oil-audit-start-input" required>
                    <input type="datetime-local" name="started_at" id="oil-audit-start-input" required class="input">
                </x-ui.field>
                <div class="flex justify-end gap-2 pt-2">
                    <x-ui.button variant="ghost" id="oil-audit-start-cancel">Cancel</x-ui.button>
                    <x-ui.button type="submit">START</x-ui.button>
                </div>
            </form>
        </x-ui.modal>

        <script>
            (function () {
                const prompt = document.getElementById('oil-audit-daily-prompt');
                const startModal = document.getElementById('oil-audit-start-modal');
                const input = document.getElementById('oil-audit-start-input');

                function nowLocal() {
                    const d = new Date();
                    d.setMinutes(d.getMinutes() - d.getTimezoneOffset());
                    return d.toISOString().slice(0, 16);
                }

                function show(el) { el.classList.remove('hidden'); el.classList.add('flex'); }
                function hide(el) { el.classList.add('hidden'); el.classList.remove('flex'); }

                document.getElementById('oil-audit-prompt-no').addEventListener('click', function () {
                    hide(prompt);
                });

                document.getElementById('oil-audit-prompt-yes').addEventListener('click', function () {
                    hide(prompt);
                    input.value = nowLocal();
                    show(startModal);
                });

                document.getElementById('oil-audit-start-cancel').addEventListener('click', function () {
                    hide(startModal);
                    show(prompt);
                });
            })();
        </script>
    @endif

    @include('partials.activity-conflict-modal')
@endsection
