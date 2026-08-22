<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Greasing extends Model
{
    public const STATUSES = [
        'OPEN',
        'FINISH',
        'FINISH ON TIME',
    ];

    protected $fillable = [
        'group_id',
        'order_number',
        'cycle',
        'plan_date',
        'due_date',
        'pic',
        'action_date',
        'status',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'plan_date' => 'date',
            'due_date' => 'date',
            'action_date' => 'date',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function findings(): HasMany
    {
        return $this->hasMany(GreasingFinding::class);
    }

    /**
     * due_date is always plan_date + 14 days. Never trust a due_date
     * coming from the request/browser/import.
     */
    public static function calculateDueDate(string|Carbon $planDate): Carbon
    {
        return Carbon::parse($planDate)->addDays(14);
    }

    /**
     * Status is derived purely from action_date vs due_date and must
     * never be trusted from request input.
     *
     * Comparison is normalized to date-only (startOfDay) so that any
     * time-of-day or timezone artifact on either value can never flip
     * the result (e.g. action_date == due_date must always resolve to
     * FINISH ON TIME).
     */
    public static function resolveStatus(string|Carbon|null $actionDate, string|Carbon $dueDate): string
    {
        if (blank($actionDate)) {
            return 'OPEN';
        }

        $action = Carbon::parse($actionDate)->startOfDay();
        $due = Carbon::parse($dueDate)->startOfDay();

        return $action->lessThanOrEqualTo($due)
            ? 'FINISH ON TIME'
            : 'FINISH';
    }
}
