<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\MachineMeasurement;
use App\Models\Machine;
use App\Imports\MachineMeasurementImport;
use Maatwebsite\Excel\Facades\Excel;

class MachineMeasurementController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = MachineMeasurement::query();

        // FILTER MACHINE TYPE
        if ($request->filled('machine_type')) {
            $query->where('machine_type', $request->machine_type);
        }

        // filter measurement
        if ($request->filled('measurement')) {
            $query->where('measurement_item', $request->measurement);
        }

        // SORT
        $sort = $request->get('sort');

        switch ($sort) {

            case 'machine_type_asc':
                $query->orderBy('machine_type', 'asc');
                break;

            case 'machine_type_desc':
                $query->orderBy('machine_type', 'desc');
                break;

            case 'measurement_asc':
                $query->orderBy('measurement_item', 'asc');
                break;

            case 'measurement_desc':
                $query->orderBy('measurement_item', 'desc');
                break;

            default:
                $query->orderBy('machine_type', 'asc');
        }

        $measurements = $query->paginate(20);

        $machines = Machine::select('machine_type')
            ->distinct()
            ->orderBy('machine_type')
            ->get();

        return view(
            'machine-measurements.index',
            compact('measurements', 'machines')
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
            'machine-measurements.create',
            compact('machines')
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'machine_type' => 'required',
            'measurements' => 'required|array|min:1',
        ]);

        foreach ($request->measurements as $index => $item) {

            if (!$item) {
                continue;
            }

            $exists = MachineMeasurement::where(
                'machine_type',
                $request->machine_type
            )
            ->where(
                'measurement_item',
                trim($item)
            )
            ->exists();

            if (!$exists) {

                MachineMeasurement::create([
                    'machine_type'     => $request->machine_type,
                    'measurement_item' => trim($item),
                    'unit'             => $request->units[$index] ?? null,
                    'standard'         => $request->standards[$index] ?? null,
                ]);
            }
        }

        return redirect()
            ->route('machine-measurements.index')
            ->with('success', 'Measurement added successfully');
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
    public function edit(MachineMeasurement $machineMeasurement)
    {
        $machines = Machine::select('machine_type')
            ->distinct()
            ->orderBy('machine_type')
            ->get();

        return view(
            'machine-measurements.edit',
            compact('machineMeasurement', 'machines')
        );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, MachineMeasurement $machineMeasurement)
    {
        $request->validate([
            'machine_type'     => 'required',
            'measurement_item' => 'required',
            'unit'             => 'nullable',
            'standard'         => 'nullable',
        ]);

        // cek duplicate selain data yang sedang diedit
        $exists = MachineMeasurement::where(
            'machine_type',
            $request->machine_type
        )
            ->where(
                'measurement_item',
                trim($request->measurement_item)
            )
            ->where('id', '!=', $machineMeasurement->id)
            ->exists();

        if ($exists) {
            return back()
                ->withInput()
                ->with('error', 'Measurement item already exists for this machine type.');
        }

        $machineMeasurement->update([
            'machine_type'     => $request->machine_type,
            'measurement_item' => trim($request->measurement_item),
            'unit'             => trim($request->unit),
            'standard'         => trim((string) $request->standard),
        ]);

        return redirect()
            ->route('machine-measurements.index')
            ->with('success', 'Measurement updated successfully');
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => [
                'required',
                'file',
                'max:20480',
                function ($attribute, $value, $fail) {
                    $extension = strtolower($value->getClientOriginalExtension());
                    $mime = strtolower($value->getClientMimeType());
                    $allowedExtensions = ['csv', 'xlsx', 'xls', 'txt'];
                    $allowedMimes = [
                        'text/csv',
                        'text/plain',
                        'application/csv',
                        'application/vnd.ms-excel',
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'application/octet-stream',
                    ];

                    if (!in_array($extension, $allowedExtensions, true) && !in_array($mime, $allowedMimes, true)) {
                        $fail('File must be a CSV or Excel file.');
                    }
                },
            ],
        ]);

        try {
            Excel::import(
                new MachineMeasurementImport,
                $request->file('file')
            );
        } catch (\Throwable $e) {
            return back()->withErrors([
                'file' => 'Import failed: ' . $e->getMessage(),
            ]);
        }

        return back()->with(
            'success',
            'Machine Measurements imported successfully.'
        );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $measurement = MachineMeasurement::findOrFail($id);

        $measurement->delete();

        return redirect()
            ->route('machine-measurements.index')
            ->with('success', 'Measurement deleted successfully');
    }
}