@extends('layouts.app')

@php
    $hideSidebar = true;
    $hideTopbar = true;
    $fullWidth = true;
    $contentMaxWidth = 'max-w-[110rem]';

    $labels = [
        'display' => ['VME Display', 'Menampilkan halaman peta VME (/vme).'],
        'activity_sync' => ['Today Activity Synchronization', 'Snapshot membaca aktivitas aktual PIC dari FreeDOMS. OFF = karakter selalu idle.'],
        'human_movement' => ['Human Character Movement', 'Karakter berjalan ke lokasi aktivitas. OFF = karakter tetap di basis.'],
        'ai_characters' => ['AI Character Display', 'Menampilkan karakter AI di Office (hanya memantau, tidak mengubah data FreeDOMS). OFF = karakter AI tidak tampil.'],
    ];
@endphp

@section('content')
<div class="space-y-6">

    <div class="card p-5 sm:p-6">
        <a href="{{ route('maintenance-intelligence.index') }}" class="btn btn-ghost -ml-2 min-h-11">Maintenance Intelligence Center</a>
        <h1 class="mt-3 font-signage text-2xl font-semibold leading-tight text-text sm:text-3xl">Virtual Maintenance Environment</h1>
        <p class="mt-1 text-sm text-text-muted">
            Visualisasi 2D aktivitas aktual FreeDOMS. VME hanya membaca data; mematikannya tidak memengaruhi PM, Greasing, Oil Audit, atau modul lain.
        </p>
        @if ($effective['display'])
            <a href="{{ route('vme.index') }}" class="btn btn-primary mt-3 min-h-11">Buka VME</a>
        @endif
    </div>

    @if (session('success'))
        <div class="alert alert-success" role="status">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
    @endif

    {{-- Feature switches --}}
    <section class="card p-5 sm:p-6">
        <h2 class="text-lg font-semibold text-text">Feature Switch</h2>
        @if ($killed)
            <div class="alert alert-danger mt-3" role="alert">
                Kill switch lingkungan aktif (<code>VME_KILL_SWITCH=true</code>): seluruh fitur VME dinonaktifkan, apa pun isi pengaturan di bawah.
            </div>
        @endif
        <form method="POST" action="{{ route('maintenance-intelligence.vme.settings') }}" class="mt-4 space-y-3">
            @csrf
            @method('PUT')
            @foreach ($labels as $key => [$title, $hint])
                <label class="flex cursor-pointer items-start gap-3 rounded-control border border-border p-4 hover:bg-surface-sunken">
                    <input type="checkbox" name="{{ $key }}" value="1" class="mt-0.5 h-4 w-4 shrink-0 rounded border-border-strong text-primary" @checked($features[$key])>
                    <span>
                        <span class="block text-sm font-semibold text-text">{{ $title }}
                            <span class="badge ml-2 {{ $effective[$key] ? 'badge-success' : 'badge-neutral' }}">{{ $effective[$key] ? 'ON' : 'OFF' }}</span>
                        </span>
                        <span class="block text-xs text-text-muted">{{ $hint }}</span>
                    </span>
                </label>
            @endforeach
            @if (count($aiCharacters))
                <input type="hidden" name="characters_submitted" value="1">
                <div class="rounded-control border border-border p-4">
                    <p class="text-sm font-semibold text-text">Karakter AI</p>
                    <p class="text-xs text-text-muted">Berlaku bila AI Character Display ON. Karakter yang dimatikan tidak tampil di peta.</p>
                    <div class="mt-2 space-y-1">
                        @foreach ($aiCharacters as $key => $c)
                            <label class="flex min-h-11 items-start gap-2 py-2 text-sm text-text">
                                <input type="checkbox" name="character_{{ $key }}" value="1" class="mt-0.5 h-4 w-4 shrink-0 rounded border-border-strong text-primary" @checked($c['enabled'])>
                                <span><span class="font-medium">{{ $c['name'] }}</span> <span class="text-xs text-text-muted">· {{ $c['role'] }}</span></span>
                            </label>
                        @endforeach
                    </div>
                    <a href="{{ route('maintenance-intelligence.vme.ai-runs') }}" class="mt-2 inline-flex min-h-11 items-center text-sm font-semibold text-primary underline-offset-2 hover:underline">Lihat kumpulan hasil AI</a>
                </div>
            @endif
            <button class="btn btn-primary min-h-11">Simpan</button>
        </form>
    </section>

    {{-- Avatars --}}
    <section class="card p-5 sm:p-6">
        <h2 class="text-lg font-semibold text-text">Avatar Karakter PIC</h2>
        <p class="mt-1 text-sm text-text-muted">Satu karakter per PIC aktif. PIC tanpa pilihan mendapat avatar otomatis.</p>
        <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @forelse ($pics as $pic)
                <form method="POST" action="{{ route('maintenance-intelligence.vme.avatar', $pic) }}" class="flex flex-col gap-2 rounded-control border border-border p-3 sm:flex-row sm:items-center">
                    @csrf
                    @method('PUT')
                    <div class="min-w-0 flex-1">
                        <div class="truncate text-sm font-medium text-text">{{ $pic->name }}</div>
                        <div class="text-xs text-text-muted">{{ $pic->area?->name ?? 'Tanpa area' }}</div>
                    </div>
                    <select name="avatar_key" class="select min-w-0 sm:w-auto lg:min-h-9" aria-label="Avatar {{ $pic->name }}">
                        @foreach ($avatars as $key => $a)
                            <option value="{{ $key }}" @selected(($profiles[$pic->id] ?? null) === $key)>{{ $a['label'] }}</option>
                        @endforeach
                    </select>
                    <button class="btn btn-outline btn-sm min-h-11 shrink-0 lg:min-h-9">Simpan</button>
                </form>
            @empty
                <p class="text-sm text-text-muted">Belum ada PIC aktif.</p>
            @endforelse
        </div>
    </section>

    {{-- Unmapped --}}
    <section class="card p-5 sm:p-6">
        <h2 class="text-lg font-semibold text-text">Aktivitas yang Belum Dapat Dipetakan</h2>
        <p class="mt-1 text-sm text-text-muted">
            PM dan Greasing menyimpan PIC sebagai nama. Bila nama dipakai lebih dari satu akun PIC, atau tidak cocok dengan PIC mana pun, VME tidak menebak dan tidak menampilkan aktivitasnya.
        </p>
        <div class="mt-3 grid gap-4 md:grid-cols-2">
            <div>
                <h3 class="text-sm font-semibold text-text">Nama PIC ganda</h3>
                <ul class="mt-2 space-y-1 text-sm text-text-muted">
                    @forelse ($diagnostics['ambiguous'] as $row)
                        <li>{{ $row['name'] }} <span class="text-xs text-text-muted">(user id {{ implode(', ', $row['user_ids']) }})</span></li>
                    @empty
                        <li class="text-text-muted">Tidak ada.</li>
                    @endforelse
                </ul>
            </div>
            <div>
                <h3 class="text-sm font-semibold text-text">Aktivitas hari ini tanpa PIC yang cocok</h3>
                <ul class="mt-2 space-y-1 text-sm text-text-muted">
                    @forelse ($diagnostics['orphans'] as $row)
                        <li>{{ $row['source'] }} #{{ $row['record_id'] }}: "{{ $row['pic'] }}"</li>
                    @empty
                        <li class="text-text-muted">Tidak ada.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </section>
</div>

@endsection
