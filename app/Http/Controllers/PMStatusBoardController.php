<?php

namespace App\Http\Controllers;

use App\Models\PMSchedule;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PMStatusBoardController extends Controller
{
    /**
     * Maps the lowercase URL segment (route-constrained to these keys, see
     * routes/web.php) to the uppercase area value stored on pm_schedules.
     */
    public const AREA_CODES = [
        'wwd' => 'WWD',
        'bul' => 'BUL',
    ];

    /**
     * Public PM Status Board — read-only view for production team.
     * No authentication required. One area per URL (/pm-status/wwd,
     * /pm-status/bul) — area is a route constraint, not a filter, so the
     * query never mixes areas. Displays schedule status: OPEN/CLOSED, plan
     * date, actual completion, and GAP DAY (realtime calc, not stored).
     */
    public function index(Request $request, string $area)
    {
        $areaCode = self::AREA_CODES[$area];

        $now = Carbon::now('Asia/Jakarta');

        // Default to current month
        $month = (int) ($request->input('plan_month') ?? $now->month);
        $year = (int) ($request->input('plan_year') ?? $now->year);

        $query = PMSchedule::query()
            ->where('area', $areaCode)
            ->whereYear('plan_date', $year)
            ->whereMonth('plan_date', $month);

        // Calendar-date boundary for GAP DAY, independent of the current
        // time-of-day (so 23:59 vs 00:01 on the same day gives the same gap).
        $today = $now->copy()->startOfDay();

        $schedules = $query
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
                $isClosed = in_array($schedule->status, ['FINISHED', 'FINISHED_ON_TIME']);
                $displayStatus = $isClosed ? 'CLOSED' : 'OPEN';

                return [
                    'machine_number' => $schedule->machine_number,
                    'plan_date' => $planDate->format('d M Y'),
                    'gap_day' => $gapDay,
                    'is_gap_day_out_of_range' => $gapDay < -14 || $gapDay > 14,
                    'actual_date' => $schedule->actual_date ? Carbon::parse($schedule->actual_date, 'Asia/Jakarta')->format('d M Y') : null,
                    'status' => $displayStatus,
                ];
            });

        // SUMMARY
        $summary = [
            'total' => $schedules->count(),
            'open' => $schedules->where('status', 'OPEN')->count(),
            'closed' => $schedules->where('status', 'CLOSED')->count(),
        ];

        // FILTERS
        $months = [
            'January', 'February', 'March', 'April', 'May', 'June',
            'July', 'August', 'September', 'October', 'November', 'December',
        ];

        // The selected year must always be a selectable option, even when
        // no PM schedule exists yet for it in this area (e.g. a freshly
        // seeded area or a future/empty period) — otherwise the dropdown
        // renders blank.
        $years = PMSchedule::where('area', $areaCode)
            ->select('plan_year')
            ->distinct()
            ->orderBy('plan_year', 'desc')
            ->pluck('plan_year');

        if (! $years->contains($year)) {
            $years = $years->push($year)->sortByDesc(fn ($y) => $y)->values();
        }

        return view('pm-status-board.index', [
            'schedules' => $schedules,
            'summary' => $summary,
            'months' => $months,
            'years' => $years,
            'currentMonth' => $month,
            'currentYear' => $year,
            'currentArea' => $areaCode,
            'areaSlug' => $area,
            'periodLabel' => $now->translatedFormat('F Y'),
            'hideSidebar' => ! Auth::check(),
            'hideTopbar' => ! Auth::check(),
        ]);
    }
}
