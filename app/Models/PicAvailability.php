<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A per-day "PIC not available" marker (Cuti / Sakit / Training / …) set
 * from the Activity Control Panel. An INACTIVE PIC is NOT an activity: it
 * never appears as a card, never enters the activity-distribution donut and
 * is never treated as a completed activity. Nothing in the activity /
 * module data is changed by it.
 */
class PicAvailability extends Model
{
    /** Predefined reasons; "Other" requires a free-text note. */
    public const REASONS = ['Cuti', 'Sakit', 'Training', 'Meeting', 'Tugas Lain', 'Off', 'Tidak Masuk', 'Other'];

    protected $fillable = [
        'user_id',
        'date',
        'reason',
        'notes',
        'set_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Short label for the monitor: "Training", or the note when reason is "Other". */
    public function label(): string
    {
        if ($this->reason === 'Other') {
            return $this->notes !== null && $this->notes !== '' ? $this->notes : 'Other';
        }

        return $this->reason;
    }
}
