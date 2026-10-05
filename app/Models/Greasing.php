<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
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

    /**
     * Statuses that mean the greasing work is done — used to decide whether
     * a schedule still represents an active activity.
     */
    public const DONE_STATUSES = [
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
        'start_time',
        'status',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'plan_date' => 'date',
            'due_date' => 'date',
            'action_date' => 'date',
            'start_time' => 'datetime',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    /**
     * The area this schedule belongs to. Greasing has no area column of its
     * own — it is derived from the linked Group's real `area` relation (see
     * Group::area()).
     */
    public function inferredArea(): ?string
    {
        return $this->group?->area?->name;
    }

    /**
     * Restricts a query to the schedules a user is allowed to see, giving
     * Greasing the same per-area authorization PMScheduleController applies
     * to PM schedules: a KOORDINATOR never sees a schedule confirmed to
     * belong to a different area, and a PIC is additionally narrowed to
     * schedules assigned to them by name. ADMIN sees everything.
     *
     * This is the relation-based counterpart of
     * App\Support\AreaAuthorizationScope's rule (area reached via
     * group.area rather than a direct column) — NOT an accidental
     * reimplementation. It deliberately keeps the existing "exclude the
     * opposite area" leniency: a schedule whose Group has no area set (area
     * indeterminate) stays visible instead of disappearing, so a group that
     * hasn't been assigned an area yet isn't silently hidden from everyone.
     */
    public function scopeVisibleToUser(Builder $query, User $user): Builder
    {
        if ($user->seesAllAreas()) {
            if (($restricted = $user->restrictedAreaNames()) === null) {
                return $query;
            }

            return $query->where(fn (Builder $q) => $q
                ->whereDoesntHave('group.area')
                ->orWhereHas('group.area', fn (Builder $qa) => $qa->whereIn('areas.name', $restricted)));
        }

        if ((! $user->isKoordinator() && ! $user->isPic()) || ! $user->area) {
            return $query->whereRaw('0 = 1');
        }

        $query->where(fn (Builder $q) => $q
            ->whereDoesntHave('group.area')
            ->orWhereHas('group.area', fn (Builder $qa) => $qa->where('areas.id', $user->area_id)));

        if ($user->isPic()) {
            $query->where('pic', $user->name);
        }

        return $query;
    }

    /**
     * Per-record counterpart of scopeVisibleToUser(), for the execution
     * flow where a single schedule is acted on via its URL. Mirrors
     * PMScheduleController::authorizeScheduleAccess(). Same "exclude the
     * opposite area" leniency for an indeterminate group area.
     */
    public function isAccessibleBy(User $user): bool
    {
        if ($user->seesAllAreas()) {
            $area = $this->inferredArea();

            return $area === null || $user->hasArea($area);
        }

        if ((! $user->isKoordinator() && ! $user->isPic()) || ! $user->area) {
            return false;
        }

        $area = $this->inferredArea();
        $inScope = $area === null || $area === $user->area->name;

        return $user->isKoordinator()
            ? $inScope
            : $inScope && $this->pic === $user->name;
    }

    public function findings(): HasMany
    {
        return $this->hasMany(GreasingFinding::class);
    }

    /**
     * "Active activity" = the greasing work has been started (start_time
     * populated) and the schedule is not yet completed. Derived purely from
     * existing columns so Today's Activity can read it without any parallel
     * activity-state table.
     */
    public function isActiveActivity(): bool
    {
        return filled($this->start_time)
            && ! in_array($this->status, self::DONE_STATUSES, true);
    }

    public function scopeActiveActivity(Builder $query): Builder
    {
        return $query
            ->whereNotNull('start_time')
            ->whereNotIn('status', self::DONE_STATUSES);
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
