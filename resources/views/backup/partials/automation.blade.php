@php
    $lastScheduled = $automation['last_scheduled'];
    $lastScheduledTime = $lastScheduled
        ? ($lastScheduled->finished_at ?? $lastScheduled->started_at)->timezone(config('app.timezone'))->format('d-m-Y H:i')
        : null;
@endphp

<section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm space-y-4" aria-labelledby="backup-automation-title">
    <div>
        <h2 id="backup-automation-title" class="text-lg font-semibold text-slate-800">Backup otomatis</h2>
        <p class="mt-1 text-sm text-slate-500">Server membuat backup sendiri sesuai jadwal. Backup manual dengan tombol di bawah tetap bisa dibuat kapan saja dan tidak menggeser jadwal.</p>
    </div>

    <dl class="grid gap-4 text-sm sm:grid-cols-3">
        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
            <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Jadwal</dt>
            @if ($automation['enabled'])
                <dd class="mt-1 font-medium text-slate-800">
                    Database tiap {{ $automation['days'][\App\Models\BackupRun::TYPE_DATABASE] }} hari, source code tiap {{ $automation['days'][\App\Models\BackupRun::TYPE_SOURCE] }} hari
                </dd>
                <dd class="mt-1 text-xs text-slate-500">Dicek setiap hari pukul {{ $automation['time'] }} ({{ config('app.timezone') }}).</dd>
            @else
                <dd class="mt-1 font-medium text-slate-800">Dimatikan</dd>
                <dd class="mt-1 text-xs text-slate-500">Aktifkan dengan BACKUP_SCHEDULE_ENABLED=true di .env.</dd>
            @endif
        </div>

        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
            <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Terakhir berjalan otomatis</dt>
            @if ($lastScheduled === null)
                <dd class="mt-1 font-medium text-slate-800">Belum pernah</dd>
                <dd class="mt-1 text-xs text-amber-800">Kalau sudah lewat jadwal, cron <code>schedule:run</code> di server kemungkinan belum terpasang.</dd>
            @elseif ($lastScheduled->status === \App\Models\BackupRun::STATUS_FAILED)
                <dd class="mt-1 font-medium text-red-600">{{ $lastScheduledTime }}, gagal</dd>
                <dd class="mt-1 text-xs text-red-600">{{ $lastScheduled->error_message }}</dd>
            @else
                <dd class="mt-1 font-medium text-slate-800">{{ $lastScheduledTime }}</dd>
                <dd class="mt-1 text-xs text-slate-500">{{ $lastScheduled->type === \App\Models\BackupRun::TYPE_DATABASE ? 'Backup database' : 'Backup source code' }}</dd>
            @endif
        </div>

        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
            <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Salinan Google Drive</dt>
            @if ($automation['drive_configured'])
                <dd class="mt-1 font-medium text-emerald-700">Aktif</dd>
                <dd class="mt-1 text-xs text-slate-500">
                    Menyimpan {{ $automation['keep'][\App\Models\BackupRun::TYPE_DATABASE] }} file database dan {{ $automation['keep'][\App\Models\BackupRun::TYPE_SOURCE] }} file source code terbaru.
                </dd>
            @else
                <dd class="mt-1 font-medium text-slate-800">Belum diatur</dd>
                <dd class="mt-1 text-xs text-amber-800">File hanya tersimpan di server. Langkah setup ada di docs/backup-google-drive.md.</dd>
            @endif
        </div>
    </dl>
</section>
