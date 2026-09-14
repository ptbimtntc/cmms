<?php

namespace App\Http\Controllers;

use App\Exceptions\Sync\SyncConflictException;
use App\Models\Machine;
use App\Models\OilAudit;
use App\Models\OilAuditFollowUp;
use App\Models\PMSchedule;
use App\Models\SyncOperation;
use App\Models\User;
use App\Services\OilAuditCreateService;
use App\Services\OilAuditFollowUpService;
use App\Services\PMChecklistSaveService;
use App\Services\PMScheduleSaveService;
use App\Services\PMStartService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * FreeDOMS offline-first — server sync endpoint (Phase 1, Task 2).
 *
 * Accepts one offline operation per request and replays it through the
 * EXACT SAME business services the online controllers use (built in
 * Task 1) — PMStartService, PMScheduleSaveService, PMChecklistSaveService,
 * OilAuditCreateService, OilAuditFollowUpService. No business rule is
 * re-implemented here; this controller only adds what an offline replay
 * additionally needs: idempotency (sync_operations.operation_uuid),
 * atomicity (one DB transaction per operation, covering both the business
 * write and the ledger row), and conflict detection against the device's
 * `expected_state`.
 *
 * This endpoint is purely additive: it does not replace or redirect any
 * existing online route (pm-schedules.*, oil-audits.*), which keep working
 * exactly as before.
 */
class SyncOperationController extends Controller
{
    public const TYPE_PM_START = 'PM_START';

    public const TYPE_PM_SAVE = 'PM_SAVE';

    public const TYPE_PM_CHECKLIST_SAVE = 'PM_CHECKLIST_SAVE';

    public const TYPE_OIL_AUDIT_CREATE = 'OIL_AUDIT_CREATE';

    public const TYPE_OIL_AUDIT_FOLLOW_UP_SAVE = 'OIL_AUDIT_FOLLOW_UP_SAVE';

    private const TRANSACTION_TYPES = [
        self::TYPE_PM_START,
        self::TYPE_PM_SAVE,
        self::TYPE_PM_CHECKLIST_SAVE,
        self::TYPE_OIL_AUDIT_CREATE,
        self::TYPE_OIL_AUDIT_FOLLOW_UP_SAVE,
    ];

    /**
     * Mirrors the role scope each transaction type's ONLINE route already
     * enforces in routes/web.php — PM routes under
     * role:ADMIN,KOORDINATOR WWD,KOORDINATOR BUL,PIC WWD,PIC BUL; Oil Audit
     * routes under role:ADMIN,KOORDINATOR WWD,PIC WWD. Kept as an explicit
     * map here (rather than route middleware) because a single sync
     * endpoint carries every transaction type, each with its own scope.
     *
     * @var array<string, array<int, string>>
     */
    private const ALLOWED_ROLES = [
        self::TYPE_PM_START => [User::ROLE_ADMIN, User::ROLE_KOORDINATOR_WWD, User::ROLE_KOORDINATOR_BUL, User::ROLE_PIC_WWD, User::ROLE_PIC_BUL],
        self::TYPE_PM_SAVE => [User::ROLE_ADMIN, User::ROLE_KOORDINATOR_WWD, User::ROLE_KOORDINATOR_BUL, User::ROLE_PIC_WWD, User::ROLE_PIC_BUL],
        self::TYPE_PM_CHECKLIST_SAVE => [User::ROLE_ADMIN, User::ROLE_KOORDINATOR_WWD, User::ROLE_KOORDINATOR_BUL, User::ROLE_PIC_WWD, User::ROLE_PIC_BUL],
        self::TYPE_OIL_AUDIT_CREATE => [User::ROLE_ADMIN, User::ROLE_KOORDINATOR_WWD, User::ROLE_PIC_WWD],
        self::TYPE_OIL_AUDIT_FOLLOW_UP_SAVE => [User::ROLE_ADMIN, User::ROLE_KOORDINATOR_WWD, User::ROLE_PIC_WWD],
    ];

    public function handle(Request $request): JsonResponse
    {
        try {
            $envelope = $request->validate([
                'operation_uuid' => ['required', 'uuid'],
                'transaction_type' => ['required', 'string', Rule::in(self::TRANSACTION_TYPES)],
                'payload' => ['required', 'array'],
                'expected_state' => ['nullable', 'array'],
            ]);
        } catch (ValidationException $e) {
            return $this->respond('validation_failed', 422, ['errors' => $e->errors()]);
        }

        $uuid = $envelope['operation_uuid'];
        $type = $envelope['transaction_type'];
        $payload = $envelope['payload'];
        $expectedState = $envelope['expected_state'] ?? [];

        // The user is never trusted from the payload — only the
        // session-authenticated user (see the 'auth' middleware on this
        // route) is ever used as created_by_user_id / the acting PIC.
        $user = $request->user();

        if (! in_array($user->role, self::ALLOWED_ROLES[$type], true)) {
            return $this->respond('forbidden', 403, [
                'operation_uuid' => $uuid,
                'message' => 'Role Anda tidak diizinkan untuk operasi ini.',
            ]);
        }

        $payloadHash = self::hashPayload($payload);

        try {
            $syncOperation = DB::transaction(function () use ($uuid, $type, $payload, $expectedState, $user, $payloadHash) {
                // Insert the ledger row FIRST, inside the same transaction
                // as the business write it guards — see section 6/14: the
                // unique index on operation_uuid is what actually makes two
                // concurrent requests for the same operation safe (only one
                // INSERT can ever succeed), not an "exists then create"
                // check, which would leave a race window.
                $syncOperation = SyncOperation::create([
                    'operation_uuid' => $uuid,
                    'transaction_type' => $type,
                    'created_by_user_id' => $user->id,
                    'payload_hash' => $payloadHash,
                ]);

                $subject = $this->process($type, $payload, $expectedState, $user);

                $syncOperation->forceFill([
                    'processed_at' => now(),
                    'subject_type' => $subject->getMorphClass(),
                    'subject_id' => $subject->getKey(),
                ])->save();

                return $syncOperation;
            });

            return $this->respond('processed', 200, [
                'operation_uuid' => $uuid,
                'transaction_type' => $type,
                'subject_type' => $syncOperation->subject_type,
                'subject_id' => $syncOperation->subject_id,
                'processed_at' => Carbon::parse($syncOperation->processed_at)->toIso8601String(),
            ]);
        } catch (UniqueConstraintViolationException) {
            // Someone else (a genuine retry, or a concurrent duplicate
            // request) already owns this operation_uuid — see section 5/14.
            return $this->handleDuplicate($uuid, $payloadHash);
        } catch (AuthorizationException $e) {
            return $this->respond('forbidden', 403, [
                'operation_uuid' => $uuid,
                'message' => $e->getMessage(),
            ]);
        } catch (SyncConflictException $e) {
            return $this->respond('conflict', 409, [
                'operation_uuid' => $uuid,
                'message' => $e->getMessage(),
                'context' => $e->context,
            ]);
        } catch (ValidationException $e) {
            return $this->respond('validation_failed', 422, [
                'operation_uuid' => $uuid,
                'errors' => $e->errors(),
            ]);
        } catch (Throwable $e) {
            Log::error('Sync operation failed', [
                'operation_uuid' => $uuid,
                'transaction_type' => $type,
                'error' => $e->getMessage(),
            ]);

            return $this->respond('failed', 500, [
                'operation_uuid' => $uuid,
                'message' => 'Operasi gagal diproses di server. Operasi ini aman untuk dicoba lagi.',
            ]);
        }
    }

    /**
     * The UNIQUE constraint on operation_uuid just rejected our INSERT, so
     * a row for this uuid already exists (committed by an earlier request —
     * InnoDB's implicit row lock on the unique index means a still-running
     * concurrent transaction would have made US wait, not fail outright;
     * see section 14). Look at that row to tell the client what actually
     * happened last time — never a bare "duplicate".
     */
    private function handleDuplicate(string $uuid, string $payloadHash): JsonResponse
    {
        $existing = SyncOperation::where('operation_uuid', $uuid)->first();

        if (! $existing) {
            return $this->respond('failed', 500, [
                'operation_uuid' => $uuid,
                'message' => 'Tidak dapat menentukan status operasi ini. Operasi ini aman untuk dicoba lagi.',
            ]);
        }

        if ($existing->payload_hash !== $payloadHash) {
            return $this->respond('payload_mismatch', 409, [
                'operation_uuid' => $uuid,
                'message' => 'operation_uuid ini sudah pernah digunakan dengan payload yang berbeda. Operasi TIDAK diproses ulang.',
            ]);
        }

        if ($existing->processed_at === null) {
            // Only reachable on a database without InnoDB-style blocking
            // (e.g. SQLite in tests): the owning request's transaction has
            // not committed yet. Tell the client to retry shortly rather
            // than guessing success or failure.
            return $this->respond('in_progress', 409, [
                'operation_uuid' => $uuid,
                'message' => 'Operasi ini sedang diproses oleh request lain. Silakan coba lagi sesaat lagi.',
            ]);
        }

        return $this->respond('already_processed', 200, [
            'operation_uuid' => $uuid,
            'transaction_type' => $existing->transaction_type,
            'subject_type' => $existing->subject_type,
            'subject_id' => $existing->subject_id,
            'processed_at' => Carbon::parse($existing->processed_at)->toIso8601String(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function respond(string $status, int $httpStatus, array $data): JsonResponse
    {
        return response()->json(array_merge([
            'success' => $httpStatus < 300,
            'status' => $status,
        ], $data), $httpStatus);
    }

    /**
     * Public + static so tests (and any future internal caller that needs
     * to know what hash a given payload will produce) can compute it the
     * exact same way, without duplicating the normalization logic.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function hashPayload(array $payload): string
    {
        return hash('sha256', json_encode(self::normalizeForHash($payload), JSON_THROW_ON_ERROR));
    }

    /**
     * Recursively sorts associative-array keys so the hash is stable
     * regardless of the key order the client happened to serialize —
     * list arrays (sessions[], problems[], ...) keep their order, since
     * order is significant there.
     */
    private static function normalizeForHash(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $normalized = array_map(self::normalizeForHash(...), $value);

        if (! array_is_list($normalized)) {
            ksort($normalized);
        }

        return $normalized;
    }

    /**
     * Every field the device expects still to be true is compared against
     * the actual current value. A device that sends no expected_state (or
     * omits a given key) simply skips that check — conflict detection is
     * opt-in per field, never a hard requirement (section 9/10: minimal,
     * not over-engineered).
     *
     * @param  array<string, mixed>  $expected
     * @param  array<string, mixed>  $actual
     */
    private function assertNoStateConflict(array $expected, array $actual): void
    {
        foreach ($expected as $key => $value) {
            if (array_key_exists($key, $actual) && $actual[$key] !== $value) {
                throw new SyncConflictException(
                    "Server state has changed for '{$key}'.",
                    ['field' => $key, 'expected' => $value, 'actual' => $actual[$key]]
                );
            }
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $expectedState
     */
    private function process(string $type, array $payload, array $expectedState, User $user): Model
    {
        return match ($type) {
            self::TYPE_PM_START => $this->processPmStart($payload, $expectedState, $user),
            self::TYPE_PM_SAVE => $this->processPmSave($payload, $expectedState, $user),
            self::TYPE_PM_CHECKLIST_SAVE => $this->processPmChecklistSave($payload, $expectedState, $user),
            self::TYPE_OIL_AUDIT_CREATE => $this->processOilAuditCreate($payload, $user),
            self::TYPE_OIL_AUDIT_FOLLOW_UP_SAVE => $this->processOilAuditFollowUpSave($payload, $expectedState, $user),
            // Unreachable in practice — $type was already checked against
            // self::TRANSACTION_TYPES by the envelope validation above —
            // but kept as an explicit, loud failure rather than a silent
            // null so a future transaction type added to TRANSACTION_TYPES
            // without a matching arm here fails fast instead of quietly.
            default => throw new \LogicException("Unhandled sync transaction_type: {$type}"),
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function findPmSchedule(array $payload): PMSchedule
    {
        $pmSchedule = PMSchedule::find((int) ($payload['pm_schedule_id'] ?? 0));

        if (! $pmSchedule) {
            throw new SyncConflictException(
                'PM Schedule tidak ditemukan (mungkin sudah dihapus sejak device terakhir sync).',
                ['pm_schedule_id' => $payload['pm_schedule_id'] ?? null]
            );
        }

        return $pmSchedule;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $expectedState
     */
    private function processPmStart(array $payload, array $expectedState, User $user): PMSchedule
    {
        $pmSchedule = $this->findPmSchedule($payload);

        if (! $pmSchedule->isAccessibleBy($user)) {
            throw new AuthorizationException('Anda tidak memiliki akses ke PM Schedule ini.');
        }

        $this->assertNoStateConflict($expectedState, [
            'status' => $pmSchedule->status,
            'is_started' => filled($pmSchedule->start_time),
        ]);

        $result = app(PMStartService::class)->start(
            $pmSchedule,
            $user,
            $payload['started_at'] ?? null,
            (bool) ($payload['confirm_end_start'] ?? false)
        );

        if ($result['outcome'] === PMStartService::OUTCOME_ACTIVITY_CONFLICT) {
            $conflict = $result['conflict'] ?? null;

            if (! $conflict) {
                throw new \LogicException('PMStartService reported activity_conflict without a conflict payload.');
            }

            throw new SyncConflictException(
                'PIC memiliki aktivitas lain yang masih berjalan — konfirmasi END & START diperlukan.',
                [
                    'current_activity' => [
                        'source' => $conflict->source,
                        'label' => $conflict->label,
                        'machine_number' => $conflict->machineNumber,
                        'started_at' => $conflict->startedAt->toIso8601String(),
                    ],
                ]
            );
        }

        // OUTCOME_ALREADY_FINISHED / OUTCOME_ALREADY_STARTED / OUTCOME_STARTED
        // are all legitimate idempotent-or-successful outcomes from the
        // device's own point of view once it passed the expected_state
        // check above — PMStartService already guarantees no duplicate
        // start / no overwrite, exactly like the online endpoint.
        return $pmSchedule;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $expectedState
     */
    private function processPmSave(array $payload, array $expectedState, User $user): PMSchedule
    {
        $pmSchedule = $this->findPmSchedule($payload);

        if (! $pmSchedule->isAccessibleBy($user)) {
            throw new AuthorizationException('Anda tidak memiliki akses ke PM Schedule ini.');
        }

        $this->assertNoStateConflict($expectedState, ['status' => $pmSchedule->status]);

        // Same PIC-field guard as PMScheduleController::update(): only
        // ADMIN/KOORDINATOR may change the assigned PIC.
        if (! in_array($user->role, ['ADMIN', 'KOORDINATOR WWD', 'KOORDINATOR BUL'], true)) {
            $payload['pic'] = $pmSchedule->pic;
        }

        $hasSessions = array_key_exists('sessions', $payload);

        Validator::make($payload, PMScheduleSaveService::rules($hasSessions))->validate();

        $data = collect($payload)->only([
            'order_number', 'pic', 'oil_change', 'greasing', 'wo_zsbp', 'remarks',
            'sessions', 'actual_date', 'start_time', 'end_time',
            'measurements', 'problems', 'spareparts',
        ])->all();

        app(PMScheduleSaveService::class)->save($pmSchedule, $data);

        return $pmSchedule;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $expectedState
     */
    private function processPmChecklistSave(array $payload, array $expectedState, User $user): PMSchedule
    {
        $pmSchedule = $this->findPmSchedule($payload);

        if (! $pmSchedule->isAccessibleBy($user)) {
            throw new AuthorizationException('Anda tidak memiliki akses ke PM Schedule ini.');
        }

        $this->assertNoStateConflict($expectedState, ['status' => $pmSchedule->status]);

        $checklistService = app(PMChecklistSaveService::class);
        $errors = $checklistService->completionErrors($pmSchedule);

        if (! empty($errors)) {
            throw ValidationException::withMessages([
                'checklists' => ['Fill PM belum lengkap: '.implode(', ', $errors)],
            ]);
        }

        $checklistService->save($pmSchedule, (array) ($payload['checklists'] ?? []));

        return $pmSchedule;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function processOilAuditCreate(array $payload, User $user): OilAudit
    {
        $validated = Validator::make($payload, OilAuditCreateService::rules())->validate();

        $machine = Machine::whereKey($validated['machine_id'])
            ->where('area', OilAudit::AREA)
            ->whereIn('machine_type', OilAudit::MACHINE_TYPES)
            ->first();

        if (! $machine) {
            throw ValidationException::withMessages([
                'machine_id' => ['Machine tidak ditemukan atau tidak memenuhi syarat audit oli.'],
            ]);
        }

        // No expected_state / conflict check here by design: OIL_AUDIT_CREATE
        // always inserts a brand new row (see section 5/9) — the only thing
        // that must never duplicate it is the operation_uuid, already
        // guaranteed above.
        return app(OilAuditCreateService::class)->create($machine, $user, $validated['condition']);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $expectedState
     */
    private function processOilAuditFollowUpSave(array $payload, array $expectedState, User $user): OilAuditFollowUp
    {
        $oilAudit = OilAudit::find((int) ($payload['oil_audit_id'] ?? 0));

        if (! $oilAudit) {
            throw new SyncConflictException(
                'Oil Audit tidak ditemukan (mungkin sudah berubah sejak device terakhir sync).',
                ['oil_audit_id' => $payload['oil_audit_id'] ?? null]
            );
        }

        if (! $oilAudit->isInAuditScope()) {
            throw ValidationException::withMessages([
                'oil_audit_id' => ['Oil Audit di luar scope yang diizinkan.'],
            ]);
        }

        if (! $oilAudit->needsFollowUp()) {
            throw ValidationException::withMessages([
                'oil_audit_id' => ['Follow up hanya diperlukan untuk kondisi oli yang tidak oke.'],
            ]);
        }

        $existingFollowUp = $oilAudit->followUp()->first();
        $actualExists = $existingFollowUp !== null;

        $this->assertNoStateConflict($expectedState, ['follow_up_exists' => $actualExists]);

        $validated = app(OilAuditFollowUpService::class)->validate($payload);

        if ($actualExists) {
            app(OilAuditFollowUpService::class)->update($existingFollowUp, $validated);

            return $existingFollowUp;
        }

        return app(OilAuditFollowUpService::class)->store($oilAudit, $validated, $user);
    }
}
