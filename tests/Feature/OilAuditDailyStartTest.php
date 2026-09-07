<?php

use App\Models\OilAudit;
use App\Models\User;
use Carbon\Carbon;

test('a PIC who has not started today sees the daily start prompt', function () {
    $pic = User::factory()->create(['role' => User::ROLE_PIC_WWD]);

    $this->actingAs($pic)->get(route('oil-audits.scan'))
        ->assertOk()
        ->assertSee('DO YOU WANT TO START OIL AUDIT?')
        ->assertSee('oil-audit-daily-prompt', false);
});

test('a PIC who already started today does not see the prompt', function () {
    $pic = User::factory()->create([
        'role' => User::ROLE_PIC_WWD,
        'oil_audit_started_at' => now(),
    ]);

    $this->actingAs($pic)->get(route('oil-audits.scan'))
        ->assertOk()
        ->assertDontSee('DO YOU WANT TO START OIL AUDIT?');
});

test('a PIC whose last start was yesterday sees the prompt again today', function () {
    $pic = User::factory()->create([
        'role' => User::ROLE_PIC_WWD,
        'oil_audit_started_at' => now()->subDay(),
    ]);

    $this->actingAs($pic)->get(route('oil-audits.scan'))
        ->assertOk()
        ->assertSee('DO YOU WANT TO START OIL AUDIT?');
});

test('non-PIC roles never see the prompt', function () {
    foreach ([User::ROLE_ADMIN, User::ROLE_KOORDINATOR_WWD] as $role) {
        $user = User::factory()->create(['role' => $role]);

        $this->actingAs($user)->get(route('oil-audits.scan'))
            ->assertOk()
            ->assertDontSee('DO YOU WANT TO START OIL AUDIT?');
    }
});

test('START records the start time and stops the prompt for the rest of the day', function () {
    $pic = User::factory()->create(['role' => User::ROLE_PIC_WWD]);

    $startedAt = now()->setTime(8, 15);

    $this->actingAs($pic)
        ->post(route('oil-audits.start-daily'), ['started_at' => $startedAt->format('Y-m-d\TH:i')])
        ->assertRedirect(route('oil-audits.scan'));

    $pic->refresh();

    expect($pic->oil_audit_started_at)->not->toBeNull()
        ->and($pic->oil_audit_started_at->format('Y-m-d H:i'))->toBe($startedAt->format('Y-m-d H:i'))
        ->and($pic->hasStartedOilAuditToday())->toBeTrue();

    $this->actingAs($pic)->get(route('oil-audits.scan'))
        ->assertDontSee('DO YOU WANT TO START OIL AUDIT?');
});

test('a second START on the same day does not overwrite the original start time', function () {
    $pic = User::factory()->create([
        'role' => User::ROLE_PIC_WWD,
        'oil_audit_started_at' => Carbon::parse(now()->toDateString().' 07:00'),
    ]);
    $original = $pic->oil_audit_started_at->format('Y-m-d H:i');

    $this->actingAs($pic)
        ->post(route('oil-audits.start-daily'), ['started_at' => now()->toDateString().'T11:30'])
        ->assertRedirect(route('oil-audits.scan'));

    expect($pic->fresh()->oil_audit_started_at->format('Y-m-d H:i'))->toBe($original);
});

test('START requires a datetime', function () {
    $pic = User::factory()->create(['role' => User::ROLE_PIC_WWD]);

    $this->actingAs($pic)
        ->post(route('oil-audits.start-daily'), [])
        ->assertSessionHasErrors('started_at');

    expect($pic->fresh()->oil_audit_started_at)->toBeNull();
});

test('the daily start mechanism is independent per PIC', function () {
    $andi = User::factory()->create(['role' => User::ROLE_PIC_WWD, 'oil_audit_started_at' => now()]);
    $budi = User::factory()->create(['role' => User::ROLE_PIC_WWD]);

    $this->actingAs($andi)->get(route('oil-audits.scan'))
        ->assertDontSee('DO YOU WANT TO START OIL AUDIT?');

    $this->actingAs($budi)->get(route('oil-audits.scan'))
        ->assertSee('DO YOU WANT TO START OIL AUDIT?');
});

test('starting the daily activity creates no oil audit record', function () {
    $pic = User::factory()->create(['role' => User::ROLE_PIC_WWD]);

    $this->actingAs($pic)->post(route('oil-audits.start-daily'), ['started_at' => now()->format('Y-m-d\TH:i')]);

    expect(OilAudit::count())->toBe(0);
});
