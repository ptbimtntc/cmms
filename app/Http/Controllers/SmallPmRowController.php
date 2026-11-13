<?php

namespace App\Http\Controllers;

use App\Imports\SmallPmRowImport;
use App\Models\Area;
use App\Models\Machine;
use App\Models\SmallPmRow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Small PM row master. Same flow as GroupController: create with name and
 * area, then tick the machines on the edit page. A machine holds one
 * small_pm_row_id, so ticking it here moves it out of any other row.
 */
class SmallPmRowController extends Controller
{
    public function index(Request $request)
    {
        $query = SmallPmRow::with('area')->withCount('machines');

        if ($request->filled('search')) {
            $query->where('name', 'like', '%'.$request->search.'%');
        }

        $rows = $query->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('small-pm-rows.index', compact('rows'));
    }

    /**
     * Bulk fill rows and their machines from a file, see SmallPmRowImport
     * for the columns. Additive: machines missing from the file keep their row.
     */
    public function import(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'max:20480', 'mimes:csv,txt,xlsx,xls'],
        ]);

        $import = new SmallPmRowImport;

        try {
            Excel::import($import, $request->file('file'));
        } catch (\Throwable $e) {
            report($e);

            return back()->withErrors(['file' => 'Import failed. Check the column format and the file contents.']);
        }

        return back()->with('small_pm_row_import_result', $import->result);
    }

    public function show(SmallPmRow $smallPmRow)
    {
        $smallPmRow->load('area');

        $machines = $smallPmRow->machines()->orderBy('machine_number')->get();

        return view('small-pm-rows.show', ['row' => $smallPmRow, 'machines' => $machines]);
    }

    public function create()
    {
        return view('small-pm-rows.create', [
            'areas' => $this->areas(),
            'nextOrder' => ((int) SmallPmRow::max('sort_order')) + 1,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->rules());

        $row = SmallPmRow::create($validated);

        return redirect()
            ->route('small-pm-rows.edit', $row)
            ->with('success', "Row {$row->name} dibuat. Sekarang centang mesin yang masuk row ini.");
    }

    public function edit(SmallPmRow $smallPmRow)
    {
        $areaName = $smallPmRow->area?->name;

        $machines = Machine::with('smallPmRow')
            ->when($areaName, fn ($q) => $q->where('area', $areaName))
            ->where(fn ($q) => $q->whereNull('small_pm_row_id')->orWhere('small_pm_row_id', $smallPmRow->id))
            ->orderBy('machine_number')
            ->get();

        return view('small-pm-rows.edit', [
            'row' => $smallPmRow,
            'machines' => $machines,
            'areas' => $this->areas(),
        ]);
    }

    public function update(Request $request, SmallPmRow $smallPmRow)
    {
        $validated = $request->validate($this->rules($smallPmRow) + [
            'machine_ids' => 'nullable|array',
            'machine_ids.*' => 'exists:machines,id',
        ]);

        $selected = $validated['machine_ids'] ?? [];

        DB::transaction(function () use ($smallPmRow, $validated, $selected) {
            $smallPmRow->update([
                'name' => $validated['name'],
                'area_id' => $validated['area_id'],
                'sort_order' => $validated['sort_order'],
            ]);

            if (! empty($selected)) {
                Machine::whereIn('id', $selected)->update(['small_pm_row_id' => $smallPmRow->id]);
            }

            Machine::where('small_pm_row_id', $smallPmRow->id)
                ->whereNotIn('id', $selected)
                ->update(['small_pm_row_id' => null]);
        });

        return redirect()
            ->route('small-pm-rows.index', ['page' => $request->input('return_page')])
            ->with('success', "Row {$validated['name']} disimpan.");
    }

    public function destroy(SmallPmRow $smallPmRow)
    {
        if ($smallPmRow->machines()->exists()) {
            return back()->with('error', 'A row that still holds machines cannot be deleted.');
        }

        if ($smallPmRow->schedules()->exists()) {
            return back()->with('error', 'A row that already has a Small PM schedule cannot be deleted.');
        }

        $smallPmRow->delete();

        return back()->with('success', 'Row deleted.');
    }

    /** @return array<string, mixed> */
    private function rules(?SmallPmRow $row = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('small_pm_rows', 'name')->ignore($row?->id)],
            'area_id' => ['required', Rule::exists('areas', 'id')->where('is_active', true)->whereIn('name', config('small_pm.areas', []))],
            'sort_order' => ['required', 'integer', 'min:0', 'max:9999'],
        ];
    }

    private function areas()
    {
        return Area::active()->whereIn('name', config('small_pm.areas', []))->orderBy('name')->get();
    }
}
