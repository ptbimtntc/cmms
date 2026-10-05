<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class PMSchedule extends Model
{
    protected $table = 'pm_schedules';

    protected $fillable = [
        'machine_id',
        'machine_number',
        'machine_type',
        'area',
        'order_number',
        'plan_date',
        'plan_month',
        'plan_year',
        'due_date',
        'last_pm',
        'pic',
        'actual_date',
        'start_time',
        'end_time',
        'duration',
        'oil_change',
        'greasing',
        'wo_zsbp',
        'gearbox_problem',
        'remarks',
        'next_pm',
        'status',
    ];

    public function model(array $row)
    {
        return new PMSchedule([
            'machine_number' => $row['machine_number'],
            'plan_date' => $row['plan_date'],
            'plan_month' => $row['plan_month'],
            'plan_year' => $row['plan_year'],
            'status' => $row['status'],
        ]);
    }

    protected function picFormatted(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->pic
                ? Str::title(strtolower($this->pic))
                : '-'
        );
    }

    /**
     * PM statuses that count as "work is done" — used to decide whether a
     * PM still represents an active activity.
     */
    public const DONE_STATUSES = ['FINISHED', 'FINISHED_ON_TIME'];

    /**
     * The datetime this PM's on-site work was started, composed from the
     * EXISTING actual_date (date) + start_time (time-of-day) columns. There
     * is no dedicated started_at column and none is needed. Returns null
     * until the PM is started (see PMScheduleController::start()) or filled.
     */
    public function startedAt(): ?Carbon
    {
        if (blank($this->start_time)) {
            return null;
        }

        $date = $this->actual_date
            ? Carbon::parse($this->actual_date)->toDateString()
            : now()->toDateString();

        return Carbon::parse($date.' '.$this->start_time);
    }

    /**
     * "Active activity" = work has been started (start_time populated) and
     * the PM is not yet finished. This is derived purely from existing
     * columns, so Today's Activity can read it without any parallel
     * activity-state table to keep in sync.
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
     * Central single-record authorization gate, reused by both the online
     * controller (via abort_unless) and the offline sync handler (as a plain
     * bool, without aborting the whole request). ADMIN sees everything;
     * KOORDINATOR is scoped to their own area; PIC is scoped to their own
     * area AND their own assigned name.
     */
    public function isAccessibleBy(User $user): bool
    {
        if ($user->seesAllAreas()) {
            return $user->hasArea($this->area);
        }

        if ((! $user->isKoordinator() && ! $user->isPic()) || ! $user->area) {
            return false;
        }

        if ($this->area !== $user->area->name) {
            return false;
        }

        return $user->isKoordinator() || $this->pic === $user->name;
    }

    /**
     * Read/UI convenience relation onto the Area master row matching this
     * schedule's `area` string snapshot. Deliberately NOT named area() —
     * `area` is already a real column on this model, and Eloquent always
     * resolves $model->area to that column, never to a same-named relation
     * method, so a relation named area() would be silently unreachable.
     */
    public function areaMaster(): BelongsTo
    {
        return $this->belongsTo(Area::class, 'area', 'name');
    }

    /**
     * Which machine types require an oil change is configured in the
     * machine_maintenance_requirements table (managed via admin UI) rather
     * than hardcoded here, so new machine types can be added without a code
     * change. Result is cached per machine_type for the life of the request
     * since this is called repeatedly for the same PM (edit form, PDF
     * export, checklist validation).
     */
    public function requiresOilChange(): bool
    {
        static $cache = [];

        return $cache[$this->machine_type] ??= MachineMaintenanceRequirement::query()
            ->where('machine_type', $this->machine_type)
            ->where('requires_oil_change', true)
            ->exists();
    }

    /**
     * Business rule, not authorization: gearbox tracking (mainshaft/
     * innershaft problems) is permanently WWD-only, regardless of how many
     * other areas exist. Do not make this dynamic.
     */
    public function isGearboxApplicable(): bool
    {
        return $this->area === 'WWD';
    }

    public const GEARBOX_KEYWORDS = ['mainshaft', 'innershaft'];

    public static function matchesGearboxKeyword(?string $problemText): bool
    {
        if (! $problemText) {
            return false;
        }

        $problemText = strtolower($problemText);

        foreach (self::GEARBOX_KEYWORDS as $keyword) {
            if (str_contains($problemText, $keyword)) {
                return true;
            }
        }

        return false;
    }

    public function getDurationFormattedAttribute()
    {
        // Prefer aggregated work session duration when available (multi-day)
        if ($this->relationLoaded('workSessions') && $this->workSessions->isNotEmpty()) {
            $total = (int) $this->workSessions->sum('duration');
        } elseif ($this->workSessions()->exists()) {
            $total = (int) $this->workSessions()->sum('duration');
        } else {
            $total = $this->duration;
        }

        if (! $total) {
            return '';
        }

        $hours = floor($total / 60);
        $minutes = $total % 60;

        return "{$hours} Hours {$minutes} Minutes";
    }

    public function workSessions()
    {
        return $this->hasMany(PMWorkSession::class, 'pm_schedule_id');
    }

    public function machine()
    {
        return $this->belongsTo(Machine::class);
    }

    public function measurements()
    {
        return $this->hasMany(
            PMMeasurement::class,
            'pm_schedule_id'
        );
    }

    public function problems()
    {
        return $this->hasMany(
            PMProblem::class,
            'pm_schedule_id'
        );
    }

    public function spareparts()
    {
        return $this->hasMany(
            PMSparepart::class,
            'pm_schedule_id'
        );
    }

    public function checklists()
    {
        return $this->hasMany(
            PMChecklist::class,
            'pm_schedule_id'
        );
    }
}
