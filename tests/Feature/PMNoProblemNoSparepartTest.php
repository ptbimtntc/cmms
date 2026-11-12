<?php

use App\Models\Machine;
use App\Models\MachineProblem;
use App\Models\MandatorySparepart;
use App\Models\PMProblem;
use App\Models\PMSchedule;
use App\Models\PMSparepart;
use App\Models\Sparepart;
use App\Models\User;
use App\Services\PMChecklistSaveService;
use Carbon\Carbon;
use Illuminate\Support\Str;

/**
 * "No problem found" / "No sparepart used" let a PM with genuinely nothing
 * to record pass the Fill PM completion check, instead of looping back to
 * Fill PM forever.
 */
function noneMachine(): Machine
{
    return Machine::create([
        'machine_number' => 'MC-'.uniqid(),
        'area' => 'WWD',
        'machine_type' => 'NDE',
        'status' => 'ACTIVE',
    ]);
}

function noneSchedule(Machine $machine): PMSchedule
{
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

function noneFormData(PMSchedule $pm, array $overrides = []): array
{
    return array_merge([
        'order_number' => $pm->order_number,
        'pic' => 'Budi',
        'actual_date' => now()->toDateString(),
        'start_time' => '08:00',
        'end_time' => '10:00',
    ], $overrides);
}

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $this->actingAs($this->admin);
    $this->pm = noneSchedule(noneMachine());
});

test('without confirmation an empty problem and sparepart list still blocks completion', function () {
    $this->put(route('pm-schedules.update', $this->pm), noneFormData($this->pm));

    expect(app(PMChecklistSaveService::class)->completionErrors($this->pm->fresh()))
        ->toContain('Problem')
        ->toContain('Sparepart');
});

test('ticking both boxes saves the flags and clears the completion errors', function () {
    $this->put(route('pm-schedules.update', $this->pm), noneFormData($this->pm, [
        'no_problem' => '1',
        'no_sparepart' => '1',
    ]))->assertSessionHasNoErrors();

    $pm = $this->pm->fresh();

    expect($pm->no_problem)->toBeTrue()
        ->and($pm->no_sparepart)->toBeTrue();

    $errors = app(PMChecklistSaveService::class)->completionErrors($pm);

    expect($errors)->not->toContain('Problem')
        ->and($errors)->not->toContain('Sparepart');
});

test('no problem together with a filled problem row is rejected', function () {
    $problem = MachineProblem::create(['machine_type' => 'NDE', 'category' => 'General', 'problem' => 'Belt Retak']);

    $this->put(route('pm-schedules.update', $this->pm), noneFormData($this->pm, [
        'no_problem' => '1',
        'problems' => [['problem' => $problem->id, 'finding' => pmTestFindingId(), 'severity' => 'Low']],
    ]))->assertSessionHasErrors('no_problem');

    expect(PMProblem::where('pm_schedule_id', $this->pm->id)->count())->toBe(0)
        ->and($this->pm->fresh()->no_problem)->toBeFalse();
});

test('no sparepart together with a filled sparepart row is rejected', function () {
    $sparepart = Sparepart::create(['material_number' => 'SP-'.uniqid(), 'description' => 'Belt']);

    $this->put(route('pm-schedules.update', $this->pm), noneFormData($this->pm, [
        'no_sparepart' => '1',
        'spareparts' => [['sparepart_id' => $sparepart->id, 'qty' => 1]],
    ]))->assertSessionHasErrors('no_sparepart');

    expect(PMSparepart::where('pm_schedule_id', $this->pm->id)->count())->toBe(0);
});

test('unticking after recording problems clears the flag', function () {
    $this->pm->update(['no_problem' => true]);
    $problem = MachineProblem::create(['machine_type' => 'NDE', 'category' => 'General', 'problem' => 'Belt Retak']);

    $this->put(route('pm-schedules.update', $this->pm), noneFormData($this->pm, [
        'problems' => [['problem' => $problem->id, 'finding' => pmTestFindingId(), 'severity' => 'Low']],
    ]))->assertSessionHasNoErrors();

    expect($this->pm->fresh()->no_problem)->toBeFalse()
        ->and(PMProblem::where('pm_schedule_id', $this->pm->id)->count())->toBe(1);
});

test('warehouse-injected usage overrides no sparepart', function () {
    $sparepart = Sparepart::create(['material_number' => 'SP-'.uniqid(), 'description' => 'Belt']);
    PMSparepart::create([
        'pm_schedule_id' => $this->pm->id,
        'sparepart_id' => $sparepart->id,
        'qty' => 2,
        'source' => PMSparepart::SOURCE_WAREHOUSE,
    ]);

    $this->put(route('pm-schedules.update', $this->pm), noneFormData($this->pm, [
        'no_sparepart' => '1',
    ]))->assertSessionHasNoErrors();

    expect($this->pm->fresh()->no_sparepart)->toBeFalse()
        ->and(PMSparepart::where('pm_schedule_id', $this->pm->id)->count())->toBe(1);
});

test('mandatory spareparts are not prefilled once no sparepart was confirmed', function () {
    $sparepart = Sparepart::create(['material_number' => 'SP-'.uniqid(), 'description' => 'Belt']);
    MandatorySparepart::create(['machine_type' => 'NDE', 'sparepart_id' => $sparepart->id, 'qty' => 1]);

    $this->pm->update(['no_sparepart' => true]);

    $response = $this->get(route('pm-schedules.edit', $this->pm));

    $response->assertOk();
    expect($response->viewData('pmSpareparts'))->toBeEmpty();
});

test('offline PM_SAVE carries both flags', function () {
    $response = $this->postJson(route('api.sync'), [
        'operation_uuid' => (string) Str::uuid(),
        'transaction_type' => 'PM_SAVE',
        'payload' => array_merge(['pm_schedule_id' => $this->pm->id], noneFormData($this->pm, [
            'no_problem' => '1',
            'no_sparepart' => '1',
        ])),
        'expected_state' => ['status' => 'OPEN'],
    ]);

    $response->assertOk()->assertJson(['status' => 'processed']);

    $pm = $this->pm->fresh();

    expect($pm->no_problem)->toBeTrue()
        ->and($pm->no_sparepart)->toBeTrue();
});
