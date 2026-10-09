<?php

namespace App\Services;

use App\Exceptions\DuplicateOilAuditException;
use App\Models\Machine;
use App\Models\OilAudit;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
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

    /**
     * One audit per machine per business date (app timezone, Asia/Jakarta),
     * for every user and device. The exists() pre-check only gives a fast,
     * clear rejection; the unique (machine_id, audit_date) index is what
     * actually guarantees it under concurrency. The index violation is
     * translated here so it never reaches callers as a raw database error
     * (the sync endpoint would otherwise mistake it for a duplicate
     * operation_uuid).
     *
     * @throws DuplicateOilAuditException
     */
    public function create(Machine $machine, User $user, string $condition): OilAudit
    {
        $auditedAt = now();
        $auditDate = $auditedAt->toDateString();

        if (OilAudit::where('machine_id', $machine->id)->where('audit_date', $auditDate)->exists()) {
            throw DuplicateOilAuditException::forMachine($machine->machine_number);
        }

        try {
            return DB::transaction(function () use ($machine, $user, $condition, $auditedAt, $auditDate) {
                return OilAudit::create([
                    'machine_id' => $machine->id,
                    'machine_number' => $machine->machine_number,
                    'machine_type' => $machine->machine_type,
                    'area' => $machine->area,
                    'condition' => $condition,
                    'audited_by_user_id' => $user->id,
                    'audited_by_name' => $user->name,
                    'audited_at' => $auditedAt,
                    'audit_date' => $auditDate,
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            throw DuplicateOilAuditException::forMachine($machine->machine_number);
        }
    }
}
