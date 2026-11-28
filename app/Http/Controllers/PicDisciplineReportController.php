<?php

namespace App\Http\Controllers;

use App\Exports\PicDisciplineReportExport;
use App\Models\PicDisciplineEntry;
use App\Models\PicDisciplineItemType;
use App\Models\User;
use App\Services\PicDisciplineKpiCalculator;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Quarterly Kedisiplinan PIC report: Achieved/Breach/% per PIC per
 * administration item, plus an overall Average per PIC. ADMIN/KOORDINATOR
 * full access, SUPERVISOR read-only (via the `reports.` route-name
 * convention in RoleMiddleware), PIC has no access at all (see
 * routes/web.php — this report's route group deliberately excludes PIC,
 * unlike the other reports.* routes).
 */
class PicDisciplineReportController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $year = $request->filled('year') ? (int) $request->input('year') : (int) now()->year;
        $quarter = $request->filled('quarter') ? (int) $request->input('quarter') : (int) ceil(now()->month / 3);
        $area = $user->seesAllAreas() && $user->selectableAreaNames()->contains($request->input('area'))
            ? $request->input('area')
            : null;
        $pic = $request->input('pic') ?: null;

        $itemTypes = PicDisciplineItemType::where('is_active', true)->orderBy('sort_order')->get();

        $entries = $this->filteredEntries($user, $year, $quarter, $area, $pic)->get();

        $pics = $entries->pluck('picUser')->filter()->unique('id')->sortBy('name')->values();

        return view('reports.pic-discipline.index', [
            'itemTypes' => $itemTypes,
            'pics' => $pics,
            'perItem' => PicDisciplineKpiCalculator::perItemByPic($entries),
            'overall' => PicDisciplineKpiCalculator::overallByPic($entries),
            'isAdmin' => $user->seesAllAreas(),
            'years' => $this->availableYears($user, $year),
            'quarters' => [1, 2, 3, 4],
            'areas' => $user->selectableAreaNames(),
            'picOptions' => User::where('role', User::ROLE_PIC)
                ->when(! $user->seesAllAreas(), fn ($q) => $q->where('area_id', $user->area_id))
                ->orderBy('name')
                ->pluck('name'),
            'selectedYear' => $year,
            'selectedQuarter' => $quarter,
            'selectedArea' => $area,
            'selectedPic' => $pic,
        ]);
    }

    public function export(Request $request)
    {
        $user = $request->user();

        $year = $request->filled('year') ? (int) $request->input('year') : (int) now()->year;
        $quarter = $request->filled('quarter') ? (int) $request->input('quarter') : (int) ceil(now()->month / 3);
        $area = $user->seesAllAreas() && $user->selectableAreaNames()->contains($request->input('area'))
            ? $request->input('area')
            : null;
        $pic = $request->input('pic') ?: null;

        $itemTypes = PicDisciplineItemType::where('is_active', true)->orderBy('sort_order')->get();
        $entries = $this->filteredEntries($user, $year, $quarter, $area, $pic)->get();
        $pics = $entries->pluck('picUser')->filter()->unique('id')->sortBy('name')->values();

        return Excel::download(
            new PicDisciplineReportExport(
                $itemTypes,
                $pics,
                PicDisciplineKpiCalculator::perItemByPic($entries),
                PicDisciplineKpiCalculator::overallByPic($entries),
            ),
            'pic-discipline-'.now()->format('Y-m-d_His').'.xlsx'
        );
    }

    private function filteredEntries(User $user, int $year, int $quarter, ?string $area, ?string $pic)
    {
        [$startMonth, $endMonth] = [$quarter * 3 - 2, $quarter * 3];

        $query = PicDisciplineEntry::query()
            ->visibleToUser($user)
            ->with(['picUser', 'itemType'])
            ->whereYear('due_date', $year)
            ->whereMonth('due_date', '>=', $startMonth)
            ->whereMonth('due_date', '<=', $endMonth);

        if ($area) {
            $query->whereHas('pmSchedule', fn ($q) => $q->where('area', $area));
        }

        if ($pic) {
            $query->whereHas('picUser', fn ($q) => $q->where('name', $pic));
        }

        return $query;
    }

    private function availableYears(User $user, ?int $selectedYear): array
    {
        $scope = PicDisciplineEntry::query()->visibleToUser($user);
        $min = (clone $scope)->min('due_date');
        $max = (clone $scope)->max('due_date');

        $years = $min && $max
            ? range((int) Carbon::parse($max)->format('Y'), (int) Carbon::parse($min)->format('Y'))
            : [];

        if ($selectedYear && ! in_array($selectedYear, $years, true)) {
            $years[] = $selectedYear;
        }

        rsort($years);

        return $years ?: [now()->year];
    }
}
