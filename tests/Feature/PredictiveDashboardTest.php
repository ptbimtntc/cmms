<?php

require_once __DIR__.'/../Support/predictive_helpers.php';

use App\Services\Predictive\PredictiveDashboardService;

test('closing rate is closed over total, and an empty set is 0 percent', function () {
    $admin = pvUser('ADMIN');
    expect(pvDash($admin)['overview'])->toBe(['total' => 0, 'open' => 0, 'closed' => 0, 'closing_rate' => 0.0]);

    pvWo(['status' => 'CLOSED']);
    pvWo(['status' => 'CLOSED']);
    pvWo(['status' => 'CLOSED']);
    pvWo(['status' => 'OPEN']);

    expect(pvDash($admin)['overview'])->toBe(['total' => 4, 'open' => 1, 'closed' => 3, 'closing_rate' => 75.0]);
});

test('a WO that was closed and then reopened counts as open', function () {
    $pic = pvUser('PIC WWD');
    $koordinator = pvUser('KOORDINATOR WWD');
    $wo = pvWo();
    $other = pvWo(['status' => 'CLOSED']);

    $this->actingAs($pic)->post(route('predictive.claim', $wo));
    pvClose($this, $pic, $wo);
    expect(pvDash($koordinator)['overview']['closed'])->toBe(2);

    $this->actingAs($koordinator)->post(route('predictive.reopen', $wo));

    expect(pvDash($koordinator)['overview'])->toBe(['total' => 2, 'open' => 1, 'closed' => 1, 'closing_rate' => 50.0]);
});

test('finding and problem summaries count every action including those after reopen', function () {
    $wo = pvWo(['status' => 'CLOSED']);
    $second = pvWo(['status' => 'CLOSED']);
    pvAddAction($wo, 1, 'Bearing aus', 'Getaran');
    pvAddAction($wo, 2, 'bearing AUS ', 'Getaran');
    pvAddAction($second, 1, 'Baut kendor', 'getaran');

    $dash = pvDash(pvUser('ADMIN'));

    expect($dash['findings'])->toBe(['Bearing aus' => 2, 'Baut kendor' => 1])
        ->and(array_values($dash['problems']))->toBe([3]);
});

test('summaries are ordered by count and capped at the top ten', function () {
    $wo = pvWo(['status' => 'CLOSED']);
    foreach (range(1, 12) as $i) {
        pvAddAction($wo, $i, "Finding {$i}", 'P');
    }
    pvAddAction($wo, 13, 'Finding 5', 'P');

    $service = app(PredictiveDashboardService::class);
    $rows = $service->findingSummary($service->workOrders(pvUser('ADMIN'), []));

    expect($rows)->toHaveCount(10)->and($rows->first()->label)->toBe('Finding 5')->and((int) $rows->first()->total)->toBe(2);
});

test('category, area, machine type, period and PIC filters narrow every component together', function () {
    $admin = pvUser('ADMIN');
    $picA = pvUser('PIC WWD');
    $picB = pvUser('PIC WWD');

    $vib = pvWo(['category' => 'VIBRATION', 'status' => 'CLOSED', 'wo_date' => '2026-10-05', 'machine' => pvMachine('V1', 'WWD', 'NDE')]);
    $air = pvWo(['category' => 'AIR_LEAKED', 'status' => 'OPEN', 'wo_date' => '2026-11-05', 'machine' => pvMachine('A1', 'BUL', 'FORGED')]);
    pvAddAction($vib, 1, 'Finding V', 'Problem V', $picA);
    pvAddAction($air, 1, 'Finding A', 'Problem A', $picB);

    expect(pvDash($admin)['overview']['total'])->toBe(2);

    $byCategory = pvDash($admin, ['category' => 'AIR_LEAKED']);
    expect($byCategory['overview']['total'])->toBe(1)->and($byCategory['findings'])->toBe(['Finding A' => 1]);

    $byArea = pvDash($admin, ['area' => 'WWD']);
    expect($byArea['overview']['closed'])->toBe(1)->and($byArea['problems'])->toBe(['Problem V' => 1]);

    $byType = pvDash($admin, ['machine_type' => 'FORGED']);
    expect($byType['overview']['open'])->toBe(1)->and($byType['findings'])->toBe(['Finding A' => 1]);

    $byPeriod = pvDash($admin, ['date_from' => '2026-11-01', 'date_to' => '2026-11-30']);
    expect($byPeriod['overview']['total'])->toBe(1)->and($byPeriod['findings'])->toBe(['Finding A' => 1]);

    $byPic = pvDash($admin, ['pic' => $picA->id]);
    expect($byPic['overview']['total'])->toBe(1)->and($byPic['findings'])->toBe(['Finding V' => 1]);

    $none = pvDash($admin, ['category' => 'VIBRATION', 'area' => 'BUL']);
    expect($none['overview']['total'])->toBe(0)->and($none['findings'])->toBe([]);
});

test('PIC filter also matches a WO the PIC currently holds a claim on', function () {
    $pic = pvUser('PIC WWD');
    pvWo(['pic_user_id' => $pic->id]);
    pvWo();

    expect(pvDash(pvUser('ADMIN'), ['pic' => $pic->id])['overview']['total'])->toBe(1);
});

test('invalid filter values are ignored instead of breaking the query', function () {
    pvWo();

    $dash = pvDash(pvUser('ADMIN'), ['date_from' => 'garbage', 'category' => 'NOPE', 'pic' => 'abc']);

    expect($dash['overview']['total'])->toBe(1);
});

test('dashboard figures respect area authorization', function () {
    pvWo(['machine' => pvMachine('W1', 'WWD')]);
    $bul = pvWo(['status' => 'CLOSED', 'machine' => pvMachine('B1', 'BUL')]);
    pvAddAction($bul, 1, 'Rahasia BUL', 'P');

    $pic = pvDash(pvUser('PIC WWD'));
    expect($pic['overview']['total'])->toBe(1)->and($pic['findings'])->toBe([]);

    // An area filter must not let a non-admin escape their own area.
    $forced = pvDash(pvUser('KOORDINATOR WWD'), ['area' => 'BUL']);
    expect($forced['overview']['total'])->toBe(1)->and($forced['findings'])->toBe([]);
});

test('dashboard page renders the three blocks with filters applied', function () {
    $wo = pvWo(['status' => 'CLOSED']);
    pvAddAction($wo, 1, 'Bearing aus', 'Getaran');

    $this->actingAs(pvUser('ADMIN'))->get(route('predictive.index', ['tab' => 'dashboard']))
        ->assertOk()
        ->assertSee('Closing Rate')
        ->assertSee('100%')
        ->assertSee('Finding Summary')
        ->assertSee('Bearing aus')
        ->assertSee('Problem Summary');

    $this->actingAs(pvUser('ADMIN'))->get(route('predictive.index', ['tab' => 'dashboard', 'category' => 'AIR_LEAKED']))
        ->assertOk()->assertDontSee('Bearing aus')->assertSee('No Action');
});
