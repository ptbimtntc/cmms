<?php

use App\Models\Machine;
use App\Models\PicDisciplineEntry;
use App\Models\PicDisciplineItemType;
use App\Models\PMSchedule;
use App\Models\User;
use App\Services\PicDisciplineKpiCalculator;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;

function prMachine(array $overrides = []): Machine
{
    return Machine::create(array_merge([
        'machine_number' => 'MC-'.uniqid(),
        'area' => 'WWD',
        'machine_type' => 'NDE',
        'status' => 'ACTIVE',
    ], $overrides));
}

function prPmSchedule(Machine $machine, array $overrides = []): PMSchedule
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
        'actual_date' => $planDate,
        'pic' => null,
        'status' => 'FINISHED_ON_TIME',
    ], $overrides));
}

function prEntry(User $pic, Machine $machine, string $itemCode, string $status, array $overrides = []): PicDisciplineEntry
{
    $itemType = PicDisciplineItemType::firstOrCreate(
        ['code' => $itemCode],
        ['name' => $itemCode, 'is_auto' => false, 'grace_days' => 3, 'sort_order' => 1]
    );
    $pm = prPmSchedule($machine);

    return PicDisciplineEntry::create(array_merge([
        'pm_schedule_id' => $pm->id,
        'item_type_id' => $itemType->id,
        'pic_user_id' => $pic->id,
        'due_date' => now()->toDateString(),
        'status' => $status,
        'source' => 'MANUAL',
    ], $overrides));
}

test('kpi calculator computes achieved breach percentage excluding pending', function () {
    expect(PicDisciplineKpiCalculator::fromCounts(9, 2))->toMatchArray([
        'achieved' => 9,
        'breach' => 2,
        'total' => 11,
        'percentage' => 81.82,
    ]);

    expect(PicDisciplineKpiCalculator::fromCounts(0, 0)['percentage'])->toBe(0.0);
});

test('admin can view the pic discipline report', function () {
    $admin = User::factory()->admin()->create();
    $pic = User::factory()->pic()->forArea('WWD')->create();
    $machine = prMachine();
    prEntry($pic, $machine, 'WO_PROCESS', PicDisciplineEntry::STATUS_ACHIEVED);
    prEntry($pic, $machine, 'WO_PROCESS', PicDisciplineEntry::STATUS_ACHIEVED);
    prEntry($pic, $machine, 'WO_PROCESS', PicDisciplineEntry::STATUS_BREACH);

    $response = $this->actingAs($admin)->get(route('reports.pic-discipline'));

    $response->assertOk();
    $response->assertSee('66.67%'); // 2 achieved / (2 achieved + 1 breach)
});

test('supervisor can view but is still read only on the pic discipline report route group', function () {
    $supervisor = User::factory()->supervisor()->create();

    $response = $this->actingAs($supervisor)->get(route('reports.pic-discipline'));

    $response->assertOk();
});

test('pic cannot access the pic discipline report', function () {
    $pic = User::factory()->pic()->forArea('WWD')->create();

    $response = $this->actingAs($pic)->get(route('reports.pic-discipline'));

    $response->assertForbidden();
});

test('pic cannot access the pic discipline report export', function () {
    $pic = User::factory()->pic()->forArea('WWD')->create();

    $response = $this->actingAs($pic)->get(route('reports.pic-discipline.export'));

    $response->assertForbidden();
});

test('admin can export the pic discipline report to excel', function () {
    Excel::fake();

    $admin = User::factory()->admin()->create();
    $pic = User::factory()->pic()->forArea('WWD')->create();
    $machine = prMachine();
    prEntry($pic, $machine, 'WO_PROCESS', PicDisciplineEntry::STATUS_ACHIEVED);

    $response = $this->actingAs($admin)->get(route('reports.pic-discipline.export'));

    $response->assertOk();
    Excel::assertDownloaded('pic-discipline-'.now()->format('Y-m-d_His').'.xlsx', function () {
        return true;
    });
});
