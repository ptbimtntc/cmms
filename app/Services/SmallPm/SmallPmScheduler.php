<?php

namespace App\Services\SmallPm;

use App\Exceptions\SmallPmWorkflowException;
use App\Models\SmallPmRow;
use App\Models\SmallPmSchedule;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The two things Small PM does on top of the Greasing-style schedule list:
 *  - generate: put rows without an OPEN schedule into consecutive weeks,
 *    rows_per_week at a time, in row order;
 *  - next cycle: once a schedule is executed, plan the same row again
 *    `cycle` weeks after the action date.
 */
class SmallPmScheduler
{
    public const TZ = 'Asia/Jakarta';

    public static function monday(CarbonInterface|string $date): CarbonImmutable
    {
        $d = is_string($date)
            ? CarbonImmutable::createFromFormat('!Y-m-d', $date, self::TZ)
            : CarbonImmutable::instance($date)->setTimezone(self::TZ);

        return $d->startOfWeek()->startOfDay();
    }

    public function rowsPerWeek(): int
    {
        return max(1, (int) config('small_pm.rows_per_week', 4));
    }

    /**
     * Rows (optionally of some areas) that have machines and no OPEN
     * schedule for this task, in generate order.
     *
     * @param  list<int>|null  $areaIds  null = every area
     * @return Collection<int, SmallPmRow>
     */
    public function unscheduledRows(string $taskType, ?array $areaIds = null): Collection
    {
        return SmallPmRow::query()
            ->when($areaIds !== null, fn ($q) => $q->whereIn('area_id', $areaIds))
            ->whereHas('machines')
            ->whereDoesntHave('schedules', fn ($q) => $q->where('task_type', $taskType)->where('status', 'OPEN'))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /**
     * @param  list<int>|null  $areaIds
     * @return Collection<int, SmallPmSchedule>
     */
    public function generate(string $taskType, CarbonInterface $startWeek, string $cycle, ?array $areaIds = null): Collection
    {
        $monday = self::monday($startWeek);

        return DB::transaction(function () use ($taskType, $monday, $cycle, $areaIds) {
            $rows = $this->unscheduledRows($taskType, $areaIds);

            if ($rows->isEmpty()) {
                throw new SmallPmWorkflowException('Every row that holds machines already has an OPEN schedule.');
            }

            return $rows->values()->map(function (SmallPmRow $row, int $i) use ($taskType, $monday, $cycle) {
                $planDate = $monday->addWeeks(intdiv($i, $this->rowsPerWeek()));

                return SmallPmSchedule::create([
                    'small_pm_row_id' => $row->id,
                    'task_type' => $taskType,
                    'cycle' => strtoupper($cycle),
                    'plan_date' => $planDate->toDateString(),
                    'due_date' => SmallPmSchedule::calculateDueDate($planDate->toDateString())->toDateString(),
                    'status' => 'OPEN',
                ]);
            });
        });
    }

    public function weeksFor(int $rowCount): int
    {
        return (int) ceil($rowCount / $this->rowsPerWeek());
    }

    /**
     * Plans (or, when the action date was edited, re-plans) the next cycle of
     * an executed schedule. A next schedule that was already started or
     * executed is left alone.
     */
    public function planNext(SmallPmSchedule $schedule): ?SmallPmSchedule
    {
        if (! $schedule->action_date || SmallPmSchedule::cycleWeeks($schedule->cycle) < 1) {
            return null;
        }

        $planDate = self::monday(CarbonImmutable::parse($schedule->action_date->toDateString(), self::TZ)
            ->addWeeks(SmallPmSchedule::cycleWeeks($schedule->cycle)));

        $next = SmallPmSchedule::where('generated_from_id', $schedule->id)->first();

        if ($next && ($next->start_time || $next->action_date)) {
            return $next;
        }

        $attrs = [
            'small_pm_row_id' => $schedule->small_pm_row_id,
            'task_type' => $schedule->task_type,
            'cycle' => $schedule->cycle,
            'plan_date' => $planDate->toDateString(),
            'due_date' => SmallPmSchedule::calculateDueDate($planDate->toDateString())->toDateString(),
            'status' => 'OPEN',
            'generated_from_id' => $schedule->id,
        ];

        if ($next) {
            $next->update($attrs);

            return $next;
        }

        return SmallPmSchedule::create($attrs);
    }
}
