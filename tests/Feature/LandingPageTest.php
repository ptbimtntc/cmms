<?php

use App\Models\Area;
use App\Models\User;

// The areas table migration seeds WWD and BUL into every fresh test database
// (see database/migrations/2026_09_29_090000_create_areas_table.php), so
// these tests build on those two instead of creating colliding rows.

it('renders with exactly one h1 and no fabricated numbers when every table is empty', function () {
    $response = $this->get('/')->assertOk();

    $html = $response->getContent();
    expect(substr_count($html, '<h1'))->toBe(1);

    // Real figures only: the overview section must show zeroes, not any
    // placeholder-looking statistic (R-17).
    $response->assertSee('No PM is planned for this month yet.');
});

it('links to every public destination a guest can reach without an account', function () {
    $response = $this->get('/')->assertOk();

    $response->assertSee(route('pm-status.show', 'wwd'), false);
    $response->assertSee(route('monitor'), false);
    $response->assertSee(route('dashboard-guest'), false);
    $response->assertSee(route('machine-history.index'), false);
    $response->assertSee(route('qr.scan'), false);
    $response->assertSee(route('login'), false);
});

it('offers every active area and only active ones', function () {
    $inactive = Area::factory()->inactive()->create();

    $response = $this->get('/')->assertOk();

    $response->assertSee('WWD');
    $response->assertDontSee($inactive->name);
});

it('hides Maintenance Intelligence from guests and shows it to admins', function () {
    $this->get('/')->assertOk()->assertDontSee('Maintenance Intelligence');

    $admin = User::factory()->admin()->create();
    $this->actingAs($admin)->get('/')->assertOk()
        ->assertSee(route('maintenance-intelligence.index'), false)
        ->assertSee('Maintenance Intelligence');
});

it('still renders a usable page when Area has no active rows', function () {
    Area::query()->update(['is_active' => false]);

    // The hero itself does not query Area (it is the restored cinematic
    // version with fixed CTAs); the closing section does, and falls back to
    // a dashboard link instead of an empty area list.
    $this->get('/')->assertOk()
        ->assertSee('Open the dashboard instead')
        ->assertSee(route('dashboard-guest'), false);
});

it('search box on the landing page reaches the real machine history search', function () {
    $this->get('/machine-history?search=30001')->assertOk();
});
