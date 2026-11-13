<?php

require_once __DIR__.'/../Support/predictive_helpers.php';

use App\Exceptions\PredictiveWorkflowException;
use App\Models\PredictiveAction;
use App\Models\PredictiveActionProblem;
use App\Models\PredictiveProblemOption;
use App\Models\PredictiveReopen;
use App\Models\PredictiveWorkOrder;
use App\Services\Predictive\PredictiveWorkOrderService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('a PIC claims an open WO of their area and is recorded as PIC', function () {
    $pic = pvUser('PIC WWD');
    $wo = pvWo();

    $this->actingAs($pic)->post(route('predictive.claim', $wo))->assertSessionHas('success');

    $wo->refresh();
    expect($wo->pic_user_id)->toBe($pic->id)
        ->and($wo->claimed_at)->not->toBeNull()
        ->and($wo->status)->toBe('OPEN');
});

test('a WO claimed by one PIC cannot be taken by another', function () {
    $first = pvUser('PIC WWD');
    $second = pvUser('PIC WWD');
    $wo = pvWo();

    $this->actingAs($first)->post(route('predictive.claim', $wo));
    $this->actingAs($second)->post(route('predictive.claim', $wo))->assertSessionHas('error');

    expect($wo->fresh()->pic_user_id)->toBe($first->id);
});

test('two concurrent claims on a stale copy of the WO produce exactly one winner', function () {
    $a = pvUser('PIC WWD');
    $b = pvUser('PIC WWD');
    $wo = pvWo();

    // Both requests loaded the WO while it was still unclaimed.
    $copyA = PredictiveWorkOrder::find($wo->id);
    $copyB = PredictiveWorkOrder::find($wo->id);

    $service = app(PredictiveWorkOrderService::class);
    $service->claim($copyA, $a);

    expect(fn () => $service->claim($copyB, $b))->toThrow(PredictiveWorkflowException::class);
    expect($wo->fresh()->pic_user_id)->toBe($a->id);
});

test('claim is limited to PICs of the WO area', function () {
    $bulWo = pvWo(['machine' => pvMachine('B1', 'BUL')]);
    $wo = pvWo();

    $this->actingAs(pvUser('PIC WWD'))->post(route('predictive.claim', $bulWo))->assertForbidden();
    expect($bulWo->fresh()->pic_user_id)->toBeNull();

    foreach (['KOORDINATOR WWD', 'ADMIN'] as $role) {
        $this->actingAs(pvUser($role))->post(route('predictive.claim', $wo))->assertForbidden();
    }
    expect($wo->fresh()->pic_user_id)->toBeNull();
});

test('a closed WO cannot be claimed', function () {
    $wo = pvWo(['status' => 'CLOSED']);

    $this->actingAs(pvUser('PIC WWD'))->post(route('predictive.claim', $wo))->assertSessionHas('error');
    expect($wo->fresh()->pic_user_id)->toBeNull();
});

test('closing saves Action 1, closes the WO and releases the claim', function () {
    $pic = pvUser('PIC WWD');
    $wo = pvWo();
    $this->actingAs($pic)->post(route('predictive.claim', $wo));

    pvClose($this, $pic, $wo)->assertSessionHas('success');

    $wo->refresh();
    $action = PredictiveAction::firstOrFail();
    expect($wo->status)->toBe('CLOSED')
        ->and($wo->pic_user_id)->toBeNull()
        ->and($wo->closed_at)->not->toBeNull()
        ->and($action->action_number)->toBe(1)
        ->and($action->problems)->toHaveCount(1)
        ->and($action->problems[0]->problem)->toBe('Getaran berlebih')
        ->and($action->problems[0]->findings->pluck('finding')->all())->toBe(['Bearing aus'])
        ->and($action->remarks)->toBe('Jadwalkan ganti bearing')
        ->and($action->pic_user_id)->toBe($pic->id)
        ->and($action->pic_name)->toBe($pic->name)
        ->and($action->actioned_at)->not->toBeNull();
});

test('only the claiming PIC can save an action', function () {
    $owner = pvUser('PIC WWD');
    $other = pvUser('PIC WWD');
    $wo = pvWo();
    $this->actingAs($owner)->post(route('predictive.claim', $wo));

    pvClose($this, $other, $wo)->assertForbidden();
    pvClose($this, pvUser('KOORDINATOR WWD'), $wo)->assertForbidden();
    pvClose($this, pvUser('ADMIN'), $wo)->assertForbidden();

    expect(PredictiveAction::count())->toBe(0)->and($wo->fresh()->status)->toBe('OPEN');
});

test('an unclaimed WO cannot receive an action', function () {
    $wo = pvWo();

    pvClose($this, pvUser('PIC WWD'), $wo)->assertForbidden();
    expect(PredictiveAction::count())->toBe(0);
});

test('a PIC from another area cannot act on a WO even if the id is forged', function () {
    $wo = pvWo(['machine' => pvMachine('B1', 'BUL')]);
    $wo->update(['pic_user_id' => ($intruder = pvUser('PIC WWD'))->id]);

    pvClose($this, $intruder, $wo)->assertForbidden();
    expect(PredictiveAction::count())->toBe(0);
});

test('action form validates its fields', function () {
    $pic = pvUser('PIC WWD');
    $wo = pvWo();
    $this->actingAs($pic)->post(route('predictive.claim', $wo));

    pvClose($this, $pic, $wo, ['raw' => ['problems' => [['problem_id' => '', 'findings' => [['finding_id' => '']]]]]])
        ->assertSessionHasErrors(['problems.0.problem_id', 'problems.0.findings.0.finding_id']);
    pvClose($this, $pic, $wo, ['problems' => []])->assertSessionHasErrors('problems');
    pvClose($this, $pic, $wo, ['remarks' => str_repeat('x', 2001)])->assertSessionHasErrors('remarks');
    pvClose($this, $pic, $wo, ['remarks' => null])->assertSessionHasNoErrors();

    expect(PredictiveAction::first()->remarks)->toBeNull();
});

test('a second submit of the same action is refused and creates no duplicate', function () {
    $pic = pvUser('PIC WWD');
    $wo = pvWo();
    $this->actingAs($pic)->post(route('predictive.claim', $wo));

    pvClose($this, $pic, $wo)->assertSessionHas('success');
    pvClose($this, $pic, $wo)->assertSessionHas('error');

    expect(PredictiveAction::count())->toBe(1);
});

test('only admin or the koordinator of the area can reopen, and only a closed WO', function () {
    $pic = pvUser('PIC WWD');
    $wo = pvWo();
    $this->actingAs($pic)->post(route('predictive.claim', $wo));
    pvClose($this, $pic, $wo);

    foreach (['PIC WWD', 'KOORDINATOR BUL'] as $role) {
        $this->actingAs(pvUser($role))->post(route('predictive.reopen', $wo))->assertForbidden();
    }
    expect($wo->fresh()->status)->toBe('CLOSED')->and(PredictiveReopen::count())->toBe(0);

    $open = pvWo();
    $this->actingAs(pvUser('KOORDINATOR WWD'))->post(route('predictive.reopen', $open))->assertSessionHas('error');
    expect(PredictiveReopen::count())->toBe(0);
});

test('admin can reopen a closed WO of any area', function () {
    $admin = pvUser('ADMIN');
    $wo = pvWo(['status' => 'CLOSED', 'machine' => pvMachine('B1', 'BUL')]);

    $this->actingAs($admin)->post(route('predictive.reopen', $wo))->assertSessionHas('success');

    expect($wo->fresh()->status)->toBe('OPEN')->and(PredictiveReopen::first()->user_id)->toBe($admin->id);
});

test('reopen returns the WO to open, keeps history and logs the koordinator', function () {
    $pic = pvUser('PIC WWD');
    $koordinator = pvUser('KOORDINATOR WWD');
    $wo = pvWo();
    $this->actingAs($pic)->post(route('predictive.claim', $wo));
    pvClose($this, $pic, $wo);

    $this->actingAs($koordinator)->post(route('predictive.reopen', $wo))->assertSessionHas('success');

    $wo->refresh();
    $reopen = PredictiveReopen::firstOrFail();
    expect($wo->status)->toBe('OPEN')
        ->and($wo->pic_user_id)->toBeNull()
        ->and($wo->closed_at)->toBeNull()
        ->and(PredictiveWorkOrder::count())->toBe(1)
        ->and(PredictiveAction::count())->toBe(1)
        ->and($reopen->user_id)->toBe($koordinator->id)
        ->and($reopen->user_name)->toBe($koordinator->name)
        ->and($reopen->reopened_at)->not->toBeNull();

    // Back in the pool for any PIC.
    $this->actingAs(pvUser('PIC WWD'))->post(route('predictive.claim', $wo))->assertSessionHas('success');
});

test('repeated close and reopen cycles number actions 1, 2, 3 and never overwrite history', function () {
    $pic = pvUser('PIC WWD');
    $koordinator = pvUser('KOORDINATOR WWD');
    $wo = pvWo();

    foreach ([1, 2, 3] as $n) {
        $this->actingAs($pic)->post(route('predictive.claim', $wo));
        pvClose($this, $pic, $wo, ['finding' => "Finding {$n}"]);
        if ($n < 3) {
            $this->actingAs($koordinator)->post(route('predictive.reopen', $wo));
        }
    }

    expect(PredictiveAction::orderBy('action_number')->with('problems.findings')->get()->mapWithKeys(fn ($a) => [$a->action_number => $a->problems[0]->findings[0]->finding])->all())
        ->toBe([1 => 'Finding 1', 2 => 'Finding 2', 3 => 'Finding 3'])
        ->and(PredictiveReopen::count())->toBe(2)
        ->and($wo->fresh()->status)->toBe('CLOSED');
});

test('action number is unique per WO but may repeat across WOs', function () {
    $one = pvWo();
    $two = pvWo();
    $base = ['pic_name' => 'x', 'actioned_at' => now()];

    PredictiveAction::create($base + ['work_order_id' => $one->id, 'action_number' => 1]);
    PredictiveAction::create($base + ['work_order_id' => $two->id, 'action_number' => 1]);

    expect(fn () => PredictiveAction::create($base + ['work_order_id' => $one->id, 'action_number' => 1]))
        ->toThrow(UniqueConstraintViolationException::class);
});

test('a failed close leaves the WO open and unclosed (all-or-nothing)', function () {
    $pic = pvUser('PIC WWD');
    $wo = pvWo();
    app(PredictiveWorkOrderService::class)->claim($wo, $pic);

    // A malformed problem (no findings key) blows up after the action row was already written.
    expect(fn () => app(PredictiveWorkOrderService::class)->close($wo, $pic, ['problems' => [['problem' => 'x']]]))
        ->toThrow(ErrorException::class);

    $wo->refresh();
    expect($wo->status)->toBe('OPEN')->and($wo->pic_user_id)->toBe($pic->id)->and(PredictiveAction::count())->toBe(0)->and(PredictiveActionProblem::count())->toBe(0);
});

test('detail page lists every action and reopen in order, and shows role based buttons', function () {
    $pic = pvUser('PIC WWD');
    $koordinator = pvUser('KOORDINATOR WWD');
    $koordinator->update(['name' => 'Budi Koordinator']);
    $wo = pvWo();
    $this->actingAs($pic)->post(route('predictive.claim', $wo));
    pvClose($this, $pic, $wo, ['finding' => 'Temuan Pertama']);
    $this->actingAs($koordinator)->post(route('predictive.reopen', $wo));
    $this->actingAs($pic)->post(route('predictive.claim', $wo));
    pvClose($this, $pic, $wo, ['finding' => 'Temuan Kedua']);

    $this->actingAs($koordinator)->get(route('predictive.show', $wo))
        ->assertOk()
        ->assertSeeInOrder(['Action #1', 'Temuan Pertama', 'Reopen', 'Budi Koordinator', 'Action #2', 'Temuan Kedua'])
        ->assertSee('Reopen');

    $this->actingAs($pic)->get(route('predictive.show', $wo))->assertOk()->assertDontSee('onsubmit="return confirm(\'Reopen', false);
});

test('list renders claim, action and reopen controls only to those who may use them', function () {
    $pic = pvUser('PIC WWD');
    $wo = pvWo(['order_number' => 'WO-CTRL']);

    $this->actingAs($pic)->get(route('predictive.index'))->assertSee('Claim WO')->assertDontSee('Action / Closing');
    $this->actingAs($pic)->post(route('predictive.claim', $wo));
    $this->actingAs($pic)->get(route('predictive.index'))->assertSee('Action / Closing')->assertDontSee('Claim WO');
    $this->actingAs(pvUser('PIC WWD'))->get(route('predictive.index'))->assertDontSee('Action / Closing')->assertDontSee('Claim WO');
});

test('one action saves several problems each with several findings, in order', function () {
    $pic = pvUser('PIC WWD');
    $wo = pvWo();
    $this->actingAs($pic)->post(route('predictive.claim', $wo));

    pvClose($this, $pic, $wo, ['problems' => [
        ['problem' => 'Bearing aus', 'findings' => [['finding' => 'Sisi motor'], ['finding' => 'Sisi gearbox']]],
        ['problem' => 'Baut kendor', 'findings' => [['finding' => 'Dudukan']]],
    ]])->assertSessionHasNoErrors();

    $action = PredictiveAction::with('problems.findings')->firstOrFail();
    expect($action->problems->pluck('problem')->all())->toBe(['Bearing aus', 'Baut kendor'])
        ->and($action->problems[0]->findings->pluck('finding')->all())->toBe(['Sisi motor', 'Sisi gearbox'])
        ->and($action->problems[1]->findings->pluck('finding')->all())->toBe(['Dudukan'])
        ->and($wo->fresh()->status)->toBe('CLOSED');
});

test('a problem may appear only once per action and a finding only once per problem', function () {
    $pic = pvUser('PIC WWD');
    $wo = pvWo();
    $this->actingAs($pic)->post(route('predictive.claim', $wo));

    pvClose($this, $pic, $wo, ['problems' => [
        ['problem' => 'Bearing aus', 'findings' => [['finding' => 'A']]],
        ['problem' => 'Bearing aus', 'findings' => [['finding' => 'B']]],
    ]])->assertSessionHasErrors('problems');

    pvClose($this, $pic, $wo, ['problems' => [
        ['problem' => 'Bearing aus', 'findings' => [['finding' => 'Sisi motor'], ['finding' => 'Sisi motor']]],
    ]])->assertSessionHasErrors('problems.0.findings');

    // The same finding under two different problems is fine.
    pvClose($this, $pic, $wo, ['problems' => [
        ['problem' => 'P1', 'findings' => [['finding' => 'Sisi motor']]],
        ['problem' => 'P2', 'findings' => [['finding' => 'Sisi motor']]],
    ]])->assertSessionHasNoErrors();

    expect(PredictiveAction::count())->toBe(1);
});

test('every problem needs at least one finding', function () {
    $pic = pvUser('PIC WWD');
    $wo = pvWo();
    $this->actingAs($pic)->post(route('predictive.claim', $wo));

    pvClose($this, $pic, $wo, ['problems' => [['problem' => 'Bearing aus', 'findings' => []]]])->assertSessionHasErrors('problems.0.findings');
    pvClose($this, $pic, $wo, ['problems' => [['problem' => 'Bearing aus']]])->assertSessionHasErrors('problems.0.findings');
    expect(PredictiveAction::count())->toBe(0)->and($wo->fresh()->status)->toBe('OPEN');
});

test('a failed submit keeps the entered problems and findings and reopens the dialog', function () {
    $pic = pvUser('PIC WWD');
    $wo = pvWo();
    $this->actingAs($pic)->post(route('predictive.claim', $wo));
    $dobel = pvOption($wo, 'Dobel', ['x', 'y']);

    $this->actingAs($pic)->from(route('predictive.show', $wo))->followingRedirects()
        ->post(route('predictive.action', $wo), ['_action_wo' => $wo->id, 'remarks' => 'Catatan saya', 'problems' => [
            ['problem_id' => $dobel['problem']->id, 'findings' => [['finding_id' => $dobel['findings'][0]->id]]],
            ['problem_id' => $dobel['problem']->id, 'findings' => [['finding_id' => $dobel['findings'][1]->id]]],
        ]])
        ->assertSee('Dobel')->assertSee('Catatan saya')->assertSee('may be selected only once');
});

test('the action form offers only the master of the WO category and area, and only to PICs', function () {
    $pic = pvUser('PIC WWD');
    $vib = pvWo(['order_number' => 'OPEN-VIB']);
    pvOption($vib, 'Bearing aus', ['Sisi motor']);
    $air = pvWo(['order_number' => 'OPEN-AIR', 'category' => 'AIR_LEAKED']);
    pvOption($air, 'Selang bocor', ['Quick coupler']);
    $bul = pvWo(['machine' => pvMachine('B1', 'BUL')]);
    pvOption($bul, 'Rahasia BUL', ['Bagian BUL']);

    $page = $this->actingAs($pic)->get(route('predictive.index', ['tab' => 'vibration']));
    $page->assertSee('Bearing aus')->assertSee('Sisi motor')->assertDontSee('Selang bocor')->assertDontSee('Rahasia BUL');
    $this->actingAs($pic)->get(route('predictive.index', ['tab' => 'air-leaked']))->assertSee('Selang bocor')->assertDontSee('Bearing aus');
    $this->actingAs(pvUser('ADMIN'))->get(route('predictive.index'))->assertDontSee('Bearing aus');
});

test('a problem without any active finding is not offered, and an empty master shows a clear notice', function () {
    $pic = pvUser('PIC WWD');
    $wo = pvWo();
    PredictiveProblemOption::create(['category' => 'VIBRATION', 'area' => 'WWD', 'name' => 'Tanpa finding']);

    $this->actingAs($pic)->get(route('predictive.show', $wo))->assertDontSee('Tanpa finding')->assertSee('is empty');
});

test('legacy single finding and problem columns are copied into the new tables by the migration', function () {
    $wo = pvWo(['status' => 'CLOSED']);
    $id = DB::table('predictive_actions')->insertGetId([
        'work_order_id' => $wo->id, 'action_number' => 1, 'finding' => 'Lama F', 'problem' => 'Lama P',
        'pic_name' => 'x', 'actioned_at' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('predictive_action_problems')->delete();

    $migration = require base_path('database/migrations/2026_11_10_000000_add_problems_and_findings_to_predictive_actions.php');
    Schema::drop('predictive_action_findings');
    Schema::drop('predictive_action_problems');
    $migration->up();

    $action = PredictiveAction::with('problems.findings')->find($id);
    expect($action->problems->pluck('problem')->all())->toBe(['Lama P'])
        ->and($action->problems[0]->findings->pluck('finding')->all())->toBe(['Lama F']);
});
