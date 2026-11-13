<?php

namespace App\Services\Vme\Ai;

use App\Models\PMSchedule;

/**
 * Koordinator (one per Area): watches the PM plan of its Area and how work is being handed out.
 * Alert when the Area's PMs past their due date reach config('vme.ai.thresholds.coordinator_overdue_pm').
 */
class CoordinatorAnalysis implements AiCharacterAnalysis
{
    public function analyze(AiContext $context): AiAnalysisResult
    {
        // pm_schedules.area holds the Area's name; a context without an Area covers every area.
        $pm = fn () => PMSchedule::query()->when($context->areaName, fn ($q, $name) => $q->where('area', $name));

        $overdue = $pm()->whereIn('status', ['OPEN', 'IN_PROGRESS'])->whereDate('due_date', '<', today())->count();
        $running = $pm()->where('status', 'IN_PROGRESS')->count();
        $missed = $pm()->where('status', 'MISSED')
            ->whereMonth('due_date', now()->month)->whereYear('due_date', now()->year)->count();
        $soon = $pm()->where('status', 'OPEN')
            ->whereBetween('plan_date', [today()->toDateString(), today()->addDays(7)->toDateString()])->count();

        $waiting = $context->activitySync ? $context->countStatus('IDLE') : null;
        $threshold = (int) config('vme.ai.thresholds.coordinator_overdue_pm');
        $alert = $threshold > 0 && $overdue >= $threshold;

        $summary = "{$overdue} PM lewat jatuh tempo, {$running} sedang berjalan, {$soon} terjadwal 7 hari ke depan.";
        $summary .= $waiting === null ? '' : " {$waiting} PIC menunggu tugas.";

        $details = [
            ['label' => 'PM lewat jatuh tempo', 'value' => (string) $overdue],
            ['label' => 'PM sedang berjalan', 'value' => (string) $running],
            ['label' => 'PM terlewat bulan ini', 'value' => (string) $missed],
            ['label' => 'PM terjadwal 7 hari ke depan', 'value' => (string) $soon],
        ];
        if ($waiting !== null) {
            $details[] = ['label' => 'PICs waiting for work', 'value' => (string) $waiting];
        }

        return new AiAnalysisResult(
            $alert ? AiAnalysisResult::ALERT : AiAnalysisResult::IDLE,
            $summary,
            ['source_tables' => ['pm_schedules'], 'area' => $context->areaName, 'alert_overdue_at' => $threshold],
            $details,
        );
    }
}
