<?php

use App\Models\ActivityMonitorClosure;
use App\Models\Machine;
use App\Models\PMSchedule;
use App\Models\User;
use App\Services\ActiveActivityResolver;

/**
 * Regression coverage for: "for Oil Audit / Oil Audit Action, the START
 * prompt shows the first time a PIC visits, but after the PIC switches to a
 * different activity and comes back, the prompt no longer reappears."
 *
 * Root cause: promptStart (and the START endpoint's own write-guard) used
 * to check User::hasStartedOilAuditToday() / hasStartedOilAuditActionToday()
 * — "did this ever get set today" — instead of "is this MY CURRENT activity
 * right now" (ActiveActivityResolver::currentFor()). Since the started-at
 * marker is never reset when the PIC moves to another activity, the prompt
 * stayed suppressed — and the START endpoint silently no-op'd — for the
 * rest of the day even after Oil Audit / Oil Audit Action stopped being
 * current. See app/Http/Controllers/OilAuditController.php.
 */
function reactPic(string $name = 'Reactivation PIC'): User
{
    return User::factory()->create(['role' => User::ROLE_PIC_WWD, 'name' => $name]);
}

function reactPm(string $pic): PMSchedule
{
    $machine = Machine::create([
        'machine_number' => 'MC-'.uniqid(),
        'area' => 'WWD',
        'machine_type' => 'NDE',
        'status' => 'ACTIVE',
    ]);

    return PMSchedule::create([
        'machine_id' => $machine->id,
        'machine_number' => $machine->machine_number,
        'machine_type' => $machine->machine_type,
        'area' => 'WWD',
        'order_number' => 'ORD-'.uniqid(),
        'plan_date' => now()->toDateString(),
        'plan_month' => now()->format('F'),
        'plan_year' => now()->format('Y'),
        'due_date' => now()->addDays(14),
        'pic' => $pic,
        'status' => 'OPEN',
    ]);
}

test('the Oil Audit prompt reappears after the PIC moves to another activity and comes back', function () {
    $pic = reactPic();
    $pm = reactPm($pic->name);

    // 1) First visit — never started anything — prompt shows.
    $this->actingAs($pic)->get(route('oil-audits.scan'))
        ->assertSee('DO YOU WANT TO START OIL AUDIT?');

    // 2) PIC starts Oil Audit.
    $this->actingAs($pic)
        ->post(route('oil-audits.start-daily'), ['started_at' => now()->format('Y-m-d\TH:i')])
        ->assertSessionHas('success');

    expect(app(ActiveActivityResolver::class)->currentFor($pic->fresh())->source)->toBe('OIL_AUDIT');

    // Prompt correctly stays hidden while Oil Audit is still current.
    $this->actingAs($pic)->get(route('oil-audits.scan'))
        ->assertDontSee('DO YOU WANT TO START OIL AUDIT?');

    // 3) PIC switches to PM (END & START) — Oil Audit's own marker is left
    // untouched, but PM is now the PIC's current activity.
    $this->actingAs($pic)
        ->post(route('pm-schedules.start', $pm), [
            'started_at' => now()->addMinutes(30)->format('Y-m-d\TH:i'),
            'confirm_end_start' => 1,
        ])
        ->assertSessionHas('success');

    expect(app(ActiveActivityResolver::class)->currentFor($pic->fresh())->source)->toBe('PM')
        ->and($pic->fresh()->oil_audit_started_at)->not->toBeNull(); // never cleared

    // 4) BUG: this used to stay hidden forever once Oil Audit had been
    // started once today. It must reappear now that PM is current.
    $this->actingAs($pic)->get(route('oil-audits.scan'))
        ->assertSee('DO YOU WANT TO START OIL AUDIT?');
});

test('starting Oil Audit again while another activity is current goes through the conflict flow, not a silent no-op', function () {
    $pic = reactPic();
    $pm = reactPm($pic->name);

    $this->actingAs($pic)->post(route('oil-audits.start-daily'), ['started_at' => now()->format('Y-m-d\TH:i')]);
    $this->actingAs($pic)->post(route('pm-schedules.start', $pm), [
        'started_at' => now()->addMinutes(30)->format('Y-m-d\TH:i'),
        'confirm_end_start' => 1,
    ]);

    $originalOilAuditStart = $pic->fresh()->oil_audit_started_at;

    // Re-clicking START on Oil Audit without confirming must flash the
    // conflict payload (PM is active) instead of silently doing nothing.
    $this->actingAs($pic)
        ->post(route('oil-audits.start-daily'), ['started_at' => now()->addHour()->format('Y-m-d\TH:i')])
        ->assertSessionHas('activity_conflict');

    expect($pic->fresh()->oil_audit_started_at->equalTo($originalOilAuditStart))->toBeTrue();

    // Confirming END & START re-activates Oil Audit as current again.
    $this->actingAs($pic)
        ->post(route('oil-audits.start-daily'), [
            'started_at' => now()->addHour()->format('Y-m-d\TH:i'),
            'confirm_end_start' => 1,
        ])
        ->assertSessionHas('success')
        ->assertSessionMissing('activity_conflict');

    $fresh = $pic->fresh();
    expect($fresh->oil_audit_started_at->equalTo($originalOilAuditStart))->toBeFalse()
        ->and(app(ActiveActivityResolver::class)->currentFor($fresh)->source)->toBe('OIL_AUDIT');

    $this->actingAs($pic)->get(route('oil-audits.scan'))
        ->assertDontSee('DO YOU WANT TO START OIL AUDIT?');
});

test('the Oil Audit Action prompt reappears after the PIC moves to another activity and comes back', function () {
    $pic = reactPic();
    $pm = reactPm($pic->name);

    $this->actingAs($pic)
        ->post(route('oil-audits.report.start-daily'), ['started_at' => now()->format('Y-m-d\TH:i')])
        ->assertSessionHas('success');

    $this->actingAs($pic)->get(route('oil-audits.report'))
        ->assertDontSee('DO YOU WANT TO START OIL AUDIT ACTION?');

    $this->actingAs($pic)
        ->post(route('pm-schedules.start', $pm), [
            'started_at' => now()->addMinutes(30)->format('Y-m-d\TH:i'),
            'confirm_end_start' => 1,
        ])
        ->assertSessionHas('success');

    $this->actingAs($pic)->get(route('oil-audits.report'))
        ->assertSee('DO YOU WANT TO START OIL AUDIT ACTION?');
});

test('switching directly between Oil Audit and Oil Audit Action re-prompts for the one left behind', function () {
    $pic = reactPic();

    // Start Oil Audit first.
    $this->actingAs($pic)->post(route('oil-audits.start-daily'), ['started_at' => now()->format('Y-m-d\TH:i')]);

    // Move to Oil Audit Action via END & START.
    $this->actingAs($pic)
        ->post(route('oil-audits.report.start-daily'), [
            'started_at' => now()->addMinutes(15)->format('Y-m-d\TH:i'),
            'confirm_end_start' => 1,
        ])
        ->assertSessionHas('success');

    expect(app(ActiveActivityResolver::class)->currentFor($pic->fresh())->source)->toBe('OIL_AUDIT_ACTION');

    // Oil Audit scan should prompt again (no longer current)...
    $this->actingAs($pic)->get(route('oil-audits.scan'))
        ->assertSee('DO YOU WANT TO START OIL AUDIT?');

    // ...while Oil Audit Action correctly stays quiet (it IS current).
    $this->actingAs($pic)->get(route('oil-audits.report'))
        ->assertDontSee('DO YOU WANT TO START OIL AUDIT ACTION?');
});

test('after Finish, restarting Oil Audit later the same day makes it current again (stale closure does not stick)', function () {
    $pic = reactPic();

    // Start Oil Audit, then Finish it from the control panel (closure keyed
    // only by pic_user_id — there is no per-instance row for Oil Audit).
    $pic->update(['oil_audit_started_at' => now()->subHour()]);

    ActivityMonitorClosure::create([
        'source' => 'OIL_AUDIT',
        'source_key' => (string) $pic->id,
        'business_date' => today(),
        'pic_user_id' => $pic->id,
        'closed_by_user_id' => $pic->id,
        'closed_at' => now()->subMinutes(30),
    ]);

    expect(app(ActiveActivityResolver::class)->currentFor($pic->fresh()))->toBeNull();

    // Prompt correctly reappears (Oil Audit is not current — it's finished).
    $this->actingAs($pic)->get(route('oil-audits.scan'))
        ->assertSee('DO YOU WANT TO START OIL AUDIT?');

    // PIC starts Oil Audit again, later than the stale closure.
    $this->actingAs($pic)
        ->post(route('oil-audits.start-daily'), ['started_at' => now()->format('Y-m-d\TH:i')])
        ->assertSessionHas('success');

    // The NEW instance must be current — the stale closure must not apply to it.
    $current = app(ActiveActivityResolver::class)->currentFor($pic->fresh());
    expect($current?->source)->toBe('OIL_AUDIT')
        ->and($current->isFinished())->toBeFalse();

    $this->actingAs($pic)->get(route('oil-audits.scan'))
        ->assertDontSee('DO YOU WANT TO START OIL AUDIT?');
});

test('Finish, restart, Finish again on the same day does not crash on a duplicate closure', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'is_active' => true]);
    $pic = reactPic();

    $pic->update(['oil_audit_started_at' => now()->subHours(2)]);

    // First Finish — creates the (OIL_AUDIT, <pic id>, today) closure row.
    $this->actingAs($admin)->post(route('today-activity.finish'), [
        'source' => 'OIL_AUDIT', 'source_key' => (string) $pic->id, 'pic_user_id' => $pic->id,
    ])->assertSessionHas('success');

    expect(ActivityMonitorClosure::count())->toBe(1);

    // PIC restarts Oil Audit later the same day (reactivation).
    $this->actingAs($pic)
        ->post(route('oil-audits.start-daily'), ['started_at' => now()->format('Y-m-d\TH:i')])
        ->assertSessionHas('success');

    expect(app(ActiveActivityResolver::class)->currentFor($pic->fresh())?->source)->toBe('OIL_AUDIT');

    // Finishing it again used to throw a UniqueConstraintViolationException
    // (duplicate source/source_key/business_date) instead of updating the
    // existing closure row.
    $this->actingAs($admin)->post(route('today-activity.finish'), [
        'source' => 'OIL_AUDIT', 'source_key' => (string) $pic->id, 'pic_user_id' => $pic->id,
    ])->assertSessionHas('success');

    expect(ActivityMonitorClosure::count())->toBe(1) // still one row, updated in place
        ->and(app(ActiveActivityResolver::class)->currentFor($pic->fresh()))->toBeNull();
});

test('END and START from Oil Audit into Oil Audit Action properly closes Oil Audit, so it does not resurface when Oil Audit Action is later finished', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'is_active' => true]);
    $pic = reactPic();

    // 1) PIC starts Oil Audit.
    $this->actingAs($pic)
        ->post(route('oil-audits.start-daily'), ['started_at' => now()->format('Y-m-d\TH:i')])
        ->assertSessionHas('success');

    expect(app(ActiveActivityResolver::class)->currentFor($pic->fresh())?->source)->toBe('OIL_AUDIT');

    // 2) PIC clicks Oil Audit Action, gets a conflict, and confirms END & START.
    $this->actingAs($pic)
        ->post(route('oil-audits.report.start-daily'), [
            'started_at' => now()->addMinutes(5)->format('Y-m-d\TH:i'),
            'confirm_end_start' => 1,
        ])
        ->assertSessionHas('success');

    expect(app(ActiveActivityResolver::class)->currentFor($pic->fresh())?->source)->toBe('OIL_AUDIT_ACTION');

    // The old Oil Audit must now be explicitly closed, not merely "older".
    expect(ActivityMonitorClosure::where('source', 'OIL_AUDIT')
        ->where('source_key', (string) $pic->id)
        ->whereDate('business_date', today())
        ->exists())->toBeTrue();

    // 3) Admin finishes Oil Audit Action from the control panel.
    $this->actingAs($admin)->post(route('today-activity.finish'), [
        'source' => 'OIL_AUDIT_ACTION', 'source_key' => (string) $pic->id, 'pic_user_id' => $pic->id,
    ])->assertSessionHas('success');

    // BUG: Oil Audit used to incorrectly resurface as "active" here.
    expect(app(ActiveActivityResolver::class)->currentFor($pic->fresh()))->toBeNull();

    // And the monitor board must show no active tile for this PIC at all.
    $this->getJson(route('monitor.data'))->assertOk()->assertJsonPath('activeCount', 0);
});
