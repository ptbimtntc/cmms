<?php

namespace App\Services\Vme\Ai;

use App\Models\AiForecastRecommendation;
use App\Models\AiForecastResult;
use App\Models\AiForecastRun;

/**
 * AI Sparepart Analyst: only READS the runs of the existing forecast engine. It never starts a
 * run (that stays on the AI Sparepart Analyst page).
 *
 *   RUNNING   -> WORKING
 *   FAILED    -> ALERT
 *   COMPLETED -> ALERT when the warnings reach config('vme.ai.thresholds.sparepart_warnings'), else IDLE
 */
class SparepartAnalystAnalysis implements AiCharacterAnalysis
{
    public function analyze(AiContext $context): AiAnalysisResult
    {
        $run = AiForecastRun::query()->latest('id')->first();

        if (! $run) {
            return new AiAnalysisResult(AiAnalysisResult::IDLE, 'No sparepart forecast run yet.', ['source_tables' => ['ai_forecast_runs']]);
        }

        $ref = ['type' => 'ai_forecast_runs', 'id' => $run->id];
        $input = ['source_tables' => ['ai_forecast_runs', 'ai_forecast_results', 'ai_forecast_recommendations'], 'run_id' => $run->id,
            'period' => $run->period_start?->toDateString().' - '.$run->period_end?->toDateString()];

        if ($run->status === AiForecastRun::RUNNING) {
            return new AiAnalysisResult(
                AiAnalysisResult::WORKING,
                "Forecast #{$run->id} sedang berjalan sejak ".($run->started_at?->format('H:i') ?? '-').'.',
                $input,
                [['label' => 'Run', 'value' => "#{$run->id}"], ['label' => 'Status', 'value' => 'Berjalan']],
                $ref,
                $run->started_at,
            );
        }

        if ($run->status === AiForecastRun::FAILED) {
            $error = trim((string) $run->error) !== '' ? mb_strimwidth(trim((string) $run->error), 0, 140, '…') : 'tanpa pesan';

            return new AiAnalysisResult(
                AiAnalysisResult::ALERT,
                "Forecast #{$run->id} gagal: {$error}",
                $input,
                [['label' => 'Run', 'value' => "#{$run->id}"], ['label' => 'Status', 'value' => 'Gagal'], ['label' => 'Pesan', 'value' => $error]],
                $ref,
                $run->started_at,
                $run->finished_at,
            );
        }

        $likely = AiForecastResult::query()->where('run_id', $run->id)->where('level', 'LIKELY')->count();
        $warnings = AiForecastRecommendation::query()->where('run_id', $run->id)->where('severity', 'WARNING')->count();
        $threshold = (int) config('vme.ai.thresholds.sparepart_warnings');
        $alert = $threshold > 0 && $warnings >= $threshold;
        $when = ($run->finished_at ?? $run->started_at)?->format('d-m-Y H:i') ?? '-';

        return new AiAnalysisResult(
            $alert ? AiAnalysisResult::ALERT : AiAnalysisResult::IDLE,
            "Forecast #{$run->id} selesai {$when}: {$likely} sparepart perlu disiapkan, {$warnings} peringatan.",
            $input + ['alert_warnings_at' => $threshold],
            [
                ['label' => 'Run', 'value' => "#{$run->id}"],
                ['label' => 'Spareparts to prepare', 'value' => (string) $likely],
                ['label' => 'Peringatan', 'value' => (string) $warnings],
                ['label' => 'Selesai', 'value' => $when],
            ],
            $ref,
            $run->started_at,
            $run->finished_at,
        );
    }
}
