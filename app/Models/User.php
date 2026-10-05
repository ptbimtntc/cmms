<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use Notifiable;

    /**
     * Role constants. Role and Area are deliberately separate: a role no
     * longer encodes an area (see the `area()` relation below) so a new area
     * can be introduced purely as master data (see App\Models\Area), with no
     * new role constant and no code change required anywhere.
     */
    public const ROLE_ADMIN = 'ADMIN';

    public const ROLE_KOORDINATOR = 'KOORDINATOR';

    public const ROLE_PIC = 'PIC';

    public const ROLE_GUEST = 'GUEST';

    /**
     * View-only role. Sees every area unless one or more areas are assigned
     * (see areas()), in which case it sees only those.
     */
    public const ROLE_SUPERVISOR = 'SUPERVISOR';

    public const ROLES = [
        self::ROLE_ADMIN,
        self::ROLE_KOORDINATOR,
        self::ROLE_PIC,
        self::ROLE_GUEST,
        self::ROLE_SUPERVISOR,
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'area_id',
        'is_active',
        'avatar_path',
        'oil_audit_started_at',
        'oil_audit_action_started_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'oil_audit_started_at' => 'datetime',
            'oil_audit_action_started_at' => 'datetime',
        ];
    }

    /**
     * Whether this user has already recorded an Oil Audit activity start
     * for the current business date (Asia/Jakarta). Drives the once-a-day
     * Start prompt on the Oil Audit menu — true suppresses it until the
     * next day.
     */
    public function hasStartedOilAuditToday(): bool
    {
        return $this->oil_audit_started_at !== null
            && $this->oil_audit_started_at->isToday();
    }

    /**
     * Same once-a-day rule as hasStartedOilAuditToday(), for the separate
     * Oil Audit Action (follow-up monitoring) menu.
     */
    public function hasStartedOilAuditActionToday(): bool
    {
        return $this->oil_audit_action_started_at !== null
            && $this->oil_audit_action_started_at->isToday();
    }

    /*
    |--------------------------------------------------------------------------
    | Role Checking Methods
    |--------------------------------------------------------------------------
    */

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isKoordinator(): bool
    {
        return $this->role === self::ROLE_KOORDINATOR;
    }

    public function isPic(): bool
    {
        return $this->role === self::ROLE_PIC;
    }

    public function isGuest(): bool
    {
        return $this->role === self::ROLE_GUEST;
    }

    public function isSupervisor(): bool
    {
        return $this->role === self::ROLE_SUPERVISOR;
    }

    /**
     * Visibility (not permission) across areas: ADMIN and SUPERVISOR (the
     * latter possibly narrowed to its assigned areas, see restrictedAreaNames()).
     * Used by data scoping and area filters; write permission is still
     * gated by role middleware and isAdmin()/isKoordinator() checks.
     */
    public function seesAllAreas(): bool
    {
        return $this->isAdmin() || $this->isSupervisor();
    }

    /**
     * Areas assigned to a SUPERVISOR (zero, one or many).
     */
    public function areas(): BelongsToMany
    {
        return $this->belongsToMany(Area::class, 'area_user');
    }

    /**
     * Names of the only areas this user may see, or null when unrestricted
     * (ADMIN, or a SUPERVISOR with no area assigned).
     *
     * @return list<string>|null
     */
    public function restrictedAreaNames(): ?array
    {
        if (! $this->isSupervisor()) {
            return null;
        }

        $names = $this->areas->pluck('name')->all();

        return $names === [] ? null : $names;
    }

    /**
     * Active area names offered in an Area filter for a user who sees all
     * areas, already narrowed to the supervisor's assigned areas.
     *
     * @return Collection<int, string>
     */
    public function selectableAreaNames(): Collection
    {
        $names = Area::active()->orderBy('name')->pluck('name');
        $restricted = $this->restrictedAreaNames();

        return $restricted === null
            ? $names
            : $names->filter(fn (string $name) => in_array($name, $restricted, true))->values();
    }

    public function isActive(): bool
    {
        return $this->is_active === true;
    }

    public function hasRole(array $roles): bool
    {
        return in_array($this->role, $roles);
    }

    /**
     * The user's area (WWD/BUL/any area added later via Area Management).
     * ADMIN is never restricted to one (see hasArea()) but may still have
     * area_id null here since Area is not meaningful for that role.
     */
    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    /**
     * True for ADMIN unconditionally, a SUPERVISOR for every area unless restricted to assigned ones, otherwise
     * true only if this user's own area matches the one given.
     */
    public function hasArea(Area|string $area): bool
    {
        $name = $area instanceof Area ? $area->name : $area;

        if ($this->seesAllAreas()) {
            return $this->restrictedAreaNames() === null
                || in_array($name, $this->restrictedAreaNames(), true);
        }

        return $this->area?->name === $name;
    }

    protected function nameFormatted(): Attribute
    {
        return Attribute::make(
            get: fn () => Str::title(strtolower($this->name))
        );
    }

    /**
     * Returns null (falling back to the initials placeholder in the UI)
     * whenever avatar_path is empty OR points to a file that no longer
     * exists on disk — e.g. deleted outside the app, or lost during a
     * storage reset — instead of emitting a URL that 404s as a broken
     * image icon.
     */
    protected function photoUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->avatar_path && Storage::disk('public')->exists($this->avatar_path)
                ? Storage::disk('public')->url($this->avatar_path)
                : null,
        );
    }

    public function initials(): string
    {
        $words = preg_split('/\s+/', trim($this->name));
        $words = array_filter($words);

        if (empty($words)) {
            return '?';
        }

        if (count($words) === 1) {
            return Str::upper(Str::substr($words[0], 0, 2));
        }

        $first = Str::substr(reset($words), 0, 1);
        $last = Str::substr(end($words), 0, 1);

        return Str::upper($first.$last);
    }
}
