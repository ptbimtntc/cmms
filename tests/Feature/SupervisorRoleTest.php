<?php

use App\Models\Area;
use App\Models\Machine;
use App\Models\OilAudit;
use App\Models\PMSchedule;
use App\Models\User;
use Illuminate\Support\Str;

function supervisorMachine(string $area): Machine
{
    return Machine::create([
        'machine_number' => 'SV-'.uniqid(),
        'area' => $area,
        'machine_type' => 'NDE',
        'status' => 'ACTIVE',
    ]);
}

function supervisorPm(Machine $machine): PMSchedule
{
    return PMSchedule::create([
        'machine_id' => $machine->id,
        'machine_number' => $machine->machine_number,
        'machine_type' => $machine->machine_type,
        'area' => $machine->area,
        'order_number' => 'ORD-'.uniqid(),
        'plan_date' => now()->toDateString(),
        'plan_month' => now()->format('F'),
        'plan_year' => now()->format('Y'),
        'due_date' => now()->addDays(14),
        'status' => 'OPEN',
    ]);
}

test('supervisor can log in and reaches the dashboard', function () {
    $supervisor = User::factory()->supervisor()->create(['password' => 'password123']);

    $this->post('/login', ['email' => $supervisor->email, 'password' => 'password123'])
        ->assertRedirect();
    $this->assertAuthenticatedAs($supervisor);

    $this->actingAs($supervisor)->get(route('dashboard'))->assertOk();
});

test('supervisor has no area and sees every area', function () {
    $supervisor = User::factory()->supervisor()->create();

    expect($supervisor->area_id)->toBeNull()
        ->and($supervisor->isSupervisor())->toBeTrue()
        ->and($supervisor->seesAllAreas())->toBeTrue()
        ->and($supervisor->hasArea('WWD'))->toBeTrue()
        ->and($supervisor->hasArea('ANY-FUTURE-AREA'))->toBeTrue();
});

test('supervisor sees PM schedules from all areas and can filter by area', function () {
    $supervisor = User::factory()->supervisor()->create();
    $wwd = supervisorPm(supervisorMachine('WWD'));
    $bul = supervisorPm(supervisorMachine('BUL'));
    $other = supervisorPm(supervisorMachine('NEWAREA'));

    $all = $this->actingAs($supervisor)->get(route('pm-schedules.index'))->assertOk();
    $all->assertSee($wwd->machine_number)->assertSee($bul->machine_number)->assertSee($other->machine_number);

    $filtered = $this->actingAs($supervisor)->get(route('pm-schedules.index', ['area' => 'BUL']))->assertOk();
    $filtered->assertSee($bul->machine_number)->assertDontSee($wwd->machine_number);
});

test('supervisor can open every read-only page', function (string $routeName) {
    $supervisor = User::factory()->supervisor()->create();

    $this->actingAs($supervisor)->get(route($routeName))->assertOk();
})->with([
    'dashboard',
    'today-activity.index',
    'pm-schedules.index',
    'reports.pm',
    'reports.greasing',
    'reports.sparepart',
    'reports.machine',
    'reports.problem',
    'reports.cost',
    'greasings.index',
    'machines.index',
    'groups.index',
    'spareparts.index',
    'machine-measurements.index',
    'machine-checklists.index',
    'machine-problems.index',
    'machine-problem-findings.index',
    'machine-history.index',
]);

test('supervisor report area filter narrows data across areas', function () {
    $supervisor = User::factory()->supervisor()->create();
    supervisorPm(supervisorMachine('WWD'));
    supervisorPm(supervisorMachine('BUL'));

    $month = ['year' => now()->year, 'month' => now()->month];
    $all = $this->actingAs($supervisor)->get(route('reports.pm', $month));
    $bul = $this->actingAs($supervisor)->get(route('reports.pm', $month + ['area' => 'BUL']));

    expect($all->viewData('summary')['total'])->toBe(2)
        ->and($bul->viewData('summary')['total'])->toBe(1);
});

test('supervisor cannot reach write endpoints', function (string $method, string $uri) {
    $supervisor = User::factory()->supervisor()->create();

    $this->actingAs($supervisor)->call($method, $uri)->assertForbidden();
})->with([
    ['GET', '/machines/create'],
    ['POST', '/machines'],
    ['PUT', '/machines/1'],
    ['DELETE', '/machines/1'],
    ['POST', '/machines/import'],
    ['GET', '/groups/create'],
    ['POST', '/groups'],
    ['DELETE', '/groups/1'],
    ['POST', '/spareparts'],
    ['POST', '/spareparts/import'],
    ['DELETE', '/spareparts/1'],
    ['POST', '/machine-measurements'],
    ['POST', '/machine-measurements/import'],
    ['POST', '/machine-checklists'],
    ['POST', '/machine-checklists/import'],
    ['DELETE', '/machine-problems/1'],
    ['POST', '/machine-problem-findings/import'],
    ['POST', '/pm-schedules'],
    ['POST', '/pm-schedules/import'],
    ['GET', '/pm-schedules/1/edit'],
    ['PUT', '/pm-schedules/1'],
    ['DELETE', '/pm-schedules/1'],
    ['POST', '/pm-schedules/1/assign-pic'],
    ['POST', '/pm-schedules/1/start'],
    ['GET', '/pm-schedules/1/checklist'],
    ['POST', '/pm-schedules/1/checklist'],
    ['POST', '/pm-schedules/1/revert-to-open'],
    ['POST', '/greasings'],
    ['POST', '/greasings/import'],
    ['PUT', '/greasings/1'],
    ['DELETE', '/greasings/1'],
    ['POST', '/greasings/1/assign-pic'],
    ['POST', '/greasings/1/start'],
    ['GET', '/greasings/1/execute'],
    ['POST', '/greasings/1/execute'],
    ['POST', '/greasings/1/findings'],
    ['PATCH', '/greasings/1/findings/1'],
    ['DELETE', '/greasings/1/findings/1'],
    ['POST', '/today-activity/manual'],
    ['POST', '/today-activity/finish'],
    ['POST', '/today-activity/inactive'],
    ['GET', '/import-templates'],
    ['POST', '/oil-audits/start'],
    ['POST', '/oil-audit-report/start'],
    ['POST', '/oil-audits/1/follow-up'],
    ['PUT', '/oil-audits/1/follow-up'],
    ['DELETE', '/oil-audits/1/follow-up'],
    ['POST', '/oil-audits'],
    ['GET', '/users'],
    ['POST', '/users'],
    ['GET', '/areas'],
]);

test('supervisor cannot use the offline sync endpoint', function () {
    $supervisor = User::factory()->supervisor()->create();

    $this->actingAs($supervisor)
        ->postJson(route('api.sync'), ['operation_uuid' => (string) Str::uuid(), 'transaction_type' => 'PM_START', 'payload' => ['pm_schedule_id' => 1]])
        ->assertStatus(403);
});

test('supervisor does not see write buttons on list pages', function () {
    $supervisor = User::factory()->supervisor()->create();
    $machine = supervisorMachine('WWD');
    supervisorPm($machine);

    $this->actingAs($supervisor)->get(route('machines.index'))
        ->assertOk()
        ->assertDontSee(route('machines.import'), false)
        ->assertDontSee(route('machines.edit', $machine->id), false);

    $this->actingAs($supervisor)->get(route('pm-schedules.index'))
        ->assertOk()
        ->assertDontSee(route('pm-schedules.import'), false)
        ->assertDontSee('Revert to Open')
        ->assertDontSee('Delete');

    $this->actingAs($supervisor)->get(route('greasings.index'))
        ->assertOk()
        ->assertDontSee(route('greasings.import'), false);
});

test('supervisor sidebar hides input and management menus', function () {
    $supervisor = User::factory()->supervisor()->create();

    $this->actingAs($supervisor)->get(route('dashboard'))
        ->assertOk()
        ->assertSee(route('pm-schedules.index'), false)
        ->assertSee(route('machines.index'), false)
        ->assertDontSee(route('users.index'), false)
        ->assertDontSee(route('import-templates'), false)
        ->assertSee(route('oil-audits.scan'), false);
});

test('admin can create and edit supervisor users without an area', function () {
    $admin = User::factory()->admin()->create();
    $area = Area::factory()->create();

    $this->actingAs($admin)->post(route('users.store'), [
        'name' => 'Sup One',
        'email' => 'sup@example.com',
        'password' => 'password123',
        'role' => User::ROLE_SUPERVISOR,
        'area_id' => $area->id,
    ])->assertRedirect(route('users.index'));

    $supervisor = User::where('email', 'sup@example.com')->firstOrFail();
    expect($supervisor->role)->toBe(User::ROLE_SUPERVISOR)->and($supervisor->area_id)->toBeNull();

    $this->actingAs($admin)->get(route('users.create'))->assertOk()->assertSee('SUPERVISOR');
});

test('admin keeps full access', function () {
    $admin = User::factory()->admin()->create();

    foreach (['machines.create', 'users.index', 'import-templates', 'pm-schedules.index', 'reports.pm'] as $name) {
        $this->actingAs($admin)->get(route($name))->assertOk();
    }
});

test('koordinator and PIC stay restricted to their own area', function () {
    $koordinator = User::factory()->koordinator()->forArea('WWD')->create();
    $pic = User::factory()->pic()->forArea('WWD')->create();
    $wwd = supervisorPm(supervisorMachine('WWD'));
    $bul = supervisorPm(supervisorMachine('BUL'));
    $wwd->update(['pic' => $pic->name]);

    $this->actingAs($koordinator)->get(route('pm-schedules.index'))
        ->assertSee($wwd->machine_number)->assertDontSee($bul->machine_number);
    $this->actingAs($pic)->get(route('pm-schedules.index'))
        ->assertSee($wwd->machine_number)->assertDontSee($bul->machine_number);
    $this->actingAs($pic)->get(route('machines.index'))->assertForbidden();
    expect($pic->seesAllAreas())->toBeFalse()->and($koordinator->hasArea('BUL'))->toBeFalse();
});

test('guest keeps landing on the guest dashboard', function () {
    $guest = User::factory()->guest()->create(['password' => 'password123']);

    $this->post('/login', ['email' => $guest->email, 'password' => 'password123'])
        ->assertRedirect(route('dashboard-guest'));
    $this->actingAs($guest)->get(route('dashboard'))->assertForbidden();
});

function supervisorWithAreas(string ...$names): User
{
    $supervisor = User::factory()->supervisor()->create();
    foreach ($names as $name) {
        $supervisor->areas()->attach(Area::firstOrCreate(['name' => $name], ['slug' => Str::slug($name), 'is_active' => true]));
    }

    return $supervisor->refresh();
}

test('supervisor with one area only sees that area', function () {
    $supervisor = supervisorWithAreas('WWD');
    Area::firstOrCreate(['name' => 'BUL'], ['slug' => 'bul', 'is_active' => true]);
    $wwd = supervisorPm(supervisorMachine('WWD'));
    $bul = supervisorPm(supervisorMachine('BUL'));

    $this->actingAs($supervisor)->get(route('pm-schedules.index'))
        ->assertSee($wwd->machine_number)->assertDontSee($bul->machine_number);

    // an explicit filter for a non-assigned area cannot widen the view
    $month = ['year' => now()->year, 'month' => now()->month];
    $report = $this->actingAs($supervisor)->get(route('reports.pm', $month + ['area' => 'BUL']));
    expect($report->viewData('summary')['total'])->toBe(1);
    expect($supervisor->hasArea('WWD'))->toBeTrue()->and($supervisor->hasArea('BUL'))->toBeFalse();
});

test('supervisor with several areas sees exactly those areas', function () {
    $supervisor = supervisorWithAreas('WWD', 'BUL');
    Area::firstOrCreate(['name' => 'XYZ'], ['slug' => 'xyz', 'is_active' => true]);
    $wwd = supervisorPm(supervisorMachine('WWD'));
    $bul = supervisorPm(supervisorMachine('BUL'));
    $xyz = supervisorPm(supervisorMachine('XYZ'));

    $this->actingAs($supervisor)->get(route('pm-schedules.index'))
        ->assertSee($wwd->machine_number)->assertSee($bul->machine_number)->assertDontSee($xyz->machine_number);
    expect($supervisor->selectableAreaNames()->all())->toBe(['BUL', 'WWD']);
});

test('restricted supervisor is limited on greasing and record access', function () {
    $supervisor = supervisorWithAreas('WWD');
    $pm = supervisorPm(supervisorMachine('BUL'));

    expect($pm->isAccessibleBy($supervisor))->toBeFalse()
        ->and(supervisorPm(supervisorMachine('WWD'))->isAccessibleBy($supervisor))->toBeTrue();
});

test('admin assigns zero, one or many areas to a supervisor', function () {
    $admin = User::factory()->admin()->create();
    $a = Area::factory()->create();
    $b = Area::factory()->create();

    $this->actingAs($admin)->post(route('users.store'), [
        'name' => 'Sup Multi', 'email' => 'multi@example.com', 'password' => 'password123',
        'role' => User::ROLE_SUPERVISOR, 'area_ids' => [$a->id, $b->id],
    ])->assertRedirect(route('users.index'));
    $supervisor = User::where('email', 'multi@example.com')->firstOrFail();
    expect($supervisor->areas()->count())->toBe(2)->and($supervisor->area_id)->toBeNull();

    $this->actingAs($admin)->put(route('users.update', $supervisor), [
        'name' => 'Sup Multi', 'email' => 'multi@example.com',
        'role' => User::ROLE_SUPERVISOR, 'is_active' => 1, 'area_ids' => [$a->id],
    ])->assertRedirect(route('users.index'));
    expect($supervisor->areas()->pluck('areas.id')->all())->toBe([$a->id]);

    $this->actingAs($admin)->put(route('users.update', $supervisor), [
        'name' => 'Sup Multi', 'email' => 'multi@example.com',
        'role' => User::ROLE_SUPERVISOR, 'is_active' => 1,
    ]);
    expect($supervisor->areas()->count())->toBe(0);

    $this->actingAs($admin)->get(route('users.edit', $supervisor))->assertOk()->assertSee('area_ids[]', false);
});

test('supervisor can view oil audit pages but the check forms are greyed out', function () {
    $supervisor = User::factory()->supervisor()->create();
    $machine = Machine::create([
        'machine_number' => 'OA-'.uniqid(),
        'area' => 'WWD',
        'machine_type' => oilAuditMachineType(),
        'status' => 'ACTIVE',
    ]);
    OilAudit::create([
        'machine_id' => $machine->id,
        'machine_number' => $machine->machine_number,
        'machine_type' => $machine->machine_type,
        'area' => 'WWD',
        'condition' => 'KRITIS',
        'audited_by_name' => 'Tester',
        'audited_at' => now(),
    ]);

    $this->actingAs($supervisor)->get(route('oil-audits.scan'))->assertOk();
    $this->actingAs($supervisor)->get(route('oil-audits.report'))->assertOk();
    $this->actingAs($supervisor)->get(route('reports.oil-audit'))->assertOk();

    $this->actingAs($supervisor)->get(route('oil-audits.entry', $machine->machine_number))
        ->assertOk()
        ->assertSee('Mode lihat saja')
        ->assertSee('grayscale')
        ->assertSee('disabled', false);

    $this->actingAs($supervisor)->get(route('oil-audits.history', $machine->machine_number))
        ->assertOk()
        ->assertSee('<fieldset disabled', false)
        ->assertDontSee('Edit tindak lanjut');

    $this->actingAs($supervisor)->post(route('oil-audits.store'), ['machine_id' => $machine->id, 'condition' => 'OKE'])
        ->assertForbidden();
});

test('supervisor restricted to another area cannot open oil audit pages', function () {
    $supervisor = supervisorWithAreas('BUL');

    $this->actingAs($supervisor)->get(route('oil-audits.scan'))->assertForbidden();
});

test('PIC still sees enabled oil audit entry buttons', function () {
    $pic = User::factory()->pic()->forArea('WWD')->create();
    $machine = Machine::create([
        'machine_number' => 'OA-'.uniqid(),
        'area' => 'WWD',
        'machine_type' => oilAuditMachineType(),
        'status' => 'ACTIVE',
    ]);

    $this->actingAs($pic)->get(route('oil-audits.entry', $machine->machine_number))
        ->assertOk()
        ->assertDontSee('Mode lihat saja')
        ->assertDontSee('grayscale');
});
