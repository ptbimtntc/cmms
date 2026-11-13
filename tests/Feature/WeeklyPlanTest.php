<?php

use App\Models\Area;
use App\Models\Machine;
use App\Models\PicAvailability;
use App\Models\PMSchedule;
use App\Models\User;
use App\Models\WeeklyPmPlan;
use App\Services\WeeklyPlan\EmailComposer;
use App\Services\WeeklyPlan\MachineFamily;

const WP_WEEK = '2026-11-09'; // Monday

function wpArea(string $name = 'WWD'): Area
{
    return Area::firstOrCreate(['name' => $name], ['slug' => strtolower($name), 'is_active' => true]);
}

function wpUser(string $role, ?string $area = 'WWD', array $attrs = []): User
{
    return User::factory()->create(array_merge([
        'role' => $role,
        'area_id' => $area ? wpArea($area)->id : null,
    ], $attrs));
}

function wpSchedule(string $number, string $type = 'NDE', array $attrs = []): PMSchedule
{
    $area = $attrs['area'] ?? 'WWD';
    $machine = Machine::firstOrCreate(
        ['machine_number' => $number],
        ['area' => $area, 'machine_type' => $type, 'status' => 'ACTIVE']
    );

    return PMSchedule::create(array_merge([
        'machine_id' => $machine->id,
        'machine_number' => $number,
        'machine_type' => $type,
        'area' => $area,
        'plan_date' => '2026-11-02',
        'plan_month' => 'November',
        'plan_year' => 2026,
        'due_date' => '2026-11-12',
        'status' => 'OPEN',
    ], $attrs));
}

function wpPlan(string $area = 'WWD', string $week = WP_WEEK): WeeklyPmPlan
{
    return WeeklyPmPlan::create(['area' => $area, 'week_start' => $week, 'status' => WeeklyPmPlan::STATUS_DRAFT]);
}

test('email text follows the coordinator format: grouped by type, indented, Nopember, PIC in caps', function () {
    $rows = [
        ['pm_schedule_id' => 1, 'machine_number' => '20043', 'machine_type' => 'NDE', 'day' => '2026-11-09', 'pic' => 'Asep', 'construction_note' => '0.225 ST'],
        ['pm_schedule_id' => 2, 'machine_number' => '20044', 'machine_type' => 'NDE', 'day' => '2026-11-09', 'pic' => 'jaenal', 'construction_note' => '0.225 ST'],
        ['pm_schedule_id' => 3, 'machine_number' => 'SE47', 'machine_type' => 'NDE', 'day' => '2026-11-10', 'pic' => 'Asep', 'construction_note' => '0.32'],
        ['pm_schedule_id' => 4, 'machine_number' => '20293', 'machine_type' => 'SHX', 'day' => '2026-11-10', 'pic' => 'Fajar', 'construction_note' => '0.295 HT'],
        ['pm_schedule_id' => 5, 'machine_number' => '20294', 'machine_type' => 'SHX', 'day' => '2026-11-10', 'pic' => 'Gunawan', 'construction_note' => '0.295 HT'],
        ['pm_schedule_id' => 6, 'machine_number' => 'SE48', 'machine_type' => 'NDE', 'day' => '2026-11-10', 'pic' => null, 'construction_note' => null],
    ];

    $email = app(EmailComposer::class)->compose('WWD', $rows);

    expect($email['text'])->toBe(implode("\n", [
        'Dear All,',
        '',
        'Planning PM WWD Hari Senin, 9 Nopember 2026:',
        'NDE: 20043 (0.225 ST) - ASEP',
        '     20044 (0.225 ST) - JAENAL',
        '',
        'Planning PM WWD Hari Selasa, 10 Nopember 2026:',
        'NDE: SE47 (0.32) - ASEP',
        '     SE48 ()',
        'SHX: 20293 (0.295 HT) - FAJAR',
        '     20294 (0.295 HT) - GUNAWAN',
    ]));

    expect($email['html'])->toContain('<b>Planning PM WWD Hari Senin, 9 Nopember 2026:</b>')
        ->and($email['html'])->toContain('<td style="padding:0">20044 (0.225 ST) - JAENAL</td>');
});

test('email escapes html in user text', function () {
    $email = app(EmailComposer::class)->compose('WWD', [
        ['pm_schedule_id' => 1, 'machine_number' => '1', 'machine_type' => 'NDE', 'day' => '2026-11-09', 'pic' => null, 'construction_note' => '<script>'],
    ]);

    expect($email['html'])->not->toContain('<script>')->toContain('&lt;script&gt;');
});

test('koordinator opens a week and gets open schedules of their area only', function () {
    $koor = wpUser(User::ROLE_KOORDINATOR);
    wpArea('BUL');
    wpSchedule('20043');
    wpSchedule('20044', 'NDE', ['status' => 'FINISHED']);
    wpSchedule('B-1', 'NDB', ['area' => 'BUL']);
    wpSchedule('20999', 'NDE', ['plan_date' => '2026-12-01']); // next month: out

    $response = $this->actingAs($koor)->get(route('weekly-plan.show', ['area' => 'wwd', 'week' => WP_WEEK]));

    $response->assertOk();
    $pool = collect($response->viewData('payload')['state']['pool'])->pluck('machine_number')->all();
    expect($pool)->toBe(['20043']);
});

test('a date in the middle of the week redirects to its monday', function () {
    $koor = wpUser(User::ROLE_KOORDINATOR);

    $this->actingAs($koor)
        ->get(route('weekly-plan.show', ['area' => 'wwd', 'week' => '2026-11-11']))
        ->assertRedirect(route('weekly-plan.show', ['area' => 'wwd', 'week' => WP_WEEK]));
});

test('koordinator cannot open another area and pic role cannot open the planner', function () {
    wpArea('BUL');
    $koor = wpUser(User::ROLE_KOORDINATOR);
    $pic = wpUser(User::ROLE_PIC);

    $this->actingAs($koor)->get(route('weekly-plan.show', ['area' => 'bul', 'week' => WP_WEEK]))->assertForbidden();
    $this->actingAs($pic)->get(route('weekly-plan.show', ['area' => 'wwd', 'week' => WP_WEEK]))->assertForbidden();
});

test('saving items stores the arrangement and returns the email without touching pm_schedules', function () {
    $koor = wpUser(User::ROLE_KOORDINATOR);
    wpUser(User::ROLE_PIC, 'WWD', ['name' => 'Asep']);
    $a = wpSchedule('20043', 'NDE', ['pic' => null]);
    $b = wpSchedule('20044');
    $plan = wpPlan();

    $response = $this->actingAs($koor)->putJson(route('weekly-plan.items.save', $plan), ['items' => [
        ['pm_schedule_id' => $b->id, 'day' => '2026-11-09', 'pic' => 'Asep', 'construction_note' => '0.225 ST'],
        ['pm_schedule_id' => $a->id, 'day' => '2026-11-09', 'pic' => 'Asep', 'construction_note' => '0.225 ST'],
    ]]);

    $response->assertOk();
    expect($response->json('email.text'))->toContain("NDE: 20044 (0.225 ST) - ASEP\n     20043 (0.225 ST) - ASEP");
    expect($plan->items()->pluck('machine_number')->all())->toBe(['20044', '20043']);
    expect($a->fresh()->pic)->toBeNull()
        ->and($a->fresh()->plan_date)->toBe('2026-11-02');
});

test('saving rejects a pic from another area, a weekend day and a schedule from another area', function () {
    $koor = wpUser(User::ROLE_KOORDINATOR);
    wpUser(User::ROLE_PIC, 'BUL', ['name' => 'Budi']);
    $a = wpSchedule('20043');
    $bul = wpSchedule('B-1', 'NDB', ['area' => 'BUL']);
    $plan = wpPlan();
    $url = route('weekly-plan.items.save', $plan);

    $this->actingAs($koor)->putJson($url, ['items' => [['pm_schedule_id' => $a->id, 'day' => '2026-11-09', 'pic' => 'Budi']]])
        ->assertUnprocessable();
    $this->actingAs($koor)->putJson($url, ['items' => [['pm_schedule_id' => $a->id, 'day' => '2026-11-14']]])
        ->assertUnprocessable();
    $this->actingAs($koor)->putJson($url, ['items' => [['pm_schedule_id' => $bul->id, 'day' => '2026-11-09']]])
        ->assertUnprocessable();

    expect($plan->items()->count())->toBe(0);
});

test('autofill places machines due by friday, keeps manual ones and skips pics on leave', function () {
    $koor = wpUser(User::ROLE_KOORDINATOR);
    $asep = wpUser(User::ROLE_PIC, 'WWD', ['name' => 'Asep']);
    wpUser(User::ROLE_PIC, 'WWD', ['name' => 'Jaenal']);
    PicAvailability::create(['user_id' => $asep->id, 'date' => '2026-11-09', 'reason' => 'Cuti', 'set_by_user_id' => $koor->id]);

    $manual = wpSchedule('M-1', 'SHX', ['due_date' => '2026-11-20']);
    $due = collect(range(1, 9))->map(fn ($i) => wpSchedule("N-$i", 'NDE', ['due_date' => '2026-11-1'.($i % 3)]));
    $later = wpSchedule('L-1', 'NDE', ['due_date' => '2026-11-25']);

    $plan = wpPlan();
    $this->actingAs($koor)->putJson(route('weekly-plan.items.save', $plan), ['items' => [
        ['pm_schedule_id' => $manual->id, 'day' => '2026-11-13', 'pic' => 'Jaenal'],
    ]])->assertOk();

    $response = $this->actingAs($koor)->postJson(route('weekly-plan.autofill', $plan));

    $response->assertOk()->assertJson(['placed' => 9, 'left' => 0]);
    $items = $plan->items()->get();

    expect($items->firstWhere('machine_number', 'M-1')->day->toDateString())->toBe('2026-11-13')
        ->and($items->pluck('machine_number'))->not->toContain('L-1')
        ->and($items->filter(fn ($i) => $i->day->toDateString() === '2026-11-09')->pluck('pic')->unique()->values()->all())->toBe(['Jaenal'])
        ->and($items->whereNull('pic'))->toHaveCount(0);

    // Default limit 4 a day, Monday first: 4, 4, 1, plus the manual one on Friday.
    expect($items->groupBy(fn ($i) => $i->day->toDateString())->map->count()->all())
        ->toBe(['2026-11-09' => 4, '2026-11-10' => 4, '2026-11-11' => 1, '2026-11-13' => 1]);
});

test('autofill respects the per-day limit and leaves the rest unscheduled', function () {
    $koor = wpUser(User::ROLE_KOORDINATOR);
    collect(range(1, 8))->each(fn ($i) => wpSchedule("N-$i"));
    $plan = wpPlan();

    $this->actingAs($koor)->putJson(route('weekly-plan.day-limits.save', $plan), ['limits' => [
        '2026-11-09' => 2, '2026-11-10' => 0, '2026-11-11' => 1, '2026-11-12' => 1, '2026-11-13' => 0,
    ]])->assertOk();

    $response = $this->actingAs($koor)->postJson(route('weekly-plan.autofill', $plan));

    $response->assertOk()->assertJson(['placed' => 4, 'left' => 4]);
    expect($plan->items()->get()->groupBy(fn ($i) => $i->day->toDateString())->map->count()->all())
        ->toBe(['2026-11-09' => 2, '2026-11-11' => 1, '2026-11-12' => 1])
        ->and(collect($response->json('state.days'))->pluck('limit')->all())->toBe([2, 0, 1, 1, 0])
        ->and($response->json('state.pool'))->toHaveCount(4);
});

test('day limits reject values outside 0 to 6 and dates outside the week', function () {
    $koor = wpUser(User::ROLE_KOORDINATOR);
    $plan = wpPlan();
    $url = route('weekly-plan.day-limits.save', $plan);

    $this->actingAs($koor)->putJson($url, ['limits' => ['2026-11-09' => 7]])->assertUnprocessable();
    $this->actingAs($koor)->putJson($url, ['limits' => ['2026-11-14' => 2]])->assertUnprocessable();
    $this->actingAs($koor)->putJson($url, ['limits' => ['2026-11-09' => 0]])->assertOk();

    expect($plan->fresh()->dayLimit('2026-11-09'))->toBe(0)
        ->and($plan->fresh()->dayLimit('2026-11-10'))->toBe(4);
});

test('construction note is prefilled from the previous week', function () {
    $koor = wpUser(User::ROLE_KOORDINATOR);
    $s = wpSchedule('20043', 'NDE', ['due_date' => '2026-11-04']);
    $previous = wpPlan('WWD', '2026-11-02');
    $previous->items()->create([
        'pm_schedule_id' => $s->id, 'machine_id' => $s->machine_id, 'machine_number' => '20043',
        'machine_type' => 'NDE', 'day' => '2026-11-03', 'construction_note' => '0.225 ST',
    ]);

    $response = $this->actingAs($koor)->get(route('weekly-plan.show', ['area' => 'wwd', 'week' => WP_WEEK]));
    $card = collect($response->viewData('payload')['state']['pool'])->firstWhere('machine_number', '20043');

    expect($card['construction_note'])->toBe('0.225 ST')
        ->and($card['carried_over'])->toBeTrue()
        ->and($card['overdue'])->toBeTrue();
});

test('revision email lists only the changes since it was marked sent', function () {
    $koor = wpUser(User::ROLE_KOORDINATOR);
    wpUser(User::ROLE_PIC, 'WWD', ['name' => 'Asep']);
    wpUser(User::ROLE_PIC, 'WWD', ['name' => 'Jaenal']);
    $a = wpSchedule('20043');
    $b = wpSchedule('20044');
    $c = wpSchedule('20045');
    $plan = wpPlan();
    $url = route('weekly-plan.items.save', $plan);

    $this->actingAs($koor)->putJson($url, ['items' => [
        ['pm_schedule_id' => $a->id, 'day' => '2026-11-09', 'pic' => 'Asep'],
        ['pm_schedule_id' => $b->id, 'day' => '2026-11-09', 'pic' => 'Asep'],
    ]])->assertOk();

    $this->actingAs($koor)->postJson(route('weekly-plan.mark-sent', $plan))
        ->assertOk()
        ->assertJsonPath('revision.count', 0);

    $response = $this->actingAs($koor)->putJson($url, ['items' => [
        ['pm_schedule_id' => $a->id, 'day' => '2026-11-11', 'pic' => 'Jaenal'],
        ['pm_schedule_id' => $c->id, 'day' => '2026-11-10', 'pic' => 'Asep'],
    ]]);

    $text = $response->json('revision.text');
    expect($response->json('revision.count'))->toBe(4)
        ->and($text)->toContain('Revisi Planning PM WWD minggu 9 - 13 Nopember 2026:')
        ->and($text)->toContain('- Pindah: NDE 20043 dari Hari Senin, 9 Nopember 2026 ke Hari Rabu, 11 Nopember 2026')
        ->and($text)->toContain('- Ganti PIC: NDE 20043 (Hari Rabu, 11 Nopember 2026): ASEP menjadi JAENAL')
        ->and($text)->toContain('- Tambah: NDE 20045 () - ASEP, Hari Selasa, 10 Nopember 2026')
        ->and($text)->toContain('- Batal: NDE 20044, Hari Senin, 9 Nopember 2026')
        ->and($text)->toContain("Jadwal terbaru:\n\nPlanning PM WWD Hari Selasa, 10 Nopember 2026:");
});

test('realization compares last week plan with actual pm schedule results', function () {
    $koor = wpUser(User::ROLE_KOORDINATOR);
    $onTime = wpSchedule('20043', 'NDE', ['status' => 'FINISHED', 'actual_date' => '2026-11-03']);
    $late = wpSchedule('20044', 'NDE', ['status' => 'FINISHED', 'actual_date' => '2026-11-05']);
    $open = wpSchedule('20045', 'NDE');
    $previous = wpPlan('WWD', '2026-11-02');

    foreach ([$onTime, $late, $open] as $s) {
        $previous->items()->create([
            'pm_schedule_id' => $s->id, 'machine_id' => $s->machine_id, 'machine_number' => $s->machine_number,
            'machine_type' => 'NDE', 'day' => '2026-11-03', 'pic' => 'Asep',
        ]);
    }

    $response = $this->actingAs($koor)->get(route('weekly-plan.show', ['area' => 'wwd', 'week' => WP_WEEK]));
    $r = $response->viewData('payload')['realization'];

    expect($r['total'])->toBe(3)
        ->and($r['on_day'])->toBe(1)
        ->and($r['text'])->toContain('Realisasi Planning PM WWD minggu 2 - 6 Nopember 2026: 2 dari 3 mesin selesai.')
        ->and($r['text'])->toContain('NDE: 20044 - ASEP (rencana Selasa, 3 Nopember 2026, selesai Kamis, 5 Nopember 2026)')
        ->and($r['text'])->toContain("Belum selesai:\nNDE: 20045 - ASEP (rencana Selasa, 3 Nopember 2026)");
});

test('realization is absent when last week had no plan', function () {
    $koor = wpUser(User::ROLE_KOORDINATOR);

    $response = $this->actingAs($koor)->get(route('weekly-plan.show', ['area' => 'wwd', 'week' => WP_WEEK]));

    expect($response->viewData('payload')['realization'])->toBeNull();
});

test('index redirects to the picked area and week', function () {
    $admin = wpUser(User::ROLE_ADMIN, null);
    wpArea('WWD');
    wpArea('BUL');

    $this->actingAs($admin)
        ->get(route('weekly-plan.index', ['area' => 'bul', 'week' => '2026-11-12']))
        ->assertRedirect(route('weekly-plan.show', ['area' => 'bul', 'week' => WP_WEEK]));
});

test('autofill follows the per-day machine type mix, then fills the room left', function () {
    $koor = wpUser(User::ROLE_KOORDINATOR);
    collect(range(1, 6))->each(fn ($i) => wpSchedule("N-$i", 'NDE', ['due_date' => '2026-11-0'.$i]));
    collect(range(0, 3))->each(fn ($i) => wpSchedule("S-$i", 'SHUIXING', ['due_date' => '2026-11-1'.$i]));
    $plan = wpPlan();

    $this->actingAs($koor)->putJson(route('weekly-plan.day-limits.save', $plan), [
        'limits' => ['2026-11-09' => 4, '2026-11-10' => 4, '2026-11-11' => 0, '2026-11-12' => 0, '2026-11-13' => 0],
        'types' => [
            '2026-11-09' => [['type' => 'SHUIXING', 'count' => 2], ['type' => 'NDE', 'count' => 1]],
            '2026-11-10' => [['type' => 'SHUIXING', 'count' => 2]],
        ],
    ])->assertOk();

    $this->actingAs($koor)->postJson(route('weekly-plan.autofill', $plan))
        ->assertOk()
        ->assertJson(['placed' => 8, 'left' => 2])
        ->assertJsonPath('state.days.0.types.0', ['type' => 'SHUIXING', 'count' => 2]);

    $byDay = $plan->items()->get()->groupBy(fn ($i) => $i->day->toDateString())
        ->map(fn ($items) => $items->countBy('machine_type')->sortKeys()->all())->all();

    // Monday: SHUIXING 2 + NDE 1 from the mix, 1 more NDE (most urgent left) to reach the limit 4.
    // Tuesday: SHUIXING 2 from the mix, 2 NDE to reach 4.
    expect($byDay)->toBe([
        '2026-11-09' => ['NDE' => 2, 'SHUIXING' => 2],
        '2026-11-10' => ['NDE' => 2, 'SHUIXING' => 2],
    ]);
    expect($plan->items()->whereDate('day', '2026-11-09')->where('machine_type', 'NDE')->pluck('machine_number')->sort()->values()->all())
        ->toBe(['N-1', 'N-2']);
});

test('type mix is rejected when it exceeds the day limit, repeats a type or names an unknown type', function () {
    $koor = wpUser(User::ROLE_KOORDINATOR);
    wpSchedule('N-1', 'NDE');
    wpSchedule('S-1', 'SHUIXING');
    $plan = wpPlan();
    $url = route('weekly-plan.day-limits.save', $plan);

    $this->actingAs($koor)->putJson($url, ['types' => ['2026-11-09' => [['type' => 'NDE', 'count' => 3], ['type' => 'SHUIXING', 'count' => 2]]]])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'The type mix for Senin adds up to 5 machines, over the limit of 4.');
    $this->actingAs($koor)->putJson($url, ['types' => ['2026-11-09' => [['type' => 'NDE', 'count' => 1], ['type' => 'NDE', 'count' => 1]]]])
        ->assertUnprocessable();
    $this->actingAs($koor)->putJson($url, ['types' => ['2026-11-09' => [['type' => 'BFM', 'count' => 1]]]])
        ->assertUnprocessable();

    // A valid mix, then lowering the limit below it is refused.
    $this->actingAs($koor)->putJson($url, ['types' => ['2026-11-09' => [['type' => 'NDE', 'count' => 3]]]])->assertOk();
    $this->actingAs($koor)->putJson($url, ['limits' => ['2026-11-09' => 2]])->assertUnprocessable();

    expect($plan->fresh()->dayLimit('2026-11-09'))->toBe(4)
        ->and($plan->fresh()->dayTypes('2026-11-09'))->toBe([['type' => 'NDE', 'count' => 3]]);
});

test('machine types are planned and emailed by family, the first word of the type', function () {
    expect(MachineFamily::of('NDE SC 2003/2007'))->toBe('NDE')
        ->and(MachineFamily::of('  ndb  TRITON '))->toBe('NDB')
        ->and(MachineFamily::of('SHUIXING'))->toBe('SHUIXING');

    $email = app(EmailComposer::class)->compose('WWD', [
        ['pm_schedule_id' => 1, 'machine_number' => 'SE47', 'machine_type' => 'NDE SW MONO', 'day' => '2026-11-10', 'pic' => 'Asep', 'construction_note' => '0.32'],
        ['pm_schedule_id' => 2, 'machine_number' => '20030', 'machine_type' => 'NDE SC 2003/2007', 'day' => '2026-11-10', 'pic' => 'Fajar', 'construction_note' => null],
    ]);
    expect($email['text'])->toContain("NDE: SE47 (0.32) - ASEP\n     20030 () - FAJAR");

    $koor = wpUser(User::ROLE_KOORDINATOR);
    wpSchedule('SE47', 'NDE SW MONO');
    wpSchedule('20030', 'NDE SC 2003/2007');
    wpSchedule('B-1', 'NDB TRITON');
    $plan = wpPlan();

    $response = $this->actingAs($koor)->get(route('weekly-plan.show', ['area' => 'wwd', 'week' => WP_WEEK]));
    expect($response->viewData('payload')['state']['machine_types'])->toBe(['NDB', 'NDE']);

    $this->actingAs($koor)->putJson(route('weekly-plan.day-limits.save', $plan), [
        'limits' => ['2026-11-09' => 2, '2026-11-10' => 0, '2026-11-11' => 0, '2026-11-12' => 0, '2026-11-13' => 0],
        'types' => ['2026-11-09' => [['type' => 'NDE', 'count' => 2]]],
    ])->assertOk();

    $this->actingAs($koor)->postJson(route('weekly-plan.autofill', $plan))->assertJson(['placed' => 2, 'left' => 1]);
    expect($plan->items()->pluck('machine_number')->sort()->values()->all())->toBe(['20030', 'SE47']);
});

test('a type mix saved with a full type name is read as its family', function () {
    $plan = new WeeklyPmPlan(['day_types' => ['2026-11-09' => [['type' => 'NDE SW', 'count' => 1], ['type' => 'NDE SC 2003/2007', 'count' => 2]]]]);

    expect($plan->dayTypes('2026-11-09'))->toBe([['type' => 'NDE', 'count' => 3]]);
});

test('a new WWD week starts with the default limits and type mix, other areas with limit 4', function () {
    $koor = wpUser(User::ROLE_KOORDINATOR);

    $days = $this->actingAs($koor)->get(route('weekly-plan.show', ['area' => 'wwd', 'week' => WP_WEEK]))
        ->viewData('payload')['state']['days'];

    expect(array_column($days, 'limit'))->toBe([4, 4, 4, 4, 0])
        ->and($days[0]['types'])->toBe([['type' => 'NDE', 'count' => 2], ['type' => 'SHUIXING', 'count' => 2]])
        ->and($days[2]['types'])->toBe([['type' => 'NDE', 'count' => 2], ['type' => 'SHUIXING', 'count' => 2]])
        ->and($days[3]['types'])->toBe([['type' => 'NDE', 'count' => 2], ['type' => 'NDB', 'count' => 2]])
        ->and($days[4]['types'])->toBe([]);

    $bul = wpUser(User::ROLE_KOORDINATOR, 'BUL');
    $bulDays = $this->actingAs($bul)->get(route('weekly-plan.show', ['area' => 'bul', 'week' => WP_WEEK]))
        ->viewData('payload')['state']['days'];

    expect(array_column($bulDays, 'limit'))->toBe([4, 4, 4, 4, 4])
        ->and(array_merge(...array_column($bulDays, 'types')))->toBe([]);
});

test('an existing week keeps its own settings instead of the defaults', function () {
    $koor = wpUser(User::ROLE_KOORDINATOR);
    WeeklyPmPlan::create(['area' => 'WWD', 'week_start' => WP_WEEK, 'status' => 'DRAFT', 'day_limits' => ['2026-11-09' => 1]]);

    $days = $this->actingAs($koor)->get(route('weekly-plan.show', ['area' => 'wwd', 'week' => WP_WEEK]))
        ->viewData('payload')['state']['days'];

    expect($days[0]['limit'])->toBe(1)->and($days[0]['types'])->toBe([]);
});

test('the sidebar link opens WWD for a user who sees every area', function () {
    $admin = wpUser(User::ROLE_ADMIN, null);
    wpArea('BUL');
    wpArea('WWD');

    $this->actingAs($admin)->get(route('weekly-plan.index'))
        ->assertRedirectContains('/weekly-plan/wwd/');
});

test('apply PIC writes plan PICs into PM Schedule and takes them back when a machine leaves the plan', function () {
    $koor = wpUser(User::ROLE_KOORDINATOR);
    wpUser(User::ROLE_PIC, 'WWD', ['name' => 'Asep']);
    wpUser(User::ROLE_PIC, 'WWD', ['name' => 'Jaenal']);
    $empty = wpSchedule('A-1', 'NDE', ['pic' => null]);
    $other = wpSchedule('A-2', 'NDE', ['pic' => 'Budi']);
    $started = wpSchedule('A-3', 'NDE', ['pic' => null, 'status' => 'IN_PROGRESS']);
    $plan = wpPlan();
    $save = fn (array $items) => $this->actingAs($koor)->putJson(route('weekly-plan.items.save', $plan), ['items' => $items])->assertOk();

    $response = $save([
        ['pm_schedule_id' => $empty->id, 'day' => '2026-11-09', 'pic' => 'Asep'],
        ['pm_schedule_id' => $other->id, 'day' => '2026-11-09', 'pic' => 'Jaenal'],
        ['pm_schedule_id' => $started->id, 'day' => '2026-11-10', 'pic' => 'Asep'],
    ]);
    expect($response->json('pic_sync.pending'))->toBe(2);

    $this->actingAs($koor)->getJson(route('weekly-plan.pic-sync.preview', $plan))
        ->assertOk()
        ->assertJson(['set' => 1, 'replace' => 1, 'locked' => 1, 'pending' => 2]);

    $this->actingAs($koor)->postJson(route('weekly-plan.pic-sync.apply', $plan))
        ->assertOk()
        ->assertJsonPath('result.pending', 2)
        ->assertJsonPath('pic_sync.pending', 0);

    expect($empty->fresh()->pic)->toBe('Asep')
        ->and($other->fresh()->pic)->toBe('Jaenal')
        ->and($started->fresh()->pic)->toBeNull();

    // Revision: A-1 goes back to the pool, A-2 changes PIC. Leaving the plan
    // takes the PIC back on save; the new PIC for A-2 waits for the button.
    $response = $save([
        ['pm_schedule_id' => $other->id, 'day' => '2026-11-09', 'pic' => 'Asep'],
        ['pm_schedule_id' => $started->id, 'day' => '2026-11-10', 'pic' => 'Asep'],
    ]);
    expect($empty->fresh()->pic)->toBeNull()
        ->and($other->fresh()->pic)->toBe('Jaenal')
        ->and($response->json('pic_sync.pending'))->toBe(1);

    $this->actingAs($koor)->postJson(route('weekly-plan.pic-sync.apply', $plan))
        ->assertJsonPath('result.set', 1)
        ->assertJsonPath('result.restore', 0);
    expect($other->fresh()->pic)->toBe('Asep');

    // A-2 leaves too: on save it gets Budi back, the PIC it had before the plan touched it.
    $save([['pm_schedule_id' => $started->id, 'day' => '2026-11-10', 'pic' => 'Asep']]);
    expect($other->fresh()->pic)->toBe('Budi');

    // Emptying the plan entirely ("Kosongkan semua hari") releases everything that is left.
    $save([]);
    expect($plan->fresh()->applied_pics)->toBe([]);

    // Applying again with nothing new changes nothing.
    $this->actingAs($koor)->postJson(route('weekly-plan.pic-sync.apply', $plan))->assertJsonPath('result.pending', 0);
});

test('apply PIC leaves a PIC that was changed by hand in PM Schedule, and clearing the PIC in the plan takes it back', function () {
    $koor = wpUser(User::ROLE_KOORDINATOR);
    wpUser(User::ROLE_PIC, 'WWD', ['name' => 'Asep']);
    $a = wpSchedule('A-1', 'NDE', ['pic' => null]);
    $b = wpSchedule('A-2', 'NDE', ['pic' => null]);
    $plan = wpPlan();
    $url = route('weekly-plan.items.save', $plan);

    $this->actingAs($koor)->putJson($url, ['items' => [
        ['pm_schedule_id' => $a->id, 'day' => '2026-11-09', 'pic' => 'Asep'],
        ['pm_schedule_id' => $b->id, 'day' => '2026-11-09', 'pic' => 'Asep'],
    ]])->assertOk();
    $this->actingAs($koor)->postJson(route('weekly-plan.pic-sync.apply', $plan))->assertOk();

    $a->update(['pic' => 'Dedi']); // reassigned by hand on the PM Schedule page

    // A-1 leaves the plan and A-2's PIC is cleared in the plan; both are handled on save.
    $this->actingAs($koor)->putJson($url, ['items' => [
        ['pm_schedule_id' => $b->id, 'day' => '2026-11-09', 'pic' => null],
    ]])->assertOk()->assertJsonPath('pic_sync.pending', 0);

    expect($a->fresh()->pic)->toBe('Dedi')
        ->and($b->fresh()->pic)->toBeNull()
        ->and($plan->fresh()->applied_pics)->toBe([]);
});

test('returning machines to the pool restores PICs for a plan applied before this change', function () {
    // The state the coordinator's real plan was in: PICs applied, then every machine sent back.
    $koor = wpUser(User::ROLE_KOORDINATOR);
    $a = wpSchedule('A-1', 'NDE', ['pic' => 'Asep']);
    $plan = wpPlan();
    $plan->update(['applied_pics' => [$a->id => ['pic' => 'Asep', 'previous' => null]]]);

    $this->actingAs($koor)->putJson(route('weekly-plan.items.save', $plan), ['items' => []])->assertOk();

    expect($a->fresh()->pic)->toBeNull();
});

test('a koordinator of another area cannot apply PICs', function () {
    wpArea('BUL');
    $plan = wpPlan();
    $bul = wpUser(User::ROLE_KOORDINATOR, 'BUL');

    $this->actingAs($bul)->postJson(route('weekly-plan.pic-sync.apply', $plan))->assertForbidden();
});

test('restoring day defaults puts a changed WWD week back to the default limits and type mix', function () {
    $koor = wpUser(User::ROLE_KOORDINATOR);
    $plan = WeeklyPmPlan::create([
        'area' => 'WWD', 'week_start' => WP_WEEK, 'status' => 'DRAFT',
        'day_limits' => ['2026-11-09' => 4, '2026-11-10' => 1, '2026-11-11' => 1, '2026-11-12' => 1, '2026-11-13' => 0],
        'day_types' => ['2026-11-09' => [['type' => 'NDE', 'count' => 2], ['type' => 'SHUIXING', 'count' => 2]]],
    ]);

    $show = $this->actingAs($koor)->get(route('weekly-plan.show', ['area' => 'wwd', 'week' => WP_WEEK]))->viewData('payload')['state'];
    expect($show['day_defaults'][1])->toBe(['limit' => 4, 'types' => [['type' => 'NDE', 'count' => 2], ['type' => 'SHUIXING', 'count' => 2]]])
        ->and($show['days'][1]['limit'])->toBe(1);

    $response = $this->actingAs($koor)->postJson(route('weekly-plan.day-defaults.restore', $plan))->assertOk();

    expect(collect($response->json('state.days'))->pluck('limit')->all())->toBe([4, 4, 4, 4, 0])
        ->and($response->json('state.days.3.types'))->toBe([['type' => 'NDE', 'count' => 2], ['type' => 'NDB', 'count' => 2]])
        ->and($plan->fresh()->dayTypes('2026-11-11'))->toBe([['type' => 'NDE', 'count' => 2], ['type' => 'SHUIXING', 'count' => 2]]);

    $bul = wpUser(User::ROLE_KOORDINATOR, 'BUL');
    $this->actingAs($bul)->postJson(route('weekly-plan.day-defaults.restore', $plan))->assertForbidden();
});
