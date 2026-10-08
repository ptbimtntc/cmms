<?php

use App\Models\Machine;
use App\Models\PMSchedule;
use App\Models\User;
use Carbon\Carbon;

function navContextMachine(array $overrides = []): Machine
{
    return Machine::create(array_merge([
        'machine_number' => 'MC-'.uniqid(),
        'area' => 'WWD',
        'machine_type' => 'NDE',
        'status' => 'ACTIVE',
    ], $overrides));
}

function navContextPmSchedule(Machine $machine, array $overrides = []): PMSchedule
{
    $planDate = $overrides['plan_date'] ?? now()->toDateString();

    return PMSchedule::create(array_merge([
        'machine_id' => $machine->id,
        'machine_number' => $machine->machine_number,
        'machine_type' => $machine->machine_type,
        'area' => $machine->area,
        'order_number' => 'ORD-'.uniqid(),
        'plan_date' => $planDate,
        'plan_month' => Carbon::parse($planDate)->format('F'),
        'plan_year' => Carbon::parse($planDate)->format('Y'),
        'due_date' => Carbon::parse($planDate)->addDays(14),
        'pic' => null,
        'actual_date' => now()->toDateString(),
        'status' => 'FINISHED',
    ], $overrides));
}

function navContextActiveClass(string $html, string $href): ?string
{
    preg_match('/href="'.preg_quote($href, '/').'"\s+class="([^"]*)"/', $html, $matches);

    return $matches[1] ?? null;
}

test('report pm view link carries report-pm context and a return_url back to the filtered report', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = navContextMachine();
    $pm = navContextPmSchedule($machine);

    $response = $this->actingAs($admin)->get(route('reports.pm', ['search' => $machine->machine_number]));

    $response->assertOk();
    $response->assertSee('from=report-pm', false);
    $response->assertSee('return_url='.urlencode(route('reports.pm', ['search' => $machine->machine_number])), false);
});

test('machine history detail opened from report pm keeps pm report active and backs to it', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = navContextMachine();
    $pm = navContextPmSchedule($machine);

    $returnUrl = route('reports.pm', ['area' => 'WWD']);

    $response = $this->actingAs($admin)->get(route('machine-history.detail', [
        'machineNumber' => $machine->machine_number,
        'pmSchedule' => $pm->id,
        'from' => 'report-pm',
        'return_url' => $returnUrl,
    ]));

    $response->assertOk();

    // Back button goes to the preserved Report PM URL (filters intact), not Machine History.
    $response->assertSee('href="'.$returnUrl.'"', false);

    $html = $response->getContent();

    $machineHistoryClass = navContextActiveClass($html, route('machine-history.index'));
    $pmReportClass = navContextActiveClass($html, route('reports.pm'));

    expect($machineHistoryClass)->not->toContain('bg-sidebar-active');
    expect($pmReportClass)->toContain('bg-sidebar-active');
});

test('machine history detail opened from report pm falls back to report pm index when return_url is untrusted', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = navContextMachine();
    $pm = navContextPmSchedule($machine);

    $response = $this->actingAs($admin)->get(route('machine-history.detail', [
        'machineNumber' => $machine->machine_number,
        'pmSchedule' => $pm->id,
        'from' => 'report-pm',
        'return_url' => 'https://evil.example.com/phish',
    ]));

    $response->assertOk();
    $response->assertSee('href="'.route('reports.pm').'"', false);
    $response->assertDontSee('evil.example.com');
});

test('machine history detail opened directly from machine history keeps existing back behavior', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = navContextMachine();
    $pm = navContextPmSchedule($machine);

    $response = $this->actingAs($admin)->get(route('machine-history.detail', [
        'machineNumber' => $machine->machine_number,
        'pmSchedule' => $pm->id,
    ]));

    $response->assertOk();

    // Unchanged existing behavior: back goes to this machine's Machine History page.
    $response->assertSee('href="'.route('machine-history.show', $machine->machine_number).'"', false);

    $html = $response->getContent();

    $machineHistoryClass = navContextActiveClass($html, route('machine-history.index'));
    $pmReportClass = navContextActiveClass($html, route('reports.pm'));

    expect($machineHistoryClass)->toContain('bg-sidebar-active');
    expect($pmReportClass)->not->toContain('bg-sidebar-active');
});

test('machine history index to show to detail flow is unaffected', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = navContextMachine();
    $pm = navContextPmSchedule($machine);

    $indexResponse = $this->actingAs($admin)->get(route('machine-history.index'));
    $indexResponse->assertOk();
    $indexResponse->assertSee(route('machine-history.show', $machine->machine_number), false);

    $showResponse = $this->actingAs($admin)->get(route('machine-history.show', $machine->machine_number));
    $showResponse->assertOk();
    $showResponse->assertSee(route('machine-history.detail', [
        'machineNumber' => $machine->machine_number,
        'pmSchedule' => $pm->id,
    ]), false);
});

test('report machine view link carries report-machine context and a return_url', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = navContextMachine();
    navContextPmSchedule($machine);

    $url = route('reports.machine', ['search' => $machine->machine_number]);
    $response = $this->actingAs($admin)->get($url);

    $response->assertOk();
    $response->assertSee('from=report-machine', false);
    $response->assertSee('return_url='.urlencode($url), false);
});

test('machine history opened from report machine keeps report machine active and backs to it', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = navContextMachine();
    $pm = navContextPmSchedule($machine);
    $returnUrl = route('reports.machine', ['area' => 'WWD']);

    $response = $this->actingAs($admin)->get(route('machine-history.show', [
        'machine_history' => $machine->machine_number,
        'from' => 'report-machine',
        'return_url' => $returnUrl,
    ]));

    $response->assertOk();
    $response->assertSee('href="'.$returnUrl.'"', false);

    $html = $response->getContent();
    expect(navContextActiveClass($html, route('machine-history.index')))->not->toContain('bg-sidebar-active');
    expect(navContextActiveClass($html, route('reports.machine')))->toContain('bg-sidebar-active');

    // Context is carried on to the PM detail page, whose Back returns here.
    $response->assertSee('from=report-machine', false);
    $detail = $this->actingAs($admin)->get(route('machine-history.detail', [
        'machineNumber' => $machine->machine_number,
        'pmSchedule' => $pm->id,
        'from' => 'report-machine',
        'return_url' => $returnUrl,
    ]));
    $detail->assertOk();
    $detail->assertSee(e(route('machine-history.show', [
        'machine_history' => $machine->machine_number,
        'from' => 'report-machine',
        'return_url' => $returnUrl,
    ])), false);
    expect(navContextActiveClass($detail->getContent(), route('reports.machine')))->toContain('bg-sidebar-active');
});

test('machine history from report machine ignores an untrusted return_url', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = navContextMachine();

    $response = $this->actingAs($admin)->get(route('machine-history.show', [
        'machine_history' => $machine->machine_number,
        'from' => 'report-machine',
        'return_url' => 'https://evil.example.com/phish',
    ]));

    $response->assertOk()
        ->assertSee('href="'.route('reports.machine').'"', false)
        ->assertDontSee('evil.example.com');
});

test('machine history opened directly keeps its default sidebar state and back button', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = navContextMachine();

    $response = $this->actingAs($admin)->get(route('machine-history.show', $machine->machine_number));

    $response->assertOk()->assertSee('href="'.route('machine-history.index').'"', false);
    $html = $response->getContent();
    expect(navContextActiveClass($html, route('machine-history.index')))->toContain('bg-sidebar-active');
    expect(navContextActiveClass($html, route('reports.machine')))->not->toContain('bg-sidebar-active');
});
