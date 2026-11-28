<?php

use App\Exports\PMScheduleExport;
use App\Models\Machine;
use App\Models\PMSchedule;
use App\Models\User;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;

function makeExportTestMachine(array $overrides = []): Machine
{
    return Machine::create(array_merge([
        'machine_number' => 'MC-'.uniqid(),
        'area' => 'WWD',
        'machine_type' => 'NDE',
        'status' => 'ACTIVE',
    ], $overrides));
}

function makeExportTestPmSchedule(Machine $machine, array $overrides = []): PMSchedule
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
    ], $overrides));
}

test('admin can export pm schedules to excel', function () {
    Excel::fake();

    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = makeExportTestMachine();
    makeExportTestPmSchedule($machine);

    $response = $this->actingAs($admin)->get(route('pm-schedules.export'));

    $response->assertOk();
    Excel::assertDownloaded('pm-schedules-'.now()->format('Y-m-d_His').'.xlsx', function () {
        return true;
    });
});

test('koordinator can export pm schedules to excel', function () {
    Excel::fake();

    $koordinator = User::factory()->create([...roleAttributes('KOORDINATOR WWD')]);
    $machine = makeExportTestMachine(['area' => 'WWD']);
    makeExportTestPmSchedule($machine, ['area' => 'WWD']);

    $response = $this->actingAs($koordinator)->get(route('pm-schedules.export'));

    $response->assertOk();
});

test('supervisor cannot export pm schedules to excel', function () {
    $supervisor = User::factory()->create(['role' => User::ROLE_SUPERVISOR]);

    $response = $this->actingAs($supervisor)->get(route('pm-schedules.export'));

    $response->assertForbidden();
});

test('pic cannot export pm schedules to excel', function () {
    $pic = User::factory()->create([...roleAttributes('PIC WWD')]);

    $response = $this->actingAs($pic)->get(route('pm-schedules.export'));

    $response->assertForbidden();
});

test('guest cannot export pm schedules to excel', function () {
    $response = $this->get(route('pm-schedules.export'));

    $response->assertRedirect(route('login'));
});

test('export only includes rows matching the active filters', function () {
    Excel::fake();

    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $machineA = makeExportTestMachine(['machine_type' => 'NDE']);
    $machineB = makeExportTestMachine(['machine_type' => 'DE']);
    makeExportTestPmSchedule($machineA, ['machine_type' => 'NDE']);
    makeExportTestPmSchedule($machineB, ['machine_type' => 'DE']);

    $response = $this->actingAs($admin)
        ->get(route('pm-schedules.export', ['machine_type' => 'NDE']));

    $response->assertOk();

    Excel::assertDownloaded('pm-schedules-'.now()->format('Y-m-d_His').'.xlsx', function (PMScheduleExport $export) {
        $rows = $export->array();

        return count($rows) === 2 // header + 1 matching row
            && $rows[1][1] === 'NDE';
    });
});

test('export row shows dash fallbacks for empty order number, pic and action date', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = makeExportTestMachine();
    $schedule = makeExportTestPmSchedule($machine, [
        'order_number' => null,
        'pic' => null,
        'actual_date' => null,
    ]);

    $export = new PMScheduleExport(collect([$schedule->fresh()]));
    $rows = $export->array();

    expect($rows[1][2])->toBe('-'); // Order Number
    expect($rows[1][4])->toBe('-'); // Action Date
    expect($rows[1][5])->toBe('-'); // PIC
});

test('export row formats pic and status to match the on-screen badge text', function () {
    $machine = makeExportTestMachine();
    $schedule = makeExportTestPmSchedule($machine, [
        'pic' => 'BUDI SANTOSO',
        'status' => 'FINISHED_ON_TIME',
    ]);

    $export = new PMScheduleExport(collect([$schedule->fresh()]));
    $rows = $export->array();

    expect($rows[1][5])->toBe('Budi Santoso');
    expect($rows[1][6])->toBe('FINISHED ON TIME');
});
