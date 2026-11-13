<?php

use App\Models\Machine;
use App\Models\MandatorySparepart;
use App\Models\PMSchedule;
use App\Models\PMSparepart;
use App\Models\PMWorkSession;
use App\Models\Sparepart;
use App\Models\User;
use App\Services\SparepartPlanningService;

function spMachine(string $type = 'NDE', string $area = 'WWD'): Machine
{
    return Machine::create([
        'machine_number' => 'MC-'.uniqid(),
        'area' => $area,
        'machine_type' => $type,
        'status' => 'ACTIVE',
    ]);
}

function spPm(Machine $machine, array $overrides = []): PMSchedule
{
    $planDate = $overrides['plan_date'] ?? '2026-10-10';

    return PMSchedule::create(array_merge([
        'machine_id' => $machine->id,
        'machine_number' => $machine->machine_number,
        'machine_type' => $machine->machine_type,
        'area' => $machine->area,
        'order_number' => 'ORD-'.uniqid(),
        'plan_date' => $planDate,
        'plan_month' => date('F', strtotime($planDate)),
        'plan_year' => date('Y', strtotime($planDate)),
        'due_date' => date('Y-m-d', strtotime($planDate.' +14 days')),
        'status' => 'OPEN',
    ], $overrides));
}

function spPart(?string $material = null, string $unit = 'PCS'): Sparepart
{
    return Sparepart::create([
        'material_number' => $material ?? 'SP-'.uniqid(),
        'description' => 'Part '.uniqid(),
        'unit' => $unit,
    ]);
}

function spMandatory(string $type, Sparepart $part, int $qty): MandatorySparepart
{
    return MandatorySparepart::create(['machine_type' => $type, 'sparepart_id' => $part->id, 'qty' => $qty]);
}

function spUser(string $role, ?string $area = 'WWD'): User
{
    $factory = User::factory();
    $factory = match ($role) {
        'admin' => $factory->admin(),
        'koordinator' => $factory->koordinator()->forArea($area),
        'pic' => $factory->pic()->forArea($area),
        'supervisor' => $factory->supervisor(),
    };

    return $factory->create();
}

function spDone(Machine $machine, string $actualDate, array $sessionDates = [], string $status = 'FINISHED'): PMSchedule
{
    $pm = spPm($machine, ['status' => $status, 'actual_date' => $actualDate]);

    foreach ($sessionDates as $date) {
        PMWorkSession::create(['pm_schedule_id' => $pm->id, 'actual_date' => $date, 'start_time' => '08:00', 'end_time' => '09:00', 'duration' => 60]);
    }

    return $pm;
}

// ---------------------------------------------------------------- CRUD / auth

test('admin and koordinator can create, update and delete mandatory sparepart', function (string $role) {
    $user = spUser($role);
    spMachine('NDE');
    $a = spPart();
    $b = spPart();

    $this->actingAs($user)->post(route('sparepart-planning.store'), ['machine_type' => 'NDE', 'sparepart_id' => $a->id, 'qty' => 2])
        ->assertRedirect(route('sparepart-planning.index', ['tab' => 'mandatory']));
    $this->actingAs($user)->post(route('sparepart-planning.store'), ['machine_type' => 'NDE', 'sparepart_id' => $b->id, 'qty' => 1])
        ->assertSessionHasNoErrors();

    $config = MandatorySparepart::where('sparepart_id', $a->id)->firstOrFail();
    expect(MandatorySparepart::where('machine_type', 'NDE')->count())->toBe(2);

    $this->actingAs($user)->put(route('sparepart-planning.update', $config), ['machine_type' => 'NDE', 'sparepart_id' => $a->id, 'qty' => 5])
        ->assertSessionHasNoErrors();
    expect($config->fresh()->qty)->toBe(5);

    $this->actingAs($user)->delete(route('sparepart-planning.destroy', $config))->assertRedirect();
    expect(MandatorySparepart::find($config->id))->toBeNull()
        ->and(Sparepart::find($a->id))->not->toBeNull();
})->with(['admin', 'koordinator']);

test('duplicate sparepart for the same machine type is rejected but allowed for another type', function () {
    $admin = spUser('admin');
    spMachine('NDE');
    spMachine('DE');
    $part = spPart();
    spMandatory('NDE', $part, 1);

    $this->actingAs($admin)->post(route('sparepart-planning.store'), ['machine_type' => 'NDE', 'sparepart_id' => $part->id, 'qty' => 3])
        ->assertSessionHasErrors('sparepart_id');
    expect(MandatorySparepart::count())->toBe(1);

    $this->actingAs($admin)->post(route('sparepart-planning.store'), ['machine_type' => 'DE', 'sparepart_id' => $part->id, 'qty' => 3])
        ->assertSessionHasNoErrors();
    expect(MandatorySparepart::count())->toBe(2);
});

test('qty must be a positive integer, machine type must exist in FreeDOMS and sparepart must exist', function (mixed $qty) {
    $admin = spUser('admin');
    spMachine('NDE');
    $part = spPart();

    $this->actingAs($admin)->post(route('sparepart-planning.store'), ['machine_type' => 'NDE', 'sparepart_id' => $part->id, 'qty' => $qty])
        ->assertSessionHasErrors('qty');
    expect(MandatorySparepart::count())->toBe(0);
})->with([0, -1, 1.5, 'abc', '']);

test('unknown machine type or sparepart is rejected', function () {
    $admin = spUser('admin');
    spMachine('NDE');
    $part = spPart();

    $this->actingAs($admin)->post(route('sparepart-planning.store'), ['machine_type' => 'GHOST', 'sparepart_id' => $part->id, 'qty' => 1])
        ->assertSessionHasErrors('machine_type');
    $this->actingAs($admin)->post(route('sparepart-planning.store'), ['machine_type' => 'NDE', 'sparepart_id' => 9999, 'qty' => 1])
        ->assertSessionHasErrors('sparepart_id');
});

test('koordinator cannot manage machine types that only exist in another area', function () {
    $koor = spUser('koordinator', 'WWD');
    spMachine('NDE', 'WWD');
    spMachine('BULTYPE', 'BUL');
    $part = spPart();
    $foreign = spMandatory('BULTYPE', $part, 1);

    $this->actingAs($koor)->post(route('sparepart-planning.store'), ['machine_type' => 'BULTYPE', 'sparepart_id' => spPart()->id, 'qty' => 1])
        ->assertSessionHasErrors('machine_type');
    $this->actingAs($koor)->put(route('sparepart-planning.update', $foreign), ['machine_type' => 'NDE', 'sparepart_id' => $part->id, 'qty' => 1])
        ->assertForbidden();
    $this->actingAs($koor)->delete(route('sparepart-planning.destroy', $foreign))->assertForbidden();
    $this->actingAs($koor)->get(route('sparepart-planning.index'))->assertOk()->assertDontSee('BULTYPE');
});

test('PIC has no access and supervisor is read-only', function () {
    spMachine('NDE');
    $part = spPart();
    $config = spMandatory('NDE', $part, 1);
    $pic = spUser('pic');
    $supervisor = spUser('supervisor');
    $body = ['machine_type' => 'NDE', 'sparepart_id' => spPart()->id, 'qty' => 2];

    $this->actingAs($pic)->get(route('sparepart-planning.index'))->assertForbidden();
    $this->actingAs($pic)->get(route('sparepart-planning.index', ['tab' => 'estimate']))->assertForbidden();
    $this->actingAs($pic)->get(route('sparepart-planning.export', ['machine_type' => 'NDE']))->assertForbidden();
    $this->actingAs($pic)->post(route('sparepart-planning.store'), $body)->assertForbidden();
    $this->actingAs($pic)->put(route('sparepart-planning.update', $config), $body)->assertForbidden();
    $this->actingAs($pic)->delete(route('sparepart-planning.destroy', $config))->assertForbidden();

    $this->actingAs($supervisor)->get(route('sparepart-planning.index'))->assertOk()->assertDontSee('Delete');
    $this->actingAs($supervisor)->get(route('sparepart-planning.index', ['tab' => 'estimate']))->assertOk();
    $this->actingAs($supervisor)->get(route('sparepart-planning.export', ['machine_type' => 'NDE']))->assertOk();
    $this->actingAs($supervisor)->get(route('sparepart-planning.edit', $config))->assertForbidden();
    $this->actingAs($supervisor)->post(route('sparepart-planning.store'), $body)->assertForbidden();
    $this->actingAs($supervisor)->put(route('sparepart-planning.update', $config), $body)->assertForbidden();
    $this->actingAs($supervisor)->delete(route('sparepart-planning.destroy', $config))->assertForbidden();

    expect(MandatorySparepart::count())->toBe(1)->and($config->fresh()->qty)->toBe(1);
});

// ---------------------------------------------------------------- Fill PM

test('Fill PM pre-fills mandatory sparepart without writing anything', function () {
    $pic = spUser('pic');
    $machine = spMachine('NDE');
    $pm = spPm($machine, ['pic' => $pic->name]);
    $part = spPart('MAT-1');
    spMandatory('NDE', $part, 3);
    $other = spPart('MAT-2');
    spMandatory('OTHER', $other, 9);

    // The whole sparepart master is embedded for the picker, so assert on
    // the pre-selected rows rather than on the material number text.
    $this->actingAs($pic)->get(route('pm-schedules.edit', $pm))
        ->assertOk()
        ->assertSee('data-selected="'.$part->id.'"', false)
        ->assertDontSee('data-selected="'.$other->id.'"', false)
        ->assertSee('name="spareparts[0][qty]" value="3"', false);

    expect(PMSparepart::count())->toBe(0);
});

test('PIC can change the quantity and saved usage is not overwritten by mandatory config', function () {
    $pic = spUser('pic');
    $pm = spPm(spMachine('NDE'), ['pic' => $pic->name]);
    $part = spPart('MAT-1');
    $config = spMandatory('NDE', $part, 3);

    $save = fn (int $qty) => $this->actingAs($pic)->put(route('pm-schedules.update', $pm), [
        'order_number' => 'ORD-1', 'pic' => $pic->name, 'actual_date' => '2026-10-11', 'start_time' => '08:00',
        'spareparts' => [['sparepart_id' => $part->id, 'qty' => $qty]],
    ])->assertSessionHasNoErrors();

    $save(7);
    expect(PMSparepart::where('pm_schedule_id', $pm->id)->pluck('qty')->all())->toBe([7]);

    // Config changes later; the saved transaction must stay as saved.
    $config->update(['qty' => 50]);
    $this->actingAs($pic)->get(route('pm-schedules.edit', $pm->fresh()))
        ->assertOk()
        ->assertSee('name="spareparts[0][qty]" value="7"', false)
        ->assertDontSee('value="50"', false);
    expect(PMSparepart::where('pm_schedule_id', $pm->id)->pluck('qty')->all())->toBe([7]);
});

test('reopening Fill PM never duplicates sparepart rows', function () {
    $pic = spUser('pic');
    $pm = spPm(spMachine('NDE'), ['pic' => $pic->name]);
    $part = spPart('MAT-1');
    spMandatory('NDE', $part, 2);

    $this->actingAs($pic)->get(route('pm-schedules.edit', $pm))->assertOk();
    $this->actingAs($pic)->put(route('pm-schedules.update', $pm), [
        'order_number' => 'ORD-1', 'pic' => $pic->name, 'actual_date' => '2026-10-11', 'start_time' => '08:00',
        'spareparts' => [['sparepart_id' => $part->id, 'qty' => 2]],
    ])->assertSessionHasNoErrors();

    $html = $this->actingAs($pic)->get(route('pm-schedules.edit', $pm->fresh()))->assertOk()->getContent();
    expect(substr_count($html, 'name="spareparts[0][sparepart_id]"'))->toBe(1)
        ->and(substr_count($html, 'name="spareparts[1]'))->toBe(0)
        ->and(PMSparepart::count())->toBe(1);
});

test('Fill PM works when no mandatory is configured and keeps an explicit empty usage list valid', function () {
    $pic = spUser('pic');
    $pm = spPm(spMachine('NDE'), ['pic' => $pic->name]);

    $this->actingAs($pic)->get(route('pm-schedules.edit', $pm))->assertOk()->assertDontSee('pre-filled below');
    $this->actingAs($pic)->put(route('pm-schedules.update', $pm), [
        'order_number' => 'ORD-1', 'pic' => $pic->name, 'actual_date' => '2026-10-11', 'start_time' => '08:00',
    ])->assertSessionHasNoErrors();
    expect(PMSparepart::count())->toBe(0);
});

test('save validation of sparepart qty is unchanged', function () {
    $pic = spUser('pic');
    $pm = spPm(spMachine('NDE'), ['pic' => $pic->name]);
    $part = spPart();

    $this->actingAs($pic)->put(route('pm-schedules.update', $pm), [
        'order_number' => 'ORD-1', 'pic' => $pic->name, 'actual_date' => '2026-10-11', 'start_time' => '08:00',
        'spareparts' => [['sparepart_id' => $part->id, 'qty' => 0]],
    ])->assertSessionHasErrors('spareparts.0.qty');
});

test('finished PM history is never pre-filled and existing PM data is untouched by config changes', function () {
    $admin = spUser('admin');
    $machine = spMachine('NDE');
    $pm = spDone($machine, '2026-10-05');
    $used = spPart('USED');
    PMSparepart::create(['pm_schedule_id' => $pm->id, 'sparepart_id' => $used->id, 'qty' => 4]);
    $done = spDone($machine, '2026-10-06');
    $mandatoryPart = spPart('MAT-9');
    $config = spMandatory('NDE', $mandatoryPart, 2);

    $this->actingAs($admin)->get(route('pm-schedules.edit', $done))->assertOk()
        ->assertDontSee('data-selected="'.$mandatoryPart->id.'"', false);

    $before = PMSparepart::orderBy('id')->get(['id', 'pm_schedule_id', 'sparepart_id', 'qty'])->toArray();
    $this->actingAs($admin)->put(route('sparepart-planning.update', $config), ['machine_type' => 'NDE', 'sparepart_id' => $config->sparepart_id, 'qty' => 99]);
    $this->actingAs($admin)->delete(route('sparepart-planning.destroy', $config));
    expect(PMSparepart::orderBy('id')->get(['id', 'pm_schedule_id', 'sparepart_id', 'qty'])->toArray())->toBe($before)
        ->and($pm->fresh()->status)->toBe('FINISHED');
});

// ---------------------------------------------------------------- Calculation

test('planned need counts unfinished PMs by plan_date, period, machine type and status', function () {
    $admin = spUser('admin');
    $nde = spMachine('NDE');
    $de = spMachine('DE');
    $part = spPart('MAT-1');
    spMandatory('NDE', $part, 3);
    spMandatory('DE', $part, 1);
    spMandatory('NOCFG', spPart('MAT-X'), 5);

    spPm($nde, ['plan_date' => '2026-10-01']);                                  // OPEN
    spPm($nde, ['plan_date' => '2026-10-31', 'status' => 'IN_PROGRESS']);       // IN_PROGRESS
    spPm($nde, ['plan_date' => '2026-10-15', 'status' => 'MISSED']);            // MISSED, still needed
    spPm($nde, ['plan_date' => '2026-10-15', 'status' => 'FINISHED']);          // done: excluded
    spPm($nde, ['plan_date' => '2026-10-15', 'status' => 'FINISHED_ON_TIME']);  // done: excluded
    spPm($nde, ['plan_date' => '2026-11-01']);                                  // other month
    spPm($nde, ['plan_date' => '2025-10-10']);                                  // other year
    spPm($de, ['plan_date' => '2026-10-20']);
    spPm(spMachine('NOCFG'), ['plan_date' => '2026-10-20']);                    // type has config for MAT-X only

    $svc = app(SparepartPlanningService::class);
    $plan = $svc->plan($admin, ...array_merge($svc->period(2026, 10), [null, null]));

    $row = $plan->firstWhere('material_number', 'MAT-1');
    expect($row['total'])->toBe(3 * 3 + 1 * 1)
        ->and($row['pm_count'])->toBe(4)
        ->and(collect($row['breakdown'])->keyBy('machine_type')->map->total->all())->toEqual(['NDE' => 9, 'DE' => 1])
        ->and($plan->firstWhere('material_number', 'MAT-X')['total'])->toBe(5);

    $onlyNde = $svc->plan($admin, ...array_merge($svc->period(2026, 10), ['NDE', null]));
    expect($onlyNde)->toHaveCount(1)->and($onlyNde->first()['total'])->toBe(9);

    $year = $svc->plan($admin, ...array_merge($svc->period(2026, null), ['NDE', null]));
    expect($year->first()['total'])->toBe(4 * 3);

    // Config never counts for a machine type without schedules, and types
    // without configuration produce nothing.
    expect(spPm(spMachine('PLAIN'), ['plan_date' => '2026-10-02']))->not->toBeNull();
    expect($svc->plan($admin, ...array_merge($svc->period(2026, 10), ['PLAIN', null])))->toBeEmpty();
});

test('actual usage uses saved pm_spareparts of finished PMs once, by last session date', function () {
    $admin = spUser('admin');
    $machine = spMachine('NDE');
    $mandatory = spPart('MAT-M');
    $extra = spPart('MAT-EXTRA');
    spMandatory('NDE', $mandatory, 100); // config must never leak into actuals

    // Multi-day PM: three sessions, completes on the last (in October).
    $multi = spDone($machine, '2026-09-30', ['2026-09-30', '2026-10-01', '2026-10-02']);
    PMSparepart::create(['pm_schedule_id' => $multi->id, 'sparepart_id' => $mandatory->id, 'qty' => 2]);
    PMSparepart::create(['pm_schedule_id' => $multi->id, 'sparepart_id' => $extra->id, 'qty' => 1]);

    // Legacy PM without work sessions: falls back to actual_date.
    $legacy = spDone($machine, '2026-10-20', [], 'FINISHED_ON_TIME');
    PMSparepart::create(['pm_schedule_id' => $legacy->id, 'sparepart_id' => $mandatory->id, 'qty' => 4]);

    // Not finished: ignored even though it has saved spareparts.
    $wip = spPm($machine, ['status' => 'IN_PROGRESS', 'actual_date' => '2026-10-10']);
    PMSparepart::create(['pm_schedule_id' => $wip->id, 'sparepart_id' => $mandatory->id, 'qty' => 50]);

    // Finished in another month.
    $other = spDone($machine, '2026-11-02', ['2026-11-02']);
    PMSparepart::create(['pm_schedule_id' => $other->id, 'sparepart_id' => $mandatory->id, 'qty' => 70]);

    $svc = app(SparepartPlanningService::class);
    $actual = $svc->actual($admin, ...array_merge($svc->period(2026, 10), [null, null]));

    expect($actual->firstWhere('material_number', 'MAT-M')['total'])->toBe(6)
        ->and($actual->firstWhere('material_number', 'MAT-M')['pm_count'])->toBe(2)
        ->and($actual->firstWhere('material_number', 'MAT-EXTRA')['total'])->toBe(1);

    $september = $svc->actual($admin, ...array_merge($svc->period(2026, 9), [null, null]));
    expect($september)->toBeEmpty();
});

test('recap keeps plan and actual independent and consistent with the filters', function () {
    $admin = spUser('admin');
    $nde = spMachine('NDE');
    $part = spPart('MAT-1');
    $extra = spPart('MAT-2');
    spMandatory('NDE', $part, 2);
    spPm($nde, ['plan_date' => '2026-10-05']);
    spPm($nde, ['plan_date' => '2026-10-06']);
    $done = spDone($nde, '2026-10-03', ['2026-10-03']);
    PMSparepart::create(['pm_schedule_id' => $done->id, 'sparepart_id' => $part->id, 'qty' => 1]);
    PMSparepart::create(['pm_schedule_id' => $done->id, 'sparepart_id' => $extra->id, 'qty' => 3]);

    $result = app(SparepartPlanningService::class)->calculate($admin, 2026, 10, 'NDE', null);
    $recap = $result['recap']->keyBy('material_number');

    expect($recap['MAT-1']['plan'])->toBe(4)
        ->and($recap['MAT-1']['actual'])->toBe(1)
        ->and($recap['MAT-1']['difference'])->toBe(3)
        ->and($recap['MAT-2']['plan'])->toBe(0)
        ->and($recap['MAT-2']['actual'])->toBe(3)
        ->and($recap['MAT-2']['difference'])->toBe(-3);

});

test('estimate is scoped to the koordinator area and supervisor assigned areas', function () {
    $koor = spUser('koordinator', 'WWD');
    $part = spPart('MAT-1');
    spMandatory('NDE', $part, 1);
    spPm(spMachine('NDE', 'WWD'), ['plan_date' => '2026-10-05']);
    spPm(spMachine('NDE', 'BUL'), ['plan_date' => '2026-10-05']);
    spPm(spMachine('NDE', 'BUL'), ['plan_date' => '2026-10-06']);

    $svc = app(SparepartPlanningService::class);
    expect($svc->plan($koor, ...array_merge($svc->period(2026, 10), [null, 'BUL']))->first()['total'])->toBe(1);

    $admin = spUser('admin');
    expect($svc->plan($admin, ...array_merge($svc->period(2026, 10), [null, null]))->first()['total'])->toBe(3)
        ->and($svc->plan($admin, ...array_merge($svc->period(2026, 10), [null, 'BUL']))->first()['total'])->toBe(2);
});

// ------------------------------------------------- Sparepart Order Estimation

function spEstimate(User $user, array $query = [])
{
    return test()->actingAs($user)->get(route('sparepart-planning.index', array_merge(
        ['tab' => 'estimate', 'machine_type' => 'NDE', 'year' => 2026, 'month' => 10],
        $query,
    )));
}

function spOrderRows($response): array
{
    return $response->viewData('estimate')['rows']->keyBy('material_number')->all();
}

/** 3 distinct NDE machines (one with two schedules) + noise that must not count. */
function spSeedEstimate(): array
{
    $m1 = spMachine('NDE');
    $m2 = spMachine('NDE');
    $m3 = spMachine('NDE');
    spPm($m1, ['plan_date' => '2026-10-01']);
    spPm($m1, ['plan_date' => '2026-10-20', 'status' => 'IN_PROGRESS']); // same machine twice
    spPm($m2, ['plan_date' => '2026-10-31', 'status' => 'MISSED']);
    spPm($m3, ['plan_date' => '2026-10-15']);
    spPm(spMachine('NDE'), ['plan_date' => '2026-10-15', 'status' => 'FINISHED']); // done
    spPm(spMachine('NDE'), ['plan_date' => '2026-11-01']);                         // other month
    spPm(spMachine('DE'), ['plan_date' => '2026-10-15']);                          // other type
    $a = spPart('MAT-A');
    $b = spPart('MAT-B');
    spMandatory('NDE', $a, 2);
    spMandatory('NDE', $b, 1);

    return [$a, $b];
}

test('machine count is distinct per type and period and the final count drives mandatory qty', function () {
    $admin = spUser('admin');
    spSeedEstimate();

    $response = spEstimate($admin)->assertOk();
    expect($response->viewData('estimate')['machines_system'])->toBe(3)
        ->and($response->viewData('estimate')['machines_final'])->toBe(3);
    $rows = spOrderRows($response);
    expect($rows['MAT-A']['system_qty'])->toBe(6)->and($rows['MAT-A']['order_qty'])->toBe(6)
        ->and($rows['MAT-B']['order_qty'])->toBe(3)
        ->and($rows['MAT-A']['source'])->toBe('Mandatory');

    $before = PMSchedule::count();
    $response = spEstimate($admin, ['machines_final' => 5]);
    $rows = spOrderRows($response);
    expect($response->viewData('estimate')['machines_system'])->toBe(3)
        ->and($response->viewData('estimate')['machines_final'])->toBe(5)
        ->and($rows['MAT-A']['system_qty'])->toBe(6)   // system estimate stays as the comparison
        ->and($rows['MAT-A']['order_qty'])->toBe(10)
        ->and($rows['MAT-B']['order_qty'])->toBe(5)
        ->and(PMSchedule::count())->toBe($before);
});

test('machine count honours the area scope', function () {
    $koor = spUser('koordinator', 'WWD');
    spPm(spMachine('NDE', 'WWD'), ['plan_date' => '2026-10-05']);
    spPm(spMachine('NDE', 'BUL'), ['plan_date' => '2026-10-05']);
    spMandatory('NDE', spPart('MAT-A'), 1);

    expect(spEstimate($koor)->viewData('estimate')['machines_system'])->toBe(1);
});

test('manual quantity corrections survive a machine count change and can be reset', function () {
    $admin = spUser('admin');
    [$a, $b] = spSeedEstimate();

    // User edited MAT-A to 20 while the calculated value shown was 6.
    $state = ['qty' => [$a->id => 20, $b->id => 3], 'orig' => [$a->id => 6, $b->id => 3]];
    $rows = spOrderRows(spEstimate($admin, $state + ['machines_final' => 11, 'prev_scope' => 'NDE|2026|10|']));

    expect($rows['MAT-A']['order_qty'])->toBe(20)
        ->and($rows['MAT-A']['overridden'])->toBeTrue()
        ->and($rows['MAT-B']['order_qty'])->toBe(11)  // untouched row follows the machine count
        ->and($rows['MAT-B']['overridden'])->toBeFalse();

    $rows = spOrderRows(spEstimate($admin, $state + ['machines_final' => 4, 'prev_scope' => 'NDE|2026|10|']));
    expect($rows['MAT-A']['order_qty'])->toBe(20)
        ->and($rows['MAT-A']['auto_qty'])->toBe(8)
        ->and($rows['MAT-A']['system_qty'])->toBe(6)
        ->and($rows['MAT-A']['overridden'])->toBeTrue();

    $rows = spOrderRows(spEstimate($admin, $state + ['machines_final' => 4, 'reset' => 'qty', 'prev_scope' => 'NDE|2026|10|']));
    expect($rows['MAT-A']['order_qty'])->toBe(8)->and($rows['MAT-A']['overridden'])->toBeFalse();
});

test('invalid quantities are rejected with a message and zero is allowed', function () {
    $admin = spUser('admin');
    [$a, $b] = spSeedEstimate();

    $response = spEstimate($admin, ['qty' => [$a->id => -3, $b->id => 0], 'orig' => [$a->id => 6, $b->id => 3], 'machines_final' => 'abc']);
    $rows = spOrderRows($response);

    expect($rows['MAT-A']['order_qty'])->toBe(6)
        ->and($rows['MAT-B']['order_qty'])->toBe(0)
        ->and($response->viewData('estimate')['machines_final'])->toBe(3)
        ->and($response->viewData('messages'))->toHaveCount(2);
});

test('extra spareparts are added, edited, removed, never duplicated and not multiplied', function () {
    $admin = spUser('admin');
    [$a] = spSeedEstimate();
    $extra = spPart('MAT-X');

    $rows = spOrderRows(spEstimate($admin, ['add_sparepart_id' => $extra->id, 'add_qty' => 4, 'machines_final' => 9]));
    expect($rows['MAT-X']['source'])->toBe('Manual')->and($rows['MAT-X']['order_qty'])->toBe(4)
        ->and($rows['MAT-A']['source'])->toBe('Mandatory')->and($rows['MAT-A']['order_qty'])->toBe(18);

    // Edit qty (and adding the same material again does not create a second row).
    $response = spEstimate($admin, ['manual' => [$extra->id => 7], 'add_sparepart_id' => $extra->id, 'add_qty' => 1]);
    expect($response->viewData('estimate')['rows']->where('sparepart_id', $extra->id))->toHaveCount(1)
        ->and(spOrderRows($response)['MAT-X']['order_qty'])->toBe(7)
        ->and(implode(' ', $response->viewData('messages')))->toContain('already in the list');

    // A mandatory material is refused as an extra row and the user is pointed to the existing one.
    $response = spEstimate($admin, ['add_sparepart_id' => $a->id, 'add_qty' => 5]);
    expect($response->viewData('estimate')['rows']->where('sparepart_id', $a->id))->toHaveCount(1)
        ->and(spOrderRows($response)['MAT-A']['source'])->toBe('Mandatory')
        ->and(implode(' ', $response->viewData('messages')))->toContain('already a Mandatory part');

    // Remove.
    $response = spEstimate($admin, ['manual' => [$extra->id => 7], 'remove' => $extra->id]);
    expect(spOrderRows($response))->not->toHaveKey('MAT-X');
});

test('changing the filter scope drops corrections and manual items from the previous scope', function () {
    $admin = spUser('admin');
    [$a] = spSeedEstimate();
    $extra = spPart('MAT-X');

    $rows = spOrderRows(spEstimate($admin, [
        'prev_scope' => 'DE|2026|10|', 'qty' => [$a->id => 99], 'orig' => [$a->id => 6], 'manual' => [$extra->id => 3],
    ]));

    expect($rows['MAT-A']['order_qty'])->toBe(6)->and($rows)->not->toHaveKey('MAT-X');
});

test('usage history is reference only: finished PMs before the period, counted once, never added to the order', function () {
    $admin = spUser('admin');
    spSeedEstimate();
    $used = spPart('MAT-H');
    $machine = spMachine('NDE');
    $multi = spDone($machine, '2026-08-01', ['2026-08-01', '2026-08-02', '2026-08-03']);
    PMSparepart::create(['pm_schedule_id' => $multi->id, 'sparepart_id' => $used->id, 'qty' => 3]);
    $running = spPm($machine, ['status' => 'IN_PROGRESS', 'actual_date' => '2026-08-05']);
    PMSparepart::create(['pm_schedule_id' => $running->id, 'sparepart_id' => $used->id, 'qty' => 50]);
    $before = PMSparepart::count();

    $response = spEstimate($admin)->assertOk();
    $ref = $response->viewData('usageReference')['rows']->firstWhere('material_number', 'MAT-H');

    expect($ref['total'])->toBe(3)->and($ref['pm_count'])->toBe(1)
        ->and(spOrderRows($response))->not->toHaveKey('MAT-H')
        ->and(PMSparepart::count())->toBe($before);
});

test('export uses the final on-screen quantities, includes manual items and changes no data', function () {
    Excel::fake();
    $admin = spUser('admin');
    [$a, $b] = spSeedEstimate();
    $extra = spPart('MAT-X');
    $config = MandatorySparepart::where('sparepart_id', $a->id)->first();
    $counts = [PMSchedule::count(), PMSparepart::count(), MandatorySparepart::count()];

    $this->actingAs($admin)->get(route('sparepart-planning.export', [
        'machine_type' => 'NDE', 'year' => 2026, 'month' => 10, 'machines_final' => 5,
        'qty' => [$a->id => 30, $b->id => 5], 'orig' => [$a->id => 10, $b->id => 5],
        'manual' => [$extra->id => 4],
    ]))->assertOk();

    Excel::assertDownloaded('sparepart-order-estimation-nde-2026-10.xlsx', function ($export) {
        $rows = collect($export->array())->skip(1)->keyBy(fn ($r) => $r[4]);

        return $rows->count() === 3
            && $rows['MAT-A'][2] === 3 && $rows['MAT-A'][3] === 5 && $rows['MAT-A'][7] === 'Mandatory'
            && $rows['MAT-A'][8] === 2 && $rows['MAT-A'][9] === 6 && $rows['MAT-A'][10] === 30
            && $rows['MAT-B'][10] === 5
            && $rows['MAT-X'][7] === 'Manual' && $rows['MAT-X'][8] === null && $rows['MAT-X'][10] === 4;
    });

    expect([PMSchedule::count(), PMSparepart::count(), MandatorySparepart::count()])->toBe($counts)
        ->and($config->fresh()->qty)->toBe(2);
});

test('estimation page and export keep working per role', function () {
    spSeedEstimate();

    foreach (['admin', 'koordinator', 'supervisor'] as $role) {
        spEstimate(spUser($role))->assertOk()->assertSee('Sparepart Order Estimation');
    }
    spEstimate(spUser('supervisor'), ['machine_type' => ''])->assertOk()->assertSee('Select a machine type');
    $this->actingAs(spUser('admin'))->get(route('sparepart-planning.export'))->assertStatus(422);
    spEstimate(spUser('pic'))->assertForbidden();
});
