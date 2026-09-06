<?php

use App\Models\Greasing;
use App\Models\Group;
use App\Models\User;
use Carbon\Carbon;

function makeStartGreasing(array $attributes = []): Greasing
{
    $group = Group::create(['name' => 'WWD Group '.uniqid()]);

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

test('pic can start a greasing activity, writing only the start_time column', function () {
    $pic = User::factory()->create(['role' => User::ROLE_PIC_WWD, 'name' => 'Sari']);
    $greasing = makeStartGreasing(['pic' => 'Sari']);

    $this->actingAs($pic)
        ->post(route('greasings.start', $greasing), ['started_at' => '2026-09-06T08:30'])
        ->assertRedirect();

    $greasing->refresh();

    expect($greasing->start_time)->not->toBeNull()
        ->and($greasing->start_time->format('Y-m-d H:i'))->toBe('2026-09-06 08:30')
        ->and($greasing->status)->toBe('OPEN')            // scheduling/status untouched
        ->and($greasing->action_date)->toBeNull()
        ->and($greasing->isActiveActivity())->toBeTrue();
});

test('starting does not overwrite an already started greasing', function () {
    $pic = User::factory()->create(['role' => User::ROLE_PIC_WWD, 'name' => 'Sari']);
    $greasing = makeStartGreasing(['pic' => 'Sari', 'start_time' => '2026-09-05 07:00:00']);

    $this->actingAs($pic)
        ->post(route('greasings.start', $greasing), ['started_at' => '2026-09-06T09:00'])
        ->assertSessionHas('warning');

    expect($greasing->fresh()->start_time->format('Y-m-d H:i'))->toBe('2026-09-05 07:00');
});

test('a completed greasing schedule cannot be started', function () {
    $pic = User::factory()->create(['role' => User::ROLE_PIC_WWD, 'name' => 'Sari']);
    $greasing = makeStartGreasing([
        'pic' => 'Sari',
        'action_date' => '2026-08-10',
        'status' => 'FINISH ON TIME',
    ]);

    $this->actingAs($pic)
        ->post(route('greasings.start', $greasing), ['started_at' => '2026-09-06T08:30'])
        ->assertSessionHas('warning');

    expect($greasing->fresh()->start_time)->toBeNull();
});

test('a pic cannot start a greasing schedule assigned to someone else', function () {
    $pic = User::factory()->create(['role' => User::ROLE_PIC_WWD, 'name' => 'Sari']);
    $greasing = makeStartGreasing(['pic' => 'Andi']);

    $this->actingAs($pic)
        ->post(route('greasings.start', $greasing), ['started_at' => '2026-09-06T08:30'])
        ->assertForbidden();

    expect($greasing->fresh()->start_time)->toBeNull();
});

test('start without a datetime fails validation and saves nothing', function () {
    $pic = User::factory()->create(['role' => User::ROLE_PIC_WWD, 'name' => 'Sari']);
    $greasing = makeStartGreasing(['pic' => 'Sari']);

    $this->actingAs($pic)
        ->post(route('greasings.start', $greasing), [])
        ->assertSessionHasErrors('started_at');

    expect($greasing->fresh()->start_time)->toBeNull();
});

test('the greasing index shows START next to the action button, then STARTED after starting', function () {
    $pic = User::factory()->create(['role' => User::ROLE_PIC_WWD, 'name' => 'Sari']);
    $greasing = makeStartGreasing(['pic' => 'Sari']);

    $this->actingAs($pic)->get(route('greasings.index'))
        ->assertOk()
        ->assertSee('START')
        ->assertSee('Execute');

    $greasing->update(['start_time' => Carbon::parse('2026-09-06 08:30')]);

    $this->actingAs($pic)->get(route('greasings.index'))
        ->assertOk()
        ->assertSee('STARTED');
});

test('greasing execution flow still works unchanged after a start', function () {
    $pic = User::factory()->create(['role' => User::ROLE_PIC_WWD, 'name' => 'Sari']);
    $greasing = makeStartGreasing(['pic' => 'Sari', 'start_time' => Carbon::parse('2026-09-06 08:30')]);

    $this->actingAs($pic)->post(route('greasings.execute.store', $greasing), [
        'action_date' => $greasing->due_date->format('Y-m-d'),
        'remarks' => 'done',
        'findings' => ['Finding 1'],
    ])->assertRedirect(route('greasings.index'));

    $greasing->refresh();

    expect($greasing->status)->toBe('FINISH ON TIME')
        ->and($greasing->findings()->count())->toBe(1)
        ->and($greasing->start_time->format('Y-m-d H:i'))->toBe('2026-09-06 08:30')
        ->and($greasing->isActiveActivity())->toBeFalse();
});
