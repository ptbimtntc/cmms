<?php

use App\Models\Greasing;
use App\Models\Group;
use App\Models\User;

function filterGreasing(Group $group, array $attributes = []): Greasing
{
    $planDate = $attributes['plan_date'] ?? '2026-08-01';

    return Greasing::create(array_merge([
        'group_id' => $group->id,
        'cycle' => '4W',
        'plan_date' => $planDate,
        'due_date' => Greasing::calculateDueDate($planDate),
        'pic' => null,
        'action_date' => null,
        'status' => 'OPEN',
    ], $attributes));
}

test('group filter narrows the greasing table', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $groupA = Group::create(['name' => 'Filter Group A '.uniqid()]);
    $groupB = Group::create(['name' => 'Filter Group B '.uniqid()]);

    filterGreasing($groupA, ['order_number' => 'ORD-A']);
    filterGreasing($groupB, ['order_number' => 'ORD-B']);

    $response = $this->actingAs($admin)->get(route('reports.greasing', [
        'year' => 2026, 'month' => 8, 'group_id' => $groupA->id,
    ]));

    $response->assertOk();
    $response->assertSee('ORD-A');
    $response->assertDontSee('ORD-B');
});

test('cycle filter narrows the greasing table', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $group = Group::create(['name' => 'Filter Group '.uniqid()]);

    filterGreasing($group, ['order_number' => 'ORD-4W', 'cycle' => '4W']);
    filterGreasing($group, ['order_number' => 'ORD-16W', 'cycle' => '16W']);

    $response = $this->actingAs($admin)->get(route('reports.greasing', [
        'year' => 2026, 'month' => 8, 'cycle' => '16W',
    ]));

    $response->assertOk();
    $response->assertSee('ORD-16W');
    $response->assertDontSee('ORD-4W');
});

test('pic filter narrows the greasing table for admin', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $group = Group::create(['name' => 'Filter Group '.uniqid()]);

    filterGreasing($group, ['order_number' => 'ORD-ANDI', 'pic' => 'Andi']);
    filterGreasing($group, ['order_number' => 'ORD-BUDI', 'pic' => 'Budi']);

    $response = $this->actingAs($admin)->get(route('reports.greasing', [
        'year' => 2026, 'month' => 8, 'pic' => 'Andi',
    ]));

    $response->assertOk();
    $response->assertSee('ORD-ANDI');
    $response->assertDontSee('ORD-BUDI');
});

test('pic filter dropdown is hidden for pic role users since they are already scoped', function () {
    $pic = User::factory()->create(['role' => User::ROLE_PIC_WWD, 'name' => 'Andi']);

    $response = $this->actingAs($pic)->get(route('reports.greasing'));

    $response->assertOk();
    $response->assertDontSee('name="pic"', false);
});

test('status filter narrows the greasing table', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $group = Group::create(['name' => 'Filter Group '.uniqid()]);

    filterGreasing($group, ['order_number' => 'ORD-OPEN', 'status' => 'OPEN']);
    filterGreasing($group, ['order_number' => 'ORD-FINISH', 'status' => 'FINISH', 'action_date' => '2026-09-01']);

    $response = $this->actingAs($admin)->get(route('reports.greasing', [
        'year' => 2026, 'month' => 8, 'status' => 'FINISH',
    ]));

    $response->assertOk();
    $response->assertSee('ORD-FINISH');
    $response->assertDontSee('ORD-OPEN');
});

test('search matches order number', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $group = Group::create(['name' => 'Filter Group '.uniqid()]);

    filterGreasing($group, ['order_number' => 'UNIQUE-12345']);
    filterGreasing($group, ['order_number' => 'OTHER-99999']);

    $response = $this->actingAs($admin)->get(route('reports.greasing', [
        'year' => 2026, 'month' => 8, 'search' => 'UNIQUE-12345',
    ]));

    $response->assertOk();
    expect($response->viewData('greasings')->total())->toBe(1);
});

test('search matches group name', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $uniqueGroup = Group::create(['name' => 'VeryUniqueGroupName']);
    $otherGroup = Group::create(['name' => 'SomeOtherGroup']);

    filterGreasing($uniqueGroup, ['order_number' => 'ORD-1']);
    filterGreasing($otherGroup, ['order_number' => 'ORD-2']);

    $response = $this->actingAs($admin)->get(route('reports.greasing', [
        'year' => 2026, 'month' => 8, 'search' => 'VeryUniqueGroupName',
    ]));

    $response->assertOk();
    expect($response->viewData('greasings')->total())->toBe(1);
});

test('filters and search combine with and logic', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $wwdGroup = Group::create(['name' => 'WWD Combo '.uniqid()]);
    $bulGroup = Group::create(['name' => 'BUL Combo '.uniqid()]);

    // Matches area + year + search.
    filterGreasing($wwdGroup, ['order_number' => '12345678', 'plan_date' => '2026-05-01']);
    // Same search term but wrong area — must be excluded.
    filterGreasing($bulGroup, ['order_number' => '12345678', 'plan_date' => '2026-05-02']);
    // Same area+year but wrong search term — must be excluded.
    filterGreasing($wwdGroup, ['order_number' => '00000000', 'plan_date' => '2026-05-03']);

    $response = $this->actingAs($admin)->get(route('reports.greasing', [
        'period_type' => 'yearly', 'year' => 2026, 'area' => 'WWD', 'search' => '12345678',
    ]));

    $response->assertOk();
    expect($response->viewData('greasings')->total())->toBe(1);
});

test('filters affect the yearly chart trend, not just the table', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $groupA = Group::create(['name' => 'Chart Group A '.uniqid()]);
    $groupB = Group::create(['name' => 'Chart Group B '.uniqid()]);

    // Group A: one FINISH ON TIME in March.
    filterGreasing($groupA, ['plan_date' => '2026-03-01', 'status' => 'FINISH ON TIME', 'action_date' => '2026-03-05']);
    // Group B: one OPEN in March — would change March's KPI if not filtered out.
    filterGreasing($groupB, ['plan_date' => '2026-03-02', 'status' => 'OPEN']);

    $response = $this->actingAs($admin)->get(route('reports.greasing', [
        'period_type' => 'yearly', 'year' => 2026, 'group_id' => $groupA->id,
    ]));

    $response->assertOk();
    $trend = collect($response->viewData('monthlyTrend'));
    $march = $trend->firstWhere('month', 3);

    expect($march['total'])->toBe(1)
        ->and($march['closing_percent'])->toBe(100.0);
});

test('pagination preserves active filters across pages', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $group = Group::create(['name' => 'Pagination Group '.uniqid()]);

    for ($i = 0; $i < 20; $i++) {
        filterGreasing($group, ['plan_date' => '2026-08-'.str_pad((string) ($i % 28 + 1), 2, '0', STR_PAD_LEFT)]);
    }

    $response = $this->actingAs($admin)->get(route('reports.greasing', [
        'year' => 2026, 'month' => 8, 'group_id' => $group->id, 'greasing_page' => 2,
    ]));

    $response->assertOk();
    $response->assertSee('group_id='.$group->id, false);
    expect($response->viewData('greasings')->currentPage())->toBe(2);
});
