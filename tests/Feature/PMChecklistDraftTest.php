<?php

use App\Models\MachineChecklist;
use App\Models\PMChecklist;
use App\Models\User;

function draftFixture(): array
{
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $machine = checklistMachine();
    $pm = checklistSchedule($machine);
    $item = MachineChecklist::create(['machine_type' => $machine->machine_type, 'section' => 'General', 'checklist_item' => 'Clean Body', 'maintenance_type' => 'clean']);

    return [$admin, $pm, $item];
}

function draftPayload($item, string $clean, string $remarks): array
{
    return ['checklists' => [['machine_checklist_id' => $item->id, 'clean' => $clean, 'check' => 'YES', 'lubrication' => 'YES', 'replace' => 'NO', 'remarks' => $remarks]]];
}

it('keeps checklist input in a session draft when Fill PM is incomplete and overrides the DB', function () {
    [$admin, $pm, $item] = draftFixture();
    PMChecklist::create(['pm_schedule_id' => $pm->id, 'machine_checklist_id' => $item->id, 'clean' => 'NO', 'check' => 'NO', 'lubrication' => 'NO', 'replace' => 'NO']);

    test()->actingAs($admin)->post(route('pm-schedules.checklist.save', $pm), draftPayload($item, 'YES', 'draft-note'))
        ->assertRedirect(route('pm-schedules.edit', $pm->id));

    expect(PMChecklist::where('pm_schedule_id', $pm->id)->first()->clean)->toBe('NO');

    test()->actingAs($admin)->get(route('pm-schedules.checklist', $pm))
        ->assertSee('draft-note', false)
        ->assertViewHas('pmChecklists', fn ($c) => $c[$item->id]->clean === 'YES');
});

it('does not leak a draft to another PM', function () {
    [$admin, $pm, $item] = draftFixture();
    $other = checklistSchedule($pm->machine);

    test()->actingAs($admin)->post(route('pm-schedules.checklist.save', $pm), draftPayload($item, 'YES', 'x'));

    test()->actingAs($admin)->get(route('pm-schedules.checklist', $other))
        ->assertViewHas('pmChecklists', fn ($c) => $c->isEmpty());
});

it('clears the draft after a successful save', function () {
    [$admin, $pm, $item] = draftFixture();

    test()->actingAs($admin)->post(route('pm-schedules.checklist.save', $pm), draftPayload($item, 'YES', 'x'));
    expect(session()->has('pm_checklist_draft.'.$pm->id))->toBeTrue();

    makeChecklistEligible($pm->fresh());
    test()->actingAs($admin)->post(route('pm-schedules.checklist.save', $pm), draftPayload($item, 'YES', 'x'))
        ->assertRedirect(route('pm-schedules.index'));

    expect(session()->has('pm_checklist_draft.'.$pm->id))->toBeFalse();
    expect(PMChecklist::where('pm_schedule_id', $pm->id)->count())->toBe(1);
});

it('stays on the checklist page (not Fill PM) when only the checklist itself is incomplete', function () {
    [$admin, $pm, $item] = draftFixture();
    makeChecklistEligible($pm->fresh()); // Fill PM prerequisites are satisfied

    $response = test()->actingAs($admin)->post(
        route('pm-schedules.checklist.save', $pm),
        ['checklists' => [['machine_checklist_id' => $item->id, 'clean' => 'NO', 'check' => 'YES', 'lubrication' => 'YES', 'replace' => 'NO', 'remarks' => '']]]
    );

    $response->assertRedirect(route('pm-schedules.checklist', $pm->id));
    expect(session('warning'))->toBe('Checklist (1 item belum diisi)')
        ->and(session('warning'))->not->toContain('Fill PM belum lengkap');

    // Nothing was persisted, but the input is kept for re-display.
    expect(PMChecklist::where('pm_schedule_id', $pm->id)->count())->toBe(0)
        ->and(session()->has('pm_checklist_draft.'.$pm->id))->toBeTrue();
});
