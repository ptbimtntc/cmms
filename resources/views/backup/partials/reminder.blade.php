@php
    $backupManager = app(\App\Services\Backup\BackupManager::class);
    $backupReminders = collect($backupManager->reminders())->filter(
        fn ($reminder) => $reminder['state'] !== \App\Services\Backup\BackupManager::REMINDER_CURRENT
    );
    $backupLabels = [
        \App\Models\BackupRun::TYPE_DATABASE => 'Backup database',
        \App\Models\BackupRun::TYPE_SOURCE => 'Backup source code',
    ];
@endphp

{{-- Non-blocking reminder: a plain notice, never a modal, and shown only when a backup is missing or older than the reminder window. --}}
@if ($backupReminders->isNotEmpty())
    <div class="alert alert-warning flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between" role="status">
        <div class="space-y-1">
            <p class="font-semibold">Pengingat backup</p>
            @foreach ($backupReminders as $type => $reminder)
                <p>
                    {{ $backupLabels[$type] }}:
                    @if ($reminder['state'] === \App\Services\Backup\BackupManager::REMINDER_NEVER)
                        belum pernah dibuat.
                    @else
                        terakhir {{ $reminder['last_backup_at']->timezone(config('app.timezone'))->format('d-m-Y H:i') }}, sudah lebih dari {{ $reminder['days'] }} hari.
                    @endif
                </p>
            @endforeach
            <p class="text-xs">Pengingat ini hanya menandai riwayat di aplikasi. Pastikan file backup juga disimpan di lokasi lain.</p>
        </div>
        <a href="{{ route('backup.index') }}" class="btn btn-outline min-h-11 shrink-0">Buka Backup</a>
    </div>
@endif
