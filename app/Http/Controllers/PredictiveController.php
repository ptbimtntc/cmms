<?php

namespace App\Http\Controllers;

use App\Exceptions\PredictiveWorkflowException;
use App\Http\Controllers\Concerns\HandlesActivityConflict;
use App\Imports\PredictiveWorkOrderImport;
use App\Models\ActivityMonitorClosure;
use App\Models\Machine;
use App\Models\PredictiveProblemOption;
use App\Models\PredictiveWorkOrder;
use App\Models\User;
use App\Services\ActiveActivityResolver;
use App\Services\Predictive\PredictiveDashboardService;
use App\Services\Predictive\PredictiveWorkOrderService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;

class PredictiveController extends Controller
{
    use HandlesActivityConflict;

    /** URL tab key => WO category. The third tab (dashboard) has no category. */
    private const TABS = [
        'vibration' => PredictiveWorkOrder::CATEGORY_VIBRATION,
        'air-leaked' => PredictiveWorkOrder::CATEGORY_AIR_LEAKED,
    ];

    public function __construct(
        private readonly PredictiveWorkOrderService $service,
        private readonly PredictiveDashboardService $dashboard,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $tab = $request->query('tab', 'vibration');
        $tab = ($tab === 'dashboard' || isset(self::TABS[$tab])) ? $tab : 'vibration';

        $openByCategory = PredictiveWorkOrder::query()
            ->visibleToUser($user)
            ->where('status', PredictiveWorkOrder::STATUS_OPEN)
            ->selectRaw('category, COUNT(*) as total')
            ->groupBy('category')
            ->pluck('total', 'category');

        $data = [
            'tab' => $tab,
            'tabs' => [
                'vibration' => ['label' => 'Action Predictive Vibration', 'open' => (int) ($openByCategory[PredictiveWorkOrder::CATEGORY_VIBRATION] ?? 0)],
                'air-leaked' => ['label' => 'Action Predictive Air Leaked', 'open' => (int) ($openByCategory[PredictiveWorkOrder::CATEGORY_AIR_LEAKED] ?? 0)],
                'dashboard' => ['label' => 'Dashboard', 'open' => null],
            ],
            'canManage' => $this->canManage($user),
            'canWork' => $this->canWork($user),
            'promptStart' => $user->isPic() && ! $this->predictiveIsCurrent($user),
            'areaOptions' => $user->seesAllAreas() ? $user->selectableAreaNames() : collect(),
        ];

        if ($tab === 'dashboard') {
            return view('predictive.index', $data + $this->dashboardData($request, $user));
        }

        $category = self::TABS[$tab];
        $workOrders = $this->listQuery($request, $user, $category)
            ->with('pic:id,name')
            ->withCount(['actions', 'reopens'])
            ->orderByRaw("CASE WHEN status = 'OPEN' THEN 0 ELSE 1 END")
            ->orderBy('due_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('predictive.index', $data + [
            'actionOptions' => $this->actionOptions($user, $category, $user->area?->name),
            'actionContext' => ['category' => PredictiveWorkOrder::CATEGORIES[$category], 'area' => $user->area?->name],
            'category' => $category,
            'categoryLabel' => PredictiveWorkOrder::CATEGORIES[$category],
            'workOrders' => $workOrders,
        ]);
    }

    public function show(Request $request, PredictiveWorkOrder $workOrder): View
    {
        $user = $request->user();
        $this->authorizeVisible($workOrder, $user);

        $workOrder->load(['pic:id,name', 'actions.pic:id,name', 'actions.problems.findings', 'reopens']);

        // One history. The service guarantees strict alternation (Action 1,
        // Reopen 1, Action 2, Reopen 2, ...), so the cycle number gives an
        // exact order even when two events share a timestamp.
        $history = $workOrder->actions
            ->map(fn ($a) => ['type' => 'action', 'at' => $a->actioned_at, 'model' => $a, 'seq' => $a->action_number * 2 - 1])
            ->concat($workOrder->reopens->values()->map(fn ($r, $i) => ['type' => 'reopen', 'at' => $r->reopened_at, 'model' => $r, 'seq' => ($i + 1) * 2]))
            ->sortBy('seq')
            ->values();

        return view('predictive.show', [
            'canWork' => $this->canWork($user),
            'workOrder' => $workOrder,
            'actionOptions' => $this->actionOptions($user, $workOrder->category, $workOrder->area),
            'actionContext' => ['category' => $workOrder->categoryLabel(), 'area' => $workOrder->area],
            'history' => $history,
            'tab' => $workOrder->category === PredictiveWorkOrder::CATEGORY_AIR_LEAKED ? 'air-leaked' : 'vibration',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($this->canManage($user), 403, 'Unauthorized');

        $data = $request->validate([
            'category' => ['required', Rule::in(array_keys(PredictiveWorkOrder::CATEGORIES))],
            'order_number' => ['required', 'string', 'max:100', 'unique:predictive_work_orders,order_number'],
            'machine_number' => ['required', 'string', 'max:255'],
            'wo_date' => ['required', 'date_format:Y-m-d'],
            'problem_description' => ['required', 'string', 'max:2000'],
            'due_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:wo_date'],
        ], $this->messages());

        $data['due_date'] = $data['due_date'] ?? PredictiveWorkOrder::defaultDueDate($data['wo_date']);

        $machine = Machine::where('machine_number', $data['machine_number'])->first();

        if (! $machine) {
            return back()->withInput()->withErrors(['machine_number' => 'Machine Number not found in Machine Master.']);
        }

        if (! $user->isAdmin() && $user->area?->name !== $machine->area) {
            return back()->withInput()->withErrors(['machine_number' => 'This machine is outside your area.']);
        }

        $this->service->create($data['category'], $machine, $data, $user);

        return redirect()
            ->route('predictive.index', ['tab' => $this->tabFor($data['category'])])
            ->with('success', "WO {$data['order_number']} berhasil ditambahkan.");
    }

    public function import(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($this->canManage($user), 403, 'Unauthorized');

        $request->validate([
            'category' => ['required', Rule::in(array_keys(PredictiveWorkOrder::CATEGORIES))],
            'file' => ['required', 'file', 'max:5120', 'mimes:xlsx,xls,csv,txt'],
        ]);

        $category = $request->input('category');
        $import = new PredictiveWorkOrderImport($category, $user, $this->service);

        try {
            Excel::import($import, $request->file('file'));
        } catch (\Throwable $e) {
            report($e);

            return redirect()
                ->route('predictive.index', ['tab' => $this->tabFor($category)])
                ->with('error', 'The import could not be read. Check the file format (.xlsx, .xls, or .csv) and the column headers.');
        }

        return redirect()
            ->route('predictive.index', ['tab' => $this->tabFor($category)])
            ->with('predictive_import_result', [
                'success' => $import->success,
                'failed' => $import->failed,
                'skipped' => $import->skipped,
                'details' => $import->details,
                'hidden' => $import->hiddenDetails,
                'fatal' => $import->fatalError,
            ]);
    }

    /** Type-ahead for the Add WO form. Shows only machines the user may create WOs for. */
    public function machines(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($this->canManage($user), 403, 'Unauthorized');

        $term = trim((string) $request->query('q', ''));
        abort_if($term === '', 422);

        $machines = Machine::query()
            ->when(! $user->isAdmin(), fn (Builder $q) => $q->where('area', $user->area?->name ?? ''))
            ->where('machine_number', 'like', '%'.addcslashes($term, '%_\\').'%')
            ->orderBy('machine_number')
            ->limit(15)
            ->get(['machine_number', 'area', 'machine_type']);

        return response()->json($machines);
    }

    public function update(Request $request, PredictiveWorkOrder $workOrder): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeManage($workOrder, $user);

        $data = $request->validate([
            'order_number' => ['required', 'string', 'max:100', Rule::unique('predictive_work_orders', 'order_number')->ignore($workOrder->id)],
            'machine_number' => ['required', 'string', 'max:255'],
            'wo_date' => ['required', 'date_format:Y-m-d'],
            'problem_description' => ['required', 'string', 'max:2000'],
            'due_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:wo_date'],
        ], $this->messages());

        $changes = [
            'order_number' => $data['order_number'],
            'wo_date' => $data['wo_date'],
            'due_date' => $data['due_date'],
            'problem_description' => $data['problem_description'],
        ];

        if ($data['machine_number'] !== $workOrder->machine_number) {
            if ($workOrder->pic_user_id !== null || $workOrder->actions()->exists() || $workOrder->reopens()->exists()) {
                return back()->withInput()->withErrors(['machine_number' => 'Machine Number cannot be changed: the WO is already claimed or has Action history.']);
            }

            $machine = Machine::where('machine_number', $data['machine_number'])->first();

            if (! $machine) {
                return back()->withInput()->withErrors(['machine_number' => 'Machine Number not found in Machine Master.']);
            }

            if (! $user->isAdmin() && $user->area?->name !== $machine->area) {
                return back()->withInput()->withErrors(['machine_number' => 'This machine is outside your area.']);
            }

            $changes += [
                'machine_id' => $machine->id,
                'machine_number' => $machine->machine_number,
                'area' => $machine->area,
                'machine_type' => $machine->machine_type,
            ];
        }

        $workOrder->update($changes);

        return back()->with('success', "WO {$workOrder->order_number} berhasil diperbarui.");
    }

    public function destroy(Request $request, PredictiveWorkOrder $workOrder): RedirectResponse
    {
        $this->authorizeManage($workOrder, $request->user());

        $order = $workOrder->order_number;
        $tab = $this->tabFor($workOrder->category);

        // Actions and reopens go with it through the FK cascade.
        $workOrder->delete();

        return redirect()->route('predictive.index', ['tab' => $tab])->with('success', "WO {$order} dihapus.");
    }

    /**
     * START ACTION PREDICTIVE (daily Start prompt). Mirrors
     * OilAuditController::startDailyAction: a no-op while Predictive is already
     * this PIC's current activity, otherwise the one-active-activity-per-PIC
     * guard asks END & START when something else is running.
     */
    public function start(Request $request): RedirectResponse
    {
        $validated = $request->validate(['started_at' => ['required', 'date']]);

        $user = $request->user();
        abort_unless($user->isPic(), 403, 'Only a PIC can start the Action Predictive activity.');

        if (! $this->predictiveIsCurrent($user)) {
            $startedAt = Carbon::parse($validated['started_at']);

            if (! $request->boolean('confirm_end_start')) {
                $current = $this->activityConflictFor($user);

                if ($current) {
                    return $this->backToPredictive()->with('activity_conflict', $this->activityConflictPayload(
                        $current,
                        route('predictive.start'),
                        $startedAt,
                    ));
                }
            } else {
                $startedAt = $this->confirmedStartTime($user, $startedAt);
            }

            $user->update(['predictive_started_at' => $startedAt]);

            // Predictive has no per-record id, so a closure from an earlier instance today
            // (finished, then restarted) must go or the new start would look finished.
            ActivityMonitorClosure::where('source', 'PREDICTIVE')
                ->where('source_key', (string) $user->id)
                ->whereDate('business_date', today())
                ->delete();
        }

        return $this->backToPredictive()->with('success', 'Aktivitas Action Predictive dimulai.');
    }

    public function claim(Request $request, PredictiveWorkOrder $workOrder): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeVisible($workOrder, $user);
        abort_unless($user->isPic(), 403, 'Only a PIC can claim a WO.');

        if (! $this->predictiveIsCurrent($user)) {
            return back()->with('error', self::START_FIRST);
        }

        try {
            $this->service->claim($workOrder, $user);
        } catch (PredictiveWorkflowException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "WO {$workOrder->order_number} berhasil di-claim. Silakan lanjut ke Action / Closing.");
    }

    public function storeAction(Request $request, PredictiveWorkOrder $workOrder): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeVisible($workOrder, $user);
        abort_unless($user->isPic(), 403, 'Only a PIC can save an Action.');

        if ($workOrder->isOpen() && ! $workOrder->isClaimedBy($user)) {
            abort(403, 'Only the PIC who claimed this WO can save an Action.');
        }

        if (! $this->predictiveIsCurrent($user)) {
            return back()->withInput()->with('error', self::START_FIRST);
        }

        $data = $this->service->validateAction($workOrder, $request->all());

        try {
            $action = $this->service->close($workOrder, $user, $data);
        } catch (PredictiveWorkflowException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Action #{$action->action_number} tersimpan. WO {$workOrder->order_number} sekarang CLOSED.");
    }

    public function reopen(Request $request, PredictiveWorkOrder $workOrder): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeVisible($workOrder, $user);
        abort_unless($user->isAdmin() || $user->isKoordinator(), 403, 'Only an Admin or Koordinator can reopen a WO.');

        try {
            $this->service->reopen($workOrder, $user);
        } catch (PredictiveWorkflowException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "WO {$workOrder->order_number} dibuka kembali dan tersedia untuk di-claim PIC.");
    }

    /** @return array<string, string> */
    private function messages(): array
    {
        return [
            'order_number.unique' => 'That Order Number is already used by another WO.',
            'due_date.after_or_equal' => 'The Due Date cannot be earlier than the Date.',
        ];
    }

    private function authorizeManage(PredictiveWorkOrder $workOrder, User $user): void
    {
        abort_unless($this->canManage($user) && $workOrder->isManageableBy($user), 403, 'Unauthorized');
    }

    /**
     * Problem > Finding choices the Action form offers: active master entries
     * for the WO's category and area. Only PICs can save an Action, so nobody
     * else gets the list.
     *
     * @return list<array{id: int, name: string, findings: list<array{id: int, name: string}>}>
     */
    private function actionOptions(User $user, string $category, ?string $area): array
    {
        if (! $user->isPic() || $area === null) {
            return [];
        }

        return PredictiveProblemOption::query()
            ->active()
            ->forScope($category, $area)
            ->with(['findings' => fn ($q) => $q->where('is_active', true)])
            ->orderBy('name')
            ->get()
            ->filter(fn ($p) => $p->findings->isNotEmpty())
            ->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'findings' => $p->findings->map(fn ($f) => ['id' => $f->id, 'name' => $f->name])->values()->all(),
            ])
            ->values()
            ->all();
    }

    private const START_FIRST = 'Start the Action Predictive activity before Claim or Action / Closing.';

    /** Predictive is this PIC's one current activity (started today and not ended or replaced). */
    private function predictiveIsCurrent(User $user): bool
    {
        return app(ActiveActivityResolver::class)->currentFor($user)?->source === 'PREDICTIVE';
    }

    /** Whether the buttons that need an active Predictive activity (Claim, Action) are usable. Only PICs are gated. */
    private function canWork(User $user): bool
    {
        return ! $user->isPic() || $this->predictiveIsCurrent($user);
    }

    private function backToPredictive(): RedirectResponse
    {
        return redirect()->back(302, [], route('predictive.index'));
    }

    private function canManage(User $user): bool
    {
        return $user->isAdmin() || ($user->isKoordinator() && $user->area !== null);
    }

    private function authorizeVisible(PredictiveWorkOrder $workOrder, User $user): void
    {
        abort_unless($workOrder->isVisibleTo($user), 403, 'Unauthorized');
    }

    private function tabFor(string $category): string
    {
        return array_search($category, self::TABS, true);
    }

    private function listQuery(Request $request, User $user, string $category): Builder
    {
        $adminArea = $user->seesAllAreas() ? $request->query('area') : null;

        $query = PredictiveWorkOrder::query()
            ->visibleToUser($user, is_string($adminArea) && $adminArea !== '' ? $adminArea : null)
            ->where('category', $category);

        if (is_string($search = $request->query('search')) && trim($search) !== '') {
            $like = '%'.addcslashes(trim($search), '%_\\').'%';
            $query->where(function (Builder $q) use ($like) {
                $q->where('order_number', 'like', $like)
                    ->orWhere('machine_number', 'like', $like)
                    ->orWhere('machine_type', 'like', $like)
                    ->orWhere('problem_description', 'like', $like);
            });
        }

        if (in_array($status = $request->query('status'), [PredictiveWorkOrder::STATUS_OPEN, PredictiveWorkOrder::STATUS_CLOSED], true)) {
            $query->where('status', $status);
        }

        match ($request->query('claim')) {
            'unclaimed' => $query->where('status', PredictiveWorkOrder::STATUS_OPEN)->whereNull('pic_user_id'),
            'mine' => $query->where('pic_user_id', $user->id),
            default => null,
        };

        foreach (['date_from' => '>=', 'date_to' => '<='] as $key => $operator) {
            $value = $request->query($key);
            if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) && strtotime($value) !== false) {
                $query->whereDate('wo_date', $operator, $value);
            }
        }

        return $query;
    }

    /** @return array<string, mixed> */
    private function dashboardData(Request $request, User $user): array
    {
        $filters = $request->only(['date_from', 'date_to', 'category', 'area', 'machine_type', 'pic']);
        $filters = array_map(fn ($v) => is_scalar($v) ? (string) $v : null, $filters);
        if (! $user->seesAllAreas()) {
            $filters['area'] = null;
        }

        $workOrders = $this->dashboard->workOrders($user, $filters);

        $machineTypes = PredictiveWorkOrder::query()
            ->visibleToUser($user)
            ->whereNotNull('machine_type')
            ->distinct()
            ->orderBy('machine_type')
            ->pluck('machine_type');

        $pics = User::query()
            ->where('role', User::ROLE_PIC)
            ->when(! $user->seesAllAreas(), fn (Builder $q) => $q->where('area_id', $user->area_id))
            ->orderBy('name')
            ->get(['id', 'name']);

        return [
            'filters' => $filters,
            'overview' => $this->dashboard->overview($workOrders),
            'findings' => $this->dashboard->findingSummary($workOrders),
            'problems' => $this->dashboard->problemSummary($workOrders),
            'machineTypes' => $machineTypes,
            'pics' => $pics,
        ];
    }
}
