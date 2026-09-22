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
 * Shorthand for the area-scoped board URL, e.g. pmStatusUrl('wwd', ['plan_month' => 9]).
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
    $response->assertSee('PM Status — WWD', false);
    $response->assertDontSee('All Area');
});

test('bul page header shows bul, not all area', function () {
    $response = $this->get(pmStatusUrl('bul'));

    $response->assertOk();
    $response->assertSee('PM Status — BUL', false);
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

test('a finished schedule displays as closed', function () {
    $machine = pmStatusBoardMachine(['machine_number' => 'MC-CLOSED']);
    pmStatusBoardSchedule($machine, [
        'plan_date' => now(),
        'status' => 'FINISHED',
        'actual_date' => now(),
    ]);

    $response = $this->get(pmStatusUrl('wwd'));

    $row = collect($response->viewData('schedules'))->firstWhere('machine_number', 'MC-CLOSED');
    expect($row['status'])->toBe('CLOSED');
});

test('a finished_on_time schedule displays as closed', function () {
    $machine = pmStatusBoardMachine(['machine_number' => 'MC-CLOSED-OT']);
    pmStatusBoardSchedule($machine, [
        'plan_date' => now(),
        'status' => 'FINISHED_ON_TIME',
        'actual_date' => now(),
    ]);

    $response = $this->get(pmStatusUrl('wwd'));

    $row = collect($response->viewData('schedules'))->firstWhere('machine_number', 'MC-CLOSED-OT');
    expect($row['status'])->toBe('CLOSED');
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

test('board never renders a status other than open or closed', function () {
    $machine = pmStatusBoardMachine();
    pmStatusBoardSchedule($machine, ['plan_date' => now(), 'status' => 'IN_PROGRESS']);
    pmStatusBoardSchedule($machine, ['plan_date' => now(), 'status' => 'OPEN', 'order_number' => 'WO-2']);
    pmStatusBoardSchedule($machine, ['plan_date' => now(), 'status' => 'FINISHED', 'order_number' => 'WO-3']);

    $response = $this->get(pmStatusUrl('wwd'));

    $statuses = collect($response->viewData('schedules'))->pluck('status')->unique()->all();
    expect($statuses)->each->toBeIn(['OPEN', 'CLOSED']);
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
        ['plan' => '2026-10-06', 'expected' => -14, 'red' => false],
        ['plan' => '2026-10-07', 'expected' => -15, 'red' => true],
    ];

    foreach ($cases as $i => $case) {
        $machine = pmStatusBoardMachine(['machine_number' => 'MC-GAP-'.$i]);
        pmStatusBoardSchedule($machine, ['plan_date' => $case['plan']]);
    }

    $response = $this->get(pmStatusUrl('wwd', ['plan_month' => 9, 'plan_year' => 2026]));
    // October dates fall outside the September filter, so also check without a month filter.
    $responseAll = $this->get(pmStatusUrl('wwd', ['plan_month' => 10, 'plan_year' => 2026]));

    $allRows = collect($response->viewData('schedules'))->concat($responseAll->viewData('schedules'));

    foreach ($cases as $i => $case) {
        $row = $allRows->firstWhere('machine_number', 'MC-GAP-'.$i);
        expect($row)->not->toBeNull("row for plan {$case['plan']} not found");
        expect($row['gap_day'])->toBe($case['expected']);
        expect($row['is_gap_day_out_of_range'])->toBe($case['red']);
    }

    Carbon::setTestNow();
});

test('gap day uses calendar date, not time-of-day — same day at 23:59 matches 00:01', function () {
    $machine = pmStatusBoardMachine(['machine_number' => 'MC-GAP-EOD']);
    pmStatusBoardSchedule($machine, ['plan_date' => '2026-09-07']);

    Carbon::setTestNow(Carbon::parse('2026-09-22 23:59:00', 'Asia/Jakarta'));
    $lateNight = $this->get(pmStatusUrl('wwd', ['plan_month' => 9, 'plan_year' => 2026]));

    Carbon::setTestNow(Carbon::parse('2026-09-22 00:01:00', 'Asia/Jakarta'));
    $earlyMorning = $this->get(pmStatusUrl('wwd', ['plan_month' => 9, 'plan_year' => 2026]));

    $lateRow = collect($lateNight->viewData('schedules'))->firstWhere('machine_number', 'MC-GAP-EOD');
    $earlyRow = collect($earlyMorning->viewData('schedules'))->firstWhere('machine_number', 'MC-GAP-EOD');

    expect($lateRow['gap_day'])->toBe(15);
    expect($earlyRow['gap_day'])->toBe(15);

    Carbon::setTestNow();
});

test('gap day does not affect open/closed status — all four combinations render correctly', function () {
    Carbon::setTestNow(Carbon::parse('2026-09-22', 'Asia/Jakarta'));

    $machine = pmStatusBoardMachine();

    // CLOSED + red gap day
    pmStatusBoardSchedule($machine, [
        'machine_number' => 'MC-CLOSED-RED',
        'plan_date' => '2026-09-07',
        'status' => 'FINISHED',
        'actual_date' => '2026-09-08',
        'order_number' => 'WO-A',
    ]);

    // CLOSED + normal gap day
    pmStatusBoardSchedule($machine, [
        'machine_number' => 'MC-CLOSED-NORMAL',
        'plan_date' => '2026-09-20',
        'status' => 'FINISHED',
        'actual_date' => '2026-09-20',
        'order_number' => 'WO-B',
    ]);

    // OPEN + red gap day
    pmStatusBoardSchedule($machine, [
        'machine_number' => 'MC-OPEN-RED',
        'plan_date' => '2026-09-01',
        'status' => 'OPEN',
        'order_number' => 'WO-C',
    ]);

    // OPEN + normal gap day
    pmStatusBoardSchedule($machine, [
        'machine_number' => 'MC-OPEN-NORMAL',
        'plan_date' => '2026-09-20',
        'status' => 'OPEN',
        'order_number' => 'WO-D',
    ]);

    $response = $this->get(pmStatusUrl('wwd', ['plan_month' => 9, 'plan_year' => 2026]));
    $rows = collect($response->viewData('schedules'));

    $closedRed = $rows->firstWhere('machine_number', 'MC-CLOSED-RED');
    expect($closedRed['status'])->toBe('CLOSED');
    expect($closedRed['is_gap_day_out_of_range'])->toBeTrue();

    $closedNormal = $rows->firstWhere('machine_number', 'MC-CLOSED-NORMAL');
    expect($closedNormal['status'])->toBe('CLOSED');
    expect($closedNormal['is_gap_day_out_of_range'])->toBeFalse();

    $openRed = $rows->firstWhere('machine_number', 'MC-OPEN-RED');
    expect($openRed['status'])->toBe('OPEN');
    expect($openRed['is_gap_day_out_of_range'])->toBeTrue();

    $openNormal = $rows->firstWhere('machine_number', 'MC-OPEN-NORMAL');
    expect($openNormal['status'])->toBe('OPEN');
    expect($openNormal['is_gap_day_out_of_range'])->toBeFalse();

    Carbon::setTestNow();
});

// ---------------------------------------------------------------
// Period filter — retained from Task 1, now scoped within the area
// ---------------------------------------------------------------

test('period filter narrows the board to the selected month/year within the area', function () {
    $machine = pmStatusBoardMachine();
    pmStatusBoardSchedule($machine, ['machine_number' => 'MC-SEP', 'plan_date' => '2026-09-15', 'order_number' => 'WO-SEP']);
    pmStatusBoardSchedule($machine, ['machine_number' => 'MC-OCT', 'plan_date' => '2026-10-15', 'order_number' => 'WO-OCT']);

    $response = $this->get(pmStatusUrl('wwd', ['plan_month' => 9, 'plan_year' => 2026]));

    $numbers = collect($response->viewData('schedules'))->pluck('machine_number')->all();
    expect($numbers)->toContain('MC-SEP');
    expect($numbers)->not->toContain('MC-OCT');
});

test('switching area via the url preserves the period filter behavior independently per area', function () {
    $wwd = pmStatusBoardMachine(['area' => 'WWD']);
    $bul = pmStatusBoardMachine(['area' => 'BUL']);
    pmStatusBoardSchedule($wwd, ['machine_number' => 'MC-WWD-SEP', 'plan_date' => '2026-09-15', 'order_number' => 'WO-W1']);
    pmStatusBoardSchedule($bul, ['machine_number' => 'MC-BUL-SEP', 'plan_date' => '2026-09-15', 'order_number' => 'WO-B1']);

    $wwdResponse = $this->get(pmStatusUrl('wwd', ['plan_month' => 9, 'plan_year' => 2026]));
    $bulResponse = $this->get(pmStatusUrl('bul', ['plan_month' => 9, 'plan_year' => 2026]));

    expect(collect($wwdResponse->viewData('schedules'))->pluck('machine_number')->all())->toBe(['MC-WWD-SEP']);
    expect(collect($bulResponse->viewData('schedules'))->pluck('machine_number')->all())->toBe(['MC-BUL-SEP']);
});

test('summary totals follow the active filters and the current area', function () {
    $wwd = pmStatusBoardMachine(['area' => 'WWD']);
    $bul = pmStatusBoardMachine(['area' => 'BUL']);
    pmStatusBoardSchedule($wwd, ['plan_date' => '2026-09-05', 'status' => 'OPEN', 'order_number' => 'WO-S1']);
    pmStatusBoardSchedule($wwd, ['plan_date' => '2026-09-06', 'status' => 'FINISHED', 'actual_date' => '2026-09-06', 'order_number' => 'WO-S2']);
    pmStatusBoardSchedule($wwd, ['plan_date' => '2026-09-07', 'status' => 'FINISHED_ON_TIME', 'actual_date' => '2026-09-07', 'order_number' => 'WO-S3']);
    pmStatusBoardSchedule($bul, ['plan_date' => '2026-09-05', 'status' => 'FINISHED', 'actual_date' => '2026-09-05', 'order_number' => 'WO-S4']);

    $response = $this->get(pmStatusUrl('wwd', ['plan_month' => 9, 'plan_year' => 2026]));

    $summary = $response->viewData('summary');
    expect($summary['total'])->toBe(3);
    expect($summary['open'])->toBe(1);
    expect($summary['closed'])->toBe(2);
});

test('default period is the current month when no filter is given', function () {
    Carbon::setTestNow(Carbon::parse('2026-09-22', 'Asia/Jakarta'));

    $response = $this->get(pmStatusUrl('wwd'));

    expect($response->viewData('currentMonth'))->toBe(9);
    expect($response->viewData('currentYear'))->toBe(2026);

    Carbon::setTestNow();
});

test('year filter still offers the current year even with no pm schedule data at all', function () {
    Carbon::setTestNow(Carbon::parse('2026-09-22', 'Asia/Jakarta'));

    $response = $this->get(pmStatusUrl('wwd'));

    $response->assertOk();
    expect($response->viewData('years'))->toContain(2026);
    $response->assertSee('2026');

    Carbon::setTestNow();
});

test('year filter includes a year that only has schedules in a different month, within the same area', function () {
    $machine = pmStatusBoardMachine();
    pmStatusBoardSchedule($machine, ['plan_date' => '2025-03-01', 'plan_month' => 'March', 'plan_year' => 2025]);

    $response = $this->get(pmStatusUrl('wwd', ['plan_month' => 9, 'plan_year' => 2026]));

    expect($response->viewData('years'))->toContain(2025, 2026);
});

test('year filter for one area is not polluted by another area having a different year', function () {
    $wwd = pmStatusBoardMachine(['area' => 'WWD']);
    $bul = pmStatusBoardMachine(['area' => 'BUL']);
    pmStatusBoardSchedule($wwd, ['plan_date' => '2026-09-01']);
    pmStatusBoardSchedule($bul, ['plan_date' => '2019-01-01', 'plan_month' => 'January', 'plan_year' => 2019]);

    $response = $this->get(pmStatusUrl('wwd', ['plan_month' => 9, 'plan_year' => 2026]));

    expect($response->viewData('years'))->not->toContain(2019);
});
