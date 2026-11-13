<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OilAuditFollowUp extends Model
{
    protected $fillable = [
        'oil_audit_id',
        'refill_oil',
        'full_check',
        'problem',
        'action_taken',
        'pic_user_id',
        'pic_name',
        'actioned_at',
    ];

    protected function casts(): array
    {
        return [
            'actioned_at' => 'datetime',
            'refill_oil' => 'boolean',
            'full_check' => 'boolean',
        ];
    }

    public function oilAudit(): BelongsTo
    {
        return $this->belongsTo(OilAudit::class);
    }

    public function pic(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pic_user_id');
    }

    public function problems(): HasMany
    {
        return $this->hasMany(OilAuditFollowUpProblem::class);
    }
}
