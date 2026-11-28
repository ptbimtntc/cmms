<?php

namespace App\Exports;

use App\Models\PMSchedule;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\FromArray;

class PMScheduleExport implements FromArray
{
    /**
     * Status text mirrors resources/views/pm-schedules/partials/status-badge.blade.php
     * so the exported value matches the badge shown on screen.
     */
    private const STATUS_LABELS = [
        'OPEN' => 'OPEN',
        'IN_PROGRESS' => 'IN PROGRESS',
        'FINISHED' => 'FINISHED',
        'FINISHED_ON_TIME' => 'FINISHED ON TIME',
        'MISSED' => 'MISSED',
    ];

    /**
     * @param  Collection<int, PMSchedule>  $schedules
     */
    public function __construct(private Collection $schedules) {}

    public function array(): array
    {
        $rows = [[
            'Machine Number', 'Machine Type', 'Order Number', 'Plan Date', 'Action Date', 'PIC', 'Status',
        ]];

        $date = fn ($value) => $value ? Carbon::parse($value)->format('d-m-Y') : '-';

        foreach ($this->schedules as $schedule) {
            $rows[] = [
                $schedule->machine_number,
                $schedule->machine_type,
                $schedule->order_number ?: '-',
                $date($schedule->plan_date),
                $date($schedule->actual_date),
                $schedule->pic ? Str::title(strtolower($schedule->pic)) : '-',
                self::STATUS_LABELS[$schedule->status] ?? ($schedule->status ?: '-'),
            ];
        }

        return $rows;
    }
}
