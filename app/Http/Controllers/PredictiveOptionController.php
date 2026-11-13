<?php

namespace App\Http\Controllers;

use App\Imports\PredictiveOptionImport;
use App\Models\Area;
use App\Models\PredictiveActionFinding;
use App\Models\PredictiveActionProblem;
use App\Models\PredictiveFindingOption;
use App\Models\PredictiveProblemOption;
use App\Models\PredictiveWorkOrder;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Master list of Problem > Finding choices used by the Predictive Action form,
 * one list per category and area. ADMIN manages every area, a KOORDINATOR only
 * their own; the route group already keeps every other role out, and each
 * write re-checks the area here.
 */
class PredictiveOptionController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $areas = $this->areasFor($user);

        $category = $request->query('category');
        $category = isset(PredictiveWorkOrder::CATEGORIES[$category]) ? $category : PredictiveWorkOrder::CATEGORY_VIBRATION;

        $area = $request->query('area');
        $area = is_string($area) && $areas->contains($area) ? $area : $areas->first();

        $problems = $area === null
            ? collect()
            : PredictiveProblemOption::query()->forScope($category, $area)->with('findings')->orderBy('name')->get();

        $usedProblems = PredictiveActionProblem::query()
            ->whereIn('problem_option_id', $problems->pluck('id'))->distinct()->pluck('problem_option_id')->flip();
        $usedFindings = PredictiveActionFinding::query()
            ->whereIn('finding_option_id', $problems->flatMap->findings->pluck('id'))->distinct()->pluck('finding_option_id')->flip();

        return view('predictive.master.index', [
            'category' => $category,
            'area' => $area,
            'areas' => $areas,
            'problems' => $problems,
            'usedProblems' => $usedProblems,
            'usedFindings' => $usedFindings,
        ]);
    }

    public function storeProblem(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'category' => ['required', Rule::in(array_keys(PredictiveWorkOrder::CATEGORIES))],
            'area' => ['required', 'string'],
            'name' => ['required', 'string', 'max:255'],
        ]);
        $this->authorizeArea($request->user(), $data['area']);

        $name = trim($data['name']);
        $taken = PredictiveProblemOption::query()->forScope($data['category'], $data['area'])->pluck('name')
            ->contains(fn ($n) => PredictiveProblemOption::normalize($n) === PredictiveProblemOption::normalize($name));

        if ($taken) {
            return $this->back($data['category'], $data['area'])->with('error', "Problem \"{$name}\" sudah ada.");
        }

        PredictiveProblemOption::create(['category' => $data['category'], 'area' => $data['area'], 'name' => $name]);

        return $this->back($data['category'], $data['area'])->with('success', "Problem \"{$name}\" ditambahkan.");
    }

    public function updateProblem(Request $request, PredictiveProblemOption $problem): RedirectResponse
    {
        $this->authorizeArea($request->user(), $problem->area);

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if (isset($data['name'])) {
            $name = trim($data['name']);
            $taken = PredictiveProblemOption::query()->forScope($problem->category, $problem->area)
                ->whereKeyNot($problem->id)->pluck('name')
                ->contains(fn ($n) => PredictiveProblemOption::normalize($n) === PredictiveProblemOption::normalize($name));

            if ($taken) {
                return $this->back($problem->category, $problem->area)->with('error', "Problem \"{$name}\" sudah ada.");
            }

            DB::transaction(function () use ($problem, $name) {
                $problem->update(['name' => $name]);
                // Saved Actions keep the wording as a snapshot; follow the rename so reports stay consistent.
                PredictiveActionProblem::where('problem_option_id', $problem->id)->update(['problem' => $name]);
            });
        }

        if (array_key_exists('is_active', $data)) {
            $problem->update(['is_active' => (bool) $data['is_active']]);
        }

        return $this->back($problem->category, $problem->area)->with('success', 'Problem diperbarui.');
    }

    public function destroyProblem(Request $request, PredictiveProblemOption $problem): RedirectResponse
    {
        $this->authorizeArea($request->user(), $problem->area);

        if ($problem->isUsed()) {
            return $this->back($problem->category, $problem->area)
                ->with('error', "Problem \"{$problem->name}\" sudah dipakai di Action. Nonaktifkan saja agar tidak muncul lagi di form.");
        }

        $problem->delete();

        return $this->back($problem->category, $problem->area)->with('success', "Problem \"{$problem->name}\" dihapus.");
    }

    public function storeFinding(Request $request, PredictiveProblemOption $problem): RedirectResponse
    {
        $this->authorizeArea($request->user(), $problem->area);

        $data = $request->validate(['name' => ['required', 'string', 'max:255']]);
        $name = trim($data['name']);

        $taken = $problem->findings()->pluck('name')
            ->contains(fn ($n) => PredictiveProblemOption::normalize($n) === PredictiveProblemOption::normalize($name));

        if ($taken) {
            return $this->back($problem->category, $problem->area)->with('error', "Finding \"{$name}\" sudah ada di Problem ini.");
        }

        $problem->findings()->create(['name' => $name]);

        return $this->back($problem->category, $problem->area)->with('success', "Finding \"{$name}\" ditambahkan.");
    }

    public function updateFinding(Request $request, PredictiveFindingOption $finding): RedirectResponse
    {
        $problem = $finding->problem;
        $this->authorizeArea($request->user(), $problem->area);

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if (isset($data['name'])) {
            $name = trim($data['name']);
            $taken = $problem->findings()->whereKeyNot($finding->id)->pluck('name')
                ->contains(fn ($n) => PredictiveProblemOption::normalize($n) === PredictiveProblemOption::normalize($name));

            if ($taken) {
                return $this->back($problem->category, $problem->area)->with('error', "Finding \"{$name}\" sudah ada di Problem ini.");
            }

            DB::transaction(function () use ($finding, $name) {
                $finding->update(['name' => $name]);
                PredictiveActionFinding::where('finding_option_id', $finding->id)->update(['finding' => $name]);
            });
        }

        if (array_key_exists('is_active', $data)) {
            $finding->update(['is_active' => (bool) $data['is_active']]);
        }

        return $this->back($problem->category, $problem->area)->with('success', 'Finding diperbarui.');
    }

    public function destroyFinding(Request $request, PredictiveFindingOption $finding): RedirectResponse
    {
        $problem = $finding->problem;
        $this->authorizeArea($request->user(), $problem->area);

        if ($finding->isUsed()) {
            return $this->back($problem->category, $problem->area)
                ->with('error', "Finding \"{$finding->name}\" sudah dipakai di Action. Nonaktifkan saja agar tidak muncul lagi di form.");
        }

        $finding->delete();

        return $this->back($problem->category, $problem->area)->with('success', "Finding \"{$finding->name}\" dihapus.");
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate(['file' => ['required', 'file', 'max:5120', 'mimes:xlsx,xls,csv,txt']]);

        $user = $request->user();
        $import = new PredictiveOptionImport($user, $this->areasFor($user));

        try {
            Excel::import($import, $request->file('file'));
        } catch (\Throwable $e) {
            report($e);

            return redirect()->route('predictive.master.index')
                ->with('error', 'The import could not be read. Check the file format (.xlsx, .xls, or .csv) and the column headers.');
        }

        return redirect()->route('predictive.master.index')->with('predictive_master_import_result', [
            'success' => $import->success,
            'failed' => $import->failed,
            'skipped' => $import->skipped,
            'details' => $import->details,
            'hidden' => $import->hiddenDetails,
            'fatal' => $import->fatalError,
        ]);
    }

    /** @return Collection<int, string> Area names this user may manage. */
    private function areasFor(User $user): Collection
    {
        if ($user->isAdmin()) {
            return Area::active()->orderBy('name')->pluck('name');
        }

        return $user->area ? collect([$user->area->name]) : collect();
    }

    private function authorizeArea(User $user, string $area): void
    {
        abort_unless($this->areasFor($user)->contains($area), 403, 'Unauthorized');
    }

    private function back(string $category, string $area): RedirectResponse
    {
        return redirect()->route('predictive.master.index', ['category' => $category, 'area' => $area]);
    }
}
