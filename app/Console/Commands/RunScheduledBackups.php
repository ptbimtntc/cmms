<?php

namespace App\Console\Commands;

use App\Models\BackupRun;
use App\Services\Backup\BackupBusyException;
use App\Services\Backup\BackupManager;
use Illuminate\Console\Command;

/**
 * Automatic backups. The scheduler calls this once a day; each type is only
 * created when its interval has passed (database weekly, source code every two
 * weeks by default, see config/backup.php). Checking "is it due?" daily instead
 * of firing on a fixed weekday means a day the cron did not run is caught up
 * on the next check.
 */
class RunScheduledBackups extends Command
{
    protected $signature = 'backup:scheduled
        {--type=* : Only this type (database or source)}
        {--force : Create the backup even when it is not due yet}';

    protected $description = 'Create the database and source-code backups that are due, and copy them to Google Drive';

    public function handle(BackupManager $backups): int
    {
        $types = $this->option('type') ?: BackupManager::TYPES;
        $failed = false;

        foreach ($types as $type) {
            if (! in_array($type, BackupManager::TYPES, true)) {
                $this->error("Jenis backup tidak dikenal: {$type}");
                $failed = true;

                continue;
            }

            if (! $this->option('force') && ! $backups->isDue($type)) {
                $this->line("{$type}: belum jadwalnya, dilewati.");

                continue;
            }

            try {
                $run = $backups->create($type, null, BackupRun::TRIGGER_SCHEDULED);
            } catch (BackupBusyException) {
                $this->warn("{$type}: backup lain sedang berjalan, dilewati.");

                continue;
            }

            if ($run->status !== BackupRun::STATUS_SUCCEEDED) {
                $this->error("{$type}: gagal. {$run->error_message}");
                $failed = true;

                continue;
            }

            $this->info("{$type}: {$run->file_name} ({$run->sizeLabel()})");

            if ($run->drive_file_id !== null) {
                $this->info("{$type}: tersimpan di Google Drive.");
            } elseif ($run->drive_error !== null) {
                $this->error("{$type}: upload Google Drive gagal. {$run->drive_error}");
                $failed = true;
            } else {
                $this->warn("{$type}: Google Drive belum dikonfigurasi, file hanya ada di server.");
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
