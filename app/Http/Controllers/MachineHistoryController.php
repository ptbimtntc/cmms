<?php

namespace App\Http\Controllers;

use App\Models\Machine;
use App\Models\MachineMeasurement;
use App\Models\PMChecklist;
use App\Models\PMMeasurement;
use App\Models\PMProblem;
use App\Models\PMSchedule;
use App\Models\PMSparepart;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MachineHistoryController extends Controller
{
    /**
     * MACHINE-CENTRIC: the list defaults to every machine in Machine Master,
     * one row each — including machines that have never had a PM Schedule
     * record — not the set of machines that merely happen to appear in
     * pm_schedules. "Last PM" is only supplemental info per machine (via
     * withMax on the pmSchedules relation, a single extra query for the
     * whole page), it never determines which machines are listed.
     */
    public function index(Request $request)
    {
        $query = Machine::query();

        // SEARCH
        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('machine_number', 'like', '%'.$request->search.'%')
                    ->orWhere('machine_type', 'like', '%'.$request->search.'%');
            });
        }

        // FILTER AREA
        if ($request->filled('area')
        ) {
            $query->where('area', $request->area);
        }

        // FILTER MACHINE TYPE
        if ($request->filled('machine_type')) {
            $query->where('machine_type', $request->machine_type);
        }

        // GET UNIQUE AREAS — from Machine Master, so an area with machines
        // that have no PM history yet still shows up as a filter option.
        $areas = Machine::select('area')
            ->whereNotNull('area')
            ->distinct()
            ->orderBy('area')
            ->pluck('area');

        // GET UNIQUE MACHINE TYPES — same reasoning as areas above.
        $machineTypes = Machine::select('machine_type')
            ->whereNotNull('machine_type')
            ->distinct()
            ->orderBy('machine_type')
            ->pluck('machine_type');

        $machines = $query
            ->withMax('pmSchedules as last_pm', 'actual_date')
            ->orderBy('machine_number')
            ->paginate(20)
            ->withQueryString();

        return view(
            'machine-history.index',
            [
                'machines' => $machines,
                'machineTypes' => $machineTypes,
                'areas' => $areas,
                // This route sits outside the auth middleware (same as
                // show()/detail() below) so it can be reached by a guest —
                // the sidebar/topbar assume an authenticated user, so they
                // must stay hidden here too.
                'hideSidebar' => ! Auth::check(),
                'hideTopbar' => ! Auth::check(),
            ]
        );
    }

    public function show($machineNumber)
    {
        $machine = Machine::where(
            'machine_number',
            $machineNumber
        )->firstOrFail();

        $pmHistories = PMSchedule::where(
            'machine_number',
            $machineNumber
        )
            ->whereNotNull('actual_date')
            ->with('workSessions')
            ->latest('actual_date')
            ->paginate(10);

        $lastPmRecord = $pmHistories->first();

        $lastPm = $lastPmRecord?->actual_date
            ? Carbon::parse($lastPmRecord->actual_date)
            : null;

        $nextPm = null;

        if ($lastPm) {

            $nextPm = $lastPm->copy();

            switch (strtolower($machine->pm_cycle_unit)) {

                case 'day':
                    $nextPm->addDays($machine->pm_cycle_value);
                    break;

                case 'week':
                    $nextPm->addWeeks($machine->pm_cycle_value);
                    break;

                case 'month':
                    $nextPm->addMonths($machine->pm_cycle_value);
                    break;
            }

        }

        return view(
            'machine-history.show',
            [
                'machine' => $machine,
                'pmHistories' => $pmHistories,
                'lastPm' => $lastPm,
                'nextPm' => $nextPm,
                'hideSidebar' => ! Auth::check(),
                'hideTopbar' => ! Auth::check(),
            ]
        );
    }

    public function detail($machineNumber, PMSchedule $pmSchedule)
    {
        $machine = Machine::where(
            'machine_number',
            $machineNumber
        )->firstOrFail();

        $pmSchedule->load('workSessions');

        $measurements = MachineMeasurement::where(
            'machine_type',
            $pmSchedule->machine_type
        )
            ->orderBy('measurement_item')
            ->get();

        $pmMeasurements = PMMeasurement::where(
            'pm_schedule_id',
            $pmSchedule->id
        )->get();

        $pmProblems = PMProblem::with([
            'machineProblem',
            'machineProblemFinding',
        ])
            ->where('pm_schedule_id', $pmSchedule->id)
            ->get();

        $pmSpareparts = PMSparepart::with('sparepart')
            ->where('pm_schedule_id', $pmSchedule->id)
            ->get();

        $lastPm = PMSchedule::where('machine_number', $pmSchedule->machine_number)
            ->whereNotNull('actual_date')
            ->where('actual_date', '<', $pmSchedule->actual_date)
            ->latest('actual_date')
            ->value('actual_date');

        $lastPm = $lastPm ? Carbon::parse($lastPm) : null;

        $nextPm = null;

        if ($pmSchedule->actual_date) {

            $nextPm = Carbon::parse($pmSchedule->actual_date);

            $unit = strtolower(trim($pmSchedule->machine->pm_cycle_unit));
            $value = (int) $pmSchedule->machine->pm_cycle_value;

            match ($unit) {
                'day' => $nextPm->addDays($value),
                'week' => $nextPm->addWeeks($value),
                'month' => $nextPm->addMonths($value),
                default => null,
            };
        }

        $totalCost = $pmSpareparts->sum(function ($item) {
            return ($item->qty ?? 0) * ($item->sparepart->price ?? 0);
        });

        $checklists = PMChecklist::with('machineChecklist')
            ->where('pm_schedule_id', $pmSchedule->id)
            ->get()
            ->groupBy(function ($item) {
                return $item->machineChecklist->section ?? 'Others';
            });

        return view(
            'machine-history.detail',
            [
                'machine' => $machine,
                'pmSchedule' => $pmSchedule,
                'measurements' => $measurements,
                'pmMeasurements' => $pmMeasurements,
                'pmProblems' => $pmProblems,
                'pmSpareparts' => $pmSpareparts,
                'lastPm' => $lastPm,
                'nextPm' => $nextPm,
                'totalCost' => $totalCost,
                'checklists' => $checklists,
                'hideSidebar' => ! Auth::check(),
                'hideTopbar' => ! Auth::check(),
            ]
        );
    }
}
