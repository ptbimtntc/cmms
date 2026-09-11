<?php

namespace App\Http\Controllers;

use App\Models\PicAvailability;
use App\Models\User;
use App\Services\ActiveActivityResolver;
use App\Support\Activities\ActiveActivity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Today's Activity — a public, login-free operational monitoring board for
 * the PM team, built for a wall/TV display. Layout is 3:1 — active-activity
 * cards on the left, a compact "Maintenance Status" column on the right
 * (active-PIC count, an activity-distribution donut of the CURRENTLY ACTIVE
 * activities, and the not-started PICs).
 *
 * It is intentionally NOT a dashboard: no shift / cost / sparepart /
 * completed KPIs. Read-only; it changes nothing.
 *
 * {@see data()} backs the lightweight 60s auto-refresh — it returns only the
 * board data as JSON so the page can patch itself in place instead of doing
 * a full browser reload.
 */
class TodayActivityMonitorController extends Controller
{
    public function index(Request $request): View
    {
        $area = $this->sanitizeArea($request);

        return view('today-activity-monitor', [
            ...$this->board($area),
            'isAdmin' => $request->user()?->isAdmin() ?? false,
            'selectedArea' => $area ?? 'ALL',
        ]);
    }

    /**
     * Board data only — consumed by the auto-refresh poll (and by the Area
     * filter, which re-fetches this in place — no full page reload).
     * No history, no reports, no dashboard aggregates.
     */
    public function data(Request $request): JsonResponse
    {
        $board = $this->board($this->sanitizeArea($request));

        $active = array_map(fn (array $entry) => [
            'name' => $entry['pic']->name,
            'photo' => $entry['pic']->photo_url,
            'initials' => $entry['pic']->initials(),
            'source' => $entry['activity']->source,
            'activity' => $entry['activity']->displayLabel(),
            'machine' => $entry['activity']->machineNumber,
            'location' => $entry['activity']->locationLabel(),
            'startTime' => $entry['activity']->startedAt->format('H:i'),
        ], $board['active']);

        return response()->json([
            'active' => $active,
            'notStarted' => array_map('strtoupper', $board['notStarted']),
            'activeCount' => count($active),
            'totalPics' => $board['totalPics'],
            'distribution' => $board['distribution'],
            'manualBreakdown' => $board['manualBreakdown'],
            'inactive' => $board['inactive'],
            'counts' => $board['counts'],
            'area' => $board['area'],
        ]);
    }

    /**
     * "ALL" (default), "WWD" or "BUL" from ?area=, or null for ALL / anything
     * unrecognised. Server-side sanitization — never trusts the raw query
     * value beyond this whitelist.
     */
    private function sanitizeArea(Request $request): ?string
    {
        $area = strtoupper((string) $request->query('area', ''));

        return in_array($area, ['WWD', 'BUL'], true) ? $area : null;
    }

    /**
     * The single small query set Today's Activity needs: the active PIC
     * roster (a handful of columns), optionally narrowed to one area, plus
     * per PIC their current activity derived by ActiveActivityResolver from
     * already-indexed columns. Every other figure (donut, Area Status, Not
     * Started, Inactive, counts) is derived from this SAME filtered roster,
     * so the whole right panel stays consistent with the selected area.
     *
     * @param  string|null  $areaFilter  "WWD", "BUL", or null for every area
     * @return array{active: list<array{pic: User, activity: ActiveActivity}>, notStarted: list<string>, inactive: list<array{name: string, reason: string}>, totalPics: int, distribution: array<string, int>, manualBreakdown: list<array{name: string, count: int}>, area: array<string, array{active: int, available: int}>, counts: array{active: int, notStarted: int, inactive: int}}
     */
    private function board(?string $areaFilter = null): array
    {
        $resolver = app(ActiveActivityResolver::class);

        $roles = match ($areaFilter) {
            'WWD' => [User::ROLE_PIC_WWD],
            'BUL' => [User::ROLE_PIC_BUL],
            default => [User::ROLE_PIC_WWD, User::ROLE_PIC_BUL],
        };

        $pics = User::query()
            ->select(['id', 'name', 'role', 'avatar_path', 'oil_audit_started_at', 'oil_audit_action_started_at'])
            ->whereIn('role', $roles)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        // Today's INACTIVE PICs — not an activity: no card, no donut count.
        $inactiveByUser = PicAvailability::query()
            ->with('user:id,name')
            ->whereIn('user_id', $pics->pluck('id'))
            ->whereDate('date', today())
            ->get()
            ->keyBy('user_id');

        $active = [];
        $notStarted = [];
        $inactive = [];
        // Distribution counts ONLY currently-active activities, by source.
        $distribution = ['PM' => 0, 'GREASING' => 0, 'OIL_AUDIT' => 0, 'OIL_AUDIT_ACTION' => 0, 'MANUAL' => 0];
        $manualByName = [];
        // Only the area(s) in scope — when filtered to one area, the other
        // area's row simply isn't returned (rather than showing a
        // misleading 0 ACTIVE / 0 AVAILABLE for PICs that were never
        // queried), so the right panel stays consistent with the filter.
        $area = [];
        foreach ($areaFilter ? [$areaFilter] : ['WWD', 'BUL'] as $k) {
            $area[$k] = ['active' => 0, 'available' => 0];
        }

        foreach ($pics as $pic) {
            $areaKey = $pic->role === User::ROLE_PIC_BUL ? 'BUL' : 'WWD';
            $availability = $inactiveByUser->get($pic->id);

            if ($availability) {
                $inactive[] = ['name' => $pic->name, 'reason' => $availability->label()];

                continue; // inactive PICs are not "available" and never active
            }

            $area[$areaKey]['available']++;

            $current = $resolver->currentFor($pic);

            if ($current) {
                $active[] = ['pic' => $pic, 'activity' => $current];
                $area[$areaKey]['active']++;

                if (isset($distribution[$current->source])) {
                    $distribution[$current->source]++;
                }

                if ($current->source === 'MANUAL') {
                    $name = $current->displayLabel();
                    $manualByName[$name] = ($manualByName[$name] ?? 0) + 1;
                }
            } else {
                $notStarted[] = $pic->name;
            }
        }

        arsort($manualByName);
        $manualBreakdown = [];
        foreach ($manualByName as $name => $count) {
            $manualBreakdown[] = ['name' => $name, 'count' => $count];
        }

        return [
            'active' => $active,
            'notStarted' => $notStarted,
            'inactive' => $inactive,
            'totalPics' => $pics->count(),
            'distribution' => $distribution,
            'manualBreakdown' => $manualBreakdown,
            'area' => $area,
            'counts' => [
                'active' => count($active),
                'notStarted' => count($notStarted),
                'inactive' => count($inactive),
            ],
        ];
    }
}
