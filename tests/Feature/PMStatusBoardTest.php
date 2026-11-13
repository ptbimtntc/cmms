<?php

use App\Models\Machine;
use App\Models\PMSchedule;
use Carbon\Carbon;

function pmStatusBoardMachine(array $overrides = []): Machine
{
    return Machine::create(array_merge([
        'machine_number' => 'MC-'.uniqid(),
        'area' => 'WWD',
        'machine_type' => 'NDE',
        'status' => 'ACTIVE',
    ], $overrides));
}

function pmStatusBoardSchedule(Machine $machine, array $overrides = []): PMSchedule
{
    $planDate = $overrides['plan_date'] ?? now();

    return PMSchedule::create(array_merge([
        'machine_id' => $machine->id,
        'machine_number' => $machine->machine_number,
        'machine_type' => $machine->machine_type,
        'area' => $machine->area,
        'order_number' => 'WO-'.uniqid(),
        'plan_date' => $planDate,
        'plan_month' => Carbon::parse($planDate)->format('F'),
        'plan_year' => Carbon::parse($planDate)->year,
        'due_date' => now(),
        'status' => 'OPEN',
    ], $overrides));
}

/**
 * Shorthand for the area-scoped board URL, e.g. pmStatusUrl('wwd').
 */
function pmStatusUrl(string $area, array $params = []): string
{
    return route('pm-status.show', array_merge(['area' => $area], $params));
}

// ---------------------------------------------------------------
// Guest access
// ---------------------------------------------------------------

test('guest can open the wwd pm status board without login', function () {
    $response = $this->get(pmStatusUrl('wwd'));

    $response->assertOk();
});

test('guest can open the bul pm status board without login', function () {
    $response = $this->get(pmStatusUrl('bul'));

    $response->assertOk();
});

test('guest sees no edit/action controls on the board', function () {
    $machine = pmStatusBoardMachine();
    pmStatusBoardSchedule($machine, ['plan_date' => now()]);

    $response = $this->get(pmStatusUrl('wwd'));

    $response->assertOk();
    $response->assertDontSee('Start PM');
    $response->assertDontSee('Save PM');
    $response->assertDontSee('Shift');
    $response->assertDontSee('Delete');
});

test('board hides authenticated sidebar/topbar for guests', function () {
    $response = $this->get(pmStatusUrl('wwd'));

    $response->assertOk();
    expect($response->viewData('hideSidebar'))->toBeTrue();
    expect($response->viewData('hideTopbar'))->toBeTrue();
});

// ---------------------------------------------------------------
// Area routing — area is a URL segment, not a filter
// ---------------------------------------------------------------

test('an invalid area segment returns 404, not an all-area fallback', function () {
    $response = $this->get('/pm-status/abc');

    $response->assertNotFound();
});

test('wwd page header shows wwd, not all area', function () {
    $response = $this->get(pmStatusUrl('wwd'));

    $response->assertOk();
    $response->assertSee('PM Status: WWD', false);
    $response->assertDontSee('All Area');
});

test('bul page header shows bul, not all area', function () {
    $response = $this->get(pmStatusUrl('bul'));

    $response->assertOk();
    $response->assertSee('PM Status: BUL', false);
    $response->assertDontSee('All Area');
});

test('the area filter dropdown from task 1 no longer exists', function () {
    $response = $this->get(pmStatusUrl('wwd'));

    $response->assertOk();
    $response->assertDontSee('name="area"', false);
});

test('the area switcher links to both area urls with the active area marked', function () {
    $response = $this->get(pmStatusUrl('wwd'));

    $response->assertOk();
    $response->assertSee(pmStatusUrl('wwd'), false);
    $response->assertSee(pmStatusUrl('bul'), false);

    // The active tab carries the highlighted classes; the inactive one does not.
    $html = $response->getContent();
    $wwdHref = e(pmStatusUrl('wwd'));
    $bulHref = e(pmStatusUrl('bul'));
    $wwdAnchorStart = strpos($html, 'href="'.$wwdHref.'"');
    $bulAnchorStart = strpos($html, 'href="'.$bulHref.'"');

    expect($wwdAnchorStart)->not->toBeFalse();
    expect($bulAnchorStart)->not->toBeFalse();
    expect(substr($html, $wwdAnchorStart, 200))->toContain('bg-blue-600');
    expect(substr($html, $bulAnchorStart, 200))->not->toContain('bg-blue-600');
});

// ---------------------------------------------------------------
// Data isolation — the core of Task 2
// ---------------------------------------------------------------

test('wwd board only shows wwd pm schedules, never bul', function () {
    $wwd = pmStatusBoardMachine(['machine_number' => 'MC-WWD-ONLY', 'area' => 'WWD']);
    $bul = pmStatusBoardMachine(['machine_number' => 'MC-BUL-ONLY', 'area' => 'BUL']);
    pmStatusBoardSchedule($wwd, ['plan_date' => now()]);
    pmStatusBoardSchedule($bul, ['plan_date' => now()]);

    $response = $this->get(pmStatusUrl('wwd'));

    $numbers = collect($response->viewData('schedules'))->pluck('machine_number')->all();
    expect($numbers)->toBe(['MC-WWD-ONLY']);
    $response->assertDontSee('MC-BUL-ONLY');
});

test('bul board only shows bul pm schedules, never wwd', function () {
    $wwd = pmStatusBoardMachine(['machine_number' => 'MC-WWD-ONLY-2', 'area' => 'WWD']);
    $bul = pmStatusBoardMachine(['machine_number' => 'MC-BUL-ONLY-2', 'area' => 'BUL']);
    pmStatusBoardSchedule($wwd, ['plan_date' => now()]);
    pmStatusBoardSchedule($bul, ['plan_date' => now()]);

    $response = $this->get(pmStatusUrl('bul'));

    $numbers = collect($response->viewData('schedules'))->pluck('machine_number')->all();
    expect($numbers)->toBe(['MC-BUL-ONLY-2']);
    $response->assertDontSee('MC-WWD-ONLY-2');
});

test('area filtering happens server-side in the query, not by fetching everything', function () {
    $wwd = pmStatusBoardMachine(['area' => 'WWD']);
    $bul = pmStatusBoardMachine(['area' => 'BUL']);
    pmStatusBoardSchedule($wwd, ['plan_date' => now()]);
    pmStatusBoardSchedule($bul, ['plan_date' => now()]);

    $response = $this->get(pmStatusUrl('wwd'));

    // The schedules collection handed to the view is already area-scoped —
    // there is no second (unscoped) collection anywhere for the view to
    // accidentally render from.
    expect(collect($response->viewData('schedules')))->toHaveCount(1);
});

// ---------------------------------------------------------------
// Status OPEN/CLOSED — uses existing status source of truth
// ---------------------------------------------------------------

test('finished and finished_on_time schedules are not listed at all', function () {
    $machine = pmStatusBoardMachine(['machine_number' => 'MC-CLOSED']);
    pmStatusBoardSchedule($machine, ['plan_date' => now(), 'status' => 'FINISHED', 'actual_date' => now()]);
    pmStatusBoardSchedule($machine, ['plan_date' => now(), 'status' => 'FINISHED_ON_TIME', 'actual_date' => now(), 'order_number' => 'WO-OT']);
    pmStatusBoardSchedule($machine, ['plan_date' => now()->subMonths(2), 'status' => 'FINISHED', 'order_number' => 'WO-OLD']);

    $response = $this->get(pmStatusUrl('wwd'));

    expect($response->viewData('schedules'))->toHaveCount(0);
    $response->assertDontSee('MC-CLOSED');
});

test('an open schedule with no actual pm displays as open', function () {
    $machine = pmStatusBoardMachine(['machine_number' => 'MC-OPEN']);
    pmStatusBoardSchedule($machine, [
        'plan_date' => now(),
        'status' => 'OPEN',
    ]);

    $response = $this->get(pmStatusUrl('wwd'));

    $row = collect($response->viewData('schedules'))->firstWhere('machine_number', 'MC-OPEN');
    expect($row['status'])->toBe('OPEN');
});

test('a missed schedule displays as open, not a new status', function () {
    $machine = pmStatusBoardMachine(['machine_number' => 'MC-MISSED']);
    pmStatusBoardSchedule($machine, [
        'plan_date' => now(),
        'status' => 'MISSED',
    ]);

    $response = $this->get(pmStatusUrl('wwd'));

    $row = collect($response->viewData('schedules'))->firstWhere('machine_number', 'MC-MISSED');
    expect($row['status'])->toBe('OPEN');
});

test('board never renders a status other than open', function () {
    $machine = pmStatusBoardMachine();
    pmStatusBoardSchedule($machine, ['plan_date' => now(), 'status' => 'IN_PROGRESS']);
    pmStatusBoardSchedule($machine, ['plan_date' => now(), 'status' => 'OPEN', 'order_number' => 'WO-2']);
    pmStatusBoardSchedule($machine, ['plan_date' => now(), 'status' => 'FINISHED', 'order_number' => 'WO-3']);

    $response = $this->get(pmStatusUrl('wwd'));

    $statuses = collect($response->viewData('schedules'))->pluck('status')->unique()->all();
    expect($statuses)->each->toBe('OPEN');
});

// ---------------------------------------------------------------
// GAP DAY — Today - Plan Date, boundary at +/-14, independent of status
// ---------------------------------------------------------------

test('gap day boundary cases around today = 22 sep 2026', function () {
    Carbon::setTestNow(Carbon::parse('2026-09-22', 'Asia/Jakarta'));

    $cases = [
        ['plan' => '2026-09-07', 'expected' => 15, 'red' => true],
        ['plan' => '2026-09-08', 'expected' => 14, 'red' => false],
        ['plan' => '2026-09-09', 'expected' => 13, 'red' => false],
        ['plan' => '2026-09-22', 'expected' => 0, 'red' => false],
    ];

    foreach ($cases as $i => $case) {
        $machine = pmStatusBoardMachine(['machine_number' => 'MC-GAP-'.$i]);
        pmStatusBoardSchedule($machine, ['plan_date' => $case['plan']]);
    }

    $rows = collect($this->get(pmStatusUrl('wwd'))->viewData('schedules'));

    foreach ($cases as $i => $case) {
        $row = $rows->firstWhere('machine_number', 'MC-GAP-'.$i);
        expect($row)->not->toBeNull("row for plan {$case['plan']} not found");
        expect($row['gap_day'])->toBe($case['expected']);
        expect($row['is_gap_day_out_of_range'])->toBe($case['red']);
    }

    Carbon::setTestNow();
});

test('gap day boundary on the future side is -14 normal and -15 red', function () {
    Carbon::setTestNow(Carbon::parse('2026-09-22', 'Asia/Jakarta'));

    $machine = pmStatusBoardMachine(['machine_number' => 'MC-GAP-F14']);
    pmStatusBoardSchedule($machine, ['plan_date' => '2026-10-06']);
    $machine = pmStatusBoardMachine(['machine_number' => 'MC-GAP-F15']);
    pmStatusBoardSchedule($machine, ['plan_date' => '2026-10-07']);

    $html = $this->getJson(route('pm-status.month', ['area' => 'wwd', 'offset' => 1]))->json('desktop');

    $row14 = substr($html, strpos($html, 'MC-GAP-F14'));
    $row14 = substr($row14, 0, strpos($row14, '</tr>'));
    $row15 = substr($html, strpos($html, 'MC-GAP-F15'));
    $row15 = substr($row15, 0, strpos($row15, '</tr>'));

    expect($row14)->toContain('-14')->toContain('bg-slate-100');
    expect($row15)->toContain('-15')->toContain('bg-red-100');

    Carbon::setTestNow();
});

test('gap day uses calendar date, not time-of-day — same day at 23:59 matches 00:01', function () {
    $machine = pmStatusBoardMachine(['machine_number' => 'MC-GAP-EOD']);
    pmStatusBoardSchedule($machine, ['plan_date' => '2026-09-07']);

    Carbon::setTestNow(Carbon::parse('2026-09-22 23:59:00', 'Asia/Jakarta'));
    $lateNight = $this->get(pmStatusUrl('wwd'));

    Carbon::setTestNow(Carbon::parse('2026-09-22 00:01:00', 'Asia/Jakarta'));
    $earlyMorning = $this->get(pmStatusUrl('wwd'));

    $lateRow = collect($lateNight->viewData('schedules'))->firstWhere('machine_number', 'MC-GAP-EOD');
    $earlyRow = collect($earlyMorning->viewData('schedules'))->firstWhere('machine_number', 'MC-GAP-EOD');

    expect($lateRow['gap_day'])->toBe(15);
    expect($earlyRow['gap_day'])->toBe(15);

    Carbon::setTestNow();
});

test('gap day does not affect open status — red and normal gap days both render', function () {
    Carbon::setTestNow(Carbon::parse('2026-09-22', 'Asia/Jakarta'));

    $machine = pmStatusBoardMachine();

    pmStatusBoardSchedule($machine, [
        'plan_date' => '2026-09-01',
        'status' => 'OPEN',
        'order_number' => 'WO-C',
        'machine_number' => 'MC-OPEN-RED',
    ]);
    pmStatusBoardSchedule($machine, [
        'plan_date' => '2026-09-20',
        'status' => 'OPEN',
        'order_number' => 'WO-D',
        'machine_number' => 'MC-OPEN-NORMAL',
    ]);

    $rows = collect($this->get(pmStatusUrl('wwd'))->viewData('schedules'));

    $openRed = $rows->firstWhere('machine_number', 'MC-OPEN-RED');
    expect($openRed['status'])->toBe('OPEN');
    expect($openRed['is_gap_day_out_of_range'])->toBeTrue();

    $openNormal = $rows->firstWhere('machine_number', 'MC-OPEN-NORMAL');
    expect($openNormal['status'])->toBe('OPEN');
    expect($openNormal['is_gap_day_out_of_range'])->toBeFalse();

    Carbon::setTestNow();
});

// ---------------------------------------------------------------
// Open-only board: current month + older still-open, no period filter
// ---------------------------------------------------------------

test('the period filter form is gone', function () {
    $response = $this->get(pmStatusUrl('wwd'));

    $response->assertOk();
    $response->assertDontSee('name="plan_month"', false);
    $response->assertDontSee('name="plan_year"', false);
});

test('an open machine from an older month stays on the board with its month label', function () {
    Carbon::setTestNow(Carbon::parse('2026-09-22', 'Asia/Jakarta'));

    $machine = pmStatusBoardMachine();
    pmStatusBoardSchedule($machine, ['machine_number' => 'MC-OLD', 'plan_date' => '2026-06-10', 'order_number' => 'WO-OLD']);
    pmStatusBoardSchedule($machine, ['machine_number' => 'MC-NOW', 'plan_date' => '2026-09-15', 'order_number' => 'WO-NOW']);

    $rows = collect($this->get(pmStatusUrl('wwd'))->viewData('schedules'));

    expect($rows->pluck('machine_number')->all())->toBe(['MC-OLD', 'MC-NOW']);
    expect($rows->firstWhere('machine_number', 'MC-OLD')['month_label'])->toContain('2026');
    expect($rows->firstWhere('machine_number', 'MC-OLD')['month_label'])->not->toBe($rows->firstWhere('machine_number', 'MC-NOW')['month_label']);

    Carbon::setTestNow();
});

test('a machine from an older month disappears once it is closed', function () {
    Carbon::setTestNow(Carbon::parse('2026-09-22', 'Asia/Jakarta'));

    $machine = pmStatusBoardMachine();
    pmStatusBoardSchedule($machine, ['machine_number' => 'MC-OLD-DONE', 'plan_date' => '2026-06-10', 'status' => 'FINISHED']);

    $rows = collect($this->get(pmStatusUrl('wwd'))->viewData('schedules'));

    expect($rows)->toHaveCount(0);

    Carbon::setTestNow();
});

test('future months are not on the initial board', function () {
    Carbon::setTestNow(Carbon::parse('2026-09-22', 'Asia/Jakarta'));

    $machine = pmStatusBoardMachine();
    pmStatusBoardSchedule($machine, ['machine_number' => 'MC-NEXT', 'plan_date' => '2026-10-02']);

    $rows = collect($this->get(pmStatusUrl('wwd'))->viewData('schedules'));

    expect($rows)->toHaveCount(0);

    Carbon::setTestNow();
});

test('the board orders by plan date then machine number', function () {
    Carbon::setTestNow(Carbon::parse('2026-09-22', 'Asia/Jakarta'));

    $machine = pmStatusBoardMachine();
    pmStatusBoardSchedule($machine, ['machine_number' => 'MC-B', 'plan_date' => '2026-09-10', 'order_number' => 'WO-1']);
    pmStatusBoardSchedule($machine, ['machine_number' => 'MC-A', 'plan_date' => '2026-09-10', 'order_number' => 'WO-2']);
    pmStatusBoardSchedule($machine, ['machine_number' => 'MC-Z', 'plan_date' => '2026-07-01', 'order_number' => 'WO-3']);

    $rows = collect($this->get(pmStatusUrl('wwd'))->viewData('schedules'));

    expect($rows->pluck('machine_number')->all())->toBe(['MC-Z', 'MC-A', 'MC-B']);

    Carbon::setTestNow();
});

test('summary totals follow the rows shown and the current area', function () {
    Carbon::setTestNow(Carbon::parse('2026-09-22', 'Asia/Jakarta'));

    $wwd = pmStatusBoardMachine(['area' => 'WWD']);
    $bul = pmStatusBoardMachine(['area' => 'BUL']);
    pmStatusBoardSchedule($wwd, ['plan_date' => '2026-09-05', 'status' => 'OPEN', 'order_number' => 'WO-S1']);
    pmStatusBoardSchedule($wwd, ['plan_date' => '2026-07-06', 'status' => 'MISSED', 'order_number' => 'WO-S2']);
    pmStatusBoardSchedule($wwd, ['plan_date' => '2026-09-07', 'status' => 'FINISHED_ON_TIME', 'actual_date' => '2026-09-07', 'order_number' => 'WO-S3']);
    pmStatusBoardSchedule($bul, ['plan_date' => '2026-09-05', 'status' => 'OPEN', 'order_number' => 'WO-S4']);

    $summary = $this->get(pmStatusUrl('wwd'))->viewData('summary');

    expect($summary['total'])->toBe(2);
    expect($summary['open'])->toBe(2);
    expect($summary['closed'])->toBe(0);

    Carbon::setTestNow();
});

test('the board shows a load-next-month button pointing at the month endpoint', function () {
    Carbon::setTestNow(Carbon::parse('2026-09-22', 'Asia/Jakarta'));

    $response = $this->get(pmStatusUrl('wwd'));

    $response->assertOk();
    $response->assertSee('data-pm-month-next', false);
    $response->assertSee(route('pm-status.month', 'wwd'), false);
    $response->assertSee('October 2026');

    Carbon::setTestNow();
});

// ---------------------------------------------------------------
// Month endpoint — one future month per call, up to 2 months ahead
// ---------------------------------------------------------------

test('month endpoint returns only the open machines of the requested future month, for guests', function () {
    Carbon::setTestNow(Carbon::parse('2026-09-22', 'Asia/Jakarta'));

    $machine = pmStatusBoardMachine();
    pmStatusBoardSchedule($machine, ['machine_number' => 'MC-OCT-OPEN', 'plan_date' => '2026-10-10', 'order_number' => 'WO-1']);
    pmStatusBoardSchedule($machine, ['machine_number' => 'MC-OCT-DONE', 'plan_date' => '2026-10-11', 'status' => 'FINISHED', 'order_number' => 'WO-2']);
    pmStatusBoardSchedule($machine, ['machine_number' => 'MC-NOV-OPEN', 'plan_date' => '2026-11-10', 'order_number' => 'WO-3']);

    $json = $this->getJson(route('pm-status.month', ['area' => 'wwd', 'offset' => 1]))->assertOk()->json();

    expect($json['desktop'])->toContain('MC-OCT-OPEN')->not->toContain('MC-OCT-DONE')->not->toContain('MC-NOV-OPEN');
    expect($json['mobile'])->toContain('MC-OCT-OPEN');
    expect($json['has_more'])->toBeTrue();
    expect($json['next_offset'])->toBe(2);

    Carbon::setTestNow();
});

test('month endpoint offset 2 is the last one', function () {
    Carbon::setTestNow(Carbon::parse('2026-09-22', 'Asia/Jakarta'));

    $machine = pmStatusBoardMachine();
    pmStatusBoardSchedule($machine, ['machine_number' => 'MC-NOV-OPEN', 'plan_date' => '2026-11-10']);

    $json = $this->getJson(route('pm-status.month', ['area' => 'wwd', 'offset' => 2]))->assertOk()->json();

    expect($json['desktop'])->toContain('MC-NOV-OPEN');
    expect($json['has_more'])->toBeFalse();
    expect($json['next_offset'])->toBeNull();

    Carbon::setTestNow();
});

test('month endpoint rejects offsets outside 1-2', function () {
    foreach ([0, 3, -1, 'abc'] as $offset) {
        $this->getJson(route('pm-status.month', ['area' => 'wwd', 'offset' => $offset]))->assertStatus(422);
    }

    $this->getJson(route('pm-status.month', ['area' => 'wwd']))->assertStatus(422);
});

test('month endpoint is scoped to the area and 404s on an unknown one', function () {
    Carbon::setTestNow(Carbon::parse('2026-09-22', 'Asia/Jakarta'));

    $bul = pmStatusBoardMachine(['machine_number' => 'MC-BUL-OCT', 'area' => 'BUL']);
    pmStatusBoardSchedule($bul, ['plan_date' => '2026-10-10']);

    $json = $this->getJson(route('pm-status.month', ['area' => 'wwd', 'offset' => 1]))->json();
    expect($json['desktop'])->not->toContain('MC-BUL-OCT');

    $this->getJson('/pm-status/abc/bulan?offset=1')->assertNotFound();

    Carbon::setTestNow();
});
