<?php

use App\Models\Machine;
use App\Models\MachineChecklist;
use App\Models\PMSchedule;
use App\Services\PMChecklistSaveService;
use Carbon\Carbon;

/**
 * Save Checklist must be blocked while a master checklist item's
 * APPLICABLE action column(s) — as determined by its maintenance_type,
 * see pm-schedules/checklist.blade.php — are not all YES.
 *
 * Not every item has a check/clean/lubrication checkbox: only the
 * column(s) matching maintenance_type are rendered at all:
 *   - 'check'       -> only Check is rendered/required.
 *   - 'clean'       -> Check AND Clean are rendered/required.
 *   - 'lubrication' -> Check AND Lubrication are rendered/required.
 *   - 'replace'     -> nothing is required (replace/remarks optional).
 */
function checklistFieldsSchedule(): PMSchedule
{
    $machine = Machine::create([
        'machine_number' => 'MC-'.uniqid(),
        'area' => 'WWD',
        'machine_type' => 'NDE',
        'status' => 'ACTIVE',
    ]);

    $planDate = now()->toDateString();

    return PMSchedule::create([
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
    ]);
}

function addChecklistMasterItem(PMSchedule $pm, string $item, string $maintenanceType = 'check'): MachineChecklist
{
    return MachineChecklist::create([
        'machine_type' => $pm->machine_type,
        'section' => 'General',
        'checklist_item' => $item,
        'maintenance_type' => $maintenanceType,
    ]);
}

// --- 'check'-type items: only Check is rendered/required ------------------

test('check-type item blocked when check is not filled', function () {
    $pm = checklistFieldsSchedule();
    $item = addChecklistMasterItem($pm, 'Outside Ring', 'check');

    $rows = [
        ['machine_checklist_id' => $item->id, 'check' => 'NO', 'replace' => 'NO', 'remarks' => ''],
    ];

    expect(app(PMChecklistSaveService::class)->checklistErrors($pm, $rows))
        ->toBe(['Checklist (1 item belum diisi)']);
});

test('check-type item allowed when check is YES, regardless of clean/lubrication', function () {
    $pm = checklistFieldsSchedule();
    $item = addChecklistMasterItem($pm, 'Outside Ring', 'check');

    $rows = [
        ['machine_checklist_id' => $item->id, 'check' => 'YES', 'replace' => 'NO', 'remarks' => ''],
    ];

    expect(app(PMChecklistSaveService::class)->checklistErrors($pm, $rows))->toBe([]);
});

// --- 'clean'-type items: Check AND Clean are rendered/required ------------

test('clean-type item blocked when clean is not filled even though check is YES', function () {
    $pm = checklistFieldsSchedule();
    $item = addChecklistMasterItem($pm, 'CONE 1', 'clean');

    $rows = [
        ['machine_checklist_id' => $item->id, 'check' => 'YES', 'clean' => 'NO', 'replace' => 'NO', 'remarks' => ''],
    ];

    expect(app(PMChecklistSaveService::class)->checklistErrors($pm, $rows))
        ->toBe(['Checklist (1 item belum diisi)']);
});

test('clean-type item blocked when check is not filled even though clean is YES', function () {
    $pm = checklistFieldsSchedule();
    $item = addChecklistMasterItem($pm, 'CONE 1', 'clean');

    $rows = [
        ['machine_checklist_id' => $item->id, 'check' => 'NO', 'clean' => 'YES', 'replace' => 'NO', 'remarks' => ''],
    ];

    expect(app(PMChecklistSaveService::class)->checklistErrors($pm, $rows))
        ->toBe(['Checklist (1 item belum diisi)']);
});

test('clean-type item allowed when both check and clean are YES', function () {
    $pm = checklistFieldsSchedule();
    $item = addChecklistMasterItem($pm, 'CONE 1', 'clean');

    $rows = [
        ['machine_checklist_id' => $item->id, 'check' => 'YES', 'clean' => 'YES', 'replace' => 'NO', 'remarks' => ''],
    ];

    expect(app(PMChecklistSaveService::class)->checklistErrors($pm, $rows))->toBe([]);
});

// --- 'lubrication'-type items: Check AND Lubrication are rendered/required -

test('lubrication-type item blocked when lubrication is not filled even though check is YES', function () {
    $pm = checklistFieldsSchedule();
    $item = addChecklistMasterItem($pm, 'Cone bearing', 'lubrication');

    $rows = [
        ['machine_checklist_id' => $item->id, 'check' => 'YES', 'lubrication' => 'NO', 'replace' => 'NO', 'remarks' => ''],
    ];

    expect(app(PMChecklistSaveService::class)->checklistErrors($pm, $rows))
        ->toBe(['Checklist (1 item belum diisi)']);
});

test('lubrication-type item allowed when both check and lubrication are YES', function () {
    $pm = checklistFieldsSchedule();
    $item = addChecklistMasterItem($pm, 'Cone bearing', 'lubrication');

    $rows = [
        ['machine_checklist_id' => $item->id, 'check' => 'YES', 'lubrication' => 'YES', 'replace' => 'NO', 'remarks' => ''],
    ];

    expect(app(PMChecklistSaveService::class)->checklistErrors($pm, $rows))->toBe([]);
});

// --- 'replace'-type items: nothing is mandatory ----------------------------

test('replace-type item never blocks save, even with no row submitted at all', function () {
    $pm = checklistFieldsSchedule();
    addChecklistMasterItem($pm, 'Gearbox Unit', 'replace');

    expect(app(PMChecklistSaveService::class)->checklistErrors($pm, []))->toBe([]);
});

test('replace-type item never blocks save even when replace checkbox itself is NO', function () {
    $pm = checklistFieldsSchedule();
    $item = addChecklistMasterItem($pm, 'Gearbox Unit', 'replace');

    $rows = [
        ['machine_checklist_id' => $item->id, 'replace' => 'NO', 'remarks' => ''],
    ];

    expect(app(PMChecklistSaveService::class)->checklistErrors($pm, $rows))->toBe([]);
});

// --- missing rows / mixed items --------------------------------------------

test('blocked when an applicable item is missing from submission entirely', function () {
    $pm = checklistFieldsSchedule();
    addChecklistMasterItem($pm, 'Outside Ring', 'check');

    expect(app(PMChecklistSaveService::class)->checklistErrors($pm, []))
        ->toBe(['Checklist (1 item belum diisi)']);
});

test('counts only unfilled items across a mix of maintenance types', function () {
    $pm = checklistFieldsSchedule();
    $checkItem = addChecklistMasterItem($pm, 'Outside Ring', 'check');
    $cleanItem = addChecklistMasterItem($pm, 'CONE 1', 'clean');
    $lubeItem = addChecklistMasterItem($pm, 'Cone bearing', 'lubrication');
    $replaceItem = addChecklistMasterItem($pm, 'Gearbox Unit', 'replace');

    $rows = [
        ['machine_checklist_id' => $checkItem->id, 'check' => 'YES', 'replace' => 'NO', 'remarks' => ''],
        ['machine_checklist_id' => $cleanItem->id, 'check' => 'YES', 'clean' => 'NO', 'replace' => 'NO', 'remarks' => ''], // unfilled
        ['machine_checklist_id' => $lubeItem->id, 'check' => 'YES', 'lubrication' => 'YES', 'replace' => 'NO', 'remarks' => ''],
        ['machine_checklist_id' => $replaceItem->id, 'replace' => 'NO', 'remarks' => ''],
    ];

    expect(app(PMChecklistSaveService::class)->checklistErrors($pm, $rows))
        ->toBe(['Checklist (1 item belum diisi)']);
});

test('no master items for the machine type means nothing is blocked', function () {
    $pm = checklistFieldsSchedule();

    expect(app(PMChecklistSaveService::class)->checklistErrors($pm, []))->toBe([]);
});
