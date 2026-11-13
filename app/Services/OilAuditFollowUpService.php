<?php

namespace App\Services;

use App\Models\OilAudit;
use App\Models\OilAuditFollowUp;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * OIL_AUDIT_FOLLOW_UP_SAVE — extracted verbatim from
 * OilAuditController::storeFollowUp() / updateFollowUp() /
 * syncFollowUpProblems() / followUpRules() / validateFollowUp() so the
 * business logic AND its validation are reused by both the online
 * controller and the offline sync handler — never duplicated. Same atomic
 * boundary as before: header + problems + findings all commit together.
 */
class OilAuditFollowUpService
{
    /**
     * Validation rules — extracted verbatim from
     * OilAuditController::followUpRules().
     *
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'problems' => ['required', 'array', 'min:1'],
            'problems.*.problem' => ['required', Rule::in(OilAudit::PROBLEM_OPTIONS)],
            'problems.*.findings' => ['required', 'array', 'min:1'],
            'problems.*.findings.*.finding' => ['required', Rule::in(OilAudit::FINDING_OPTIONS)],
            'action_taken' => ['required', 'string', 'max:2000'],
        ];
    }

    /**
     * Runs rules() plus the same two uniqueness checks as the original
     * OilAuditController::validateFollowUp() — extracted verbatim, now
     * taking a plain array so it works for both a web Request's ->all()
     * and an offline sync operation's JSON payload. Throws
     * ValidationException on failure.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function validate(array $data, ?OilAudit $oilAudit = null): array
    {
        $isPantau = $oilAudit?->condition === 'PANTAU';

        // Only a PANTAU audit has a choice of action. Every other condition is
        // always a full check (problem, finding and action are required).
        $refillOil = $isPantau && filter_var($data['refill_oil'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $fullCheck = ! $isPantau || filter_var($data['full_check'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if ($isPantau && ! $refillOil && ! $fullCheck) {
            throw ValidationException::withMessages([
                'refill_oil' => 'Pick at least one action: refill the oil or run a full check.',
            ]);
        }

        if (! $fullCheck) {
            return ['refill_oil' => true, 'full_check' => false];
        }

        $validator = Validator::make($data, self::rules());

        $validator->after(function ($validator) use ($data) {
            $problemRows = (array) ($data['problems'] ?? []);

            $problems = collect($problemRows)
                ->map(fn ($problem) => trim((string) ($problem['problem'] ?? '')))
                ->filter();

            if ($problems->count() !== $problems->unique()->count()) {
                $validator->errors()->add('problems', 'Each problem may be selected only once.');
            }

            foreach ($problemRows as $i => $problem) {
                $problemText = trim((string) ($problem['problem'] ?? ''));
                $allowedFindings = OilAudit::findingOptionsFor($problemText);

                $findings = collect($problem['findings'] ?? [])
                    ->map(fn ($finding) => trim((string) ($finding['finding'] ?? '')))
                    ->filter();

                if ($findings->count() !== $findings->unique()->count()) {
                    $validator->errors()->add(
                        "problems.{$i}.findings",
                        'Findings within one problem must differ.'
                    );
                }

                foreach ((array) ($problem['findings'] ?? []) as $j => $finding) {
                    $value = trim((string) ($finding['finding'] ?? ''));

                    if ($value !== '' && ! in_array($value, $allowedFindings, true)) {
                        $validator->errors()->add(
                            "problems.{$i}.findings.{$j}.finding",
                            in_array($problemText, OilAudit::GENERIC_FINDING_PROBLEMS, true)
                                ? 'For the problem "'.$problemText.'", the finding can only be "'.OilAudit::GENERIC_FINDING.'".'
                                : 'That finding is not valid for the selected problem.'
                        );
                    }
                }
            }
        });

        return $validator->validate() + ['refill_oil' => $refillOil, 'full_check' => true];
    }

    /**
     * @param  array<string, mixed>  $validated  Shape: action_taken (string),
     *                                           problems (array of {problem, findings[]}),
     *                                           as produced by OilAuditController::validateFollowUp().
     */
    public function store(OilAudit $oilAudit, array $validated, User $user): OilAuditFollowUp
    {
        return DB::transaction(function () use ($oilAudit, $validated, $user) {
            $followUp = OilAuditFollowUp::create([
                'oil_audit_id' => $oilAudit->id,
                'refill_oil' => (bool) ($validated['refill_oil'] ?? false),
                'full_check' => (bool) ($validated['full_check'] ?? true),
                // Keep the legacy column populated for backward compatibility.
                'problem' => $validated['problems'][0]['problem'] ?? null,
                'action_taken' => $validated['action_taken'] ?? null,
                'pic_user_id' => $user->id,
                'pic_name' => $user->name,
                'actioned_at' => now(),
            ]);

            $this->syncFollowUpProblems($followUp, $validated['problems'] ?? []);

            // Repeating low-priority findings (Oli Keruh / Level Glass
            // Burem / Pantau): the same follow-up closes the older open ones too,
            // inside the same transaction. Only on first save — later edits
            // do not rewrite the older copies.
            foreach ($oilAudit->olderOpenRepeats()->get() as $older) {
                $copy = OilAuditFollowUp::create([
                    'oil_audit_id' => $older->id,
                    'refill_oil' => $followUp->refill_oil,
                    'full_check' => $followUp->full_check,
                    'problem' => $followUp->problem,
                    'action_taken' => $followUp->action_taken,
                    'pic_user_id' => $followUp->pic_user_id,
                    'pic_name' => $followUp->pic_name,
                    'actioned_at' => $followUp->actioned_at,
                ]);

                $this->syncFollowUpProblems($copy, $validated['problems'] ?? []);
            }

            return $followUp;
        });
    }

    /**
     * @param  array<string, mixed>  $validated  Shape: action_taken (string),
     *                                           problems (array of {problem, findings[]}),
     *                                           as produced by OilAuditController::validateFollowUp().
     */
    public function update(OilAuditFollowUp $followUp, array $validated): void
    {
        DB::transaction(function () use ($followUp, $validated) {
            // pic_* / actioned_at are intentionally left untouched: they record
            // who first actioned the finding and when, not who last edited it.
            $followUp->update([
                'refill_oil' => (bool) ($validated['refill_oil'] ?? false),
                'full_check' => (bool) ($validated['full_check'] ?? true),
                'problem' => $validated['problems'][0]['problem'] ?? null,
                'action_taken' => $validated['action_taken'] ?? null,
            ]);

            $this->syncFollowUpProblems($followUp, $validated['problems'] ?? []);
        });
    }

    /**
     * Delete-and-recreate the nested problem/finding tree, mirroring
     * PMScheduleSaveService. Deleting a problem row cascades its findings
     * via the FK, so a full replace stays consistent. Blank rows are
     * skipped defensively even though validation already rejects them.
     *
     * @param  array<int, array<string, mixed>>  $problems
     */
    private function syncFollowUpProblems(OilAuditFollowUp $followUp, array $problems): void
    {
        $followUp->problems()->delete();

        foreach ($problems as $problem) {
            $problemText = trim((string) ($problem['problem'] ?? ''));

            if ($problemText === '') {
                continue;
            }

            $findings = collect($problem['findings'] ?? [])
                ->map(fn ($finding) => trim((string) ($finding['finding'] ?? '')))
                ->filter()
                ->values();

            if ($findings->isEmpty()) {
                continue;
            }

            $created = $followUp->problems()->create(['problem' => $problemText]);
            $created->findings()->createMany(
                $findings->map(fn (string $finding) => ['finding' => $finding])->all()
            );
        }
    }
}
