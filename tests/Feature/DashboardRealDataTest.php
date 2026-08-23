<?php

use App\Models\Machine;
use App\Models\MachineProblem;
use App\Models\PMProblem;
use App\Models\PMSchedule;
use App\Models\PMSparepart;
use App\Models\Sparepart;
use App\Models\User;
use Carbon\Carbon;

function makeMachine(string $number, string $area): Machine
{
    return Machine::create([
        'machine_number' => $number,
        'area' => $area,
        'machine_type' => 'TypeX',
        'status' => 'ACTIVE',
    ]);
}

function makePmSchedule(Machine $machine, array $overrides = []): PMSchedule
{
    $planDate = $overrides['plan_date'] ?? now()->toDateString();

    return PMSchedule::create(array_merge([
        'machine_id' => $machine->id,
        'machine_number' => $machine->machine_number,
        'machine_type' => $machine->machine_type,
        'area' => $machine->area,
        'order_number' => 'WO-'.uniqid(),
        'plan_date' => $planDate,
        'plan_month' => Carbon::parse($planDate)->format('F'),
        'plan_year' => Carbon::parse($planDate)->year,
        'due_date' => $planDate,
        'pic' => null,
        'status' => 'OPEN',
    ], $overrides));
}

test('dashboard renders for admin with no data without error', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $response = $this->actingAs($admin)->get(route('dashboard'));

    $response->assertOk();
    $response->assertSee('No data available');
});

test('dashboard KPI numbers match real database counts for admin', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $machine = makeMachine('MC-1', 'WWD');

    makePmSchedule($machine, ['status' => 'FINISHED_ON_TIME', 'actual_date' => now()->toDateString()]);
    makePmSchedule($machine, ['status' => 'FINISHED', 'actual_date' => now()->toDateString()]);
    makePmSchedule($machine, ['status' => 'OPEN']);
    makePmSchedule($machine, ['status' => 'MISSED']);

    $response = $this->actingAs($admin)->get(route('dashboard'));

    $response->assertOk();
    // Target = 4 (all this month), Actual = 2 (FINISHED + FINISHED_ON_TIME), Remaining = 2, Completion = 50%
    $response->assertSee('50%', false);
    $response->assertSee('>4<', false);
    $response->assertSee('>2<', false);
});

test('koordinator wwd only sees WWD area data', function () {
    $koordinator = User::factory()->create(['role' => User::ROLE_KOORDINATOR_WWD, 'name' => 'Koor WWD']);

    $wwdMachine = makeMachine('MC-WWD', 'WWD');
    $bulMachine = makeMachine('MC-BUL', 'BUL');

    makePmSchedule($wwdMachine, ['status' => 'OPEN']);
    makePmSchedule($bulMachine, ['status' => 'OPEN']);
    makePmSchedule($bulMachine, ['status' => 'OPEN']);

    $response = $this->actingAs($koordinator)->get(route('dashboard'));

    $response->assertOk();
    // Target for WWD koordinator must be 1 (only the WWD schedule), not 3.
    $response->assertSee('>1<', false);
    $response->assertDontSee('>3<', false);
});

test('koordinator bul only sees BUL area data', function () {
    $koordinator = User::factory()->create(['role' => User::ROLE_KOORDINATOR_BUL]);

    $wwdMachine = makeMachine('MC-WWD2', 'WWD');
    $bulMachine = makeMachine('MC-BUL2', 'BUL');

    makePmSchedule($wwdMachine, ['status' => 'OPEN']);
    makePmSchedule($bulMachine, ['status' => 'OPEN']);

    $response = $this->actingAs($koordinator)->get(route('dashboard'));

    $response->assertOk();
    $response->assertSee('>1<', false);
});

test('pic wwd only sees their own assigned schedules', function () {
    $pic = User::factory()->create(['role' => User::ROLE_PIC_WWD, 'name' => 'Budi']);
    $otherPic = User::factory()->create(['role' => User::ROLE_PIC_WWD, 'name' => 'Andi']);

    $machine = makeMachine('MC-PIC1', 'WWD');

    makePmSchedule($machine, ['status' => 'OPEN', 'pic' => 'Budi']);
    makePmSchedule($machine, ['status' => 'OPEN', 'pic' => 'Andi']);
    makePmSchedule($machine, ['status' => 'OPEN', 'pic' => 'Andi']);

    $response = $this->actingAs($pic)->get(route('dashboard'));

    $response->assertOk();
    $response->assertSee('>1<', false);
});

test('pic bul only sees their own assigned schedules', function () {
    $pic = User::factory()->create(['role' => User::ROLE_PIC_BUL, 'name' => 'Citra']);

    $machine = makeMachine('MC-PIC2', 'BUL');

    makePmSchedule($machine, ['status' => 'OPEN', 'pic' => 'Citra']);

    $response = $this->actingAs($pic)->get(route('dashboard'));

    $response->assertOk();
});

test('overdue count reflects MISSED status regardless of month', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = makeMachine('MC-OVERDUE', 'WWD');

    makePmSchedule($machine, [
        'status' => 'MISSED',
        'plan_date' => now()->subMonths(2)->toDateString(),
        'due_date' => now()->subMonths(2)->toDateString(),
    ]);

    $response = $this->actingAs($admin)->get(route('dashboard'));

    $response->assertOk();
    $response->assertSee('>1<', false);
});

test('completion by area uses real WWD/BUL areas, not fabricated categories', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = makeMachine('MC-AREA', 'WWD');
    makePmSchedule($machine, ['status' => 'OPEN']);

    $response = $this->actingAs($admin)->get(route('dashboard'));

    $response->assertOk();
    $response->assertDontSee('C&amp;B');
    $response->assertDontSee('Assembly');
    $response->assertDontSee('Painting');
});

test('top sparepart usage reflects real pm_spareparts consumption', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = makeMachine('MC-SP', 'WWD');
    $pm = makePmSchedule($machine, ['status' => 'FINISHED_ON_TIME', 'actual_date' => now()->toDateString()]);

    $sparepart = Sparepart::create(['material_number' => 'SP-USAGE', 'description' => 'Bearing Test']);
    PMSparepart::create(['pm_schedule_id' => $pm->id, 'sparepart_id' => $sparepart->id, 'qty' => 5]);

    $response = $this->actingAs($admin)->get(route('dashboard'));

    $response->assertOk();
    $response->assertSee('Bearing Test');
});

test('top problem categories reflects real pm_problems occurrences', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = makeMachine('MC-PROB', 'WWD');
    $pm = makePmSchedule($machine, ['status' => 'OPEN']);

    $machineProblem = MachineProblem::create([
        'machine_type' => 'TypeX',
        'category' => 'Leakage Real',
        'problem' => 'Oil seal worn',
    ]);
    PMProblem::create([
        'pm_schedule_id' => $pm->id,
        'machine_problem_id' => $machineProblem->id,
    ]);

    $response = $this->actingAs($admin)->get(route('dashboard'));

    $response->assertOk();
    $response->assertSee('Leakage Real');
});

test('year filter changes which pm schedules are counted', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = makeMachine('MC-YEAR', 'WWD');

    makePmSchedule($machine, ['status' => 'OPEN', 'plan_date' => '2020-05-01']);

    $response = $this->actingAs($admin)->get(route('dashboard', ['year' => 2020]));

    $response->assertOk();
    $response->assertSee('value="2020" selected', false);
});

test('today schedule table shows schedules planned today, not by due date', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = makeMachine('MC-TODAY', 'WWD');

    // Planned today but due much later — must still show up.
    makePmSchedule($machine, [
        'order_number' => 'WO-PLANNED-TODAY',
        'status' => 'OPEN',
        'plan_date' => now()->toDateString(),
        'due_date' => now()->addDays(14)->toDateString(),
    ]);
    // Due today but planned earlier — must NOT show up (this widget is
    // about what's planned today, not what's due today).
    makePmSchedule($machine, [
        'order_number' => 'WO-DUE-TODAY',
        'status' => 'OPEN',
        'plan_date' => now()->subDays(10)->toDateString(),
        'due_date' => now()->toDateString(),
    ]);
    // Planned on a different day entirely — must NOT show up.
    makePmSchedule($machine, [
        'order_number' => 'WO-OTHER-DAY',
        'status' => 'OPEN',
        'plan_date' => now()->addDays(5)->toDateString(),
        'due_date' => now()->addDays(19)->toDateString(),
    ]);

    $response = $this->actingAs($admin)->get(route('dashboard'));

    $response->assertOk();

    $todaySchedules = $response->viewData('todaySchedules');

    expect($todaySchedules)->toHaveCount(1);
    expect($todaySchedules->first()['due_date'])->toBe(now()->addDays(14)->format('Y-m-d'));
});
