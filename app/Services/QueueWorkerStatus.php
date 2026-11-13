<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Whether a queue worker is alive, inferred from the heartbeat the
 * Looping listener (AppServiceProvider) writes while `queue:work` polls.
 */
class QueueWorkerStatus
{
    public const KEY = 'queue-worker-heartbeat';

    /** Heartbeat older than this means no worker is running. */
    public const STALE_SECONDS = 120;

    /**
     * @return array{active: bool, message: string, pending: int}
     */
    public static function check(): array
    {
        $pending = self::pending();
        $beat = (int) Cache::get(self::KEY, 0);

        if (config('queue.default') === 'sync') {
            return ['active' => true, 'message' => 'Queue memakai driver sync: email diproses langsung tanpa worker.', 'pending' => 0];
        }

        if ($beat > 0 && time() - $beat <= self::STALE_SECONDS) {
            return ['active' => true, 'message' => 'Sinyal terakhir '.max(0, time() - $beat).' detik lalu.', 'pending' => $pending];
        }

        return [
            'active' => false,
            'message' => $beat > 0
                ? 'No heartbeat since '.date('d-m-Y H:i', $beat).'. Jalankan: php artisan queue:work --tries=3'
                : 'The worker has never reported in. Run: php artisan queue:work --tries=3',
            'pending' => $pending,
        ];
    }

    private static function pending(): int
    {
        try {
            return config('queue.default') === 'database' ? DB::table('jobs')->count() : 0;
        } catch (Throwable) {
            return 0;
        }
    }
}
