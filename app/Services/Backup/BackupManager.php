<?php

namespace App\Services\Backup;

use App\Models\BackupRun;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Creates database and source-code backups, records every attempt in
 * backup_runs, and keeps the archive folder bounded. Failures are recorded as
 * failed runs and never update the "last successful backup" timestamp.
 */
class BackupManager
{
    /** Newest successful archives kept per type; older ones are deleted from disk. */
    public const KEEP_FILES_PER_TYPE = 5;

    /** Stale half-written temp files older than this are removed on the next run. */
    private const STALE_TEMP_SECONDS = 86400;

    /** Upper bound for one backup run, so a crashed request cannot hold the lock forever. */
    private const LOCK_SECONDS = 3600;

    public const REMINDER_NEVER = 'never';

    public const REMINDER_DUE = 'due';

    public const REMINDER_CURRENT = 'current';

    /** @var list<string> */
    public const TYPES = [BackupRun::TYPE_DATABASE, BackupRun::TYPE_SOURCE];

    public function __construct(
        private readonly DatabaseDumper $databaseDumper,
        private readonly SourceArchiver $sourceArchiver,
        private readonly GoogleDriveUploader $drive,
    ) {}

    /**
     * Runs one backup and always returns the recorded run (succeeded or failed).
     * $admin is null for scheduled runs. When Google Drive is configured, a
     * successful archive is also copied there; a failed upload is recorded in
     * drive_error and never turns the backup itself into a failed run.
     *
     * @throws BackupBusyException when the same type is already running.
     */
    public function create(string $type, ?User $admin, string $trigger = BackupRun::TRIGGER_MANUAL): BackupRun
    {
        $this->assertType($type);

        $lock = Cache::lock("backup:{$type}", self::LOCK_SECONDS);

        if (! $lock->get()) {
            throw new BackupBusyException('Backup jenis ini sedang berjalan. Tunggu hingga selesai, lalu coba lagi.');
        }

        try {
            $this->removeStaleTemporaryFiles();

            $run = BackupRun::create([
                'type' => $type,
                'status' => BackupRun::STATUS_RUNNING,
                'trigger' => $trigger,
                'started_at' => now(),
                'created_by_user_id' => $admin?->id,
            ]);

            $temporary = null;

            try {
                $name = $this->nextFileName($type);
                $temporary = $this->temporaryPath($name);
                $final = Storage::disk('local')->path($this->finalRelativePath($name));

                Storage::disk('local')->makeDirectory(BackupRun::DIRECTORY.'/tmp');

                $type === BackupRun::TYPE_DATABASE
                    ? $this->databaseDumper->dumpTo($temporary)
                    : $this->sourceArchiver->archive(base_path(), $temporary);

                chmod($temporary, 0600);

                if (! rename($temporary, $final)) {
                    throw new BackupStepException('Gagal menyimpan file backup.');
                }

                $run->forceFill([
                    'status' => BackupRun::STATUS_SUCCEEDED,
                    'file_name' => $name,
                    'file_size' => filesize($final) ?: 0,
                    'finished_at' => now(),
                ])->save();
            } catch (Throwable $e) {
                $this->deleteFile($temporary);

                // Only the exception class is logged: messages can contain paths or connection details.
                Log::error("Backup {$type} gagal.", ['exception' => $e::class]);

                $run->forceFill([
                    'status' => BackupRun::STATUS_FAILED,
                    'error_message' => $this->safeMessage($type, $e),
                    'finished_at' => now(),
                ])->save();
            }

            // Pruning runs after the outcome is recorded, so a cleanup problem
            // can never turn a finished archive into a "failed" run.
            if ($run->status === BackupRun::STATUS_SUCCEEDED) {
                $this->pruneOldArchives($type);

                if ($this->drive->isConfigured()) {
                    $this->copyToDrive($run);
                }
            }

            return $run->refresh();
        } finally {
            $lock->release();
        }
    }

    /** True when Google Drive credentials and folder are set. */
    public function driveConfigured(): bool
    {
        return $this->drive->isConfigured();
    }

    /** Days between automatic backups of this type. */
    public function intervalDays(string $type): int
    {
        $this->assertType($type);

        return max(1, (int) config("backup.interval_days.{$type}", $type === BackupRun::TYPE_DATABASE ? 7 : 14));
    }

    /**
     * True when the scheduler should create this type now: no scheduled backup
     * has succeeded yet, or the newest one is at least one interval old. A small
     * grace keeps a run that finished a few minutes after the daily check from
     * pushing the next one a whole day later.
     */
    public function isDue(string $type): bool
    {
        $last = BackupRun::query()->ofType($type)->succeeded()
            ->where('trigger', BackupRun::TRIGGER_SCHEDULED)
            ->latest('finished_at')->first();

        return $last === null
            || $last->finished_at->lte(now()->subDays($this->intervalDays($type))->addHours(2));
    }

    /**
     * Records that the server started streaming a file and returns its absolute path.
     */
    public function startDownload(BackupRun $run, User $admin): string
    {
        if (! $run->isDownloadable()) {
            throw new RuntimeException('File backup tidak tersedia lagi. Buat backup baru.');
        }

        $run->forceFill([
            'download_started_at' => now(),
            'downloaded_by_user_id' => $admin->id,
        ])->save();

        return $run->absolutePath();
    }

    /**
     * Reminder state per type, derived from persisted successful runs only.
     * A type is due once its newest backup is older than its schedule interval.
     *
     * @return array<string, array{state: string, last_backup_at: ?Carbon, days: int}>
     */
    public function reminders(): array
    {
        $result = [];

        foreach (self::TYPES as $type) {
            $last = $this->lastSucceeded($type);
            $days = $this->intervalDays($type);

            $state = match (true) {
                $last === null => self::REMINDER_NEVER,
                $last->finished_at->lt(now()->subDays($days)) => self::REMINDER_DUE,
                default => self::REMINDER_CURRENT,
            };

            $result[$type] = ['state' => $state, 'last_backup_at' => $last?->finished_at, 'days' => $days];
        }

        return $result;
    }

    /** True when at least one type needs a fresh backup (drives the dashboard reminder). */
    public function reminderNeeded(): bool
    {
        return collect($this->reminders())->contains(fn ($r) => $r['state'] !== self::REMINDER_CURRENT);
    }

    public function lastSucceeded(string $type): ?BackupRun
    {
        return BackupRun::query()->ofType($type)->succeeded()->latest('finished_at')->first();
    }

    /** Newest successful archive that is still on disk, or null. */
    public function latestDownloadable(string $type): ?BackupRun
    {
        return BackupRun::query()->ofType($type)->succeeded()->whereNull('file_removed_at')
            ->latest('id')->limit(self::KEEP_FILES_PER_TYPE)->get()
            ->first(fn (BackupRun $run) => $run->isDownloadable());
    }

    public function lastDownloadStarted(string $type): ?BackupRun
    {
        return BackupRun::query()->ofType($type)->whereNotNull('download_started_at')
            ->latest('download_started_at')->first();
    }

    /** Newest scheduled run of any type, or null when the scheduler has never run a backup. */
    public function lastScheduledRun(): ?BackupRun
    {
        return BackupRun::query()->where('trigger', BackupRun::TRIGGER_SCHEDULED)->latest('id')->first();
    }

    public function lastRun(string $type): ?BackupRun
    {
        return BackupRun::query()->ofType($type)->latest('id')->first();
    }

    /** @return Collection<int, BackupRun> */
    public function history(string $type, int $limit = 10)
    {
        return BackupRun::query()->ofType($type)->with(['creator:id,name', 'downloader:id,name'])
            ->latest('id')->limit($limit)->get();
    }

    private function assertType(string $type): void
    {
        if (! in_array($type, self::TYPES, true)) {
            throw new InvalidArgumentException("Jenis backup [{$type}] tidak dikenal.");
        }
    }

    private function nextFileName(string $type): string
    {
        $prefix = $type === BackupRun::TYPE_DATABASE ? 'freedoms-db' : 'freedoms-source';
        $extension = $type === BackupRun::TYPE_DATABASE ? '.sql.gz' : '.zip';
        $base = $prefix.'-'.now()->timezone(config('app.timezone'))->format('Ymd-Hi');

        // Never overwrite an earlier archive created within the same minute.
        $name = $base.$extension;

        for ($n = 2; Storage::disk('local')->exists($this->finalRelativePath($name)); $n++) {
            $name = "{$base}-{$n}{$extension}";
        }

        return $name;
    }

    private function finalRelativePath(string $name): string
    {
        return BackupRun::DIRECTORY.'/'.$name;
    }

    private function temporaryPath(string $name): string
    {
        return Storage::disk('local')->path(BackupRun::DIRECTORY.'/tmp/'.$name.'.part');
    }

    private function removeStaleTemporaryFiles(): void
    {
        $disk = Storage::disk('local');

        if (! $disk->exists(BackupRun::DIRECTORY.'/tmp')) {
            return;
        }

        foreach ($disk->files(BackupRun::DIRECTORY.'/tmp') as $file) {
            if ($disk->lastModified($file) < now()->subSeconds(self::STALE_TEMP_SECONDS)->getTimestamp()) {
                $disk->delete($file);
            }
        }
    }

    /** Keeps the newest KEEP_FILES_PER_TYPE archives on disk and marks the rest as removed. */
    private function pruneOldArchives(string $type): void
    {
        $stale = BackupRun::query()->ofType($type)->succeeded()->whereNull('file_removed_at')
            ->latest('id')->get()->slice(self::KEEP_FILES_PER_TYPE);

        foreach ($stale as $run) {
            Storage::disk('local')->delete($run->relativePath());
            $run->forceFill(['file_removed_at' => now()])->save();
        }
    }

    private function copyToDrive(BackupRun $run): void
    {
        try {
            $mime = $run->type === BackupRun::TYPE_DATABASE ? 'application/gzip' : 'application/zip';
            $fileId = $this->drive->upload($run->absolutePath(), (string) $run->file_name, $mime);

            $run->forceFill([
                'drive_file_id' => $fileId,
                'drive_uploaded_at' => now(),
                'drive_error' => null,
            ])->save();
        } catch (Throwable $e) {
            Log::error("Upload backup {$run->type} ke Google Drive gagal.", ['exception' => $e::class]);

            $run->forceFill([
                'drive_error' => $e instanceof BackupStepException
                    ? mb_substr($e->getMessage(), 0, 250)
                    : 'Gagal mengunggah ke Google Drive. Periksa log server.',
            ])->save();

            return;
        }

        $this->pruneDriveArchives($run->type);
    }

    /**
     * Keeps the newest configured number of Drive copies per type. Only files
     * this app uploaded (tracked in backup_runs) are ever deleted.
     */
    private function pruneDriveArchives(string $type): void
    {
        $keep = max(1, (int) config("backup.google_drive.keep.{$type}", 5));

        $stale = BackupRun::query()->ofType($type)->whereNotNull('drive_file_id')->whereNull('drive_removed_at')
            ->latest('id')->get()->slice($keep);

        foreach ($stale as $old) {
            try {
                $this->drive->delete($old->drive_file_id);
                $old->forceFill(['drive_removed_at' => now()])->save();
            } catch (Throwable $e) {
                // Left in place and retried after the next successful upload.
                Log::warning("Hapus backup lama {$type} di Google Drive gagal.", ['exception' => $e::class]);
            }
        }
    }

    private function deleteFile(?string $absolutePath): void
    {
        if ($absolutePath !== null && is_file($absolutePath)) {
            @unlink($absolutePath);
        }
    }

    /** Messages shown in the UI. Never includes paths, SQL, or credentials. */
    private function safeMessage(string $type, Throwable $e): string
    {
        if ($e instanceof BackupStepException) {
            return $e->getMessage();
        }

        return $type === BackupRun::TYPE_DATABASE
            ? 'Gagal membuat backup database. Periksa log server.'
            : 'Gagal membuat backup source code. Periksa log server.';
    }
}
