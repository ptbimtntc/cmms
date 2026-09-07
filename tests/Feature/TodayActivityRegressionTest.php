<?php

/**
 * TASK 10 — consolidated, date-agnostic regression pass over the whole
 * Today's Activity feature (Tasks 06–09). It does not replace the
 * per-feature suites; it proves the end-to-end paths still line up after
 * every task and covers the scenarios listed in the Task 10 brief.
 */

use App\Models\Greasing;
use App\Models\Group;
use App\Models\Machine;
use App\Models\ManualActivity;
use App\Models\PMSchedule;
use App\Models\User;
use App\Services\ActiveActivityResolver;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

function reg_pic(string $name = 'REG PIC'): User
{
    return User::factory()->create(['role' => User::ROLE_PIC_WWD, 'name' => $name, 'is_active' => true]);
}

function reg_machine(string $number): Machine
{
    return Machine::create([
        'machine_number' => $number,
        'area' => 'WWD',
        'machine_type' => 'NDE',
        'status' => 'ACTIVE',
    ]);
}

function reg_pm(string $pic, string $machineNumber, array $overrides = []): PMSchedule
{
    $machine = reg_machine($machineNumber);
    $today = now()->toDateString();

    return PMSchedule::create(array_merge([
        'machine_id' => $machine->id,
        'machine_number' => $machineNumber,
        'machine_type' => 'NDE',
        'area' => 'WWD',
        'order_number' => 'ORD-'.uniqid(),
        'plan_date' => $today,
        'plan_month' => Carbon::parse($today)->format('F'),
        'plan_year' => Carbon::parse($today)->format('Y'),
        'due_date' => Carbon::parse($today)->addDays(14),
        'pic' => $pic,
        'status' => 'OPEN',
    ], $overrides));
}

function reg_greasing(string $pic, array $overrides = []): Greasing
{
    $group = Group::create(['name' => 'WWD Group '.uniqid()]);

    return Greasing::create(array_merge([
        'group_id' => $group->id,
        'order_number' => 'WO-'.uniqid(),
        'cycle' => '4W',
        'plan_date' => now()->toDateString(),
        'due_date' => Greasing::calculateDueDate(now()->toDateString()),
        'pic' => $pic,
        'status' => 'OPEN',
    ], $overrides));
}

// ---------------------------------------------------------------------------
// 1 + 2 — PM: START uses the existing start_time column, then Fill PM still finishes it
// ---------------------------------------------------------------------------
test('PM START then Fill PM: existing start_time is used and the fill workflow still finishes the PM', function () {
    $pic = reg_pic('PM PERSON');
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $pm = reg_pm('PM PERSON', 'M-1001');

    $this->actingAs($pic)
        ->post(route('pm-schedules.start', $pm), ['started_at' => now()->format('Y-m-d\TH:i')])
        ->assertRedirect()
        ->assertSessionHas('success');

    $pm->refresh();
    expect($pm->start_time)->not->toBeNull()
        ->and(Carbon::parse($pm->actual_date)->toDateString())->toBe(now()->toDateString())
        ->and($pm->status)->toBe('IN_PROGRESS')
        ->and(app(ActiveActivityResolver::class)->currentFor($pic->fresh())->source)->toBe('PM');

    // Fill PM (existing workflow, must still run end-to-end after Start)
    $this->actingAs($admin)->put(route('pm-schedules.update', $pm), [
        'order_number' => $pm->order_number,
        'pic' => 'PM PERSON',
        'actual_date' => now()->toDateString(),
        'start_time' => '08:00',
        'end_time' => '10:00',
        'problems' => [],
    ])->assertSessionHasNoErrors()->assertRedirect();

    $pm->refresh();
    expect($pm->end_time)->not->toBeNull()
        ->and($pm->status)->not->toBe('OPEN');   // moved forward by the fill, not reverted
});

// ---------------------------------------------------------------------------
// 3 + 4 — Greasing: START does not touch status, Action still completes it
// ---------------------------------------------------------------------------
test('Greasing START then Action: start_time set without changing status, execution still completes it', function () {
    $pic = reg_pic('GREASE PERSON');
    $greasing = reg_greasing('GREASE PERSON');

    $this->actingAs($pic)
        ->post(route('greasings.start', $greasing), ['started_at' => now()->format('Y-m-d\TH:i')])
        ->assertSessionHas('success');

    $greasing->refresh();
    expect($greasing->start_time)->not->toBeNull()
        ->and($greasing->status)->toBe('OPEN')
        ->and(app(ActiveActivityResolver::class)->currentFor($pic->fresh())->source)->toBe('GREASING');

    $this->actingAs($pic)->post(route('greasings.execute.store', $greasing), [
        'action_date' => $greasing->due_date->format('Y-m-d'),
        'remarks' => 'done',
        'findings' => ['Finding A'],
    ])->assertRedirect(route('greasings.index'));

    $greasing->refresh();
    expect($greasing->status)->toBeIn(['FINISH', 'FINISH ON TIME'])
        ->and($greasing->findings()->count())->toBe(1)
        ->and($greasing->start_time)->not->toBeNull()          // start time preserved
        ->and($greasing->isActiveActivity())->toBeFalse();
});

// ---------------------------------------------------------------------------
// 5 + 6 — Oil Audit + Oil Audit Action daily START (date-agnostic)
// ---------------------------------------------------------------------------
test('Oil Audit daily START and Oil Audit Action daily START both work today', function () {
    // Separate PICs: the two are distinct activity sources and the
    // one-active-per-PIC rule (Task 06) would otherwise make the second
    // start prompt an END & START confirmation.
    $scanPic = reg_pic('OIL SCAN PERSON');
    $actionPic = reg_pic('OIL ACTION PERSON');

    $this->actingAs($scanPic)
        ->post(route('oil-audits.start-daily'), ['started_at' => now()->format('Y-m-d\TH:i')])
        ->assertRedirect(route('oil-audits.scan'));

    expect($scanPic->fresh()->hasStartedOilAuditToday())->toBeTrue()
        ->and(app(ActiveActivityResolver::class)->currentFor($scanPic->fresh())->source)->toBe('OIL_AUDIT');

    $this->actingAs($actionPic)
        ->post(route('oil-audits.report.start-daily'), ['started_at' => now()->format('Y-m-d\TH:i')])
        ->assertRedirect(route('oil-audits.report'));

    expect($actionPic->fresh()->hasStartedOilAuditActionToday())->toBeTrue()
        ->and(app(ActiveActivityResolver::class)->currentFor($actionPic->fresh())->source)->toBe('OIL_AUDIT_ACTION');
});

test('starting Oil Audit Action while Oil Audit is already active prompts END & START (one active per PIC)', function () {
    $pic = reg_pic('OIL BOTH PERSON');

    $this->actingAs($pic)->post(route('oil-audits.start-daily'), ['started_at' => now()->format('Y-m-d\TH:i')]);

    // second source -> confirmation, action marker NOT set
    $this->actingAs($pic)
        ->post(route('oil-audits.report.start-daily'), ['started_at' => now()->format('Y-m-d\TH:i')])
        ->assertSessionHas('activity_conflict');
    expect($pic->fresh()->hasStartedOilAuditActionToday())->toBeFalse();

    // END & START switches over
    $this->actingAs($pic)
        ->post(route('oil-audits.report.start-daily'), [
            'started_at' => now()->format('Y-m-d\TH:i'),
            'confirm_end_start' => '1',
        ]);
    expect($pic->fresh()->hasStartedOilAuditActionToday())->toBeTrue();
});

// ---------------------------------------------------------------------------
// 7 + 11 — Manual Activity (for a PIC, with a machine) shows up on the monitor
// ---------------------------------------------------------------------------
test('a koordinator starts a Manual Activity for a PIC with a location and it appears on the monitor by its name', function () {
    $koor = User::factory()->create(['role' => User::ROLE_KOORDINATOR_WWD, 'is_active' => true]);
    $pic = reg_pic('MANUAL PIC');

    $this->actingAs($koor)->post(route('today-activity.manual.store'), [
        'user_id' => $pic->id,
        'name' => 'Repair Conveyor',
        'machine_number' => 'M-7788',   // free text
        'started_at' => now()->format('Y-m-d\TH:i'),
    ])->assertSessionHas('success');

    expect(ManualActivity::where('name', 'Repair Conveyor')->first())
        ->machine_number->toBe('M-7788')
        ->user_id->toBe($pic->id);

    $this->getJson(route('monitor.data'))
        ->assertOk()
        ->assertJsonPath('active.0.name', 'MANUAL PIC')
        ->assertJsonPath('active.0.activity', 'Repair Conveyor')   // activity name, not "Manual Activity"
        ->assertJsonPath('active.0.machine', 'M-7788');
});

// ---------------------------------------------------------------------------
// 8 + 9 + 10 — one active per PIC, END & START, close != complete
// ---------------------------------------------------------------------------
test('second activity for the same PIC is blocked with a confirmation, END & START switches without completing the first', function () {
    $pic = reg_pic('BUSY PERSON');
    $pm = reg_pm('BUSY PERSON', 'M-2001', [
        'status' => 'IN_PROGRESS',
        'start_time' => now()->subHour()->format('H:i'),
        'actual_date' => now()->toDateString(),
    ]);
    $greasing = reg_greasing('BUSY PERSON');

    // second start -> confirmation, nothing started
    $this->actingAs($pic)
        ->post(route('greasings.start', $greasing), ['started_at' => now()->format('Y-m-d\TH:i')])
        ->assertSessionHas('activity_conflict');
    expect($greasing->fresh()->start_time)->toBeNull();

    // END & START
    $this->actingAs($pic)
        ->post(route('greasings.start', $greasing), [
            'started_at' => now()->format('Y-m-d\TH:i'),
            'confirm_end_start' => '1',
        ])
        ->assertSessionHas('success');

    $current = app(ActiveActivityResolver::class)->currentFor($pic->fresh());
    expect($current->source)->toBe('GREASING')
        ->and($greasing->fresh()->start_time)->not->toBeNull()
        // closing the PM activity must NOT complete the PM work
        ->and($pm->fresh()->status)->toBe('IN_PROGRESS');
});

// ---------------------------------------------------------------------------
// 9b — day rollover is derived; the underlying work status is untouched
// ---------------------------------------------------------------------------
test('an activity started yesterday is not active today and no work status changes at rollover', function () {
    $pic = reg_pic('YESTERDAY PERSON');
    $pm = reg_pm('YESTERDAY PERSON', 'M-3001', [
        'status' => 'IN_PROGRESS',
        'start_time' => '09:00',
        'actual_date' => now()->subDay()->toDateString(),
    ]);
    $greasing = reg_greasing('YESTERDAY PERSON', ['start_time' => now()->subDay()->setTime(9, 0)]);

    expect(app(ActiveActivityResolver::class)->forToday($pic))->toHaveCount(0);

    // Nothing mutated the rows — rollover is purely derived.
    expect($pm->fresh()->status)->toBe('IN_PROGRESS')
        ->and($pm->fresh()->actual_date)->toBe(now()->subDay()->toDateString())
        ->and($greasing->fresh()->status)->toBe('OPEN');
});

// ---------------------------------------------------------------------------
// 10b — Activity without machine (manual) — monitor copes, machine is null
// ---------------------------------------------------------------------------
test('an activity without a machine renders on the monitor and reports machine null in the data feed', function () {
    $pic = reg_pic('NO MACHINE PERSON');
    ManualActivity::create([
        'user_id' => $pic->id,
        'name' => 'Meeting',
        'machine_number' => null,
        'started_at' => now()->setTime(9, 30),
    ]);

    // Manual activities are owned by their user; make this user a PIC row so
    // the board (which iterates PICs) picks it up.
    $this->getJson(route('monitor.data'))
        ->assertOk()
        ->assertJsonPath('active.0.name', 'NO MACHINE PERSON')
        ->assertJsonPath('active.0.machine', null);
});

// ---------------------------------------------------------------------------
// 11–15 — access matrix
// ---------------------------------------------------------------------------
test('access: guest can view the public monitor, no toggle', function () {
    $this->get(route('monitor'))->assertOk()->assertDontSee('auto-refresh-toggle', false);
});

test('access: ADMIN sees the monitor auto-refresh toggle', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'is_active' => true]);
    $this->actingAs($admin)->get(route('monitor'))->assertOk()->assertSee('auto-refresh-toggle', false);
});

test('access: KOORDINATOR sees + ACTIVITY on Today\'s Activity, PIC does not', function () {
    $koor = User::factory()->create(['role' => User::ROLE_KOORDINATOR_WWD, 'is_active' => true]);
    $pic = reg_pic('PLAIN PIC');

    $this->actingAs($koor)->get(route('today-activity.index'))->assertOk()->assertSee('manual-activity-open', false);
    $this->actingAs($pic)->get(route('today-activity.index'))->assertOk()->assertDontSee('manual-activity-open', false);
});

test('access: a PIC cannot POST a manual activity', function () {
    $pic = reg_pic('NOT ALLOWED');
    $this->actingAs($pic)->post(route('today-activity.manual.store'), [
        'name' => 'x', 'started_at' => now()->format('Y-m-d\TH:i'),
    ])->assertForbidden();
});

// ---------------------------------------------------------------------------
// 16–19 — monitor board rules
// ---------------------------------------------------------------------------
test('monitor: auto-refresh default ON, 60s, no full reload, not-started as small text, no Completed Today', function () {
    reg_pic('AAA');
    $active = reg_pic('BBB');
    ManualActivity::create(['user_id' => $active->id, 'name' => 'Task', 'started_at' => now()]);

    $html = $this->get(route('monitor'))->assertOk()->getContent();

    expect($html)->toContain('data-auto-refresh-default="on"')
        ->and($html)->toContain('data-poll-interval="60000"')
        ->and($html)->not->toContain('location.reload')
        ->and($html)->toContain('Not started:')       // small text line
        ->and($html)->not->toContain('Completed Today');
});

test('monitor: empty state when nobody is active', function () {
    reg_pic('AAA');
    reg_pic('BBB');

    $this->get(route('monitor'))->assertOk()->assertSee('No Active Activity');
});

// ---------------------------------------------------------------------------
// PART 4 — newest-start-wins across three sources on the same day
// ---------------------------------------------------------------------------
test('with PM 08:00, Greasing 10:00 and Oil Audit 13:00 the same PIC only Oil Audit is active', function () {
    $pic = reg_pic('THREE PERSON');

    reg_pm('THREE PERSON', 'M-9001', [
        'status' => 'IN_PROGRESS',
        'start_time' => '08:00',
        'actual_date' => now()->toDateString(),
    ]);
    reg_greasing('THREE PERSON', ['start_time' => now()->setTime(10, 0)]);
    $pic->update(['oil_audit_started_at' => now()->setTime(13, 0)]);

    $resolver = app(ActiveActivityResolver::class);
    $today = $resolver->forToday($pic->fresh());

    expect($today)->toHaveCount(3)                       // all three are candidates...
        ->and($resolver->currentFor($pic->fresh())->source)->toBe('OIL_AUDIT'); // ...only the newest is active
});

// ---------------------------------------------------------------------------
// PART 2 / PART 12 — Greasing is group-level: card shows the group, not a machine
// ---------------------------------------------------------------------------
test('a started Greasing shows its group on the monitor and reports machine null', function () {
    $pic = reg_pic('GROUP PERSON');
    $group = Group::create(['name' => 'WWD LINE 4']);
    Greasing::create([
        'group_id' => $group->id,
        'order_number' => 'WO-'.uniqid(),
        'cycle' => '4W',
        'plan_date' => now()->toDateString(),
        'due_date' => Greasing::calculateDueDate(now()->toDateString()),
        'pic' => 'GROUP PERSON',
        'status' => 'OPEN',
        'start_time' => now()->setTime(8, 0),
    ]);

    $this->getJson(route('monitor.data'))
        ->assertOk()
        ->assertJsonPath('active.0.name', 'GROUP PERSON')
        ->assertJsonPath('active.0.activity', 'Greasing')
        ->assertJsonPath('active.0.machine', null)
        ->assertJsonPath('active.0.location', 'Group: WWD LINE 4');

    $this->get(route('monitor'))->assertOk()->assertSee('Group: WWD LINE 4');
});

// ---------------------------------------------------------------------------
// PART 6 / PART 17 — cross-source conflict: a PIC's active Oil Audit vs a
// Manual Activity started FOR that PIC. END & START must not complete work.
// ---------------------------------------------------------------------------
test('creating a Manual Activity for a PIC who has an active Oil Audit triggers the conflict flow', function () {
    $koor = User::factory()->create(['role' => User::ROLE_KOORDINATOR_WWD, 'is_active' => true]);
    $pic = reg_pic('OA PIC');
    $pic->update(['oil_audit_started_at' => now()->subHour()]);

    // CANCEL path: no confirm flag -> conflict, nothing created
    $this->actingAs($koor)->post(route('today-activity.manual.store'), [
        'user_id' => $pic->id, 'name' => 'Site walk', 'started_at' => now()->format('Y-m-d\TH:i'),
    ])->assertSessionHas('activity_conflict');
    expect(ManualActivity::where('user_id', $pic->id)->exists())->toBeFalse();

    // END & START path — manual becomes active; the Oil Audit marker (work) is untouched
    $this->actingAs($koor)->post(route('today-activity.manual.store'), [
        'user_id' => $pic->id, 'name' => 'Site walk', 'started_at' => now()->format('Y-m-d\TH:i'), 'confirm_end_start' => '1',
    ])->assertSessionHas('success');

    expect(app(ActiveActivityResolver::class)->currentFor($pic->fresh())->source)->toBe('MANUAL')
        ->and($pic->fresh()->oil_audit_started_at)->not->toBeNull();   // daily marker not cleared
});

// ---------------------------------------------------------------------------
// PART 8 — avatar fallback: a broken avatar_path must not break the page
// ---------------------------------------------------------------------------
test('a PIC whose avatar file is missing still renders (initials, name, no exception)', function () {
    $pic = User::factory()->create([
        'name' => 'GHOST AVATAR', 'role' => User::ROLE_PIC_WWD, 'is_active' => true,
        'avatar_path' => 'avatars/does-not-exist.jpg',
    ]);
    ManualActivity::create(['user_id' => $pic->id, 'name' => 'Task', 'started_at' => now()]);

    $this->get(route('monitor'))->assertOk()->assertSee('GHOST AVATAR')->assertSee('GA');
    $this->getJson(route('monitor.data'))->assertOk()->assertJsonPath('active.0.photo', null);
});

// ---------------------------------------------------------------------------
// PART 5 — the auto-close console command no longer exists
// ---------------------------------------------------------------------------
test('there is no activities:auto-close command any more (rollover is derived)', function () {
    expect(array_key_exists('activities:auto-close', Artisan::all()))->toBeFalse();
});

// ---------------------------------------------------------------------------
// 23 + 24 — schema guarantees
// ---------------------------------------------------------------------------
test('schema: no maintenance_activities table, manual_activities exists, start_time not duplicated', function () {
    expect(Schema::hasTable('maintenance_activities'))->toBeFalse()
        ->and(Schema::hasTable('manual_activities'))->toBeTrue();

    // PM reuses its existing column; nothing added a parallel one.
    expect(Schema::hasColumn('pm_schedules', 'start_time'))->toBeTrue()
        ->and(Schema::hasColumn('pm_schedules', 'started_at'))->toBeFalse();

    // Greasing has exactly one start column (added once, in Task pre-06).
    expect(Schema::hasColumn('greasings', 'start_time'))->toBeTrue()
        ->and(Schema::hasColumn('greasings', 'started_at'))->toBeFalse();

    // Manual activity table uses its own started_at, not a second "start_time".
    expect(Schema::hasColumn('manual_activities', 'started_at'))->toBeTrue()
        ->and(Schema::hasColumn('manual_activities', 'start_time'))->toBeFalse();
});
