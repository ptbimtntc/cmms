<?php

namespace App\Http\Controllers;

use App\Imports\MachinesImport;
use App\Models\Group;
use App\Models\Machine;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class MachineController extends Controller
{
    public function index(Request $request)
    {
        $query = Machine::with('group');

        // SEARCH
        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('machine_number', 'like', '%'.$request->search.'%')
                    ->orWhere('machine_type', 'like', '%'.$request->search.'%')
                    ->orWhere('area', 'like', '%'.$request->search.'%');
            });
        }

        // FILTER TYPE
        if ($request->filled('machine_type')) {
            $query->where('machine_type', $request->machine_type);
        }

        // FILTER AREA
        if ($request->filled('area')) {
            $query->where('area', $request->area);
        }

        // FILTER STATUS
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // 🔥 SORTING (NEW)
        if ($request->filled('sort')) {

            if ($request->sort == 'machine_number_asc') {
                $query->orderBy('machine_number', 'asc');
            }

            if ($request->sort == 'machine_number_desc') {
                $query->orderBy('machine_number', 'desc');
            }

            if ($request->sort == 'area_asc') {
                $query->orderBy('area', 'asc');
            }

            if ($request->sort == 'area_desc') {
                $query->orderBy('area', 'desc');
            }

        } else {
            // default sort CMMS style
            $query->orderBy('machine_number', 'asc');
        }

        $machines = $query->paginate(20)->withQueryString();

        // dropdown filter
        $machineTypes = Machine::select('machine_type')->distinct()->get();
        $areas = Machine::select('area')->distinct()->get();

        return view('machines.index', compact(
            'machines',
            'machineTypes',
            'areas'
        ));
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => [
                'required',
                'file',
                'max:20480', // 20 MB
                function ($attribute, $value, $fail) {
                    $extension = strtolower(
                        $value->getClientOriginalExtension()
                    );

                    $mime = strtolower(
                        $value->getMimeType()
                    );

                    $allowedExtensions = [
                        'csv',
                    ];

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
            Excel::import(
                new MachinesImport,
                $request->file('file')
            );
        } catch (\Throwable $e) {
            report($e);

            return back()->withErrors([
                'file' => 'Import failed. Please check the file format and data.',
            ]);
        }

        return back()->with(
            'success',
            'Machine imported successfully'
        );
    }

    public function importForm()
    {
        return view('machines.import');
    }

    public function create()
    {
        $groups = Group::orderBy('name')->get();

        return view('machines.create', compact('groups'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'area' => 'required',
            'machine_type' => 'required',
            'machine_number' => 'required|unique:machines',
            'description' => 'nullable',
            'status' => 'required',
            'group_id' => 'nullable|exists:groups,id',
        ]);

        Machine::create([
            'machine_number' => $validated['machine_number'],
            'machine_type' => $validated['machine_type'],
            'area' => $validated['area'],
            'description' => $validated['description'] ?? null,
            'status' => $validated['status'],
            'group_id' => $validated['group_id'] ?? null,
        ]);

        return redirect()
            ->route('machines.index')
            ->with('success', 'Machine created successfully');
    }

    public function edit(Machine $machine)
    {
        $groups = Group::orderBy('name')->get();

        return view(
            'machines.edit',
            compact('machine', 'groups')
        );
    }

    public function update(Request $request, Machine $machine)
    {
        $validated = $request->validate([
            'area' => 'required',
            'machine_type' => 'required',
            'machine_number' => 'required|unique:machines,machine_number,'.$machine->id,
            'description' => 'nullable',
            'status' => 'required',
            'pm_cycle_value' => 'nullable|integer|min:1',
            'pm_cycle_unit' => 'nullable|in:DAY,WEEK,MONTH,HOUR',
            'group_id' => 'nullable|exists:groups,id',
        ]);

        $machine->update([
            'machine_number' => $validated['machine_number'],
            'machine_type' => $validated['machine_type'],
            'area' => $validated['area'],
            'description' => $validated['description'] ?? null,
            'status' => $validated['status'],
            'pm_cycle_value' => $validated['pm_cycle_value'] ?? null,
            'pm_cycle_unit' => $validated['pm_cycle_unit'] ?? null,
            'group_id' => $validated['group_id'] ?? null,
        ]);

        return redirect()
            ->route('machines.index')
            ->with('success', 'Machine updated successfully.');
    }

    public function destroy(Machine $machine)
    {
        $machine->delete();

        return back()->with('success', 'Machine deleted');
    }
}
