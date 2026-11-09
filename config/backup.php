<?php

return [

    /*
    | Automatic backups (php artisan backup:scheduled, run daily by the scheduler).
    | A type is created only when its last scheduled success is older than its interval.
    */
    'schedule_enabled' => (bool) env('BACKUP_SCHEDULE_ENABLED', true),

    'schedule_time' => env('BACKUP_SCHEDULE_TIME', '02:00'),

    'interval_days' => [
        'database' => (int) env('BACKUP_INTERVAL_DAYS_DATABASE', 7),
        'source' => (int) env('BACKUP_INTERVAL_DAYS_SOURCE', 14),
    ],

    /*
    | Google Drive copy. Uses OAuth with a refresh token from a personal Google
    | account (service accounts cannot write to a personal "My Drive").
    | Setup steps: docs/backup-google-drive.md
    */
    'google_drive' => [
        'client_id' => env('GOOGLE_DRIVE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_DRIVE_CLIENT_SECRET'),
        'refresh_token' => env('GOOGLE_DRIVE_REFRESH_TOKEN'),
        'folder_id' => env('GOOGLE_DRIVE_FOLDER_ID'),

        // Newest files kept in Drive per type; older ones uploaded by this app are deleted.
        'keep' => [
            'database' => (int) env('BACKUP_DRIVE_KEEP_DATABASE', 8),
            'source' => (int) env('BACKUP_DRIVE_KEEP_SOURCE', 6),
        ],

        'chunk_bytes' => 8 * 1024 * 1024,
    ],

];
