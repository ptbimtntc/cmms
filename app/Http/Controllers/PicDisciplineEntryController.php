<?php

namespace App\Http\Controllers;

use App\Models\PicDisciplineEntry;
use App\Models\PicDisciplineItemType;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Manual input grid for the Kedisiplinan PIC admin items (Pasang Tag,
 * Checklist Kertas, WO Process, Create WO Rep, Closing WO SAP) — FILL_PM is
 * excluded here since it is filled automatically (see
 * PicDisciplineEntryGenerator). ADMIN/KOORDINATOR can edit; SUPERVISOR sees
 * the same grid read-only (see routes/web.php — the POST update route
 * excludes SUPERVISOR entirely); PIC has no access to either route.
 */
class PicDisciplineEntryController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $year = $request->filled('year') ? (int) $request->input('year') : (int) now()->year;
        $quarter = $request->filled('quarter') ? (int) $request->input('quarter') : null;
        $area = $user->seesAllAreas() && $user->selectableAreaNames()->contains($request->input('area'))
            ? $request->input('area')
            : null;
        $pic = $request->input('pic') ?: null;

        $itemTypes = PicDisciplineItemType::where('is_active', true)
            ->where('is_auto', false)
            ->orderBy('sort_order')
            ->get();

        $query = PicDisciplineEntry::query()
            ->visibleToUser($user)
            ->whereIn('item_type_id', $itemTypes->pluck('id') ?: [0])
            ->with(['pmSchedule', 'picUser', 'itemType'])
            ->whereYear('due_date', $year);

        if ($quarter) {
            [$startMonth, $endMonth] = [$quarter * 3 - 2, $quarter * 3];
            $query->whereMonth('due_date', '>=', $startMonth)->whereMonth('due_date', '<=', $endMonth);
        }

        if ($area) {
            $query->whereHas('pmSchedule', fn ($q) => $q->where('area', $area));
        }

        if ($pic) {
            $query->whereHas('picUser', fn ($q) => $q->where('name', $pic));
        }

        $entries = $query->orderByDesc('due_date')->get();

        // One grid row per PM Schedule, columns = manual item types.
        $rows = $entries->groupBy('pm_schedule_id')->map(function ($group) {
            $first = $group->first();

            return [
                'pm_schedule' => $first->pmSchedule,
                'pic' => $first->picUser,
                'entries' => $group->keyBy('item_type_id'),
            ];
        })->values();

        $canEdit = $user->isAdmin() || $user->isKoordinator();

        return view('pic-discipline.entries.index', [
            'itemTypes' => $itemTypes,
            'rows' => $rows,
            'canEdit' => $canEdit,
            'isAdmin' => $user->seesAllAreas(),
            'years' => $this->availableYears($user),
            'areas' => $user->selectableAreaNames(),
            'pics' => User::where('role', User::ROLE_PIC)
                ->when(! $user->seesAllAreas(), fn ($q) => $q->where('area_id', $user->area_id))
                ->orderBy('name')
                ->pluck('name'),
            'selectedYear' => $year,
            'selectedQuarter' => $quarter,
            'selectedArea' => $area,
            'selectedPic' => $pic,
        ]);
    }

    public function update(Request $request, PicDisciplineEntry $entry)
    {
        abort_unless(
            PicDisciplineEntry::query()->visibleToUser($request->user())->whereKey($entry->id)->exists(),
            403
        );

        $data = $request->validate([
            'completed_date' => ['nullable', 'date'],
        ]);

        $entry->completed_date = $data['completed_date'] ?: null;
        $entry->recorded_by = $request->user()->id;
        $entry->recomputeStatus();
        $entry->save();

        return response()->json([
            'success' => true,
            'status' => $entry->status,
        ]);
    }

    private function availableYears(User $user): array
    {
        $scope = PicDisciplineEntry::query()->visibleToUser($user);
        $min = (clone $scope)->min('due_date');
        $max = (clone $scope)->max('due_date');

        if (! $min || ! $max) {
            return [now()->year];
        }

        return range((int) Carbon::parse($max)->format('Y'), (int) Carbon::parse($min)->format('Y'));
    }
}
