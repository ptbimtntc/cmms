<?php

require_once __DIR__.'/../Support/predictive_helpers.php';

use App\Models\ActivityMonitorClosure;
use App\Models\JobType;
use App\Models\PMSchedule;
use App\Models\PredictiveAction;
use App\Models\PredictiveWorkOrder;
use App\Models\User;
use App\Services\ActiveActivityResolver;
use App\Services\Vme\JobTypeResolver;
use Carbon\Carbon;

const PV_PROMPT = 'DO YOU WANT TO START ACTION PREDICTIVE?';

function pvPlainPic(): User
{
    return pvUser('PIC WWD', started: false);
}

function pvStartedPm(User $pic): PMSchedule
{
    $machine = pvMachine('PM-'.uniqid());
    $today = now()->toDateString();

    return PMSchedule::create([
        'machine_id' => $machine->id, 'machine_number' => $machine->machine_number, 'machine_type' => 'NDE', 'area' => 'WWD',
        'order_number' => 'ORD-'.uniqid(), 'plan_date' => $today,
        'plan_month' => Carbon::parse($today)->format('F'), 'plan_year' => Carbon::parse($today)->format('Y'),
        'due_date' => Carbon::parse($today)->addDays(14), 'pic' => $pic->name,
        'status' => 'IN_PROGRESS', 'start_time' => '08:00', 'actual_date' => $today,
    ]);
}

function PredictiveWorkOrderForTest(): PredictiveWorkOrder
{
    return PredictiveWorkOrder::query()->firstOrFail();
}

function pvStartPayload(array $extra = []): array
{
    return array_merge(['started_at' => now()->format('Y-m-d\TH:i')], $extra);
}

test('a PIC who has not started Action Predictive sees the prompt on every list tab', function () {
    $pic = pvPlainPic();

    foreach (['vibration', 'air-leaked', 'dashboard'] as $tab) {
        $this->actingAs($pic)->get(route('predictive.index', ['tab' => $tab]))->assertOk()->assertSee(PV_PROMPT);
    }
});

test('the prompt is not shown once started, after yesterday only, or to other roles', function () {
    $started = pvUser('PIC WWD');
    $this->actingAs($started)->get(route('predictive.index'))->assertOk()->assertDontSee(PV_PROMPT);

    $yesterday = pvPlainPic();
    $yesterday->update(['predictive_started_at' => now()->subDay()]);
    $this->actingAs($yesterday)->get(route('predictive.index'))->assertSee(PV_PROMPT);

    $supervisor = User::factory()->create(['role' => User::ROLE_SUPERVISOR]);
    foreach ([pvUser('ADMIN'), pvUser('KOORDINATOR WWD'), $supervisor] as $user) {
        $this->actingAs($user)->get(route('predictive.index'))->assertOk()->assertDontSee(PV_PROMPT);
    }
});

test('the detail page has no prompt but tells an unstarted PIC why the buttons are off', function () {
    $wo = pvWo();
    $pic = pvPlainPic();

    $this->actingAs($pic)->get(route('predictive.show', $wo))
        ->assertOk()->assertDontSee(PV_PROMPT)->assertSee('Action Predictive activity has not started');
});

test('start records the activity as the PIC current one', function () {
    $pic = pvPlainPic();

    $this->actingAs($pic)->post(route('predictive.start'), pvStartPayload())->assertSessionHas('success');

    $current = app(ActiveActivityResolver::class)->currentFor($pic->fresh());
    expect($pic->fresh()->predictive_started_at)->not->toBeNull()
        ->and($current->source)->toBe('PREDICTIVE')
        ->and($current->label)->toBe('Action Predictive')
        ->and($current->picName)->toBe($pic->name)
        ->and($current->jobTypeKey)->toBe('predictive');

    $this->actingAs($pic->fresh())->get(route('predictive.index'))->assertDontSee(PV_PROMPT);
});

test('starting again while Predictive is already current changes nothing', function () {
    Illuminate\Support\Carbon::setTestNow('2026-11-07 12:00:00');
    $pic = pvPlainPic();
    $this->actingAs($pic)->post(route('predictive.start'), pvStartPayload(['started_at' => now()->subHour()->format('Y-m-d\TH:i')]));
    $first = $pic->fresh()->predictive_started_at;

    $this->actingAs($pic->fresh())->post(route('predictive.start'), pvStartPayload())->assertSessionHas('success');

    expect($pic->fresh()->predictive_started_at->equalTo($first))->toBeTrue();
});

test('only a PIC can start, and a start time is required', function () {
    $supervisor = User::factory()->create(['role' => User::ROLE_SUPERVISOR]);

    foreach ([pvUser('ADMIN'), pvUser('KOORDINATOR WWD'), $supervisor] as $user) {
        $this->actingAs($user)->post(route('predictive.start'), pvStartPayload())->assertForbidden();
        expect($user->fresh()->predictive_started_at)->toBeNull();
    }

    $this->actingAs(pvPlainPic())->post(route('predictive.start'), [])->assertSessionHasErrors('started_at');
});

test('start requires login', function () {
    $this->post(route('predictive.start'), pvStartPayload())->assertRedirect(route('login'));
});

test('an active PM makes the start ask for END and START', function () {
    $pic = pvPlainPic();
    $pm = pvStartedPm($pic);

    $this->actingAs($pic)->post(route('predictive.start'), pvStartPayload())->assertSessionHas('activity_conflict');

    $conflict = session('activity_conflict');
    expect($conflict['current'])->toBe('PM')
        ->and($conflict['action'])->toBe(route('predictive.start'))
        ->and($pic->fresh()->predictive_started_at)->toBeNull()
        ->and($pm->fresh()->status)->toBe('IN_PROGRESS');
});

test('END and START closes the PM activity, starts Predictive and leaves the PM work untouched', function () {
    $pic = pvPlainPic();
    $pm = pvStartedPm($pic);

    $this->actingAs($pic)->post(route('predictive.start'), pvStartPayload(['confirm_end_start' => '1']))
        ->assertSessionHas('success')->assertSessionMissing('activity_conflict');

    $resolver = app(ActiveActivityResolver::class);
    expect($resolver->currentFor($pic->fresh())->source)->toBe('PREDICTIVE')
        ->and($pm->fresh()->status)->toBe('IN_PROGRESS')
        ->and(ActivityMonitorClosure::where('source', 'PM')->where('source_key', (string) $pm->id)->exists())->toBeTrue();
});

test('an active Predictive makes other modules ask before they start', function () {
    $pic = pvUser('PIC WWD');
    $this->actingAs($pic)->post(route('predictive.start'), pvStartPayload());
    $pm = pvStartedPm($pic);
    $pm->update(['status' => 'OPEN', 'start_time' => null, 'actual_date' => null]);

    $this->actingAs($pic->fresh())->post(route('pm-schedules.start', $pm), pvStartPayload())->assertSessionHas('activity_conflict');

    expect(session('activity_conflict')['current'])->toBe('Action Predictive');
});

test('Claim is refused until the PIC has started, and then works', function () {
    $pic = pvPlainPic();
    $wo = pvWo();

    $this->actingAs($pic)->post(route('predictive.claim', $wo))->assertSessionHas('error');
    expect($wo->fresh()->pic_user_id)->toBeNull();

    $this->actingAs($pic)->post(route('predictive.start'), pvStartPayload());
    $this->actingAs($pic->fresh())->post(route('predictive.claim', $wo))->assertSessionHas('success');
    expect($wo->fresh()->pic_user_id)->toBe($pic->id);
});

test('Action and Closing is refused until the PIC has started, keeping what was typed', function () {
    $pic = pvPlainPic();
    $wo = pvWo(['pic_user_id' => $pic->id]);
    $option = pvOption($wo, 'Bearing aus', ['Sisi motor']);
    $body = ['_action_wo' => $wo->id, 'remarks' => 'Catatan saya', 'problems' => [['problem_id' => $option['problem']->id, 'findings' => [['finding_id' => $option['findings'][0]->id]]]]];

    $this->actingAs($pic)->post(route('predictive.action', $wo), $body)
        ->assertSessionHas('error')->assertSessionHasInput('remarks', 'Catatan saya');
    expect(PredictiveAction::count())->toBe(0)->and($wo->fresh()->status)->toBe('OPEN');

    $this->actingAs($pic)->post(route('predictive.start'), pvStartPayload());
    $this->actingAs($pic->fresh())->post(route('predictive.action', $wo), $body)->assertSessionHas('success');
    expect($wo->fresh()->status)->toBe('CLOSED');
});

test('the gate returns when the activity is finished from the control panel or replaced by another one', function () {
    $pic = pvUser('PIC WWD');
    $this->actingAs($pic)->post(route('predictive.start'), pvStartPayload());
    $wo = pvWo();
    $this->actingAs($pic->fresh())->post(route('predictive.claim', $wo))->assertSessionHas('success');

    // Finish from the panel.
    ActivityMonitorClosure::create([
        'source' => 'PREDICTIVE', 'source_key' => (string) $pic->id, 'business_date' => today(),
        'pic_user_id' => $pic->id, 'closed_by_user_id' => $pic->id, 'closed_at' => now(),
    ]);
    $second = pvWo();
    $this->actingAs($pic->fresh())->post(route('predictive.claim', $second))->assertSessionHas('error');
    expect($second->fresh()->pic_user_id)->toBeNull();

    // Starting again clears the stale closure and reopens the gate.
    $this->actingAs($pic->fresh())->post(route('predictive.start'), pvStartPayload())->assertSessionHas('success');
    expect(ActivityMonitorClosure::where('source', 'PREDICTIVE')->count())->toBe(0);
    $this->actingAs($pic->fresh())->post(route('predictive.claim', $second))->assertSessionHas('success');

    // Another module takes over as the current activity (END and START into a PM).
    $pm = pvStartedPm($pic);
    $pm->update(['status' => 'OPEN', 'start_time' => null, 'actual_date' => null]);
    $this->actingAs($pic->fresh())->post(route('pm-schedules.start', $pm), pvStartPayload(['confirm_end_start' => '1']))->assertSessionHas('success');
    expect(app(ActiveActivityResolver::class)->currentFor($pic->fresh())->source)->toBe('PM');

    $third = pvWo();
    $this->actingAs($pic->fresh())->post(route('predictive.claim', $third))->assertSessionHas('error');
    expect($third->fresh()->pic_user_id)->toBeNull();
});

test('only PICs are gated: managers keep every action without starting anything', function () {
    $koordinator = pvUser('KOORDINATOR WWD');
    $wo = pvWo(['status' => 'CLOSED']);

    $this->actingAs($koordinator)->post(route('predictive.reopen', $wo))->assertSessionHas('success');
    $this->actingAs($koordinator)->put(route('predictive.update', $wo), pvEdit(['machine_number' => $wo->machine_number]))->assertSessionHasNoErrors();
    expect($wo->fresh()->status)->toBe('OPEN');
});

test('an unstarted PIC sees Claim and Action disabled with a banner, a started PIC sees them live', function () {
    $pic = pvPlainPic();
    pvWo(['order_number' => 'WO-GATE']);

    $this->actingAs($pic)->get(route('predictive.index'))
        ->assertSee('Action Predictive activity has not started')
        ->assertSee('Start the Action Predictive activity first')
        ->assertSee('Start Predictive')
        ->assertDontSee('action="'.route('predictive.claim', PredictiveWorkOrderForTest()), false);

    $started = pvUser('PIC WWD');
    $this->actingAs($started)->get(route('predictive.index'))
        ->assertDontSee('Action Predictive activity has not started')
        ->assertSee(route('predictive.claim', PredictiveWorkOrderForTest()), false);
});

test('Action Predictive shows on the monitor, its distribution and the VME job type', function () {
    $pic = pvPlainPic();
    $pic->update(['name' => 'BUDI PREDICT', 'is_active' => true]);
    $this->actingAs($pic)->post(route('predictive.start'), pvStartPayload());

    $this->getJson(route('monitor.data'))->assertOk()->assertJsonPath('distribution.PREDICTIVE', 1);
    $this->get(route('monitor'))->assertOk()->assertSee('BUDI PREDICT')->assertSee('Action Predictive');

    expect(JobType::where('key', 'predictive')->where('is_active', true)->exists())->toBeTrue()
        ->and((new JobTypeResolver)->keyFor('PREDICTIVE'))->toBe('predictive')
        ->and((new JobTypeResolver)->keyFor('MANUAL', 'Action Predictive'))->toBe('predictive');
});

test('a manager can finish a Predictive activity from the control panel without touching any WO', function () {
    $pic = pvUser('PIC WWD');
    $this->actingAs($pic)->post(route('predictive.start'), pvStartPayload());
    $wo = pvWo(['pic_user_id' => $pic->id]);

    $this->actingAs(pvUser('KOORDINATOR WWD'))->post(route('today-activity.finish'), [
        'source' => 'PREDICTIVE', 'source_key' => (string) $pic->id, 'pic_user_id' => $pic->id,
    ])->assertSessionHas('success');

    expect(app(ActiveActivityResolver::class)->currentFor($pic->fresh()))->toBeNull()
        ->and($wo->fresh()->pic_user_id)->toBe($pic->id)->and($wo->fresh()->status)->toBe('OPEN');
});
