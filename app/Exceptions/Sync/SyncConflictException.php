<?php

namespace App\Exceptions\Sync;

use RuntimeException;

/**
 * Thrown by SyncOperationController's per-transaction-type handlers when
 * the server's current business state no longer matches what the offline
 * device expected (its `expected_state`, or the referenced record no
 * longer existing). Caught by the controller BEFORE the business service
 * runs — see docs/tasks FreeDOMS Phase 1 Task 2 section 9/10: conflicts are
 * reported back to the client as a structured `conflict` response, never
 * silently resolved (no auto-overwrite, no last-write-wins).
 *
 * Thrown from inside the same DB::transaction() that would otherwise
 * record the sync_operations row, so throwing it also rolls back that
 * transaction — the operation_uuid is never marked processed, and the
 * device can safely retry once the conflict is resolved (see section 6).
 */
class SyncConflictException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(string $message, public readonly array $context = [])
    {
        parent::__construct($message);
    }
}
