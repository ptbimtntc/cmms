<?php

use App\Models\Machine;
use App\Models\MachineProblem;
use App\Models\PMProblem;
use App\Models\PMSchedule;
use App\Models\User;
use Carbon\Carbon;

function reportProblemMachine(array $overrides = []): Machine
{
    return Machine::create(array_merge([
        'machine_number' => 'MC-'.uniqid(),
        'area' => 'WWD',
        'machine_type' => 'NDE',
        'status' => 'ACTIVE',
    ], $overrides));
}

function reportProblemPmSchedule(Machine $machine, array $overrides = []): PMSchedule
{
    $planDate = $overrides['plan_date'] ?? now()->toDateString();
    $actualDate = $overrides['actual_date'] ?? $planDate;

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
        'actual_date' => $actualDate,
        'pic' => null,
        'status' => 'FINISHED',
    ], $overrides));
}

function reportMachineProblem(array $overrides = []): MachineProblem
{
    return MachineProblem::create(array_merge([
        'machine_type' => 'NDE',
        'category' => 'Belt',
        'problem' => 'Belt Retak',
    ], $overrides));
}

function reportPmProblem(PMSchedule $pmSchedule, MachineProblem $machineProblem, array $overrides = []): PMProblem
{
    return PMProblem::create(array_merge([
        'pm_schedule_id' => $pmSchedule->id,
        'machine_problem_id' => $machineProblem->id,
        'severity' => 'Medium',
    ], $overrides));
}

test('report page requires authentication', function () {
    $this->get(route('reports.problem'))->assertRedirect(route('login'));
});

test('guest role cannot access the problem report', function () {
    $guest = User::factory()->create(['role' => User::ROLE_GUEST]);

    $this->actingAs($guest)->get(route('reports.problem'))->assertForbidden();
});

test('area all shows both wwd and bul problems for admin', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $wwdMachine = reportProblemMachine(['area' => 'WWD']);
    $bulMachine = reportProblemMachine(['area' => 'BUL', 'machine_type' => 'BF']);

    reportPmProblem(reportProblemPmSchedule($wwdMachine, ['actual_date' => '2026-08-01']), reportMachineProblem());
    reportPmProblem(reportProblemPmSchedule($bulMachine, ['actual_date' => '2026-08-02']), reportMachineProblem(['machine_type' => 'BF']));

    $response = $this->actingAs($admin)->get(route('reports.problem', ['year' => 2026, 'month' => 8]));

    $response->assertOk();
    expect($response->viewData('summary')['total_problems'])->toBe(2);
});

test('area wwd filter narrows to wwd problems only for admin', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $wwdMachine = reportProblemMachine(['area' => 'WWD']);
    $bulMachine = reportProblemMachine(['area' => 'BUL', 'machine_type' => 'BF']);

    reportPmProblem(reportProblemPmSchedule($wwdMachine, ['actual_date' => '2026-08-01']), reportMachineProblem());
    reportPmProblem(reportProblemPmSchedule($bulMachine, ['actual_date' => '2026-08-02']), reportMachineProblem(['machine_type' => 'BF']));

    $response = $this->actingAs($admin)->get(route('reports.problem', ['year' => 2026, 'month' => 8, 'area' => 'WWD']));

    $response->assertOk();
    expect($response->viewData('summary')['total_problems'])->toBe(1);
});

test('area bul filter narrows to bul problems only for admin', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $wwdMachine = reportProblemMachine(['area' => 'WWD']);
    $bulMachine = reportProblemMachine(['area' => 'BUL', 'machine_type' => 'BF']);

    reportPmProblem(reportProblemPmSchedule($wwdMachine, ['actual_date' => '2026-08-01']), reportMachineProblem());
    reportPmProblem(reportProblemPmSchedule($bulMachine, ['actual_date' => '2026-08-02']), reportMachineProblem(['machine_type' => 'BF']));

    $response = $this->actingAs($admin)->get(route('reports.problem', ['year' => 2026, 'month' => 8, 'area' => 'BUL']));

    $response->assertOk();
    expect($response->viewData('summary')['total_problems'])->toBe(1);
});

test('koordinator bul area is fixed regardless of the area filter param', function () {
    $koordinator = User::factory()->create(['role' => User::ROLE_KOORDINATOR_BUL]);
    $wwdMachine = reportProblemMachine(['area' => 'WWD']);
    $bulMachine = reportProblemMachine(['area' => 'BUL', 'machine_type' => 'BF']);

    reportPmProblem(reportProblemPmSchedule($wwdMachine, ['actual_date' => '2026-08-01']), reportMachineProblem());
    reportPmProblem(reportProblemPmSchedule($bulMachine, ['actual_date' => '2026-08-02']), reportMachineProblem(['machine_type' => 'BF']));

    $response = $this->actingAs($koordinator)->get(route('reports.problem', ['year' => 2026, 'month' => 8, 'area' => 'WWD']));

    $response->assertOk();
    expect($response->viewData('summary')['total_problems'])->toBe(1);
});

test('pic only sees problems from their own pm schedules', function () {
    $pic = User::factory()->create(['role' => User::ROLE_PIC_WWD, 'name' => 'Andi']);
    $machine = reportProblemMachine();

    reportPmProblem(reportProblemPmSchedule($machine, ['pic' => 'Andi', 'actual_date' => '2026-08-01']), reportMachineProblem());
    reportPmProblem(reportProblemPmSchedule($machine, ['pic' => 'Budi', 'actual_date' => '2026-08-02']), reportMachineProblem());

    $response = $this->actingAs($pic)->get(route('reports.problem', ['year' => 2026, 'month' => 8]));

    $response->assertOk();
    expect($response->viewData('summary')['total_problems'])->toBe(1);
});

test('year filter isolates problems from other years', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = reportProblemMachine();

    reportPmProblem(reportProblemPmSchedule($machine, ['actual_date' => '2026-01-15']), reportMachineProblem());
    reportPmProblem(reportProblemPmSchedule($machine, ['actual_date' => '2025-01-15']), reportMachineProblem());

    $response = $this->actingAs($admin)->get(route('reports.problem', ['year' => 2026]));

    $response->assertOk();
    expect($response->viewData('summary')['total_problems'])->toBe(1);
});

test('month filter isolates problems from other months', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = reportProblemMachine();

    reportPmProblem(reportProblemPmSchedule($machine, ['actual_date' => '2026-08-05']), reportMachineProblem());
    reportPmProblem(reportProblemPmSchedule($machine, ['actual_date' => '2026-09-05']), reportMachineProblem());

    $response = $this->actingAs($admin)->get(route('reports.problem', ['year' => 2026, 'month' => 8]));

    $response->assertOk();
    expect($response->viewData('summary')['total_problems'])->toBe(1);
});

test('machine filter narrows to the selected machine', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machineA = reportProblemMachine(['machine_number' => 'MC-A']);
    $machineB = reportProblemMachine(['machine_number' => 'MC-B']);

    reportPmProblem(reportProblemPmSchedule($machineA, ['actual_date' => '2026-08-01']), reportMachineProblem());
    reportPmProblem(reportProblemPmSchedule($machineB, ['actual_date' => '2026-08-02']), reportMachineProblem());

    $response = $this->actingAs($admin)->get(route('reports.problem', [
        'year' => 2026, 'month' => 8, 'machine' => 'MC-A',
    ]));

    $response->assertOk();
    expect($response->viewData('problems')->pluck('machine_number')->all())->toBe(['MC-A']);
});

test('category filter narrows results', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = reportProblemMachine();

    reportPmProblem(reportProblemPmSchedule($machine, ['actual_date' => '2026-08-01']), reportMachineProblem(['category' => 'Belt']));
    reportPmProblem(reportProblemPmSchedule($machine, ['actual_date' => '2026-08-02']), reportMachineProblem(['category' => 'Capstan']));

    $response = $this->actingAs($admin)->get(route('reports.problem', [
        'year' => 2026, 'month' => 8, 'category' => 'Belt',
    ]));

    $response->assertOk();
    expect($response->viewData('problems')->total())->toBe(1);
});

test('search matches machine number problem and order number', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = reportProblemMachine();

    reportPmProblem(
        reportProblemPmSchedule($machine, ['order_number' => 'UNIQUE-ORD-001']),
        reportMachineProblem(['problem' => 'Some Problem'])
    );
    reportPmProblem(
        reportProblemPmSchedule($machine, ['order_number' => 'OTHER-ORD-002']),
        reportMachineProblem(['problem' => 'Other Problem'])
    );

    $response = $this->actingAs($admin)->get(route('reports.problem', ['search' => 'UNIQUE-ORD-001']));

    $response->assertOk();
    expect($response->viewData('problems')->total())->toBe(1);
});

test('top category ranks by count', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = reportProblemMachine();

    $beltProblem = reportMachineProblem(['category' => 'Belt', 'problem' => 'Belt Retak']);
    $capstanProblem = reportMachineProblem(['category' => 'Capstan', 'problem' => 'Capstan Oleng']);

    reportPmProblem(reportProblemPmSchedule($machine, ['actual_date' => '2026-08-01']), $beltProblem);
    reportPmProblem(reportProblemPmSchedule($machine, ['actual_date' => '2026-08-02']), $beltProblem);
    reportPmProblem(reportProblemPmSchedule($machine, ['actual_date' => '2026-08-03']), $capstanProblem);

    $response = $this->actingAs($admin)->get(route('reports.problem', ['year' => 2026, 'month' => 8]));

    $response->assertOk();
    expect($response->viewData('summary')['top_category'])->toBe(['label' => 'Belt', 'count' => 2]);
    expect($response->viewData('topCategories')->first())->toMatchArray(['label' => 'Belt', 'value' => 2, 'percent' => 100]);
});

test('repeated problem detects the same machine and problem recorded more than once', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = reportProblemMachine();
    $problem = reportMachineProblem(['problem' => 'Bearing Noise']);
    $otherProblem = reportMachineProblem(['problem' => 'Only Once']);

    reportPmProblem(reportProblemPmSchedule($machine, ['actual_date' => '2026-08-01']), $problem);
    reportPmProblem(reportProblemPmSchedule($machine, ['actual_date' => '2026-08-02']), $problem);
    reportPmProblem(reportProblemPmSchedule($machine, ['actual_date' => '2026-08-03']), $otherProblem);

    $response = $this->actingAs($admin)->get(route('reports.problem', ['year' => 2026, 'month' => 8]));

    $response->assertOk();
    $repeated = $response->viewData('repeatedProblems');

    expect($repeated)->toHaveCount(1);
    expect($repeated->first()->problem)->toBe('Bearing Noise');
    expect((int) $repeated->first()->occurrences)->toBe(2);
});

test('pagination preserves active filters across pages', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = reportProblemMachine();

    for ($i = 0; $i < 25; $i++) {
        reportPmProblem(reportProblemPmSchedule($machine, ['actual_date' => now()->subDays($i)->toDateString()]), reportMachineProblem());
    }

    $response = $this->actingAs($admin)->get(route('reports.problem', ['machine' => $machine->machine_number, 'page' => 2]));

    $response->assertOk();
    $response->assertSee('machine='.$machine->machine_number, false);
    expect($response->viewData('problems')->currentPage())->toBe(2);
});

test('empty state is shown when no problem data matches', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $response = $this->actingAs($admin)->get(route('reports.problem', ['search' => 'no-such-problem']));

    $response->assertOk();
    $response->assertSee('No Problem data found.');
    expect($response->viewData('summary')['total_problems'])->toBe(0);
});
