<?php

namespace App\Services\Vme\Ai;

/**
 * AI Supervisor: an overview of the whole floor from the same PIC states the map shows.
 * Alert when activities cannot be attributed to a PIC (ambiguous names).
 */
class SupervisorAnalysis implements AiCharacterAnalysis
{
    public function analyze(AiContext $context): AiAnalysisResult
    {
        $total = count($context->pics);

        if (! $context->activitySync) {
            return new AiAnalysisResult(
                AiAnalysisResult::IDLE,
                "{$total} PIC aktif. Sinkronisasi aktivitas sedang OFF, jadi aktivitas PIC tidak dibaca.",
                ['pic_total' => $total, 'activity_sync' => false],
                [['label' => 'PIC aktif', 'value' => (string) $total]],
            );
        }

        $working = $context->countStatus('WORKING');
        $idle = $context->countStatus('IDLE');
        $inactive = $context->countStatus('INACTIVE');
        $threshold = (int) config('vme.ai.thresholds.supervisor_unmapped');
        $alert = $threshold > 0 && $context->unmapped >= $threshold;

        $summary = "{$working} dari {$total} PIC sedang bekerja, {$idle} menunggu, {$inactive} tidak aktif hari ini.";
        if ($context->unmapped > 0) {
            $summary .= " {$context->unmapped} aktivitas tidak bisa dipetakan ke PIC (nama ganda).";
        }

        return new AiAnalysisResult(
            $alert ? AiAnalysisResult::ALERT : AiAnalysisResult::IDLE,
            $summary,
            ['pic_total' => $total, 'activity_sync' => true, 'alert_unmapped_at' => $threshold],
            [
                ['label' => 'PIC aktif', 'value' => (string) $total],
                ['label' => 'Sedang bekerja', 'value' => (string) $working],
                ['label' => 'Menunggu', 'value' => (string) $idle],
                ['label' => 'Not active today', 'value' => (string) $inactive],
                ['label' => 'Activity not mapped', 'value' => (string) $context->unmapped],
            ],
        );
    }
}
