@php
    $backupManager = app(\App\Services\Backup\BackupManager::class);
    $backupReminders = collect($backupManager->reminders())->filter(
        fn ($reminder) => $reminder['state'] !== \App\Services\Backup\BackupManager::REMINDER_CURRENT
    );
    $backupLabels = [
        \App\Models\BackupRun::TYPE_DATABASE => 'Database backup',
        \App\Models\BackupRun::TYPE_SOURCE => 'Source code backup',
    ];
@endphp

{{-- Non-blocking reminder: a plain notice, never a modal, and shown only when a backup is missing or older than the reminder window. --}}
@if ($backupReminders->isNotEmpty())
    <div class="alert alert-warning flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between" role="status">
        <div class="space-y-1">
            <p class="font-semibold">Backup reminder</p>
            @foreach ($backupReminders as $type => $reminder)
                <p>
                    {{ $backupLabels[$type] }}:
                    @if ($reminder['state'] === \App\Services\Backup\BackupManager::REMINDER_NEVER)
                        has never been created.
                    @else
                        last taken {{ $reminder['last_backup_at']->timezone(config('app.timezone'))->format('d-m-Y H:i') }}, more than {{ $reminder['days'] }} days ago.
                    @endif
                </p>
            @endforeach
            <p class="text-xs">This reminder only reads the history inside the app. Make sure the backup files are also kept somewhere else.</p>
        </div>
        <a href="{{ route('backup.index') }}" class="btn btn-outline min-h-11 shrink-0">Open Backup</a>
    </div>
@endif
