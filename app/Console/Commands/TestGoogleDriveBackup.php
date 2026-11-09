<?php

namespace App\Console\Commands;

use App\Services\Backup\BackupStepException;
use App\Services\Backup\GoogleDriveUploader;
use Illuminate\Console\Command;

/**
 * One-time setup helper for the Google Drive backup copy: creates the target
 * folder, or checks that the configured credentials can write to it.
 */
class TestGoogleDriveBackup extends Command
{
    protected $signature = 'backup:drive-test
        {--create-folder : Create a "FreeDOMS Backups" folder and print its ID for GOOGLE_DRIVE_FOLDER_ID}';

    protected $description = 'Check that backups can be uploaded to Google Drive';

    public function handle(GoogleDriveUploader $drive): int
    {
        try {
            if ($this->option('create-folder')) {
                $id = $drive->createFolder('FreeDOMS Backups');

                $this->info('Folder "FreeDOMS Backups" dibuat di Google Drive.');
                $this->line("Isi di .env: GOOGLE_DRIVE_FOLDER_ID={$id}");
                $this->line('Lalu jalankan: php artisan config:clear (atau config:cache) dan ulangi backup:drive-test.');

                return self::SUCCESS;
            }

            if (! $drive->isConfigured()) {
                $this->error('Google Drive belum dikonfigurasi. Isi GOOGLE_DRIVE_CLIENT_ID, GOOGLE_DRIVE_CLIENT_SECRET, GOOGLE_DRIVE_REFRESH_TOKEN, dan GOOGLE_DRIVE_FOLDER_ID.');

                return self::FAILURE;
            }

            $probe = tempnam(sys_get_temp_dir(), 'drive-test');
            file_put_contents($probe, 'FreeDOMS backup connection test '.now()->toIso8601String());

            try {
                $id = $drive->upload($probe, 'freedoms-drive-test.txt', 'text/plain');
                $drive->delete($id);
            } finally {
                @unlink($probe);
            }
        } catch (BackupStepException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Berhasil: file uji diunggah ke folder Google Drive lalu dihapus.');

        return self::SUCCESS;
    }
}
