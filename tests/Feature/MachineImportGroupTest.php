<?php

use App\Models\Group;
use App\Models\Machine;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

function machineImportCsv(string $filename, array $rows): UploadedFile
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

function machineImportAdmin(): User
{
    return User::factory()->create(['role' => User::ROLE_ADMIN]);
}

$header = ['machine_number', 'machine_type', 'area', 'status', 'pm_cycle_value', 'pm_cycle_unit', 'group'];

test('group valid resolves to the matching group_id', function () use ($header) {
    $admin = machineImportAdmin();
    $groupA = Group::create(['name' => 'GROUP A']);

    $csv = machineImportCsv('valid-group.csv', [
        $header,
        ['30001', 'SHUIXING', 'WWD', 'ACTIVE', '', '', 'GROUP A'],
    ]);

    $this->actingAs($admin)->post(route('machines.import'), ['file' => $csv]);

    $machine = Machine::where('machine_number', '30001')->first();
    expect($machine)->not->toBeNull()
        ->and($machine->group_id)->toBe($groupA->id);
});

test('group empty on a new machine imports successfully with a null group_id', function () use ($header) {
    $admin = machineImportAdmin();

    $csv = machineImportCsv('empty-group-new.csv', [
        $header,
        ['30002', 'SHUIXING', 'WWD', 'ACTIVE', '', '', ''],
    ]);

    $this->actingAs($admin)->post(route('machines.import'), ['file' => $csv]);

    $machine = Machine::where('machine_number', '30002')->first();
    expect($machine)->not->toBeNull()
        ->and($machine->group_id)->toBeNull();

    expect(session('machines_import_result'))->toMatchArray(['imported' => 1, 'duplicate' => 0, 'skipped' => 0]);
});

test('group empty on an existing machine does not clear its current group', function () use ($header) {
    $admin = machineImportAdmin();
    $groupB = Group::create(['name' => 'GROUP B']);
    Machine::create([
        'machine_number' => '30003',
        'machine_type' => 'SHUIXING',
        'area' => 'WWD',
        'status' => 'ACTIVE',
        'group_id' => $groupB->id,
    ]);

    $csv = machineImportCsv('empty-group-existing.csv', [
        $header,
        ['30003', 'SHUIXING', 'WWD', 'ACTIVE', '', '', ''],
    ]);

    $this->actingAs($admin)->post(route('machines.import'), ['file' => $csv]);

    $machine = Machine::where('machine_number', '30003')->first();
    expect($machine->group_id)->toBe($groupB->id);
});

test('group not found causes the row to be skipped and not written to the db', function () use ($header) {
    $admin = machineImportAdmin();

    $csv = machineImportCsv('unknown-group.csv', [
        $header,
        ['30004', 'SHUIXING', 'WWD', 'ACTIVE', '', '', 'GROUP X'],
    ]);

    $this->actingAs($admin)->post(route('machines.import'), ['file' => $csv]);

    expect(Machine::where('machine_number', '30004')->exists())->toBeFalse();

    $result = session('machines_import_result');
    expect($result['imported'])->toBe(0)
        ->and($result['skipped'])->toBe(1)
        ->and($result['errors'])->toContain('Row 2: Group "GROUP X" not found.');
});

test('group matching is case-insensitive', function () use ($header) {
    $admin = machineImportAdmin();
    $groupA = Group::create(['name' => 'GROUP A']);

    $csv = machineImportCsv('lowercase-group.csv', [
        $header,
        ['30005', 'SHUIXING', 'WWD', 'ACTIVE', '', '', 'group a'],
    ]);

    $this->actingAs($admin)->post(route('machines.import'), ['file' => $csv]);

    $machine = Machine::where('machine_number', '30005')->first();
    expect($machine->group_id)->toBe($groupA->id);
});

test('group name whitespace is trimmed before matching', function () use ($header) {
    $admin = machineImportAdmin();
    $groupA = Group::create(['name' => 'GROUP A']);

    $csv = machineImportCsv('whitespace-group.csv', [
        $header,
        ['30006', 'SHUIXING', 'WWD', 'ACTIVE', '', '', '  GROUP A  '],
    ]);

    $this->actingAs($admin)->post(route('machines.import'), ['file' => $csv]);

    $machine = Machine::where('machine_number', '30006')->first();
    expect($machine->group_id)->toBe($groupA->id);
});

test('mixed import processes each row independently without failing the whole file', function () use ($header) {
    $admin = machineImportAdmin();
    $groupA = Group::create(['name' => 'GROUP A']);
    $groupB = Group::create(['name' => 'GROUP B']);

    $csv = machineImportCsv('mixed.csv', [
        $header,
        ['30001', 'SHUIXING', 'WWD', 'ACTIVE', '', '', 'GROUP A'],
        ['30002', 'SHUIXING', 'WWD', 'ACTIVE', '', '', ''],
        ['30003', 'SHUIXING', 'WWD', 'ACTIVE', '', '', 'GROUP X'],
        ['30004', 'SHUIXING', 'BUL', 'ACTIVE', '', '', 'GROUP B'],
    ]);

    $this->actingAs($admin)->post(route('machines.import'), ['file' => $csv]);

    expect(Machine::where('machine_number', '30001')->first()?->group_id)->toBe($groupA->id)
        ->and(Machine::where('machine_number', '30002')->first()?->group_id)->toBeNull()
        ->and(Machine::where('machine_number', '30003')->exists())->toBeFalse()
        ->and(Machine::where('machine_number', '30004')->first()?->group_id)->toBe($groupB->id);

    $result = session('machines_import_result');
    expect($result['imported'])->toBe(3)
        ->and($result['skipped'])->toBe(1);
});

test('no group column at all still imports machines with a null group_id, unrelated behavior unaffected', function () {
    $admin = machineImportAdmin();

    $csv = machineImportCsv('no-group-column.csv', [
        ['machine_number', 'machine_type', 'area', 'status', 'pm_cycle_value', 'pm_cycle_unit'],
        ['30007', 'SHUIXING', 'WWD', 'ACTIVE', 104, 'WEEK'],
    ]);

    $this->actingAs($admin)->post(route('machines.import'), ['file' => $csv]);

    $machine = Machine::where('machine_number', '30007')->first();
    expect($machine)->not->toBeNull()
        ->and($machine->group_id)->toBeNull()
        ->and($machine->pm_cycle_value)->toBe(104)
        ->and($machine->pm_cycle_unit)->toBe('WEEK');
});

test('a group filled on an existing machine updates its group_id', function () use ($header) {
    $admin = machineImportAdmin();
    $groupA = Group::create(['name' => 'GROUP A']);
    $groupB = Group::create(['name' => 'GROUP B']);
    Machine::create([
        'machine_number' => '30008',
        'machine_type' => 'SHUIXING',
        'area' => 'WWD',
        'status' => 'ACTIVE',
        'group_id' => $groupA->id,
    ]);

    $csv = machineImportCsv('reassign-group.csv', [
        $header,
        ['30008', 'SHUIXING', 'WWD', 'ACTIVE', '', '', 'GROUP B'],
    ]);

    $this->actingAs($admin)->post(route('machines.import'), ['file' => $csv]);

    expect(Machine::where('machine_number', '30008')->first()->group_id)->toBe($groupB->id);
});

test('import never creates a new group automatically', function () use ($header) {
    $admin = machineImportAdmin();

    $csv = machineImportCsv('no-auto-create.csv', [
        $header,
        ['30009', 'SHUIXING', 'WWD', 'ACTIVE', '', '', 'BRAND NEW GROUP'],
    ]);

    $this->actingAs($admin)->post(route('machines.import'), ['file' => $csv]);

    expect(Group::where('name', 'BRAND NEW GROUP')->exists())->toBeFalse()
        ->and(Machine::where('machine_number', '30009')->exists())->toBeFalse();
});

test('import result details are shown on the machine index page', function () use ($header) {
    $admin = machineImportAdmin();

    $csv = machineImportCsv('shown-on-index.csv', [
        $header,
        ['30010', 'SHUIXING', 'WWD', 'ACTIVE', '', '', 'GROUP X'],
    ]);

    $this->actingAs($admin)->post(route('machines.import'), ['file' => $csv]);
    $response = $this->actingAs($admin)->get(route('machines.index'));

    $response->assertOk();
    $response->assertSee('Row 2: Group &quot;GROUP X&quot; not found.', false);
});

test('pic and guest roles cannot import machines', function (string $role) use ($header) {
    $user = User::factory()->create(['role' => $role]);

    $csv = machineImportCsv('forbidden-'.Str::slug($role).'.csv', [
        $header,
        ['30011', 'SHUIXING', 'WWD', 'ACTIVE', '', '', ''],
    ]);

    $this->actingAs($user)->post(route('machines.import'), ['file' => $csv])->assertForbidden();
    expect(Machine::where('machine_number', '30011')->exists())->toBeFalse();
})->with(['PIC WWD', 'PIC BUL', 'GUEST']);
