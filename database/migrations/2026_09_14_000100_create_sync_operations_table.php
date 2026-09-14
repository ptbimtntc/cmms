<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Idempotency ledger for offline sync (FreeDOMS offline-first, Task 1
     * preparation — see docs/tasks). Purely additive: does not touch any
     * existing table, column, or primary key.
     *
     * A future offline sync endpoint will write one row here per accepted
     * operation BEFORE/WITHIN the same DB transaction that applies it
     * (PM_SAVE, PM_START, OIL_AUDIT_CREATE, OIL_AUDIT_FOLLOW_UP_SAVE, ...).
     * If a device retries the same operation_uuid (e.g. because the
     * response was lost after the server already committed), the sync
     * handler finds the existing row and returns the original result
     * instead of re-applying the operation — see section 8 of the task
     * brief for the exact "response hilang -> retry" scenario this guards.
     *
     * Deliberately NOT wired into any controller yet — this migration only
     * prepares the storage. Existing online flows are completely untouched
     * by this table's existence.
     *
     * subject_type/subject_id: polymorphic pointer to the PM Schedule /
     * Oil Audit (etc.) row the operation produced or touched, filled once
     * processing completes — lets a retry look up "what did this operation
     * actually create/update" without re-deriving it.
     */
    public function up(): void
    {
        Schema::create('sync_operations', function (Blueprint $table) {
            $table->id();

            // Client-generated identifier for one sync operation. Existing
            // auto-increment PKs are NOT replaced by this — see task brief
            // section 7 — this UUID only identifies the operation itself.
            $table->uuid('operation_uuid')->unique();

            // e.g. PM_SAVE, PM_START, OIL_AUDIT_CREATE, OIL_AUDIT_FOLLOW_UP_SAVE
            $table->string('transaction_type', 64);

            $table->nullableMorphs('subject');

            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            // Null until the operation has actually been applied; presence
            // of a value is what makes a retry a no-op.
            $table->timestamp('processed_at')->nullable();

            // Hash of the operation payload, to detect a genuinely
            // different payload arriving under a reused operation_uuid
            // (a client bug) rather than a legitimate retry.
            $table->string('payload_hash', 64)->nullable();

            $table->timestamps();

            $table->index(['transaction_type', 'processed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_operations');
    }
};
