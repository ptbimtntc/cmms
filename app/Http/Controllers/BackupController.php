<?php

namespace App\Http\Controllers;

use App\Models\BackupRun;
use App\Models\User;
use App\Services\Backup\BackupBusyException;
use App\Services\Backup\BackupManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Backup & Data Protection. Every action is also behind the role:ADMIN route
 * group; the check here repeats it so the controller stays safe if the route
 * is ever moved.
 */
class BackupController extends Controller
{
    public function __construct(private readonly BackupManager $backups) {}

    public function index(): View
    {
        $this->admin();

        $sections = [];

        foreach (BackupManager::TYPES as $type) {
            $sections[$type] = [
                'last_success' => $this->backups->lastSucceeded($type),
                'last_download' => $this->backups->lastDownloadStarted($type),
                'last_run' => $this->backups->lastRun($type),
                'downloadable' => $this->backups->latestDownloadable($type),
                'history' => $this->backups->history($type),
            ];
        }

        return view('backup.index', [
            'sections' => $sections,
            'reminders' => $this->backups->reminders(),
            'automation' => [
                'enabled' => (bool) config('backup.schedule_enabled'),
                'time' => config('backup.schedule_time'),
                'days' => collect(BackupManager::TYPES)->mapWithKeys(fn ($type) => [$type => $this->backups->intervalDays($type)]),
                'keep' => config('backup.google_drive.keep'),
                'drive_configured' => $this->backups->driveConfigured(),
                'last_scheduled' => $this->backups->lastScheduledRun(),
            ],
        ]);
    }

    public function storeDatabase(): RedirectResponse
    {
        return $this->create(BackupRun::TYPE_DATABASE);
    }

    public function storeSource(): RedirectResponse
    {
        return $this->create(BackupRun::TYPE_SOURCE);
    }

    public function download(BackupRun $backupRun): BinaryFileResponse|RedirectResponse
    {
        $admin = $this->admin();

        if (! $backupRun->isDownloadable()) {
            return redirect()->route('backup.index')
                ->with('error', 'That backup file is no longer available. Create a new backup.');
        }

        $path = $this->backups->startDownload($backupRun, $admin);

        $response = response()->download($path, $backupRun->file_name);
        $response->headers->addCacheControlDirective('no-store');
        $response->setPrivate();

        return $response;
    }

    private function create(string $type): RedirectResponse
    {
        $admin = $this->admin();

        try {
            $run = $this->backups->create($type, $admin);
        } catch (BackupBusyException $e) {
            return redirect()->route('backup.index')->with('error', $e->getMessage());
        }

        $label = $type === BackupRun::TYPE_DATABASE ? 'Backup database' : 'Backup source code';

        if ($run->status === BackupRun::STATUS_SUCCEEDED) {
            return redirect()->route('backup.index')->with('success', "{$label} berhasil dibuat: {$run->file_name}.");
        }

        return redirect()->route('backup.index')->with('error', "{$label} gagal. {$run->error_message}");
    }

    private function admin(): User
    {
        /** @var User|null $user */
        $user = Auth::user();

        abort_unless($user?->isAdmin(), 403, 'Unauthorized');

        return $user;
    }
}
