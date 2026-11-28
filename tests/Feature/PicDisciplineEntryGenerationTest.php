<?php

use App\Models\Machine;
use App\Models\MachineChecklist;
use App\Models\PicDisciplineEntry;
use App\Models\PicDisciplineItemType;
use App\Models\PMSchedule;
use App\Models\User;
use App\Services\PMChecklistSaveService;
use Carbon\Carbon;

function pdMachine(array $overrides = []): Machine
{
    return Machine::create(array_merge([
        'machine_number' => 'MC-'.uniqid(),
        'area' => 'WWD',
        'machine_type' => 'NDE',
        'status' => 'ACTIVE',
    ], $overrides));
}

function pdPmSchedule(Machine $machine, array $overrides = []): PMSchedule
{
    $planDate = $overrides['plan_date'] ?? now()->toDateString();

    return PMSchedule::create(array_merge([
        'machine_id' => $machine->id,
        'machine_number' => $machine->machine_number,
        'machine_type' => $machine->machine_type,
        'area' => $machine->area,
        'order_number' => 'ORD-'.uniqid(),
        'plan_date' => $planDate,
        'plan_month' => Carbon::parse($planDate)->format('F'),
        'plan_year' => Carbon::parse($planDate)->format('Y'),
        'due_date' => Carbon::parse($planDate)->addDays(14),
        'pic' => null,
        'status' => 'OPEN',
        'no_problem' => true,
        'no_sparepart' => true,
    ], $overrides));
}

function pdChecklistItem(string $machineType = 'NDE'): MachineChecklist
{
    return MachineChecklist::firstOrCreate(
        ['machine_type' => $machineType, 'checklist_item' => 'Visual Check'],
        ['section' => 'General', 'section_order' => 1, 'item_order' => 1, 'maintenance_type' => 'check']
    );
}

beforeEach(function () {
    PicDisciplineItemType::query()->delete();
    $this->seed(Database\Seeders\PicDisciplineItemTypeSeeder::class);
});

test('saving a pm checklist auto-generates one discipline entry per active item type', function () {
    $machine = pdMachine();
    $pm = pdPmSchedule($machine, ['actual_date' => now()->toDateString()]);
    $checklistItem = pdChecklistItem($machine->machine_type);

    app(PMChecklistSaveService::class)->save($pm, [
        ['machine_checklist_id' => $checklistItem->id, 'check' => 'YES'],
    ]);

    expect(PicDisciplineEntry::where('pm_schedule_id', $pm->id)->count())
        ->toBe(PicDisciplineItemType::where('is_active', true)->count());
});

test('fill pm entry is achieved when the checklist is saved within the grace window', function () {
    $machine = pdMachine();
    $pm = pdPmSchedule($machine, ['actual_date' => now()->subDays(2)->toDateString()]);
    $checklistItem = pdChecklistItem($machine->machine_type);

    app(PMChecklistSaveService::class)->save($pm, [
        ['machine_checklist_id' => $checklistItem->id, 'check' => 'YES'],
    ]);

    $fillPmType = PicDisciplineItemType::where('code', PicDisciplineItemType::CODE_FILL_PM)->first();
    $entry = PicDisciplineEntry::where('pm_schedule_id', $pm->id)->where('item_type_id', $fillPmType->id)->first();

    expect($entry->status)->toBe(PicDisciplineEntry::STATUS_ACHIEVED);
    expect($entry->source)->toBe(PicDisciplineEntry::SOURCE_AUTO);
});

test('fill pm entry is breach when the checklist is saved more than 5 days after actual date', function () {
    $machine = pdMachine();
    $pm = pdPmSchedule($machine, ['actual_date' => now()->subDays(10)->toDateString()]);
    $checklistItem = pdChecklistItem($machine->machine_type);

    // The checklist's created_at timestamp (now, 10 days after actual_date)
    // is what updateStatus()'s H+5 comparison uses — simulate a PIC who
    // physically did the PM 10 days ago but only just saved the checklist.
    app(PMChecklistSaveService::class)->save($pm, [
        ['machine_checklist_id' => $checklistItem->id, 'check' => 'YES'],
    ]);

    $fillPmType = PicDisciplineItemType::where('code', PicDisciplineItemType::CODE_FILL_PM)->first();
    $entry = PicDisciplineEntry::where('pm_schedule_id', $pm->id)->where('item_type_id', $fillPmType->id)->first();

    expect($entry->status)->toBe(PicDisciplineEntry::STATUS_BREACH);
});

test('manual item types start pending with no completed date', function () {
    $machine = pdMachine();
    $pm = pdPmSchedule($machine, ['actual_date' => now()->toDateString()]);
    $checklistItem = pdChecklistItem($machine->machine_type);

    app(PMChecklistSaveService::class)->save($pm, [
        ['machine_checklist_id' => $checklistItem->id, 'check' => 'YES'],
    ]);

    $manualType = PicDisciplineItemType::where('code', 'PASANG_TAG')->first();
    $entry = PicDisciplineEntry::where('pm_schedule_id', $pm->id)->where('item_type_id', $manualType->id)->first();

    expect($entry->source)->toBe(PicDisciplineEntry::SOURCE_MANUAL);
    expect($entry->completed_date)->toBeNull();
    expect($entry->status)->toBe(PicDisciplineEntry::STATUS_PENDING);
});

test('re-saving the checklist does not duplicate discipline entries', function () {
    $machine = pdMachine();
    $pm = pdPmSchedule($machine, ['actual_date' => now()->toDateString()]);
    $checklistItem = pdChecklistItem($machine->machine_type);

    $service = app(PMChecklistSaveService::class);
    $service->save($pm, [['machine_checklist_id' => $checklistItem->id, 'check' => 'YES']]);
    $service->save($pm->fresh(), [['machine_checklist_id' => $checklistItem->id, 'check' => 'YES']]);

    expect(PicDisciplineEntry::where('pm_schedule_id', $pm->id)->count())
        ->toBe(PicDisciplineItemType::where('is_active', true)->count());
});

test('discipline entry pic matches the pm schedule assigned pic user', function () {
    $pic = User::factory()->pic()->forArea('WWD')->create(['name' => 'BUDI SANTOSO']);
    $machine = pdMachine();
    $pm = pdPmSchedule($machine, ['actual_date' => now()->toDateString(), 'pic' => 'BUDI SANTOSO']);
    $checklistItem = pdChecklistItem($machine->machine_type);

    app(PMChecklistSaveService::class)->save($pm, [
        ['machine_checklist_id' => $checklistItem->id, 'check' => 'YES'],
    ]);

    $entry = PicDisciplineEntry::where('pm_schedule_id', $pm->id)->first();

    expect($entry->pic_user_id)->toBe($pic->id);
});
