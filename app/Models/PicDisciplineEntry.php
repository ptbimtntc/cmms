<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PicDisciplineEntry extends Model
{
    public const STATUS_PENDING = 'PENDING';

    public const STATUS_ACHIEVED = 'ACHIEVED';

    public const STATUS_BREACH = 'BREACH';

    public const SOURCE_AUTO = 'AUTO';

    public const SOURCE_MANUAL = 'MANUAL';

    protected $fillable = [
        'pm_schedule_id',
        'item_type_id',
        'pic_user_id',
        'due_date',
        'completed_date',
        'status',
        'source',
        'recorded_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'completed_date' => 'date',
        ];
    }

    public function pmSchedule(): BelongsTo
    {
        return $this->belongsTo(PMSchedule::class);
    }

    public function itemType(): BelongsTo
    {
        return $this->belongsTo(PicDisciplineItemType::class, 'item_type_id');
    }

    public function picUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pic_user_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * Area comes from the related PM Schedule (string `area` column), not a
     * direct column here, so this can't go through AreaAuthorizationScope —
     * same situation as Greasing::scopeVisibleToUser(). PIC is excluded from
     * every route this scope backs (see routes/web.php), the isPic() branch
     * exists only for defense in depth.
     */
    public function scopeVisibleToUser(Builder $query, User $user): Builder
    {
        if ($user->seesAllAreas()) {
            if (($restricted = $user->restrictedAreaNames()) !== null) {
                $query->whereHas('pmSchedule', fn (Builder $q) => $q->whereIn('area', $restricted));
            }

            return $query;
        }

        if ((! $user->isKoordinator() && ! $user->isPic()) || ! $user->area) {
            return $query->whereRaw('0 = 1');
        }

        $query->whereHas('pmSchedule', fn (Builder $q) => $q->where('area', $user->area->name));

        if ($user->isPic()) {
            $query->where('pic_user_id', $user->id);
        }

        return $query;
    }

    /**
     * Single source of truth for A (Achieved) vs B (Breach): completed on or
     * before due_date = Achieved; completed after due_date, or not completed
     * at all once due_date has passed, = Breach. Still open and not yet due
     * = Pending (excluded from scoring until it resolves one way or the
     * other — see PicDisciplineKpiCalculator).
     */
    public function recomputeStatus(): void
    {
        if ($this->completed_date === null) {
            $this->status = Carbon::now()->startOfDay()->greaterThan($this->due_date)
                ? self::STATUS_BREACH
                : self::STATUS_PENDING;

            return;
        }

        $this->status = $this->completed_date->greaterThan($this->due_date)
            ? self::STATUS_BREACH
            : self::STATUS_ACHIEVED;
    }
}
