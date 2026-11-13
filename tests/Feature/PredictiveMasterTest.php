<?php

require_once __DIR__.'/../Support/predictive_helpers.php';

use App\Models\PredictiveActionFinding;
use App\Models\PredictiveActionProblem;
use App\Models\PredictiveFindingOption;
use App\Models\PredictiveProblemOption;
use App\Models\User;
use Illuminate\Http\UploadedFile;

function pvMasterProblem(string $name = 'Bearing aus', string $category = 'VIBRATION', string $area = 'WWD', array $findings = ['Sisi motor']): PredictiveProblemOption
{
    roleAttributes('KOORDINATOR BUL');
    $problem = PredictiveProblemOption::create(['category' => $category, 'area' => $area, 'name' => $name]);
    foreach ($findings as $f) {
        $problem->findings()->create(['name' => $f]);
    }

    return $problem;
}

test('only admin and koordinator reach the master pages and writes', function () {
    $problem = pvMasterProblem();
    $supervisor = User::factory()->create(['role' => User::ROLE_SUPERVISOR]);

    foreach ([pvUser('PIC WWD'), $supervisor, User::factory()->create(['role' => User::ROLE_GUEST])] as $user) {
        $this->actingAs($user)->get(route('predictive.master.index'))->assertForbidden();
        $this->actingAs($user)->post(route('predictive.master.problems.store'), ['category' => 'VIBRATION', 'area' => 'WWD', 'name' => 'X'])->assertForbidden();
        $this->actingAs($user)->delete(route('predictive.master.problems.destroy', $problem))->assertForbidden();
    }
    foreach (['ADMIN', 'KOORDINATOR WWD'] as $role) {
        $this->actingAs(pvUser($role))->get(route('predictive.master.index'))->assertOk()->assertSee('Predictive Problem');
    }
});

test('the master requires login', function () {
    $this->get(route('predictive.master.index'))->assertRedirect(route('login'));
});

test('koordinator adds a problem and a finding for their own area', function () {
    $koordinator = pvUser('KOORDINATOR WWD');

    $this->actingAs($koordinator)->post(route('predictive.master.problems.store'), ['category' => 'VIBRATION', 'area' => 'WWD', 'name' => '  Bearing aus  '])
        ->assertSessionHas('success');
    $problem = PredictiveProblemOption::firstOrFail();
    expect($problem->name)->toBe('Bearing aus')->and($problem->is_active)->toBeTrue();

    $this->actingAs($koordinator)->post(route('predictive.master.findings.store', $problem), ['name' => 'Sisi motor'])->assertSessionHas('success');
    expect($problem->findings()->pluck('name')->all())->toBe(['Sisi motor']);

    $this->actingAs($koordinator)->get(route('predictive.master.index', ['category' => 'VIBRATION']))->assertSee('Bearing aus')->assertSee('Sisi motor');
});

test('a koordinator cannot manage another area, while admin can manage any active area', function () {
    pvUser('KOORDINATOR BUL');
    $bul = pvMasterProblem('Milik BUL', 'VIBRATION', 'BUL');
    $koordinator = pvUser('KOORDINATOR WWD');

    $this->actingAs($koordinator)->post(route('predictive.master.problems.store'), ['category' => 'VIBRATION', 'area' => 'BUL', 'name' => 'Selundup'])->assertForbidden();
    $this->actingAs($koordinator)->put(route('predictive.master.problems.update', $bul), ['name' => 'Ubah'])->assertForbidden();
    $this->actingAs($koordinator)->delete(route('predictive.master.problems.destroy', $bul))->assertForbidden();
    $this->actingAs($koordinator)->post(route('predictive.master.findings.store', $bul), ['name' => 'F'])->assertForbidden();
    expect(PredictiveProblemOption::pluck('name')->all())->toBe(['Milik BUL']);

    $this->actingAs(pvUser('ADMIN'))->post(route('predictive.master.problems.store'), ['category' => 'AIR_LEAKED', 'area' => 'BUL', 'name' => 'Dari admin'])->assertSessionHas('success');
    expect(PredictiveProblemOption::where('name', 'Dari admin')->value('area'))->toBe('BUL');
});

test('koordinator only sees their own area in the list even when asking for another', function () {
    pvUser('KOORDINATOR BUL');
    pvMasterProblem('Milik BUL', 'VIBRATION', 'BUL');
    pvMasterProblem('Milik WWD', 'VIBRATION', 'WWD');

    $this->actingAs(pvUser('KOORDINATOR WWD'))->get(route('predictive.master.index', ['area' => 'BUL']))
        ->assertSee('Milik WWD')->assertDontSee('Milik BUL');
});

test('category and area are validated on create', function () {
    $admin = pvUser('ADMIN');

    $this->actingAs($admin)->post(route('predictive.master.problems.store'), ['category' => 'OTHER', 'area' => 'WWD', 'name' => 'X'])->assertSessionHasErrors('category');
    $this->actingAs($admin)->post(route('predictive.master.problems.store'), ['category' => 'VIBRATION', 'area' => 'NOPE', 'name' => 'X'])->assertForbidden();
    $this->actingAs($admin)->post(route('predictive.master.problems.store'), ['category' => 'VIBRATION', 'area' => 'WWD', 'name' => ''])->assertSessionHasErrors('name');
});

test('duplicate problems and findings are refused ignoring case and spaces, per scope', function () {
    $problem = pvMasterProblem('Bearing aus', 'VIBRATION', 'WWD', ['Sisi motor']);
    $koordinator = pvUser('KOORDINATOR WWD');

    $this->actingAs($koordinator)->post(route('predictive.master.problems.store'), ['category' => 'VIBRATION', 'area' => 'WWD', 'name' => ' bearing AUS '])->assertSessionHas('error');
    $this->actingAs($koordinator)->post(route('predictive.master.findings.store', $problem), ['name' => 'sisi MOTOR'])->assertSessionHas('error');
    expect(PredictiveProblemOption::count())->toBe(1)->and(PredictiveFindingOption::count())->toBe(1);

    // The same wording is fine in another category.
    $this->actingAs($koordinator)->post(route('predictive.master.problems.store'), ['category' => 'AIR_LEAKED', 'area' => 'WWD', 'name' => 'Bearing aus'])->assertSessionHas('success');
    expect(PredictiveProblemOption::count())->toBe(2);
});

test('renaming follows into saved actions and keeps dashboard wording consistent', function () {
    $wo = pvWo(['status' => 'CLOSED']);
    $option = pvOption($wo, 'Bearing aus', ['Sisi motor']);
    $action = pvAddAction($wo, 1, 'ignored', 'ignored');
    $action->problems()->delete();
    $row = $action->problems()->create(['problem_option_id' => $option['problem']->id, 'problem' => 'Bearing aus']);
    $row->findings()->create(['finding_option_id' => $option['findings'][0]->id, 'finding' => 'Sisi motor']);
    $koordinator = pvUser('KOORDINATOR WWD');

    $this->actingAs($koordinator)->put(route('predictive.master.problems.update', $option['problem']), ['name' => 'Bearing rusak'])->assertSessionHas('success');
    $this->actingAs($koordinator)->put(route('predictive.master.findings.update', $option['findings'][0]), ['name' => 'Sisi motor DE'])->assertSessionHas('success');

    expect(PredictiveActionProblem::first()->problem)->toBe('Bearing rusak')
        ->and(PredictiveActionFinding::first()->finding)->toBe('Sisi motor DE');
    $dash = pvDash(pvUser('ADMIN'));
    expect($dash['problems'])->toBe(['Bearing rusak' => 1])->and($dash['findings'])->toBe(['Sisi motor DE' => 1]);
});

test('renaming onto an existing name is refused', function () {
    $a = pvMasterProblem('Satu');
    pvMasterProblem('Dua');

    $this->actingAs(pvUser('KOORDINATOR WWD'))->put(route('predictive.master.problems.update', $a), ['name' => 'dua'])->assertSessionHas('error');
    expect($a->fresh()->name)->toBe('Satu');
});

test('deactivating hides an option from new actions without touching history', function () {
    $wo = pvWo();
    $pic = pvUser('PIC WWD');
    $this->actingAs($pic)->post(route('predictive.claim', $wo));
    $option = pvOption($wo, 'Bearing aus', ['Sisi motor', 'Sisi gearbox']);
    pvOption($wo, 'Tetap aktif', ['Bagian lain']);
    $koordinator = pvUser('KOORDINATOR WWD');

    $this->actingAs($koordinator)->put(route('predictive.master.findings.update', $option['findings'][0]), ['is_active' => 0])->assertSessionHas('success');
    pvClose($this, $pic, $wo, ['raw' => ['problems' => [['problem_id' => $option['problem']->id, 'findings' => [['finding_id' => $option['findings'][0]->id]]]]]])
        ->assertSessionHasErrors('problems.0.findings.0.finding_id');

    $this->actingAs($koordinator)->put(route('predictive.master.findings.update', $option['findings'][0]), ['is_active' => 1]);
    $this->actingAs($koordinator)->put(route('predictive.master.problems.update', $option['problem']), ['is_active' => 0]);
    pvClose($this, $pic, $wo, ['raw' => ['problems' => [['problem_id' => $option['problem']->id, 'findings' => [['finding_id' => $option['findings'][0]->id]]]]]])
        ->assertSessionHasErrors('problems.0.problem_id');
    expect(PredictiveActionProblem::count())->toBe(0);
});

test('an unused entry can be deleted with its findings, a used one only deactivated', function () {
    $free = pvMasterProblem('Belum dipakai', 'VIBRATION', 'WWD', ['A', 'B']);
    $koordinator = pvUser('KOORDINATOR WWD');

    $this->actingAs($koordinator)->delete(route('predictive.master.problems.destroy', $free))->assertSessionHas('success');
    expect(PredictiveProblemOption::count())->toBe(0)->and(PredictiveFindingOption::count())->toBe(0);

    $wo = pvWo();
    $pic = pvUser('PIC WWD');
    $this->actingAs($pic)->post(route('predictive.claim', $wo));
    pvClose($this, $pic, $wo);
    $used = PredictiveProblemOption::firstOrFail();
    $usedFinding = $used->findings()->firstOrFail();

    $this->actingAs($koordinator)->delete(route('predictive.master.findings.destroy', $usedFinding))->assertSessionHas('error');
    $this->actingAs($koordinator)->delete(route('predictive.master.problems.destroy', $used))->assertSessionHas('error');
    expect(PredictiveProblemOption::count())->toBe(1)->and(PredictiveFindingOption::count())->toBe(1);
});

test('action validation only accepts the master of the WO category and area', function () {
    $pic = pvUser('PIC WWD');
    $wo = pvWo();
    $this->actingAs($pic)->post(route('predictive.claim', $wo));
    $good = pvOption($wo, 'Bearing aus', ['Sisi motor', 'Sisi gearbox']);
    $otherCategory = PredictiveProblemOption::create(['category' => 'AIR_LEAKED', 'area' => 'WWD', 'name' => 'Selang bocor']);
    $otherCategoryFinding = $otherCategory->findings()->create(['name' => 'Coupler']);
    $otherArea = PredictiveProblemOption::create(['category' => 'VIBRATION', 'area' => 'BUL', 'name' => 'Milik BUL']);
    $otherAreaFinding = $otherArea->findings()->create(['name' => 'Bagian BUL']);
    $second = pvOption($wo, 'Baut kendor', ['Dudukan']);

    $send = fn (array $problems) => pvClose($this, $pic, $wo, ['raw' => ['problems' => $problems]]);

    $send([['problem_id' => $otherCategory->id, 'findings' => [['finding_id' => $otherCategoryFinding->id]]]])->assertSessionHasErrors('problems.0.problem_id');
    $send([['problem_id' => $otherArea->id, 'findings' => [['finding_id' => $otherAreaFinding->id]]]])->assertSessionHasErrors('problems.0.problem_id');
    $send([['problem_id' => 999999, 'findings' => [['finding_id' => 1]]]])->assertSessionHasErrors('problems.0.problem_id');
    $send([['problem_id' => $good['problem']->id, 'findings' => [['finding_id' => $second['findings'][0]->id]]]])->assertSessionHasErrors('problems.0.findings.0.finding_id');
    $send([['problem_id' => $good['problem']->id, 'findings' => [['finding_id' => $otherAreaFinding->id]]]])->assertSessionHasErrors('problems.0.findings.0.finding_id');
    $send([['problem_id' => 'abc', 'findings' => [['finding_id' => 'x']]]])->assertSessionHasErrors(['problems.0.problem_id', 'problems.0.findings.0.finding_id']);
    expect(PredictiveActionProblem::count())->toBe(0);

    $send([
        ['problem_id' => $good['problem']->id, 'findings' => [['finding_id' => $good['findings'][0]->id], ['finding_id' => $good['findings'][1]->id]]],
        ['problem_id' => $second['problem']->id, 'findings' => [['finding_id' => $second['findings'][0]->id]]],
    ])->assertSessionHasNoErrors();

    $saved = PredictiveActionProblem::with('findings')->orderBy('id')->get();
    expect($saved->pluck('problem')->all())->toBe(['Bearing aus', 'Baut kendor'])
        ->and($saved[0]->problem_option_id)->toBe($good['problem']->id)
        ->and($saved[0]->findings->pluck('finding')->all())->toBe(['Sisi motor', 'Sisi gearbox'])
        ->and($saved[0]->findings[0]->finding_option_id)->toBe($good['findings'][0]->id);
});

test('free text can no longer be used in place of master ids', function () {
    $pic = pvUser('PIC WWD');
    $wo = pvWo();
    $this->actingAs($pic)->post(route('predictive.claim', $wo));
    pvOption($wo, 'Bearing aus', ['Sisi motor']);

    pvClose($this, $pic, $wo, ['raw' => ['problems' => [['problem' => 'Teks bebas', 'findings' => [['finding' => 'Teks bebas']]]]]])
        ->assertSessionHasErrors(['problems.0.problem_id', 'problems.0.findings.0.finding_id']);
    expect(PredictiveActionProblem::count())->toBe(0)->and($wo->fresh()->status)->toBe('OPEN');
});

test('closing is refused with a clear message while the master of that scope is empty', function () {
    $pic = pvUser('PIC WWD');
    $wo = pvWo();
    $this->actingAs($pic)->post(route('predictive.claim', $wo));

    $this->actingAs($pic)->post(route('predictive.action', $wo), ['problems' => [['problem_id' => 1, 'findings' => [['finding_id' => 1]]]]])
        ->assertSessionHasErrors('problems');
    expect(session('errors')->first('problems'))->toContain('is empty')->and($wo->fresh()->status)->toBe('OPEN');
});

test('import loads categories, areas, problems and findings, reusing what exists', function () {
    pvUser('KOORDINATOR BUL');
    $csv = implode("\n", [
        'Category,Area,Problem,Finding',
        'VIBRATION,WWD,Bearing aus,Sisi motor',
        'vibration,wwd,bearing AUS,Sisi gearbox',
        'Air Leaked,WWD,Selang bocor,Quick coupler',
        'VIBRATION,WWD,Baut kendor,',
        'VIBRATION,WWD,Bearing aus,sisi MOTOR',
        'PANAS,WWD,X,Y',
        'VIBRATION,NOPE,X,Y',
        'VIBRATION,WWD,,Y',
        ',,,',
    ]);

    $this->actingAs(pvUser('ADMIN'))->post(route('predictive.master.import'), ['file' => UploadedFile::fake()->createWithContent('m.csv', $csv)])
        ->assertRedirect(route('predictive.master.index'));

    $result = session('predictive_master_import_result');
    expect($result['success'])->toBe(4)->and($result['skipped'])->toBe(1)->and($result['failed'])->toBe(3);
    $reasons = collect($result['details'])->pluck('reason', 'row');
    expect($reasons[6])->toContain('Already in the master')->and($reasons[7])->toContain('Category')->and($reasons[8])->toContain('NOPE');

    expect(PredictiveProblemOption::forScope('VIBRATION', 'WWD')->count())->toBe(2)
        ->and(PredictiveProblemOption::forScope('AIR_LEAKED', 'WWD')->count())->toBe(1)
        ->and(PredictiveProblemOption::forScope('VIBRATION', 'WWD')->where('name', 'Bearing aus')->first()->findings()->pluck('name')->all())->toBe(['Sisi gearbox', 'Sisi motor']);
});

test('a koordinator import is limited to their own area', function () {
    pvUser('KOORDINATOR BUL');

    $this->actingAs(pvUser('KOORDINATOR WWD'))->post(route('predictive.master.import'), ['file' => UploadedFile::fake()->createWithContent('m.csv', "Category,Area,Problem,Finding\nVIBRATION,WWD,Milik WWD,A\nVIBRATION,BUL,Milik BUL,B")]);

    $result = session('predictive_master_import_result');
    expect($result['success'])->toBe(1)->and($result['failed'])->toBe(1)->and($result['details'][0]['reason'])->toContain('di luar area')
        ->and(PredictiveProblemOption::pluck('name')->all())->toBe(['Milik WWD']);
});

test('an import with wrong headers or file type changes nothing', function () {
    $admin = pvUser('ADMIN');

    $this->actingAs($admin)->post(route('predictive.master.import'), ['file' => UploadedFile::fake()->createWithContent('m.csv', "Problem,Finding\nA,B")]);
    expect(session('predictive_master_import_result')['fatal'])->toContain('Required column')->and(PredictiveProblemOption::count())->toBe(0);

    $this->actingAs($admin)->post(route('predictive.master.import'), ['file' => UploadedFile::fake()->create('m.pdf', 10, 'application/pdf')])->assertSessionHasErrors('file');
});

test('the sidebar offers the master only to admin and koordinator', function () {
    $this->actingAs(pvUser('ADMIN'))->get(route('predictive.index'))->assertSee(route('predictive.master.index'), false);
    $this->actingAs(pvUser('KOORDINATOR'))->get(route('predictive.index'))->assertSee(route('predictive.master.index'), false);
    $this->actingAs(pvUser('PIC'))->get(route('predictive.index'))->assertDontSee(route('predictive.master.index'), false);
});
