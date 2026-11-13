<?php

use App\Jobs\SendOilAuditCriticalNotification;
use App\Mail\NotificationTestMail;
use App\Mail\OilAuditCriticalMail;
use App\Models\EmailNotificationSetting;
use App\Models\Machine;
use App\Models\OilAudit;
use App\Models\OilAuditNotification;
use App\Models\User;
use App\Services\GmailSmtpMailer;
use App\Services\OilAuditCreateService;
use App\Services\OilAuditCriticalNotifier;
use App\Services\QueueWorkerStatus;
use Illuminate\Contracts\Mail\Mailable;
use Illuminate\Queue\Events\Looping;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

function critMachine(): Machine
{
    return Machine::create([
        'machine_number' => 'CR-'.uniqid(), 'area' => 'WWD',
        'machine_type' => oilAuditMachineType(), 'status' => 'ACTIVE',
    ]);
}

function critSetup(array $o = []): EmailNotificationSetting
{
    return EmailNotificationSetting::create(array_merge([
        'enabled' => true, 'sender_email' => 'sender@gmail.com',
        'sender_password' => 'abcdefghijklmnop', 'recipients' => ['a@example.com', 'b@example.com'],
    ], $o));
}

function critSync(User $u, Machine $m, string $condition, ?string $uuid = null)
{
    return test()->actingAs($u)->postJson(route('api.sync'), [
        'operation_uuid' => $uuid ?? (string) Str::uuid(),
        'transaction_type' => 'OIL_AUDIT_CREATE',
        'payload' => ['machine_id' => $m->id, 'condition' => $condition],
        'expected_state' => [],
    ]);
}

beforeEach(fn () => Mail::fake());

test('online KRITIS audit sends one email to configured recipients', function () {
    critSetup();
    $pic = User::factory()->create(roleAttributes('PIC WWD'));
    $m = critMachine();

    test()->actingAs($pic)->post(route('oil-audits.store'), ['machine_id' => $m->id, 'condition' => 'KRITIS'])
        ->assertRedirect();

    Mail::assertSent(OilAuditCriticalMail::class, 1);
    Mail::assertSent(OilAuditCriticalMail::class, fn ($mail) => $mail->hasTo('a@example.com') && $mail->hasTo('b@example.com'));
    expect(OilAuditNotification::first()->status)->toBe('sent');
});

test('non-KRITIS audit sends nothing', function () {
    critSetup();
    $pic = User::factory()->create(roleAttributes('PIC WWD'));
    test()->actingAs($pic)->post(route('oil-audits.store'), ['machine_id' => critMachine()->id, 'condition' => 'PAS_GARIS']);

    Mail::assertNothingSent();
    expect(OilAuditNotification::count())->toBe(0);
});

test('failed audit save (duplicate) sends nothing extra', function () {
    critSetup();
    $pic = User::factory()->create(roleAttributes('PIC WWD'));
    $m = critMachine();
    test()->actingAs($pic)->post(route('oil-audits.store'), ['machine_id' => $m->id, 'condition' => 'OKE']);
    test()->actingAs($pic)->post(route('oil-audits.store'), ['machine_id' => $m->id, 'condition' => 'KRITIS']);

    Mail::assertNothingSent();
    expect(OilAudit::count())->toBe(1);
});

test('offline sync KRITIS sends once and a replay of the same operation does not resend', function () {
    critSetup();
    $pic = User::factory()->create(roleAttributes('PIC WWD'));
    $m = critMachine();
    $uuid = (string) Str::uuid();

    critSync($pic, $m, 'KRITIS', $uuid)->assertJson(['status' => 'processed']);
    critSync($pic, $m, 'KRITIS', $uuid)->assertJson(['status' => 'already_processed']);
    // A different operation for the same machine/day is a duplicate audit.
    critSync($pic, $m, 'KRITIS')->assertStatus(409);

    Mail::assertSent(OilAuditCriticalMail::class, 1);
    expect(OilAuditNotification::count())->toBe(1);
});

test('rolled back sync transaction sends no email', function () {
    critSetup();
    $m = critMachine();
    $user = User::factory()->create(roleAttributes('PIC WWD'));

    try {
        DB::transaction(function () use ($m, $user) {
            app(OilAuditCreateService::class)->create($m, $user, 'KRITIS');
            throw new RuntimeException('boom');
        });
    } catch (RuntimeException) {
    }

    Mail::assertNothingSent();
    expect(OilAuditNotification::count())->toBe(0);
});

test('notifier is idempotent per audit and queue retry never resends a sent row', function () {
    critSetup();
    $user = User::factory()->create(roleAttributes('PIC WWD'));
    $audit = app(OilAuditCreateService::class)->create(critMachine(), $user, 'KRITIS');

    app(OilAuditCriticalNotifier::class)->notify($audit);
    (new SendOilAuditCriticalNotification(OilAuditNotification::first()->id))->handle(app(GmailSmtpMailer::class));

    Mail::assertSent(OilAuditCriticalMail::class, 1);
    expect(OilAuditNotification::count())->toBe(1);
});

test('failed send is retried and sends once after recovery, audit untouched', function () {
    critSetup();
    $user = User::factory()->create(roleAttributes('PIC WWD'));
    Queue::fake();
    $audit = app(OilAuditCreateService::class)->create(critMachine(), $user, 'KRITIS');
    $n = OilAuditNotification::first();

    // First attempt fails.
    $failing = new class extends GmailSmtpMailer
    {
        public function send(EmailNotificationSetting $setting, array $recipients, Mailable $mailable): void
        {
            throw new RuntimeException('535 Username and Password not accepted abcdefghijklmnop');
        }
    };
    $job = new SendOilAuditCriticalNotification($n->id);
    $job->handle($failing);
    $n->refresh();
    expect($n->status)->toBe('failed')->and($n->last_error)->not->toContain('abcdefghijklmnop');
    expect(OilAudit::find($audit->id))->not->toBeNull();

    // Retry succeeds (and a further duplicate run is a no-op).
    $job->handle(app(GmailSmtpMailer::class));
    $job->handle(app(GmailSmtpMailer::class));
    expect($n->fresh()->status)->toBe('sent');
    Mail::assertSent(OilAuditCriticalMail::class, 1);
});

test('disabled or incomplete configuration sends nothing but still saves the audit', function (array $cfg) {
    critSetup($cfg);
    $pic = User::factory()->create(roleAttributes('PIC WWD'));
    test()->actingAs($pic)->post(route('oil-audits.store'), ['machine_id' => critMachine()->id, 'condition' => 'KRITIS'])
        ->assertSessionHas('success');

    Mail::assertNothingSent();
    expect(OilAudit::count())->toBe(1)->and(OilAuditNotification::first()->status)->toBe('skipped');
})->with([
    'disabled' => [['enabled' => false]],
    'no password' => [['sender_password' => null]],
    'no recipients' => [['recipients' => []]],
]);

test('no settings row at all still saves the audit', function () {
    $pic = User::factory()->create(roleAttributes('PIC WWD'));
    test()->actingAs($pic)->post(route('oil-audits.store'), ['machine_id' => critMachine()->id, 'condition' => 'KRITIS'])
        ->assertSessionHas('success');
    Mail::assertNothingSent();
    expect(OilAudit::count())->toBe(1);
});

test('only ADMIN can open the settings page', function () {
    foreach (['PIC WWD', 'KOORDINATOR WWD', 'GUEST'] as $r) {
        test()->actingAs(User::factory()->create(roleAttributes($r)))->get(route('email-notifications.edit'))->assertForbidden();
    }
    test()->actingAs(User::factory()->create(roleAttributes('ADMIN')))->get(route('email-notifications.edit'))->assertOk();
});

test('non-admin cannot update or test via direct requests', function () {
    $this->actingAs(User::factory()->create(roleAttributes('PIC WWD')));
    $this->put(route('email-notifications.update'), ['enabled' => 1])->assertForbidden();
    $this->post(route('email-notifications.test'))->assertForbidden();
    expect(EmailNotificationSetting::count())->toBe(0);
});

function critSave(array $set = [])
{
    return test()->put(route('email-notifications.update'), array_merge([
        'enabled' => 1, 'sender_email' => 'Sender@Gmail.com', 'app_password' => 'abcd efgh ijkl mnop',
        'recipients' => ['x@example.com', '', 'y@example.com'],
    ], $set));
}

test('admin saves settings; password is encrypted and never rendered', function () {
    $this->actingAs(User::factory()->create(roleAttributes('ADMIN')));

    critSave()->assertRedirect(route('email-notifications.edit'))->assertSessionHasNoErrors();

    $s = EmailNotificationSetting::first();
    expect($s->sender_password)->toBe('abcdefghijklmnop')
        ->and($s->recipients)->toBe(['x@example.com', 'y@example.com'])
        ->and($s->sender_email)->toBe('sender@gmail.com');
    expect(DB::table('email_notification_settings')->value('sender_password'))->not->toContain('abcdefghijklmnop');
    expect(json_encode($s))->not->toContain('abcdefghijklmnop');

    $this->get(route('email-notifications.edit'))->assertOk()
        ->assertSee('x@example.com')->assertDontSee('abcdefghijklmnop');

    // Blank password on a later save keeps the stored one.
    critSave(['app_password' => '', 'recipients' => ['z@example.com']])->assertSessionHasNoErrors();
    expect(EmailNotificationSetting::first()->sender_password)->toBe('abcdefghijklmnop');
});

test('settings validation', function (array $set, string $errorKey) {
    $this->actingAs(User::factory()->create(roleAttributes('ADMIN')));
    critSave($set)->assertSessionHasErrors($errorKey);
    expect(EmailNotificationSetting::count())->toBe(0);
})->with([
    'non-gmail sender' => [['sender_email' => 'a@yahoo.com'], 'sender_email'],
    'bad app password' => [['app_password' => 'short'], 'app_password'],
    'bad recipient' => [['recipients' => ['nope']], 'recipients.0'],
    'no recipients when enabled' => [['recipients' => ['']], 'recipients'],
    'duplicate recipients' => [['recipients' => ['a@x.com', 'A@x.com']], 'recipients'],
]);

test('test email goes only through the test path, creates no audit or notification', function () {
    critSetup();
    $this->actingAs(User::factory()->create(roleAttributes('ADMIN')));

    $this->post(route('email-notifications.test'))->assertSessionHas('success');

    Mail::assertSent(NotificationTestMail::class, 1);
    Mail::assertNotSent(OilAuditCriticalMail::class);
    expect(OilAudit::count())->toBe(0)->and(OilAuditNotification::count())->toBe(0);
    expect(EmailNotificationSetting::first()->last_test_ok)->toBeTrue();
});

test('test email failure is reported without leaking the password', function () {
    critSetup();
    $this->actingAs(User::factory()->create(roleAttributes('ADMIN')));
    app()->instance(GmailSmtpMailer::class, new class extends GmailSmtpMailer
    {
        public function send(EmailNotificationSetting $setting, array $recipients, Mailable $mailable): void
        {
            throw new RuntimeException('535 5.7.8 Username and Password not accepted abcdefghijklmnop');
        }
    });

    $this->post(route('email-notifications.test'))->assertSessionHas('error');

    $s = EmailNotificationSetting::first();
    expect($s->last_test_ok)->toBeFalse()->and($s->last_test_message)->not->toContain('abcdefghijklmnop');
});

test('settings page reports queue worker status from the heartbeat', function () {
    $this->actingAs(User::factory()->create(roleAttributes('ADMIN')));
    config(['queue.default' => 'database']);

    Cache::forget(QueueWorkerStatus::KEY);
    $this->get(route('email-notifications.edit'))->assertSee('NOT ACTIVE');

    event(new Looping('database', 'default'));
    $this->get(route('email-notifications.edit'))->assertSee('Queue worker: ACTIVE', false);

    Cache::put(QueueWorkerStatus::KEY, time() - 600);
    $this->get(route('email-notifications.edit'))->assertSee('NOT ACTIVE');
});
