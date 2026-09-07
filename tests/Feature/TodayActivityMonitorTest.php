<?php

use App\Models\Machine;
use App\Models\ManualActivity;
use App\Models\PMSchedule;
use App\Models\User;
use Carbon\Carbon;

function monitorPic(string $name): User
{
    return User::factory()->create([
        'name' => $name,
        'role' => User::ROLE_PIC_WWD,
        'is_active' => true,
    ]);
}

function monitorStartedPm(User $pic, string $machineNumber): PMSchedule
{
    $machine = Machine::create([
        'machine_number' => $machineNumber,
        'area' => 'WWD',
        'machine_type' => 'NDE',
        'status' => 'ACTIVE',
    ]);

    $today = now()->toDateString();

    return PMSchedule::create([
        'machine_id' => $machine->id,
        'machine_number' => $machineNumber,
        'machine_type' => 'NDE',
        'area' => 'WWD',
        'order_number' => 'ORD-'.uniqid(),
        'plan_date' => $today,
        'plan_month' => Carbon::parse($today)->format('F'),
        'plan_year' => Carbon::parse($today)->format('Y'),
        'due_date' => Carbon::parse($today)->addDays(14),
        'pic' => $pic->name,
        'status' => 'IN_PROGRESS',
        'start_time' => '08:12',
        'actual_date' => $today,
    ]);
}

test('the monitor is a public page reachable without logging in', function () {
    $this->get(route('monitor'))->assertOk();
});

test('a PIC with an active activity is shown as a card, one without is only listed at the bottom', function () {
    $andi = monitorPic('ANDI');
    $budi = monitorPic('BUDI');
    monitorStartedPm($andi, 'M-1023');

    $res = $this->get(route('monitor'))->assertOk();

    $res->assertSee('ANDI')
        ->assertSee('M-1023')
        ->assertSee('08:12')
        ->assertSee('Not started:')
        ->assertSee('BUDI');
});

test('machine is optional on a card', function () {
    $caca = monitorPic('CACA');
    ManualActivity::create([
        'user_id' => $caca->id,
        'name' => 'Repair Conveyor',
        'machine_number' => null,
        'started_at' => now()->setTime(9, 5),
    ]);

    $this->get(route('monitor'))
        ->assertOk()
        ->assertSee('CACA')
        ->assertSee('09:05');
});

test('a PIC without a photo falls back to initials', function () {
    monitorStartedPm(monitorPic('DEDI SURYA'), 'M-1');

    $this->get(route('monitor'))
        ->assertOk()
        ->assertSee('DS'); // initials of "Dedi Surya"
});

test('when every PIC is active the bottom line says so', function () {
    monitorStartedPm(monitorPic('ANDI'), 'M-1');
    monitorStartedPm(monitorPic('BUDI'), 'M-2');

    $html = $this->get(route('monitor'))->assertOk()->getContent();

    // Check the rendered footer region, not the whole document (the JS
    // auto-refresh renderer legitimately contains the "Not started:" template).
    preg_match('/<p id="monitor-notstarted"[^>]*>(.*?)<\/p>/s', $html, $m);

    expect(trim($m[1]))->toContain('All PIC are active')
        ->and(trim($m[1]))->not->toContain('Not started');
});

test('with no active activity the empty state is shown', function () {
    monitorPic('ANDI');
    monitorPic('BUDI');

    $this->get(route('monitor'))
        ->assertOk()
        ->assertSee('No Active Activity')
        ->assertSee('Not started:');
});

test('ADMIN sees the Auto Refresh toggle', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'is_active' => true]);

    $this->actingAs($admin)
        ->get(route('monitor'))
        ->assertOk()
        ->assertSee('Auto Refresh')
        ->assertSee('auto-refresh-toggle', false);
});

test('non-ADMIN roles and guests do not see the Auto Refresh toggle', function () {
    $this->get(route('monitor'))
        ->assertOk()
        ->assertDontSee('auto-refresh-toggle', false);

    foreach ([User::ROLE_PIC_WWD, User::ROLE_KOORDINATOR_WWD] as $role) {
        $user = User::factory()->create(['role' => $role, 'is_active' => true]);
        $this->actingAs($user)
            ->get(route('monitor'))
            ->assertOk()
            ->assertDontSee('auto-refresh-toggle', false);
    }
});

test('the monitor is not linked from the authenticated sidebar', function () {
    $pic = monitorPic('ANDI');

    $this->actingAs($pic)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee(route('monitor'));
});

test('inactive PIC and non-PIC roles are excluded from the board', function () {
    $inactivePic = User::factory()->create(['name' => 'ZZZ INACTIVE', 'role' => User::ROLE_PIC_WWD, 'is_active' => false]);
    $koor = User::factory()->create(['name' => 'ZZZ KOOR', 'role' => User::ROLE_KOORDINATOR_WWD, 'is_active' => true]);
    monitorStartedPm(monitorPic('ANDI'), 'M-1');

    $this->get(route('monitor'))
        ->assertOk()
        ->assertDontSee('ZZZ INACTIVE')
        ->assertDontSee('ZZZ KOOR');
});

/*
|--------------------------------------------------------------------------
| Auto refresh (Task 09) — default ON, 60s, in-place data poll
|--------------------------------------------------------------------------
*/

test('the page auto-refreshes in place: default ON, 60s, no full reload', function () {
    $html = $this->get(route('monitor'))->assertOk()->getContent();

    expect($html)->toContain('data-poll-interval="60000"')
        ->and($html)->toContain('data-auto-refresh-default="on"')
        ->and($html)->toContain(route('monitor.data'))
        ->and($html)->not->toContain('location.reload');
});

test('the polling interval can never be forced below 60s', function () {
    // The clamp lives in the view: Math.max(60000, ...).
    $html = $this->get(route('monitor'))->assertOk()->getContent();

    expect($html)->toContain('Math.max(60000,');
});

test('the data endpoint is public and returns only the board data', function () {
    $andi = monitorPic('ANDI');
    monitorPic('BUDI');
    monitorStartedPm($andi, 'M-1023');

    $this->getJson(route('monitor.data'))
        ->assertOk()
        ->assertJsonPath('activeCount', 1)
        ->assertJsonPath('totalPics', 2)
        ->assertJsonPath('active.0.name', 'ANDI')
        ->assertJsonPath('active.0.activity', 'PM')
        ->assertJsonPath('active.0.machine', 'M-1023')
        ->assertJsonPath('active.0.startTime', '08:12')
        ->assertJsonPath('notStarted', ['BUDI'])
        ->assertJsonStructure(['active' => [['name', 'photo', 'initials', 'activity', 'machine', 'startTime']], 'notStarted', 'activeCount', 'totalPics']);
});

test('the data endpoint reports machine as null when the activity has none', function () {
    $caca = monitorPic('CACA');
    ManualActivity::create([
        'user_id' => $caca->id,
        'name' => 'Meeting',
        'machine_number' => null,
        'started_at' => now()->setTime(9, 5),
    ]);

    $this->getJson(route('monitor.data'))
        ->assertOk()
        ->assertJsonPath('active.0.name', 'CACA')
        ->assertJsonPath('active.0.machine', null)
        ->assertJsonPath('active.0.startTime', '09:05');
});

test('the data endpoint excludes inactive PIC and non-PIC roles', function () {
    User::factory()->create(['name' => 'ZZZ INACTIVE', 'role' => User::ROLE_PIC_WWD, 'is_active' => false]);
    User::factory()->create(['name' => 'ZZZ KOOR', 'role' => User::ROLE_KOORDINATOR_WWD, 'is_active' => true]);
    monitorStartedPm(monitorPic('ANDI'), 'M-1');

    $json = $this->getJson(route('monitor.data'))->assertOk()->json();

    expect($json['totalPics'])->toBe(1)
        ->and($json['notStarted'])->not->toContain('ZZZ KOOR')
        ->and($json['notStarted'])->not->toContain('ZZZ INACTIVE');
});

test('the data endpoint stays a small number of queries for a full 10-PIC roster', function () {
    for ($i = 1; $i <= 10; $i++) {
        $pic = monitorPic('PIC '.str_pad((string) $i, 2, '0', STR_PAD_LEFT));
        if ($i % 2 === 0) {
            monitorStartedPm($pic, 'M-'.$i);
        }
    }

    DB::enableQueryLog();
    $this->getJson(route('monitor.data'))->assertOk();
    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    // 1 roster query + at most a few small indexed lookups per PIC.
    expect($count)->toBeLessThanOrEqual(45);
});
