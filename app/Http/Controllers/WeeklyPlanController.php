<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Models\User;
use App\Models\WeeklyPmPlan;
use App\Services\WeeklyPlan\AutoFillService;
use App\Services\WeeklyPlan\DayDefaults;
use App\Services\WeeklyPlan\EmailComposer;
use App\Services\WeeklyPlan\IndoDate;
use App\Services\WeeklyPlan\MachineFamily;
use App\Services\WeeklyPlan\PicSyncService;
use App\Services\WeeklyPlan\PlanBoardService;
use App\Services\WeeklyPlan\PlanSaveService;
use App\Services\WeeklyPlan\RealizationService;
use App\Services\WeeklyPlan\RevisionComposer;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Weekly PM planner: pick which open PM Schedule machines go on which weekday,
 * assign the PIC, note the construction, and copy the production email.
 * Plans are kept apart from pm_schedules (nothing there is changed).
 */
class WeeklyPlanController extends Controller
{
    public function __construct(
        private readonly PlanBoardService $board,
        private readonly EmailComposer $email,
        private readonly RevisionComposer $revision,
        private readonly RealizationService $realization,
        private readonly PicSyncService $picSync,
    ) {}

    /**
     * Opens a plan from the area/week picker (?area=slug&week=Y-m-d), or next
     * week's plan for the default area (WWD, see config/weekly_plan.php) when
     * nothing was picked and the user may see it, else the user's first area.
     */
    public function index(Request $request): RedirectResponse
    {
        $areas = $this->allowedAreas($request->user());
        $area = $areas->firstWhere('slug', $request->query('area'))
            ?? $areas->firstWhere('name', config('weekly_plan.default_area'))
            ?? $areas->first();
        abort_unless($area, 403, 'This account has no area yet.');

        $week = $request->filled('week') && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $request->query('week')) === 1
            ? $this->monday($request->query('week'))
            : CarbonImmutable::now('Asia/Jakarta')->addWeek()->startOfWeek();

        return redirect()->route('weekly-plan.show', ['area' => $area->slug, 'week' => $week->toDateString()]);
    }

    public function show(Request $request, string $area, string $week)
    {
        $areaModel = $this->resolveArea($request->user(), $area);
        $monday = $this->monday($week);

        if ($monday->toDateString() !== $week) {
            return redirect()->route('weekly-plan.show', ['area' => $areaModel->slug, 'week' => $monday->toDateString()]);
        }

        // whereDate: SQLite stores the date with a time part, so a plain
        // equality on week_start would miss an existing plan.
        $plan = WeeklyPmPlan::query()
            ->where('area', $areaModel->name)
            ->whereDate('week_start', $monday->toDateString())
            ->first()
            ?? WeeklyPmPlan::create([
                'area' => $areaModel->name,
                'week_start' => $monday->toDateString(),
                'status' => WeeklyPmPlan::STATUS_DRAFT,
                'created_by' => $request->user()->id,
                ...DayDefaults::for($areaModel->name, $monday),
            ]);

        return view('weekly-plan.show', [
            'plan' => $plan,
            'areaModel' => $areaModel,
            'areas' => $this->allowedAreas($request->user()),
            'weekLabel' => IndoDate::range($monday, $monday->addDays(4)),
            'prevWeek' => $monday->subWeek()->toDateString(),
            'nextWeek' => $monday->addWeek()->toDateString(),
            'payload' => $this->payload($plan),
            'contentMaxWidth' => 'max-w-none',
            'fullWidth' => true,
        ]);
    }

    public function saveItems(Request $request, WeeklyPmPlan $plan, PlanSaveService $saver): JsonResponse
    {
        $this->authorizePlan($request->user(), $plan);

        // bootstrap/app.php renders JSON errors only for api/*, so a failed
        // validation here is answered as JSON explicitly for the board script.
        try {
            $data = $request->validate([
                'items' => ['present', 'array'],
                'items.*.pm_schedule_id' => ['required', 'integer'],
                'items.*.day' => ['required', 'date_format:Y-m-d'],
                'items.*.pic' => ['nullable', 'string', 'max:255'],
                'items.*.construction_note' => ['nullable', 'string', 'max:100'],
            ]);

            $released = $saver->save($plan, $data['items'], $request->user());
        } catch (ValidationException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        }

        return response()->json(['pic_released' => $released] + $this->outputs($plan->fresh()));
    }

    public function autofill(Request $request, WeeklyPmPlan $plan, AutoFillService $autofill): JsonResponse
    {
        $this->authorizePlan($request->user(), $plan);

        $result = $autofill->fill($plan, $request->user());

        return response()->json($result + $this->payload($plan->fresh()));
    }

    /**
     * Saves the per-day auto-fill settings: the machine limit (0..6) and the
     * machine type mix. Either may be sent alone; the type counts of a day
     * may never add up to more than that day's limit.
     */
    public function saveDayLimits(Request $request, WeeklyPmPlan $plan, PlanBoardService $board): JsonResponse
    {
        $this->authorizePlan($request->user(), $plan);

        $days = array_map(fn ($d) => $d->toDateString(), $plan->workDays());
        $limits = $request->input('limits', []);
        $types = $request->input('types', []);
        $fail = fn (string $message) => response()->json(['message' => $message, 'errors' => ['day_limits' => [$message]]], 422);

        if (! is_array($limits) || ! is_array($types) || array_diff(array_keys($limits + $types), $days) !== []) {
            return $fail('The day must be Monday to Friday of this week.');
        }

        $isCount = fn ($v, int $min) => filter_var($v, FILTER_VALIDATE_INT, ['options' => ['min_range' => $min, 'max_range' => WeeklyPmPlan::MAX_DAY_LIMIT]]) !== false;

        if (collect($limits)->contains(fn ($v) => ! $isCount($v, 0))) {
            return $fail('The machine limit per day must be 0 to '.WeeklyPmPlan::MAX_DAY_LIMIT.'.');
        }

        $knownTypes = $board->machineTypes($plan);
        $cleanTypes = [];

        foreach ($types as $date => $rows) {
            if (! is_array($rows)) {
                return $fail('The type mix format was not recognised.');
            }

            $cleanTypes[$date] = [];

            foreach ($rows as $row) {
                $type = is_array($row) ? MachineFamily::of($row['type'] ?? '') : '';

                if (! in_array($type, $knownTypes, true)) {
                    return $fail('Machine type '.($type === '' ? '(empty)' : $type).' does not exist in area '.$plan->area.'.');
                }

                if (! $isCount($row['count'] ?? null, 1)) {
                    return $fail('Machine count '.$type.' must be 1 to '.WeeklyPmPlan::MAX_DAY_LIMIT.'.');
                }

                if (collect($cleanTypes[$date])->contains('type', $type)) {
                    return $fail('Type '.$type.' is picked twice on the same day.');
                }

                $cleanTypes[$date][] = ['type' => $type, 'count' => (int) $row['count']];
            }
        }

        $newLimits = array_merge($plan->day_limits ?? [], array_map('intval', $limits));
        $newTypes = array_merge($plan->day_types ?? [], $cleanTypes);
        $probe = new WeeklyPmPlan(['day_limits' => $newLimits, 'day_types' => $newTypes]);

        foreach ($plan->workDays() as $day) {
            $date = $day->toDateString();
            $total = array_sum(array_column($probe->dayTypes($date), 'count'));

            if ($total > $probe->dayLimit($date)) {
                return $fail('The type mix for '.IndoDate::dayName($day).' adds up to '.$total.' machines, over the limit of '.$probe->dayLimit($date).'.');
            }
        }

        $plan->update([
            'day_limits' => $newLimits,
            'day_types' => array_filter($newTypes, fn ($rows) => $rows !== []),
            'updated_by' => $request->user()->id,
        ]);

        return response()->json(['day_limits' => $plan->day_limits, 'day_types' => $plan->day_types]);
    }

    public function markSent(Request $request, WeeklyPmPlan $plan): JsonResponse
    {
        $this->authorizePlan($request->user(), $plan);

        $plan->update([
            'status' => WeeklyPmPlan::STATUS_SENT,
            'sent_snapshot' => $plan->items()->get()->map->toRow()->all(),
            'sent_at' => now(),
            'updated_by' => $request->user()->id,
        ]);

        return response()->json($this->payload($plan->fresh()));
    }

    /** Puts the per-day limits and type mix back to the area defaults (config/weekly_plan.php). */
    public function restoreDayDefaults(Request $request, WeeklyPmPlan $plan): JsonResponse
    {
        $this->authorizePlan($request->user(), $plan);

        $plan->update(DayDefaults::for($plan->area, $plan->week_start) + ['updated_by' => $request->user()->id]);

        return response()->json($this->payload($plan->fresh()));
    }

    /** Dry run for the confirmation dialog of "Terapkan PIC ke PM Schedule". */
    public function picSyncPreview(Request $request, WeeklyPmPlan $plan): JsonResponse
    {
        $this->authorizePlan($request->user(), $plan);
        $actions = $this->picSync->preview($plan);

        return response()->json($this->picSync->summary($actions) + ['actions' => $actions]);
    }

    public function picSyncApply(Request $request, WeeklyPmPlan $plan): JsonResponse
    {
        $this->authorizePlan($request->user(), $plan);
        $result = $this->picSync->apply($plan);

        return response()->json(['result' => $result] + $this->outputs($plan->fresh()));
    }

    private function payload(WeeklyPmPlan $plan): array
    {
        return ['state' => $this->board->state($plan)] + $this->outputs($plan);
    }

    private function outputs(WeeklyPmPlan $plan): array
    {
        $plan->load('items');

        return [
            'email' => $this->email->compose($plan->area, $plan->items->map->toRow()),
            'revision' => $plan->sent_snapshot !== null ? $this->revision->compose($plan) : null,
            'realization' => $this->realization->summary($plan),
            'sent_at' => $plan->sent_at?->timezone('Asia/Jakarta')->format('d/m/Y H:i'),
            'pic_sync' => [
                'pending' => $this->picSync->summary($this->picSync->preview($plan))['pending'],
                'applied_at' => $plan->pics_applied_at?->timezone('Asia/Jakarta')->format('d/m/Y H:i'),
            ],
        ];
    }

    /** @return Collection<int, Area> */
    private function allowedAreas(User $user): Collection
    {
        if ($user->seesAllAreas()) {
            return Area::active()->whereIn('name', $user->selectableAreaNames())->orderBy('name')->get();
        }

        return $user->area ? collect([$user->area]) : collect();
    }

    private function resolveArea(User $user, string $slug): Area
    {
        $area = Area::active()->where('slug', $slug)->firstOrFail();
        abort_unless($user->hasArea($area), 403);

        return $area;
    }

    private function authorizePlan(User $user, WeeklyPmPlan $plan): void
    {
        abort_unless($user->hasArea($plan->area), 403);
    }

    private function monday(string $week): CarbonImmutable
    {
        abort_unless(preg_match('/^\d{4}-\d{2}-\d{2}$/', $week) === 1, 404);

        try {
            return CarbonImmutable::createFromFormat('!Y-m-d', $week, 'Asia/Jakarta')->startOfWeek();
        } catch (\Throwable) {
            abort(404);
        }
    }
}
