<?php

namespace App\Http\Controllers;

class ReportController extends Controller
{
    /**
     * Report Center: a hub of cards linking to each report's own dedicated
     * controller/view (see PMReportController for the PM Report). This
     * controller intentionally stays thin — per-report logic belongs in
     * its own controller, not here.
     */
    public function index()
    {
        return view('reports.index');
    }
}
