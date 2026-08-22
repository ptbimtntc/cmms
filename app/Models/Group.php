<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Group extends Model
{
    protected $fillable = [
        'name',
    ];

    public function machines(): HasMany
    {
        return $this->hasMany(Machine::class);
    }

    public function greasings(): HasMany
    {
        return $this->hasMany(Greasing::class);
    }
}
