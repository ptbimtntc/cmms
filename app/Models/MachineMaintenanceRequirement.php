<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MachineMaintenanceRequirement extends Model
{
    protected $table = 'machine_maintenance_requirements';

    protected $fillable = [
        'machine_type',
        'requires_oil_change',
    ];

    protected $casts = [
        'requires_oil_change' => 'boolean',
    ];
}
