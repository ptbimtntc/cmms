<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * The one shared implementation of the role×area visibility rule used
 * throughout the app (Dashboard + the 5 report controllers + the PM Schedule
 * list): ADMIN sees everything (optionally narrowed by an explicit area
 * filter, e.g. the admin-only `?area=` query param); KOORDINATOR sees only
 * their own area; PIC sees only their own area AND their own assigned rows.
 *
 * Replaces what used to be ~7 near-duplicate private `applyScopeTo`/
 * `filteredQuery` methods, each re-implementing this same switch on the old
 * combined role strings. Column names are parameters because they differ per
 * query ("area" on machines/pm_schedules directly, "pm_schedules.area" when
 * joined from a report, etc).
 *
 * Intentional behavior change vs. the old duplicated methods: those only
 * filtered PIC rows by name (no area check). This unifies PIC scoping to
 * area+name everywhere, matching the stricter rule PMSchedule::isAccessibleBy()
 * already used for single-record access — see PMScheduleAreaScopeTest.
 *
 * For models where area is reached via a relation rather than a direct
 * column (e.g. Greasing, whose area comes from `group.area`), this class
 * does not apply — see Greasing::scopeVisibleToUser() for the deliberate
 * relation-based counterpart of this same three-tier rule.
 */
final class AreaAuthorizationScope
{
    public static function apply(
        Builder $query,
        User $user,
        string $areaColumn,
        ?string $picColumn = null,
        ?string $adminAreaFilter = null,
    ): Builder {
        if ($user->seesAllAreas()) {
            if (($restricted = $user->restrictedAreaNames()) !== null) {
                $query->whereIn($areaColumn, $restricted);
            }

            return $adminAreaFilter
                ? $query->where($areaColumn, $adminAreaFilter)
                : $query;
        }

        if ((! $user->isKoordinator() && ! $user->isPic()) || ! $user->area) {
            return $query->whereRaw('0 = 1');
        }

        $query->where($areaColumn, $user->area->name);

        if ($user->isPic() && $picColumn) {
            $query->where($picColumn, $user->name);
        }

        return $query;
    }
}
