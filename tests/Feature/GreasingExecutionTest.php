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

    $response->assertRedirect(route('greasings.index'));
    $greasing->refresh();

    expect($greasing->status)->toBe('FINISH ON TIME')
        ->and($greasing->findings()->count())->toBe(3);
});

test('new finding defaults to OPEN status', function () {
    $pic = User::factory()->create(['role' => User::ROLE_PIC_WWD]);
    $greasing = makeGreasing(['pic' => $pic->name]);

    $this->actingAs($pic)->post(route('greasings.execute.store', $greasing), [
        'action_date' => $greasing->due_date->format('Y-m-d'),
        'findings' => ['Grease point blocked'],
    ]);

    $finding = $greasing->findings()->first();

    expect($finding->status)->toBe('OPEN');
});

test('execution without an action date fails validation and does not save', function () {
    $pic = User::factory()->create(['role' => User::ROLE_PIC_WWD]);
    $greasing = makeGreasing(['pic' => $pic->name]);

    $response = $this->actingAs($pic)->post(route('greasings.execute.store', $greasing), [
        'remarks' => 'trying to save without a date',
    ]);

    $response->assertSessionHasErrors('action_date');
    expect($greasing->fresh()->status)->toBe('OPEN')
        ->and($greasing->fresh()->action_date)->toBeNull();
});

test('saving a valid execution redirects to the greasing index, not back to the execute page', function () {
    $pic = User::factory()->create(['role' => User::ROLE_PIC_WWD]);
    $greasing = makeGreasing(['pic' => $pic->name]);

    $response = $this->actingAs($pic)->post(route('greasings.execute.store', $greasing), [
        'action_date' => $greasing->due_date->format('Y-m-d'),
    ]);

    $response->assertRedirect(route('greasings.index'));
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

test('non-admin sees the Execute button relabeled to Edit once a schedule is no longer OPEN', function () {
    $pic = User::factory()->create(['role' => User::ROLE_PIC_WWD]);
    $finished = makeGreasing([
        'pic' => $pic->name,
        'action_date' => '2026-08-10',
        'status' => 'FINISH ON TIME',
    ]);

    $response = $this->actingAs($pic)->get(route('greasings.index'));

    $response->assertOk();
    $response->assertSeeInOrder(['Edit'], false);
});

test('non-admin still sees Execute label while a schedule is OPEN', function () {
    $pic = User::factory()->create(['role' => User::ROLE_PIC_WWD]);
    makeGreasing(['pic' => $pic->name, 'status' => 'OPEN']);

    $response = $this->actingAs($pic)->get(route('greasings.index'));

    $response->assertOk();
    $response->assertSee('Execute');
});

test('admin always sees Execute label on the execute link regardless of status', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $greasing = makeGreasing(['status' => 'FINISH ON TIME', 'action_date' => '2026-08-10']);

    $response = $this->actingAs($admin)->get(route('greasings.index'));

    $response->assertOk();
    // Admin also has a separate real "Edit" link (route('greasings.edit'))
    // regardless of status, so we check the execute link specifically
    // rather than asserting "Edit" is absent from the page entirely.
    preg_match('/href="[^"]*'.$greasing->id.'\/execute"[^>]*>\s*(\w+)\s*</', $response->getContent(), $matches);
    expect($matches[1] ?? null)->toBe('Execute');
});
