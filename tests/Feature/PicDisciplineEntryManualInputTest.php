<?php

use App\Models\Machine;
use App\Models\PicDisciplineEntry;
use App\Models\PicDisciplineItemType;
use App\Models\PMSchedule;
use App\Models\User;
use Carbon\Carbon;

function pdiMachine(array $overrides = []): Machine
{
    return Machine::create(array_merge([
        'machine_number' => 'MC-'.uniqid(),
        'area' => 'WWD',
        'machine_type' => 'NDE',
        'status' => 'ACTIVE',
    ], $overrides));
}

function pdiPmSchedule(Machine $machine, array $overrides = []): PMSchedule
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
        'actual_date' => now()->toDateString(),
        'pic' => null,
        'status' => 'IN_PROGRESS',
    ], $overrides));
}

function pdiManualEntry(array $overrides = []): PicDisciplineEntry
{
    $itemType = PicDisciplineItemType::firstOrCreate(
        ['code' => 'PASANG_TAG'],
        ['name' => 'Pasang Tag', 'is_auto' => false, 'grace_days' => 3, 'sort_order' => 1]
    );
    $machine = pdiMachine();
    $pm = pdiPmSchedule($machine);

    return PicDisciplineEntry::create(array_merge([
        'pm_schedule_id' => $pm->id,
        'item_type_id' => $itemType->id,
        'due_date' => now()->addDays(3)->toDateString(),
        'status' => PicDisciplineEntry::STATUS_PENDING,
        'source' => PicDisciplineEntry::SOURCE_MANUAL,
    ], $overrides));
}

test('admin can view the manual input grid', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->get(route('pic-discipline.entries.index'));

    $response->assertOk();
});

test('koordinator can view the manual input grid', function () {
    $koordinator = User::factory()->koordinator()->forArea('WWD')->create();

    $response = $this->actingAs($koordinator)->get(route('pic-discipline.entries.index'));

    $response->assertOk();
});

test('supervisor can view the manual input grid read only', function () {
    $supervisor = User::factory()->supervisor()->create();

    $response = $this->actingAs($supervisor)->get(route('pic-discipline.entries.index'));

    $response->assertOk();
});

test('pic cannot access the manual input grid', function () {
    $pic = User::factory()->pic()->forArea('WWD')->create();

    $response = $this->actingAs($pic)->get(route('pic-discipline.entries.index'));

    $response->assertForbidden();
});

test('admin can mark a manual entry achieved by setting a completed date on time', function () {
    $admin = User::factory()->admin()->create();
    $entry = pdiManualEntry(['due_date' => now()->addDays(2)->toDateString()]);

    $response = $this->actingAs($admin)->post(route('pic-discipline.entries.update', $entry), [
        'completed_date' => now()->toDateString(),
    ]);

    $response->assertOk()->assertJson(['status' => PicDisciplineEntry::STATUS_ACHIEVED]);
    expect($entry->fresh()->status)->toBe(PicDisciplineEntry::STATUS_ACHIEVED);
});

test('admin marking a completed date after due date records it as breach', function () {
    $admin = User::factory()->admin()->create();
    $entry = pdiManualEntry(['due_date' => now()->subDays(3)->toDateString()]);

    $response = $this->actingAs($admin)->post(route('pic-discipline.entries.update', $entry), [
        'completed_date' => now()->toDateString(),
    ]);

    $response->assertOk()->assertJson(['status' => PicDisciplineEntry::STATUS_BREACH]);
});

test('supervisor cannot update a manual entry', function () {
    $supervisor = User::factory()->supervisor()->create();
    $entry = pdiManualEntry();

    $response = $this->actingAs($supervisor)->post(route('pic-discipline.entries.update', $entry), [
        'completed_date' => now()->toDateString(),
    ]);

    $response->assertForbidden();
    expect($entry->fresh()->status)->toBe(PicDisciplineEntry::STATUS_PENDING);
});

test('pic cannot update a manual entry', function () {
    $pic = User::factory()->pic()->forArea('WWD')->create();
    $entry = pdiManualEntry();

    $response = $this->actingAs($pic)->post(route('pic-discipline.entries.update', $entry), [
        'completed_date' => now()->toDateString(),
    ]);

    $response->assertForbidden();
});

test('koordinator from a different area cannot update another areas entry', function () {
    $koordinator = User::factory()->koordinator()->forArea('BUL')->create();
    $entry = pdiManualEntry(); // belongs to a WWD pm schedule

    $response = $this->actingAs($koordinator)->post(route('pic-discipline.entries.update', $entry), [
        'completed_date' => now()->toDateString(),
    ]);

    $response->assertForbidden();
});
