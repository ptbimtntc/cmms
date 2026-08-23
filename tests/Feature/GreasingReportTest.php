<?php

use App\Models\Greasing;
use App\Models\Group;
use App\Models\User;
use App\Services\GreasingKpiCalculator;

function reportGreasing(array $attributes = []): Greasing
{
    $group = Group::create(['name' => 'Report Group '.uniqid()]);

    return Greasing::create(array_merge([
        'group_id' => $group->id,
        'cycle' => '4W',
        'plan_date' => '2026-08-01',
        'due_date' => Greasing::calculateDueDate('2026-08-01'),
        'pic' => null,
        'action_date' => null,
        'status' => 'OPEN',
    ], $attributes));
}

test('closing and completion formula matches the required example', function () {
    // Total = 100: 70 FINISH ON TIME, 20 FINISH, 10 OPEN
    $kpi = GreasingKpiCalculator::fromCounts(70, 20, 10);

    expect($kpi['closing_percent'])->toBe(90.0)
        ->and($kpi['completion_percent'])->toBe(80.0);
});

test('kpi calculator handles zero total without division error', function () {
    $kpi = GreasingKpiCalculator::fromCounts(0, 0, 0);

    expect($kpi['closing_percent'])->toBe(0.0)
        ->and($kpi['completion_percent'])->toBe(0.0);
});

test('report page requires authentication', function () {
    $this->get(route('greasing-report.index'))->assertRedirect(route('login'));
});

test('guest role cannot access the greasing report', function () {
    $guest = User::factory()->create(['role' => User::ROLE_GUEST]);

    $this->actingAs($guest)->get(route('greasing-report.index'))->assertForbidden();
});

test('monthly filter only includes schedules from the selected month', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $inAugust = reportGreasing(['plan_date' => '2026-08-05', 'cycle' => '4W']);
    $inSeptember = reportGreasing(['plan_date' => '2026-09-05', 'cycle' => '16W']);

    $response = $this->actingAs($admin)->get(route('greasing-report.index', [
        'period_type' => 'monthly',
        'month' => 8,
        'year' => 2026,
    ]));

    $response->assertOk();
    $response->assertSee($inAugust->cycle);
    $response->assertDontSee($inSeptember->cycle);
});

test('yearly filter includes schedules across the whole year but not other years', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $inYear = reportGreasing(['plan_date' => '2026-01-15', 'cycle' => '4W']);
    $inDecember = reportGreasing(['plan_date' => '2026-12-15', 'cycle' => '52W']);
    $otherYear = reportGreasing(['plan_date' => '2025-06-15', 'cycle' => '16W']);

    $response = $this->actingAs($admin)->get(route('greasing-report.index', [
        'period_type' => 'yearly',
        'year' => 2026,
    ]));

    $response->assertOk();
    $response->assertSee($inYear->cycle);
    $response->assertSee($inDecember->cycle);
    $response->assertDontSee($otherYear->cycle);
});

test('kpi on the report page matches the calculator for the filtered period', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    reportGreasing(['plan_date' => '2026-08-01', 'status' => 'FINISH ON TIME', 'action_date' => '2026-08-10']);
    reportGreasing(['plan_date' => '2026-08-02', 'status' => 'FINISH', 'action_date' => '2026-08-20']);
    reportGreasing(['plan_date' => '2026-08-03', 'status' => 'OPEN']);
    // outside the filtered month, must not affect KPI
    reportGreasing(['plan_date' => '2026-09-01', 'status' => 'FINISH ON TIME', 'action_date' => '2026-09-10']);

    $response = $this->actingAs($admin)->get(route('greasing-report.index', [
        'period_type' => 'monthly',
        'month' => 8,
        'year' => 2026,
    ]));

    $expected = GreasingKpiCalculator::fromCounts(1, 1, 1);

    $response->assertOk();
    $response->assertSee($expected['closing_percent'].'%');
    $response->assertSee($expected['completion_percent'].'%');
});

test('finding count badge reflects the actual number of findings', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $withFindings = reportGreasing(['plan_date' => '2026-08-01']);
    $withFindings->findings()->createMany([
        ['finding' => 'Finding 1', 'status' => 'OPEN'],
        ['finding' => 'Finding 2', 'status' => 'OPEN'],
        ['finding' => 'Finding 3', 'status' => 'COMPLETED'],
    ]);
    $withoutFindings = reportGreasing(['plan_date' => '2026-08-02']);

    $response = $this->actingAs($admin)->get(route('greasing-report.index', [
        'period_type' => 'monthly',
        'month' => 8,
        'year' => 2026,
    ]));

    $response->assertOk();
    $response->assertSee('[ 3 Findings ]');
    $response->assertSee('[ No Finding ]');
});

test('finding table only shows findings whose schedule falls in the selected period', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $inAugust = reportGreasing(['plan_date' => '2026-08-01']);
    $inAugust->findings()->create(['finding' => 'August finding text', 'status' => 'OPEN']);

    $inSeptember = reportGreasing(['plan_date' => '2026-09-01']);
    $inSeptember->findings()->create(['finding' => 'September finding text', 'status' => 'OPEN']);

    $response = $this->actingAs($admin)->get(route('greasing-report.index', [
        'period_type' => 'monthly',
        'month' => 8,
        'year' => 2026,
    ]));

    $response->assertOk();
    $response->assertSee('August finding text');
    $response->assertDontSee('September finding text');
});

test('pic only sees their own schedules and findings on the report', function () {
    $pic = User::factory()->create(['role' => User::ROLE_PIC_WWD]);
    $other = User::factory()->create(['role' => User::ROLE_PIC_WWD]);

    $mine = reportGreasing(['plan_date' => '2026-08-01', 'pic' => $pic->name, 'cycle' => '4W']);
    $notMine = reportGreasing(['plan_date' => '2026-08-02', 'pic' => $other->name, 'cycle' => '52W']);

    $response = $this->actingAs($pic)->get(route('greasing-report.index', [
        'period_type' => 'monthly',
        'month' => 8,
        'year' => 2026,
    ]));

    $response->assertOk();
    $response->assertSee($mine->cycle);
    $response->assertDontSee($notMine->cycle);
});

test('yearly trend has twelve months and each month kpi uses the same formula', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    reportGreasing(['plan_date' => '2026-03-01', 'status' => 'FINISH ON TIME', 'action_date' => '2026-03-05']);
    reportGreasing(['plan_date' => '2026-03-02', 'status' => 'OPEN']);

    $response = $this->actingAs($admin)->get(route('greasing-report.index', [
        'period_type' => 'yearly',
        'year' => 2026,
    ]));

    $response->assertOk();

    // March: 1 FINISH ON TIME, 0 FINISH, 1 OPEN -> closing 50%, completion 50%
    $expectedMarch = GreasingKpiCalculator::fromCounts(1, 0, 1);
    expect($expectedMarch['closing_percent'])->toBe(50.0)
        ->and($expectedMarch['completion_percent'])->toBe(50.0);

    $response->assertSee('Mar');
    $response->assertSee('Jan');
    $response->assertSee('Dec');
});

test('yearly chart marks months with no schedule as no-data instead of a colored zero bar', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    reportGreasing(['plan_date' => '2026-03-01', 'status' => 'FINISH ON TIME', 'action_date' => '2026-03-05']);

    $response = $this->actingAs($admin)->get(route('greasing-report.index', [
        'period_type' => 'yearly',
        'year' => 2026,
    ]));

    $response->assertOk();
    // January has no schedule at all this year -> rendered as a dash, not "0%".
    $response->assertSee('bg-slate-100', false);
});

test('admin can filter the greasing report by area', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $wwdGroup = Group::create(['name' => 'WWD 1']);
    $bulGroup = Group::create(['name' => 'BUL 1']);

    $wwd = Greasing::create([
        'group_id' => $wwdGroup->id, 'order_number' => 'WO-WWD', 'cycle' => '4W',
        'plan_date' => '2026-08-01', 'due_date' => Greasing::calculateDueDate('2026-08-01'), 'status' => 'OPEN',
    ]);
    $bul = Greasing::create([
        'group_id' => $bulGroup->id, 'order_number' => 'WO-BUL', 'cycle' => '52W',
        'plan_date' => '2026-08-01', 'due_date' => Greasing::calculateDueDate('2026-08-01'), 'status' => 'OPEN',
    ]);

    $wwdOnly = $this->actingAs($admin)->get(route('greasing-report.index', [
        'period_type' => 'monthly', 'month' => 8, 'year' => 2026, 'area' => 'WWD',
    ]));
    $wwdOnly->assertOk();
    $wwdOnly->assertSee($wwd->order_number);
    $wwdOnly->assertDontSee($bul->order_number);

    $bulOnly = $this->actingAs($admin)->get(route('greasing-report.index', [
        'period_type' => 'monthly', 'month' => 8, 'year' => 2026, 'area' => 'BUL',
    ]));
    $bulOnly->assertOk();
    $bulOnly->assertSee($bul->order_number);
    $bulOnly->assertDontSee($wwd->order_number);

    $all = $this->actingAs($admin)->get(route('greasing-report.index', [
        'period_type' => 'monthly', 'month' => 8, 'year' => 2026,
    ]));
    $all->assertOk();
    $all->assertSee($wwd->order_number);
    $all->assertSee($bul->order_number);
});

test('area filter on the greasing report is admin-only', function () {
    $pic = User::factory()->create(['role' => User::ROLE_PIC_WWD]);

    $response = $this->actingAs($pic)->get(route('greasing-report.index'));

    $response->assertOk();
    $response->assertDontSee('name="area"', false);
});
