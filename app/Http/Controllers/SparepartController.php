<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Sparepart;
use App\Models\Machine;
use App\Imports\SparepartsImport;
use Maatwebsite\Excel\Facades\Excel;

class SparepartController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Sparepart::query();

        // SEARCH
        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {

                $q->where('material_number', 'like', '%' . $request->search . '%')
                  ->orWhere('description', 'like', '%' . $request->search . '%');

            });
        }

        // FILTER MACHINE TYPE
        if ($request->filled('machine_type')) {
            $query->where('machine_type', $request->machine_type);
        }

        // FILTER STATUS
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        // FILTER SEGMENT
        if ($request->filled('segment')) {
            $query->where('segment', $request->segment);
        }

        // SORT
        switch ($request->sort) {

            case 'material_asc':
                $query->orderBy('material_number');
                break;

            case 'material_desc':
                $query->orderBy('material_number', 'desc');
                break;

            case 'stock_asc':
                $query->orderBy('stock');
                break;

            case 'stock_desc':
                $query->orderBy('stock', 'desc');
                break;

            default:
                $query->orderBy('material_number');
        }

        $spareparts = $query->paginate(20);

        $machineTypes = Sparepart::select('machine_type')
            ->whereNotNull('machine_type')
            ->where('machine_type', '!=', '')
            ->distinct()
            ->orderBy('machine_type', 'asc')
            ->pluck('machine_type');

        $segments = Sparepart::select('segment')
            ->whereNotNull('segment')
            ->where('segment', '!=', '')
            ->distinct()
            ->orderBy('segment', 'asc')
            ->pluck('segment');

        return view(
            'spareparts.index',
            compact(
                'spareparts',
                'machineTypes',
                'segments'
            )
        );
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

        // A large CSV can take longer than the default 30s; this request
        // alone gets more room (server max_execution_time is untouched).
        set_time_limit(300);

        try {
            Excel::import(
                new SparepartsImport(),
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
            'Spareparts imported successfully'
        );
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'material_number' => 'required|unique:spareparts,material_number',
            'description'     => 'required',
            'stock'           => 'numeric|min:0',
            'rop'             => 'numeric|min:0',
            'price'           => 'numeric|min:0',
        ]);

        Sparepart::create([
            'material_number' => $request->material_number,
            'location'        => $request->location,
            'description'     => $request->description,
            'remarks'         => $request->remarks,
            'stock'           => $request->stock,
            'unit'            => $request->unit,
            'rop'             => $request->rop,
            'mrp_type'        => $request->mrp_type,
            'price'           => $request->price,
            'status'          => $request->status,
            'machine_type'    => $request->machine_type,
            'segment'         => $request->segment,
            'pdt'             => $request->pdt,
        ]);

        return redirect()
            ->route('spareparts.index')
            ->with('success', 'Sparepart added successfully');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Sparepart $sparepart)
    {
        $machines = Machine::select('machine_type')
            ->distinct()
            ->orderBy('machine_type')
            ->get();

        return view(
            'spareparts.edit',
            compact('sparepart', 'machines')
        );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Sparepart $sparepart)
    {
        $request->validate([
            'material_number' => 'required|unique:spareparts,material_number,' . $sparepart->id,
            'description'     => 'required',
            'stock'           => 'required|numeric|min:0',
            'rop'             => 'nullable|numeric|min:0',
            'price'           => 'nullable|numeric|min:0',
        ]);

        $sparepart->update([
            'material_number' => $request->material_number,
            'location'        => $request->location,
            'description'     => $request->description,
            'remarks'         => $request->remarks,
            'stock'           => $request->stock,
            'unit'            => $request->unit,
            'rop'             => $request->rop,
            'mrp_type'        => $request->mrp_type,
            'price'           => $request->price,
            'status'          => $request->status,
            'machine_type'    => $request->machine_type,
            'segment'         => $request->segment,
            'pdt'             => $request->pdt,
        ]);

        return redirect()
            ->route('spareparts.index', ['page' => $request->input('return_page')])
            ->with('success', 'Sparepart updated successfully');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $sparepart = Sparepart::findOrFail($id);

        $sparepart->delete();

        return redirect()
            ->route('spareparts.index')
            ->with('success', 'Sparepart deleted successfully');
    }
}