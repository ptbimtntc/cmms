<?php

use App\Models\Area;
use App\Models\Machine;
use App\Models\PMSchedule;
use App\Models\User;

function picFilterSchedule(string $area, ?string $pic): PMSchedule
{
    $machine = Machine::create([
        'machine_number' => 'MC-'.uniqid(),
        'area' => $area,
        'machine_type' => 'NDE SW',
        'status' => 'ACTIVE',
    ]);

    return PMSchedule::create([
        'machine_id' => $machine->id,
        'machine_number' => $machine->machine_number,
        'machine_type' => $machine->machine_type,
        'area' => $area,
        'order_number' => 'ORD-'.uniqid(),
        'plan_date' => now()->toDateString(),
        'plan_month' => now()->format('F'),
        'plan_year' => now()->format('Y'),
        'due_date' => now()->addDays(14),
        'status' => 'OPEN',
        'pic' => $pic,
    ]);
}

function picFilterNumbers($response): array
{
    return $response->viewData('schedules')->pluck('machine_number')->sort()->values()->all();
}

test('admin, koordinator and supervisor can filter PM schedules by PIC', function (string $role) {
    $user = User::factory()->create(['role' => $role, 'area_id' => null]);
    if ($role === User::ROLE_KOORDINATOR) {
        $user = User::factory()->koordinator()->forArea('WWD')->create();
    }
    $budi = picFilterSchedule('WWD', 'Budi');
    $andi = picFilterSchedule('WWD', 'Andi');
    $none = picFilterSchedule('WWD', null);

    $page = $this->actingAs($user)->get(route('pm-schedules.index'));
    $page->assertOk()->assertSee('name="pic"', false)->assertSee('All PIC');
    expect($page->viewData('picFilterOptions')->all())->toBe(['Andi', 'Budi']);

    $byBudi = $this->actingAs($user)->get(route('pm-schedules.index', ['pic' => 'Budi']));
    expect(picFilterNumbers($byBudi))->toBe([$budi->machine_number]);

    $unassigned = $this->actingAs($user)->get(route('pm-schedules.index', ['pic' => '__UNASSIGNED__']));
    expect(picFilterNumbers($unassigned))->toBe([$none->machine_number]);
})->with([User::ROLE_ADMIN, User::ROLE_KOORDINATOR, User::ROLE_SUPERVISOR]);

test('PIC filter options respect the area scope', function () {
    $koordinator = User::factory()->koordinator()->forArea('WWD')->create();
    picFilterSchedule('WWD', 'Budi');
    picFilterSchedule('BUL', 'Citra');
    $supervisor = User::factory()->supervisor()->create();
    $supervisor->areas()->attach(Area::firstOrCreate(['name' => 'BUL'], ['slug' => 'bul', 'is_active' => true]));

    expect($this->actingAs($koordinator)->get(route('pm-schedules.index'))->viewData('picFilterOptions')->all())->toBe(['Budi'])
        ->and($this->actingAs($supervisor->refresh())->get(route('pm-schedules.index'))->viewData('picFilterOptions')->all())->toBe(['Citra'])
        ->and($this->actingAs(User::factory()->admin()->create())->get(route('pm-schedules.index', ['area' => 'BUL']))->viewData('picFilterOptions')->all())->toBe(['Citra']);
});

test('PIC role does not get the PIC filter and cannot widen its own rows', function () {
    $pic = User::factory()->pic()->forArea('WWD')->create(['name' => 'Budi']);
    $mine = picFilterSchedule('WWD', 'Budi');
    picFilterSchedule('WWD', 'Andi');

    $page = $this->actingAs($pic)->get(route('pm-schedules.index', ['pic' => 'Andi']));

    $page->assertOk()->assertDontSee('All PIC');
    expect(picFilterNumbers($page))->toBe([$mine->machine_number]);
});
