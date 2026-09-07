<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesActivityConflict;
use App\Imports\GreasingScheduleImport;
use App\Models\Greasing;
use App\Models\GreasingFinding;
use App\Models\Group;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;

class GreasingController extends Controller
{
    use HandlesActivityConflict;

    public function index(Request $request)
    {
        $query = Greasing::with('group')->withCount('findings');

        $user = auth()->user();

        // Per-area authorization, same rule as PMScheduleController::index():
        // WWD roles only see WWD-group schedules, BUL roles only BUL-group,
        // and PIC roles are further limited to schedules assigned to them.
        $query->visibleToUser($user);

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('pic', 'like', '%' . $request->search . '%')
                  ->orWhere('cycle', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->filled('group_id')) {
            $query->where('group_id', $request->group_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $greasings = $query
            ->orderBy('plan_date', 'asc')
            ->paginate(20)
            ->withQueryString();

        $groups = Group::orderBy('name')->get();

        $picsByArea = [
            'WWD' => User::where('role', User::ROLE_PIC_WWD)->orderBy('name')->get(),
            'BUL' => User::where('role', User::ROLE_PIC_BUL)->orderBy('name')->get(),
        ];

        return view('greasings.index', compact('greasings', 'groups', 'picsByArea'));
    }

    public function create()
    {
        $groups = Group::orderBy('name')->get();

        return view('greasings.create', compact('groups'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'group_id' => 'required|exists:groups,id',
            'order_number' => 'required|string|max:255',
            'cycle' => ['required', 'string', 'regex:/^\d+W$/i'],
            'plan_date' => 'required|date',
            'pic' => [
                'nullable',
                'string',
                'max:255',
                Rule::exists('users', 'name')
                    ->where(fn ($query) => $query->whereIn('role', [User::ROLE_PIC_WWD, User::ROLE_PIC_BUL])),
            ],
            'remarks' => 'nullable|string',
        ]);

        // Duplicate identity for a Greasing schedule is (group_id + plan_date +
        // order_number) — the same tuple enforced by GreasingScheduleImport.
        // order_number is intentionally NOT a global unique value.
        $isDuplicate = Greasing::where('group_id', $validated['group_id'])
            ->whereDate('plan_date', $validated['plan_date'])
            ->where('order_number', $validated['order_number'])
            ->exists();

        if ($isDuplicate) {
            return back()
                ->withErrors(['order_number' => 'A greasing schedule with the same Group, Plan Date, and Order Number already exists.'])
                ->withInput();
        }

        $dueDate = Greasing::calculateDueDate($validated['plan_date']);

        Greasing::create([
            'group_id' => $validated['group_id'],
            'order_number' => $validated['order_number'],
            'cycle' => strtoupper($validated['cycle']),
            'plan_date' => $validated['plan_date'],
            'due_date' => $dueDate,
            'pic' => $validated['pic'] ?? null,
            'action_date' => null,
            'status' => 'OPEN',
            'remarks' => $validated['remarks'] ?? null,
        ]);

        return redirect()
            ->route('greasings.index')
            ->with('success', 'Greasing schedule created successfully');
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => [
                'required',
                'file',
                'max:20480', // 20 MB
                function ($attribute, $value, $fail) {
                    $extension = strtolower($value->getClientOriginalExtension());
                    $mime = strtolower($value->getMimeType());

                    $allowedExtensions = ['csv'];

                    $allowedMimes = [
                        'text/csv',
                        'text/plain',
                        'application/csv',
                        'application/vnd.ms-excel',
                    ];

                    if (
                        ! in_array($extension, $allowedExtensions, true)
                        || ! in_array($mime, $allowedMimes, true)
                    ) {
                        $fail('File must be a valid CSV file.');
                    }
                },
            ],
        ]);

        try {
            Excel::import(new GreasingScheduleImport(), $request->file('file'));
        } catch (\Throwable $e) {
            report($e);

            return back()->withErrors([
                'file' => 'Import failed. Please check the file format and data.',
            ]);
        }

        return back()->with('success', 'Greasing schedule imported successfully');
    }

    /**
     * Admin/Koordinator schedule management form. Deliberately does not
     * touch action_date/status/findings — those belong to the execution
     * flow (execute/storeExecution) only.
     */
    public function edit(Greasing $greasing)
    {
        $groups = Group::orderBy('name')->get();

        return view('greasings.edit', compact('greasing', 'groups'));
    }

    public function update(Request $request, Greasing $greasing)
    {
        $validated = $request->validate([
            'group_id' => 'required|exists:groups,id',
            'order_number' => 'required|string|max:255',
            'cycle' => ['required', 'string', 'regex:/^\d+W$/i'],
            'plan_date' => 'required|date',
            'pic' => [
                'nullable',
                'string',
                'max:255',
                Rule::exists('users', 'name')
                    ->where(fn ($query) => $query->whereIn('role', [User::ROLE_PIC_WWD, User::ROLE_PIC_BUL])),
            ],
        ]);

        // Same duplicate identity as store()/import: (group_id + plan_date +
        // order_number), excluding this record itself.
        $isDuplicate = Greasing::where('id', '!=', $greasing->id)
            ->where('group_id', $validated['group_id'])
            ->whereDate('plan_date', $validated['plan_date'])
            ->where('order_number', $validated['order_number'])
            ->exists();

        if ($isDuplicate) {
            return back()
                ->withErrors(['order_number' => 'A greasing schedule with the same Group, Plan Date, and Order Number already exists.'])
                ->withInput();
        }

        // due_date is always derived from plan_date. Since plan_date may
        // change here, status must be recalculated against the *existing*
        // action_date so it never drifts out of sync with due_date.
        $dueDate = Greasing::calculateDueDate($validated['plan_date']);
        $status = Greasing::resolveStatus($greasing->action_date, $dueDate);

        $greasing->update([
            'group_id' => $validated['group_id'],
            'order_number' => $validated['order_number'],
            'cycle' => strtoupper($validated['cycle']),
            'plan_date' => $validated['plan_date'],
            'due_date' => $dueDate,
            'pic' => $validated['pic'] ?? null,
            'status' => $status,
        ]);

        return redirect()
            ->route('greasings.index')
            ->with('success', 'Greasing schedule updated successfully');
    }

    public function destroy(Greasing $greasing)
    {
        $greasing->delete();

        return back()->with('success', 'Greasing schedule deleted');
    }

    /**
     * Quick PIC assignment from the schedule index dropdown (mirrors
     * PMScheduleController::assignPic()). The route is only reachable by
     * ADMIN/KOORDINATOR (see routes/web.php), matching the fact that the
     * dropdown itself is only rendered for those roles.
     *
     * The PIC role offered (PIC WWD vs PIC BUL) is inferred from the
     * schedule's Group name, since Group/Greasing has no area column.
     */
    public function assignPic(Request $request, Greasing $greasing)
    {
        // This endpoint is only ever called via fetch()/AJAX from the index
        // dropdown, so it must always answer in JSON — never rely on
        // Laravel's default exception-to-redirect behavior, since this
        // app's shouldRenderJsonWhen() only auto-renders JSON for /api/*.

        // Per-area authorization: a koordinator may only assign a PIC on a
        // schedule in their own area (ADMIN may assign anywhere). Mirrors
        // PMScheduleController::assignPic().
        if (! $greasing->isAccessibleBy(auth()->user())) {
            return response()->json([
                'success' => false,
                'message' => 'You are not allowed to assign a PIC for this area.',
            ], 403);
        }

        $area = $greasing->group?->inferredArea();

        if ($area === null) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot determine PIC area (WWD/BUL) from this group\'s name.',
            ], 422);
        }

        $picRole = $area === 'WWD' ? User::ROLE_PIC_WWD : User::ROLE_PIC_BUL;

        // Treat an empty selection ("Assign PIC" placeholder option) as
        // explicitly clearing the PIC, not as an invalid value.
        $requestedPic = $request->input('pic');
        $requestedPic = $requestedPic === '' ? null : $requestedPic;

        $validator = validator(['pic' => $requestedPic], [
            'pic' => [
                'nullable',
                'string',
                'max:255',
                Rule::exists('users', 'name')
                    ->where(fn ($query) => $query->where('role', $picRole)),
            ],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $greasing->update([
            'pic' => $validator->validated()['pic'],
        ]);

        return response()->json(['success' => true]);
    }

    /**
     * Start Greasing Activity — records the moment the PIC begins work on
     * this schedule into the greasings.start_time column. It writes ONLY
     * that column: scheduling (plan_date / due_date / cycle), status,
     * action_date, remarks, and findings are all left untouched because
     * Start Activity is not part of the execution / close flow.
     *
     * "Active" is then derived from existing data — start_time populated
     * while the schedule is not yet completed (Greasing::isActiveActivity()).
     */
    public function start(Request $request, Greasing $greasing)
    {
        $this->authorizeGreasingAccess($greasing);

        if (in_array($greasing->status, Greasing::DONE_STATUSES, true)) {
            return back()->with('warning', 'This greasing schedule is already completed.');
        }

        // Idempotent: keep the original start time instead of overwriting.
        if (filled($greasing->start_time)) {
            return back()->with('warning', 'This greasing activity has already been started.');
        }

        $validated = $request->validate([
            'started_at' => ['required', 'date'],
        ]);

        $startedAt = Carbon::parse($validated['started_at']);
        $user = auth()->user();

        // One active activity per PIC — checked across every activity source
        // (PM, Greasing, Oil Audit, Oil Audit Action). Unless the PIC has
        // already confirmed END & START, bounce back with the confirmation
        // payload instead of starting.
        if (! $request->boolean('confirm_end_start')) {
            $current = $this->activityConflictFor($user);

            if ($current) {
                return back()->with('activity_conflict', $this->activityConflictPayload(
                    $current,
                    route('greasings.start', $greasing),
                    $startedAt,
                ));
            }
        } else {
            $startedAt = $this->confirmedStartTime($user, $startedAt);
        }

        $greasing->update(['start_time' => $startedAt]);

        return back()->with('success', 'Greasing activity started at '.$startedAt->format('d M Y H:i').'.');
    }

    /**
     * Execution form: Action Date, Remarks, and Findings only.
     * Plan Date, Due Date, Group, and Cycle are intentionally read-only here.
     */
    public function execute(Greasing $greasing)
    {
        $this->authorizeGreasingAccess($greasing);

        $greasing->load(['group', 'findings' => fn ($q) => $q->latest('id')]);

        return view('greasings.execute', compact('greasing'));
    }

    public function storeExecution(Request $request, Greasing $greasing)
    {
        $this->authorizeGreasingAccess($greasing);

        $validated = $request->validate([
            'action_date' => 'required|date',
            'remarks' => 'nullable|string',
            'findings' => 'nullable|array',
            'findings.*' => 'nullable|string|max:1000',
        ]);

        // due_date/status are never trusted from the request — always
        // recomputed server-side from the schedule's own plan_date/due_date.
        $status = Greasing::resolveStatus($validated['action_date'], $greasing->due_date);

        $greasing->update([
            'action_date' => $validated['action_date'],
            'remarks' => $validated['remarks'] ?? null,
            'status' => $status,
        ]);

        $newFindings = collect($validated['findings'] ?? [])
            ->map(fn ($finding) => trim((string) $finding))
            ->filter()
            ->values();

        foreach ($newFindings as $finding) {
            $greasing->findings()->create([
                'finding' => $finding,
                'status' => 'OPEN',
            ]);
        }

        return redirect()
            ->route('greasings.index')
            ->with('success', 'Greasing execution saved successfully');
    }

    /**
     * Toggle a single finding's status. Deliberately does not touch the
     * parent Greasing's status — finding status and schedule status are
     * independent by design.
     */
    public function updateFinding(Request $request, Greasing $greasing, GreasingFinding $finding)
    {
        $this->authorizeGreasingAccess($greasing);

        abort_unless($finding->greasing_id === $greasing->id, 404);

        $validated = $request->validate([
            'status' => ['required', Rule::in(GreasingFinding::STATUSES)],
            'action' => 'nullable|string|max:2000',
            'action_date' => 'nullable|date',
        ]);

        $finding->update([
            'status' => $validated['status'],
            'action' => $validated['action'] ?? $finding->action,
            'action_date' => $validated['status'] === 'COMPLETED'
                ? ($validated['action_date'] ?? $finding->action_date ?? now()->toDateString())
                : ($validated['action_date'] ?? $finding->action_date),
        ]);

        return back()->with('success', 'Finding updated successfully');
    }

    /**
     * Backend authorization for the execution flow. Route-level middleware
     * only gates by role; this enforces per-record access so a PIC cannot
     * execute a schedule that is not assigned to them, even via a manual
     * request to a URL they can technically reach.
     */
    private function authorizeGreasingAccess(Greasing $greasing): void
    {
        $user = auth()->user();

        // Per-area authorization, same rule as
        // PMScheduleController::authorizeScheduleAccess(): a WWD role can
        // only touch WWD-group schedules, a BUL role only BUL-group ones,
        // and a PIC additionally only their own assigned schedules. The
        // area is derived from the linked Group's name.
        abort_unless($greasing->isAccessibleBy($user), 403);
    }
}
