<?php

namespace App\Services\Vme\Ai;

use App\Models\AiCharacterRun;
use App\Models\Area;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Runs the enabled AI characters' analyses and keeps their outputs. The ONLY thing written here
 * is ai_character_runs; analyses themselves only read.
 */
class AiCharacterService
{
    /** Re-stamp the latest row at most this often when the output has not changed. */
    private const CHECK_STAMP_SECONDS = 60;

    public function __construct(private readonly AiCharacterRegistry $registry) {}

    /**
     * Status entries for the VME snapshot (uniform contract:
     * character_key, status, summary, updated_at, source) plus what the map needs to draw them.
     *
     * @return list<array<string, mixed>>
     */
    public function statuses(AiContext $context, ?int $onlyAreaId = null): array
    {
        $out = [];

        foreach ($this->registry->enabled($onlyAreaId) as $key => $def) {
            try {
                $result = $this->registry->analysisFor($def)->analyze($this->contextFor($def, $context));
                $run = $this->record($key, $result);
                $status = $result->status;
                $summary = $result->summary;
                $updated = $run->checked_at ?? $run->created_at;
            } catch (Throwable $e) {
                // An analysis that cannot run must not break the map: show it as alerting.
                Log::warning('VME AI character analysis failed', ['character' => $key, 'error' => $e->getMessage()]);
                $status = AiAnalysisResult::ALERT;
                $summary = 'The analysis could not be run.';
                $updated = now();
            }

            $out[] = [
                'character_key' => $key,
                'name' => $def['name'],
                'role' => $def['role'],
                'desk' => $def['desk'],
                'area_id' => $def['area_id'],
                'visit_zones' => $def['visit_zones'],
                'avatar_key' => $def['avatar'] ?? array_key_first((array) config('vme.avatars')),
                'status' => $status,
                'summary' => $summary,
                'updated_at' => Carbon::parse($updated)->toIso8601String(),
                'source' => 'internal',
            ];
        }

        return $out;
    }

    /** A per-Area character looks at its own Area only. */
    private function contextFor(array $def, AiContext $context): AiContext
    {
        if ($def['area_id'] === null) {
            return $context;
        }

        return $context->forArea($def['area_id'], Area::query()->whereKey($def['area_id'])->value('name'));
    }

    /** Store the output if it differs from the last stored one; otherwise just re-stamp it (rarely). */
    public function record(string $key, AiAnalysisResult $result): AiCharacterRun
    {
        $last = AiCharacterRun::query()->where('character_key', $key)->latest('id')->first();

        if ($last && $last->result_hash === $result->hash()) {
            if (! $last->checked_at || $last->checked_at->lt(now()->subSeconds(self::CHECK_STAMP_SECONDS))) {
                $last->forceFill(['checked_at' => now()])->save();
            }

            return $last;
        }

        return AiCharacterRun::create([
            'character_key' => $key,
            'status' => $result->status,
            'summary' => $result->summary,
            'input' => $result->input,
            'result' => $result->details,
            'result_hash' => $result->hash(),
            'source' => 'internal',
            'source_ref_type' => $result->sourceRef['type'] ?? null,
            'source_ref_id' => $result->sourceRef['id'] ?? null,
            'started_at' => $result->startedAt,
            'finished_at' => $result->finishedAt,
            'checked_at' => now(),
        ]);
    }

    /**
     * The panel data of one character: its latest stored output (computed first if none exists yet).
     * `$context` is a closure so the (expensive) snapshot context is only built when nothing is stored.
     *
     * @return array<string, mixed>|null null when the character is unknown or switched off
     */
    public function detail(string $key, \Closure $context): ?array
    {
        $def = $this->registry->find($key);

        if (! $def) {
            return null;
        }

        $run = AiCharacterRun::query()->where('character_key', $key)->latest('id')->first();

        if (! $run) {
            try {
                $run = $this->record($key, $this->registry->analysisFor($def)->analyze($this->contextFor($def, $context())));
            } catch (Throwable $e) {
                Log::warning('VME AI character analysis failed', ['character' => $key, 'error' => $e->getMessage()]);
            }
        }

        return [
            'type' => 'ai',
            'character' => ['key' => $key, 'name' => $def['name'], 'role' => $def['role'], 'source' => 'internal'],
            'status' => $run?->status ?? AiAnalysisResult::IDLE,
            'summary' => $run?->summary ?? 'Belum ada hasil analisis.',
            'details' => $run?->result ?? [],
            'updated_at' => ($run?->checked_at ?? $run?->created_at)?->toIso8601String(),
            'analyzed_since' => $run?->created_at?->toIso8601String(),
            'source_ref' => $run && $run->source_ref_type ? ['type' => $run->source_ref_type, 'id' => $run->source_ref_id] : null,
        ];
    }
}
