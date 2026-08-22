<?php

use App\Models\Greasing;
use App\Models\GreasingFinding;
use App\Models\Group;
use App\Models\User;
use App\Services\GreasingKpiCalculator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;

function allowedMasterRoles(): array
{
    return ['ADMIN', 'KOORDINATOR WWD', 'KOORDINATOR BUL'];
}

function blockedMasterRoles(): array
{
    return ['PIC WWD', 'PIC BUL', 'GUEST'];
}

function broadRoles(): array
{
    return ['ADMIN', 'KOORDINATOR WWD', 'KOORDINATOR BUL', 'PIC WWD', 'PIC BUL'];
}

function auditCsv(string $filename, array $rows): UploadedFile
{
    $path = storage_path('framework/testing/'.$filename);
    if (! is_dir(dirname($path))) {
        mkdir(dirname($path), 0777, true);
    }
    $handle = fopen($path, 'w');
    foreach ($rows as $row) {
        fputcsv($handle, $row);
    }
    fclose($handle);

    return new UploadedFile($path, $filename, 'text/csv', null, true);
}

function auditUser(string $role, ?string $name = null): User
{
    $attributes = ['role' => $role];

    if ($name !== null) {
        $attributes['name'] = $name;
    }

    return User::factory()->create($attributes);
}

function auditGroup(array $attributes = []): Group
{
    return Group::create(array_merge(['name' => 'Audit Group '.uniqid()], $attributes));
}

function auditGreasing(array $attributes = []): Greasing
{
    $group = auditGroup();

    return Greasing::create(array_merge([
        'group_id' => $group->id,
        'order_number' => 'WO-'.uniqid(),
        'cycle' => '4W',
        'plan_date' => '2026-08-01',
        'due_date' => Greasing::calculateDueDate('2026-08-01'),
        'pic' => null,
        'action_date' => null,
        'status' => 'OPEN',
    ], $attributes));
}

// =====================================================================
// AUDIT 1 — Authorization (direct URL / direct POST-PUT-PATCH-DELETE)
// =====================================================================

describe('Audit 1: Group management authorization', function () {
    test('index is reachable only for ADMIN/KOORDINATOR', function (string $role) {
        $response = $this->actingAs(auditUser($role))->get(route('groups.index'));
        in_array($role, allowedMasterRoles(), true) ? $response->assertOk() : $response->assertForbidden();
    })->with([...allowedMasterRoles(), ...blockedMasterRoles()]);

    test('direct POST to store is blocked for PIC/GUEST', function (string $role) {
        $response = $this->actingAs(auditUser($role))
            ->post(route('groups.store'), ['name' => 'New Group '.uniqid()]);
        $response->assertForbidden();
    })->with(blockedMasterRoles());

    test('ADMIN/KOORDINATOR can create a group via direct POST', function (string $role) {
        $response = $this->actingAs(auditUser($role))
            ->post(route('groups.store'), ['name' => 'New Group '.uniqid()]);
        $response->assertRedirect(route('groups.index'));
    })->with(allowedMasterRoles());

    test('direct DELETE is blocked for PIC/GUEST', function (string $role) {
        $group = auditGroup();
        $response = $this->actingAs(auditUser($role))->delete(route('groups.destroy', $group));
        $response->assertForbidden();
        expect(Group::find($group->id))->not->toBeNull();
    })->with(blockedMasterRoles());

    test('deleting a group that still has a greasing schedule is rejected gracefully, not a raw DB error', function () {
        $admin = auditUser('ADMIN');
        $greasing = auditGreasing();
        $group = $greasing->group;

        $response = $this->actingAs($admin)->delete(route('groups.destroy', $group));

        $response->assertRedirect();
        $response->assertSessionHas('error');
        expect(session('error'))->not->toContain('SQLSTATE')
            ->and(Group::find($group->id))->not->toBeNull();
    });
});

describe('Audit 1: Greasing Schedule management authorization', function () {
    test('create/store/edit/update/destroy are blocked for PIC and GUEST via direct URL', function (string $role) {
        $greasing = auditGreasing();
        $user = auditUser($role);

        $this->actingAs($user)->get(route('greasings.create'))->assertForbidden();
        $this->actingAs($user)->post(route('greasings.store'), [])->assertForbidden();
        $this->actingAs($user)->get(route('greasings.edit', $greasing))->assertForbidden();
        $this->actingAs($user)->put(route('greasings.update', $greasing), [])->assertForbidden();
        $this->actingAs($user)->delete(route('greasings.destroy', $greasing))->assertForbidden();
    })->with(blockedMasterRoles());

    test('ADMIN/KOORDINATOR can reach the create and edit forms', function (string $role) {
        $greasing = auditGreasing();
        $user = auditUser($role);

        $this->actingAs($user)->get(route('greasings.create'))->assertOk();
        $this->actingAs($user)->get(route('greasings.edit', $greasing))->assertOk();
    })->with(allowedMasterRoles());

    test('index is reachable for every role except GUEST', function (string $role) {
        $response = $this->actingAs(auditUser($role))->get(route('greasings.index'));
        $response->assertOk();
    })->with(broadRoles());

    test('GUEST cannot reach the schedule index', function () {
        $this->actingAs(auditUser('GUEST'))->get(route('greasings.index'))->assertForbidden();
    });
});

describe('Audit 1: Greasing execution authorization', function () {
    test('ADMIN/KOORDINATOR can execute any schedule regardless of assigned pic', function (string $role) {
        $pic = auditUser('PIC WWD');
        $greasing = auditGreasing(['pic' => $pic->name]);

        $this->actingAs(auditUser($role))->get(route('greasings.execute', $greasing))->assertOk();
    })->with(allowedMasterRoles());

    test('PIC can execute only their own schedule, not another pic\'s, via direct URL/POST', function (string $picRole) {
        $owner = auditUser($picRole, 'Owner '.uniqid());
        $stranger = auditUser($picRole, 'Stranger '.uniqid());
        $greasing = auditGreasing(['pic' => $owner->name]);

        $this->actingAs($owner)->get(route('greasings.execute', $greasing))->assertOk();

        $this->actingAs($stranger)->get(route('greasings.execute', $greasing))->assertForbidden();
        $this->actingAs($stranger)->post(route('greasings.execute.store', $greasing), [
            'action_date' => '2026-08-10',
        ])->assertForbidden();

        expect($greasing->fresh()->action_date)->toBeNull();
    })->with(['PIC WWD', 'PIC BUL']);

    test('GUEST cannot open or post to the execution endpoint', function () {
        $guest = auditUser('GUEST');
        $greasing = auditGreasing();

        $this->actingAs($guest)->get(route('greasings.execute', $greasing))->assertForbidden();
        $this->actingAs($guest)->post(route('greasings.execute.store', $greasing), [])->assertForbidden();
    });
});

describe('Audit 1: Finding follow-up authorization', function () {
    test('a stranger pic cannot PATCH another pic\'s finding', function (string $picRole) {
        $owner = auditUser($picRole, 'Owner '.uniqid());
        $stranger = auditUser($picRole, 'Stranger '.uniqid());
        $greasing = auditGreasing(['pic' => $owner->name]);
        $finding = $greasing->findings()->create(['finding' => 'leak', 'status' => 'OPEN']);

        $response = $this->actingAs($stranger)->patch(
            route('greasings.findings.update', [$greasing, $finding]),
            ['status' => 'COMPLETED']
        );

        $response->assertForbidden();
        expect($finding->fresh()->status)->toBe('OPEN');
    })->with(['PIC WWD', 'PIC BUL']);

    test('GUEST cannot PATCH any finding', function () {
        $guest = auditUser('GUEST');
        $greasing = auditGreasing();
        $finding = $greasing->findings()->create(['finding' => 'leak', 'status' => 'OPEN']);

        $this->actingAs($guest)->patch(
            route('greasings.findings.update', [$greasing, $finding]),
            ['status' => 'COMPLETED']
        )->assertForbidden();
    });

    test('ADMIN/KOORDINATOR can close any finding regardless of assigned pic', function (string $role) {
        $pic = auditUser('PIC BUL');
        $greasing = auditGreasing(['pic' => $pic->name]);
        $finding = $greasing->findings()->create(['finding' => 'leak', 'status' => 'OPEN']);

        $this->actingAs(auditUser($role))->patch(
            route('greasings.findings.update', [$greasing, $finding]),
            ['status' => 'COMPLETED']
        )->assertRedirect();

        expect($finding->fresh()->status)->toBe('COMPLETED');
    })->with(allowedMasterRoles());
});

describe('Audit 1: Greasing report authorization', function () {
    test('report is reachable for every role except GUEST', function (string $role) {
        $this->actingAs(auditUser($role))->get(route('greasing-report.index'))->assertOk();
    })->with(broadRoles());

    test('GUEST cannot reach the report', function () {
        $this->actingAs(auditUser('GUEST'))->get(route('greasing-report.index'))->assertForbidden();
    });

    test('pic only sees their own schedule on the report, not another pic\'s', function (string $picRole) {
        $mine = auditUser($picRole, 'Mine '.uniqid());
        $other = auditUser($picRole, 'Other '.uniqid());
        $myGreasing = auditGreasing(['pic' => $mine->name, 'plan_date' => '2026-08-01', 'cycle' => '4W']);
        $otherGreasing = auditGreasing(['pic' => $other->name, 'plan_date' => '2026-08-02', 'cycle' => '52W']);

        $response = $this->actingAs($mine)->get(route('greasing-report.index', [
            'period_type' => 'monthly', 'month' => 8, 'year' => 2026,
        ]));

        $response->assertOk();
        $response->assertSee($myGreasing->order_number);
        $response->assertDontSee($otherGreasing->order_number);
    })->with(['PIC WWD', 'PIC BUL']);
});

// =====================================================================
// AUDIT 2 — Validation
// =====================================================================

describe('Audit 2: Greasing Schedule field validation (manual create/edit)', function () {
    test('missing plan date is rejected', function () {
        $admin = auditUser('ADMIN');
        $group = auditGroup();

        $response = $this->actingAs($admin)->post(route('greasings.store'), [
            'group_id' => $group->id,
            'order_number' => 'WO-1',
            'cycle' => '4W',
        ]);

        $response->assertSessionHasErrors('plan_date');
    });

    test('missing order number is rejected', function () {
        $admin = auditUser('ADMIN');
        $group = auditGroup();

        $response = $this->actingAs($admin)->post(route('greasings.store'), [
            'group_id' => $group->id,
            'cycle' => '4W',
            'plan_date' => '2026-08-01',
        ]);

        $response->assertSessionHasErrors('order_number');
        expect(Greasing::count())->toBe(0);
    });

    test('invalid date is rejected', function () {
        $admin = auditUser('ADMIN');
        $group = auditGroup();

        $response = $this->actingAs($admin)->post(route('greasings.store'), [
            'group_id' => $group->id,
            'order_number' => 'WO-1',
            'cycle' => '4W',
            'plan_date' => 'not-a-date',
        ]);

        $response->assertSessionHasErrors('plan_date');
    });

    test('missing group is rejected', function () {
        $admin = auditUser('ADMIN');

        $response = $this->actingAs($admin)->post(route('greasings.store'), [
            'order_number' => 'WO-1',
            'cycle' => '4W',
            'plan_date' => '2026-08-01',
        ]);

        $response->assertSessionHasErrors('group_id');
    });

    test('invalid (non-existent) group id is rejected', function () {
        $admin = auditUser('ADMIN');

        $response = $this->actingAs($admin)->post(route('greasings.store'), [
            'group_id' => 999999,
            'order_number' => 'WO-1',
            'cycle' => '4W',
            'plan_date' => '2026-08-01',
        ]);

        $response->assertSessionHasErrors('group_id');
    });

    test('empty cycle is rejected', function () {
        $admin = auditUser('ADMIN');
        $group = auditGroup();

        $response = $this->actingAs($admin)->post(route('greasings.store'), [
            'group_id' => $group->id,
            'order_number' => 'WO-1',
            'cycle' => '',
            'plan_date' => '2026-08-01',
        ]);

        $response->assertSessionHasErrors('cycle');
    });

    test('invalid pic (not a registered pic-role user) is rejected', function () {
        $admin = auditUser('ADMIN');
        $group = auditGroup();

        $response = $this->actingAs($admin)->post(route('greasings.store'), [
            'group_id' => $group->id,
            'order_number' => 'WO-1',
            'cycle' => '4W',
            'plan_date' => '2026-08-01',
            'pic' => 'Totally Made Up Name',
        ]);

        $response->assertSessionHasErrors('pic');
        expect(Greasing::count())->toBe(0);
    });

    test('a koordinator name as pic is rejected because koordinator is not a pic role', function () {
        $admin = auditUser('ADMIN');
        $group = auditGroup();
        $koordinator = auditUser('KOORDINATOR WWD', 'Koordinator Name');

        $response = $this->actingAs($admin)->post(route('greasings.store'), [
            'group_id' => $group->id,
            'order_number' => 'WO-1',
            'cycle' => '4W',
            'plan_date' => '2026-08-01',
            'pic' => $koordinator->name,
        ]);

        $response->assertSessionHasErrors('pic');
    });

    test('a valid registered pic is accepted', function () {
        $admin = auditUser('ADMIN');
        $group = auditGroup();
        $pic = auditUser('PIC BUL', 'Real Pic');

        $response = $this->actingAs($admin)->post(route('greasings.store'), [
            'group_id' => $group->id,
            'order_number' => 'WO-1',
            'cycle' => '4W',
            'plan_date' => '2026-08-01',
            'pic' => $pic->name,
        ]);

        $response->assertRedirect(route('greasings.index'));
        expect(Greasing::where('order_number', 'WO-1')->first()->pic)->toBe('Real Pic');
    });

    test('duplicate schedule identity (group + plan date + order number) is rejected on manual create', function () {
        $admin = auditUser('ADMIN');
        $existing = auditGreasing(['order_number' => 'WO-DUP', 'plan_date' => '2026-08-01']);

        $response = $this->actingAs($admin)->post(route('greasings.store'), [
            'group_id' => $existing->group_id,
            'order_number' => 'WO-DUP',
            'cycle' => '52W',
            'plan_date' => '2026-08-01',
        ]);

        $response->assertSessionHasErrors('order_number');
        expect(Greasing::where('order_number', 'WO-DUP')->count())->toBe(1);
    });

    test('same order number is allowed again when plan date differs', function () {
        $admin = auditUser('ADMIN');
        $existing = auditGreasing(['order_number' => 'WO-REUSE', 'plan_date' => '2026-08-01']);

        $response = $this->actingAs($admin)->post(route('greasings.store'), [
            'group_id' => $existing->group_id,
            'order_number' => 'WO-REUSE',
            'cycle' => '4W',
            'plan_date' => '2026-09-01',
        ]);

        $response->assertRedirect(route('greasings.index'));
        expect(Greasing::where('order_number', 'WO-REUSE')->count())->toBe(2);
    });

    test('same order number is allowed again when group differs', function () {
        $admin = auditUser('ADMIN');
        $existing = auditGreasing(['order_number' => 'WO-REUSE2', 'plan_date' => '2026-08-01']);
        $otherGroup = auditGroup();

        $response = $this->actingAs($admin)->post(route('greasings.store'), [
            'group_id' => $otherGroup->id,
            'order_number' => 'WO-REUSE2',
            'cycle' => '4W',
            'plan_date' => '2026-08-01',
        ]);

        $response->assertRedirect(route('greasings.index'));
        expect(Greasing::where('order_number', 'WO-REUSE2')->count())->toBe(2);
    });

    test('duplicate identity is also rejected on manual update against another existing record', function () {
        $admin = auditUser('ADMIN');
        $existing = auditGreasing(['order_number' => 'WO-A', 'plan_date' => '2026-08-01']);
        $another = auditGreasing(['group_id' => $existing->group_id, 'order_number' => 'WO-B', 'plan_date' => '2026-08-02']);

        $response = $this->actingAs($admin)->put(route('greasings.update', $another), [
            'group_id' => $existing->group_id,
            'order_number' => 'WO-A',
            'cycle' => $another->cycle,
            'plan_date' => '2026-08-01',
        ]);

        $response->assertSessionHasErrors('order_number');
        expect($another->fresh()->order_number)->toBe('WO-B');
    });
});

describe('Audit 2: Execution and finding validation', function () {
    test('invalid action date is rejected', function () {
        $pic = auditUser('PIC WWD');
        $greasing = auditGreasing(['pic' => $pic->name]);

        $response = $this->actingAs($pic)->post(route('greasings.execute.store', $greasing), [
            'action_date' => 'not-a-real-date',
        ]);

        $response->assertSessionHasErrors('action_date');
        expect($greasing->fresh()->action_date)->toBeNull();
    });

    test('invalid finding status is rejected', function () {
        $admin = auditUser('ADMIN');
        $greasing = auditGreasing();
        $finding = $greasing->findings()->create(['finding' => 'x', 'status' => 'OPEN']);

        $response = $this->actingAs($admin)->patch(
            route('greasings.findings.update', [$greasing, $finding]),
            ['status' => 'IN_PROGRESS']
        );

        $response->assertSessionHasErrors('status');
        expect($finding->fresh()->status)->toBe('OPEN');
    });
});

describe('Audit 2: CSV import validation', function () {

    test('oversized upload is rejected', function () {
        $admin = auditUser('ADMIN');
        $file = UploadedFile::fake()->create('huge.csv', 20481, 'text/csv');

        $this->actingAs($admin)->post(route('greasings.import'), ['file' => $file])
            ->assertSessionHasErrors('file');
    });

    test('invalid (non-csv) file is rejected', function () {
        $admin = auditUser('ADMIN');
        $file = UploadedFile::fake()->create('schedule.pdf', 5, 'application/pdf');

        $this->actingAs($admin)->post(route('greasings.import'), ['file' => $file])
            ->assertSessionHasErrors('file');
    });

    test('malformed csv content does not crash the import and is handled gracefully', function () {
        $admin = auditUser('ADMIN');
        $path = storage_path('framework/testing/malformed.csv');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        // Deliberately malformed: unbalanced quote, ragged columns.
        file_put_contents($path, "order_number,plan_date,group,cycle,pic,remarks\n\"WO-1,2026-08-01,Line 1,4W,Budi\nWO-2\n");
        $file = new UploadedFile($path, 'malformed.csv', 'text/csv', null, true);

        $response = $this->actingAs($admin)->post(route('greasings.import'), ['file' => $file]);

        // Must never surface a raw parser exception; either a clean redirect
        // or a generic file error — never a 500 with internal details.
        expect($response->getStatusCode())->toBe(302);
        expect(Greasing::count())->toBe(0);
    });

    test('csv missing expected columns does not crash and skips incomplete rows', function () {
        $admin = auditUser('ADMIN');
        auditGroup(['name' => 'Line 1']);
        $csv = auditCsv('short-columns.csv', [
            ['order_number', 'plan_date'],
            ['WO-1', '2026-08-01'],
        ]);

        $response = $this->actingAs($admin)->post(route('greasings.import'), ['file' => $csv]);

        $response->assertRedirect();
        expect(Greasing::where('order_number', 'WO-1')->exists())->toBeFalse();
        expect(session('greasing_import_result')['skipped'])->toBe(1);
    });

    test('completely empty csv file does not crash and imports nothing', function () {
        $admin = auditUser('ADMIN');
        $path = storage_path('framework/testing/empty.csv');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        file_put_contents($path, '');
        $file = new UploadedFile($path, 'empty.csv', 'text/csv', null, true);

        $response = $this->actingAs($admin)->post(route('greasings.import'), ['file' => $file]);

        $response->assertRedirect();
        expect(Greasing::count())->toBe(0);
    });

    test('header-only csv (no data rows) imports nothing without error', function () {
        $admin = auditUser('ADMIN');
        $csv = auditCsv('header-only.csv', [
            ['order_number', 'plan_date', 'group', 'cycle', 'pic', 'remarks'],
        ]);

        $response = $this->actingAs($admin)->post(route('greasings.import'), ['file' => $csv]);

        $response->assertRedirect();
        expect(session('greasing_import_result'))->toMatchArray(['imported' => 0, 'duplicate' => 0, 'skipped' => 0]);
    });
});

// =====================================================================
// AUDIT 3 — Status / Due Date formula
// =====================================================================

describe('Audit 3: Status and Due Date formula', function () {
    test('empty action date resolves to OPEN', function () {
        expect(Greasing::resolveStatus(null, '2026-08-15'))->toBe('OPEN');
    });

    test('action date on or before due date resolves to FINISH ON TIME', function () {
        expect(Greasing::resolveStatus('2026-08-15', '2026-08-15'))->toBe('FINISH ON TIME')
            ->and(Greasing::resolveStatus('2026-08-10', '2026-08-15'))->toBe('FINISH ON TIME');
    });

    test('action date after due date resolves to FINISH', function () {
        expect(Greasing::resolveStatus('2026-08-16', '2026-08-15'))->toBe('FINISH');
    });

    test('due date is always plan date plus 14 days', function () {
        expect(Greasing::calculateDueDate('2026-08-01')->format('Y-m-d'))->toBe('2026-08-15');
    });
});

// =====================================================================
// AUDIT 4 — KPI (end-to-end through the real report page)
// =====================================================================

describe('Audit 4: KPI formula end-to-end', function () {
    test('100 schedules with 70 finish-on-time, 20 finish, 10 open yields closing 90% and completion 80%', function () {
        $admin = auditUser('ADMIN');

        for ($i = 0; $i < 70; $i++) {
            auditGreasing([
                'order_number' => 'FOT-'.$i,
                'plan_date' => '2026-08-01',
                'action_date' => '2026-08-10',
                'status' => 'FINISH ON TIME',
            ]);
        }
        for ($i = 0; $i < 20; $i++) {
            auditGreasing([
                'order_number' => 'FIN-'.$i,
                'plan_date' => '2026-08-01',
                'action_date' => '2026-08-20',
                'status' => 'FINISH',
            ]);
        }
        for ($i = 0; $i < 10; $i++) {
            auditGreasing([
                'order_number' => 'OPEN-'.$i,
                'plan_date' => '2026-08-01',
                'status' => 'OPEN',
            ]);
        }

        expect(Greasing::count())->toBe(100);

        $response = $this->actingAs($admin)->get(route('greasing-report.index', [
            'period_type' => 'monthly', 'month' => 8, 'year' => 2026,
        ]));

        $response->assertOk();
        $response->assertSee('90%');
        $response->assertSee('80%');

        $expected = GreasingKpiCalculator::fromCounts(70, 20, 10);
        expect($expected['closing_percent'])->toBe(90.0)
            ->and($expected['completion_percent'])->toBe(80.0);
    });

    test('monthly and yearly filters use the same kpi formula for an isolated month', function () {
        $admin = auditUser('ADMIN');
        auditGreasing(['plan_date' => '2026-08-01', 'action_date' => '2026-08-10', 'status' => 'FINISH ON TIME']);
        auditGreasing(['plan_date' => '2026-08-02', 'status' => 'OPEN']);
        // Different month, must not leak into the August monthly KPI.
        auditGreasing(['plan_date' => '2026-09-01', 'action_date' => '2026-09-05', 'status' => 'FINISH ON TIME']);

        $monthly = $this->actingAs($admin)->get(route('greasing-report.index', [
            'period_type' => 'monthly', 'month' => 8, 'year' => 2026,
        ]));
        $monthly->assertOk();
        // August only: 1 FINISH ON TIME + 1 OPEN => closing 50%, completion 50%.
        $expected = GreasingKpiCalculator::fromCounts(1, 0, 1);
        $monthly->assertSee($expected['closing_percent'].'%');
        $monthly->assertSee($expected['completion_percent'].'%');

        $yearly = $this->actingAs($admin)->get(route('greasing-report.index', [
            'period_type' => 'yearly', 'year' => 2026,
        ]));
        $yearly->assertOk();
        // Whole year: 2 FINISH ON TIME + 1 OPEN => closing 66.67%, completion 66.67%.
        $expectedYear = GreasingKpiCalculator::fromCounts(2, 0, 1);
        $yearly->assertSee($expectedYear['closing_percent'].'%');
    });
});

// =====================================================================
// AUDIT 5 — Finding
// =====================================================================

describe('Audit 5: Finding relationship and independence', function () {
    test('one greasing can have many findings', function () {
        $greasing = auditGreasing();
        $greasing->findings()->createMany([
            ['finding' => 'a', 'status' => 'OPEN'],
            ['finding' => 'b', 'status' => 'OPEN'],
            ['finding' => 'c', 'status' => 'COMPLETED'],
        ]);

        expect($greasing->findings()->count())->toBe(3);
    });

    test('zero findings does not cause an error on execute page or report', function () {
        $admin = auditUser('ADMIN');
        $greasing = auditGreasing();

        $this->actingAs($admin)->get(route('greasings.execute', $greasing))->assertOk();

        $report = $this->actingAs($admin)->get(route('greasing-report.index', [
            'period_type' => 'monthly', 'month' => 8, 'year' => 2026,
        ]));
        $report->assertOk();
        $report->assertSee('[ No Finding ]');
    });

    test('finding count badge on the schedule list matches the real count', function () {
        $admin = auditUser('ADMIN');
        $greasing = auditGreasing();
        $greasing->findings()->createMany([
            ['finding' => 'a', 'status' => 'OPEN'],
            ['finding' => 'b', 'status' => 'OPEN'],
        ]);

        $response = $this->actingAs($admin)->get(route('greasings.index'));
        $response->assertSee('[ 2 Findings ]');
    });

    test('finding report shows findings from greasing_findings for the filtered period', function () {
        $admin = auditUser('ADMIN');
        $greasing = auditGreasing(['plan_date' => '2026-08-01']);
        $greasing->findings()->create(['finding' => 'unique finding text xyz', 'status' => 'OPEN']);

        $response = $this->actingAs($admin)->get(route('greasing-report.index', [
            'period_type' => 'monthly', 'month' => 8, 'year' => 2026,
        ]));

        $response->assertSee('unique finding text xyz');
    });

    test('closing a finding never changes the greasing status', function () {
        $admin = auditUser('ADMIN');
        $greasing = auditGreasing([
            'action_date' => '2026-08-10',
            'status' => 'FINISH ON TIME',
        ]);
        $finding = $greasing->findings()->create(['finding' => 'leak', 'status' => 'OPEN']);

        $this->actingAs($admin)->patch(
            route('greasings.findings.update', [$greasing, $finding]),
            ['status' => 'COMPLETED']
        );

        expect($greasing->fresh()->status)->toBe('FINISH ON TIME');
    });
});

// =====================================================================
// AUDIT 6 — Data integrity
// =====================================================================

describe('Audit 6: Data integrity', function () {
    test('greasing.group_id foreign key rejects a non-existent group at the database level', function () {
        expect(fn () => Greasing::create([
            'group_id' => 999999,
            'order_number' => 'WO-BAD',
            'cycle' => '4W',
            'plan_date' => '2026-08-01',
            'due_date' => '2026-08-15',
            'status' => 'OPEN',
        ]))->toThrow(\Illuminate\Database\QueryException::class);
    });

    test('greasing_finding.greasing_id foreign key rejects a non-existent greasing at the database level', function () {
        expect(fn () => GreasingFinding::create([
            'greasing_id' => 999999,
            'finding' => 'orphan',
            'status' => 'OPEN',
        ]))->toThrow(\Illuminate\Database\QueryException::class);
    });

    test('deleting a greasing cascades to delete its findings', function () {
        $greasing = auditGreasing();
        $finding = $greasing->findings()->create(['finding' => 'x', 'status' => 'OPEN']);

        $greasing->delete();

        expect(GreasingFinding::find($finding->id))->toBeNull();
    });

    test('a machine belongs to exactly one group via a single group_id column, not a pivot', function () {
        expect(Schema::hasTable('group_machine'))->toBeFalse()
            ->and(Schema::hasColumn('machines', 'group_id'))->toBeTrue();
    });

    test('order_number is not a global unique constraint — the same value may be reused across unrelated schedules', function () {
        $groupA = auditGroup();
        $groupB = auditGroup();

        auditGreasing(['group_id' => $groupA->id, 'order_number' => 'SHARED', 'plan_date' => '2026-08-01']);

        // Different group, same order_number and same plan_date: must succeed
        // at the database level (no unique index violation).
        $second = auditGreasing(['group_id' => $groupB->id, 'order_number' => 'SHARED', 'plan_date' => '2026-08-01']);

        expect($second->exists)->toBeTrue()
            ->and(Greasing::where('order_number', 'SHARED')->count())->toBe(2);
    });

    test('the composite schedule-identity index exists on the greasings table', function () {
        $indexNames = collect(Schema::getIndexes('greasings'))->pluck('name');

        expect($indexNames)->toContain('greasings_schedule_identity_index');
    });
});

// =====================================================================
// AUDIT 7 — Security / error disclosure
// =====================================================================

describe('Audit 7: Security and error disclosure', function () {
    test('import exception is logged and never exposes SQLSTATE or stack trace text to the browser', function () {
        $admin = auditUser('ADMIN');

        \Maatwebsite\Excel\Facades\Excel::shouldReceive('import')
            ->once()
            ->andThrow(new \RuntimeException('SQLSTATE[23000]: at /var/www/cmms/app/Imports/GreasingScheduleImport.php'));

        $path = storage_path('framework/testing/boom-audit.csv');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        file_put_contents($path, "order_number,plan_date,group,cycle,pic,remarks\nWO-1,2026-08-01,Line 1,4W,Budi,\n");
        $file = new UploadedFile($path, 'boom-audit.csv', 'text/csv', null, true);

        $response = $this->actingAs($admin)->post(route('greasings.import'), ['file' => $file]);

        $response->assertSessionHasErrors('file');
        $message = session('errors')->first('file');

        expect($message)->not->toContain('SQLSTATE')
            ->and($message)->not->toContain('/var/www')
            ->and($message)->not->toContain('RuntimeException')
            ->and($message)->toBe('Import failed. Please check the file format and data.');
    });

    test('deleting a group with greasing schedules never surfaces a raw exception message', function () {
        $admin = auditUser('ADMIN');
        $greasing = auditGreasing();

        $response = $this->actingAs($admin)->delete(route('groups.destroy', $greasing->group));

        $response->assertSessionHas('error');
        $message = session('error');

        expect($message)->not->toContain('SQLSTATE')
            ->and($message)->not->toContain('Illuminate\\Database')
            ->and($message)->not->toContain('.php');
    });

    test('a 404 for a mismatched finding/greasing pair does not leak internals', function () {
        $admin = auditUser('ADMIN');
        $greasingA = auditGreasing();
        $greasingB = auditGreasing();
        $finding = $greasingA->findings()->create(['finding' => 'x', 'status' => 'OPEN']);

        $response = $this->actingAs($admin)->patch(
            route('greasings.findings.update', [$greasingB, $finding]),
            ['status' => 'COMPLETED']
        );

        $response->assertNotFound();
    });
});
