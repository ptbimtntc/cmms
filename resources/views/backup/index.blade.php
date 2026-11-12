@extends('layouts.app')

@section('title')Backup & Data Protection@endsection

@section('content')
    @php
        $fullWidth = true;
        $contentMaxWidth = 'max-w-5xl';
    @endphp

    <div class="space-y-5">
        <header>
            <h1 class="font-signage text-2xl font-semibold leading-tight text-text sm:text-3xl">Backup &amp; Data Protection</h1>
            <p class="mt-1 max-w-prose text-sm text-text-muted sm:text-base">
                Buat dan unduh backup database serta source code yang sedang berjalan di server. Halaman ini hanya dapat diakses ADMIN.
                File backup berisi data dan konfigurasi sensitif (termasuk <code>.env</code>); simpan di tempat yang aman dan terbatas.
            </p>
        </header>

        @if (session('success'))
            <x-ui.alert tone="success">{{ session('success') }}</x-ui.alert>
        @endif
        @if (session('error'))
            <x-ui.alert tone="danger">{{ session('error') }}</x-ui.alert>
        @endif

        @include('backup.partials.reminder')

        @include('backup.partials.automation')

        @include('backup.partials.section', [
            'type' => \App\Models\BackupRun::TYPE_DATABASE,
            'title' => 'A. Backup Database',
            'description' => 'Berisi struktur tabel, indeks, constraint, dan seluruh data dari semua tabel aplikasi, dalam format .sql.gz.',
            'action' => route('backup.database.store'),
            'buttonLabel' => 'Buat Backup Database',
            'section' => $sections[\App\Models\BackupRun::TYPE_DATABASE],
            'notes' => 'Dibuat dari transaksi baca yang konsisten. Tabel session, cache, dan job antrean ikut tercakup karena disimpan di database.',
        ])

        @include('backup.partials.section', [
            'type' => \App\Models\BackupRun::TYPE_SOURCE,
            'title' => 'B. Backup Source Code Production',
            'description' => 'Arsip .zip berisi kode yang sedang dijalankan di server, termasuk .env, vendor, public/build, dan file tersembunyi.',
            'action' => route('backup.source.store'),
            'buttonLabel' => 'Buat Backup Source Code',
            'section' => $sections[\App\Models\BackupRun::TYPE_SOURCE],
            'notes' => 'Tidak disertakan: isi storage/ (file upload seperti avatar, log, cache, sesi, dan backup lama), bootstrap/cache, node_modules, dan symbolic link. Backup database dan file storage harus dibuat terpisah.',
        ])
    </div>
@endsection
