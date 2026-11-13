<?php

use App\Models\ActivityMonitorClosure;
use App\Models\AiCharacterRun;
use App\Models\AiForecastRecommendation;
use App\Models\AiForecastRun;
use App\Models\Area;
use App\Models\Machine;
use App\Models\PMSchedule;
use App\Models\User;
use App\Services\Vme\Ai\AiAnalysisResult;
use App\Services\Vme\Ai\AiCharacterAnalysis;
use App\Services\Vme\Ai\AiCharacterRegistry;
use App\Services\Vme\Ai\AiContext;
use App\Services\Vme\VmeFeature;
use Illuminate\Support\Facades\DB;

/** A character added only through config + a class: no renderer change needed. */
class VmeAiFakeAnalysis implements AiCharacterAnalysis
{
    public function analyze(AiContext $context): AiAnalysisResult
    {
        return new AiAnalysisResult(AiAnalysisResult::IDLE, 'Fake siap.', ['fake' => true], [['label' => 'x', 'value' => '1']]);
    }
}

beforeEach(function () {
    config(['vme.snapshot_ttl' => 0, 'vme.kill_switch' => false]);
});

function vaOn(array $on = VmeFeature::KEYS): void
{
    Area::firstOrCreate(['name' => 'WWD'], ['slug' => 'wwd', 'is_active' => true]); // one Koordinator per Area
    foreach (VmeFeature::KEYS as $key) {
        VmeFeature::set($key, in_array($key, $on, true));
    }
}

function vaAdmin(): User
{
    return User::factory()->admin()->create(['is_active' => true]);
}

function vaSnap($test, ?User $viewer = null): array
{
    return $test->actingAs($viewer ?? vaAdmin())->getJson('/vme/snapshot')->assertOk()->json();
}

function vaChar(array $snap, string $key): ?array
{
    return collect($snap['ai'])->firstWhere('character_key', $key);
}

function vaRun(string $status, array $over = []): AiForecastRun
{
    return AiForecastRun::create([
        'status' => $status, 'algorithm_version' => 'v1', 'period_type' => 'MONTH', 'period_start' => now()->startOfMonth(),
        'period_end' => now()->endOfMonth(), 'parameters' => [], 'started_at' => now()->subMinutes(3),
        'finished_at' => $status === 'RUNNING' ? null : now(), ...$over,
    ]);
}

function vaPm(string $status, string $due): PMSchedule
{
    $machine = Machine::firstOrCreate(['machine_number' => 'AI-'.uniqid()], ['area' => 'WWD', 'machine_type' => 'NDE', 'status' => 'ACTIVE']);

    return PMSchedule::create([
        'machine_id' => $machine->id, 'machine_number' => $machine->machine_number, 'machine_type' => 'NDE', 'area' => 'WWD',
        'order_number' => 'O'.uniqid(), 'plan_date' => $due, 'plan_month' => 'x', 'plan_year' => '2026', 'due_date' => $due, 'status' => $status,
    ]);
}

// ── Switches, registry ────────────────────────────────────────────────────

test('AI characters are OFF by default and appear when the master switch is ON', function () {
    expect(VmeFeature::all()[VmeFeature::AI_CHARACTERS])->toBeFalse();

    vaOn(['display', 'activity_sync']);
    expect(vaSnap($this)['ai'])->toBe([]);

    vaOn();
    expect(collect(vaSnap($this)['ai'])->pluck('character_key')->all())->toBe(['supervisor', 'coordinator_bul', 'coordinator_wwd', 'sparepart_analyst']);
});

test('a switched-off character does not appear, the others do', function () {
    vaOn();
    VmeFeature::setCharacter('coordinator_wwd', false);

    $keys = collect(vaSnap($this)['ai'])->pluck('character_key')->all();

    expect($keys)->toBe(['supervisor', 'coordinator_bul', 'sparepart_analyst']);

    VmeFeature::setCharacter('coordinator_wwd', true);
    expect(collect(vaSnap($this)['ai'])->pluck('character_key')->all())->toContain('coordinator_wwd');
});

test('the kill switch turns every AI character off too', function () {
    vaOn();
    $admin = vaAdmin();
    expect(vaSnap($this, $admin)['ai'])->not->toBeEmpty();

    config(['vme.kill_switch' => true]);

    $this->actingAs($admin)->getJson('/vme/snapshot')->assertNotFound();
    $this->actingAs($admin)->getJson(route('vme.ai', 'supervisor'))->assertNotFound();
    expect(app(AiCharacterRegistry::class)->enabled())->toBe([]);
});

test('a character added through the registry appears without touching the renderer', function () {
    vaOn();
    config(['vme.ai.characters.test_bot' => [
        'name' => 'Tester', 'role' => 'Hanya untuk tes', 'analysis' => VmeAiFakeAnalysis::class, 'avatar' => 'ai_sparepart',
    ]]);

    $snap = vaSnap($this);
    $bot = vaChar($snap, 'test_bot');

    expect($bot)->toMatchArray(['name' => 'Tester', 'role' => 'Hanya untuk tes', 'status' => 'IDLE', 'summary' => 'Fake siap.', 'source' => 'internal'])
        ->and($bot['avatar_key'])->toBe('ai_sparepart')
        ->and(count($snap['ai']))->toBe(5)
        // its desk is the next free slot, distinct from the others
        ->and(collect($snap['ai'])->pluck('desk')->map(fn ($d) => $d['x'].','.$d['y'])->unique())->toHaveCount(5);
});

test('a broken definition (missing or wrong class) is ignored instead of breaking the map', function () {
    vaOn();
    config(['vme.ai.characters.broken' => ['name' => 'X', 'role' => 'Y', 'analysis' => 'App\\Nope\\Missing']]);
    config(['vme.ai.characters.not_contract' => ['name' => 'X', 'role' => 'Y', 'analysis' => stdClass::class]]);

    expect(collect(vaSnap($this)['ai'])->pluck('character_key')->all())->toBe(['supervisor', 'coordinator_bul', 'coordinator_wwd', 'sparepart_analyst']);
});

test('an analysis that throws shows as an alert and does not break the snapshot', function () {
    vaOn();
    config(['vme.ai.characters.boom' => ['name' => 'Boom', 'role' => 'r', 'analysis' => VmeAiBoomAnalysis::class]]);

    $bot = vaChar(vaSnap($this), 'boom');

    expect($bot['status'])->toBe('ALERT')->and($bot['summary'])->toBe('The analysis could not be run.');
});

class VmeAiBoomAnalysis implements AiCharacterAnalysis
{
    public function analyze(AiContext $context): AiAnalysisResult
    {
        throw new RuntimeException('boom');
    }
}

test('AI characters look like people (a known avatar) and their names carry no "AI" prefix', function () {
    vaOn();
    $admin = vaAdmin();
    $snap = vaSnap($this, $admin);
    $avatars = $this->actingAs($admin)->getJson('/vme/config')->json('avatars');

    expect(collect($snap['ai'])->pluck('name')->all())->toBe(['Supervisor', 'Koordinator BUL', 'Koordinator WWD', 'Sparepart Analyst']);

    foreach ($snap['ai'] as $entry) {
        expect($entry)->not->toHaveKey('robot')
            ->and($avatars)->toHaveKey($entry['avatar_key'])
            ->and($avatars[$entry['avatar_key']])->toHaveKeys(['skin', 'hair', 'shirt', 'pants']);
    }
    expect(collect($snap['ai'])->pluck('avatar_key')->unique())->toHaveCount(4);
});

test('adding AI looks does not reshuffle the avatars PICs get', function () {
    $pics = array_keys(config('vme.avatars'));
    $ai = array_keys(config('vme.ai_avatars'));

    expect($pics)->toBe(['a1', 'a2', 'a3', 'a4', 'a5', 'a6', 'a7', 'a8'])->and(array_intersect($pics, $ai))->toBe([]);
});

test('every status entry follows the uniform contract for a future AI backend', function () {
    vaOn();

    foreach (vaSnap($this)['ai'] as $entry) {
        expect($entry)->toHaveKeys(['character_key', 'status', 'summary', 'updated_at', 'source'])
            ->and($entry['source'])->toBe('internal')
            ->and(AiAnalysisResult::STATUSES)->toContain($entry['status'])
            ->and(strtotime($entry['updated_at']))->not->toBeFalse();
    }
});

// ── Status follows real data ──────────────────────────────────────────────

test('AI Sparepart Analyst is Working while a forecast run is RUNNING', function () {
    vaOn();

    expect(vaChar(vaSnap($this), 'sparepart_analyst')['status'])->toBe('IDLE'); // no run yet

    $run = vaRun('RUNNING');
    $bot = vaChar(vaSnap($this), 'sparepart_analyst');
    expect($bot['status'])->toBe('WORKING')->and($bot['summary'])->toContain("#{$run->id}");

    $run->update(['status' => 'COMPLETED', 'finished_at' => now()]);
    expect(vaChar(vaSnap($this), 'sparepart_analyst')['status'])->toBe('IDLE');
});

test('a FAILED forecast run, or many warnings, make the Sparepart Analyst alert', function () {
    vaOn();
    $failed = vaRun('FAILED', ['error' => 'Data kosong']);
    $bot = vaChar(vaSnap($this), 'sparepart_analyst');
    expect($bot['status'])->toBe('ALERT')->and($bot['summary'])->toContain('Data kosong');

    $failed->delete();
    $ok = vaRun('COMPLETED');
    foreach (range(1, 3) as $i) {
        AiForecastRecommendation::create(['run_id' => $ok->id, 'type' => 'X', 'severity' => 'WARNING', 'message' => "m$i"]);
    }
    expect(vaChar(vaSnap($this), 'sparepart_analyst')['status'])->toBe('ALERT');

    config(['vme.ai.thresholds.sparepart_warnings' => 0]); // alert disabled
    expect(vaChar(vaSnap($this), 'sparepart_analyst')['status'])->toBe('IDLE');
});

test('AI Koordinator alerts when PMs past their due date reach the threshold', function () {
    vaOn();
    config(['vme.ai.thresholds.coordinator_overdue_pm' => 2]);
    vaPm('OPEN', now()->subDays(3)->toDateString());

    expect(vaChar(vaSnap($this), 'coordinator_wwd')['status'])->toBe('IDLE');

    vaPm('OPEN', now()->subDays(9)->toDateString());
    vaPm('FINISHED', now()->subDays(30)->toDateString()); // finished: not overdue
    $bot = vaChar(vaSnap($this), 'coordinator_wwd');

    expect($bot['status'])->toBe('ALERT')->and($bot['summary'])->toContain('2 PM lewat jatuh tempo');
});

test('AI Supervisor summarises the PICs and alerts on activities it cannot attribute', function () {
    vaOn();
    $area = Area::firstOrCreate(['name' => 'WWD'], ['slug' => 'wwd', 'is_active' => true]);
    foreach (['A', 'B', 'C'] as $n) {
        User::factory()->create(['role' => 'PIC', 'name' => "SUP $n", 'area_id' => $area->id, 'is_active' => true]);
    }
    $worker = User::where('name', 'SUP A')->first();
    $worker->update(['oil_audit_started_at' => now()]);

    $bot = vaChar(vaSnap($this), 'supervisor');
    expect($bot['status'])->toBe('IDLE')->and($bot['summary'])->toContain('1 dari 3 PIC sedang bekerja');

    // two accounts with the same name + a PM by that name => cannot be attributed
    User::factory()->create(['role' => 'PIC', 'name' => 'SAMA', 'area_id' => $area->id, 'is_active' => true]);
    $twin = User::factory()->create(['role' => 'PIC', 'name' => 'SAMA', 'area_id' => $area->id, 'is_active' => true]);
    $machine = Machine::create(['machine_number' => 'AMB-1', 'area' => 'WWD', 'machine_type' => 'NDE', 'status' => 'ACTIVE']);
    PMSchedule::create([
        'machine_id' => $machine->id, 'machine_number' => 'AMB-1', 'machine_type' => 'NDE', 'area' => 'WWD', 'order_number' => 'A1',
        'plan_date' => today(), 'plan_month' => 'x', 'plan_year' => '2026', 'due_date' => today()->addDays(10), 'pic' => 'SAMA',
        'status' => 'IN_PROGRESS', 'start_time' => '08:00', 'actual_date' => today(),
    ]);
    $bot = vaChar(vaSnap($this), 'supervisor');
    expect($bot['status'])->toBe('ALERT')->and($bot['summary'])->toContain('tidak bisa dipetakan');
    unset($twin);
});

test('with activity sync OFF the Supervisor says so instead of reporting idle PICs', function () {
    vaOn(['display', 'ai_characters']);
    User::factory()->create(['role' => 'PIC', 'name' => 'OFFLINE', 'is_active' => true]);

    expect(vaChar(vaSnap($this), 'supervisor')['summary'])->toContain('Sinkronisasi aktivitas sedang OFF');
});

// ── Storage ───────────────────────────────────────────────────────────────

test('outputs are stored only when they change, with their source and reference', function () {
    vaOn();
    $run = vaRun('RUNNING');

    vaSnap($this);
    vaSnap($this);
    vaSnap($this);
    expect(AiCharacterRun::where('character_key', 'sparepart_analyst')->count())->toBe(1);

    $row = AiCharacterRun::where('character_key', 'sparepart_analyst')->first();
    expect($row->status)->toBe('WORKING')->and($row->source)->toBe('internal')
        ->and($row->source_ref_type)->toBe('ai_forecast_runs')->and($row->source_ref_id)->toBe($run->id)
        ->and($row->result_hash)->toHaveLength(40)->and($row->result)->not->toBeEmpty()->and($row->checked_at)->not->toBeNull();

    $run->update(['status' => 'COMPLETED', 'finished_at' => now()]);
    vaSnap($this);
    expect(AiCharacterRun::where('character_key', 'sparepart_analyst')->count())->toBe(2)
        ->and(AiCharacterRun::where('character_key', 'sparepart_analyst')->latest('id')->first()->status)->toBe('IDLE');
});

test('nothing is stored while the AI characters are off', function () {
    vaOn(['display', 'activity_sync']);
    vaSnap($this);

    expect(AiCharacterRun::count())->toBe(0);
});

// ── Read-only on everything else ──────────────────────────────────────────

test('the AI characters never change operational tables or start a forecast run', function () {
    vaOn();
    $machine = Machine::create(['machine_number' => 'RO-1', 'area' => 'WWD', 'machine_type' => 'NDE', 'status' => 'ACTIVE']);
    $pic = User::factory()->create(['role' => 'PIC', 'name' => 'RO PIC', 'is_active' => true, 'area_id' => Area::firstOrCreate(['name' => 'WWD'], ['slug' => 'wwd', 'is_active' => true])->id]);
    PMSchedule::create([
        'machine_id' => $machine->id, 'machine_number' => 'RO-1', 'machine_type' => 'NDE', 'area' => 'WWD', 'order_number' => 'RO',
        'plan_date' => today(), 'plan_month' => 'x', 'plan_year' => '2026', 'due_date' => today()->subDays(2), 'pic' => $pic->name,
        'status' => 'IN_PROGRESS', 'start_time' => '08:00', 'actual_date' => today(),
    ]);
    vaRun('COMPLETED');
    $admin = vaAdmin();

    $tables = ['pm_schedules', 'pm_spareparts', 'spareparts', 'mandatory_spareparts', 'greasings', 'oil_audits', 'machines', 'manual_activities',
        'activity_monitor_closures', 'users', 'ai_forecast_runs', 'ai_forecast_results', 'ai_forecast_recommendations', 'ai_forecast_machines'];
    $fingerprint = fn () => collect($tables)->mapWithKeys(fn (string $t) => [$t => [DB::table($t)->count(), md5(DB::table($t)->orderBy('id')->get()->toJson())]])->all();

    $before = $fingerprint();

    foreach (range(1, 3) as $i) {
        $this->actingAs($admin)->getJson('/vme/snapshot')->assertOk();
    }
    foreach (['supervisor', 'coordinator_bul', 'coordinator_wwd', 'sparepart_analyst'] as $key) {
        $this->actingAs($admin)->getJson(route('vme.ai', $key))->assertOk();
    }

    expect($fingerprint())->toBe($before);
});

// ── Panel endpoint ────────────────────────────────────────────────────────

test('the panel returns name, role, status, time, summary and a link to the AI data page', function () {
    vaOn();
    vaRun('RUNNING');

    $json = $this->actingAs(vaAdmin())->getJson(route('vme.ai', 'sparepart_analyst'))->assertOk()->json();

    expect($json['character'])->toMatchArray(['key' => 'sparepart_analyst', 'name' => 'Sparepart Analyst', 'source' => 'internal'])
        ->and($json['character']['role'])->not->toBeEmpty()
        ->and($json['status'])->toBe('WORKING')
        ->and($json['summary'])->toContain('sedang berjalan')
        ->and($json['updated_at'])->not->toBeNull()
        ->and($json['details'])->not->toBeEmpty()
        ->and($json['source_ref']['type'])->toBe('ai_forecast_runs')
        ->and($json['link']['url'])->toBe(route('maintenance-intelligence.vme.ai-runs', ['character' => 'sparepart_analyst']));
});

test('panel access: guests, PIC and GUEST are refused; unknown or disabled characters are 404', function () {
    vaOn();
    $this->getJson(route('vme.ai', 'supervisor'))->assertRedirect(route('login'));

    foreach (['PIC WWD', 'GUEST'] as $role) {
        $u = User::factory()->create([...roleAttributes($role), 'is_active' => true]);
        $this->actingAs($u)->getJson(route('vme.ai', 'supervisor'))->assertForbidden();
    }

    $admin = vaAdmin();
    $this->actingAs($admin)->getJson(route('vme.ai', 'nobody'))->assertNotFound();
    VmeFeature::setCharacter('supervisor', false);
    $this->actingAs($admin)->getJson(route('vme.ai', 'supervisor'))->assertNotFound();
});

test('viewers limited to some areas see the robots and status but not the floor-wide summary', function () {
    vaOn();
    $koordinator = User::factory()->create([...roleAttributes('KOORDINATOR WWD'), 'is_active' => true]);

    $snapBot = vaChar(vaSnap($this, $koordinator), 'supervisor');
    expect($snapBot['status'])->toBeIn(['IDLE', 'ALERT', 'WORKING'])->and($snapBot['summary'])->toBeNull();

    $panel = $this->actingAs($koordinator)->getJson(route('vme.ai', 'supervisor'))->assertOk()->json();
    expect($panel['summary'])->toBeNull()->and($panel['restricted'])->toBeTrue()->and($panel['details'])->toBe([])->and($panel['link'])->toBeNull();

    $supervisor = User::factory()->supervisor()->create(['is_active' => true]); // unrestricted
    expect($this->actingAs($supervisor)->getJson(route('vme.ai', 'supervisor'))->json('summary'))->not->toBeNull();
    expect($this->actingAs($supervisor)->getJson(route('vme.ai', 'supervisor'))->json('link'))->toBeNull(); // data page is admin-only
});

// ── Admin: switches and the data page ─────────────────────────────────────

test('admin switches AI characters from the VME management page, ordinary saves keep them', function () {
    $admin = vaAdmin();

    Area::firstOrCreate(['name' => 'WWD'], ['slug' => 'wwd', 'is_active' => true]);

    $this->actingAs($admin)->put(route('maintenance-intelligence.vme.settings'), ['display' => 1, 'ai_characters' => 1, 'characters_submitted' => 1, 'character_supervisor' => 1])
        ->assertRedirect();
    expect(VmeFeature::all()[VmeFeature::AI_CHARACTERS])->toBeTrue()
        ->and(VmeFeature::characterEnabled('supervisor'))->toBeTrue()
        ->and(VmeFeature::characterEnabled('coordinator_wwd'))->toBeFalse();

    // a save without the character form does not touch per-character switches
    VmeFeature::setCharacter('coordinator_wwd', true);
    $this->actingAs($admin)->put(route('maintenance-intelligence.vme.settings'), ['display' => 1, 'ai_characters' => 1])->assertRedirect();
    expect(VmeFeature::characterEnabled('coordinator_wwd'))->toBeTrue();

    $this->actingAs($admin)->get('/maintenance-intelligence/vme')->assertOk()->assertSee('Koordinator WWD')->assertSee('AI result log');
});

test('the AI data page is admin only and can be filtered by character and date', function () {
    $this->get(route('maintenance-intelligence.vme.ai-runs'))->assertRedirect(route('login'));
    $admin = vaAdmin();
    $old = AiCharacterRun::create(['character_key' => 'supervisor', 'status' => 'IDLE', 'summary' => 'Ringkasan supervisor satu', 'result_hash' => str_repeat('a', 40)]);
    $old->created_at = now()->subDays(5); // not mass-assignable
    $old->save();
    AiCharacterRun::create(['character_key' => 'coordinator', 'status' => 'ALERT', 'summary' => 'Ringkasan koordinator dua', 'result_hash' => str_repeat('b', 40)]);

    $this->actingAs($admin)->get(route('maintenance-intelligence.vme.ai-runs'))->assertOk()
        ->assertSee('Ringkasan supervisor satu')->assertSee('Ringkasan koordinator dua');

    $this->actingAs($admin)->get(route('maintenance-intelligence.vme.ai-runs', ['character' => 'coordinator']))->assertOk()
        ->assertSee('Ringkasan koordinator dua')->assertDontSee('Ringkasan supervisor satu');

    $this->actingAs($admin)->get(route('maintenance-intelligence.vme.ai-runs', ['from' => now()->subDays(2)->toDateString()]))->assertOk()
        ->assertDontSee('Ringkasan supervisor satu')->assertSee('Ringkasan koordinator dua');

    $this->actingAs($admin)->get(route('maintenance-intelligence.vme.ai-runs', ['to' => now()->subDays(2)->toDateString()]))->assertOk()
        ->assertSee('Ringkasan supervisor satu')->assertDontSee('Ringkasan koordinator dua');

    $this->actingAs($admin)->get(route('maintenance-intelligence.vme.ai-runs', ['from' => 'not-a-date']))->assertSessionHasErrors('from');

    foreach (['KOORDINATOR WWD', 'PIC WWD'] as $role) {
        $u = User::factory()->create([...roleAttributes($role), 'is_active' => true]);
        $this->actingAs($u)->get(route('maintenance-intelligence.vme.ai-runs'))->assertForbidden();
    }
    $supervisor = User::factory()->supervisor()->create(['is_active' => true]);
    $this->actingAs($supervisor)->get(route('maintenance-intelligence.vme.ai-runs'))->assertForbidden();
});

test('closing activities from the monitor does not disturb the AI characters', function () {
    vaOn();
    $pic = User::factory()->create(['role' => 'PIC', 'name' => 'TUTUP AI', 'is_active' => true, 'oil_audit_started_at' => now(), 'area_id' => Area::firstOrCreate(['name' => 'WWD'], ['slug' => 'wwd', 'is_active' => true])->id]);
    expect(vaChar(vaSnap($this), 'supervisor')['summary'])->toContain('1 dari 1 PIC sedang bekerja');

    ActivityMonitorClosure::create(['source' => 'OIL_AUDIT', 'source_key' => (string) $pic->id, 'business_date' => today(), 'pic_user_id' => $pic->id, 'closed_by_user_id' => $pic->id, 'closed_at' => now()]);

    expect(vaChar(vaSnap($this), 'supervisor')['summary'])->toContain('0 dari 1 PIC sedang bekerja');
});

// ── One Koordinator per Area ──────────────────────────────────────────────

test('there is one Koordinator per active Area, each tied to its own Area', function () {
    vaOn();
    $bul = Area::where('name', 'BUL')->first();
    $wwd = Area::where('name', 'WWD')->first();

    $snap = vaSnap($this);
    $bulBot = vaChar($snap, 'coordinator_bul');
    $wwdBot = vaChar($snap, 'coordinator_wwd');

    expect($bulBot['name'])->toBe('Koordinator BUL')->and($bulBot['role'])->toContain('BUL')
        ->and($wwdBot['name'])->toBe('Koordinator WWD')
        ->and($bulBot['area_id'])->toBe($bul->id)->and($bulBot['visit_zones'])->toBe(['area:'.$bul->id])
        ->and($wwdBot['visit_zones'])->toBe(['area:'.$wwd->id])
        ->and($bulBot['avatar_key'])->not->toBe($wwdBot['avatar_key']);

    // the others are not tied to an Area and may visit anywhere
    expect(vaChar($snap, 'supervisor')['visit_zones'])->toBeNull()->and(vaChar($snap, 'supervisor')['area_id'])->toBeNull()
        ->and(vaChar($snap, 'sparepart_analyst')['visit_zones'])->toBeNull();
});

test('a new Area gets its Koordinator without code, an inactive one loses it', function () {
    vaOn();
    expect(collect(vaSnap($this)['ai'])->pluck('character_key')->all())->not->toContain('coordinator_gripper');

    $area = Area::create(['name' => 'GRIPPER', 'slug' => 'gripper', 'is_active' => true]);
    expect(vaChar(vaSnap($this), 'coordinator_gripper')['name'])->toBe('Koordinator GRIPPER');

    $area->update(['is_active' => false]);
    expect(vaChar(vaSnap($this), 'coordinator_gripper'))->toBeNull();
});

test('each Koordinator only counts the PMs of its own Area', function () {
    vaOn();
    config(['vme.ai.thresholds.coordinator_overdue_pm' => 2]);
    $late = now()->subDays(4)->toDateString();
    foreach ([1, 2, 3] as $i) {
        vaPm('OPEN', $late); // WWD
    }
    $bulMachine = Machine::create(['machine_number' => 'BUL-AI', 'area' => 'BUL', 'machine_type' => 'NDE', 'status' => 'ACTIVE']);
    PMSchedule::create(['machine_id' => $bulMachine->id, 'machine_number' => 'BUL-AI', 'machine_type' => 'NDE', 'area' => 'BUL', 'order_number' => 'B1',
        'plan_date' => $late, 'plan_month' => 'x', 'plan_year' => '2026', 'due_date' => $late, 'status' => 'OPEN']);

    $snap = vaSnap($this);

    expect(vaChar($snap, 'coordinator_wwd')['status'])->toBe('ALERT')->and(vaChar($snap, 'coordinator_wwd')['summary'])->toContain('3 PM lewat jatuh tempo')
        ->and(vaChar($snap, 'coordinator_bul')['status'])->toBe('IDLE')->and(vaChar($snap, 'coordinator_bul')['summary'])->toContain('1 PM lewat jatuh tempo');
});

test('the Office grows a second row of desks when there are more than four characters', function () {
    vaOn();
    $zone = fn () => collect($this->actingAs(vaAdmin())->getJson('/vme/config')->json('layout.zones'))->firstWhere('key', 'office');

    $few = vaSnap($this)['ai']; // supervisor + 2 coordinators + sparepart = 4
    $oneRow = $zone();
    expect(collect($few)->pluck('desk.y')->unique())->toHaveCount(1);

    foreach (['A1', 'A2', 'A3'] as $name) {
        Area::create(['name' => $name, 'slug' => strtolower($name), 'is_active' => true]);
    }
    $many = vaSnap($this)['ai']; // 7 characters
    $twoRows = $zone();

    expect(count($many))->toBe(7)
        ->and(collect($many)->pluck('desk.y')->unique())->toHaveCount(2)
        ->and($twoRows['h'])->toBeGreaterThan($oneRow['h'])
        ->and(collect($many)->map(fn ($c) => $c['desk']['x'].','.$c['desk']['y'])->unique())->toHaveCount(7);

    foreach ($many as $c) {
        expect($c['desk']['x'])->toBeGreaterThanOrEqual($twoRows['x'])->toBeLessThanOrEqual($twoRows['x'] + $twoRows['w'])
            ->and($c['desk']['y'])->toBeGreaterThanOrEqual($twoRows['y'])->toBeLessThanOrEqual($twoRows['y'] + $twoRows['h']);
    }
});

test('the random wander timing and the spots to visit are served from config', function () {
    vaOn();
    config(['vme.ai.wander_min_seconds' => 2, 'vme.ai.wander_max_seconds' => 7]);

    $ai = $this->actingAs(vaAdmin())->getJson('/vme/config')->assertOk()->json('ai');

    expect($ai['wander_min_seconds'])->toBe(2)->and($ai['wander_max_seconds'])->toBe(7)
        ->and($ai['visit_spots'])->toHaveKeys(['production', 'workshop'])
        ->and($ai['wander_zone_types'])->toBe(['production', 'workshop']);

    foreach ($ai['visit_spots'] as $spots) {
        foreach ($spots as [$fx, $fy]) {
            expect($fx)->toBeGreaterThan(0)->toBeLessThan(1)->and($fy)->toBeGreaterThan(0)->toBeLessThan(1);
        }
    }
});
