<?php

use App\Models\Machine;
use App\Models\MachineProblem;
use App\Models\MachineProblemFinding;
use App\Models\PMProblem;
use App\Models\PMSchedule;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Str;

/**
 * A PM Result row with a problem must also carry its finding and severity,
 * on the online form and on the offline PM_SAVE sync alike.
 */
function rowValidationSchedule(): PMSchedule
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
        'due_date' => Carbon::parse($planDate)->addDays(14),
        'pic' => 'Budi',
        'status' => 'OPEN',
    ]);
}

function rowValidationForm(PMSchedule $pm, array $problems): array
{
    return [
        'order_number' => $pm->order_number,
        'pic' => 'Budi',
        'actual_date' => now()->toDateString(),
        'start_time' => '08:00',
        'end_time' => '10:00',
        'problems' => $problems,
    ];
}

beforeEach(function () {
    $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));
    $this->pm = rowValidationSchedule();
    $this->problem = MachineProblem::create(['machine_type' => 'NDE', 'category' => 'BEARING', 'problem' => 'Bearing noise']);
    $this->finding = MachineProblemFinding::create(['category' => 'bearing', 'finding' => 'Bearing kasar']);
});

test('a problem without a finding is rejected and nothing is saved', function () {
    $this->from(route('pm-schedules.edit', $this->pm))
        ->put(route('pm-schedules.update', $this->pm), rowValidationForm($this->pm, [
            ['problem' => $this->problem->id, 'finding' => '', 'severity' => 'High'],
        ]))
        ->assertRedirect(route('pm-schedules.edit', $this->pm))
        ->assertSessionHasErrors(['problems.0.finding' => 'Choose a finding for this problem.'])
        ->assertSessionDoesntHaveErrors('problems.0.severity');

    expect(PMProblem::where('pm_schedule_id', $this->pm->id)->count())->toBe(0)
        ->and($this->pm->fresh()->status)->toBe('OPEN');
});

test('a problem without a severity is rejected', function () {
    $this->put(route('pm-schedules.update', $this->pm), rowValidationForm($this->pm, [
        ['problem' => $this->problem->id, 'finding' => $this->finding->id, 'severity' => ''],
    ]))->assertSessionHasErrors(['problems.0.severity' => 'Choose a severity for this problem.']);

    expect(PMProblem::count())->toBe(0);
});

test('only the incomplete row is flagged when several rows are sent', function () {
    $other = MachineProblem::create(['machine_type' => 'NDE', 'category' => 'BEARING', 'problem' => 'Bearing hot']);

    $this->put(route('pm-schedules.update', $this->pm), rowValidationForm($this->pm, [
        ['problem' => $this->problem->id, 'finding' => $this->finding->id, 'severity' => 'Low'],
        ['problem' => $other->id, 'finding' => '', 'severity' => ''],
    ]))->assertSessionHasErrors(['problems.1.finding', 'problems.1.severity'])
        ->assertSessionDoesntHaveErrors(['problems.0.finding', 'problems.0.severity']);
});

test('a severity outside Low, Medium, High is rejected', function () {
    $this->put(route('pm-schedules.update', $this->pm), rowValidationForm($this->pm, [
        ['problem' => $this->problem->id, 'finding' => $this->finding->id, 'severity' => 'Extreme'],
    ]))->assertSessionHasErrors(['problems.0.severity' => 'Severity must be Low, Medium, or High.']);
});

test('a complete row saves, and a blank row next to it is still ignored', function () {
    $this->put(route('pm-schedules.update', $this->pm), rowValidationForm($this->pm, [
        ['problem' => $this->problem->id, 'finding' => $this->finding->id, 'severity' => 'Medium'],
        ['problem' => '', 'finding' => '', 'severity' => ''],
    ]))->assertSessionHasNoErrors();

    $saved = PMProblem::where('pm_schedule_id', $this->pm->id)->get();

    expect($saved)->toHaveCount(1)
        ->and($saved->first()->machine_problem_finding_id)->toBe($this->finding->id)
        ->and($saved->first()->severity)->toBe('Medium');
});

test('the edit page shows the rejected rows again with their error, not the saved rows', function () {
    $this->from(route('pm-schedules.edit', $this->pm))
        ->followingRedirects()
        ->put(route('pm-schedules.update', $this->pm), rowValidationForm($this->pm, [
            ['problem' => $this->problem->id, 'finding' => '', 'severity' => 'High'],
        ]))
        ->assertOk()
        ->assertSee('Choose a finding for this problem.')
        ->assertSee('Not saved yet')
        ->assertSee('<option value="High" selected>High</option>', false)
        ->assertSee('id="problem-0-error"', false);
});

test('offline PM_SAVE with an incomplete problem row is rejected the same way', function () {
    $response = $this->postJson(route('api.sync'), [
        'operation_uuid' => (string) Str::uuid(),
        'transaction_type' => 'PM_SAVE',
        'payload' => array_merge(['pm_schedule_id' => $this->pm->id], rowValidationForm($this->pm, [
            ['problem' => $this->problem->id, 'finding' => '', 'severity' => ''],
        ])),
        'expected_state' => ['status' => 'OPEN'],
    ]);

    $response->assertStatus(422)->assertJson(['status' => 'validation_failed']);
    expect($response->json('errors'))->toHaveKeys(['problems.0.finding', 'problems.0.severity'])
        ->and(PMProblem::count())->toBe(0)
        ->and($this->pm->fresh()->status)->toBe('OPEN');
});

test('a missing session start is shown next to the session row', function () {
    $this->from(route('pm-schedules.edit', $this->pm))
        ->followingRedirects()
        ->put(route('pm-schedules.update', $this->pm), [
            'order_number' => $this->pm->order_number,
            'pic' => 'Budi',
            'sessions' => [['id' => '', 'actual_date' => now()->toDateString(), 'start_time' => '', 'end_time' => '']],
        ])
        ->assertOk()
        ->assertSee('id="session-0-error"', false)
        ->assertSee('Enter the start time of this working day.')
        ->assertSee('Not saved yet');
});
