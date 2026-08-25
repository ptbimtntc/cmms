<?php

namespace App\Http\Controllers;

use App\Models\PMSchedule;
use App\Models\User;
use App\Services\PMReportKpiCalculator;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class PMReportController extends Controller
{
    /**
     * Areas actually used by this application (PMSchedule.area is a free
     * string column, but every import/UI only ever writes WWD or BUL — see
     * DashboardController::AREAS).
     */
    private const AREAS = ['WWD', 'BUL'];

    private const STATUSES = ['OPEN', 'IN_PROGRESS', 'FINISHED', 'FINISHED_ON_TIME', 'MISSED'];

    public function index(Request $request)
    {
        $user = $request->user();

        $year = $request->filled('year') ? (int) $request->input('year') : null;
        $month = $request->filled('month') ? (int) $request->input('month') : null;
        // Area filter is ADMIN-only — every other role is already fixed to
        // one area/pic by applyScopeTo() (same convention as the dashboard
        // and Greasing Report).
        $area = $user->isAdmin() && in_array($request->input('area'), self::AREAS, true)
            ? $request->input('area')
            : null;
        $machineType = $request->input('machine_type') ?: null;
        $machine = $request->input('machine') ?: null;
        $pic = $request->input('pic') ?: null;
        $status = in_array($request->input('status'), self::STATUSES, true)
            ? $request->input('status')
            : null;
        $search = trim((string) $request->input('search', ''));

        $query = $this->filteredQuery($user, $area, $year, $month, $machineType, $machine, $pic, $status, $search);

        // Summary must always reflect the exact same filtered/scoped query
        // as the table below it, so the two can never disagree.
        $statusCounts = (clone $query)
            ->select('status')
            ->selectRaw('count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $summary = PMReportKpiCalculator::fromStatusCounts($statusCounts);

        $schedules = $query
            ->orderByDesc('plan_date')
            ->orderBy('machine_number')
            ->paginate(20)
            ->withQueryString();

        // Filter dropdown options are scoped by role/area visibility only —
        // never by the currently active filters — so narrowing one filter
        // never hides the choices available in another (same convention as
        // PMScheduleController::index()).
        $optionsScope = $this->scoped($user, $area);

        return view('reports.pm.index', [
            'summary' => $summary,
            'schedules' => $schedules,
            'years' => $this->availableYears($user, $year),
            'machineTypes' => (clone $optionsScope)->whereNotNull('machine_type')->distinct()->orderBy('machine_type')->pluck('machine_type'),
            'machines' => (clone $optionsScope)->whereNotNull('machine_number')->distinct()->orderBy('machine_number')->pluck('machine_number'),
            'pics' => (clone $optionsScope)->whereNotNull('pic')->distinct()->orderBy('pic')->pluck('pic'),
            'statuses' => self::STATUSES,
            'areas' => $this->visibleAreas($user, $area),
            'isAdmin' => $user->isAdmin(),
            'selectedYear' => $year,
            'selectedMonth' => $month,
            'selectedArea' => $area,
            'selectedMachineType' => $machineType,
            'selectedMachine' => $machine,
            'selectedPic' => $pic,
            'selectedStatus' => $status,
            'search' => $search,
            // Forecasting/Predictive Maintenance is not implemented yet —
            // deliberately no key is passed here. When it is built, add a
            // 'forecast' => ... array here (e.g. per-machine predicted
            // problem/sparepart/confidence) and the view's Forecasting
            // section already has the placeholder ready to receive it.
        ]);
    }

    /**
     * Applies every active filter (year/month/machine type/machine/pic/
     * status/search) on top of the role+area authorization scope. Search
     * matches Machine Number OR Order Number, combined with every other
     * filter via AND — e.g. Area=WWD + Year=2026 + Search=12345678 only
     * ever returns PM schedules matching all three at once.
     */
    private function filteredQuery(
        User $user,
        ?string $area,
        ?int $year,
        ?int $month,
        ?string $machineType,
        ?string $machine,
        ?string $pic,
        ?string $status,
        string $search
    ): Builder {
        $query = $this->scoped($user, $area);

        if ($year) {
            $query->whereYear('plan_date', $year);
        }

        if ($month) {
            $query->whereMonth('plan_date', $month);
        }

        if ($machineType) {
            $query->where('machine_type', $machineType);
        }

        if ($machine) {
            $query->where('machine_number', $machine);
        }

        if ($pic) {
            $query->where('pic', $pic);
        }

        if ($status) {
            $query->where('status', $status);
        }

        if ($search !== '') {
            $query->where(function (Builder $q) use ($search) {
                $q->where('machine_number', 'like', "%{$search}%")
                    ->orWhere('order_number', 'like', "%{$search}%");
            });
        }

        return $query;
    }

    private function scoped(User $user, ?string $area): Builder
    {
        return $this->applyScopeTo(PMSchedule::query(), $user, $area);
    }

    /**
     * Same role/area/PIC visibility rule as
     * DashboardController::applyScopeTo() (that method is private on a
     * different controller, and PMScheduleController::index() already
     * implements this same rule a third time as an inline switch — kept as
     * a separate copy here rather than a cross-controller refactor).
     *
     * ADMIN            -> ALL, or the given $area if provided
     * KOORDINATOR WWD  -> fixed to WWD, $area ignored
     * KOORDINATOR BUL  -> fixed to BUL, $area ignored
     * PIC WWD / PIC BUL -> fixed to their own name, $area ignored
     */
    private function applyScopeTo(Builder $query, User $user, ?string $area): Builder
    {
        switch ($user->role) {
            case User::ROLE_KOORDINATOR_WWD:
                $query->where('area', 'WWD');
                break;
            case User::ROLE_KOORDINATOR_BUL:
                $query->where('area', 'BUL');
                break;
            case User::ROLE_PIC_WWD:
            case User::ROLE_PIC_BUL:
                $query->where('pic', $user->name);
                break;
            default:
                if ($area) {
                    $query->where('area', $area);
                }
                break;
        }

        return $query;
    }

    private function userAreaMatches(User $user, string $area): bool
    {
        return match ($user->role) {
            User::ROLE_KOORDINATOR_WWD, User::ROLE_PIC_WWD => $area === 'WWD',
            User::ROLE_KOORDINATOR_BUL, User::ROLE_PIC_BUL => $area === 'BUL',
            default => true,
        };
    }

    /**
     * Which areas the Area filter dropdown should offer: role permission
     * narrowed further by the optional ADMIN-only active area filter.
     */
    private function visibleAreas(User $user, ?string $area): array
    {
        $allowed = collect(self::AREAS)->filter(fn (string $a) => $this->userAreaMatches($user, $a))->values();

        if ($area && $allowed->contains($area)) {
            return [$area];
        }

        return $allowed->all();
    }

    /**
     * MIN/MAX-based year range (portable across MySQL and SQLite), same
     * technique as DashboardController::availableYears().
     */
    private function availableYears(User $user, ?int $selectedYear): array
    {
        $scope = $this->scoped($user, null);
        $minPlanDate = (clone $scope)->min('plan_date');
        $maxPlanDate = (clone $scope)->max('plan_date');

        $years = $minPlanDate && $maxPlanDate
            ? range((int) Carbon::parse($maxPlanDate)->format('Y'), (int) Carbon::parse($minPlanDate)->format('Y'))
            : [];

        if ($selectedYear && ! in_array($selectedYear, $years, true)) {
            $years[] = $selectedYear;
        }

        rsort($years);

        return $years;
    }
}
