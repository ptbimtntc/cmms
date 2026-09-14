<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Idempotency ledger row for offline sync. See the
 * create_sync_operations_table migration for the full design note.
 *
 * Not yet written or read anywhere online — this model exists purely as
 * Task 1 preparation for the offline sync endpoint built in a later task.
 */
class SyncOperation extends Model
{
    protected $fillable = [
        'operation_uuid',
        'transaction_type',
        'subject_type',
        'subject_id',
        'created_by_user_id',
        'processed_at',
        'payload_hash',
    ];

    protected function casts(): array
    {
        return [
            'processed_at' => 'datetime',
        ];
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
