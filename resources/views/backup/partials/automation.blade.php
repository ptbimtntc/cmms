@php
    $lastScheduled = $automation['last_scheduled'];
    $lastScheduledTime = $lastScheduled
        ? ($lastScheduled->finished_at ?? $lastScheduled->started_at)->timezone(config('app.timezone'))->format('d-m-Y H:i')
        : null;
    $cell = 'flex flex-col-reverse justify-end gap-1 bg-surface p-4';
@endphp

<section class="card overflow-hidden" aria-labelledby="backup-automation-title">
    <div class="p-5 sm:p-6">
        <h2 id="backup-automation-title" class="text-lg font-semibold text-text">Automatic backup</h2>
        <p class="mt-1 text-sm text-text-muted">The server takes its own backups on a schedule. A manual backup from the buttons below can still be made at any time and does not replace the scheduled one.</p>
    </div>

    <dl class="grid gap-px border-t border-border bg-border text-sm sm:grid-cols-3">
        <div class="bg-surface p-4">
            <dt class="text-xs text-text-muted">Schedule</dt>
            @if ($automation['enabled'])
                <dd class="mt-1 font-semibold text-text">
                    Database every {{ $automation['days'][\App\Models\BackupRun::TYPE_DATABASE] }} days, source code every {{ $automation['days'][\App\Models\BackupRun::TYPE_SOURCE] }} days
                </dd>
                <dd class="mt-1 text-xs text-text-muted">Checked daily at {{ $automation['time'] }} ({{ config('app.timezone') }}).</dd>
            @else
                <dd class="mt-1 font-semibold text-text">Turned off</dd>
                <dd class="mt-1 text-xs text-text-muted">Turn it on with BACKUP_SCHEDULE_ENABLED=true in .env.</dd>
            @endif
        </div>

        <div class="bg-surface p-4">
            <dt class="text-xs text-text-muted">Last automatic run</dt>
            @if ($lastScheduled === null)
                <dd class="mt-1 font-semibold text-text">Never</dd>
                <dd class="mt-1 text-xs text-warning-text">If the schedule has passed, the cron <code>schedule:run</code> is probably not installed on the server.</dd>
            @elseif ($lastScheduled->status === \App\Models\BackupRun::STATUS_FAILED)
                <dd class="mt-1 font-semibold text-danger">{{ $lastScheduledTime }}, failed</dd>
                <dd class="mt-1 text-xs text-danger">{{ $lastScheduled->error_message }}</dd>
            @else
                <dd class="mt-1 font-semibold text-text">{{ $lastScheduledTime }}</dd>
                <dd class="mt-1 text-xs text-text-muted">{{ $lastScheduled->type === \App\Models\BackupRun::TYPE_DATABASE ? 'Database backup' : 'Source code backup' }}</dd>
            @endif
        </div>

        <div class="bg-surface p-4">
            <dt class="text-xs text-text-muted">Google Drive copy</dt>
            @if ($automation['drive_configured'])
                <dd class="mt-1"><x-ui.badge tone="success">Active</x-ui.badge></dd>
                <dd class="mt-1 text-xs text-text-muted">
                    Keeps {{ $automation['keep'][\App\Models\BackupRun::TYPE_DATABASE] }} database files and {{ $automation['keep'][\App\Models\BackupRun::TYPE_SOURCE] }} sile source code terbaru.
                </dd>
            @else
                <dd class="mt-1 font-semibold text-text">Not configured</dd>
                <dd class="mt-1 text-xs text-warning-text">Files are kept on the server only. Setup steps are in docs/backup-google-drive.md.</dd>
            @endif
        </div>
    </dl>
</section>
