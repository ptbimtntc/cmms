<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class OilAudit extends Model
{
    public const CONDITION_LABELS = [
        'OKE' => 'Oke',
        'PANTAU' => 'Pantau',
        'HAMPIR_GARIS' => 'Hampir Garis',
        'PAS_GARIS' => 'Pas Garis',
        'KRITIS' => 'Kritis',
        'OLI_KERUH' => 'Oli Keruh/Hitam',
        'GLASS_BUREM' => 'Level Glass Burem',
    ];

    /**
     * Follow-up Problem dropdown — "apa yang rusak".
     */
    public const PROBLEM_OPTIONS = [
        'Bocor Oli',
        'Bearing Oblak',
        'Baut Kendor',
        'Oli Keruh/Hitam',
        'Level Glass Burem',
        'Lainnya',
    ];

    /**
     * Follow-up Finding dropdown — "di bagian mana problem ditemukan".
     */
    public const FINDING_OPTIONS = [
        'Kapstan 1',
        'Kapstan 2',
        'Kapstan 3',
        'Kapstan 4',
        'Mainshaft',
        'Innershaft',
        'Lainnya',
    ];

    /**
     * Problems that describe the oil / sight-glass condition itself rather
     * than a mechanical part. For these the Finding is not a machine
     * location, so only the generic option is offered.
     */
    public const GENERIC_FINDING_PROBLEMS = [
        'Oli Keruh/Hitam',
        'Level Glass Burem',
        'Lainnya',
    ];

    public const GENERIC_FINDING = 'Lainnya';

    /**
     * Allowed Finding options for a given Problem selection: the full list,
     * or just the generic option for a GENERIC_FINDING_PROBLEMS problem.
     *
     * @return array<int, string>
     */
    public static function findingOptionsFor(?string $problem): array
    {
        return in_array($problem, self::GENERIC_FINDING_PROBLEMS, true)
            ? [self::GENERIC_FINDING]
            : self::FINDING_OPTIONS;
    }

    protected $fillable = [
        'machine_id',
        'machine_number',
        'machine_type',
        'area',
        'condition',
        'audited_by_user_id',
        'audited_by_name',
        'audited_at',
    ];

    protected function casts(): array
    {
        return [
            'audited_at' => 'datetime',
        ];
    }

    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }

    public function auditor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'audited_by_user_id');
    }

    public function followUp(): HasOne
    {
        return $this->hasOne(OilAuditFollowUp::class);
    }

    public function scopeRequiringFollowUp(Builder $query): void
    {
        $query->whereIn('condition', self::followUpConditions());
    }

    public static function followUpConditions(): array
    {
        // Conditions that require follow up: include GLASS_BUREM as requested
        return ['HAMPIR_GARIS', 'PAS_GARIS', 'KRITIS', 'OLI_KERUH', 'GLASS_BUREM'];
    }

    public function needsFollowUp(): bool
    {
        return in_array($this->condition, self::followUpConditions(), true);
    }

    public function conditionLabel(): string
    {
        return self::CONDITION_LABELS[$this->condition] ?? $this->condition;
    }

    public function conditionColor(): array
    {
        return match ($this->condition) {
            'OKE' => ['badge' => 'bg-emerald-600 text-white', 'dot' => 'bg-emerald-500', 'bar' => 'bg-emerald-500'],
            'PANTAU' => ['badge' => 'bg-amber-500 text-white', 'dot' => 'bg-amber-400', 'bar' => 'bg-amber-400'],
            'HAMPIR_GARIS' => ['badge' => 'bg-orange-500 text-white', 'dot' => 'bg-orange-500', 'bar' => 'bg-orange-500'],
            'PAS_GARIS' => ['badge' => 'bg-rose-500 text-white', 'dot' => 'bg-rose-500', 'bar' => 'bg-rose-500'],
            'KRITIS' => ['badge' => 'bg-red-700 text-white', 'dot' => 'bg-red-700', 'bar' => 'bg-red-700'],
            // New conditions: give OLI_KERUH a monitoring color and GLASS_BUREM a follow-up color
            'OLI_KERUH' => ['badge' => 'bg-amber-600 text-white', 'dot' => 'bg-amber-500', 'bar' => 'bg-amber-500'],
            'GLASS_BUREM' => ['badge' => 'bg-rose-600 text-white', 'dot' => 'bg-rose-500', 'bar' => 'bg-rose-500'],
            default => ['badge' => 'bg-slate-500 text-white', 'dot' => 'bg-slate-400', 'bar' => 'bg-slate-400'],
        };
    }
}
