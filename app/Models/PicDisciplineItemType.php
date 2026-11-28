<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PicDisciplineItemType extends Model
{
    protected $fillable = [
        'code',
        'name',
        'is_auto',
        'grace_days',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_auto' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * The only item filled automatically from PMChecklistSaveService — every
     * other item type is MANUAL (see PicDisciplineEntryGenerator).
     */
    public const CODE_FILL_PM = 'FILL_PM';

    public function entries(): HasMany
    {
        return $this->hasMany(PicDisciplineEntry::class, 'item_type_id');
    }
}
