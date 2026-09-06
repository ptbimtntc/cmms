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
     * The area (WWD/BUL) this schedule belongs to. Greasing has no area
     * column of its own — it is derived from the linked Group's name, the
     * same rule Group::inferredArea() and GreasingReportController use.
     */
    public function inferredArea(): ?string
    {
        return $this->group?->inferredArea();
    }

    /**
     * Restricts a query to the schedules a user is allowed to see, giving
     * Greasing the same per-area authorization PMScheduleController applies
     * to PM schedules: a WWD role (koordinator/PIC) never sees BUL-group
     * schedules and a BUL role never sees WWD-group schedules, while PIC
     * roles are additionally narrowed to schedules assigned to them by
     * name. ADMIN (and any other role) sees everything.
     *
     * The rule is expressed as "exclude the opposite area" rather than
     * "only my area" so that a schedule whose Group name carries neither
     * token (area indeterminate — see Group::inferredArea()) stays visible
     * instead of silently disappearing.
     */
    public function scopeVisibleToUser(Builder $query, User $user): Builder
    {
        $notInArea = fn (string $area) => $query->whereDoesntHave(
            'group',
            fn (Builder $q) => $q->whereRaw('UPPER(name) LIKE ?', ['%'.$area.'%'])
        );

        return match ($user->role) {
            User::ROLE_KOORDINATOR_WWD => $notInArea('BUL'),
            User::ROLE_KOORDINATOR_BUL => $notInArea('WWD'),
            User::ROLE_PIC_WWD => $notInArea('BUL')->where('pic', $user->name),
            User::ROLE_PIC_BUL => $notInArea('WWD')->where('pic', $user->name),
            default => $query,
        };
    }

    /**
     * Per-record counterpart of scopeVisibleToUser(), for the execution
     * flow where a single schedule is acted on via its URL. Mirrors
     * PMScheduleController::authorizeScheduleAccess(). Same "exclude the
     * opposite area" leniency for an indeterminate group area.
     */
    public function isAccessibleBy(User $user): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        $area = $this->inferredArea();

        return match ($user->role) {
            User::ROLE_KOORDINATOR_WWD => $area !== 'BUL',
            User::ROLE_KOORDINATOR_BUL => $area !== 'WWD',
            User::ROLE_PIC_WWD => $area !== 'BUL' && $this->pic === $user->name,
            User::ROLE_PIC_BUL => $area !== 'WWD' && $this->pic === $user->name,
            default => false,
        };
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
