<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
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

    public function requiresOilChange(): bool
    {
        return in_array($this->machine_type, [
            'NDE',
            'NDB',
            'BFM',
        ]);
    }

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
