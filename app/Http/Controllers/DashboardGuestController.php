<?php

namespace App\Http\Controllers;

use App\Services\PublicPmOverview;
use Carbon\Carbon;

class DashboardGuestController extends Controller
{
    /**
     * Public preview dashboard. Figures come from PublicPmOverview, the same
     * source the landing page reads, so the two pages never show different
     * numbers for the same month.
     */
    public function index()
    {
        $monthly = PublicPmOverview::monthly();

        return view('dashboard-guest', [
            'pmThisMonth' => $monthly['planned'] ?? 0,
            'overduePm' => $monthly['overdue'] ?? 0,
            'problems' => $monthly['problems'] ?? 0,
            'sparepartUsage' => $monthly['sparepart_usage'] ?? 0,
            'periodLabel' => $monthly['period_label'] ?? Carbon::now()->translatedFormat('F Y'),
            'overviewFailed' => $monthly === null,
        ]);
    }
}
