<?php

namespace App\Console\Commands;

use App\Models\PicDisciplineEntry;
use Illuminate\Console\Command;

/**
 * Scheduled sweep so a manual PIC discipline item nobody ever records stays
 * accurate: once its due_date passes without a completed_date, it should
 * read BREACH rather than being stuck at PENDING forever — same idea as
 * MarkOverduePmSchedulesMissed for pm_schedules.
 */
class MarkOverduePicDisciplineEntriesBreached extends Command
{
    protected $signature = 'pic-discipline:mark-overdue-breached';

    protected $description = 'Mark PIC discipline entries as BREACH once their due date has passed without being completed';

    public function handle(): int
    {
        $count = PicDisciplineEntry::query()
            ->where('status', PicDisciplineEntry::STATUS_PENDING)
            ->where('due_date', '<', now()->startOfDay())
            ->update(['status' => PicDisciplineEntry::STATUS_BREACH]);

        $this->info("Marked {$count} overdue PIC discipline entrie(s) as BREACH.");

        return self::SUCCESS;
    }
}
