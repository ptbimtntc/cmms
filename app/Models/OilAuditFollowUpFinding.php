<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OilAuditFollowUpFinding extends Model
{
    protected $fillable = [
        'oil_audit_follow_up_problem_id',
        'finding',
    ];

    public function problem(): BelongsTo
    {
        return $this->belongsTo(OilAuditFollowUpProblem::class, 'oil_audit_follow_up_problem_id');
    }
}
