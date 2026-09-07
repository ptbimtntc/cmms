<?php

namespace App\Models;

use App\Services\ActiveActivityResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Monitoring-only "Finish" marker for Today's Activity. One row = one
 * activity removed from the monitor for one business day. It never stores
 * the activity itself and never affects the source module's data/status.
 *
 * @see ActiveActivityResolver
 */
class ActivityMonitorClosure extends Model
{
    protected $fillable = [
        'source',
        'source_key',
        'business_date',
        'pic_user_id',
        'closed_by_user_id',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'business_date' => 'date',
            'closed_at' => 'datetime',
        ];
    }

    public function pic(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pic_user_id');
    }

    /** Stable key for one activity: "SOURCE:source_key". */
    public static function keyFor(string $source, int|string $sourceKey): string
    {
        return $source.':'.$sourceKey;
    }
}
