<?php

use App\Models\AiCharacterRun;
use App\Models\Area;
use App\Models\Machine;
use App\Models\ManualActivity;
use App\Models\PMSchedule;
use App\Models\User;
use App\Services\Vme\VmeFeature;

beforeEach(function () {
    config(['vme.snapshot_ttl' => 0, 'vme.kill_switch' => false]);
});

function vfOn(): void
{
    foreach (VmeFeature::KEYS as $key) {
        VmeFeature::set($key, true);
    }
}

function vfAdmin(): User
{
    return User::factory()->admin()->create(['is_active' => true]);
}

/** A PIC working on a PM at a machine in the given Area (WWD / BUL exist already). */
function vfWorker(string $areaName, string $name): array
{
    $area = Area::where('name', $areaName)->first();
    $pic = User::factory()->create(['role' => 'PIC', 'name' => $name, 'area_id' => $area->id, 'is_active' => true]);
    $machine = Machine::create(['machine_number' => 'M-'.strtoupper(substr($name, 0, 6)), 'area' => $areaName, 'machine_type' => 'NDE', 'status' => 'ACTIVE']);
    PMSchedule::create([
        'machine_id' => $machine->id, 'machine_number' => $machine->machine_number, 'machine_type' => 'NDE', 'area' => $areaName, 'order_number' => 'O-'.$name,
        'plan_date' => today(), 'plan_month' => 'x', 'plan_year' => '2026', 'due_date' => today()->addDays(10), 'pic' => $name,
        'status' => 'IN_PROGRESS', 'start_time' => '08:00', 'actual_date' => today(),
    ]);

    return [$pic, $machine];
}

test('the filter shows the chosen Area next to Office and Workshop, and nothing of the other Areas', function () {
    vfOn();
    [$wwdPic, $wwdMachine] = vfWorker('WWD', 'WWD ORANG');
    [$bulPic, $bulMachine] = vfWorker('BUL', 'BUL ORANG');
    $admin = vfAdmin();

    $all = $this->actingAs($admin)->getJson('/vme/config')->json('layout');
    $only = $this->actingAs($admin)->getJson('/vme/config?area=wwd')->assertOk()->json('layout');

    expect(collect($all['zones'])->pluck('key')->all())->toContain('area:'.$wwdPic->area_id, 'area:'.$bulPic->area_id)
        ->and(collect($only['zones'])->pluck('key')->all())->toBe(['office', 'workshop', 'area:'.$wwdPic->area_id])
        ->and($only['world']['width'])->toBeLessThanOrEqual($all['world']['width']);

    $snap = $this->actingAs($admin)->getJson('/vme/snapshot?area=wwd')->assertOk()->json();

    expect(collect($snap['pics'])->pluck('name')->all())->toBe(['WWD ORANG'])
        ->and(collect($snap['machines'])->pluck('number')->all())->toBe([$wwdMachine->machine_number])
        ->and(json_encode($snap))->not->toContain('BUL ORANG')->not->toContain($bulMachine->machine_number);
});

test('every PIC target of a filtered map lies inside the filtered layout', function () {
    vfOn();
    [$pic] = vfWorker('BUL', 'DI BUL');
    vfWorker('WWD', 'DI WWD');
    $admin = vfAdmin();

    $config = $this->actingAs($admin)->getJson('/vme/config?area=bul')->json('layout');
    $snap = $this->actingAs($admin)->getJson('/vme/snapshot?area=bul')->json();
    $zone = collect($config['zones'])->firstWhere('key', 'area:'.$pic->area_id);
    $entry = collect($snap['pics'])->firstWhere('user_id', $pic->id);

    expect($entry['zone'])->toBe('area:'.$pic->area_id)
        ->and($entry['target']['x'])->toBeGreaterThanOrEqual($zone['x'])->toBeLessThanOrEqual($zone['x'] + $zone['w'])
        ->and($entry['target']['y'])->toBeGreaterThanOrEqual($zone['y'])->toBeLessThanOrEqual($zone['y'] + $zone['h'])
        ->and($entry['base']['x'])->toBeGreaterThanOrEqual($zone['x'])->toBeLessThanOrEqual($zone['x'] + $zone['w']);
});

test('the AI characters of a filtered map are the shared ones plus that Area\'s Koordinator, still analysing the whole floor', function () {
    vfOn();
    vfWorker('WWD', 'A');
    vfWorker('BUL', 'B');
    $admin = vfAdmin();

    $all = $this->actingAs($admin)->getJson('/vme/snapshot')->json('ai');
    $wwd = $this->actingAs($admin)->getJson('/vme/snapshot?area=wwd')->json('ai');

    expect(collect($wwd)->pluck('character_key')->all())->toBe(['supervisor', 'coordinator_wwd', 'sparepart_analyst'])
        ->and(collect($wwd)->pluck('desk')->map(fn ($d) => $d['y'])->unique())->toHaveCount(1);

    // the Supervisor still reports the whole floor (2 PICs), not just the chosen Area
    $supervisor = fn (array $list) => collect($list)->firstWhere('character_key', 'supervisor')['summary'];
    expect($supervisor($wwd))->toBe($supervisor($all))->and($supervisor($wwd))->toContain('2 PIC');
});

test('filtering never writes extra AI results', function () {
    vfOn();
    vfWorker('WWD', 'A');
    $admin = vfAdmin();

    $this->actingAs($admin)->getJson('/vme/snapshot')->assertOk();
    $rows = AiCharacterRun::count();
    $hashes = AiCharacterRun::pluck('result_hash')->sort()->values()->all();

    foreach (['wwd', 'bul', 'wwd'] as $slug) {
        $this->actingAs($admin)->getJson('/vme/snapshot?area='.$slug)->assertOk();
    }

    expect(AiCharacterRun::count())->toBe($rows)->and(AiCharacterRun::pluck('result_hash')->sort()->values()->all())->toBe($hashes);
});

test('an unknown Area is 404, and an Area outside the viewer scope is 403', function () {
    vfOn();
    $koordinator = User::factory()->create([...roleAttributes('KOORDINATOR WWD'), 'is_active' => true]);
    $admin = vfAdmin();

    $this->actingAs($admin)->getJson('/vme/snapshot?area=nowhere')->assertNotFound();
    $this->actingAs($admin)->getJson('/vme/config?area=nowhere')->assertNotFound();

    $this->actingAs($koordinator)->getJson('/vme/snapshot?area=wwd')->assertOk();
    $this->actingAs($koordinator)->getJson('/vme/config?area=wwd')->assertOk();
    $this->actingAs($koordinator)->getJson('/vme/snapshot?area=bul')->assertForbidden();
    $this->actingAs($koordinator)->getJson('/vme/config?area=bul')->assertForbidden();
    $this->actingAs($koordinator)->get('/vme?area=bul')->assertForbidden();

    $supervisor = User::factory()->supervisor()->create(['is_active' => true]);
    $supervisor->areas()->attach(Area::where('name', 'WWD')->value('id'));
    $supervisor = $supervisor->fresh();
    $this->actingAs($supervisor)->getJson('/vme/snapshot?area=wwd')->assertOk();
    $this->actingAs($supervisor)->getJson('/vme/snapshot?area=bul')->assertForbidden();
});

test('an inactive Area cannot be chosen', function () {
    vfOn();
    $area = Area::create(['name' => 'OLD', 'slug' => 'old', 'is_active' => false]);

    $this->actingAs(vfAdmin())->getJson('/vme/snapshot?area='.$area->slug)->assertNotFound();
});

test('the page offers the Areas the viewer may see and preselects the chosen one', function () {
    vfOn();
    $this->withoutVite();
    $admin = vfAdmin();

    $page = $this->actingAs($admin)->get('/vme?area=wwd')->assertOk();
    $page->assertSee('id="vme-area"', false)->assertSee('All areas')->assertSee('value="bul"', false)->assertSee('value="wwd" selected', false);

    $koordinator = User::factory()->create([...roleAttributes('KOORDINATOR WWD'), 'is_active' => true]);
    $own = $this->actingAs($koordinator)->get('/vme')->assertOk();
    $own->assertSee('value="wwd"', false)->assertDontSee('value="bul"', false);
});

test('filtered and unfiltered snapshots have different versions (ETag) and cache separately', function () {
    vfOn();
    config(['vme.snapshot_ttl' => 30]);
    vfWorker('WWD', 'A');
    vfWorker('BUL', 'B');
    $admin = vfAdmin();

    $all = $this->actingAs($admin)->getJson('/vme/snapshot');
    $only = $this->actingAs($admin)->getJson('/vme/snapshot?area=wwd');

    expect($all->headers->get('ETag'))->not->toBe($only->headers->get('ETag'))
        ->and(count($all->json('pics')))->toBe(2)->and(count($only->json('pics')))->toBe(1);

    // the unfiltered one is still 2 PICs after the filtered one was cached, and 304 works per filter
    expect(count($this->actingAs($admin)->getJson('/vme/snapshot')->json('pics')))->toBe(2);
    $this->actingAs($admin)->getJson('/vme/snapshot?area=wwd', ['If-None-Match' => $only->headers->get('ETag')])->assertStatus(304);
});

test('without a filter nothing changes: the map still shows every Area', function () {
    vfOn();
    [$a] = vfWorker('WWD', 'A');
    [$b] = vfWorker('BUL', 'B');
    ManualActivity::create(['user_id' => $a->id, 'name' => 'ASSEMBLING', 'machine_number' => 'WORKSHOP', 'started_at' => now()]);

    $snap = $this->actingAs(vfAdmin())->getJson('/vme/snapshot')->json();

    expect(collect($snap['pics'])->pluck('user_id')->sort()->values()->all())->toBe(collect([$a->id, $b->id])->sort()->values()->all());
});
