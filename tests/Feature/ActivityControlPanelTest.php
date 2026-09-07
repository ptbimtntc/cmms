<?php

use App\Models\ActivityMonitorClosure;
use App\Models\Greasing;
use App\Models\Group;
use App\Models\Machine;
use App\Models\ManualActivity;
use App\Models\PMSchedule;
use App\Models\User;
use App\Services\ActiveActivityResolver;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

function panelAdmin(): User
{
    return User::factory()->create(['role' => User::ROLE_ADMIN, 'name' => 'ADMIN X', 'is_active' => true]);
}

function panelPic(string $name, string $role = User::ROLE_PIC_WWD): User
{
    return User::factory()->create(['role' => $role, 'name' => $name, 'is_active' => true]);
}

function panelStartedPm(User $pic, string $machineNumber): PMSchedule
{
    $machine = Machine::create([
        'machine_number' => $machineNumber, 'area' => 'WWD', 'machine_type' => 'NDE', 'status' => 'ACTIVE',
    ]);
    $today = now()->toDateString();

    return PMSchedule::create([
        'machine_id' => $machine->id, 'machine_number' => $machineNumber, 'machine_type' => 'NDE', 'area' => 'WWD',
        'order_number' => 'ORD-'.uniqid(), 'plan_date' => $today,
        'plan_month' => Carbon::parse($today)->format('F'), 'plan_year' => Carbon::parse($today)->format('Y'),
        'due_date' => Carbon::parse($today)->addDays(14), 'pic' => $pic->name,
        'status' => 'IN_PROGRESS', 'start_time' => '08:00', 'actual_date' => $today,
    ]);
}

function panelStartedGreasing(User $pic): Greasing
{
    $group = Group::create(['name' => 'WWD GRP '.uniqid()]);

    return Greasing::create([
        'group_id' => $group->id, 'order_number' => 'WO-'.uniqid(), 'cycle' => '4W',
        'plan_date' => now()->toDateString(), 'due_date' => Greasing::calculateDueDate(now()->toDateString()),
        'pic' => $pic->name, 'status' => 'OPEN', 'start_time' => now()->setTime(9, 0),
    ]);
}

function finishPayload(string $source, string $sourceKey, User $pic): array
{
    return ['source' => $source, 'source_key' => $sourceKey, 'pic_user_id' => $pic->id];
}

test('schema: monitoring-only closure table exists, no columns added to module tables', function () {
    expect(Schema::hasTable('activity_monitor_closures'))->toBeTrue()
        ->and(Schema::hasTable('maintenance_activities'))->toBeFalse()
        ->and(Schema::hasColumn('manual_activities', 'ended_at'))->toBeFalse()
        ->and(Schema::hasColumn('pm_schedules', 'activity_ended_at'))->toBeFalse()
        ->and(Schema::hasColumn('pm_schedules', 'monitor_ended_at'))->toBeFalse()
        ->and(Schema::hasColumn('greasings', 'activity_ended_at'))->toBeFalse();
});

test('the panel lists activities from every source across all PICs for an ADMIN', function () {
    $admin = panelAdmin();
    $andi = panelPic('ANDI');
    $budi = panelPic('BUDI');

    panelStartedPm($andi, 'M-1023');
    panelStartedGreasing($budi);
    ManualActivity::create(['user_id' => $budi->id, 'name' => 'Repair Conveyor', 'started_at' => now()->setTime(10, 0)]);

    $this->actingAs($admin)->get(route('today-activity.index'))
        ->assertOk()
        ->assertSee('ANDI')->assertSee('BUDI')
        ->assertSee('M-1023')
        ->assertSee('Repair Conveyor')
        ->assertSee('Open Fill PM')
        ->assertSee('Open Execute')
        ->assertSee('Finish');
});

test('a plain PIC only sees their own activities and no manage actions', function () {
    $me = panelPic('ME PIC');
    $other = panelPic('OTHER PIC');
    panelStartedPm($me, 'M-1');
    panelStartedPm($other, 'M-2');

    $this->actingAs($me)->get(route('today-activity.index'))
        ->assertOk()
        ->assertSee('M-1')
        ->assertDontSee('M-2')
        ->assertDontSee('manual-activity-open', false)
        ->assertDontSee(route('today-activity.finish'));
});

test('a koordinator only sees / manages PICs in their own area', function () {
    $koorWwd = User::factory()->create(['role' => User::ROLE_KOORDINATOR_WWD, 'is_active' => true]);
    $wwd = panelPic('WWD ONE', User::ROLE_PIC_WWD);
    $bul = panelPic('BUL ONE', User::ROLE_PIC_BUL);
    ManualActivity::create(['user_id' => $wwd->id, 'name' => 'W task', 'started_at' => now()]);
    ManualActivity::create(['user_id' => $bul->id, 'name' => 'B task', 'started_at' => now()]);

    $this->actingAs($koorWwd)->get(route('today-activity.index'))
        ->assertOk()->assertSee('W task')->assertDontSee('B task')->assertDontSee('BUL ONE');
});

test('Finish a PM activity: it leaves the monitor but the PM work is untouched', function () {
    $admin = panelAdmin();
    $pic = panelPic('PM PIC');
    $pm = panelStartedPm($pic, 'M-77');

    expect(app(ActiveActivityResolver::class)->currentFor($pic->fresh())?->source)->toBe('PM');

    $this->actingAs($admin)
        ->post(route('today-activity.finish'), finishPayload('PM', (string) $pm->id, $pic))
        ->assertRedirect()->assertSessionHas('success');

    // Monitor: gone.
    expect(app(ActiveActivityResolver::class)->currentFor($pic->fresh()))->toBeNull();
    $this->getJson(route('monitor.data'))->assertOk()->assertJsonPath('activeCount', 0);

    // PM row: unchanged status / dates, and a closure row was written instead.
    expect($pm->fresh()->status)->toBe('IN_PROGRESS')
        ->and($pm->fresh()->actual_date)->toBe(now()->toDateString())
        ->and($pm->fresh()->start_time)->toBe('08:00')
        ->and(ActivityMonitorClosure::where('source', 'PM')->where('source_key', (string) $pm->id)->count())->toBe(1);

    // Panel still lists it, as FINISHED.
    $this->actingAs($admin)->get(route('today-activity.index'))->assertOk()->assertSee('FINISHED');
});

test('Finish a Greasing activity does not change greasing status', function () {
    $admin = panelAdmin();
    $pic = panelPic('GR PIC');
    $greasing = panelStartedGreasing($pic);

    $this->actingAs($admin)
        ->post(route('today-activity.finish'), finishPayload('GREASING', (string) $greasing->id, $pic))
        ->assertSessionHas('success');

    expect($greasing->fresh()->status)->toBe('OPEN')
        ->and(app(ActiveActivityResolver::class)->currentFor($pic->fresh()))->toBeNull();
});

test('Finish an Oil Audit activity does not clear the daily marker', function () {
    $admin = panelAdmin();
    $pic = panelPic('OA PIC');
    $pic->update(['oil_audit_started_at' => now()->subHour()]);

    $this->actingAs($admin)
        ->post(route('today-activity.finish'), finishPayload('OIL_AUDIT', (string) $pic->id, $pic))
        ->assertSessionHas('success');

    expect($pic->fresh()->oil_audit_started_at)->not->toBeNull()   // marker untouched
        ->and($pic->fresh()->hasStartedOilAuditToday())->toBeTrue()
        ->and(app(ActiveActivityResolver::class)->currentFor($pic->fresh()))->toBeNull();
});

test('after finishing the active activity, an earlier same-day one becomes active again', function () {
    $admin = panelAdmin();
    $pic = panelPic('MULTI PIC');
    $pm = panelStartedPm($pic, 'M-9');                                            // 08:00
    ManualActivity::create(['user_id' => $pic->id, 'name' => 'Break', 'started_at' => now()]); // newer -> active

    expect(app(ActiveActivityResolver::class)->currentFor($pic->fresh())?->source)->toBe('MANUAL');

    $manualId = ManualActivity::where('user_id', $pic->id)->value('id');
    $this->actingAs($admin)->post(route('today-activity.finish'), finishPayload('MANUAL', (string) $manualId, $pic))
        ->assertSessionHas('success');

    // PM (older) is the active one again; PM untouched.
    expect(app(ActiveActivityResolver::class)->currentFor($pic->fresh())?->source)->toBe('PM')
        ->and($pm->fresh()->status)->toBe('IN_PROGRESS');
});

test('Finish is idempotent', function () {
    $admin = panelAdmin();
    $pic = panelPic('IDEM PIC');
    $pm = panelStartedPm($pic, 'M-5');

    $this->actingAs($admin)->post(route('today-activity.finish'), finishPayload('PM', (string) $pm->id, $pic))->assertSessionHas('success');
    $this->actingAs($admin)->post(route('today-activity.finish'), finishPayload('PM', (string) $pm->id, $pic))->assertSessionHas('warning');

    expect(ActivityMonitorClosure::count())->toBe(1);
});

test('Finish is ADMIN / KOORDINATOR only and area-scoped', function () {
    $picActor = panelPic('PIC ACTOR');
    $koorBul = User::factory()->create(['role' => User::ROLE_KOORDINATOR_BUL, 'is_active' => true]);
    $wwdPic = panelPic('WWD TARGET', User::ROLE_PIC_WWD);
    $pm = panelStartedPm($wwdPic, 'M-3');

    $this->actingAs($picActor)->post(route('today-activity.finish'), finishPayload('PM', (string) $pm->id, $wwdPic))->assertForbidden();
    $this->actingAs($koorBul)->post(route('today-activity.finish'), finishPayload('PM', (string) $pm->id, $wwdPic))->assertForbidden();

    expect(ActivityMonitorClosure::count())->toBe(0)
        ->and($pm->fresh()->status)->toBe('IN_PROGRESS');
});

test('Finish rejects an activity that is not actually the PICs current-day activity', function () {
    $admin = panelAdmin();
    $pic = panelPic('CLEAN PIC');   // no activities at all

    $this->actingAs($admin)
        ->post(route('today-activity.finish'), finishPayload('PM', '999999', $pic))
        ->assertSessionHas('warning');

    expect(ActivityMonitorClosure::count())->toBe(0);
});

test('a closure from a previous day does not affect today', function () {
    $pic = panelPic('ROLL PIC');
    $pm = panelStartedPm($pic, 'M-11');

    ActivityMonitorClosure::create([
        'source' => 'PM', 'source_key' => (string) $pm->id, 'business_date' => now()->subDay()->toDateString(),
        'pic_user_id' => $pic->id, 'closed_by_user_id' => $pic->id, 'closed_at' => now()->subDay(),
    ]);

    expect(app(ActiveActivityResolver::class)->currentFor($pic->fresh())?->source)->toBe('PM');
});

test('Edit a manual activity updates name / location / start time', function () {
    $admin = panelAdmin();
    $pic = panelPic('EDIT PIC');
    $manual = ManualActivity::create([
        'user_id' => $pic->id, 'name' => 'Old name', 'machine_number' => 'Old loc', 'started_at' => now()->setTime(8, 0),
    ]);

    $this->actingAs($admin)->patch(route('today-activity.manual.update', $manual), [
        'name' => 'New name', 'machine_number' => 'Workshop B',
        'started_at' => now()->setTime(9, 30)->format('Y-m-d\TH:i'),
    ])->assertRedirect()->assertSessionHas('success');

    $manual->refresh();
    expect($manual->name)->toBe('New name')
        ->and($manual->machine_number)->toBe('Workshop B')
        ->and($manual->started_at->format('H:i'))->toBe('09:30');
});

test('non-manual rows expose a deep-link to their module screen', function () {
    $admin = panelAdmin();
    $pic = panelPic('LINK PIC');
    $pm = panelStartedPm($pic, 'M-42');

    $this->actingAs($admin)->get(route('today-activity.index'))
        ->assertOk()
        ->assertSee(route('pm-schedules.edit', $pm->id), false);
});
