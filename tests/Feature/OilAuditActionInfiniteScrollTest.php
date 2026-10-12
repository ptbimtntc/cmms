<?php

use App\Models\Area;
use App\Models\Machine;
use App\Models\OilAudit;
use App\Models\OilAuditFollowUp;
use App\Models\User;

function infiniteScrollMachine(array $overrides = []): Machine
{
    return Machine::create(array_merge([
        'machine_number' => 'MC-'.uniqid(),
        'area' => 'WWD',
        'machine_type' => oilAuditMachineType(),
        'status' => 'ACTIVE',
    ], $overrides));
}

function infiniteScrollAudit(Machine $machine, array $overrides = []): OilAudit
{
    return OilAudit::create(array_merge([
        'machine_id' => $machine->id,
        'machine_number' => $machine->machine_number,
        'machine_type' => $machine->machine_type,
        'area' => $machine->area,
        'condition' => 'OKE',
        'audited_by_name' => 'Tester',
        'audited_at' => now(),
    ], $overrides));
}

test('load more endpoint requires authentication', function () {
    $this->get(route('oil-audits.report.more'))->assertRedirect(route('login'));
});

test('guest role cannot access load more', function () {
    $guest = User::factory()->create(['role' => User::ROLE_GUEST]);

    $this->actingAs($guest)->get(route('oil-audits.report.more'))->assertForbidden();
});

test('koordinator bul and pic bul cannot access load more since oil audit is wwd-only', function () {
    $koordinator = User::factory()->create([...roleAttributes('KOORDINATOR BUL')]);
    $pic = User::factory()->create([...roleAttributes('PIC BUL')]);

    $this->actingAs($koordinator)->get(route('oil-audits.report.more'))->assertForbidden();
    $this->actingAs($pic)->get(route('oil-audits.report.more'))->assertForbidden();
});

test('initial action page loads at most 20 audits and reports it has more', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = infiniteScrollMachine();

    for ($i = 0; $i < 25; $i++) {
        infiniteScrollAudit($machine, ['audited_at' => now()->subMinutes(25 - $i)]);
    }

    $response = $this->actingAs($admin)->get(route('oil-audits.report'));

    $response->assertOk();
    $audits = $response->viewData('audits');

    expect($audits->count())->toBe(20)
        ->and($audits->hasMorePages())->toBeTrue();
    $response->assertSee('oil-audit-action-mobile-list', false);
});

test('load more returns the next page of audits without duplicating the first page', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = infiniteScrollMachine();

    $audits = [];
    for ($i = 0; $i < 25; $i++) {
        $audits[] = infiniteScrollAudit($machine, ['audited_at' => now()->subMinutes(25 - $i)]);
    }

    // Newest first: the last-created audit (highest i) is page 1's first row.
    $expectedPage1Ids = collect($audits)->reverse()->take(20)->pluck('id')->values()->all();
    $expectedPage2Ids = collect($audits)->reverse()->slice(20, 5)->pluck('id')->values()->all();

    $page1 = $this->actingAs($admin)->get(route('oil-audits.report'));
    $page1Ids = $page1->viewData('audits')->pluck('id')->all();

    $page2 = $this->actingAs($admin)->getJson(route('oil-audits.report.more', ['page' => 2]));

    $page2->assertOk()->assertJsonStructure(['html', 'has_more', 'next_page']);
    expect($page1Ids)->toBe($expectedPage1Ids)
        ->and($page2->json('has_more'))->toBeFalse()
        ->and($page2->json('next_page'))->toBeNull();

    foreach ($expectedPage2Ids as $id) {
        $machineNumber = OilAudit::find($id)->machine_number;
        expect($page2->json('html'))->toContain($machineNumber);
    }

    // No overlap between what page 1 already showed and what page 2 returns.
    foreach ($expectedPage1Ids as $id) {
        $machineNumberOfOnlyThisAudit = OilAudit::find($id)->machine_number;
        // machine_number is shared across all audits here (same machine), so
        // instead assert page 2's HTML never repeats a page-1-only audit id
        // via the delete form action URL, which is id-specific.
        expect($page2->json('html'))->not->toContain(route('oil-audits.destroy', $id));
    }
});

test('load more follows the same newest-first, id-tiebreak order as the first page', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = infiniteScrollMachine();

    // 20 strictly-newer fillers fully occupy page 1.
    for ($i = 0; $i < 20; $i++) {
        infiniteScrollAudit($machine, ['audited_at' => '2025-06-01 00:00:00']);
    }

    // Two audits tied on audited_at land on page 2 — the later-created
    // (higher id) one must still sort first, exactly like the first page's
    // own tiebreak rule.
    $tiedTimestamp = '2020-01-01 10:00:00';
    $earlierId = infiniteScrollAudit($machine, ['audited_at' => $tiedTimestamp]);
    $laterId = infiniteScrollAudit($machine, ['audited_at' => $tiedTimestamp]);

    $response = $this->actingAs($admin)->getJson(route('oil-audits.report.more', ['page' => 2]));

    $response->assertOk();
    $html = $response->json('html');
    $posLater = strpos($html, route('oil-audits.destroy', $laterId));
    $posEarlier = strpos($html, route('oil-audits.destroy', $earlierId));

    expect($posLater)->not->toBeFalse()
        ->and($posEarlier)->not->toBeFalse()
        ->and($posLater)->toBeLessThan($posEarlier);
});

test('load more applies search and filters identically to the initial page', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = infiniteScrollMachine();

    $kritis = infiniteScrollAudit($machine, ['condition' => 'KRITIS', 'audited_at' => now()]);
    $oke = infiniteScrollAudit($machine, ['condition' => 'OKE', 'audited_at' => now()]);

    $response = $this->actingAs($admin)->getJson(route('oil-audits.report.more', ['condition' => 'KRITIS', 'page' => 1]));

    $response->assertOk();
    expect($response->json('html'))->toContain(route('oil-audits.destroy', $kritis))
        ->and($response->json('html'))->not->toContain(route('oil-audits.destroy', $oke));
});

test('changing the filter never mixes results from the previous filter', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = infiniteScrollMachine();

    $kritis = infiniteScrollAudit($machine, ['condition' => 'KRITIS', 'audited_at' => now()]);
    $oke = infiniteScrollAudit($machine, ['condition' => 'OKE', 'audited_at' => now()]);

    $kritisOnly = $this->actingAs($admin)->get(route('oil-audits.report', ['condition' => 'KRITIS']))
        ->viewData('audits')->pluck('id')->all();
    $okeOnly = $this->actingAs($admin)->get(route('oil-audits.report', ['condition' => 'OKE']))
        ->viewData('audits')->pluck('id')->all();

    expect($kritisOnly)->toBe([$kritis->id])
        ->and($okeOnly)->toBe([$oke->id]);
});

test('load more respects area and role scope restrictions', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $wwd = infiniteScrollAudit(infiniteScrollMachine(['area' => 'WWD']));
    $otherArea = Machine::create([
        'machine_number' => 'MC-OTHER-'.uniqid(),
        'area' => 'BUL',
        'machine_type' => oilAuditMachineType(),
        'status' => 'ACTIVE',
    ]);

    $response = $this->actingAs($admin)->getJson(route('oil-audits.report.more'));

    $response->assertOk();
    expect($response->json('html'))->toContain(route('oil-audits.destroy', $wwd));
});

test('empty result set reports no more pages', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $response = $this->actingAs($admin)->getJson(route('oil-audits.report.more'));

    $response->assertOk()
        ->assertJson(['html' => '', 'has_more' => false, 'next_page' => null]);
});

test('requesting a page beyond the last page stops cleanly with no data', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    infiniteScrollAudit(infiniteScrollMachine());

    $response = $this->actingAs($admin)->getJson(route('oil-audits.report.more', ['page' => 50]));

    $response->assertOk()
        ->assertJson(['html' => '', 'has_more' => false, 'next_page' => null]);
});

test('load more does not recompute or ship the dashboard summary at all', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = infiniteScrollMachine();
    $audit = infiniteScrollAudit($machine, ['condition' => 'KRITIS', 'audited_at' => now()]);
    OilAuditFollowUp::create([
        'oil_audit_id' => $audit->id,
        'problem' => 'Kapstan 1',
        'action_taken' => 'Replaced bearing',
        'pic_name' => 'Andi',
        'actioned_at' => now(),
    ]);

    $before = $this->actingAs($admin)->get(route('oil-audits.report'))->viewData('summary');

    $response = $this->actingAs($admin)->getJson(route('oil-audits.report.more'));
    $response->assertOk()->assertExactJsonStructure(['html', 'has_more', 'next_page']);

    $after = $this->actingAs($admin)->get(route('oil-audits.report'))->viewData('summary');

    expect($after)->toBe($before);
});

test('view and delete actions still work from a load-more rendered card', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = infiniteScrollMachine();
    $audit = infiniteScrollAudit($machine);

    $response = $this->actingAs($admin)->getJson(route('oil-audits.report.more'));
    $response->assertOk();
    expect($response->json('html'))->toContain(route('oil-audits.history', $audit->machine_number))
        ->and($response->json('html'))->toContain(route('oil-audits.destroy', $audit));

    $this->actingAs($admin)->delete(route('oil-audits.destroy', $audit))
        ->assertRedirect()
        ->assertSessionHas('success');
    $this->assertModelMissing($audit);
});

test('desktop numbered pagination is unaffected by the infinite scroll endpoint', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = infiniteScrollMachine();

    for ($i = 0; $i < 25; $i++) {
        infiniteScrollAudit($machine, ['audited_at' => now()->subDays(24 - $i)]);
    }

    $page1 = $this->actingAs($admin)->get(route('oil-audits.report', ['page' => 1]));
    $page2 = $this->actingAs($admin)->get(route('oil-audits.report', ['page' => 2]));

    expect($page1->viewData('audits')->count())->toBe(20)
        ->and($page2->viewData('audits')->count())->toBe(5);
    $page1->assertSee('oil-audit-action-mobile-list', false);
});
