<?php

use App\Models\Machine;
use App\Models\OilAudit;
use App\Models\OilAuditFollowUp;
use App\Models\User;
use Carbon\Carbon;

beforeEach(function () {
    // Deterministic "now" so the rolling period windows are testable.
    Carbon::setTestNow(Carbon::parse('2026-08-28 12:00:00'));
});

afterEach(function () {
    Carbon::setTestNow();
});

function analysisMachine(array $overrides = []): Machine
{
    return Machine::create(array_merge([
        'machine_number' => 'MC-'.uniqid(),
        'area' => 'WWD',
        'machine_type' => 'NDE',
        'status' => 'ACTIVE',
    ], $overrides));
}

function analysisAudit(Machine $machine, array $overrides = []): OilAudit
{
    return OilAudit::create(array_merge([
        'machine_id' => $machine->id,
        'machine_number' => $machine->machine_number,
        'machine_type' => $machine->machine_type,
        'area' => $machine->area,
        'condition' => 'KRITIS',
        'audited_by_name' => 'Tester',
        'audited_at' => now(),
    ], $overrides));
}

/**
 * An audit plus its confirmed follow-up carrying the given problem list
 * (one OilAuditFollowUpProblem row per entry). `$auditedAt` anchors the
 * finding on the timeline for the period-window tests.
 */
function analysisFinding(Machine $machine, array $problems, ?string $auditedAt = null): OilAudit
{
    $audit = analysisAudit($machine, $auditedAt ? ['audited_at' => $auditedAt] : []);

    $followUp = OilAuditFollowUp::create([
        'oil_audit_id' => $audit->id,
        'problem' => $problems[0],
        'action_taken' => 'Handled',
        'pic_name' => 'PIC',
        'actioned_at' => $audit->audited_at,
    ]);

    $followUp->problems()->createMany(
        array_map(fn (string $problem) => ['problem' => $problem], $problems)
    );

    return $audit;
}

test('the analysis period defaults to 90 days', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $response = $this->actingAs($admin)->get(route('reports.oil-audit'));

    $response->assertOk();
    expect($response->viewData('selectedPeriod'))->toBe('90');
    // The <select> lives in the existing filter form and pre-selects the default.
    $response->assertSee('name="period"', false);
    $response->assertSee('value="90" selected', false);
});

test('an unknown period value falls back to the 90-day default', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $response = $this->actingAs($admin)->get(route('reports.oil-audit', ['period' => 'bogus']));

    $response->assertOk();
    expect($response->viewData('selectedPeriod'))->toBe('90');
});

test('problem ranking counts every follow-up problem row and orders by frequency', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = analysisMachine();

    // Mainshaft x3, Kapstan 1 x2, Innershaft x1 — spread across 3 follow-ups,
    // one of which lists three problems at once (counted 3 times).
    analysisFinding($machine, ['Mainshaft', 'Kapstan 1']);
    analysisFinding($machine, ['Mainshaft', 'Kapstan 1', 'Innershaft']);
    analysisFinding($machine, ['Mainshaft']);

    $response = $this->actingAs($admin)->get(route('reports.oil-audit'));

    $response->assertOk();
    $ranking = $response->viewData('problemFrequency')
        ->mapWithKeys(fn ($row) => [$row->problem => (int) $row->total]);

    expect($ranking->keys()->all())->toBe(['Mainshaft', 'Kapstan 1', 'Innershaft']);
    expect($ranking->all())->toBe([
        'Mainshaft' => 3,
        'Kapstan 1' => 2,
        'Innershaft' => 1,
    ]);
});

test('the ranking ignores audit conditions that were never confirmed with a follow-up', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = analysisMachine();

    // A finding-grade audit with NO follow-up — must not be counted, since
    // the only source is OilAuditFollowUpProblem.
    analysisAudit($machine, ['condition' => 'KRITIS']);
    analysisFinding($machine, ['Innershaft']);

    $response = $this->actingAs($admin)->get(route('reports.oil-audit'));

    $ranking = $response->viewData('problemFrequency');
    expect($ranking)->toHaveCount(1)
        ->and($ranking->first()->problem)->toBe('Innershaft')
        ->and((int) $ranking->first()->total)->toBe(1);
});

test('the ranking is capped at the top 10 problems', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = analysisMachine();

    foreach (['Kapstan 1', 'Kapstan 2', 'Kapstan 3', 'Kapstan 4', 'Mainshaft', 'Innershaft', 'Oli Keruh/Hitam', 'Level Glass Buram', 'Lainnya'] as $problem) {
        analysisFinding($machine, [$problem]);
    }
    // 9 distinct problems only — still fine, but add two more findings so the
    // busiest ones clearly outrank the rest.
    analysisFinding($machine, ['Kapstan 1', 'Mainshaft']);

    $response = $this->actingAs($admin)->get(route('reports.oil-audit'));

    expect($response->viewData('problemFrequency')->count())->toBeLessThanOrEqual(10);
});

test('a machine at or above the repeat threshold is flagged as berulang', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $repeat = analysisMachine(['machine_number' => 'RPT-001']);
    $lower = analysisMachine(['machine_number' => 'RPT-002']);

    // 3 follow-up events -> repeat offender.
    analysisFinding($repeat, ['Mainshaft']);
    analysisFinding($repeat, ['Kapstan 1']);
    analysisFinding($repeat, ['Kapstan 2']);
    // 2 follow-up events -> listed, but not flagged.
    analysisFinding($lower, ['Mainshaft']);
    analysisFinding($lower, ['Innershaft']);

    $response = $this->actingAs($admin)->get(route('reports.oil-audit'));

    $response->assertOk();
    $events = $response->viewData('repeatFindingMachines')
        ->mapWithKeys(fn ($row) => [$row->machine_number => (int) $row->events]);

    expect($events->all())->toBe(['RPT-001' => 3, 'RPT-002' => 2]);

    // Badge rendered for the 3x machine, and only that one.
    $response->assertSeeInOrder(['RPT-001', 'Berulang'], false);
    expect(substr_count($response->getContent(), '>Berulang</span>'))->toBe(1);
});

test('a machine below the repeat threshold is listed without the berulang badge', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = analysisMachine(['machine_number' => 'RPT-LOW']);

    analysisFinding($machine, ['Mainshaft']);
    analysisFinding($machine, ['Kapstan 1']);

    $response = $this->actingAs($admin)->get(route('reports.oil-audit'));

    $response->assertOk();
    $response->assertSee('RPT-LOW');
    $response->assertDontSee('>Berulang</span>', false);
});

test('a machine with only one follow-up is not treated as a repeat finding', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $once = analysisMachine(['machine_number' => 'ONCE-1']);
    $twice = analysisMachine(['machine_number' => 'TWICE-1']);

    analysisFinding($once, ['Mainshaft']);
    analysisFinding($twice, ['Mainshaft']);
    analysisFinding($twice, ['Kapstan 1']);

    $response = $this->actingAs($admin)->get(route('reports.oil-audit'));

    $response->assertOk();
    // ONCE-1 still shows in the machine list below (it has an audit), but is
    // absent from the repeat-finding panel.
    $machines = $response->viewData('repeatFindingMachines')->pluck('machine_number')->all();
    expect($machines)->toBe(['TWICE-1'])
        ->and($machines)->not->toContain('ONCE-1');
});

test('each repeat-machine row links to the oil audit history page', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = analysisMachine(['machine_number' => 'RPT-LINK']);
    analysisFinding($machine, ['Mainshaft']);
    analysisFinding($machine, ['Kapstan 1']);

    $response = $this->actingAs($admin)->get(route('reports.oil-audit'));

    $response->assertOk();
    $response->assertSee(route('oil-audits.history', 'RPT-LINK'), false);
});

test('the period filter excludes findings whose audit falls outside the window', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = analysisMachine(['machine_number' => 'PER-001']);

    analysisFinding($machine, ['Mainshaft'], now()->subDays(10)->toDateTimeString());
    analysisFinding($machine, ['Kapstan 1'], now()->subDays(60)->toDateTimeString());
    analysisFinding($machine, ['Innershaft'], now()->subDays(200)->toDateTimeString());
    analysisFinding($machine, ['Kapstan 3'], now()->subDays(400)->toDateTimeString());

    $problems = fn ($response) => $response->viewData('problemFrequency')
        ->pluck('problem')->sort()->values()->all();
    $events = fn ($response) => (int) optional(
        $response->viewData('repeatFindingMachines')->firstWhere('machine_number', 'PER-001')
    )->events;

    $default = $this->actingAs($admin)->get(route('reports.oil-audit'));                       // 90 days
    $thirty = $this->actingAs($admin)->get(route('reports.oil-audit', ['period' => '30']));
    $year = $this->actingAs($admin)->get(route('reports.oil-audit', ['period' => '365']));
    $all = $this->actingAs($admin)->get(route('reports.oil-audit', ['period' => 'all']));

    expect($problems($thirty))->toBe(['Mainshaft']);                                            // 10d
    expect($problems($default))->toBe(['Kapstan 1', 'Mainshaft']);                              // 10d + 60d
    expect($problems($year))->toBe(['Innershaft', 'Kapstan 1', 'Mainshaft']);                   // + 200d, not 400d
    expect($problems($all))->toBe(['Innershaft', 'Kapstan 1', 'Kapstan 3', 'Mainshaft']);      // everything

    // The repeat panel needs >= 2 events in-window: 30d leaves only 1, so
    // PER-001 drops out of the panel entirely there.
    expect($events($thirty))->toBe(0);
    expect($events($default))->toBe(2);
    expect($events($year))->toBe(3);
    expect($events($all))->toBe(4);
    expect($thirty->viewData('repeatFindingMachines')->pluck('machine_number'))->not->toContain('PER-001');
});

test('the period filter scopes only the analysis panels, not the machine list', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = analysisMachine(['machine_number' => 'OLD-ONLY']);

    // Two ancient follow-ups: enough to be "berulang" in absolute terms,
    // but both fall outside a 30-day window.
    analysisFinding($machine, ['Mainshaft'], now()->subDays(300)->toDateTimeString());
    analysisFinding($machine, ['Kapstan 1'], now()->subDays(310)->toDateTimeString());

    $thirty = $this->actingAs($admin)->get(route('reports.oil-audit', ['period' => '30']));
    $all = $this->actingAs($admin)->get(route('reports.oil-audit', ['period' => 'all']));

    $thirty->assertOk();
    // Machine list ignores `period` entirely.
    expect($thirty->viewData('machines')->pluck('machine_number')->all())->toContain('OLD-ONLY');
    // Analysis panels honour it: empty at 30d, populated with no date bound.
    expect($thirty->viewData('problemFrequency'))->toBeEmpty();
    expect($thirty->viewData('repeatFindingMachines'))->toBeEmpty();
    expect($all->viewData('repeatFindingMachines')->pluck('machine_number')->all())->toBe(['OLD-ONLY']);
});

test('period combines with the other filters inside the same GET request', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = analysisMachine(['machine_number' => 'COMBO-1']);
    analysisMachine(['machine_number' => 'OTHER-9']);
    analysisFinding($machine, ['Mainshaft'], now()->subDays(5)->toDateTimeString());

    $response = $this->actingAs($admin)->get(route('reports.oil-audit', [
        'search' => 'COMBO-1',
        'period' => '30',
    ]));

    $response->assertOk();
    expect($response->viewData('selectedPeriod'))->toBe('30');
    expect($response->viewData('search'))->toBe('COMBO-1');
    expect($response->viewData('machines')->pluck('machine_number')->all())->toBe(['COMBO-1']);
    expect($response->viewData('problemFrequency')->pluck('problem')->all())->toBe(['Mainshaft']);
    // The chosen period is the one marked selected in the rendered form.
    $response->assertSee('value="30" selected', false);
    $response->assertDontSee('value="90" selected', false);
});

test('analysis is limited to the wwd nde/ndb oil-audit scope', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $inScope = analysisMachine(['machine_number' => 'IN-NDB', 'machine_type' => 'NDB']);
    $wrongType = analysisMachine(['machine_number' => 'OUT-SHX', 'machine_type' => 'SHX']);
    $wrongArea = analysisMachine(['machine_number' => 'OUT-BUL', 'area' => 'BUL', 'machine_type' => 'NDE']);

    analysisFinding($inScope, ['Mainshaft']);
    analysisFinding($inScope, ['Kapstan 1']);
    analysisFinding($wrongType, ['Kapstan 1']);
    analysisFinding($wrongType, ['Kapstan 2']);
    analysisFinding($wrongArea, ['Innershaft']);
    analysisFinding($wrongArea, ['Mainshaft']);

    $response = $this->actingAs($admin)->get(route('reports.oil-audit', ['period' => 'all']));

    $response->assertOk();
    expect($response->viewData('problemFrequency')->pluck('problem')->sort()->values()->all())->toBe(['Kapstan 1', 'Mainshaft']);
    expect($response->viewData('repeatFindingMachines')->pluck('machine_number')->all())->toBe(['IN-NDB']);
});
