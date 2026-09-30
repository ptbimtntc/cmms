<?php

namespace App\Http\Controllers;

use App\Models\Machine;
use App\Models\MachineMaintenanceRequirement;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Admin-only master data for which machine types require an oil change
 * during PM (see PMSchedule::requiresOilChange()). Replaces what used to be
 * a hardcoded machine_type list in that method — adding a machine type here
 * makes it show the Oil Change field on Fill PM immediately, no deploy
 * needed.
 */
class MachineMaintenanceRequirementController extends Controller
{
    public function index()
    {
        $requirements = MachineMaintenanceRequirement::orderBy('machine_type')->get();

        return view('machine-maintenance-requirements.index', compact('requirements'));
    }

    public function create()
    {
        $machineTypes = $this->availableMachineTypes();

        return view('machine-maintenance-requirements.create', compact('machineTypes'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'machine_type' => [
                'required',
                'string',
                'max:255',
                Rule::in($this->availableMachineTypes()),
                Rule::unique('machine_maintenance_requirements', 'machine_type'),
            ],
            'requires_oil_change' => ['required', 'boolean'],
        ]);

        MachineMaintenanceRequirement::create($validated);

        return redirect()
            ->route('machine-maintenance-requirements.index')
            ->with('success', 'Machine type added successfully.');
    }

    public function edit(MachineMaintenanceRequirement $machineMaintenanceRequirement)
    {
        $machineTypes = $this->availableMachineTypes($machineMaintenanceRequirement);

        return view('machine-maintenance-requirements.edit', [
            'requirement' => $machineMaintenanceRequirement,
            'machineTypes' => $machineTypes,
        ]);
    }

    public function update(Request $request, MachineMaintenanceRequirement $machineMaintenanceRequirement)
    {
        $validated = $request->validate([
            'machine_type' => [
                'required',
                'string',
                'max:255',
                Rule::in($this->availableMachineTypes($machineMaintenanceRequirement)),
                Rule::unique('machine_maintenance_requirements', 'machine_type')->ignore($machineMaintenanceRequirement->id),
            ],
            'requires_oil_change' => ['required', 'boolean'],
        ]);

        $machineMaintenanceRequirement->update($validated);

        return redirect()
            ->route('machine-maintenance-requirements.index')
            ->with('success', 'Machine type updated successfully.');
    }

    /**
     * Machine types pulled from master Machine data, minus the ones already
     * configured here — so the dropdown never offers a duplicate. When
     * editing, the record's own current machine_type is added back in so it
     * still appears as the selected option.
     */
    private function availableMachineTypes(?MachineMaintenanceRequirement $editing = null): array
    {
        $configured = MachineMaintenanceRequirement::query()
            ->when($editing, fn ($query) => $query->whereKeyNot($editing->id))
            ->pluck('machine_type');

        $types = Machine::query()
            ->select('machine_type')
            ->distinct()
            ->whereNotIn('machine_type', $configured)
            ->orderBy('machine_type')
            ->pluck('machine_type')
            ->toArray();

        if ($editing && ! in_array($editing->machine_type, $types, true)) {
            array_unshift($types, $editing->machine_type);
        }

        return $types;
    }

    public function destroy(MachineMaintenanceRequirement $machineMaintenanceRequirement)
    {
        $machineMaintenanceRequirement->delete();

        return redirect()
            ->route('machine-maintenance-requirements.index')
            ->with('success', 'Machine type removed successfully.');
    }
}
