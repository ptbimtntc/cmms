<?php

namespace App\Services;

use App\Models\PMMeasurement;
use App\Models\PMProblem;
use App\Models\PMSchedule;
use App\Models\PMSparepart;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * PM_SAVE — the single business-logic path for saving a PM Schedule's
 * execution data (header, work sessions, measurements, problems,
 * spareparts). Extracted verbatim from PMScheduleController::update() so it
 * can be called by both the existing online HTTP request and, later, an
 * offline sync replay — without duplicating the business rule in two
 * places.
 *
 * Does NOT include the PM checklist (see PMChecklistSaveService) — the
 * existing Fill PM UX saves the checklist as a separate step/request, and
 * this extraction intentionally preserves that behavior rather than
 * changing it.
 *
 * Callers remain responsible for authorization and request validation;
 * this service only encapsulates the atomic persistence step, matching
 * PMScheduleController::update()'s existing DB::transaction boundary.
 */
class PMScheduleSaveService
{
    /**
     * Validation rules — extracted verbatim from
     * PMScheduleController::update() so the same rule set is reused by both
     * the online request and the offline sync handler (never duplicated).
     *
     * @return array<string, mixed>
     */
    public static function rules(bool $hasSessions): array
    {
        $rules = [
            'order_number' => 'required',
            'pic' => 'required',
            'greasing' => 'nullable',
            'oil_change' => 'nullable',
            'wo_zsbp' => 'nullable',
            'remarks' => 'nullable',
            'problems.*.problem' => 'nullable',
            // A problem row is only complete with its finding and severity; rows without a
            // problem are still skipped on save, so blank rows stay harmless.
            'problems.*.finding' => 'nullable|required_with:problems.*.problem',
            'problems.*.severity' => 'nullable|required_with:problems.*.problem|in:Low,Medium,High',
            'measurements.*.measurement_item' => 'nullable',
            'measurements.*.measurement_value' => 'nullable',
            'spareparts.*.sparepart_id' => 'nullable',
            'spareparts.*.qty' => 'nullable|integer|min:1',
            'no_problem' => 'nullable|boolean',
            'no_sparepart' => 'nullable|boolean',
        ];

        if ($hasSessions) {
            $rules['sessions'] = 'array';
            $rules['sessions.*.actual_date'] = 'required|date';
            $rules['sessions.*.start_time'] = 'required';
            $rules['sessions.*.end_time'] = 'nullable';
        } else {
            $rules['actual_date'] = 'required|date';
            $rules['start_time'] = 'required';
            $rules['end_time'] = 'nullable';
        }

        return $rules;
    }

    /**
     * Messages for rules(), shared by the online request and the offline sync handler.
     *
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'problems.*.finding.required_with' => 'Choose a finding for this problem.',
            'problems.*.severity.required_with' => 'Choose a severity for this problem.',
            'problems.*.severity.in' => 'Severity must be Low, Medium, or High.',
            'sessions.*.actual_date.required' => 'Enter the date of this working day.',
            'sessions.*.actual_date.date' => 'Enter a valid date for this working day.',
            'sessions.*.start_time.required' => 'Enter the start time of this working day.',
        ];
    }

    /**
     * @param  array<string, mixed>  $data  Same shape as the validated
     *                                      request in PMScheduleController::update():
     *                                      order_number, pic, oil_change, greasing,
     *                                      wo_zsbp, remarks, sessions[] (multi-day) OR
     *                                      actual_date/start_time/end_time (legacy
     *                                      single-day), measurements[], problems[],
     *                                      spareparts[].
     */
    public function save(PMSchedule $pmSchedule, array $data): void
    {
        [$noProblem, $noSparepart] = $this->resolveNoneFlags($pmSchedule, $data);

        DB::transaction(function () use ($data, $pmSchedule, $noProblem, $noSparepart) {

            // Update header (do NOT change actual_date/start_time/end_time/duration for multi-day sessions)
            $updateHeader = [
                'order_number' => $data['order_number'] ?? null,
                'pic' => $data['pic'] ?? null,
                'oil_change' => $pmSchedule->requiresOilChange() ? ($data['oil_change'] ?? null) : null,
                'greasing' => $data['greasing'] ?? null,
                'wo_zsbp' => $data['wo_zsbp'] ?? null,
                'remarks' => $data['remarks'] ?? null,
                'no_problem' => $noProblem,
                'no_sparepart' => $noSparepart,
                'status' => 'IN_PROGRESS',
            ];

            // Multi-day sessions handling
            if (array_key_exists('sessions', $data)) {

                $sessionIds = [];

                // existing sessions for selective delete
                $existingIds = $pmSchedule->workSessions()->pluck('id')->toArray();

                foreach ($data['sessions'] as $s) {

                    $start = Carbon::createFromFormat('H:i', $s['start_time']);
                    $end = $s['end_time'] ? Carbon::createFromFormat('H:i', $s['end_time']) : null;

                    if ($end) {
                        if ($end->lessThan($start)) {
                            $end->addDay();
                        }
                        $duration = $start->diffInMinutes($end);
                    } else {
                        $duration = null;
                    }

                    // If id provided and belongs to this schedule, update; otherwise create
                    if (! empty($s['id'])) {
                        $ws = $pmSchedule->workSessions()->where('id', $s['id'])->first();
                        if ($ws) {
                            $ws->update([
                                'actual_date' => $s['actual_date'],
                                'start_time' => $s['start_time'],
                                'end_time' => $s['end_time'] ?? null,
                                'duration' => $duration,
                            ]);

                            $sessionIds[] = $ws->id;

                            continue;
                        }
                    }

                    $new = $pmSchedule->workSessions()->create([
                        'actual_date' => $s['actual_date'],
                        'start_time' => $s['start_time'],
                        'end_time' => $s['end_time'] ?? null,
                        'duration' => $duration,
                    ]);

                    $sessionIds[] = $new->id;
                }

                // delete removed sessions (selective)
                $toDelete = array_diff($existingIds, $sessionIds);
                if (! empty($toDelete)) {
                    $pmSchedule->workSessions()->whereIn('id', $toDelete)->delete();
                }

                // Update header without touching legacy execution columns
                $pmSchedule->update($updateHeader);

            } else {
                // Legacy single-day behavior (keep existing semantics)
                $duration = null;
                if (! empty($data['start_time']) && ! empty($data['end_time'])) {
                    $start = Carbon::createFromFormat('H:i', $data['start_time']);
                    $end = Carbon::createFromFormat('H:i', $data['end_time']);
                    if ($end->lessThan($start)) {
                        $end->addDay();
                    }
                    $duration = $start->diffInMinutes($end);
                }

                $pmSchedule->update(array_merge($updateHeader, [
                    'actual_date' => $data['actual_date'] ?? null,
                    'start_time' => $data['start_time'] ?? null,
                    'end_time' => $data['end_time'] ?? null,
                    'duration' => $duration,
                ]));
            }

            // 3. update measurements (unchanged)
            PMMeasurement::where(
                'pm_schedule_id',
                $pmSchedule->id
            )->delete();
            if (! empty($data['measurements'])) {

                foreach ($data['measurements'] as $measurement) {

                    PMMeasurement::create([

                        'pm_schedule_id' => $pmSchedule->id,

                        'machine_measurement_id' => $measurement['machine_measurement_id'],

                        'measurement_item' => $measurement['measurement_item'],

                        'standard' => $measurement['standard'],

                        'measurement_value' => $measurement['measurement_value'],

                        'unit' => $measurement['unit'],

                    ]);

                }

            }

            PMProblem::where(
                'pm_schedule_id',
                $pmSchedule->id
            )->delete();

            if (! empty($data['problems'])) {

                foreach ($data['problems'] as $problem) {

                    if (empty($problem['problem'])) {
                        continue;
                    }

                    PMProblem::create([

                        'pm_schedule_id' => $pmSchedule->id,

                        'machine_problem_id' => $problem['problem'],

                        'machine_problem_finding_id' => $problem['finding'],

                        'severity' => $problem['severity'],

                    ]);

                }

            }

            $gearboxProblem = 'NO';

            if ($pmSchedule->isGearboxApplicable()) {
                $hasGearboxProblem = PMProblem::with('machineProblem')
                    ->where('pm_schedule_id', $pmSchedule->id)
                    ->get()
                    ->contains(fn ($p) => PMSchedule::matchesGearboxKeyword($p->machineProblem->problem ?? null));

                $gearboxProblem = $hasGearboxProblem ? 'YES' : 'NO';
            }

            $pmSchedule->update(['gearbox_problem' => $gearboxProblem]);

            // Usage injected from a warehouse issue (Sparepart Injection) is
            // authoritative: never deleted, edited or duplicated by a
            // (possibly stale/offline) form save. Only the PIC's own rows
            // are replaced; a submitted row for an injected sparepart is
            // ignored because the injected row already represents it.
            $injectedSparepartIds = PMSparepart::where('pm_schedule_id', $pmSchedule->id)
                ->where('source', PMSparepart::SOURCE_WAREHOUSE)
                ->pluck('sparepart_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            PMSparepart::where('pm_schedule_id', $pmSchedule->id)
                ->where(fn ($q) => $q->whereNull('source')->orWhere('source', '!=', PMSparepart::SOURCE_WAREHOUSE))
                ->delete();

            if (! empty($data['spareparts'])) {

                foreach ($data['spareparts'] as $item) {

                    if (empty($item['sparepart_id'])) {
                        continue;
                    }

                    if (in_array((int) $item['sparepart_id'], $injectedSparepartIds, true)) {
                        continue;
                    }

                    PMSparepart::create([

                        'pm_schedule_id' => $pmSchedule->id,

                        'sparepart_id' => $item['sparepart_id'],

                        'qty' => $item['qty'] ?? 1,

                        'unit' => $item['unit'] ?? null,

                    ]);

                }

            }

        });
    }

    /**
     * "No problem found" / "No sparepart used" are explicit confirmations,
     * so they may not be sent together with filled rows. Warehouse-injected
     * usage is real usage, so it always overrides "no sparepart".
     *
     * @param  array<string, mixed>  $data
     * @return array{0: bool, 1: bool}
     *
     * @throws ValidationException
     */
    private function resolveNoneFlags(PMSchedule $pmSchedule, array $data): array
    {
        $noProblem = filter_var($data['no_problem'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $noSparepart = filter_var($data['no_sparepart'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $filledProblems = collect($data['problems'] ?? [])
            ->filter(fn ($row) => ! empty($row['problem']));

        $filledSpareparts = collect($data['spareparts'] ?? [])
            ->filter(fn ($row) => ! empty($row['sparepart_id']));

        $errors = [];

        if ($noProblem && $filledProblems->isNotEmpty()) {
            $errors['no_problem'] = 'Remove the problem rows or untick "No problem found".';
        }

        if ($noSparepart && $filledSpareparts->isNotEmpty()) {
            $errors['no_sparepart'] = 'Remove the sparepart rows or untick "No sparepart used".';
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        if ($noSparepart && $pmSchedule->spareparts()->where('source', PMSparepart::SOURCE_WAREHOUSE)->exists()) {
            $noSparepart = false;
        }

        return [$noProblem, $noSparepart];
    }
}
