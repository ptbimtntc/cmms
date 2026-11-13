<?php

namespace App\Services\Predictive;

use App\Exceptions\PredictiveWorkflowException;
use App\Models\Machine;
use App\Models\PredictiveAction;
use App\Models\PredictiveProblemOption;
use App\Models\PredictiveReopen;
use App\Models\PredictiveWorkOrder;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PredictiveWorkOrderService
{
    /**
     * Area and machine type always come from the Machine master, never from
     * the caller.
     *
     * @param  array{order_number: string, wo_date: string, problem_description: string, due_date: string}  $data
     */
    public function create(string $category, Machine $machine, array $data, ?User $creator): PredictiveWorkOrder
    {
        return PredictiveWorkOrder::create([
            'category' => $category,
            'order_number' => $data['order_number'],
            'machine_id' => $machine->id,
            'machine_number' => $machine->machine_number,
            'area' => $machine->area,
            'machine_type' => $machine->machine_type,
            'wo_date' => $data['wo_date'],
            'problem_description' => $data['problem_description'],
            'due_date' => $data['due_date'],
            'status' => PredictiveWorkOrder::STATUS_OPEN,
            'created_by' => $creator?->id,
        ]);
    }

    /**
     * The claim is a single conditional UPDATE: of two concurrent requests
     * only one can match `pic_user_id IS NULL`, so the loser affects 0 rows.
     */
    public function claim(PredictiveWorkOrder $workOrder, User $pic): void
    {
        DB::transaction(function () use ($workOrder, $pic) {
            $affected = PredictiveWorkOrder::query()
                ->whereKey($workOrder->id)
                ->where('status', PredictiveWorkOrder::STATUS_OPEN)
                ->whereNull('pic_user_id')
                ->update([
                    'pic_user_id' => $pic->id,
                    'claimed_at' => now(),
                    'updated_at' => now(),
                ]);

            if ($affected !== 1) {
                throw new PredictiveWorkflowException(
                    'Another PIC already claimed this WO, or it is no longer OPEN.'
                );
            }
        });

        $workOrder->refresh();
    }

    /**
     * Validation for the Action form. Problems and Findings are not typed: the
     * form sends ids from the master, and every id is checked here against the
     * master of the WO's own category and area (active entries only), so a
     * forged request cannot slip in another category's or area's wording.
     * At least one Problem, each with at least one Finding; a Problem once per
     * Action and a Finding once per Problem.
     *
     * Returns the choices resolved to ids plus the current names, which are
     * saved with the Action as a snapshot.
     *
     * @param  array<string, mixed>  $input
     * @return array{problems: list<array{problem_id: int, problem: string, findings: list<array{finding_id: int, finding: string}>}>, remarks: ?string}
     */
    public function validateAction(PredictiveWorkOrder $workOrder, array $input): array
    {
        $options = PredictiveProblemOption::query()
            ->active()
            ->forScope($workOrder->category, $workOrder->area)
            ->with(['findings' => fn ($q) => $q->where('is_active', true)])
            ->get()
            ->filter(fn ($p) => $p->findings->isNotEmpty())
            ->keyBy('id');

        $validator = Validator::make($input, [
            'problems' => ['required', 'array', 'min:1', 'max:20'],
            'problems.*.problem_id' => ['required', 'integer'],
            'problems.*.findings' => ['required', 'array', 'min:1', 'max:20'],
            'problems.*.findings.*.finding_id' => ['required', 'integer'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ], [
            'problems.required' => 'Record at least one Problem.',
            'problems.*.problem_id.required' => 'Every Problem must be selected.',
            'problems.*.findings.required' => 'Every Problem needs at least one Finding.',
            'problems.*.findings.*.finding_id.required' => 'Every Finding must be selected.',
        ]);

        $validator->after(function ($validator) use ($input, $options, $workOrder) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if ($options->isEmpty()) {
                $validator->errors()->add('problems', 'Master Problem '.$workOrder->categoryLabel().' area '.$workOrder->area.' is empty. Ask an Admin or Koordinator to fill it in.');

                return;
            }

            $problems = (array) $input['problems'];

            $ids = collect($problems)->pluck('problem_id')->map(fn ($id) => (int) $id);
            if ($ids->count() !== $ids->unique()->count()) {
                $validator->errors()->add('problems', 'Each Problem may be selected only once.');
            }

            foreach ($problems as $i => $problem) {
                $option = $options->get((int) $problem['problem_id']);

                if (! $option) {
                    $validator->errors()->add("problems.{$i}.problem_id", 'That Problem is not available for this WO category and area.');

                    continue;
                }

                $findingIds = collect($problem['findings'])->pluck('finding_id')->map(fn ($id) => (int) $id);
                if ($findingIds->count() !== $findingIds->unique()->count()) {
                    $validator->errors()->add("problems.{$i}.findings", 'Findings within one Problem must differ.');
                }

                foreach ($findingIds as $j => $findingId) {
                    if (! $option->findings->contains('id', $findingId)) {
                        $validator->errors()->add("problems.{$i}.findings.{$j}.finding_id", 'That Finding does not belong to the selected Problem.');
                    }
                }
            }
        });

        $validated = $validator->validate();

        return [
            'problems' => collect($validated['problems'])->map(function ($problem) use ($options) {
                $option = $options->get((int) $problem['problem_id']);

                return [
                    'problem_id' => $option->id,
                    'problem' => $option->name,
                    'findings' => collect($problem['findings'])->map(function ($finding) use ($option) {
                        $found = $option->findings->firstWhere('id', (int) $finding['finding_id']);

                        return ['finding_id' => $found->id, 'finding' => $found->name];
                    })->all(),
                ];
            })->all(),
            'remarks' => $validated['remarks'] ?? null,
        ];
    }

    /**
     * Saves Action #n, closes the WO and releases the claim in one
     * transaction. The WO row is locked first so the next action number is
     * computed from a stable view; the unique (work_order_id, action_number)
     * index is the backstop.
     *
     * @param  array{problems: list<array{problem_id?: int, problem: string, findings: list<array{finding_id?: int, finding: string}>}>, remarks?: ?string}  $data
     */
    public function close(PredictiveWorkOrder $workOrder, User $pic, array $data): PredictiveAction
    {
        $action = DB::transaction(function () use ($workOrder, $pic, $data) {
            $locked = PredictiveWorkOrder::query()->whereKey($workOrder->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isOpen()) {
                throw new PredictiveWorkflowException('This WO is already CLOSED.');
            }

            if (! $locked->isClaimedBy($pic)) {
                throw new PredictiveWorkflowException('Only the PIC who claimed this WO can save an Action.');
            }

            $next = ((int) PredictiveAction::where('work_order_id', $locked->id)->max('action_number')) + 1;

            $action = PredictiveAction::create([
                'work_order_id' => $locked->id,
                'action_number' => $next,
                'remarks' => isset($data['remarks']) && trim($data['remarks']) !== '' ? trim($data['remarks']) : null,
                'pic_user_id' => $pic->id,
                'pic_name' => $pic->name,
                'actioned_at' => now(),
            ]);

            foreach ($data['problems'] as $problem) {
                $row = $action->problems()->create([
                    'problem_option_id' => $problem['problem_id'] ?? null,
                    'problem' => trim((string) $problem['problem']),
                ]);
                $row->findings()->createMany(
                    collect($problem['findings'])->map(fn ($f) => [
                        'finding_option_id' => $f['finding_id'] ?? null,
                        'finding' => trim((string) $f['finding']),
                    ])->all()
                );
            }

            $locked->update([
                'status' => PredictiveWorkOrder::STATUS_CLOSED,
                'pic_user_id' => null,
                'claimed_at' => null,
                'closed_at' => $action->actioned_at,
            ]);

            return $action;
        });

        $workOrder->refresh();

        return $action;
    }

    /** Same WO, same order number: reopening never creates a new WO. */
    public function reopen(PredictiveWorkOrder $workOrder, User $koordinator): PredictiveReopen
    {
        $reopen = DB::transaction(function () use ($workOrder, $koordinator) {
            $locked = PredictiveWorkOrder::query()->whereKey($workOrder->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isClosed()) {
                throw new PredictiveWorkflowException('Only a CLOSED WO can be reopened.');
            }

            $reopen = PredictiveReopen::create([
                'work_order_id' => $locked->id,
                'user_id' => $koordinator->id,
                'user_name' => $koordinator->name,
                'reopened_at' => now(),
            ]);

            $locked->update([
                'status' => PredictiveWorkOrder::STATUS_OPEN,
                'pic_user_id' => null,
                'claimed_at' => null,
                'closed_at' => null,
            ]);

            return $reopen;
        });

        $workOrder->refresh();

        return $reopen;
    }
}
