<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Models\PMSchedule;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class PMStatusBoardController extends Controller
{
    /** How many months ahead of the current month the board can load. */
    private const MAX_MONTH_OFFSET = 2;

    /**
     * Public PM Status Board — read-only view for production team.
     * No authentication required. One area per URL (/pm-status/wwd,
     * /pm-status/gripper, ...) — area is a route constraint, not a filter,
     * so the query never mixes areas. Displays schedule status: OPEN/CLOSED,
     * plan date, actual completion, and GAP DAY (realtime calc, not stored).
     *
     * The slug is resolved against the live Area master list (not a
     * hardcoded map) so a newly added area gets a working URL immediately;
     * an unknown or inactive slug 404s rather than silently falling back to
     * "all areas". No compile-time route constraint is used here (see
     * routes/web.php) since a DB-driven allowlist would go stale the moment
     * `route:cache` runs.
     */
    public function index(Request $request, string $area)
    {
        $areaModel = $this->resolveArea($area);
        $areaCode = $areaModel->name;

        $now = Carbon::now('Asia/Jakarta');

        // No period filter: everything still OPEN up to and including the
        // current month. Older months have no cut-off — a machine stays on
        // the board for as long as it is open.
        $schedules = $this->rows(
            PMSchedule::query()
                ->where('area', $areaCode)
                ->whereIn('status', PMSchedule::PLANNABLE_STATUSES)
                ->where('plan_date', '<', $now->copy()->startOfMonth()->addMonth()->toDateString()),
            $now
        );

        $summary = [
            'total' => $schedules->count(),
            'open' => $schedules->where('status', 'OPEN')->count(),
            'closed' => $schedules->where('status', 'CLOSED')->count(),
        ];

        return view('pm-status-board.index', [
            'schedules' => $schedules,
            'canOverrideStatus' => $this->canOverrideStatus(),
            'summary' => $summary,
            'currentArea' => $areaCode,
            'areaSlug' => $area,
            'areas' => Area::active()->orderBy('name')->get(),
            'periodLabel' => $now->translatedFormat('F Y'),
            'nextMonthLabel' => $now->copy()->startOfMonth()->addMonth()->translatedFormat('F Y'),
            'maxMonthOffset' => self::MAX_MONTH_OFFSET,
            'hideSidebar' => ! Auth::check(),
            'hideTopbar' => ! Auth::check(),
        ]);
    }

    /**
     * Load one future month (still-OPEN machines only) for the "load next
     * month" button. Returns ready-to-insert row markup so the row layout
     * lives only in the Blade partials. Public, same as the board itself.
     */
    public function month(Request $request, string $area)
    {
        $areaModel = $this->resolveArea($area);

        $offset = (int) $request->query('offset');
        abort_unless(
            ctype_digit((string) $request->query('offset')) && $offset >= 1 && $offset <= self::MAX_MONTH_OFFSET,
            422,
            'Invalid month offset.'
        );

        $now = Carbon::now('Asia/Jakarta');
        $start = $now->copy()->startOfMonth()->addMonths($offset);

        $schedules = $this->rows(
            PMSchedule::query()
                ->where('area', $areaModel->name)
                ->whereIn('status', PMSchedule::PLANNABLE_STATUSES)
                ->where('plan_date', '>=', $start->toDateString())
                ->where('plan_date', '<', $start->copy()->addMonth()->toDateString()),
            $now
        );

        $canOverrideStatus = $this->canOverrideStatus();
        $hasMore = $offset < self::MAX_MONTH_OFFSET;

        return response()->json([
            'desktop' => view('pm-status-board._rows-desktop', compact('schedules', 'canOverrideStatus'))->render(),
            'mobile' => view('pm-status-board._cards-mobile', compact('schedules', 'canOverrideStatus'))->render(),
            'has_more' => $hasMore,
            'next_offset' => $hasMore ? $offset + 1 : null,
            'next_label' => $hasMore ? $start->copy()->addMonth()->translatedFormat('F Y') : null,
        ]);
    }

    private function resolveArea(string $slug): Area
    {
        return Area::where('slug', $slug)->active()->firstOrFail();
    }

    private function canOverrideStatus(): bool
    {
        $user = Auth::user();

        return (bool) ($user && ($user->isAdmin() || $user->isKoordinator()));
    }

    private function rows($query, Carbon $now): Collection
    {
        // Calendar-date boundary for GAP DAY, independent of the current
        // time-of-day (so 23:59 vs 00:01 on the same day gives the same gap).
        $today = $now->copy()->startOfDay();

        return $query
            ->orderBy('plan_date', 'asc')
            ->orderBy('machine_number', 'asc')
            ->get()
            ->map(function ($schedule) use ($today) {
                // Compute GAP DAY realtime = Today - Plan Date.
                // Carbon's diffInDays($other, false) yields $other - $this,
                // so the plan date is the caller to get "today - plan".
                $planDate = Carbon::parse($schedule->plan_date, 'Asia/Jakarta')->startOfDay();
                $gapDay = (int) $planDate->diffInDays($today, false);

                // Determine status: CLOSED if actual completion exists
                $isClosed = in_array($schedule->status, PMSchedule::DONE_STATUSES, true);

                return [
                    'id' => $schedule->id,
                    'machine_number' => $schedule->machine_number,
                    'month_label' => $planDate->translatedFormat('F Y'),
                    'plan_date' => $planDate->format('d M Y'),
                    'gap_day' => $gapDay,
                    'is_gap_day_out_of_range' => $gapDay < -14 || $gapDay > 14,
                    'actual_date' => $schedule->actual_date ? Carbon::parse($schedule->actual_date, 'Asia/Jakarta')->format('d M Y') : null,
                    'status' => $isClosed ? 'CLOSED' : 'OPEN',
                ];
            });
    }
}
