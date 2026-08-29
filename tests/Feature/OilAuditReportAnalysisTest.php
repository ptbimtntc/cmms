<?php

use App\Models\Machine;
use App\Models\OilAudit;
use App\Models\OilAuditFollowUp;
use App\Models\User;
use Carbon\Carbon;

beforeEach(function () {
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
 * An audit + its confirmed follow-up carrying a nested Problem -> Finding
 * tree. $problems is [ ['problem' => string, 'findings' => [string, ...]], ... ].
 * Pass 'findings' => [] to reproduce a legacy problem with no finding rows.
 */
function analysisFinding(Machine $machine, array $problems, ?string $auditedAt = null): OilAudit
{
    $audit = analysisAudit($machine, $auditedAt ? ['audited_at' => $auditedAt] : []);

    $followUp = OilAuditFollowUp::create([
        'oil_audit_id' => $audit->id,
        'problem' => $problems[0]['problem'],
        'action_taken' => 'Handled',
        'pic_name' => 'PIC',
        'actioned_at' => $audit->audited_at,
    ]);

    foreach ($problems as $problem) {
        $row = $followUp->problems()->create(['problem' => $problem['problem']]);

        foreach ($problem['findings'] ?? [] as $finding) {
            $row->findings()->create(['finding' => $finding]);
        }
    }

    return $audit;
}

function p(string $problem, array $findings): array
{
    return ['problem' => $problem, 'findings' => $findings];
}

// ---------------------------------------------------------------------------
// Problem & Finding frequency
// ---------------------------------------------------------------------------

test('the analysis period selector is gone', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $response = $this->actingAs($admin)->get(route('reports.oil-audit'));

    $response->assertOk();
    $response->assertDontSee('name="period"', false);
    expect($response->viewData())->not->toHaveKey('selectedPeriod');
});

test('ranking groups by problem + finding and orders by frequency', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = analysisMachine();

    // Bocor Seal — Kapstan 1 : x3   (2 audits list it, one twice via two audits)
    // Bocor Seal — Mainshaft  : x2
    // Bearing Oblak — Kapstan 2 : x1
    analysisFinding($machine, [p('Bocor Seal', ['Kapstan 1', 'Mainshaft'])]);
    analysisFinding($machine, [p('Bocor Seal', ['Kapstan 1', 'Mainshaft'])]);
    analysisFinding($machine, [p('Bocor Seal', ['Kapstan 1']), p('Bearing Oblak', ['Kapstan 2'])]);

    $response = $this->actingAs($admin)->get(route('reports.oil-audit'));

    $response->assertOk();
    $ranking = $response->viewData('problemFrequency')
        ->mapWithKeys(fn ($row) => [$row->problem.' — '.$row->finding => (int) $row->total]);

    expect($ranking->all())->toBe([
        'Bocor Seal — Kapstan 1' => 3,
        'Bocor Seal — Mainshaft' => 2,
        'Bearing Oblak — Kapstan 2' => 1,
    ]);
});

test('the same problem + finding on two different audits counts as two', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = analysisMachine();

    analysisFinding($machine, [p('Bocor Seal', ['Kapstan 1'])]);
    analysisFinding($machine, [p('Bocor Seal', ['Kapstan 1'])]);

    $response = $this->actingAs($admin)->get(route('reports.oil-audit'));

    $row = $response->viewData('problemFrequency')->firstWhere('finding', 'Kapstan 1');
    expect((int) $row->total)->toBe(2);
});

test('a legacy problem without any finding is still counted under a placeholder', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = analysisMachine();

    analysisFinding($machine, [p('Bocor Seal', [])]);
    analysisFinding($machine, [p('Bocor Seal', [])]);

    $response = $this->actingAs($admin)->get(route('reports.oil-audit'));

    $row = $response->viewData('problemFrequency')->firstWhere('problem', 'Bocor Seal');
    expect($row->finding)->toBe('(tanpa detail)')
        ->and((int) $row->total)->toBe(2);
});

test('the ranking is capped at the top 10 combinations', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = analysisMachine();

    foreach (range(1, 13) as $n) {
        analysisFinding($machine, [p('Problem '.$n, ['Finding '.$n])]);
    }

    $response = $this->actingAs($admin)->get(route('reports.oil-audit'));

    expect($response->viewData('problemFrequency')->count())->toBe(10);
});

// ---------------------------------------------------------------------------
// Mesin dengan Temuan Berulang
// ---------------------------------------------------------------------------

test('a machine needs at least two follow-ups to appear in the repeat panel', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $once = analysisMachine(['machine_number' => 'ONCE-1']);
    $twice = analysisMachine(['machine_number' => 'TWICE-1']);

    analysisFinding($once, [p('Bocor Seal', ['Kapstan 1'])]);
    analysisFinding($twice, [p('Bocor Seal', ['Kapstan 1'])]);
    analysisFinding($twice, [p('Bearing Oblak', ['Kapstan 2'])]);

    $response = $this->actingAs($admin)->get(route('reports.oil-audit'));

    $machines = $response->viewData('repeatFindingMachines')->pluck('machine_number')->all();
    expect($machines)->toBe(['TWICE-1']);
});

test('the repeat panel counts follow-up events regardless of problem sameness', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = analysisMachine(['machine_number' => '30001']);

    analysisFinding($machine, [p('Bocor Seal', ['Kapstan 1'])]);
    analysisFinding($machine, [p('Bearing Oblak', ['Kapstan 2'])]);
    analysisFinding($machine, [p('Kapstan Miring', ['Kapstan 3'])]);

    $response = $this->actingAs($admin)->get(route('reports.oil-audit'));

    $row = $response->viewData('repeatFindingMachines')->firstWhere('machine_number', '30001');
    expect((int) $row->events)->toBe(3);
    // 3 events -> "Berulang" badge is rendered.
    $response->assertSeeInOrder(['30001', 'Berulang'], false);
});

// ---------------------------------------------------------------------------
// Filters drive the analysis, search does not
// ---------------------------------------------------------------------------

test('the analysis follows the Area / Machine Type / Year / Month filters', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $nde = analysisMachine(['machine_number' => 'AUG-NDE', 'machine_type' => 'NDE']);
    $ndb = analysisMachine(['machine_number' => 'AUG-NDB', 'machine_type' => 'NDB']);

    analysisFinding($nde, [p('Bocor Seal', ['Kapstan 1'])], '2026-08-10 09:00:00');
    analysisFinding($nde, [p('Bocor Seal', ['Kapstan 1'])], '2026-08-11 09:00:00');
    analysisFinding($nde, [p('Bocor Seal', ['Kapstan 1'])], '2026-07-10 09:00:00'); // other month
    analysisFinding($ndb, [p('Bearing Oblak', ['Kapstan 2'])], '2026-08-12 09:00:00');
    analysisFinding($ndb, [p('Bearing Oblak', ['Kapstan 2'])], '2026-08-13 09:00:00');

    $response = $this->actingAs($admin)->get(route('reports.oil-audit', [
        'machine_type' => 'NDE',
        'year' => 2026,
        'month' => 8,
    ]));

    $response->assertOk();
    $ranking = $response->viewData('problemFrequency');
    expect($ranking)->toHaveCount(1)
        ->and($ranking->first()->problem)->toBe('Bocor Seal')
        ->and((int) $ranking->first()->total)->toBe(2); // July row + NDB rows excluded
});

test('search does not affect the analysis panels', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $a = analysisMachine(['machine_number' => 'SEARCH-HIT']);
    $b = analysisMachine(['machine_number' => 'SEARCH-MISS']);

    analysisFinding($a, [p('Bocor Seal', ['Kapstan 1'])]);
    analysisFinding($b, [p('Bearing Oblak', ['Kapstan 2'])]);

    $response = $this->actingAs($admin)->get(route('reports.oil-audit', ['search' => 'SEARCH-HIT']));

    $response->assertOk();
    // Machine list is narrowed by search...
    expect($response->viewData('machines')->pluck('machine_number')->all())->toBe(['SEARCH-HIT']);
    // ...but the analysis still sees every follow-up in scope.
    expect($response->viewData('problemFrequency')->pluck('problem')->sort()->values()->all())
        ->toBe(['Bearing Oblak', 'Bocor Seal']);
});

test('analysis is limited to the wwd nde/ndb oil-audit scope', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $inScope = analysisMachine(['machine_number' => 'IN-NDB', 'machine_type' => 'NDB']);
    $wrongType = analysisMachine(['machine_number' => 'OUT-SHX', 'machine_type' => 'SHX']);
    $wrongArea = analysisMachine(['machine_number' => 'OUT-BUL', 'area' => 'BUL', 'machine_type' => 'NDE']);

    analysisFinding($inScope, [p('Bocor Seal', ['Kapstan 1'])]);
    analysisFinding($inScope, [p('Bocor Seal', ['Kapstan 1'])]);
    analysisFinding($wrongType, [p('Bearing Oblak', ['Kapstan 2'])]);
    analysisFinding($wrongType, [p('Bearing Oblak', ['Kapstan 2'])]);
    analysisFinding($wrongArea, [p('Kapstan Miring', ['Kapstan 3'])]);
    analysisFinding($wrongArea, [p('Kapstan Miring', ['Kapstan 3'])]);

    $response = $this->actingAs($admin)->get(route('reports.oil-audit'));

    $response->assertOk();
    expect($response->viewData('problemFrequency')->pluck('problem')->unique()->values()->all())
        ->toBe(['Bocor Seal']);
    expect($response->viewData('repeatFindingMachines')->pluck('machine_number')->all())
        ->toBe(['IN-NDB']);
});
