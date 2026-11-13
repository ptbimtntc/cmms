<?php

namespace App\Http\Controllers;

use App\Exceptions\SmallPmWorkflowException;
use App\Http\Controllers\Concerns\HandlesActivityConflict;
use App\Imports\SmallPmScheduleImport;
use App\Models\Area;
use App\Models\SmallPmFinding;
use App\Models\SmallPmRow;
use App\Models\SmallPmSchedule;
use App\Models\User;
use App\Services\SmallPm\SmallPmScheduler;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Small PM schedules, one tab per kind of work. Works like
 * GreasingController (add / import / assign PIC / start / execute), plus
 * "Generate" (4 rows a week) and an automatic next cycle on execution.
 */
class SmallPmController extends Controller
{
    use HandlesActivityConflict;

    /** URL tab => task_type, available */
    public const TABS = [
        'cleaning-motor' => ['task' => SmallPmRow::TASK_CLEANING_MOTOR, 'available' => true],
        'filter-panel' => ['task' => SmallPmRow::TASK_FILTER_PANEL, 'available' => true],
    ];

    /** Every dead-blower finding of every Ganti Filter Panel schedule, in one list. */
    public const FINDINGS_TAB = 'temuan-blower';

    public function __construct(private readonly SmallPmScheduler $scheduler) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $tab = self::resolveTab($request->query('tab'));
        $tabs = collect(self::TABS)->map(fn (array $t) => ['label' => SmallPmRow::TASK_TYPES[$t['task']], 'available' => $t['available'], 'count' => null])->all();
        $tabs[self::FINDINGS_TAB] = [
            'label' => 'Dead Blower Findings',
            'available' => true,
            'count' => SmallPmFinding::where('status', 'OPEN')->whereHas('schedule', fn ($q) => $q->visibleToUser($user))->count(),
        ];
        $canManage = $user->isAdmin() || $user->isKoordinator();

        if ($tab === self::FINDINGS_TAB) {
            return $this->findingsTab($request, compact('tab', 'tabs', 'canManage'));
        }

        if (! self::TABS[$tab]['available']) {
            return view('small-pm.index', compact('tab', 'tabs', 'canManage') + ['available' => false]);
        }

        $taskType = self::TABS[$tab]['task'];

        $query = SmallPmSchedule::query()
            ->with(['row.area', 'pic:id,name'])
            ->when($taskType === SmallPmRow::TASK_FILTER_PANEL, fn ($q) => $q->withCount([
                'findings',
                'findings as open_findings_count' => fn ($f) => $f->where('status', 'OPEN'),
            ]))
            ->where('task_type', $taskType)
            ->visibleToUser($user);

        if ($request->filled('search')) {
            $search = '%'.$request->search.'%';
            $query->where(fn ($q) => $q
                ->where('order_number', 'like', $search)
                ->orWhere('cycle', 'like', $search)
                ->orWhereHas('pic', fn ($p) => $p->where('name', 'like', $search)));
        }

        if ($request->filled('row_id')) {
            $query->where('small_pm_row_id', $request->row_id);
        }

        if ($request->filled('status')) {
            $query->whereIn('status', (array) $request->status);
        }

        $schedules = $query->orderBy('plan_date')->orderBy('id')->paginate(20)->withQueryString();

        $manageAreaIds = $this->manageableAreaIds($user);

        return view('small-pm.index', compact('tab', 'tabs', 'canManage', 'schedules') + [
            'available' => true,
            'rows' => SmallPmRow::query()
                ->when(! $user->seesAllAreas(), fn ($q) => $q->where('area_id', $user->area_id))
                ->orderBy('sort_order')->orderBy('name')->get(['id', 'name']),
            'picsByArea' => $canManage ? $this->picsByArea() : collect(),
            'unscheduledCount' => $canManage ? $this->scheduler->unscheduledRows($taskType, $manageAreaIds)->count() : 0,
            'rowsPerWeek' => $this->scheduler->rowsPerWeek(),
            'defaultCycle' => self::defaultCycle($taskType),
            'hasFindings' => $taskType === SmallPmRow::TASK_FILTER_PANEL,
            'thisMonday' => SmallPmScheduler::monday(now()),
        ]);
    }

    public function create(Request $request)
    {
        $tab = $this->availableTab($request->query('tab'));

        return view('small-pm.create', [
            'tab' => $tab,
            'tabLabel' => SmallPmRow::TASK_TYPES[self::TABS[$tab]['task']],
            'rows' => $this->manageableRows($request->user()),
            'picsByArea' => $this->picsByArea(),
            'defaultCycle' => self::defaultCycle(self::TABS[$tab]['task']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tab = $this->availableTab($request->input('tab'));
        $validated = $this->validateSchedule($request);

        if ($this->isDuplicate($validated)) {
            return back()->withErrors(['order_number' => 'A schedule with the same Row, Plan Date, and Order Number already exists.'])->withInput();
        }

        SmallPmSchedule::create([
            'small_pm_row_id' => $validated['small_pm_row_id'],
            'task_type' => self::TABS[$tab]['task'],
            'order_number' => $validated['order_number'] ?? null,
            'cycle' => strtoupper($validated['cycle']),
            'plan_date' => $validated['plan_date'],
            'due_date' => SmallPmSchedule::calculateDueDate($validated['plan_date']),
            'pic_user_id' => $validated['pic_user_id'] ?? null,
            'status' => 'OPEN',
            'remarks' => $validated['remarks'] ?? null,
        ]);

        return redirect()->route('small-pm.index', ['tab' => $tab])->with('success', 'Small PM schedule added.');
    }

    public function edit(Request $request, SmallPmSchedule $schedule)
    {
        $this->authorizeManage($request->user(), $schedule);

        return view('small-pm.edit', [
            'schedule' => $schedule,
            'tab' => $this->tabFor($schedule),
            'rows' => $this->manageableRows($request->user()),
            'picsByArea' => $this->picsByArea(),
        ]);
    }

    public function update(Request $request, SmallPmSchedule $schedule): RedirectResponse
    {
        $this->authorizeManage($request->user(), $schedule);
        $validated = $this->validateSchedule($request);

        if ($this->isDuplicate($validated, $schedule->id)) {
            return back()->withErrors(['order_number' => 'A schedule with the same Row, Plan Date, and Order Number already exists.'])->withInput();
        }

        $dueDate = SmallPmSchedule::calculateDueDate($validated['plan_date']);

        $schedule->update([
            'small_pm_row_id' => $validated['small_pm_row_id'],
            'order_number' => $validated['order_number'] ?? null,
            'cycle' => strtoupper($validated['cycle']),
            'plan_date' => $validated['plan_date'],
            'due_date' => $dueDate,
            'pic_user_id' => $validated['pic_user_id'] ?? null,
            'status' => SmallPmSchedule::resolveStatus($schedule->action_date, $dueDate),
            'remarks' => $validated['remarks'] ?? $schedule->remarks,
        ]);

        return redirect()
            ->route('small-pm.index', ['tab' => $this->tabFor($schedule), 'page' => $request->input('return_page')])
            ->with('success', 'Small PM schedule saved.');
    }

    public function destroy(Request $request, SmallPmSchedule $schedule): RedirectResponse
    {
        $this->authorizeManage($request->user(), $schedule);
        $schedule->delete();

        return back()->with('success', 'Small PM schedule deleted.');
    }

    public function import(Request $request): RedirectResponse
    {
        $tab = $this->availableTab($request->input('tab'));

        $request->validate([
            'file' => ['required', 'file', 'max:20480', 'mimes:csv,txt,xlsx,xls'],
        ], [], ['file' => 'file']);

        try {
            Excel::import(new SmallPmScheduleImport(self::TABS[$tab]['task'], $request->user()), $request->file('file'));
        } catch (\Throwable $e) {
            report($e);

            return back()->withErrors(['file' => 'Import failed. Check the column format and the file contents.']);
        }

        return back()->with('success', 'Small PM schedule import finished.');
    }

    public function generate(Request $request): RedirectResponse
    {
        $tab = $this->availableTab($request->input('tab'));

        $validated = $request->validate([
            'start_week' => ['required', 'date_format:Y-m-d'],
            'cycle' => ['required', 'string', 'regex:/^\d+W$/i'],
        ], [], ['start_week' => 'minggu awal', 'cycle' => 'cycle']);

        try {
            $created = $this->scheduler->generate(
                self::TABS[$tab]['task'],
                SmallPmScheduler::monday($validated['start_week']),
                $validated['cycle'],
                $this->manageableAreaIds($request->user()),
            );
        } catch (SmallPmWorkflowException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $weeks = $this->scheduler->weeksFor($created->count());

        return redirect()->route('small-pm.index', ['tab' => $tab])
            ->with('success', "{$created->count()} row dijadwalkan dalam {$weeks} minggu.");
    }

    /** AJAX from the index dropdown, same contract as GreasingController::assignPic(). */
    public function assignPic(Request $request, SmallPmSchedule $schedule): JsonResponse
    {
        if (! $this->canManage($request->user(), $schedule)) {
            return response()->json(['success' => false, 'message' => 'You may not set the PIC in this area.'], 403);
        }

        $areaId = $schedule->row?->area_id;

        if ($areaId === null) {
            return response()->json(['success' => false, 'message' => 'This row has no Area yet. Set one first in Small PM Rows.'], 422);
        }

        $requested = $request->input('pic_user_id');
        $requested = $requested === '' ? null : $requested;

        $validator = validator(['pic_user_id' => $requested], [
            'pic_user_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('role', User::ROLE_PIC)->where('area_id', $areaId)],
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $schedule->update(['pic_user_id' => $validator->validated()['pic_user_id']]);

        return response()->json(['success' => true]);
    }

    /**
     * Start Activity, like GreasingController::start(): writes start_time
     * only. An unassigned schedule becomes the starting PIC's.
     */
    public function start(Request $request, SmallPmSchedule $schedule): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->isPic() && $schedule->isAccessibleBy($user), 403);

        if ($schedule->isDone()) {
            return back()->with('warning', 'This schedule is already finished.');
        }

        if (filled($schedule->start_time)) {
            return back()->with('warning', 'This schedule has already started.');
        }

        $validated = $request->validate(['started_at' => ['required', 'date']]);
        $startedAt = Carbon::parse($validated['started_at']);

        if (! $request->boolean('confirm_end_start')) {
            $current = $this->activityConflictFor($user);

            if ($current) {
                return back()->with('activity_conflict', $this->activityConflictPayload(
                    $current,
                    route('small-pm.start', $schedule),
                    $startedAt,
                ));
            }
        } else {
            $startedAt = $this->confirmedStartTime($user, $startedAt);
        }

        $schedule->update(['start_time' => $startedAt, 'pic_user_id' => $schedule->pic_user_id ?? $user->id]);

        return back()->with('success', 'Small PM '.$schedule->row?->name.' dimulai '.$startedAt->format('d M Y H:i').'.');
    }

    public function execute(Request $request, SmallPmSchedule $schedule)
    {
        $this->authorizeExecute($request->user(), $schedule);

        $schedule->load(['row.area', 'pic:id,name', 'findings' => fn ($q) => $q->with('machine:id,machine_number')->orderBy('id')]);

        return view('small-pm.execute', [
            'schedule' => $schedule,
            'tab' => $this->tabFor($schedule),
            'machines' => $schedule->row?->machines()->orderBy('machine_number')->get(['id', 'machine_number', 'machine_type']) ?? collect(),
            'next' => SmallPmSchedule::where('generated_from_id', $schedule->id)->first(),
        ]);
    }

    public function storeExecution(Request $request, SmallPmSchedule $schedule): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeExecute($user, $schedule);

        $validated = $request->validate([
            'action_date' => 'required|date',
            'remarks' => 'nullable|string|max:2000',
            'dead_machine_ids' => ['nullable', 'array'],
            'dead_machine_ids.*' => ['integer', 'distinct', Rule::exists('machines', 'id')->where('small_pm_row_id', $schedule->small_pm_row_id)],
        ], [
            'dead_machine_ids.*.exists' => 'A dead filter blower machine must come from this row.',
        ]);

        $next = DB::transaction(function () use ($schedule, $validated, $user) {
            if ($schedule->hasFindings()) {
                $this->syncDeadBlowers($schedule, array_map('intval', $validated['dead_machine_ids'] ?? []));
            }

            $schedule->update([
                'action_date' => $validated['action_date'],
                'remarks' => $validated['remarks'] ?? null,
                'status' => SmallPmSchedule::resolveStatus($validated['action_date'], $schedule->due_date),
                'pic_user_id' => $schedule->pic_user_id ?? ($user->isPic() ? $user->id : null),
            ]);

            return $this->scheduler->planNext($schedule->fresh());
        });

        return redirect()
            ->route('small-pm.index', ['tab' => $this->tabFor($schedule), 'page' => $request->input('return_page')])
            ->with('success', 'Execution saved.'.($next ? ' Next schedule: '.$next->plan_date->format('d M Y').'.' : ''));
    }

    /** @param  array<string, mixed>  $shared */
    private function findingsTab(Request $request, array $shared)
    {
        $user = $request->user();

        $query = SmallPmFinding::query()
            ->with(['machine:id,machine_number,machine_type', 'schedule.row:id,name'])
            ->whereHas('schedule', fn ($q) => $q->where('task_type', SmallPmRow::TASK_FILTER_PANEL)->visibleToUser($user));

        if ($request->filled('search')) {
            $query->whereHas('machine', fn ($q) => $q->where('machine_number', 'like', '%'.$request->search.'%'));
        }

        if ($request->filled('row_id')) {
            $query->whereHas('schedule', fn ($q) => $q->where('small_pm_row_id', $request->row_id));
        }

        if ($request->filled('status')) {
            $query->whereIn('status', (array) $request->status);
        }

        // OPEN first, newest first inside each status.
        $findings = $query->orderByRaw("CASE WHEN status = 'OPEN' THEN 0 ELSE 1 END")
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('small-pm.index', $shared + [
            'available' => true,
            'findings' => $findings,
            'rows' => SmallPmRow::query()
                ->when(! $user->seesAllAreas(), fn ($q) => $q->where('area_id', $user->area_id))
                ->orderBy('sort_order')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * Close a dead-blower finding by recording the action (COMPLETED), or,
     * for admin / koordinator, reopen it (status=OPEN). Same idea as
     * GreasingController::updateFinding().
     */
    public function updateFinding(Request $request, SmallPmSchedule $schedule, SmallPmFinding $finding): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeExecute($user, $schedule);
        abort_unless($finding->small_pm_schedule_id === $schedule->id, 404);

        if ($request->input('status') === 'OPEN') {
            abort_unless($this->canManage($user, $schedule), 403);
            $finding->update(['status' => 'OPEN', 'action_date' => null]);

            return back()->with('success', 'Temuan '.$finding->machine?->machine_number.' dibuka lagi.');
        }

        $validated = $request->validate([
            'action' => ['required', 'string', 'max:2000'],
            'action_date' => ['nullable', 'date'],
        ], [], ['action' => 'tindakan']);

        $finding->update([
            'status' => 'COMPLETED',
            'action' => $validated['action'],
            'action_date' => $validated['action_date'] ?? now()->toDateString(),
        ]);

        return back()->with('success', 'Temuan '.$finding->machine?->machine_number.' ditutup.');
    }

    /**
     * Ticked machines become OPEN findings; an OPEN finding whose machine was
     * unticked is removed. COMPLETED findings stay, they are history.
     *
     * @param  list<int>  $machineIds
     */
    private function syncDeadBlowers(SmallPmSchedule $schedule, array $machineIds): void
    {
        $existing = $schedule->findings()->get()->keyBy('machine_id');

        foreach ($machineIds as $id) {
            if (! $existing->has($id)) {
                $schedule->findings()->create(['machine_id' => $id, 'status' => 'OPEN']);
            }
        }

        $schedule->findings()
            ->where('status', 'OPEN')
            ->whereNotIn('machine_id', $machineIds ?: [0])
            ->delete();
    }

    public static function defaultCycle(string $taskType): string
    {
        $cycle = config('small_pm.default_cycle', '26W');

        return is_array($cycle) ? ($cycle[$taskType] ?? '26W') : (string) $cycle;
    }

    public static function resolveTab(?string $tab): string
    {
        return array_key_exists((string) $tab, self::TABS) || $tab === self::FINDINGS_TAB
            ? (string) $tab
            : array_key_first(self::TABS);
    }

    /** A schedule tab (not the findings tab) that is available. */
    private function availableTab(?string $tab): string
    {
        $tab = self::resolveTab($tab);
        abort_unless(isset(self::TABS[$tab]) && self::TABS[$tab]['available'], 404);

        return $tab;
    }

    private function tabFor(SmallPmSchedule $schedule): string
    {
        return (string) collect(self::TABS)->search(fn (array $t) => $t['task'] === $schedule->task_type) ?: array_key_first(self::TABS);
    }

    /** @return array<string, mixed> */
    private function validateSchedule(Request $request): array
    {
        $user = $request->user();
        $rowIds = $this->manageableRows($user)->pluck('id')->all();

        return validator($request->all(), [
            'small_pm_row_id' => ['required', 'integer', Rule::in($rowIds)],
            'order_number' => ['nullable', 'string', 'max:255'],
            'cycle' => ['required', 'string', 'regex:/^\d+W$/i'],
            'plan_date' => ['required', 'date'],
            'pic_user_id' => ['nullable', 'integer'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ], [
            'small_pm_row_id.in' => 'Pick a row from your own area.',
            'cycle.regex' => 'Cycle ditulis dalam minggu, misalnya 26W.',
        ], [
            'small_pm_row_id' => 'row',
            'plan_date' => 'plan date',
            'pic_user_id' => 'PIC',
        ])->after(function (Validator $v) use ($request) {
            if (! $request->filled('pic_user_id') || ! $request->filled('small_pm_row_id')) {
                return;
            }

            $areaId = SmallPmRow::whereKey($request->small_pm_row_id)->value('area_id');
            $ok = User::whereKey($request->pic_user_id)->where('role', User::ROLE_PIC)->where('area_id', $areaId)->exists();

            if (! $ok) {
                $v->errors()->add('pic_user_id', 'The PIC must be a PIC user from this row area.');
            }
        })->validate();
    }

    private function isDuplicate(array $validated, ?int $ignoreId = null): bool
    {
        if (blank($validated['order_number'] ?? null)) {
            return false;
        }

        return SmallPmSchedule::query()
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->where('small_pm_row_id', $validated['small_pm_row_id'])
            ->whereDate('plan_date', $validated['plan_date'])
            ->where('order_number', $validated['order_number'])
            ->exists();
    }

    /** @return list<int>|null null = every area (admin without restriction) */
    private function manageableAreaIds(User $user): ?array
    {
        if ($user->isAdmin()) {
            return null;
        }

        return $user->area_id ? [$user->area_id] : [];
    }

    private function manageableRows(User $user): Collection
    {
        $areaIds = $this->manageableAreaIds($user);

        return SmallPmRow::query()
            ->with('area:id,name')
            ->when($areaIds !== null, fn ($q) => $q->whereIn('area_id', $areaIds))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    private function canManage(User $user, SmallPmSchedule $schedule): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->isKoordinator() && $user->area_id && $schedule->row?->area_id === (int) $user->area_id;
    }

    private function authorizeManage(User $user, SmallPmSchedule $schedule): void
    {
        abort_unless($this->canManage($user, $schedule), 403);
    }

    private function authorizeExecute(User $user, SmallPmSchedule $schedule): void
    {
        abort_if($user->isSupervisor(), 403);
        abort_unless($schedule->isAccessibleBy($user), 403);
    }

    /** @return Collection<int, Collection<int, User>> area_id => PICs */
    private function picsByArea(): Collection
    {
        return Area::active()->orderBy('name')->get()
            ->mapWithKeys(fn (Area $area) => [
                $area->id => User::where('role', User::ROLE_PIC)->where('area_id', $area->id)->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            ]);
    }
}
