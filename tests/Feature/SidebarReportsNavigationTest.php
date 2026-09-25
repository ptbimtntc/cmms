<?php

use App\Models\User;

/**
 * The old Report Center hub (/reports, name "reports.index") was removed —
 * the sidebar now links straight to each of the 7 report pages via a
 * nested "Reports" sub-accordion. See resources/views/partials/sidebar.blade.php.
 */
test('admin sees a direct sidebar link to every report page, not a hub', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $response = $this->actingAs($admin)->get(route('dashboard'));

    $response->assertOk();
    $response->assertSee(route('reports.pm'), false);
    $response->assertSee(route('reports.greasing'), false);
    $response->assertSee(route('reports.oil-audit'), false);
    $response->assertSee(route('reports.sparepart'), false);
    $response->assertSee(route('reports.machine'), false);
    $response->assertSee(route('reports.problem'), false);
    $response->assertSee(route('reports.cost'), false);
});

test('the report center hub route no longer exists', function () {
    expect(fn () => route('reports.index'))
        ->toThrow(Symfony\Component\Routing\Exception\RouteNotFoundException::class);
});

test('visiting the old hub url returns 404', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $response = $this->actingAs($admin)->get('/reports');

    $response->assertNotFound();
});

test('oil audit report link is visible to wwd-eligible roles', function () {
    foreach ([User::ROLE_ADMIN, User::ROLE_KOORDINATOR_WWD, User::ROLE_PIC_WWD] as $role) {
        $user = User::factory()->create(['role' => $role]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertSee(route('reports.oil-audit'), false);
    }
});

test('oil audit report link is hidden from bul-only roles', function () {
    foreach ([User::ROLE_KOORDINATOR_BUL, User::ROLE_PIC_BUL] as $role) {
        $user = User::factory()->create(['role' => $role]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertDontSee(route('reports.oil-audit'), false);
    }
});

test('a pic role still sees the reports submenu with its allowed reports', function () {
    $pic = User::factory()->create(['role' => User::ROLE_PIC_WWD]);

    $response = $this->actingAs($pic)->get(route('dashboard'));

    $response->assertOk();
    $response->assertSee(route('reports.pm'), false);
    $response->assertSee(route('reports.machine'), false);
});

test('the reports submenu auto-expands when a report page is open', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $response = $this->actingAs($admin)->get(route('reports.pm'));

    $response->assertOk();
    $response->assertSee("openSubmenu: 'reports'", false);
});

test('the reports submenu stays collapsed by default outside any report page', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $response = $this->actingAs($admin)->get(route('dashboard'));

    $response->assertOk();
    $response->assertSee("openSubmenu: ''", false);
});

test('the active report link is highlighted while its siblings are not', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $response = $this->actingAs($admin)->get(route('reports.pm'));
    $html = $response->getContent();

    $pmHref = e(route('reports.pm'));
    $greasingHref = e(route('reports.greasing'));
    $pmAnchorStart = strpos($html, 'href="'.$pmHref.'"');
    $greasingAnchorStart = strpos($html, 'href="'.$greasingHref.'"');

    expect($pmAnchorStart)->not->toBeFalse();
    expect($greasingAnchorStart)->not->toBeFalse();
    expect(substr($html, $pmAnchorStart, 200))->toContain('bg-sidebar-active');
    expect(substr($html, $greasingAnchorStart, 200))->not->toContain('bg-sidebar-active');
});

test('report pages no longer show a report center back-button', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    foreach (['reports.pm', 'reports.greasing', 'reports.oil-audit', 'reports.sparepart', 'reports.machine', 'reports.problem', 'reports.cost'] as $routeName) {
        $response = $this->actingAs($admin)->get(route($routeName));

        $response->assertOk();
        $response->assertDontSee('Report Center');
    }
});
