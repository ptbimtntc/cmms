<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An ad-hoc activity started by a koordinator/admin that does not originate
 * from PM, Oil Audit, or Greasing. It participates in Today's Activity and
 * the Task 06 activity-conflict handling exactly like the other sources —
 * "active" is derived (started today + owner's newest), never a stored flag.
 */
class ManualActivity extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'machine_number',
        'started_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Manual activities the given user started on the given date
     * (defaults to today). Used by ActiveActivityResolver.
     */
    public function scopeStartedOnFor(Builder $query, int $userId, ?string $date = null): Builder
    {
        return $query
            ->where('user_id', $userId)
            ->whereDate('started_at', $date ?? today());
    }
}
