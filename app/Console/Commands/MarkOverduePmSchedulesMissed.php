<?php

namespace App\Console\Commands;

use App\Models\PMSchedule;
use Illuminate\Console\Command;

/**
 * Scheduled sweep for PM schedules nobody has touched past their due date.
 *
 * MISSED is normally computed reactively by
 * PMChecklistSaveService::updateStatus() — but only when a user opens that
 * specific PM's checklist and saves it. A schedule nobody interacts with
 * stays OPEN (or IN_PROGRESS, though that status always implies actual_date
 * is already set — see PMStartService::start()) forever once its due_date
 * passes. This command applies the exact same rule
 * (actual_date IS NULL AND due_date has passed) in bulk so the status
 * reflects reality without requiring a user to open the PM first.
 */
class MarkOverduePmSchedulesMissed extends Command
{
    protected $signature = 'pm-schedules:mark-overdue-missed';

    protected $description = 'Mark PM schedules as MISSED once their due date has passed without a PM being started';

    public function handle(): int
    {
        $count = PMSchedule::query()
            ->whereNull('actual_date')
            ->where('due_date', '<', now())
            ->where('status', '!=', 'MISSED')
            ->update(['status' => 'MISSED']);

        $this->info("Marked {$count} overdue PM schedule(s) as MISSED.");

        return self::SUCCESS;
    }
}
