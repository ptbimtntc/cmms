<?php

use App\Models\Greasing;
use App\Models\GreasingFinding;
use App\Models\Group;
use App\Models\User;

function followUpGreasing(array $attributes = []): Greasing
{
    $group = Group::create(['name' => 'FollowUp Group '.uniqid()]);

    return Greasing::create(array_merge([
        'group_id' => $group->id,
        'cycle' => '4W',
        'plan_date' => '2026-08-01',
        'due_date' => Greasing::calculateDueDate('2026-08-01'),
        'pic' => null,
        'action_date' => '2026-08-10',
        'status' => Greasing::resolveStatus('2026-08-10', Greasing::calculateDueDate('2026-08-01')),
    ], $attributes));
}

test('open finding shows a follow-up form on the finding report', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $greasing = followUpGreasing();
    $finding = $greasing->findings()->create(['finding' => 'Grease point blocked', 'status' => 'OPEN']);

    $response = $this->actingAs($admin)->get(route('greasing-report.index', [
        'period_type' => 'monthly', 'month' => 8, 'year' => 2026,
    ]));

    $response->assertOk();
    $response->assertSee(route('greasings.findings.update', [$greasing, $finding]), false);
    $response->assertSee('Grease point blocked');
});

test('completed finding does not show a follow-up form', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $greasing = followUpGreasing();
    $finding = $greasing->findings()->create([
        'finding' => 'Already fixed leak',
        'status' => 'COMPLETED',
        'action' => 'seal replaced',
        'action_date' => '2026-08-05',
    ]);

    $response = $this->actingAs($admin)->get(route('greasing-report.index', [
        'period_type' => 'monthly', 'month' => 8, 'year' => 2026,
    ]));

    $response->assertOk();
    $response->assertDontSee(route('greasings.findings.update', [$greasing, $finding]), false);
    $response->assertSee('seal replaced');
});

test('action date and action can be filled when following up a finding', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $greasing = followUpGreasing();
    $finding = $greasing->findings()->create(['finding' => 'Bearing noise', 'status' => 'OPEN']);

    $this->actingAs($admin)->patch(route('greasings.findings.update', [$greasing, $finding]), [
        'action_date' => '2026-08-12',
        'action' => 'Bearing replaced',
        'status' => 'COMPLETED',
    ])->assertRedirect();

    $finding->refresh();
    expect($finding->action_date->format('Y-m-d'))->toBe('2026-08-12')
        ->and($finding->action)->toBe('Bearing replaced')
        ->and($finding->status)->toBe('COMPLETED');
});

test('finding stays OPEN when the follow-up is not yet complete', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $greasing = followUpGreasing();
    $finding = $greasing->findings()->create(['finding' => 'Waiting for spare part', 'status' => 'OPEN']);

    $this->actingAs($admin)->patch(route('greasings.findings.update', [$greasing, $finding]), [
        'action_date' => '2026-08-12',
        'action' => 'Ordered replacement part',
        'status' => 'OPEN',
    ])->assertRedirect();

    expect($finding->fresh()->status)->toBe('OPEN');
});

test('closing a finding does not change greasing status, plan date, due date, cycle, or group', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $greasing = followUpGreasing([
        'plan_date' => '2026-08-01',
        'due_date' => Greasing::calculateDueDate('2026-08-01'),
        'action_date' => '2026-08-10',
        'status' => 'FINISH ON TIME',
    ]);
    $originalGroupId = $greasing->group_id;
    $originalCycle = $greasing->cycle;
    $originalPlanDate = $greasing->plan_date->format('Y-m-d');
    $originalDueDate = $greasing->due_date->format('Y-m-d');

    $finding = $greasing->findings()->create(['finding' => 'Minor leak', 'status' => 'OPEN']);

    $this->actingAs($admin)->patch(route('greasings.findings.update', [$greasing, $finding]), [
        'status' => 'COMPLETED',
        'action' => 'fixed',
        'action_date' => '2026-08-11',
    ])->assertRedirect();

    $greasing->refresh();

    expect($greasing->status)->toBe('FINISH ON TIME')
        ->and($greasing->plan_date->format('Y-m-d'))->toBe($originalPlanDate)
        ->and($greasing->due_date->format('Y-m-d'))->toBe($originalDueDate)
        ->and($greasing->cycle)->toBe($originalCycle)
        ->and($greasing->group_id)->toBe($originalGroupId);
});

test('a pic without authorization cannot update a finding via direct request', function () {
    $owner = User::factory()->create(['role' => User::ROLE_PIC_WWD, 'name' => 'Owner PIC']);
    $stranger = User::factory()->create(['role' => User::ROLE_PIC_WWD, 'name' => 'Stranger PIC']);
    $greasing = followUpGreasing(['pic' => $owner->name]);
    $finding = $greasing->findings()->create(['finding' => 'Owner only finding', 'status' => 'OPEN']);

    $response = $this->actingAs($stranger)->patch(route('greasings.findings.update', [$greasing, $finding]), [
        'status' => 'COMPLETED',
        'action' => 'hijacked',
    ]);

    $response->assertForbidden();
    expect($finding->fresh()->status)->toBe('OPEN')
        ->and($finding->fresh()->action)->toBeNull();
});

test('guest cannot update a finding via direct request', function () {
    $guest = User::factory()->create(['role' => User::ROLE_GUEST]);
    $greasing = followUpGreasing();
    $finding = $greasing->findings()->create(['finding' => 'Guest cannot touch this', 'status' => 'OPEN']);

    $response = $this->actingAs($guest)->patch(route('greasings.findings.update', [$greasing, $finding]), [
        'status' => 'COMPLETED',
    ]);

    $response->assertForbidden();
    expect($finding->fresh()->status)->toBe('OPEN');
});

test('pic owner can follow up and close their own finding', function () {
    $pic = User::factory()->create(['role' => User::ROLE_PIC_WWD]);
    $greasing = followUpGreasing(['pic' => $pic->name]);
    $finding = $greasing->findings()->create(['finding' => 'My own finding', 'status' => 'OPEN']);

    $response = $this->actingAs($pic)->patch(route('greasings.findings.update', [$greasing, $finding]), [
        'status' => 'COMPLETED',
        'action' => 'resolved by pic',
        'action_date' => '2026-08-15',
    ]);

    $response->assertRedirect();
    expect($finding->fresh()->status)->toBe('COMPLETED');
});

test('admin and koordinator can close a finding regardless of assigned pic', function () {
    $koordinator = User::factory()->create(['role' => User::ROLE_KOORDINATOR_WWD]);
    $pic = User::factory()->create(['role' => User::ROLE_PIC_WWD]);
    $greasing = followUpGreasing(['pic' => $pic->name]);
    $finding = $greasing->findings()->create(['finding' => 'Assigned to pic, closed by koordinator', 'status' => 'OPEN']);

    $this->actingAs($koordinator)->patch(route('greasings.findings.update', [$greasing, $finding]), [
        'status' => 'COMPLETED',
    ])->assertRedirect();

    expect($finding->fresh()->status)->toBe('COMPLETED');
});

test('finding status only accepts OPEN or COMPLETED', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $greasing = followUpGreasing();
    $finding = $greasing->findings()->create(['finding' => 'Invalid status attempt', 'status' => 'OPEN']);

    $response = $this->actingAs($admin)->patch(route('greasings.findings.update', [$greasing, $finding]), [
        'status' => 'IN_PROGRESS',
    ]);

    $response->assertSessionHasErrors('status');
    expect($finding->fresh()->status)->toBe('OPEN');
});

test('a finding cannot be attached to a mismatched greasing via url tampering', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $greasingA = followUpGreasing();
    $greasingB = followUpGreasing();
    $finding = $greasingA->findings()->create(['finding' => 'Belongs to A', 'status' => 'OPEN']);

    $response = $this->actingAs($admin)->patch(route('greasings.findings.update', [$greasingB, $finding]), [
        'status' => 'COMPLETED',
    ]);

    $response->assertNotFound();
});
