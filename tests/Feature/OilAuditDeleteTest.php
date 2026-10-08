<?php

use App\Models\Area;
use App\Models\Machine;
use App\Models\OilAudit;
use App\Models\User;

function deleteAuditFixture(string $area = 'WWD'): OilAudit
{
    $machine = Machine::create([
        'machine_number' => 'MC-'.uniqid(),
        'area' => $area,
        'machine_type' => oilAuditMachineType(),
        'status' => 'ACTIVE',
    ]);

    return OilAudit::create([
        'machine_id' => $machine->id,
        'machine_number' => $machine->machine_number,
        'machine_type' => $machine->machine_type,
        'area' => $machine->area,
        'condition' => 'KRITIS',
        'audited_by_name' => 'Tester',
        'audited_at' => now(),
    ]);
}

test('admin and koordinator WWD can delete an oil audit finding', function (string $role) {
    $user = User::factory()->create(['role' => $role]);
    if ($role === User::ROLE_KOORDINATOR) {
        $user->update(['area_id' => Area::firstOrCreate(['name' => 'WWD'], ['slug' => 'wwd', 'is_active' => true])->id]);
    }
    $audit = deleteAuditFixture();

    $this->actingAs($user)->delete(route('oil-audits.destroy', $audit))
        ->assertRedirect()
        ->assertSessionHas('success');

    $this->assertModelMissing($audit);
})->with([User::ROLE_ADMIN, User::ROLE_KOORDINATOR]);

test('deleting an oil audit also removes its follow-up', function () {
    $admin = User::factory()->admin()->create();
    $audit = deleteAuditFixture();
    $followUp = $audit->followUp()->create([
        'pic_name' => 'PIC',
        'problem' => 'x',
        'action_taken' => 'y',
        'actioned_at' => now(),
    ]);

    $this->actingAs($admin)->delete(route('oil-audits.destroy', $audit))->assertRedirect();

    $this->assertModelMissing($followUp);
});

test('PIC, supervisor and koordinator of another area cannot delete', function () {
    $audit = deleteAuditFixture();
    $pic = User::factory()->pic()->forArea('WWD')->create();
    $supervisor = User::factory()->supervisor()->create();
    $koordinatorBul = User::factory()->koordinator()->forArea('BUL')->create();

    foreach ([$pic, $supervisor, $koordinatorBul] as $user) {
        $this->actingAs($user)->delete(route('oil-audits.destroy', $audit))->assertForbidden();
    }

    $this->assertModelExists($audit);
});

test('delete button shows on the action page only for admin and koordinator', function () {
    $audit = deleteAuditFixture();
    $url = route('oil-audits.destroy', $audit);
    $wwd = Area::firstOrCreate(['name' => 'WWD'], ['slug' => 'wwd', 'is_active' => true]);

    $this->actingAs(User::factory()->admin()->create())->get(route('oil-audits.report'))->assertSee($url, false);
    $this->actingAs(User::factory()->koordinator()->forArea($wwd)->create())->get(route('oil-audits.report'))->assertSee($url, false);
    $this->actingAs(User::factory()->pic()->forArea($wwd)->create())->get(route('oil-audits.report'))->assertOk()->assertDontSee($url, false);
    $this->actingAs(User::factory()->supervisor()->create())->get(route('oil-audits.report'))->assertOk()->assertDontSee($url, false);
});

test('history page shows a delete button next to save only for admin and koordinator', function () {
    $audit = deleteAuditFixture();
    $wwd = Area::firstOrCreate(['name' => 'WWD'], ['slug' => 'wwd', 'is_active' => true]);
    $url = route('oil-audits.history', $audit->machine_number);
    $form = 'id="delete-audit-'.$audit->id.'"';
    $button = 'form="delete-audit-'.$audit->id.'"';

    foreach ([User::factory()->admin()->create(), User::factory()->koordinator()->forArea($wwd)->create()] as $user) {
        $this->actingAs($user)->get($url)->assertOk()->assertSee($form, false)->assertSee($button, false)
            ->assertSee(route('oil-audits.destroy', $audit), false);
    }

    foreach ([User::factory()->pic()->forArea($wwd)->create(), User::factory()->supervisor()->create()] as $user) {
        $this->actingAs($user)->get($url)->assertOk()->assertDontSee($form, false)->assertDontSee($button, false);
    }
});
