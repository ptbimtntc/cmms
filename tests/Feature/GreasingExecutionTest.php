<?php

use App\Models\Greasing;
use App\Models\Group;
use App\Models\User;

function makeGreasing(array $attributes = []): Greasing
{
    $group = Group::create(['name' => 'Test Group '.uniqid()]);

    return Greasing::create(array_merge([
        'group_id' => $group->id,
        'order_number' => 'WO-'.uniqid(),
        'cycle' => '4W',
        'plan_date' => '2026-08-01',
        'due_date' => Greasing::calculateDueDate('2026-08-01'),
        'pic' => null,
        'action_date' => null,
        'status' => 'OPEN',
    ], $attributes));
}

test('schedule without action date is OPEN', function () {
    $greasing = makeGreasing();

    expect($greasing->status)->toBe('OPEN');
});

test('action date on or before due date resolves to FINISH ON TIME', function () {
    expect(Greasing::resolveStatus('2026-08-15', '2026-08-15'))->toBe('FINISH ON TIME');
});

test('action date after due date resolves to FINISH', function () {
    expect(Greasing::resolveStatus('2026-08-16', '2026-08-15'))->toBe('FINISH');
});

test('pic owner can execute their own schedule and add multiple findings', function () {
    $pic = User::factory()->create(['role' => User::ROLE_PIC_WWD]);
    $greasing = makeGreasing(['pic' => $pic->name]);

    $response = $this->actingAs($pic)->post(route('greasings.execute.store', $greasing), [
        'action_date' => $greasing->due_date->format('Y-m-d'),
        'remarks' => 'done',
        'findings' => ['Finding 1', 'Finding 2', 'Finding 3'],
    ]);

    $response->assertRedirect(route('greasings.execute', $greasing));
    $greasing->refresh();

    expect($greasing->status)->toBe('FINISH ON TIME')
        ->and($greasing->findings()->count())->toBe(3);
});

test('new finding defaults to OPEN status', function () {
    $pic = User::factory()->create(['role' => User::ROLE_PIC_WWD]);
    $greasing = makeGreasing(['pic' => $pic->name]);

    $this->actingAs($pic)->post(route('greasings.execute.store', $greasing), [
        'findings' => ['Grease point blocked'],
    ]);

    $finding = $greasing->findings()->first();

    expect($finding->status)->toBe('OPEN');
});

test('a finding can be updated to COMPLETED', function () {
    $pic = User::factory()->create(['role' => User::ROLE_PIC_WWD]);
    $greasing = makeGreasing(['pic' => $pic->name]);
    $finding = $greasing->findings()->create(['finding' => 'leak', 'status' => 'OPEN']);

    $response = $this->actingAs($pic)->patch(
        route('greasings.findings.update', [$greasing, $finding]),
        ['status' => 'COMPLETED', 'action' => 'seal replaced']
    );

    $response->assertRedirect();
    expect($finding->fresh()->status)->toBe('COMPLETED');
});

test('closing a finding does not change the greasing status', function () {
    $pic = User::factory()->create(['role' => User::ROLE_PIC_WWD]);
    $greasing = makeGreasing([
        'pic' => $pic->name,
        'action_date' => '2026-08-10',
        'status' => Greasing::resolveStatus('2026-08-10', Greasing::calculateDueDate('2026-08-01')),
    ]);
    $statusBefore = $greasing->status;
    $finding = $greasing->findings()->create(['finding' => 'leak', 'status' => 'OPEN']);

    $this->actingAs($pic)->patch(
        route('greasings.findings.update', [$greasing, $finding]),
        ['status' => 'COMPLETED']
    );

    expect($greasing->fresh()->status)->toBe($statusBefore);
});

test('a pic who is not assigned cannot execute the schedule via manual request', function () {
    $owner = User::factory()->create(['role' => User::ROLE_PIC_WWD, 'name' => 'Owner PIC']);
    $stranger = User::factory()->create(['role' => User::ROLE_PIC_WWD, 'name' => 'Stranger PIC']);
    $greasing = makeGreasing(['pic' => $owner->name]);

    $response = $this->actingAs($stranger)->post(route('greasings.execute.store', $greasing), [
        'action_date' => '2026-08-10',
    ]);

    $response->assertForbidden();
    expect($greasing->fresh()->action_date)->toBeNull();
});

test('a pic who is not assigned cannot even open the execute page', function () {
    $owner = User::factory()->create(['role' => User::ROLE_PIC_WWD, 'name' => 'Owner PIC']);
    $stranger = User::factory()->create(['role' => User::ROLE_PIC_WWD, 'name' => 'Stranger PIC']);
    $greasing = makeGreasing(['pic' => $owner->name]);

    $response = $this->actingAs($stranger)->get(route('greasings.execute', $greasing));

    $response->assertForbidden();
});

test('guest cannot open the execute page', function () {
    $guest = User::factory()->create(['role' => User::ROLE_GUEST]);
    $greasing = makeGreasing();

    $response = $this->actingAs($guest)->get(route('greasings.execute', $greasing));

    $response->assertForbidden();
});

test('guest cannot post execution', function () {
    $guest = User::factory()->create(['role' => User::ROLE_GUEST]);
    $greasing = makeGreasing();

    $response = $this->actingAs($guest)->post(route('greasings.execute.store', $greasing), [
        'action_date' => '2026-08-10',
    ]);

    $response->assertForbidden();
});

test('admin can execute any schedule regardless of assigned pic', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $pic = User::factory()->create(['role' => User::ROLE_PIC_WWD]);
    $greasing = makeGreasing(['pic' => $pic->name]);

    $response = $this->actingAs($admin)->get(route('greasings.execute', $greasing));

    $response->assertOk();
});

test('koordinator can execute any schedule regardless of assigned pic', function () {
    $koordinator = User::factory()->create(['role' => User::ROLE_KOORDINATOR_WWD]);
    $pic = User::factory()->create(['role' => User::ROLE_PIC_WWD]);
    $greasing = makeGreasing(['pic' => $pic->name]);

    $response = $this->actingAs($koordinator)->get(route('greasings.execute', $greasing));

    $response->assertOk();
});

test('changing plan date on admin edit recalculates due date and status from existing action date', function () {
    $koordinator = User::factory()->create(['role' => User::ROLE_KOORDINATOR_WWD]);
    $greasing = makeGreasing([
        'plan_date' => '2026-08-01',
        'due_date' => Greasing::calculateDueDate('2026-08-01'),
        'action_date' => '2026-08-20',
        'status' => Greasing::resolveStatus('2026-08-20', Greasing::calculateDueDate('2026-08-01')),
    ]);
    expect($greasing->status)->toBe('FINISH');

    // Moving plan_date later so the same action_date is now on-time.
    $response = $this->actingAs($koordinator)->put(route('greasings.update', $greasing), [
        'group_id' => $greasing->group_id,
        'order_number' => $greasing->order_number,
        'cycle' => $greasing->cycle,
        'plan_date' => '2026-08-10',
        'pic' => $greasing->pic,
    ]);

    $response->assertRedirect(route('greasings.index'));
    $greasing->refresh();

    expect($greasing->due_date->format('Y-m-d'))->toBe('2026-08-24')
        ->and($greasing->action_date->format('Y-m-d'))->toBe('2026-08-20')
        ->and($greasing->status)->toBe('FINISH ON TIME');
});

test('admin edit request cannot inject an arbitrary status', function () {
    $koordinator = User::factory()->create(['role' => User::ROLE_KOORDINATOR_WWD]);
    $greasing = makeGreasing();

    $this->actingAs($koordinator)->put(route('greasings.update', $greasing), [
        'group_id' => $greasing->group_id,
        'order_number' => $greasing->order_number,
        'cycle' => $greasing->cycle,
        'plan_date' => '2026-08-01',
        'status' => 'FINISH ON TIME',
    ]);

    expect($greasing->fresh()->status)->toBe('OPEN');
});

test('pic cannot access schedule create/edit/destroy routes', function () {
    $pic = User::factory()->create(['role' => User::ROLE_PIC_WWD]);
    $greasing = makeGreasing(['pic' => $pic->name]);

    $this->actingAs($pic)->get(route('greasings.create'))->assertForbidden();
    $this->actingAs($pic)->get(route('greasings.edit', $greasing))->assertForbidden();
    $this->actingAs($pic)->delete(route('greasings.destroy', $greasing))->assertForbidden();
});

test('pic index only shows their own schedules', function () {
    $pic = User::factory()->create(['role' => User::ROLE_PIC_WWD]);
    $other = User::factory()->create(['role' => User::ROLE_PIC_WWD]);
    $mine = makeGreasing(['pic' => $pic->name]);
    $notMine = makeGreasing(['pic' => $other->name]);

    $response = $this->actingAs($pic)->get(route('greasings.index'));

    $response->assertOk();
    $response->assertSee($mine->cycle);
    $response->assertDontSee('greasings/'.$notMine->id.'/execute');
});
