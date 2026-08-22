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

    /**
     * Group has no dedicated area column. The PIC area (WWD/BUL) it belongs
     * to is inferred from its name, e.g. "WWD 1" => WWD, "Line BUL 2" => BUL.
     * Returns null if the name contains neither, so callers can fall back
     * to not offering a PIC dropdown at all.
     */
    public function inferredArea(): ?string
    {
        $name = strtoupper($this->name);

        if (str_contains($name, 'WWD')) {
            return 'WWD';
        }

        if (str_contains($name, 'BUL')) {
            return 'BUL';
        }

        return null;
    }
}
