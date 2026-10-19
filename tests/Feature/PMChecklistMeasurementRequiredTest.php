<?php

use App\Models\Machine;
use App\Models\MachineMeasurement;
use App\Models\MachineProblem;
use App\Models\PMMeasurement;
use App\Models\PMProblem;
use App\Models\PMSchedule;
use App\Models\PMSparepart;
use App\Models\Sparepart;
use App\Services\PMChecklistSaveService;
use Carbon\Carbon;

/**
 * Save Checklist must be blocked while any measurement actual value is
 * still empty. Before this rule, a measurement row existed (created by
 * Fill PM even with blank values), so the Measurement check passed.
 */
function measurementGateSchedule(): PMSchedule
{
    $machine = Machine::create([
        'machine_number' => 'MC-'.uniqid(),
        'area' => 'WWD',
        'machine_type' => 'NDE',
        'status' => 'ACTIVE',
    ]);

    $planDate = now()->toDateString();

    $pm = PMSchedule::create([
        'machine_id' => $machine->id,
        'machine_number' => $machine->machine_number,
        'machine_type' => $machine->machine_type,
        'area' => $machine->area,
        'order_number' => 'ORD-'.uniqid(),
        'plan_date' => $planDate,
        'plan_month' => Carbon::parse($planDate)->format('F'),
        'plan_year' => Carbon::parse($planDate)->format('Y'),
        'due_date' => Carbon::parse($planDate)->addDays(30),
        'pic' => 'Budi',
        'status' => 'IN_PROGRESS',
        'actual_date' => $planDate,
        'oil_change' => 'YES',
        'greasing' => 'YES',
        'wo_zsbp' => 'YES',
        'remarks' => 'OK',
    ]);

    $mp = MachineProblem::create(['machine_type' => 'NDE', 'category' => 'General', 'problem' => 'Belt Retak']);
    PMProblem::create(['pm_schedule_id' => $pm->id, 'machine_problem_id' => $mp->id, 'severity' => 'Low']);

    $sp = Sparepart::create(['material_number' => 'SP-'.uniqid(), 'description' => 'Test Sparepart']);
    PMSparepart::create(['pm_schedule_id' => $pm->id, 'sparepart_id' => $sp->id, 'qty' => 1]);

    return $pm;
}

function addPmMeasurement(PMSchedule $pm, ?string $value): void
{
    $mm = MachineMeasurement::create([
        'machine_type' => 'NDE',
        'measurement_item' => 'Item '.uniqid(),
        'unit' => 'mm/s',
    ]);

    PMMeasurement::create([
        'pm_schedule_id' => $pm->id,
        'machine_measurement_id' => $mm->id,
        'measurement_item' => $mm->measurement_item,
        'measurement_value' => $value,
        'unit' => 'mm/s',
    ]);
}

test('Save Checklist blocked when no measurement exists', function () {
    $pm = measurementGateSchedule();

    expect(app(PMChecklistSaveService::class)->completionErrors($pm))
        ->toContain('Measurement');
});

test('Save Checklist blocked when a measurement actual value is empty', function () {
    $pm = measurementGateSchedule();
    addPmMeasurement($pm, '3.2');
    addPmMeasurement($pm, null);

    expect(app(PMChecklistSaveService::class)->completionErrors($pm))
        ->toContain('Measurement (nilai actual belum diisi)');
});

test('Save Checklist blocked when a measurement actual value is only whitespace', function () {
    $pm = measurementGateSchedule();
    addPmMeasurement($pm, '   ');

    expect(app(PMChecklistSaveService::class)->completionErrors($pm))
        ->toContain('Measurement (nilai actual belum diisi)');
});

test('Save Checklist allowed when every measurement actual value is filled', function () {
    $pm = measurementGateSchedule();
    addPmMeasurement($pm, '3.2');
    addPmMeasurement($pm, '0');

    expect(app(PMChecklistSaveService::class)->completionErrors($pm))->toBe([]);
});
