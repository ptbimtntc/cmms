<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesActivityConflict;
use App\Imports\PMScheduleImport;
use App\Models\Machine;
use App\Models\MachineChecklist;
use App\Models\MachineMeasurement;
use App\Models\MachineProblem;
use App\Models\MachineProblemFinding;
use App\Models\PMChecklist;
use App\Models\PMMeasurement;
use App\Models\PMProblem;
use App\Models\PMSchedule;
use App\Models\PMSparepart;
use App\Models\Sparepart;
use App\Models\User;
use App\Services\PMChecklistSaveService;
use App\Services\PMScheduleSaveService;
use App\Services\PMStartService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

class PMScheduleController extends Controller
{
    use HandlesActivityConflict;

    public function index(Request $request)
    {
        $query = PMSchedule::query();

        $user = auth()->user();

        switch ($user->role) {

            case 'KOORDINATOR WWD':
                $query->where('area', 'WWD');
                break;

            case 'KOORDINATOR BUL':
                $query->where('area', 'BUL');
                break;

            case 'PIC WWD':
            case 'PIC BUL':
                $query->where('pic', $user->name);
                break;

            case 'ADMIN':
            default:
                // lihat semua
                break;
        }

        $picsByArea = [
            'WWD' => User::where('role', 'PIC WWD')
                ->orderBy('name')
                ->get(),

            'BUL' => User::where('role', 'PIC BUL')
                ->orderBy('name')
                ->get(),
        ];

        // SEARCH
        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('machine_number', 'like', '%'.$request->search.'%')
                    ->orWhere('machine_type', 'like', '%'.$request->search.'%')
                    ->orWhere('order_number', 'like', '%'.$request->search.'%');
            });
        }

        // FILTER AREA
        if (
            $user->role === 'ADMIN' &&
            $request->filled('area')
        ) {
            $query->where('area', $request->area);
        }

        // FILTER MACHINE TYPE
        if ($request->filled('machine_type')) {
            $query->where('machine_type', $request->machine_type);
        }

        // FILTER STATUS — status[] / plan_month[] arrive as arrays from the
        // checkbox-dropdown filter (multi-select), but a plain single value
        // (e.g. an old bookmarked link) still works via the (array) cast.
        if ($request->filled('status')) {
            $query->whereIn('status', (array) $request->status);
        }

        // FILTER MONTH
        if ($request->filled('plan_month')) {
            $query->whereIn('plan_month', (array) $request->plan_month);
        }

        // FILTER YEAR
        if ($request->filled('plan_year')) {
            $query->where('plan_year', $request->plan_year);
        }

        $schedules = $query
            ->orderBy('plan_date', 'asc')
            ->orderBy('machine_number', 'asc')
            ->paginate(20)
            ->withQueryString();

        // Month
        $months = [
            'January', 'February', 'March', 'April', 'May', 'June',
            'July', 'August', 'September', 'October', 'November', 'December',
        ];

        // ambil year unique + sort DESC (smart)
        $years = PMSchedule::select('plan_year')
            ->distinct()
            ->orderBy('plan_year', 'desc')
            ->pluck('plan_year');

        // GET UNIQUE AREAS
        $areas = PMSchedule::select('area')
            ->whereNotNull('area')
            ->distinct()
            ->orderBy('area')
            ->pluck('area');

        // GET UNIQUE MACHINE TYPES
        $machineTypes = PMSchedule::select('machine_type')
            ->whereNotNull('machine_type')
            ->distinct()
            ->orderBy('machine_type')
            ->pluck('machine_type');

        return view('pm-schedules.index', compact(
            'schedules',
            'months',
            'years',
            'machineTypes',
            'areas',
            'picsByArea'
        ));
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt,xlsx,xls',
        ]);

        Excel::import(new PMScheduleImport, $request->file('file'));

        return back()->with('success', 'PM Schedule imported successfully');
    }

    public function create()
    {
        $machines = Machine::orderBy('machine_number')->get();

        return view('pm-schedules.create', compact('machines'));
    }

    public function store(Request $request)
    {

        $dueDate = Carbon::parse($request->plan_date)->addDays(14);
        $request->validate([
            'machine_id' => 'required|exists:machines,id',
            'order_number' => 'required|unique:pm_schedules',
            'plan_month' => 'required',
            'plan_year' => 'required',
            'plan_date' => 'required|date',
        ]);

        $machine = Machine::findOrFail($request->machine_id);
        $lastPm = PMSchedule::where('machine_id', $request->machine_id)
            ->where('status', 'DONE')
            ->latest('actual_date')
            ->value('actual_date');

        PMSchedule::create([
            'machine_id' => $machine->id,
            'machine_number' => $machine->machine_number,
            'machine_type' => $machine->machine_type,
            'area' => $machine->area,

            'order_number' => $request->order_number,
            'plan_month' => $request->plan_month,
            'plan_year' => $request->plan_year,
            'plan_date' => $request->plan_date,
            'due_date' => $dueDate,
            'last_pm' => $lastPm,
            'pic' => $request->pic,

            'status' => 'OPEN',
        ]);

        return redirect()
            ->route('pm-schedules.index')
            ->with('success', 'PM Schedule created successfully');
    }

    public function edit(PMSchedule $pmSchedule)
    {
        $this->authorizeScheduleAccess($pmSchedule);
        $user = auth()->user();

        if (str_starts_with($user->role, 'PIC')) {

            if ($pmSchedule->pic !== $user->name) {

                abort(403);

            }

        }
        $bigProblems = MachineProblem::where(
            'machine_type',
            $pmSchedule->machine_type
        )
            ->orderBy('problem')
            ->get();

        $problemFindings = MachineProblemFinding::all()
            ->groupBy(function ($item) {
                return strtolower(trim($item->category));
            });

        $measurements = MachineMeasurement::where('machine_type', $pmSchedule->machine_type)
            ->orderBy('measurement_item')
            ->get();

        $spareparts = Sparepart::select(
            'id',
            'material_number',
            'description',
            'location',
            'remarks',
            'unit'
        )
            ->orderBy('description')
            ->get();

        // ambil data PM yang sudah ada untuk schedule ini
        $pmMeasurements = PMMeasurement::where(
            'pm_schedule_id',
            $pmSchedule->id
        )->get();

        $pmProblems = PMProblem::with([
            'machineProblem',
            'machineProblemFinding',
        ])
            ->where(
                'pm_schedule_id',
                $pmSchedule->id
            )
            ->get();

        $pmSpareparts = PMSparepart::with('sparepart')
            ->where(
                'pm_schedule_id',
                $pmSchedule->id
            )
            ->get();

        $lastPm = PMSchedule::where('machine_number', $pmSchedule->machine_number)
            ->whereNotNull('actual_date')
            ->where('id', '!=', $pmSchedule->id)
            ->latest('actual_date')
            ->value('actual_date');

        $lastPm = $lastPm ? Carbon::parse($lastPm) : null;

        $picRole = match ($pmSchedule->area) {
            'WWD' => 'PIC WWD',
            'BUL' => 'PIC BUL',
            default => null,
        };

        $pics = User::when($picRole, function ($q) use ($picRole) {
            $q->where('role', $picRole);
        })
            ->orderBy('name')
            ->get();

        return view('pm-schedules.edit', compact(
            'pmSchedule',
            'bigProblems',
            'problemFindings',
            'measurements',
            'spareparts',
            'pmMeasurements',
            'pmProblems',
            'pmSpareparts',
            'lastPm',
            'pics'
        ));
    }

    public function update(Request $request, PMSchedule $pmSchedule)
    {
        $this->authorizeScheduleAccess($pmSchedule);
        $user = auth()->user();

        if (str_starts_with($user->role, 'PIC')) {

            if ($pmSchedule->pic !== $user->name) {

                abort(403);

            }

        }

        if (! in_array($user->role, ['ADMIN', 'KOORDINATOR WWD', 'KOORDINATOR BUL'])) {
            $request->merge([
                'pic' => $pmSchedule->pic,
            ]);
        }

        // VALIDASI INPUT — rule set lives in PMScheduleSaveService::rules()
        // so the online request and the offline sync handler never drift.
        $request->validate(PMScheduleSaveService::rules($request->has('sessions')));

        // Server-side duration and persistence — PM_SAVE business logic
        // lives in PMScheduleSaveService so it can later be reused by an
        // offline sync replay without duplicating the rule.
        $data = $request->only([
            'order_number', 'pic', 'oil_change', 'greasing', 'wo_zsbp', 'remarks',
            'sessions', 'actual_date', 'start_time', 'end_time',
            'measurements', 'problems', 'spareparts',
        ]);

        app(PMScheduleSaveService::class)->save($pmSchedule, $data);

        return redirect()
            ->route('pm-schedules.checklist', $pmSchedule->id)
            ->with('success', 'PM Progress Saved');

    }

    public function checklist(PMSchedule $pmSchedule)
    {
        $this->authorizeScheduleAccess($pmSchedule);
        // Load work sessions to decide which execution date to display (avoid N+1 in view)
        $pmSchedule->load('workSessions');

        $checklists = MachineChecklist::where(
            'machine_type',
            $pmSchedule->machine_type
        )
            ->orderBy('section_order')
            ->orderBy('item_order')
            ->get();

        $pmChecklists = PMChecklist::where(
            'pm_schedule_id',
            $pmSchedule->id
        )->get()
            ->keyBy('machine_checklist_id');

        // Decide execution / actual date for display on checklist page
        $executionDate = null;

        if ($pmSchedule->relationLoaded('workSessions') && $pmSchedule->workSessions->isNotEmpty()) {
            // Sort by actual_date and start_time to deterministically pick the last session
            $lastSession = $pmSchedule->workSessions->sortBy(function ($ws) {
                return ($ws->actual_date ?? '').' '.($ws->start_time ?? '00:00');
            })->last();

            if ($lastSession && $lastSession->actual_date) {
                $executionDate = Carbon::parse($lastSession->actual_date);
            }
        }

        // Fallback to legacy single-day actual_date when no work sessions exist
        if (! $executionDate && $pmSchedule->actual_date) {
            $executionDate = Carbon::parse($pmSchedule->actual_date);
        }

        $nextPm = $executionDate
            ? $executionDate->copy()
            : null;

        if ($nextPm) {

            $unit = strtolower(trim($pmSchedule->machine->pm_cycle_unit));
            $value = (int) $pmSchedule->machine->pm_cycle_value;

            match ($unit) {
                'day' => $nextPm->addDays($value),
                'week' => $nextPm->addWeeks($value),
                'month' => $nextPm->addMonths($value),
                'hour' => $nextPm->addHours($value),
                default => null,
            };
        }

        $spareCost = PMSparepart::with('sparepart')
            ->where('pm_schedule_id', $pmSchedule->id)
            ->get()
            ->sum(function ($item) {
                return ($item->qty ?? 0) * ($item->sparepart->price ?? 0);
            });

        return view(
            'pm-schedules.checklist',
            compact(
                'pmSchedule',
                'checklists',
                'pmChecklists',
                'nextPm',
                'spareCost',
                'executionDate'
            )
        );
    }

    public function saveChecklist(Request $request, PMSchedule $pmSchedule)
    {
        $this->authorizeScheduleAccess($pmSchedule);

        $checklistService = app(PMChecklistSaveService::class);
        $errors = $checklistService->completionErrors($pmSchedule);

        if (! empty($errors)) {

            return redirect()
                ->route('pm-schedules.edit', $pmSchedule->id)
                ->with(
                    'warning',
                    'Fill PM belum lengkap : '.implode(', ', $errors)
                );

        }

        $checklistService->save($pmSchedule, (array) $request->checklists);

        return redirect()
            ->route('pm-schedules.index')
            ->with('success', 'PM Checklist saved successfully');

    }

    public function exportPdf(PMSchedule $pmSchedule)
    {
        $this->authorizeScheduleAccess($pmSchedule);

        $pmSchedule->load([
            'measurements',
            'problems.machineProblem',
            'problems.machineProblemFinding',
            'spareparts.sparepart',
            'checklists.machineChecklist',
        ]);

        $totalCost = $pmSchedule->spareparts->sum(
            fn ($item) => ($item->qty ?? 0) * ($item->sparepart->price ?? 0)
        );

        $checklistSections = $pmSchedule->checklists->groupBy(
            fn ($item) => $item->machineChecklist->section ?? 'Others'
        );

        $statusLabel = $pmSchedule->status
            ? ucwords(strtolower(str_replace('_', ' ', $pmSchedule->status)))
            : '-';

        $formatDate = fn ($date) => $date ? Carbon::parse($date)->format('d-m-Y') : '-';

        try {
            $pdf = Pdf::loadView('pm-schedules.pdf', [
                'pmSchedule' => $pmSchedule,
                'totalCost' => $totalCost,
                'checklistSections' => $checklistSections,
                'statusLabel' => $statusLabel,
                'planDate' => $formatDate($pmSchedule->plan_date),
                'dueDate' => $formatDate($pmSchedule->due_date),
                'actionDate' => $formatDate($pmSchedule->actual_date),
                'generatedAt' => Carbon::now()->format('d-m-Y H:i'),
            ])->setPaper('a4', 'portrait');

            // Returned as base64 inside a JSON payload (not a raw
            // application/pdf/attachment response) so that download
            // managers with aggressive browser integration (e.g. IDM),
            // which hook network responses by content-type/extension
            // rather than only link clicks, never recognize this as a
            // downloadable file and hijack it. The real PDF Blob is
            // reconstructed client-side with no further network request.
            return response()->json([
                'filename' => $this->pdfFilename($pmSchedule),
                'content' => base64_encode($pdf->output()),
            ]);
        } catch (Throwable $e) {
            Log::error('Failed to generate PM Schedule PDF', [
                'pm_schedule_id' => $pmSchedule->id,
                'error' => $e->getMessage(),
            ]);

            abort(500, 'Failed to generate PDF. Please try again later.');
        }
    }

    private function pdfFilename(PMSchedule $pmSchedule): string
    {
        $machine = $pmSchedule->machine_number
            ? preg_replace('/[^A-Za-z0-9_-]/', '', $pmSchedule->machine_number)
            : 'machine';

        $order = $pmSchedule->order_number
            ? preg_replace('/[^A-Za-z0-9_-]/', '', $pmSchedule->order_number)
            : 'NA';

        $date = $pmSchedule->plan_date
            ? Carbon::parse($pmSchedule->plan_date)->format('Y-m-d')
            : Carbon::now()->format('Y-m-d');

        return "PM_{$machine}_{$order}_{$date}.pdf";
    }

    public function assignPic(Request $request, PMSchedule $pmSchedule)
    {
        $user = auth()->user();

        // ADMIN boleh assign ke semua area
        if (! $user->isAdmin()) {
            $this->authorizeScheduleAccess($pmSchedule);
        }

        $picRole = match ($pmSchedule->area) {
            'WWD' => User::ROLE_PIC_WWD,
            'BUL' => User::ROLE_PIC_BUL,
            default => null,
        };

        abort_unless($picRole, 403);

        $request->validate([
            'pic' => [
                'nullable',
                'string',
                'max:255',
                Rule::exists('users', 'name')
                    ->where(fn ($query) => $query->where('role', $picRole)),
            ],
        ]);

        $pmSchedule->update([
            'pic' => $request->pic,
        ]);

        return response()->json([
            'success' => true,
        ]);
    }

    /**
     * Start PM Activity — records the moment the PIC begins work on this
     * schedule, using the EXISTING pm_schedules.start_time (time-of-day)
     * and pm_schedules.actual_date (date) columns. No new column, no new
     * table: "started" simply means start_time is populated while the PM is
     * not yet finished (see PMSchedule::isActiveActivity()).
     *
     * This deliberately does NOT change the Fill PM workflow. The only
     * status effect is the same forward OPEN -> IN_PROGRESS transition that
     * Fill PM's update() already performs; MISSED / IN_PROGRESS / finished
     * schedules keep their status.
     */
    public function start(Request $request, PMSchedule $pmSchedule)
    {
        $this->authorizeScheduleAccess($pmSchedule);

        $result = app(PMStartService::class)->start(
            $pmSchedule,
            $request->user(),
            $request->input('started_at'),
            $request->boolean('confirm_end_start')
        );

        return match ($result['outcome']) {
            PMStartService::OUTCOME_ALREADY_FINISHED => back()->with('warning', 'This PM is already finished.'),
            PMStartService::OUTCOME_ALREADY_STARTED => back()->with('warning', 'This PM activity has already been started.'),
            PMStartService::OUTCOME_ACTIVITY_CONFLICT => back()->with('activity_conflict', $this->activityConflictPayload(
                $result['conflict'],
                route('pm-schedules.start', $pmSchedule),
                $result['requested_started_at'],
            )),
            PMStartService::OUTCOME_STARTED => back()->with('success', 'PM activity started at '.$result['started_at']->format('d M Y H:i').'.'),
        };
    }

    private function authorizeScheduleAccess(PMSchedule $pmSchedule): void
    {
        abort_unless($pmSchedule->isAccessibleBy(auth()->user()), 403);
    }

    public function destroy(PMSchedule $pmSchedule)
    {
        $this->authorizeScheduleAccess($pmSchedule);
        $pmSchedule->delete();

        return back()->with('success', 'PM Schedule deleted');
    }
}
