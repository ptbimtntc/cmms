<?php

namespace App\Http\Controllers;

use App\Models\PMProblem;
use App\Models\PMSchedule;
use App\Models\PMSparepart;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class DashboardGuestController extends Controller
{
    /**
     * Public preview dashboard. Every metric is scoped to the running
     * month only (bulan berjalan) and keyed off PMSchedule.plan_date,
     * the same "planned this month" definition DashboardController uses.
     */
    public function index()
    {
        $now = Carbon::now();

        $thisMonth = fn (Builder $query) => $query
            ->whereYear('plan_date', $now->year)
            ->whereMonth('plan_date', $now->month);

        $pmThisMonth = PMSchedule::query()
            ->tap($thisMonth)
            ->count();

        $overduePm = PMSchedule::query()
            ->tap($thisMonth)
            ->where('status', 'MISSED')
            ->count();

        $problems = PMProblem::query()
            ->whereHas('pmSchedule', $thisMonth)
            ->count();

        $sparepartUsage = (int) PMSparepart::query()
            ->whereHas('pmSchedule', $thisMonth)
            ->sum('qty');

        return view('dashboard-guest', [
            'pmThisMonth' => $pmThisMonth,
            'overduePm' => $overduePm,
            'problems' => $problems,
            'sparepartUsage' => $sparepartUsage,
            'periodLabel' => $now->translatedFormat('F Y'),
        ]);
    }
}
