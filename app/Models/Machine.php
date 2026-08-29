<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Machine extends Model
{
    protected $fillable = [
        'machine_number',
        'group_id',
        'area',
        'machine_type',
        'description',
        'status',
        'install_date',
        'criticality',
        'remarks',
        'pm_cycle_value',
        'pm_cycle_unit',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function oilAudits(): HasMany
    {
        return $this->hasMany(OilAudit::class);
    }

    public function pmSchedules(): HasMany
    {
        return $this->hasMany(PMSchedule::class);
    }

    public function latestOilAudit(): HasOne
    {
        return $this->hasOne(OilAudit::class)->latestOfMany('audited_at');
    }
}
