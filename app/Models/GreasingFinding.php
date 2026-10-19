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
        'machine_id',
        'finding_area',
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

    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }

    /**
     * Canonicalizes a free-text finding area so "Kapstan3", "kapstan 3" and
     * " KAPSTAN3 " are all stored identically — otherwise the Finding
     * Report's "Top Finding Area" ranking (GreasingReportController)
     * would split one real area into several near-duplicate rows.
     *
     * Lowercases AND strips every whitespace character (not just leading/
     * trailing), matching this app's existing area-code convention
     * (kapstan1, kapstan2, ...) which never has internal spaces.
     */
    public static function normalizeFindingArea(string $value): string
    {
        return strtolower(preg_replace('/\s+/', '', $value));
    }
}
