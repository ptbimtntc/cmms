<?php

use App\Models\OilAudit;
use App\Models\User;
use Carbon\Carbon;

test('a PIC who has not started the action activity today sees the prompt', function () {
    $pic = User::factory()->create(['role' => User::ROLE_PIC_WWD]);

    $this->actingAs($pic)->get(route('oil-audits.report'))
        ->assertOk()
        ->assertSee('DO YOU WANT TO START OIL AUDIT ACTION?')
        ->assertSee('oil-audit-action-daily-prompt', false);
});

test('a PIC who already started the action activity today does not see the prompt', function () {
    $pic = User::factory()->create([
        'role' => User::ROLE_PIC_WWD,
        'oil_audit_action_started_at' => now(),
    ]);

    $this->actingAs($pic)->get(route('oil-audits.report'))
        ->assertOk()
        ->assertDontSee('DO YOU WANT TO START OIL AUDIT ACTION?');
});

test('the action prompt is independent from the oil audit scan prompt', function () {
    // Started the scan activity but NOT the action activity → action page still prompts.
    $pic = User::factory()->create([
        'role' => User::ROLE_PIC_WWD,
        'oil_audit_started_at' => now(),
    ]);

    $this->actingAs($pic)->get(route('oil-audits.report'))
        ->assertOk()
        ->assertSee('DO YOU WANT TO START OIL AUDIT ACTION?');
});

test('yesterday\'s action start does not suppress today\'s prompt', function () {
    $pic = User::factory()->create([
        'role' => User::ROLE_PIC_WWD,
        'oil_audit_action_started_at' => now()->subDay(),
    ]);

    $this->actingAs($pic)->get(route('oil-audits.report'))
        ->assertOk()
        ->assertSee('DO YOU WANT TO START OIL AUDIT ACTION?');
});

test('non-PIC roles never see the action prompt', function () {
    foreach ([User::ROLE_ADMIN, User::ROLE_KOORDINATOR_WWD] as $role) {
        $user = User::factory()->create(['role' => $role]);

        $this->actingAs($user)->get(route('oil-audits.report'))
            ->assertOk()
            ->assertDontSee('DO YOU WANT TO START OIL AUDIT ACTION?');
    }
});

test('START records the time and stops the action prompt for the rest of the day', function () {
    $pic = User::factory()->create(['role' => User::ROLE_PIC_WWD]);

    $this->actingAs($pic)
        ->post(route('oil-audits.report.start-daily'), ['started_at' => '2026-09-06T09:45'])
        ->assertRedirect(route('oil-audits.report'));

    $pic->refresh();

    expect($pic->oil_audit_action_started_at->format('Y-m-d H:i'))->toBe('2026-09-06 09:45')
        ->and($pic->hasStartedOilAuditActionToday())->toBeTrue()
        ->and($pic->oil_audit_started_at)->toBeNull();   // scan marker untouched

    $this->actingAs($pic)->get(route('oil-audits.report'))
        ->assertDontSee('DO YOU WANT TO START OIL AUDIT ACTION?');
});

test('a second START on the same day does not overwrite the original action start time', function () {
    $pic = User::factory()->create([
        'role' => User::ROLE_PIC_WWD,
        'oil_audit_action_started_at' => Carbon::parse(now()->toDateString().' 08:00'),
    ]);
    $original = $pic->oil_audit_action_started_at->format('Y-m-d H:i');

    $this->actingAs($pic)
        ->post(route('oil-audits.report.start-daily'), ['started_at' => now()->toDateString().'T13:00'])
        ->assertRedirect(route('oil-audits.report'));

    expect($pic->fresh()->oil_audit_action_started_at->format('Y-m-d H:i'))->toBe($original);
});

test('START requires a datetime', function () {
    $pic = User::factory()->create(['role' => User::ROLE_PIC_WWD]);

    $this->actingAs($pic)
        ->post(route('oil-audits.report.start-daily'), [])
        ->assertSessionHasErrors('started_at');

    expect($pic->fresh()->oil_audit_action_started_at)->toBeNull();
});

test('starting the action activity creates no oil audit or follow-up record', function () {
    $pic = User::factory()->create(['role' => User::ROLE_PIC_WWD]);

    $this->actingAs($pic)->post(route('oil-audits.report.start-daily'), [
        'started_at' => now()->format('Y-m-d\TH:i'),
    ]);

    expect(OilAudit::count())->toBe(0)
        ->and(\App\Models\OilAuditFollowUp::count())->toBe(0);
});

test('the action daily start mechanism is independent per PIC', function () {
    $andi = User::factory()->create(['role' => User::ROLE_PIC_WWD, 'oil_audit_action_started_at' => now()]);
    $budi = User::factory()->create(['role' => User::ROLE_PIC_WWD]);

    $this->actingAs($andi)->get(route('oil-audits.report'))
        ->assertDontSee('DO YOU WANT TO START OIL AUDIT ACTION?');

    $this->actingAs($budi)->get(route('oil-audits.report'))
        ->assertSee('DO YOU WANT TO START OIL AUDIT ACTION?');
});
