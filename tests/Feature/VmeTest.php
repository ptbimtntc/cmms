<?php

use App\Models\ActivityMonitorClosure;
use App\Models\Area;
use App\Models\Greasing;
use App\Models\Group;
use App\Models\JobType;
use App\Models\Machine;
use App\Models\ManualActivity;
use App\Models\PicAvailability;
use App\Models\PMSchedule;
use App\Models\User;
use App\Models\VmeCharacterProfile;
use App\Models\VmeSetting;
use App\Services\ActiveActivityResolver;
use App\Services\Vme\VmeFeature;
use App\Support\Activities\ActiveActivity;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    // No snapshot caching in tests: every request must see the data just created.
    config(['vme.snapshot_ttl' => 0, 'vme.kill_switch' => false]);
});

function vmeOn(array $on = VmeFeature::KEYS): void
{
    foreach (VmeFeature::KEYS as $key) {
        VmeFeature::set($key, in_array($key, $on, true));
    }
}

function vmePic(string $name, string $role = 'PIC WWD'): User
{
    return User::factory()->create([...roleAttributes($role), 'name' => $name, 'is_active' => true]);
}

function vmeAdmin(): User
{
    return User::factory()->admin()->create(['is_active' => true]);
}

function vmeMachine(string $number, string $area = 'WWD'): Machine
{
    Area::firstOrCreate(['name' => $area], ['slug' => strtolower($area), 'is_active' => true]);

    return Machine::create(['machine_number' => $number, 'area' => $area, 'machine_type' => 'NDE', 'status' => 'ACTIVE']);
}

function vmePm(User $pic, ?Machine $machine, string $status = 'IN_PROGRESS', ?string $end = null): PMSchedule
{
    $today = now()->toDateString();

    return PMSchedule::create([
        'machine_id' => $machine?->id, 'machine_number' => $machine?->machine_number ?? 'X-1', 'machine_type' => 'NDE',
        'area' => $machine?->area ?? 'WWD', 'order_number' => 'ORD-'.uniqid(), 'plan_date' => $today,
        'plan_month' => Carbon::parse($today)->format('F'), 'plan_year' => Carbon::parse($today)->format('Y'),
        'due_date' => Carbon::parse($today)->addDays(14), 'pic' => $pic->name,
        'status' => $status, 'start_time' => '08:00', 'end_time' => $end, 'actual_date' => $today,
    ]);
}

function vmeGreasing(User $pic, ?Group $group = null): Greasing
{
    $areaId = Area::firstOrCreate(['name' => 'WWD'], ['slug' => 'wwd', 'is_active' => true])->id;
    $group ??= Group::create(['name' => 'GRP '.uniqid(), 'area_id' => $areaId]);

    return Greasing::create([
        'group_id' => $group->id, 'order_number' => 'WO-'.uniqid(), 'cycle' => '4W',
        'plan_date' => now()->toDateString(), 'due_date' => Greasing::calculateDueDate(now()->toDateString()),
        'pic' => $pic->name, 'status' => 'OPEN', 'start_time' => now()->setTime(9, 0),
    ]);
}

function vmeSnapshot($test, User $viewer): array
{
    return $test->actingAs($viewer)->getJson('/vme/snapshot')->assertOk()->json();
}

function vmeFind(array $snapshot, User $user): ?array
{
    return collect($snapshot['pics'])->firstWhere('user_id', $user->id);
}

// ── 1. DTO identity ───────────────────────────────────────────────────────

test('ActiveActivity carries structured identity for PM, Greasing, Oil Audit and Manual', function () {
    $pic = vmePic('ANDI');
    $machine = vmeMachine('M-100');
    $pm = vmePm($pic, $machine);

    $a = (new ActiveActivityResolver)->currentFor($pic);

    expect($a->source)->toBe('PM')
        ->and($a->userId)->toBe($pic->id)
        ->and($a->areaId)->toBe($pic->area_id)
        ->and($a->machineId)->toBe($machine->id)
        ->and($a->groupId)->toBeNull()
        ->and($a->recordId)->toBe($pm->id)
        ->and($a->jobTypeKey)->toBe('pm')
        ->and($a->status)->toBe(ActiveActivity::STATUS_ACTIVE);
});

test('Greasing activity carries the group and the group area', function () {
    $pic = vmePic('BUDI');
    $g = vmeGreasing($pic);

    $a = (new ActiveActivityResolver)->currentFor($pic);

    expect($a->source)->toBe('GREASING')
        ->and($a->groupId)->toBe($g->group_id)
        ->and($a->areaId)->toBe($g->group->area_id)
        ->and($a->machineId)->toBeNull()
        ->and($a->jobTypeKey)->toBe('greasing');
});

test('Manual activity resolves a known machine number and maps its name through job type aliases', function () {
    $pic = vmePic('CICI');
    $machine = vmeMachine('M-200');
    ManualActivity::create(['user_id' => $pic->id, 'name' => 'Cleaning Motor Grilles line 2', 'machine_number' => 'M-200', 'started_at' => now()]);

    $a = (new ActiveActivityResolver)->currentFor($pic);

    expect($a->machineId)->toBe($machine->id)
        ->and($a->jobTypeKey)->toBe('cleaning_motor_grilles')
        ->and($a->displayLabel())->toBe('Cleaning Motor Grilles line 2');
});

test('Manual activity with an unknown label or name falls back safely', function () {
    $pic = vmePic('DEDE');
    ManualActivity::create(['user_id' => $pic->id, 'name' => 'Rapat vendor', 'machine_number' => 'Conveyor #03', 'started_at' => now()]);

    $a = (new ActiveActivityResolver)->currentFor($pic);

    expect($a->machineId)->toBeNull()->and($a->jobTypeKey)->toBe('manual');
});

test('job types ship with the base set and alias matching prefers the longest alias', function () {
    expect(JobType::whereIn('key', ['pm', 'oil_audit', 'oil_audit_action', 'greasing', 'manual'])->count())->toBe(5);

    $pic = vmePic('EKO');
    ManualActivity::create(['user_id' => $pic->id, 'name' => 'change  Panel BLOWER', 'started_at' => now()]);

    expect((new ActiveActivityResolver)->currentFor($pic)->jobTypeKey)->toBe('change_panel_blower');
});

// ── 2. Ambiguous names ────────────────────────────────────────────────────

test('a PM whose PIC name matches two PIC accounts is not attributed to either', function () {
    vmeOn();
    $admin = vmeAdmin();
    $a = vmePic('SAMA');
    $b = vmePic('sama ');
    vmePm($a, vmeMachine('M-1'));

    $snap = vmeSnapshot($this, $admin);

    expect(vmeFind($snap, $a)['status'])->toBe('IDLE')
        ->and(vmeFind($snap, $a)['activity'])->toBeNull()
        ->and(vmeFind($snap, $b)['activity'])->toBeNull()
        ->and($snap['sync']['unmapped_count'])->toBeGreaterThan(0);

    $this->actingAs($admin)->get('/maintenance-intelligence/vme')->assertOk()->assertSee('Duplicate PIC names');
});

test('a unique name is still mapped', function () {
    vmeOn();
    $pic = vmePic('UNIK');
    vmePm($pic, vmeMachine('M-1'));

    expect(vmeFind(vmeSnapshot($this, vmeAdmin()), $pic)['status'])->toBe('WORKING');
});

// ── 3-6. Targets ──────────────────────────────────────────────────────────

test('PM sends the character to the right machine, standing beside it', function () {
    vmeOn();
    $pic = vmePic('PEMA');
    $m1 = vmeMachine('M-1');
    $m2 = vmeMachine('M-2');
    vmePm($pic, $m2);

    $snap = vmeSnapshot($this, vmeAdmin());
    $p = vmeFind($snap, $pic);
    $machine = collect($snap['machines'])->firstWhere('id', $m2->id);
    $offset = config('vme.layout.stand_offset');

    expect($snap['machines'])->toHaveCount(1)
        ->and($p['target']['kind'])->toBe('machine')
        ->and($p['target']['machine_id'])->toBe($m2->id)
        ->and($p['target']['machine_id'])->not->toBe($m1->id)
        ->and($p['target']['x'])->toBe($machine['x'] + $offset['x'])
        ->and($p['target']['y'])->toBe($machine['y'] + $offset['y'])
        ->and($p['target']['zone'])->toBe('area:'.$pic->area_id)
        ->and($p['target']['face'])->toBe('left')
        ->and($p['activity']['job_type_key'])->toBe('pm');
});

test('Greasing sends the character to its group anchor', function () {
    vmeOn();
    $pic = vmePic('GRESI');
    $g = vmeGreasing($pic);

    $p = vmeFind(vmeSnapshot($this, vmeAdmin()), $pic);

    expect($p['target']['kind'])->toBe('group')
        ->and($p['target']['group_id'])->toBe($g->group_id)
        ->and($p['activity']['animation_key'])->toBe('work_grease');
});

test('Oil Audit and Oil Audit Action use the Oil Audit anchor of the PIC area', function () {
    vmeOn();
    $audit = vmePic('OLI A');
    $audit->update(['oil_audit_started_at' => now()]);
    $action = vmePic('OLI B');
    $action->update(['oil_audit_action_started_at' => now()]);

    $snap = vmeSnapshot($this, vmeAdmin());

    foreach ([$audit, $action] as $u) {
        $p = vmeFind($snap, $u);
        expect($p['target']['kind'])->toBe('oil_audit')
            ->and($p['target']['area_id'])->toBe($u->area_id)
            ->and($p['target']['machine_id'])->toBeNull();
    }
    expect(vmeFind($snap, $audit)['activity']['source'])->toBe('OIL_AUDIT')
        ->and(vmeFind($snap, $action)['activity']['source'])->toBe('OIL_AUDIT_ACTION');
});

test('Manual activity uses the machine when known, otherwise the area default anchor', function () {
    vmeOn();
    $withMachine = vmePic('MAN A');
    $without = vmePic('MAN B');
    $machine = vmeMachine('M-9');
    ManualActivity::create(['user_id' => $withMachine->id, 'name' => 'Repair', 'machine_number' => 'M-9', 'started_at' => now()]);
    ManualActivity::create(['user_id' => $without->id, 'name' => 'Repair', 'machine_number' => 'Conveyor 3', 'started_at' => now()]);

    $snap = vmeSnapshot($this, vmeAdmin());

    expect(vmeFind($snap, $withMachine)['target'])->toMatchArray(['kind' => 'machine', 'machine_id' => $machine->id])
        ->and(vmeFind($snap, $without)['target'])->toMatchArray(['kind' => 'area_default', 'area_id' => $without->area_id]);
});

test('a Manual Activity located in the Workshop sends the PIC to the Workshop zone', function () {
    vmeOn();
    $pic = vmePic('RIZKI');
    ManualActivity::create(['user_id' => $pic->id, 'name' => 'ASSEMBLING', 'machine_number' => 'WORKSHOP', 'started_at' => now()]);

    $p = vmeFind(vmeSnapshot($this, vmeAdmin()), $pic);

    expect($p['target'])->toMatchArray(['kind' => 'workshop', 'zone' => 'workshop', 'machine_id' => null])
        ->and($p['status'])->toBe('WORKING');
});

test('a Manual Activity whose location is a Group name goes to that Group anchor', function () {
    vmeOn();
    $pic = vmePic('INDRA');
    $group = Group::create(['name' => 'WWD 02', 'area_id' => $pic->area_id]);
    ManualActivity::create(['user_id' => $pic->id, 'name' => 'GREASING', 'machine_number' => 'wwd 02', 'started_at' => now()]);

    $p = vmeFind(vmeSnapshot($this, vmeAdmin()), $pic);

    expect($p['target'])->toMatchArray(['kind' => 'group', 'group_id' => $group->id])
        ->and($p['activity']['job_type_key'])->toBe('greasing');
});

test('PICs sharing one destination are fanned out so none is hidden behind another', function () {
    vmeOn();
    $a = vmePic('BERSAMA A');
    $b = vmePic('BERSAMA B');
    $c = vmePic('BERSAMA C');
    foreach ([$a, $b, $c] as $u) {
        ManualActivity::create(['user_id' => $u->id, 'name' => 'Repair', 'started_at' => now()]);
    }

    $snap = vmeSnapshot($this, vmeAdmin());
    $spots = collect([$a, $b, $c])->map(fn ($u) => vmeFind($snap, $u)['target']['x'].','.vmeFind($snap, $u)['target']['y']);

    expect($spots->unique())->toHaveCount(3);
});

// ── VME-04: activity -> animation ────────────────────────────────────────

test('every job type points at an animation the client can play', function () {
    $allowed = config('vme.animations');

    foreach (JobType::all() as $type) {
        expect($allowed)->toContain($type->animation_key);
    }
});

test('each kind of activity gets its own animation', function () {
    vmeOn();
    $machine = vmeMachine('M-ANIM');
    $pm = vmePic('ANIM PM');
    vmePm($pm, $machine);
    $oil = vmePic('ANIM OIL');
    $oil->update(['oil_audit_started_at' => now()]);
    $action = vmePic('ANIM ACTION');
    $action->update(['oil_audit_action_started_at' => now()]);
    $grease = vmePic('ANIM GREASE');
    vmeGreasing($grease);
    $clean = vmePic('ANIM CLEAN');
    ManualActivity::create(['user_id' => $clean->id, 'name' => 'Cleaning Motor Grilles', 'started_at' => now()]);
    $plain = vmePic('ANIM PLAIN');
    ManualActivity::create(['user_id' => $plain->id, 'name' => 'Rapat vendor', 'started_at' => now()]);

    $snap = vmeSnapshot($this, vmeAdmin());
    $anim = fn (User $u) => vmeFind($snap, $u)['activity']['animation_key'];

    expect($anim($pm))->toBe('work_pm')
        ->and($anim($oil))->toBe('work_oil_sample')
        ->and($anim($action))->toBe('work_oil_action')
        ->and($anim($grease))->toBe('work_grease')
        ->and($anim($clean))->toBe('work_clean')
        ->and($anim($plain))->toBe('work_inspect'); // Manual Activity with no mapping: generic inspection

    expect(collect([$pm, $oil, $action, $grease, $clean])->map($anim)->unique())->toHaveCount(5);
});

test('a Manual Activity is recognised by what it says, not treated as unmapped', function () {
    vmeOn();
    $cases = [
        'PM' => ['pm', 'work_pm'],
        'pm mesin 3' => ['pm', 'work_pm'],
        'ACTION TEMUAN OLI' => ['oil_audit_action', 'work_oil_action'],
        'GREASING' => ['greasing', 'work_grease'],
        'GREASSING' => ['greasing', 'work_grease'], // common typo
        'CLEANING' => ['cleaning', 'work_clean'],
        'Cleaning Motor Grilles' => ['cleaning_motor_grilles', 'work_clean'], // longest alias wins
        'ASSEMBLING' => ['assembling', 'work_tool'],
        'Repair conveyor' => ['repair', 'work_tool'],
        'Oil audit line 2' => ['oil_audit', 'work_oil_sample'],
        'Rapat vendor' => ['manual', 'work_inspect'],
        'Replace pump seal' => ['manual', 'work_inspect'], // "pm" is not a word in "pump"
    ];

    $pics = [];
    foreach (array_keys($cases) as $i => $text) {
        $pics[$text] = vmePic('MANUAL '.$i);
        ManualActivity::create(['user_id' => $pics[$text]->id, 'name' => $text, 'started_at' => now()]);
    }

    $snap = vmeSnapshot($this, vmeAdmin());

    foreach ($cases as $text => [$job, $animation]) {
        $activity = vmeFind($snap, $pics[$text])['activity'];

        expect($activity['job_type_key'])->toBe($job, $text)
            ->and($activity['animation_key'])->toBe($animation, $text)
            ->and($activity['source'])->toBe('MANUAL')       // still a Manual Activity...
            ->and($activity['label'])->toBe($text);          // ...shown exactly as typed
    }
});

test('an unknown or customised-away animation key falls back to the generic inspection', function () {
    vmeOn();
    JobType::where('key', 'pm')->update(['animation_key' => 'does_not_exist']);
    $pic = vmePic('ANIM BAD');
    vmePm($pic, vmeMachine('M-BAD'));

    expect(vmeFind(vmeSnapshot($this, vmeAdmin()), $pic)['activity']['animation_key'])->toBe('work_inspect');
});

test('the character is told which way to face: toward the machine it works at', function () {
    vmeOn();
    $pic = vmePic('FACING');
    vmePm($pic, vmeMachine('M-FACE'));

    $target = vmeFind(vmeSnapshot($this, vmeAdmin()), $pic)['target'];

    expect($target['face'])->toBe('left'); // stands to the right of the machine
});

// ── Patrol ───────────────────────────────────────────────────────────────

test('an Oil Audit gets a patrol route inside the PIC area, other work does not', function () {
    vmeOn();
    $auditor = vmePic('PATROL A');
    $auditor->update(['oil_audit_started_at' => now()]);
    $other = vmePic('PATROL B');
    $other->update(['oil_audit_started_at' => now()]);
    $action = vmePic('PATROL C');
    $action->update(['oil_audit_action_started_at' => now()]);
    $pm = vmePic('PATROL D');
    vmePm($pm, vmeMachine('M-PAT'));
    $typed = vmePic('PATROL E');
    ManualActivity::create(['user_id' => $typed->id, 'name' => 'Oil audit line 2', 'started_at' => now()]);

    $snap = vmeSnapshot($this, vmeAdmin());
    $zones = collect($this->actingAs(vmeAdmin())->getJson('/vme/config')->json('layout.zones'));
    $zone = $zones->firstWhere('area_id', $auditor->area_id);
    $route = vmeFind($snap, $auditor)['target']['patrol'];

    expect($route)->not->toBeEmpty()->and(count($route))->toBeGreaterThan(1);
    foreach ($route as $point) {
        expect($point['x'])->toBeGreaterThanOrEqual($zone['x'])->toBeLessThanOrEqual($zone['x'] + $zone['w'])
            ->and($point['y'])->toBeGreaterThanOrEqual($zone['y'])->toBeLessThanOrEqual($zone['y'] + $zone['h']);
    }

    $target = vmeFind($snap, $auditor)['target'];
    expect([$target['x'], $target['y']])->toBe([$route[0]['x'], $route[0]['y']]) // starts at the first stop
        ->and(vmeFind($snap, $other)['target']['patrol'][0])->not->toBe($route[0]) // another auditor starts elsewhere
        ->and(vmeFind($snap, $typed)['target'])->toHaveKey('patrol')              // a typed "Oil audit" patrols too
        ->and(vmeFind($snap, $action)['target'])->not->toHaveKey('patrol')        // Action Audit Oli: stays at its spot
        ->and(vmeFind($snap, $pm)['target'])->not->toHaveKey('patrol');
});

test('the patrol interval is configurable and sent to the client', function () {
    vmeOn();

    expect($this->actingAs(vmeAdmin())->getJson('/vme/config')->json('patrol_seconds'))->toBe(3);

    config(['vme.patrol_seconds' => 5]);
    expect($this->actingAs(vmeAdmin())->getJson('/vme/config')->json('patrol_seconds'))->toBe(5);
});

test('a patrol can be turned off for oil audit through config', function () {
    vmeOn();
    config(['vme.patrol_job_types' => []]);
    $auditor = vmePic('NO PATROL');
    $auditor->update(['oil_audit_started_at' => now()]);

    expect(vmeFind(vmeSnapshot($this, vmeAdmin()), $auditor)['target'])->not->toHaveKey('patrol');
});

test('a PIC without any area and without a location waits at the office lobby', function () {
    vmeOn();
    $pic = User::factory()->create(['role' => User::ROLE_PIC, 'name' => 'NOAREA', 'is_active' => true, 'area_id' => null]);
    ManualActivity::create(['user_id' => $pic->id, 'name' => 'Rapat', 'started_at' => now()]);

    $p = vmeFind(vmeSnapshot($this, vmeAdmin()), $pic);

    expect($p['zone'])->toBe('office')->and($p['target']['kind'])->toBe('base');
});

// ── 7-8. Ended vs closed, idle ────────────────────────────────────────────

test('closing from the monitor is reported as CLOSED_BY_MONITOR, not FINISHED', function () {
    vmeOn();
    $pic = vmePic('TUTUP');
    $pm = vmePm($pic, vmeMachine('M-1'));
    ActivityMonitorClosure::create([
        'source' => 'PM', 'source_key' => (string) $pm->id, 'business_date' => today(),
        'pic_user_id' => $pic->id, 'closed_by_user_id' => $pic->id, 'closed_at' => now(),
    ]);

    $p = vmeFind(vmeSnapshot($this, vmeAdmin()), $pic);

    expect($p['status'])->toBe('IDLE')
        ->and($p['activity'])->toBeNull()
        ->and($p['target'])->toBeNull()
        ->and($p['last_ended']['reason'])->toBe('CLOSED_BY_MONITOR')
        ->and($pm->fresh()->status)->toBe('IN_PROGRESS'); // source module untouched
});

test('a PM the module marked done is reported as FINISHED', function () {
    vmeOn();
    $pic = vmePic('SELESAI');
    vmePm($pic, vmeMachine('M-1'), 'FINISHED', '09:30');

    $p = vmeFind(vmeSnapshot($this, vmeAdmin()), $pic);

    expect($p['status'])->toBe('IDLE')->and($p['last_ended']['reason'])->toBe('FINISHED');
});

test('without an actual activity the character is idle at base, even with a PM scheduled', function () {
    vmeOn();
    $pic = vmePic('NGANGGUR');
    PMSchedule::create([
        'machine_id' => vmeMachine('M-1')->id, 'machine_number' => 'M-1', 'machine_type' => 'NDE', 'area' => 'WWD',
        'order_number' => 'ORD-1', 'plan_date' => today(), 'plan_month' => 'x', 'plan_year' => '2026',
        'due_date' => today()->addDays(10), 'pic' => $pic->name, 'status' => 'OPEN',
    ]);

    $p = vmeFind(vmeSnapshot($this, vmeAdmin()), $pic);

    expect($p['status'])->toBe('IDLE')->and($p['activity'])->toBeNull()->and($p['target'])->toBeNull()
        ->and($p['base'])->toHaveKeys(['x', 'y']);
});

test('END & START moves the character to the new activity location', function () {
    vmeOn();
    $pic = vmePic('PINDAH');
    $old = vmePm($pic, vmeMachine('M-1'));
    ActivityMonitorClosure::create([
        'source' => 'PM', 'source_key' => (string) $old->id, 'business_date' => today(),
        'pic_user_id' => $pic->id, 'closed_by_user_id' => $pic->id, 'closed_at' => now()->subMinute(),
    ]);
    $new = vmeMachine('M-2');
    ManualActivity::create(['user_id' => $pic->id, 'name' => 'Repair', 'machine_number' => 'M-2', 'started_at' => now()]);

    $p = vmeFind(vmeSnapshot($this, vmeAdmin()), $pic);

    expect($p['activity']['source'])->toBe('MANUAL')->and($p['target']['machine_id'])->toBe($new->id);
});

test('inactive PICs are reported INACTIVE and never working', function () {
    vmeOn();
    $pic = vmePic('CUTI');
    vmePm($pic, vmeMachine('M-1'));
    PicAvailability::create(['user_id' => $pic->id, 'date' => today(), 'reason' => 'Cuti', 'set_by_user_id' => vmeAdmin()->id]);

    $p = vmeFind(vmeSnapshot($this, vmeAdmin()), $pic);

    expect($p['status'])->toBe('INACTIVE')->and($p['activity'])->toBeNull();
});

// ── 9. Authorization and scoping ──────────────────────────────────────────

test('snapshot and config require authentication and an allowed role', function () {
    vmeOn();

    $this->getJson('/vme/snapshot')->assertRedirect(route('login'));
    $this->get('/vme')->assertRedirect(route('login'));

    foreach (['PIC WWD', 'GUEST'] as $role) {
        $u = User::factory()->create([...roleAttributes($role), 'is_active' => true]);
        $this->actingAs($u)->getJson('/vme/snapshot')->assertForbidden();
        $this->actingAs($u)->getJson('/vme/config')->assertForbidden();
        $this->actingAs($u)->get('/vme')->assertForbidden();
    }
});

test('viewers only get PICs of their own scope and no personal data', function () {
    vmeOn();
    $wwd = vmePic('WWD ORANG', 'PIC WWD');
    $bul = vmePic('BUL ORANG', 'PIC BUL');
    vmePm($bul, vmeMachine('B-1', 'BUL'));
    $koordinator = User::factory()->create([...roleAttributes('KOORDINATOR WWD'), 'is_active' => true]);
    $supervisor = User::factory()->supervisor()->create(['is_active' => true]);

    $kSnap = vmeSnapshot($this, $koordinator);
    expect(vmeFind($kSnap, $wwd))->not->toBeNull()->and(vmeFind($kSnap, $bul))->toBeNull()
        ->and($kSnap['sync'])->not->toHaveKey('unmapped_count');

    expect($kSnap['machines'])->toBe([]);

    $sSnap = vmeSnapshot($this, $supervisor);
    expect(vmeFind($sSnap, $wwd))->not->toBeNull()->and(vmeFind($sSnap, $bul))->not->toBeNull();

    expect(collect($sSnap['machines'])->pluck('number')->all())->toBe(['B-1']);

    $aSnap = vmeSnapshot($this, vmeAdmin());
    expect(vmeFind($aSnap, $wwd))->not->toBeNull()->and(vmeFind($aSnap, $bul))->not->toBeNull();

    expect(json_encode($aSnap))->not->toContain('@')->not->toContain('password');
});

test('a supervisor restricted to an area only sees that area', function () {
    vmeOn();
    $wwd = vmePic('WWD ORANG', 'PIC WWD');
    $bul = vmePic('BUL ORANG', 'PIC BUL');
    $supervisor = User::factory()->supervisor()->create(['is_active' => true]);
    $supervisor->areas()->attach(Area::where('name', 'BUL')->value('id'));

    $snap = vmeSnapshot($this, $supervisor);

    expect(vmeFind($snap, $bul))->not->toBeNull()->and(vmeFind($snap, $wwd))->toBeNull();
});

test('only ADMIN can manage VME', function () {
    $m = vmeMachine('M-1');

    foreach (['KOORDINATOR WWD', 'PIC WWD'] as $role) {
        $u = User::factory()->create([...roleAttributes($role), 'is_active' => true]);
        $this->actingAs($u)->get('/maintenance-intelligence/vme')->assertForbidden();
        $this->actingAs($u)->put(route('maintenance-intelligence.vme.settings'), ['display' => 1])->assertForbidden();
    }
    $supervisor = User::factory()->supervisor()->create();
    $this->actingAs($supervisor)->put(route('maintenance-intelligence.vme.settings'), ['display' => 1])->assertForbidden();
    expect(VmeSetting::count())->toBe(0);
});

// ── 10. Feature switches ──────────────────────────────────────────────────

test('every VME switch is OFF by default and VME endpoints are 404', function () {
    expect(VmeFeature::all())->toBe(['display' => false, 'activity_sync' => false, 'human_movement' => false, 'ai_characters' => false]);

    $admin = vmeAdmin();
    $this->actingAs($admin)->getJson('/vme/snapshot')->assertNotFound();
    $this->actingAs($admin)->getJson('/vme/config')->assertNotFound();
    $this->actingAs($admin)->get('/vme')->assertNotFound();
    $this->actingAs($admin)->get('/maintenance-intelligence/vme')->assertOk();
});

test('the environment kill switch disables VME even when the switches are ON', function () {
    vmeOn();
    config(['vme.kill_switch' => true]);
    $admin = vmeAdmin();

    $this->actingAs($admin)->getJson('/vme/snapshot')->assertNotFound();
    $this->actingAs($admin)->get('/maintenance-intelligence/vme')->assertOk()->assertSee('VME_KILL_SWITCH');
});

test('admin can toggle the switches from the Maintenance Intelligence Center', function () {
    $admin = vmeAdmin();

    $this->actingAs($admin)->put(route('maintenance-intelligence.vme.settings'), ['display' => 1, 'activity_sync' => 1])
        ->assertRedirect();

    expect(VmeFeature::all())->toBe(['display' => true, 'activity_sync' => true, 'human_movement' => false, 'ai_characters' => false]);
});

test('activity sync OFF keeps VME up but every character idle', function () {
    vmeOn(['display']);
    $pic = vmePic('SYNCOFF');
    vmePm($pic, vmeMachine('M-1'));

    $snap = vmeSnapshot($this, vmeAdmin());

    expect($snap['sync']['status'])->toBe('disabled')
        ->and(vmeFind($snap, $pic)['activity'])->toBeNull()
        ->and(vmeFind($snap, $pic)['status'])->toBe('IDLE');
});

test('VME OFF leaves Today Activity, the public monitor and the resolver untouched', function () {
    $pic = vmePic('JALAN');
    vmePm($pic, vmeMachine('M-1'));

    $this->get('/monitor/data')->assertOk()->assertJsonPath('activeCount', 1);
    expect((new ActiveActivityResolver)->currentFor($pic))->not->toBeNull();

    $admin = vmeAdmin();
    $this->actingAs($admin)->get('/today-activity')->assertOk();
});

test('a failing job type lookup cannot break Today Activity', function () {
    $pic = vmePic('RAPUH');
    ManualActivity::create(['user_id' => $pic->id, 'name' => 'Air leaked', 'started_at' => now()]);
    Schema::drop('job_type_aliases');

    $a = (new ActiveActivityResolver)->currentFor($pic);

    expect($a->jobTypeKey)->toBe('manual');
});

// ── 11. Machine positions and map ─────────────────────────────────────────

test('machines are placed automatically and never on top of each other', function () {
    vmeOn();
    $capacity = config('vme.layout.machine_cols') * config('vme.layout.machine_rows');
    foreach (range(1, $capacity) as $i) {
        vmePm(vmePic('OVERLAP '.$i), vmeMachine('OV-'.$i));
    }

    $machines = collect(vmeSnapshot($this, vmeAdmin())['machines'])->values();

    expect($machines)->toHaveCount($capacity);

    foreach ($machines as $i => $a) {
        foreach ($machines->slice($i + 1) as $b) {
            $overlap = abs($a['x'] - $b['x']) < 70 && abs($a['y'] - $b['y']) < 60;
            expect($overlap)->toBeFalse("{$a['number']} and {$b['number']} overlap");
        }
    }
});

test('a full grid area still places every machine without overlap, using a second layer', function () {
    vmeOn();
    $total = config('vme.layout.machine_cols') * config('vme.layout.machine_rows') + 3;
    foreach (range(1, $total) as $i) {
        vmePm(vmePic('FULL '.$i), vmeMachine('FL-'.$i));
    }

    $machines = collect(vmeSnapshot($this, vmeAdmin())['machines']);

    expect($machines)->toHaveCount($total)
        ->and($machines->map(fn ($m) => $m['x'].','.$m['y'])->unique())->toHaveCount($total);
});

test('a machine keeps its place while other machines come and go', function () {
    vmeOn();
    $a = vmePic('STABIL A');
    $b = vmePic('STABIL B');
    $ma = vmeMachine('ST-A');
    $mb = vmeMachine('ST-B');
    vmePm($a, $ma);
    $pmB = vmePm($b, $mb);

    $before = collect(vmeSnapshot($this, vmeAdmin())['machines'])->firstWhere('id', $ma->id);

    ActivityMonitorClosure::create([
        'source' => 'PM', 'source_key' => (string) $pmB->id, 'business_date' => today(),
        'pic_user_id' => $b->id, 'closed_by_user_id' => $b->id, 'closed_at' => now(),
    ]);

    $machines = collect(vmeSnapshot($this, vmeAdmin())['machines']);
    $after = $machines->firstWhere('id', $ma->id);

    expect($machines)->toHaveCount(1)->and([$after['x'], $after['y']])->toBe([$before['x'], $before['y']]);
});

test('no machine position feature is left: the snapshot carries no positioned flag', function () {
    vmeOn();
    $pic = vmePic('NOFLAG');
    vmePm($pic, vmeMachine('NF-1'));

    $snap = vmeSnapshot($this, vmeAdmin());

    expect($snap['machines'][0])->not->toHaveKey('positioned')
        ->and(vmeFind($snap, $pic)['target'])->not->toHaveKey('positioned');
});

test('a new Area appears on the map and its machine only when somebody works on it', function () {
    vmeOn();
    $machine = vmeMachine('Z-1', 'NEWAREA');

    $layout = $this->actingAs(vmeAdmin())->getJson('/vme/config')->json('layout');
    expect(collect($layout['zones'])->pluck('label')->all())->toContain('NEWAREA');
    expect(vmeSnapshot($this, vmeAdmin())['machines'])->toBe([]);

    $pic = vmePic('PIC Z', 'PIC WWD');
    vmePm($pic, $machine);

    expect(collect(vmeSnapshot($this, vmeAdmin())['machines'])->pluck('number')->all())->toBe(['Z-1']);
});

// ── Dynamic machine rendering ─────────────────────────────────────────────

test('the static environment renders with no PIC activity and never lists Machine Master', function () {
    vmeOn();
    foreach (range(1, 12) as $i) {
        vmeMachine('M-'.$i);
    }

    $config = $this->actingAs(vmeAdmin())->getJson('/vme/config')->assertOk()->json('layout');
    $snap = vmeSnapshot($this, vmeAdmin());

    expect($config)->not->toHaveKey('machines')
        ->and(collect($config['zones'])->pluck('type')->unique()->sort()->values()->all())->toBe(['office', 'production', 'workshop'])
        ->and($config['waypoints'])->not->toBeEmpty()
        ->and($snap['machines'])->toBe([]);
});

test('only the machine a PIC is working on is sent', function () {
    vmeOn();
    $a = vmePic('RENDER A');
    $b = vmePic('RENDER B');
    $m10 = vmeMachine('10002');
    vmeMachine('10003');
    vmeMachine('10015');
    vmePm($a, $m10);

    $snap = vmeSnapshot($this, vmeAdmin());

    expect(collect($snap['machines'])->pluck('number')->all())->toBe(['10002'])
        ->and(vmeFind($snap, $b)['target'])->toBeNull();
});

test('several PICs on the same machine produce one machine object', function () {
    vmeOn();
    $a = vmePic('SAMA A');
    $b = vmePic('SAMA B');
    $m = vmeMachine('M-5');
    vmePm($a, $m);
    ManualActivity::create(['user_id' => $b->id, 'name' => 'Repair', 'machine_number' => 'M-5', 'started_at' => now()]);

    $snap = vmeSnapshot($this, vmeAdmin());

    expect($snap['machines'])->toHaveCount(1)
        ->and(vmeFind($snap, $a)['target']['machine_id'])->toBe($m->id)
        ->and(vmeFind($snap, $b)['target']['machine_id'])->toBe($m->id);
});

test('a machine disappears once its activity is closed', function () {
    vmeOn();
    $pic = vmePic('LEPAS');
    $pm = vmePm($pic, vmeMachine('M-6'));

    expect(vmeSnapshot($this, vmeAdmin())['machines'])->toHaveCount(1);

    ActivityMonitorClosure::create([
        'source' => 'PM', 'source_key' => (string) $pm->id, 'business_date' => today(),
        'pic_user_id' => $pic->id, 'closed_by_user_id' => $pic->id, 'closed_at' => now(),
    ]);

    expect(vmeSnapshot($this, vmeAdmin())['machines'])->toBe([]);
});

test('activities without a machine id render no machine and cause no error', function () {
    vmeOn();
    vmeGreasing(vmePic('GRS'));
    $oil = vmePic('OIL');
    $oil->update(['oil_audit_started_at' => now()]);
    $manual = vmePic('MNL');
    ManualActivity::create(['user_id' => $manual->id, 'name' => 'Rapat', 'started_at' => now()]);
    // PM on a machine whose Area is not on the map: no machine object either.
    $pm = vmePic('PMX');
    $orphan = vmeMachine('G-1', 'GONE');
    Area::where('name', 'GONE')->update(['is_active' => false]);
    vmePm($pm, $orphan);

    $snap = vmeSnapshot($this, vmeAdmin());

    expect($snap['machines'])->toBe([])
        ->and(vmeFind($snap, $pm)['target']['kind'])->toBe('area_default');
});

test('group markers and Oil Audit stations are sent only while a PIC uses them', function () {
    vmeOn();
    $idle = vmeSnapshot($this, vmeAdmin());
    $layout = $this->actingAs(vmeAdmin())->getJson('/vme/config')->json('layout');

    expect($layout)->not->toHaveKeys(['groups', 'oil_stations'])
        ->and($idle['groups'])->toBe([])->and($idle['oil_stations'])->toBe([]);

    $greaser = vmePic('GREASER');
    $greasing = vmeGreasing($greaser);
    vmeGreasing(vmePic('GREASER 2'), Group::create(['name' => 'UNUSED', 'area_id' => $greaser->area_id])); // different group, second PIC
    $auditor = vmePic('AUDITOR');
    $auditor->update(['oil_audit_started_at' => now()]);

    $snap = vmeSnapshot($this, vmeAdmin());
    expect(collect($snap['groups'])->pluck('id')->sort()->values()->all())->toHaveCount(2)
        ->and($snap['oil_stations'])->toHaveCount(1)
        ->and($snap['oil_stations'][0]['area_id'])->toBe($auditor->area_id);

    // Activities end: the oil station and the first group disappear again.
    ActivityMonitorClosure::create(['source' => 'GREASING', 'source_key' => (string) $greasing->id, 'business_date' => today(),
        'pic_user_id' => $greaser->id, 'closed_by_user_id' => $greaser->id, 'closed_at' => now()]);
    ActivityMonitorClosure::create(['source' => 'OIL_AUDIT', 'source_key' => (string) $auditor->id, 'business_date' => today(),
        'pic_user_id' => $auditor->id, 'closed_by_user_id' => $auditor->id, 'closed_at' => now()]);

    $after = vmeSnapshot($this, vmeAdmin());
    expect($after['oil_stations'])->toBe([])
        ->and($after['groups'])->toHaveCount(1)
        ->and(collect($after['groups'])->pluck('id')->contains($greasing->group_id))->toBeFalse();
});

test('the snapshot stays small and unchanged data keeps the same version', function () {
    vmeOn();
    foreach (range(1, 30) as $i) {
        vmeMachine('BULK-'.$i);
    }
    vmePm(vmePic('KECIL'), Machine::first());

    $one = vmeSnapshot($this, vmeAdmin());
    $two = vmeSnapshot($this, vmeAdmin());

    expect($one['machines'])->toHaveCount(1)->and($one['version'])->toBe($two['version']);
});

test('the admin page does not render the machine master', function () {
    foreach (range(1, 8) as $i) {
        vmeMachine('HIDDEN-'.$i);
    }

    $this->actingAs(vmeAdmin())->get('/maintenance-intelligence/vme')->assertOk()->assertDontSee('HIDDEN-3');
});

test('character count follows the active PICs and avatars are configurable', function () {
    vmeOn();
    $a = vmePic('A1');
    $b = vmePic('B1');
    User::factory()->create([...roleAttributes('PIC WWD'), 'name' => 'NONAKTIF', 'is_active' => false]);

    expect(vmeSnapshot($this, vmeAdmin())['pics'])->toHaveCount(2);

    $this->actingAs(vmeAdmin())->put(route('maintenance-intelligence.vme.avatar', $a), ['avatar_key' => 'a5'])->assertRedirect();
    $this->actingAs(vmeAdmin())->put(route('maintenance-intelligence.vme.avatar', $b), ['avatar_key' => 'bogus'])->assertSessionHasErrors('avatar_key');

    expect(vmeFind(vmeSnapshot($this, vmeAdmin()), $a)['avatar_key'])->toBe('a5')
        ->and(VmeCharacterProfile::count())->toBe(1);
});

// ── 12. Polling friendliness ──────────────────────────────────────────────

test('snapshot supports ETag revalidation (304) and changes the version when data changes', function () {
    vmeOn();
    $admin = vmeAdmin();
    $pic = vmePic('ETAG');

    $first = $this->actingAs($admin)->getJson('/vme/snapshot');
    $etag = $first->headers->get('ETag');

    expect($etag)->not->toBeEmpty();
    $this->actingAs($admin)->getJson('/vme/snapshot', ['If-None-Match' => $etag])->assertStatus(304);

    vmePm($pic, vmeMachine('M-1'));
    $this->actingAs($admin)->getJson('/vme/snapshot', ['If-None-Match' => $etag])->assertOk();
});

test('the VME page renders for a viewer when enabled and the world is mounted client-side', function () {
    vmeOn();
    $this->withoutVite();

    $this->actingAs(vmeAdmin())->get('/vme')->assertOk()->assertSee('vme-root', false)->assertSee(route('vme.snapshot'), false);
});
