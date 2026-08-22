<?php

use App\Models\Greasing;
use App\Models\Group;
use App\Models\User;

function assignPicUser(string $role): User
{
    return User::factory()->create(['role' => $role]);
}

test('group name determines the inferred pic area', function () {
    expect((new Group(['name' => 'WWD 1']))->inferredArea())->toBe('WWD')
        ->and((new Group(['name' => 'Line BUL 2']))->inferredArea())->toBe('BUL')
        ->and((new Group(['name' => 'wwd lower']))->inferredArea())->toBe('WWD')
        ->and((new Group(['name' => 'Something Else']))->inferredArea())->toBeNull();
});

test('admin can assign a pic wwd user to a schedule in a wwd group', function () {
    $admin = assignPicUser('ADMIN');
    $group = Group::create(['name' => 'WWD 1']);
    $greasing = Greasing::create([
        'group_id' => $group->id,
        'order_number' => 'WO-1',
        'cycle' => '4W',
        'plan_date' => '2026-08-01',
        'due_date' => Greasing::calculateDueDate('2026-08-01'),
        'status' => 'OPEN',
    ]);
    $pic = assignPicUser('PIC WWD');

    $response = $this->actingAs($admin)->postJson(route('greasings.assign-pic', $greasing), [
        'pic' => $pic->name,
    ]);

    $response->assertOk()->assertJson(['success' => true]);
    expect($greasing->fresh()->pic)->toBe($pic->name);
});

test('a pic bul user cannot be assigned to a schedule in a wwd group', function () {
    $admin = assignPicUser('ADMIN');
    $group = Group::create(['name' => 'WWD 1']);
    $greasing = Greasing::create([
        'group_id' => $group->id,
        'order_number' => 'WO-2',
        'cycle' => '4W',
        'plan_date' => '2026-08-01',
        'due_date' => Greasing::calculateDueDate('2026-08-01'),
        'status' => 'OPEN',
    ]);
    $picBul = assignPicUser('PIC BUL');

    $response = $this->actingAs($admin)->postJson(route('greasings.assign-pic', $greasing), [
        'pic' => $picBul->name,
    ]);

    $response->assertStatus(422);
    expect($greasing->fresh()->pic)->toBeNull();
});

test('a pic wwd user cannot be assigned to a schedule in a bul group', function () {
    $admin = assignPicUser('ADMIN');
    $group = Group::create(['name' => 'BUL 1']);
    $greasing = Greasing::create([
        'group_id' => $group->id,
        'order_number' => 'WO-3',
        'cycle' => '4W',
        'plan_date' => '2026-08-01',
        'due_date' => Greasing::calculateDueDate('2026-08-01'),
        'status' => 'OPEN',
    ]);
    $picWwd = assignPicUser('PIC WWD');

    $response = $this->actingAs($admin)->postJson(route('greasings.assign-pic', $greasing), [
        'pic' => $picWwd->name,
    ]);

    $response->assertStatus(422);
    expect($greasing->fresh()->pic)->toBeNull();
});

test('selecting the blank option clears the assigned pic', function () {
    $admin = assignPicUser('ADMIN');
    $pic = assignPicUser('PIC WWD');
    $group = Group::create(['name' => 'WWD 1']);
    $greasing = Greasing::create([
        'group_id' => $group->id,
        'order_number' => 'WO-4',
        'cycle' => '4W',
        'plan_date' => '2026-08-01',
        'due_date' => Greasing::calculateDueDate('2026-08-01'),
        'pic' => $pic->name,
        'status' => 'OPEN',
    ]);

    $response = $this->actingAs($admin)->postJson(route('greasings.assign-pic', $greasing), [
        'pic' => '',
    ]);

    $response->assertOk();
    expect($greasing->fresh()->pic)->toBeNull();
});

test('assigning pic on a group whose area cannot be inferred is rejected', function () {
    $admin = assignPicUser('ADMIN');
    $group = Group::create(['name' => 'Unrelated Name']);
    $greasing = Greasing::create([
        'group_id' => $group->id,
        'order_number' => 'WO-5',
        'cycle' => '4W',
        'plan_date' => '2026-08-01',
        'due_date' => Greasing::calculateDueDate('2026-08-01'),
        'status' => 'OPEN',
    ]);
    $pic = assignPicUser('PIC WWD');

    $response = $this->actingAs($admin)->postJson(route('greasings.assign-pic', $greasing), [
        'pic' => $pic->name,
    ]);

    $response->assertStatus(422);
});

test('koordinator can assign pic regardless of which area they belong to', function () {
    $koordinatorBul = assignPicUser('KOORDINATOR BUL');
    $group = Group::create(['name' => 'WWD 1']);
    $greasing = Greasing::create([
        'group_id' => $group->id,
        'order_number' => 'WO-6',
        'cycle' => '4W',
        'plan_date' => '2026-08-01',
        'due_date' => Greasing::calculateDueDate('2026-08-01'),
        'status' => 'OPEN',
    ]);
    $pic = assignPicUser('PIC WWD');

    $this->actingAs($koordinatorBul)->postJson(route('greasings.assign-pic', $greasing), [
        'pic' => $pic->name,
    ])->assertOk();

    expect($greasing->fresh()->pic)->toBe($pic->name);
});

test('pic and guest cannot call the assign-pic endpoint directly', function (string $role) {
    $group = Group::create(['name' => 'WWD 1']);
    $greasing = Greasing::create([
        'group_id' => $group->id,
        'order_number' => 'WO-7',
        'cycle' => '4W',
        'plan_date' => '2026-08-01',
        'due_date' => Greasing::calculateDueDate('2026-08-01'),
        'status' => 'OPEN',
    ]);
    $pic = assignPicUser('PIC WWD');
    $user = assignPicUser($role);

    $this->actingAs($user)->postJson(route('greasings.assign-pic', $greasing), [
        'pic' => $pic->name,
    ])->assertForbidden();

    expect($greasing->fresh()->pic)->toBeNull();
})->with(['PIC WWD', 'PIC BUL', 'GUEST']);

test('index only renders the pic dropdown for admin/koordinator, not for pic', function () {
    $admin = assignPicUser('ADMIN');
    $pic = assignPicUser('PIC WWD');
    $group = Group::create(['name' => 'WWD 1']);
    Greasing::create([
        'group_id' => $group->id,
        'order_number' => 'WO-8',
        'cycle' => '4W',
        'plan_date' => '2026-08-01',
        'due_date' => Greasing::calculateDueDate('2026-08-01'),
        'pic' => $pic->name,
        'status' => 'OPEN',
    ]);

    // Note: the page always includes a small <script> that references the
    // ".assign-pic" selector, so we must assert on the rendered element's
    // opening tag specifically, not the bare "assign-pic" substring.
    $adminResponse = $this->actingAs($admin)->get(route('greasings.index'));
    $adminResponse->assertOk();
    $adminResponse->assertSee('class="assign-pic', false);

    $picResponse = $this->actingAs($pic)->get(route('greasings.index'));
    $picResponse->assertOk();
    $picResponse->assertDontSee('class="assign-pic', false);
});

test('index dropdown only lists pic users from the matching area', function () {
    $admin = assignPicUser('ADMIN');
    $wwdPic = assignPicUser('PIC WWD');
    $bulPic = assignPicUser('PIC BUL');
    $group = Group::create(['name' => 'WWD 1']);
    Greasing::create([
        'group_id' => $group->id,
        'order_number' => 'WO-9',
        'cycle' => '4W',
        'plan_date' => '2026-08-01',
        'due_date' => Greasing::calculateDueDate('2026-08-01'),
        'status' => 'OPEN',
    ]);

    $response = $this->actingAs($admin)->get(route('greasings.index'));

    $response->assertOk();
    $response->assertSee($wwdPic->name);
    $response->assertDontSee($bulPic->name);
});
