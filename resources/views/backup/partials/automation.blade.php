@php
    $lastScheduled = $automation['last_scheduled'];
    $lastScheduledTime = $lastScheduled
        ? ($lastScheduled->finished_at ?? $lastScheduled->started_at)->timezone(config('app.timezone'))->format('d-m-Y H:i')
        : null;
    $cell = 'flex flex-col-reverse justify-end gap-1 bg-surface p-4';
@endphp

<section class="card overflow-hidden" aria-labelledby="backup-automation-title">
    <div class="p-5 sm:p-6">
        <h2 id="backup-automation-title" class="text-lg font-semibold text-text">Backup otomatis</h2>
        <p class="mt-1 text-sm text-text-muted">Server membuat backup sendiri sesuai jadwal. Backup manual dengan tombol di bawah tetap bisa dibuat kapan saja dan tidak menggeser jadwal.</p>
    </div>

    <dl class="grid gap-px border-t border-border bg-border text-sm sm:grid-cols-3">
        <div class="bg-surface p-4">
            <dt class="text-xs text-text-muted">Jadwal</dt>
            @if ($automation['enabled'])
                <dd class="mt-1 font-semibold text-text">
                    Database tiap {{ $automation['days'][\App\Models\BackupRun::TYPE_DATABASE] }} hari, source code tiap {{ $automation['days'][\App\Models\BackupRun::TYPE_SOURCE] }} hari
                </dd>
                <dd class="mt-1 text-xs text-text-muted">Dicek setiap hari pukul {{ $automation['time'] }} ({{ config('app.timezone') }}).</dd>
            @else
                <dd class="mt-1 font-semibold text-text">Dimatikan</dd>
                <dd class="mt-1 text-xs text-text-muted">Aktifkan dengan BACKUP_SCHEDULE_ENABLED=true di .env.</dd>
            @endif
        </div>

        <div class="bg-surface p-4">
            <dt class="text-xs text-text-muted">Terakhir berjalan otomatis</dt>
            @if ($lastScheduled === null)
                <dd class="mt-1 font-semibold text-text">Belum pernah</dd>
                <dd class="mt-1 text-xs text-warning-text">Kalau sudah lewat jadwal, cron <code>schedule:run</code> di server kemungkinan belum terpasang.</dd>
            @elseif ($lastScheduled->status === \App\Models\BackupRun::STATUS_FAILED)
                <dd class="mt-1 font-semibold text-danger">{{ $lastScheduledTime }}, gagal</dd>
                <dd class="mt-1 text-xs text-danger">{{ $lastScheduled->error_message }}</dd>
            @else
                <dd class="mt-1 font-semibold text-text">{{ $lastScheduledTime }}</dd>
                <dd class="mt-1 text-xs text-text-muted">{{ $lastScheduled->type === \App\Models\BackupRun::TYPE_DATABASE ? 'Backup database' : 'Backup source code' }}</dd>
            @endif
        </div>

        <div class="bg-surface p-4">
            <dt class="text-xs text-text-muted">Salinan Google Drive</dt>
            @if ($automation['drive_configured'])
                <dd class="mt-1"><x-ui.badge tone="success">Aktif</x-ui.badge></dd>
                <dd class="mt-1 text-xs text-text-muted">
                    Menyimpan {{ $automation['keep'][\App\Models\BackupRun::TYPE_DATABASE] }} file database dan {{ $automation['keep'][\App\Models\BackupRun::TYPE_SOURCE] }} file source code terbaru.
                </dd>
            @else
                <dd class="mt-1 font-semibold text-text">Belum diatur</dd>
                <dd class="mt-1 text-xs text-warning-text">File hanya tersimpan di server. Langkah setup ada di docs/backup-google-drive.md.</dd>
            @endif
        </div>
    </dl>
</section>
