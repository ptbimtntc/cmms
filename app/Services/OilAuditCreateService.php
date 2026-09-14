<?php

namespace App\Services;

use App\Models\Machine;
use App\Models\OilAudit;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * OIL_AUDIT_CREATE — extracted verbatim from OilAuditController::store()
 * so the business logic can later be reused by an offline sync replay
 * without duplicating the rule. Wrapped in DB::transaction() so future
 * offline-sync bookkeeping (e.g. writing an idempotency record) can share
 * the same atomic boundary; behavior is otherwise identical to the
 * original single insert.
 */
class OilAuditCreateService
{
    /**
     * Validation rules — extracted verbatim from
     * OilAuditController::store() so the online request and the offline
     * sync handler never drift.
     *
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'machine_id' => ['required', 'exists:machines,id'],
            'condition' => [
                'required',
                'in:'.implode(',', array_keys(OilAudit::CONDITION_LABELS)),
            ],
        ];
    }

    public function create(Machine $machine, User $user, string $condition): OilAudit
    {
        return DB::transaction(function () use ($machine, $user, $condition) {
            return OilAudit::create([
                'machine_id' => $machine->id,
                'machine_number' => $machine->machine_number,
                'machine_type' => $machine->machine_type,
                'area' => $machine->area,
                'condition' => $condition,
                'audited_by_user_id' => $user->id,
                'audited_by_name' => $user->name,
                'audited_at' => now(),
            ]);
        });
    }
}
