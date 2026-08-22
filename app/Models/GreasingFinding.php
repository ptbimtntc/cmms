<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GreasingFinding extends Model
{
    public const STATUSES = [
        'OPEN',
        'COMPLETED',
    ];

    protected $fillable = [
        'greasing_id',
        'finding',
        'action_date',
        'action',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'action_date' => 'date',
        ];
    }

    public function greasing(): BelongsTo
    {
        return $this->belongsTo(Greasing::class);
    }
}
