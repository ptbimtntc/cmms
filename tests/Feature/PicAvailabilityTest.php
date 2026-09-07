<?php

use App\Models\Machine;
use App\Models\ManualActivity;
use App\Models\PicAvailability;
use App\Models\PMSchedule;
use App\Models\User;
use App\Services\ActiveActivityResolver;
use Illuminate\Support\Facades\Schema;

function availAdmin(): User
{
    return User::factory()->create(['role' => User::ROLE_ADMIN, 'is_active' => true]);
}

function availPic(string $name, string $role = User::ROLE_PIC_WWD): User
{
    return User::factory()->create(['role' => $role, 'name' => $name, 'is_active' => true]);
}

function availPm(User $pic, string $machineNumber): PMSchedule
{
    $machine = Machine::create(['machine_number' => $machineNumber, 'area' => 'WWD', 'machine_type' => 'NDE', 'status' => 'ACTIVE']);
    $t = now()->toDateString();

    return PMSchedule::create([
        'machine_id' => $machine->id, 'machine_number' => $machineNumber, 'machine_type' => 'NDE', 'area' => 'WWD',
        'order_number' => 'O-'.uniqid(), 'plan_date' => $t, 'plan_month' => now()->format('F'), 'plan_year' => now()->format('Y'),
        'due_date' => now()->addDays(14), 'pic' => $pic->name, 'status' => 'IN_PROGRESS', 'start_time' => '08:00', 'actual_date' => $t,
    ]);
}

test('schema: a dedicated pic_availabilities table, not maintenance_activities', function () {
    expect(Schema::hasTable('pic_availabilities'))->toBeTrue()
        ->and(Schema::hasColumn('pic_availabilities', 'reason'))->toBeTrue()
        ->and(Schema::hasColumn('pic_availabilities', 'notes'))->toBeTrue()
        ->and(Schema::hasColumn('pic_availabilities', 'date'))->toBeTrue()
        ->and(Schema::hasTable('maintenance_activities'))->toBeFalse();
});

test('an ADMIN can mark a NOT-STARTED PIC inactive; it is not an activity', function () {
    $admin = availAdmin();
    $pic = availPic('ANDI');

    $this->actingAs($admin)->post(route('today-activity.inactive.set'), [
        'user_id' => $pic->id, 'reason' => 'Training',
    ])->assertRedirect()->assertSessionHas('success');

    $row = PicAvailability::first();
    expect($row->user_id)->toBe($pic->id)
        ->and($row->reason)->toBe('Training')
        ->and($row->date->toDateString())->toBe(now()->toDateString())
        ->and($row->set_by_user_id)->toBe($admin->id);

    // Not active, not a card, not in the donut.
    expect(app(ActiveActivityResolver::class)->currentFor($pic->fresh()))->toBeNull();
    $this->getJson(route('monitor.data'))->assertOk()
        ->assertJsonPath('counts.inactive', 1)
        ->assertJsonPath('distribution.MANUAL', 0)
        ->assertJsonPath('inactive.0.name', 'ANDI')
        ->assertJsonPath('inactive.0.reason', 'Training');
});

test('reason "Other" requires notes', function () {
    $admin = availAdmin();
    $pic = availPic('BUDI');

    $this->actingAs($admin)->post(route('today-activity.inactive.set'), [
        'user_id' => $pic->id, 'reason' => 'Other',
    ])->assertSessionHasErrors('notes');

    expect(PicAvailability::count())->toBe(0);

    $this->actingAs($admin)->post(route('today-activity.inactive.set'), [
        'user_id' => $pic->id, 'reason' => 'Other', 'notes' => 'Dinas luar kota',
    ])->assertSessionHas('success');

    expect(PicAvailability::first()->label())->toBe('Dinas luar kota');
});

test('an invalid reason is rejected', function () {
    $admin = availAdmin();
    $pic = availPic('CICI');

    $this->actingAs($admin)->post(route('today-activity.inactive.set'), [
        'user_id' => $pic->id, 'reason' => 'Holiday',
    ])->assertSessionHasErrors('reason');
});

test('a PIC with an active activity cannot be set inactive', function () {
    $admin = availAdmin();
    $pic = availPic('DANI');
    availPm($pic, 'M-1');

    $this->actingAs($admin)->post(route('today-activity.inactive.set'), [
        'user_id' => $pic->id, 'reason' => 'Cuti',
    ])->assertSessionHas('warning');

    expect(PicAvailability::count())->toBe(0);
});

test('setting inactive changes nothing in PM / Manual data', function () {
    $admin = availAdmin();
    $pic = availPic('EKO');
    $manual = ManualActivity::create(['user_id' => $pic->id, 'name' => 'Break', 'started_at' => now()->subMinutes(5)]);
    // finish it so the PIC is NOT STARTED
    $this->actingAs($admin)->post(route('today-activity.finish'), [
        'source' => 'MANUAL', 'source_key' => (string) $manual->id, 'pic_user_id' => $pic->id,
    ])->assertSessionHas('success');

    $this->actingAs($admin)->post(route('today-activity.inactive.set'), [
        'user_id' => $pic->id, 'reason' => 'Sakit',
    ])->assertSessionHas('success');

    expect($manual->fresh()->name)->toBe('Break')
        ->and($manual->fresh()->started_at)->not->toBeNull();
});

test('authorization: PIC cannot set inactive; KOORDINATOR only within their area', function () {
    $plainPic = availPic('PLAIN');
    $koorBul = User::factory()->create(['role' => User::ROLE_KOORDINATOR_BUL, 'is_active' => true]);
    $wwdPic = availPic('WWD ONE', User::ROLE_PIC_WWD);
    $bulPic = availPic('BUL ONE', User::ROLE_PIC_BUL);

    $this->actingAs($plainPic)->post(route('today-activity.inactive.set'), [
        'user_id' => $wwdPic->id, 'reason' => 'Off',
    ])->assertForbidden();

    // BUL koordinator cannot touch a WWD PIC
    $this->actingAs($koorBul)->post(route('today-activity.inactive.set'), [
        'user_id' => $wwdPic->id, 'reason' => 'Off',
    ])->assertForbidden();

    // ...but can for a BUL PIC
    $this->actingAs($koorBul)->post(route('today-activity.inactive.set'), [
        'user_id' => $bulPic->id, 'reason' => 'Off',
    ])->assertSessionHas('success');

    expect(PicAvailability::count())->toBe(1);
});

test('clear inactive returns the PIC to NOT STARTED (area-scoped auth)', function () {
    $admin = availAdmin();
    $koorBul = User::factory()->create(['role' => User::ROLE_KOORDINATOR_BUL, 'is_active' => true]);
    $pic = availPic('FANI');

    $this->actingAs($admin)->post(route('today-activity.inactive.set'), ['user_id' => $pic->id, 'reason' => 'Meeting']);
    $row = PicAvailability::first();

    $this->actingAs($koorBul)->delete(route('today-activity.inactive.clear', $row))->assertForbidden();

    $this->actingAs($admin)->delete(route('today-activity.inactive.clear', $row))
        ->assertRedirect()->assertSessionHas('success');

    expect(PicAvailability::count())->toBe(0);
    $this->getJson(route('monitor.data'))->assertOk()->assertJsonPath('counts.inactive', 0);
});

test('monitor Area Status: available excludes inactive PICs', function () {
    // WWD: 3 PICs (1 active, 1 inactive, 1 not-started) -> 1 active / 2 available
    // BUL: 2 PICs (0 active, 0 inactive)                 -> 0 active / 2 available
    $w1 = availPic('W ONE', User::ROLE_PIC_WWD);
    $w2 = availPic('W TWO', User::ROLE_PIC_WWD);
    $w3 = availPic('W THREE', User::ROLE_PIC_WWD);
    availPic('B ONE', User::ROLE_PIC_BUL);
    availPic('B TWO', User::ROLE_PIC_BUL);

    availPm($w1, 'M-9');
    PicAvailability::create(['user_id' => $w2->id, 'date' => now()->toDateString(), 'reason' => 'Cuti', 'set_by_user_id' => $w2->id]);

    $this->getJson(route('monitor.data'))->assertOk()
        ->assertJsonPath('area.WWD.active', 1)
        ->assertJsonPath('area.WWD.available', 2)
        ->assertJsonPath('area.BUL.active', 0)
        ->assertJsonPath('area.BUL.available', 2)
        ->assertJsonPath('counts.active', 1)
        ->assertJsonPath('counts.inactive', 1)
        ->assertJsonPath('counts.notStarted', 3);   // w3 + B ONE + B TWO
});

test('the control panel shows PIC Availability with Set Inactive for NOT STARTED', function () {
    $admin = availAdmin();
    $active = availPic('ACTIVE PIC');
    availPm($active, 'M-3');
    availPic('IDLE PIC'); // not started
    $inactivePic = availPic('REST PIC');
    PicAvailability::create(['user_id' => $inactivePic->id, 'date' => now()->toDateString(), 'reason' => 'Sakit', 'notes' => null, 'set_by_user_id' => $admin->id]);

    $this->actingAs($admin)->get(route('today-activity.index'))->assertOk()
        ->assertSee('PIC Availability')
        ->assertSee('IDLE PIC')
        ->assertSee('Set Inactive')
        ->assertSee('REST PIC')
        ->assertSee('Set Available')
        ->assertSee('Sakit');
});

test('a plain PIC does not see PIC Availability or the inactive endpoint controls', function () {
    $pic = availPic('LONER');

    $this->actingAs($pic)->get(route('today-activity.index'))->assertOk()
        ->assertDontSee('PIC Availability')
        ->assertDontSee('pic-inactive-modal', false);
});
