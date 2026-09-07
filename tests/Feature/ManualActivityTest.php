<?php

use App\Models\ManualActivity;
use App\Models\User;
use App\Services\ActiveActivityResolver;

function manualActor(string $role): User
{
    return User::factory()->create(['role' => $role, 'name' => 'Actor '.uniqid(), 'is_active' => true]);
}

function manualPic(string $role = User::ROLE_PIC_WWD, ?string $name = null): User
{
    return User::factory()->create([
        'role' => $role,
        'name' => $name ?? ('PIC '.uniqid()),
        'is_active' => true,
    ]);
}

test('ADMIN and KOORDINATOR see the + ACTIVITY button and the PIC picker', function () {
    manualPic(User::ROLE_PIC_WWD, 'ZORRO');

    foreach ([User::ROLE_ADMIN, User::ROLE_KOORDINATOR_WWD] as $role) {
        $this->actingAs(manualActor($role))
            ->get(route('today-activity.index'))
            ->assertOk()
            ->assertSee('manual-activity-open', false)
            ->assertSee('User / PIC')
            ->assertSee('ZORRO');
    }
});

test('PIC does not see the + ACTIVITY button', function () {
    foreach ([User::ROLE_PIC_WWD, User::ROLE_PIC_BUL] as $role) {
        $this->actingAs(manualActor($role))
            ->get(route('today-activity.index'))
            ->assertOk()
            ->assertDontSee('manual-activity-open', false);
    }
});

test('a koordinator can start a manual activity for a PIC in their area, no machine', function () {
    $koor = manualActor(User::ROLE_KOORDINATOR_WWD);
    $pic = manualPic(User::ROLE_PIC_WWD, 'BUDI');

    $this->actingAs($koor)
        ->post(route('today-activity.manual.store'), [
            'user_id' => $pic->id,
            'name' => 'Meeting',
            'started_at' => now()->format('Y-m-d\TH:i'),
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $manual = ManualActivity::first();

    expect($manual->name)->toBe('Meeting')
        ->and($manual->machine_number)->toBeNull()
        ->and($manual->user_id)->toBe($pic->id);            // stored against the PIC, not the koordinator

    // ...and it is immediately the PIC's active activity.
    expect(app(ActiveActivityResolver::class)->currentFor($pic->fresh())->source)->toBe('MANUAL');
});

test('a manual activity with a free-text location shows the ACTIVITY NAME (not "Manual Activity") on the monitor', function () {
    $koor = manualActor(User::ROLE_KOORDINATOR_WWD);
    $pic = manualPic(User::ROLE_PIC_WWD, 'SARI');

    $this->actingAs($koor)->post(route('today-activity.manual.store'), [
        'user_id' => $pic->id,
        'name' => 'Repair Conveyor',
        'machine_number' => 'Conveyor #03',   // free text, no machine record needed
        'started_at' => now()->format('Y-m-d\TH:i'),
    ])->assertSessionHas('success');

    $current = app(ActiveActivityResolver::class)->currentFor($pic->fresh());
    expect($current->source)->toBe('MANUAL')
        ->and($current->displayLabel())->toBe('Repair Conveyor')
        ->and($current->machineNumber)->toBe('Conveyor #03');

    $this->getJson(route('monitor.data'))
        ->assertOk()
        ->assertJsonPath('active.0.name', 'SARI')
        ->assertJsonPath('active.0.activity', 'Repair Conveyor')   // the name, not the generic label
        ->assertJsonPath('active.0.machine', 'Conveyor #03');

    $this->get(route('monitor'))->assertOk()->assertSee('Repair Conveyor');
});

test('a PIC cannot start a manual activity (server-side, not just a hidden button)', function () {
    $pic = manualActor(User::ROLE_PIC_WWD);
    $target = manualPic(User::ROLE_PIC_WWD);

    $this->actingAs($pic)
        ->post(route('today-activity.manual.store'), [
            'user_id' => $target->id,
            'name' => 'Whatever',
            'started_at' => now()->format('Y-m-d\TH:i'),
        ])
        ->assertForbidden();

    expect(ManualActivity::count())->toBe(0);
});

test('ADMIN can start a manual activity for a PIC in EITHER area', function () {
    $admin = manualActor(User::ROLE_ADMIN);
    $wwd = manualPic(User::ROLE_PIC_WWD);
    $bul = manualPic(User::ROLE_PIC_BUL);

    foreach ([$wwd, $bul] as $pic) {
        $this->actingAs($admin)->post(route('today-activity.manual.store'), [
            'user_id' => $pic->id,
            'name' => 'Task '.$pic->id,
            'started_at' => now()->format('Y-m-d\TH:i'),
        ])->assertSessionHas('success');
    }

    expect(ManualActivity::count())->toBe(2);
});

test('a KOORDINATOR cannot start a manual activity for a PIC outside their area', function () {
    $koorWwd = manualActor(User::ROLE_KOORDINATOR_WWD);
    $picBul = manualPic(User::ROLE_PIC_BUL);

    $this->actingAs($koorWwd)->post(route('today-activity.manual.store'), [
        'user_id' => $picBul->id,
        'name' => 'Cross-area',
        'started_at' => now()->format('Y-m-d\TH:i'),
    ])->assertForbidden();

    expect(ManualActivity::count())->toBe(0);
});

test('the PIC picker is area-scoped for a KOORDINATOR', function () {
    $koorWwd = manualActor(User::ROLE_KOORDINATOR_WWD);
    manualPic(User::ROLE_PIC_WWD, 'WWD PERSON');
    manualPic(User::ROLE_PIC_BUL, 'BUL PERSON');

    $this->actingAs($koorWwd)->get(route('today-activity.index'))
        ->assertOk()
        ->assertSee('WWD PERSON')
        ->assertDontSee('BUL PERSON');
});

test('user_id and activity name are required', function () {
    $koor = manualActor(User::ROLE_KOORDINATOR_WWD);

    $this->actingAs($koor)
        ->post(route('today-activity.manual.store'), ['started_at' => now()->format('Y-m-d\TH:i')])
        ->assertSessionHasErrors(['user_id', 'name']);

    expect(ManualActivity::count())->toBe(0);
});

test('starting a second activity for a PIC who is already active asks for END & START', function () {
    $koor = manualActor(User::ROLE_KOORDINATOR_WWD);
    $pic = manualPic(User::ROLE_PIC_WWD);

    ManualActivity::create([
        'user_id' => $pic->id,
        'name' => 'Repair Conveyor',
        'started_at' => now()->subHour(),
    ]);

    $this->actingAs($koor)
        ->post(route('today-activity.manual.store'), [
            'user_id' => $pic->id,
            'name' => 'Meeting',
            'started_at' => now()->format('Y-m-d\TH:i'),
        ])
        ->assertSessionHas('activity_conflict');

    expect(ManualActivity::where('name', 'Meeting')->exists())->toBeFalse();
});

test('END & START replaces the PIC previous manual activity as the active one', function () {
    $koor = manualActor(User::ROLE_KOORDINATOR_WWD);
    $pic = manualPic(User::ROLE_PIC_WWD);

    $first = ManualActivity::create([
        'user_id' => $pic->id,
        'name' => 'Repair Conveyor',
        'started_at' => now()->subHour(),
    ]);

    $this->actingAs($koor)
        ->post(route('today-activity.manual.store'), [
            'user_id' => $pic->id,
            'name' => 'Meeting',
            'started_at' => now()->format('Y-m-d\TH:i'),
            'confirm_end_start' => '1',
        ])
        ->assertSessionHas('success');

    $current = app(ActiveActivityResolver::class)->currentFor($pic->fresh());

    expect($current->source)->toBe('MANUAL')
        ->and($current->recordId)->toBe(ManualActivity::where('name', 'Meeting')->value('id'))
        ->and($current->recordId)->not->toBe($first->id)
        ->and(ManualActivity::count())->toBe(2);   // nothing deleted
});

test('a manual activity from yesterday is not active today', function () {
    $koor = manualActor(User::ROLE_KOORDINATOR_WWD);
    $pic = manualPic(User::ROLE_PIC_WWD);

    ManualActivity::create([
        'user_id' => $pic->id,
        'name' => 'Old Task',
        'started_at' => now()->subDay()->setTime(10, 0),
    ]);

    expect(app(ActiveActivityResolver::class)->forToday($pic))->toHaveCount(0);
});

test('the conflict check is scoped to the target PIC, not the actor or other PICs', function () {
    $koor = manualActor(User::ROLE_KOORDINATOR_WWD);
    $picA = manualPic(User::ROLE_PIC_WWD);
    $picB = manualPic(User::ROLE_PIC_WWD);

    ManualActivity::create(['user_id' => $picA->id, 'name' => 'A task', 'started_at' => now()->subHour()]);

    // Starting for PIC B is not blocked by PIC A being active.
    $this->actingAs($koor)
        ->post(route('today-activity.manual.store'), [
            'user_id' => $picB->id,
            'name' => 'B task',
            'started_at' => now()->format('Y-m-d\TH:i'),
        ])
        ->assertSessionHas('success')
        ->assertSessionMissing('activity_conflict');
});
