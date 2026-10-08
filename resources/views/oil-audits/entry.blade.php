@extends('layouts.app')

@section('title', 'Input Audit Oli')

@section('content')
    <div class="mx-auto max-w-4xl">
        <a href="{{ route('oil-audits.scan') }}" class="mb-5 inline-flex items-center gap-2 text-sm font-semibold text-slate-600 transition hover:text-slate-900">
            <span aria-hidden="true">←</span> Kembali ke scan
        </a>

        <div class="mb-6 overflow-hidden rounded-3xl border border-sky-100 bg-gradient-to-br from-sky-50 via-white to-white p-5 shadow-sm sm:p-7">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-sky-700">Mesin terdeteksi</p>
                    <h1 class="mt-1 font-mono text-3xl font-bold tracking-tight text-slate-950">{{ $machine->machine_number }}</h1>
                    <p class="mt-1 text-sm text-slate-600">{{ $machine->machine_type }} · {{ $machine->area }}@if($machine->description) · {{ $machine->description }}@endif</p>
                </div>
                <div class="rounded-2xl bg-white px-4 py-3 text-sm text-slate-600 shadow-sm ring-1 ring-slate-200">
                    <span class="font-semibold text-slate-900">{{ auth()->user()->name }}</span>
                    <span class="block text-xs">Pengaudit saat ini</span>
                </div>
            </div>
        </div>

        @if ($machine->latestOilAudit)
            <div class="mb-5 overflow-hidden rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Riwayat pengecekan terakhir</p>
                        <p class="mt-1 text-lg font-semibold text-slate-900">{{ $machine->latestOilAudit->conditionLabel() }}</p>
                        <p class="mt-2 text-sm text-slate-600">
                            {{ $machine->latestOilAudit->audited_at->format('d M Y, H:i') }} · oleh {{ $machine->latestOilAudit->audited_by_name }}
                        </p>
                        @if ($machine->latestOilAudit->needsFollowUp() && $machine->latestOilAudit->followUp)
                            <p class="mt-3 inline-flex rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-700">Telah ditindaklanjuti</p>
                        @elseif ($machine->latestOilAudit->needsFollowUp())
                            <p class="mt-3 inline-flex rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-700">Menunggu tindak lanjut</p>
                        @endif
                    </div>
                    <div class="rounded-2xl bg-slate-50 px-4 py-3 text-sm text-slate-600 shadow-sm ring-1 ring-slate-200">
                        <p class="font-semibold text-slate-900">Audit terakhir</p>
                        <p class="mt-1 text-xs">{{ $machine->latestOilAudit->machine_type }} · {{ $machine->latestOilAudit->area }}</p>
                    </div>
                </div>
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-5 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                {{ $errors->first() }}
            </div>
        @endif

        @php
            $viewOnly = auth()->user()->isSupervisor();
        @endphp

        <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
            @if ($viewOnly)
                <p class="mb-4 rounded-xl border border-slate-200 bg-slate-100 px-4 py-2 text-sm text-slate-600">Mode lihat saja — Supervisor tidak dapat menyimpan hasil pengecekan.</p>
            @endif
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h2 class="text-xl font-bold text-slate-900">Bagaimana kondisi oli mesin?</h2>
                    <p class="mt-1 text-sm text-slate-500">Pilih satu kondisi untuk menyimpan audit sekarang. Tekan tombol angka <span class="font-semibold">1–7</span> untuk lebih cepat.</p>
                </div>
                <span class="text-xs font-medium text-slate-400">Tersimpan otomatis dengan waktu saat ini</span>
            </div>

            <div @class(['mt-6 grid grid-cols-2 gap-3 sm:grid-cols-5', 'pointer-events-none opacity-50 grayscale' => $viewOnly]) id="condition-buttons" @if ($viewOnly) aria-disabled="true" @endif>
                @php
                    $conditions = [
                        ['key' => 'OKE', 'number' => '1', 'label' => 'Oke', 'detail' => 'Aman', 'class' => 'border-emerald-200 bg-emerald-50 text-emerald-800 hover:border-emerald-500 hover:bg-emerald-100 focus:ring-emerald-300'],
                        ['key' => 'PANTAU', 'number' => '2', 'label' => 'Pantau', 'detail' => 'Periksa berkala', 'class' => 'border-amber-200 bg-amber-50 text-amber-800 hover:border-amber-500 hover:bg-amber-100 focus:ring-amber-300'],
                        ['key' => 'HAMPIR_GARIS', 'number' => '3', 'label' => 'Hampir Garis', 'detail' => 'Perlu action', 'class' => 'border-orange-200 bg-orange-50 text-orange-800 hover:border-orange-500 hover:bg-orange-100 focus:ring-orange-300'],
                        ['key' => 'PAS_GARIS', 'number' => '4', 'label' => 'Pas Garis', 'detail' => 'Perlu action', 'class' => 'border-rose-200 bg-rose-50 text-rose-800 hover:border-rose-500 hover:bg-rose-100 focus:ring-rose-300'],
                        ['key' => 'KRITIS', 'number' => '5', 'label' => 'Kritis', 'detail' => 'Segera action', 
                        'class' => 'border-red-300 bg-red-50 text-red-900 hover:border-red-600 hover:bg-red-100 focus:ring-red-300'],
                        ['key' => 'OLI_KERUH', 'number' => '6', 'label' => 'Oli Hitam', 'detail' => 'Periksa kualitas oli', 
                        'class' => 'border-amber-200 bg-amber-50 text-amber-800 hover:border-amber-500 hover:bg-amber-100 focus:ring-amber-300'],
                        ['key' => 'GLASS_BUREM', 'number' => '7', 'label' => 'LG Burem', 'detail' => 'Butuh tindak lanjut', 
                        'class' => 'border-rose-200 bg-rose-50 text-rose-800 hover:border-rose-500 hover:bg-rose-100 focus:ring-rose-300'],
                    ];
                @endphp
                @foreach ($conditions as $condition)
                    <form method="POST" action="{{ route('oil-audits.store') }}" data-condition-form="{{ $condition['number'] }}" @class(['col-span-2 sm:col-span-1' => $loop->last])>
                        @csrf
                        <input type="hidden" name="machine_id" value="{{ $machine->id }}">
                        <input type="hidden" name="condition" value="{{ $condition['key'] }}">
                        <button type="submit" @disabled($viewOnly) class="group flex min-h-28 w-full flex-col items-start rounded-2xl border-2 p-4 text-left transition focus:outline-none focus:ring-4 sm:min-h-32 {{ $condition['class'] }}">
                            <span class="flex h-7 w-7 items-center justify-center rounded-full bg-white/90 text-xs font-bold shadow-sm">{{ $condition['number'] }}</span>
                            <span class="mt-3 text-base font-bold">{{ $condition['label'] }}</span>
                            <span class="mt-1 text-xs font-medium opacity-75">{{ $condition['detail'] }}</span>
                        </button>
                    </form>
                @endforeach
            </div>
        </div>
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
