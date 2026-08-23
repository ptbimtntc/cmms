<?php

use App\Models\Machine;
use App\Models\PMSchedule;
use App\Models\User;

test('admin can filter the whole dashboard by area', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $wwd = Machine::create(['machine_number' => 'MC-AF-WWD', 'area' => 'WWD', 'machine_type' => 'TypeX', 'status' => 'ACTIVE']);
    $bul = Machine::create(['machine_number' => 'MC-AF-BUL', 'area' => 'BUL', 'machine_type' => 'TypeX', 'status' => 'ACTIVE']);

    PMSchedule::create([
        'machine_id' => $wwd->id, 'machine_number' => $wwd->machine_number, 'machine_type' => 'TypeX', 'area' => 'WWD',
        'order_number' => 'WO-AF-1', 'plan_date' => now()->toDateString(), 'plan_month' => now()->format('F'),
        'plan_year' => now()->year, 'due_date' => now()->toDateString(), 'status' => 'OPEN',
    ]);
    PMSchedule::create([
        'machine_id' => $bul->id, 'machine_number' => $bul->machine_number, 'machine_type' => 'TypeX', 'area' => 'BUL',
        'order_number' => 'WO-AF-2', 'plan_date' => now()->toDateString(), 'plan_month' => now()->format('F'),
        'plan_year' => now()->year, 'due_date' => now()->toDateString(), 'status' => 'OPEN',
    ]);

    $all = $this->actingAs($admin)->get(route('dashboard'));
    $all->assertOk();
    expect($all->viewData('monthKpi')['target'])->toBe(2);

    $wwdOnly = $this->actingAs($admin)->get(route('dashboard', ['area' => 'WWD']));
    $wwdOnly->assertOk();
    expect($wwdOnly->viewData('monthKpi')['target'])->toBe(1);
    expect($wwdOnly->viewData('areaProgress'))->toHaveCount(1);
    expect($wwdOnly->viewData('areaProgress')->first()['name'])->toBe('WWD');

    $bulOnly = $this->actingAs($admin)->get(route('dashboard', ['area' => 'BUL']));
    $bulOnly->assertOk();
    expect($bulOnly->viewData('monthKpi')['target'])->toBe(1);
});

test('area filter is only exposed to admin, other roles cannot override their fixed area', function () {
    $koordinator = User::factory()->create(['role' => User::ROLE_KOORDINATOR_WWD]);

    $bul = Machine::create(['machine_number' => 'MC-AF-KOOR', 'area' => 'BUL', 'machine_type' => 'TypeX', 'status' => 'ACTIVE']);
    PMSchedule::create([
        'machine_id' => $bul->id, 'machine_number' => $bul->machine_number, 'machine_type' => 'TypeX', 'area' => 'BUL',
        'order_number' => 'WO-AF-3', 'plan_date' => now()->toDateString(), 'plan_month' => now()->format('F'),
        'plan_year' => now()->year, 'due_date' => now()->toDateString(), 'status' => 'OPEN',
    ]);

    // A KOORDINATOR WWD trying to force area=BUL via the query string must
    // still only see WWD data (0 here, since the only schedule is BUL).
    $response = $this->actingAs($koordinator)->get(route('dashboard', ['area' => 'BUL']));

    $response->assertOk();
    $response->assertDontSee('id="dashAreaFilter"', false);
    expect($response->viewData('monthKpi')['target'])->toBe(0);
});

test('oil audit section is hidden when admin filters to BUL', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $response = $this->actingAs($admin)->get(route('dashboard', ['area' => 'BUL']));

    $response->assertOk();
    $response->assertDontSee('Oil Audit');
});

test('activity timeline and pm due next 7 days are fully removed from the dashboard', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $response = $this->actingAs($admin)->get(route('dashboard'));

    $response->assertOk();
    $response->assertDontSee('Activity Timeline');
    $response->assertDontSee('PM Due (Next 7 Days)');
    expect(array_key_exists('activities', $response->viewData()))->toBeFalse();
    expect(array_key_exists('pmDue', $response->viewData()))->toBeFalse();
});
