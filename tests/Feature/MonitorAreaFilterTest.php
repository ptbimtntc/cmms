<?php

use App\Models\ManualActivity;
use App\Models\PicAvailability;
use App\Models\User;

function filterPic(string $name, string $role = User::ROLE_PIC_WWD): User
{
    return User::factory()->create(['name' => $name, 'role' => $role, 'is_active' => true]);
}

test('default (no ?area=) behaves exactly like before: every area, "ALL" selected', function () {
    filterPic('ANDI', User::ROLE_PIC_WWD);
    filterPic('BUDI', User::ROLE_PIC_BUL);

    $this->get(route('monitor'))->assertOk()
        ->assertSee('ANDI')->assertSee('BUDI')
        ->assertSee('value="ALL" selected', false);

    $this->getJson(route('monitor.data'))->assertOk()
        ->assertJsonPath('totalPics', 2)
        ->assertJsonCount(2, 'area');
});

test('?area=WWD only returns WWD PICs everywhere in the payload', function () {
    $wwd = filterPic('WWD PERSON', User::ROLE_PIC_WWD);
    filterPic('BUL PERSON', User::ROLE_PIC_BUL);
    ManualActivity::create(['user_id' => $wwd->id, 'name' => 'Repair', 'started_at' => now()]);

    $json = $this->getJson(route('monitor.data', ['area' => 'WWD']))->assertOk()->json();

    expect($json['totalPics'])->toBe(1)
        ->and(collect($json['active'])->pluck('name')->all())->toBe(['WWD PERSON'])
        ->and($json['notStarted'])->toBe([])
        ->and($json['area'])->toHaveKey('WWD')
        ->and($json['area'])->not->toHaveKey('BUL')
        ->and($json['distribution']['MANUAL'])->toBe(1)
        ->and($json['counts']['active'])->toBe(1);
});

test('?area=BUL only returns BUL PICs everywhere in the payload', function () {
    filterPic('WWD PERSON', User::ROLE_PIC_WWD);
    $bul = filterPic('BUL PERSON', User::ROLE_PIC_BUL);

    $json = $this->getJson(route('monitor.data', ['area' => 'BUL']))->assertOk()->json();

    expect($json['totalPics'])->toBe(1)
        ->and($json['notStarted'])->toBe(['BUL PERSON'])
        ->and($json['area'])->toHaveKey('BUL')
        ->and($json['area'])->not->toHaveKey('WWD')
        ->and($json['area']['BUL']['available'])->toBe(1);
});

test('the WWD/BUL filter also scopes Area Status, Not Started and Inactive consistently', function () {
    $wwdActive = filterPic('W ACTIVE', User::ROLE_PIC_WWD);
    filterPic('W IDLE', User::ROLE_PIC_WWD);
    $wwdOff = filterPic('W OFF', User::ROLE_PIC_WWD);
    filterPic('B SOMEONE', User::ROLE_PIC_BUL);

    ManualActivity::create(['user_id' => $wwdActive->id, 'name' => 'Task', 'started_at' => now()]);
    PicAvailability::create(['user_id' => $wwdOff->id, 'date' => now()->toDateString(), 'reason' => 'Cuti', 'set_by_user_id' => $wwdActive->id]);

    $json = $this->getJson(route('monitor.data', ['area' => 'WWD']))->assertOk()->json();

    expect($json['counts'])->toBe(['active' => 1, 'notStarted' => 1, 'inactive' => 1])
        ->and($json['area']['WWD'])->toBe(['active' => 1, 'available' => 2])   // "W OFF" excluded from available
        ->and($json['notStarted'])->toBe(['W IDLE'])
        ->and(collect($json['inactive'])->pluck('name')->all())->toBe(['W OFF'])
        // BUL never leaks into a WWD-filtered response
        ->and(collect($json['active'])->pluck('name')->all())->not->toContain('B SOMEONE')
        ->and($json['notStarted'])->not->toContain('B SOMEONE');
});

test('an unknown ?area= value is ignored and falls back to ALL', function () {
    filterPic('ANDI', User::ROLE_PIC_WWD);
    filterPic('BUDI', User::ROLE_PIC_BUL);

    $this->getJson(route('monitor.data', ['area' => 'NOT_AN_AREA']))->assertOk()
        ->assertJsonPath('totalPics', 2)
        ->assertJsonCount(2, 'area');
});

test('the monitor page selects WWD in the filter and pre-renders WWD-only data for ?area=WWD', function () {
    filterPic('WWD PERSON', User::ROLE_PIC_WWD);
    filterPic('BUL PERSON', User::ROLE_PIC_BUL);

    $this->get(route('monitor', ['area' => 'WWD']))->assertOk()
        ->assertSee('value="WWD" selected', false)
        ->assertDontSee('BUL PERSON');
});

test('the Area filter select is present with ALL / WWD / BUL options, near the clock', function () {
    $html = $this->get(route('monitor'))->assertOk()->getContent();

    expect($html)->toContain('id="monitor-area-filter"')
        ->and($html)->toContain('>ALL</option>')
        ->and($html)->toContain('>WWD</option>')
        ->and($html)->toContain('>BUL</option>')
        ->and($html)->toContain('id="monitor-clock"');
});

test('changing the filter does not reload the page and keeps auto-refresh: filter is read live by poll()', function () {
    $html = $this->get(route('monitor'))->assertOk()->getContent();

    // The JS reads the <select> value on every poll (so auto-refresh keeps
    // respecting whatever is selected) instead of hardcoding the filter once.
    expect($html)->toContain('function currentArea ()')
        ->and($html)->toContain('function dataUrl ()')
        ->and($html)->toContain('fetch(dataUrl()')
        ->and($html)->not->toContain('location.reload')
        ->and($html)->toContain('data-poll-interval="60000"');
});

test('adaptive typography: tiles use CSS container queries, not one fixed font size', function () {
    filterPic('ANDI', User::ROLE_PIC_WWD);
    ManualActivity::create(['user_id' => User::where('name', 'ANDI')->value('id'), 'name' => 'Task', 'started_at' => now()]);

    $html = $this->get(route('monitor'))->assertOk()->getContent();

    expect($html)->toContain('container-type: size')
        ->and($html)->toContain('.tile-name')
        ->and($html)->toContain('.tile-photo')
        ->and($html)->toContain('cqw')
        ->and($html)->toContain('cqh')
        ->and($html)->toContain('clamp(')
        // no more per-tile inline px font-size (that logic moved to CSS)
        ->and($html)->not->toContain('font-size:16px')
        ->and($html)->not->toContain('function scaleTile');
});

test('the treemap still fills the board with zero blank space at 1, 2, 5, 10 and 11 activities', function () {
    foreach ([1, 2, 5, 10, 11] as $n) {
        User::query()->delete();
        ManualActivity::query()->delete();

        for ($i = 1; $i <= $n; $i++) {
            $pic = filterPic("N{$n}-{$i}", User::ROLE_PIC_WWD);
            ManualActivity::create(['user_id' => $pic->id, 'name' => "Task {$i}", 'started_at' => now()]);
        }

        $json = $this->getJson(route('monitor.data'))->assertOk()->json();
        expect(count($json['active']))->toBe($n);

        $html = $this->get(route('monitor'))->assertOk()->getContent();
        // Only the SSR board markup, before the <script> block — the JS
        // below embeds a cardHTML() template literal that also contains
        // the literal string class="monitor-tile (for the poll-rebuilt
        // tiles), which would otherwise inflate this count by one.
        $board = strstr($html, '<script>', true);
        expect(substr_count($board, 'class="monitor-tile'))->toBe($n)
            ->and($board)->toContain('id="monitor-treemap"');
    }
});
