<?php

namespace App\Http\Controllers;

use App\Models\Machine;
use App\Models\MachineChecklist;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\MachineChecklistsImport;

class MachineChecklistController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = MachineChecklist::query();

        // SEARCH
        if ($request->filled('search')) {

            $query->where(function ($q) use ($request) {

                $q->where('checklist_item', 'like', '%' . $request->search . '%')
                    ->orWhere('section', 'like', '%' . $request->search . '%');

            });

        }

        // FILTER MACHINE TYPE
        if ($request->filled('machine_type')) {

            $query->where('machine_type', $request->machine_type);

        }

        // FILTER SECTION
        if ($request->filled('section')) {

            $query->where('section', $request->section);

        }

        // SORT
        switch ($request->sort) {

            case 'machine_type_asc':
                $query->orderBy('machine_type');
                break;

            case 'machine_type_desc':
                $query->orderBy('machine_type', 'desc');
                break;

            case 'section_order_asc':
                $query->orderBy('section_order')
                      ->orderBy('item_order');
                break;

            case 'section_order_desc':
                $query->orderBy('section_order', 'desc')
                      ->orderBy('item_order', 'desc');
                break;

            case 'item_asc':
                $query->orderBy('checklist_item');
                break;

            case 'item_desc':
                $query->orderBy('checklist_item', 'desc');
                break;

            default:
                $query->orderBy('machine_type')
                      ->orderBy('section_order')
                      ->orderBy('item_order');
        }

        $checklists = $query->paginate(20);

        $machineTypes = MachineChecklist::select('machine_type')
            ->whereNotNull('machine_type')
            ->where('machine_type', '!=', '')
            ->distinct()
            ->orderBy('machine_type')
            ->pluck('machine_type');

        $sections = MachineChecklist::select('section', 'section_order')
            ->distinct()
            ->orderBy('section_order')
            ->pluck('section');

        return view(
            'machine-checklists.index',
            compact(
                'checklists',
                'machineTypes',
                'sections'
            )
        );
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $machines = Machine::select('machine_type')
            ->distinct()
            ->orderBy('machine_type')
            ->get();

        return view(
            'machine-checklists.create',
            compact('machines')
        );
    }

    /**
     * Store a newly created resource.
     */
    public function store(Request $request)
    {
        $request->validate([
            'machine_type' => 'required',
            'section' => 'required',
            'checklist_item' => 'required',
            'section_order' => 'required|integer|min:1',
            'item_order' => 'required|integer|min:1'
        ]);

        $exists = MachineChecklist::where('machine_type', $request->machine_type)
            ->where('section', $request->section)
            ->where('checklist_item', trim($request->checklist_item))
            ->exists();

        if ($exists) {

            return back()
                ->withInput()
                ->with('error', 'Checklist item already exists.');

        }

        MachineChecklist::create([

            'machine_type' => $request->machine_type,

            'section' => trim($request->section),

            'checklist_item' => trim($request->checklist_item),

            'section_order' => $request->section_order,

            'item_order' => $request->item_order

        ]);

        return redirect()
            ->route('machine-checklists.index')
            ->with('success', 'Checklist added successfully.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(MachineChecklist $machineChecklist)
    {
        $user = auth()->user();

        if (str_starts_with($user->role, 'PIC')) {

            if ($pmSchedule->pic !== $user->name) {

                abort(403);

            }

        }

        $machines = Machine::select('machine_type')
        ->distinct()
        ->orderBy('machine_type')
        ->get();

        return view(
            'machine-checklists.edit',
            compact(
                'machineChecklist',
                'machines'

            )
        );
    }

    public function update(Request $request, MachineChecklist $machineChecklist)
    {
        $user = auth()->user();

        if (str_starts_with($user->role,'PIC')) {

            if ($pmSchedule->pic !== $user->name) {

                abort(403);

            }

        }
        
        $request->validate([
            'machine_type' => 'required',
            'section' => 'required',
            'checklist_item' => 'required',
            'maintenance_type' => 'required|in:check,clean,lubrication,replace',
            'section_order' => 'required|integer|min:1',
            'item_order' => 'required|integer|min:1'
        ]);

        $exists = MachineChecklist::where('machine_type', $request->machine_type)
            ->where('section', $request->section)
            ->where('checklist_item', trim($request->checklist_item))
            ->where('id', '!=', $machineChecklist->id)
            ->exists();

        if ($exists) {

            return back()
                ->withInput()
                ->with('error', 'Checklist item already exists.');

        }

        $machineChecklist->update([
            'machine_type' => $request->machine_type,
            'section' => trim($request->section),
            'section_order' => $request->section_order,
            'checklist_item' => trim($request->checklist_item),
            'maintenance_type' => $request->maintenance_type,
            'item_order' => $request->item_order,
]);

        return redirect()
            ->route('machine-checklists.index')
            ->with('success', 'Checklist updated successfully.');
    }

    /**
     * Remove the specified resource.
     */
    public function destroy(MachineChecklist $machineChecklist)
    {
        $machineChecklist->delete();

        return redirect()
            ->route('machine-checklists.index')
            ->with('success', 'Checklist deleted successfully.');
    }

    /**
     * Import Checklist
     */
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
                        'txt',
                        'xlsx',
                        'xls',
                    ];

                    $allowedMimes = [
                        'text/csv',
                        'text/plain',
                        'application/csv',
                        'application/vnd.ms-excel',
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    ];

                    if (
                        ! in_array($extension, $allowedExtensions, true)
                        || ! in_array($mime, $allowedMimes, true)
                    ) {
                        $fail('File must be a valid CSV or Excel file.');
                    }
                },
            ],
        ]);

        try {
            Excel::import(
                new MachineChecklistsImport(),
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
            'Machine Checklist imported successfully.'
        );
    }
}
