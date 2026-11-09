@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-5xl space-y-6">
    <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
        <h1 class="text-2xl font-semibold text-slate-800">Backup &amp; Data Protection</h1>
        <p class="mt-1 text-sm text-slate-500">
            Buat dan unduh backup database serta source code yang sedang berjalan di server. Halaman ini hanya dapat diakses ADMIN.
            File backup berisi data dan konfigurasi sensitif (termasuk <code>.env</code>); simpan di tempat yang aman dan terbatas.
        </p>

        @if (session('success'))
            <div class="mt-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="mt-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ session('error') }}</div>
        @endif
    </div>

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
