<?php

use App\Models\Machine;
use App\Models\MachineChecklist;
use App\Models\MachineMeasurement;
use App\Models\MachineProblem;
use App\Models\MachineProblemFinding;
use App\Models\PMSchedule;
use App\Models\Sparepart;
use App\Models\User;
use Illuminate\Http\UploadedFile;

function makeImportCsv(string $filename, array $rows): UploadedFile
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

function importAdmin(): User
{
    return User::factory()->create(['role' => User::ROLE_ADMIN]);
}

test('machines import reports imported, duplicate, and skipped counts', function () {
    $admin = importAdmin();
    Machine::create(['machine_number' => 'MC-DUP', 'machine_type' => 'TypeX', 'area' => 'WWD']);

    $csv = makeImportCsv('machines.csv', [
        ['machine_number', 'machine_type', 'area', 'status', 'pm_cycle_value', 'pm_cycle_unit'],
        ['MC-DUP', 'TypeX', 'WWD', 'ACTIVE', '', ''],
        ['MC-NEW', 'TypeY', 'BUL', 'ACTIVE', '90', 'DAY'],
        ['', 'TypeZ', 'BUL', 'ACTIVE', '', ''],
    ]);

    $this->actingAs($admin)->post(route('machines.import'), ['file' => $csv])->assertRedirect();

    expect(session('machines_import_result'))->toMatchArray(['imported' => 1, 'duplicate' => 1, 'skipped' => 1]);
    expect(Machine::where('machine_number', 'MC-NEW')->exists())->toBeTrue();
});

test('spareparts import reports imported, duplicate, and skipped counts', function () {
    $admin = importAdmin();
    Sparepart::create(['material_number' => 'SP-DUP', 'description' => 'Existing part']);

    $csv = makeImportCsv('spareparts.csv', [
        ['material_number', 'location', 'description', 'remarks', 'stock', 'unit', 'rop', 'mrp_type', 'price', 'status', 'machine_type', 'segment', 'pdt'],
        ['SP-DUP', 'LOC1', 'Existing part updated', '', '', '', '', '', '', '', '', '', ''],
        ['SP-NEW', 'LOC2', 'New part', '', '10', 'PCS', '5', 'ZREL', '100', 'ACTIVE', '', '', ''],
        ['', 'LOC3', 'No material number', '', '', '', '', '', '', '', '', '', ''],
    ]);

    $this->actingAs($admin)->post(route('spareparts.import'), ['file' => $csv])->assertRedirect();

    expect(session('spareparts_import_result'))->toMatchArray(['imported' => 1, 'duplicate' => 1, 'skipped' => 1]);
    expect(Sparepart::where('material_number', 'SP-NEW')->exists())->toBeTrue();
});

test('machine measurements import reports imported, duplicate, and skipped counts', function () {
    $admin = importAdmin();
    MachineMeasurement::create(['machine_type' => 'MT-DUP', 'measurement_item' => 'Vibration']);

    $csv = makeImportCsv('machine-measurements.csv', [
        ['machine_type', 'measurement_item', 'standard', 'unit'],
        ['MT-DUP', 'Vibration', '', ''],
        ['MT-NEW', 'Temperature', '80', 'C'],
        ['', '', '80', ''],
    ]);

    $this->actingAs($admin)->post(route('machine-measurements.import'), ['file' => $csv])->assertRedirect();

    expect(session('machine_measurements_import_result'))->toMatchArray(['imported' => 1, 'duplicate' => 1, 'skipped' => 1]);
    expect(MachineMeasurement::where('machine_type', 'MT-NEW')->exists())->toBeTrue();
});

test('machine problems import reports imported, duplicate, and skipped counts', function () {
    $admin = importAdmin();
    MachineProblem::create(['machine_type' => 'MT-DUP', 'category' => 'Mechanical', 'problem' => 'Bearing wear']);

    $csv = makeImportCsv('machine-problems.csv', [
        ['machine_type', 'category', 'problem'],
        ['MT-DUP', 'Mechanical', 'Bearing wear'],
        ['MT-NEW', 'Electrical', 'Short circuit'],
        ['', '', 'Something'],
    ]);

    $this->actingAs($admin)->post(route('machine-problems.import'), ['file' => $csv])->assertRedirect();

    expect(session('machine_problems_import_result'))->toMatchArray(['imported' => 1, 'duplicate' => 1, 'skipped' => 1]);
    expect(MachineProblem::where('machine_type', 'MT-NEW')->exists())->toBeTrue();
});

test('machine problem findings import reports imported, duplicate, and skipped counts', function () {
    $admin = importAdmin();
    MachineProblemFinding::create(['category' => 'Mechanical', 'finding' => 'Loose bolt']);

    $csv = makeImportCsv('machine-problem-findings.csv', [
        ['category', 'finding'],
        ['Mechanical', 'Loose bolt'],
        ['Electrical', 'Burnt wire'],
        ['', 'Finding without a category'],
    ]);

    $this->actingAs($admin)->post(route('machine-problem-findings.import'), ['file' => $csv])->assertRedirect();

    expect(session('machine_problem_findings_import_result'))->toMatchArray(['imported' => 1, 'duplicate' => 1, 'skipped' => 1]);
    expect(MachineProblemFinding::where('finding', 'Burnt wire')->exists())->toBeTrue();
});

test('machine checklists import reports imported, duplicate, and skipped counts', function () {
    $admin = importAdmin();
    MachineChecklist::create([
        'machine_type' => 'MT-DUP',
        'section' => 'Sec1',
        'checklist_item' => 'Item1',
        'section_order' => 1,
        'item_order' => 1,
        'maintenance_type' => 'check',
    ]);

    $csv = makeImportCsv('machine-checklists.csv', [
        ['machine_type', 'section', 'section_order', 'checklist_item', 'maintenance_type', 'item_order'],
        ['MT-DUP', 'Sec1', '1', 'Item1', 'check', '1'],
        ['MT-NEW', 'Sec2', '1', 'Item2', 'check', '1'],
        ['', 'Sec3', '1', 'Item3', 'check', '1'],
    ]);

    $this->actingAs($admin)->post(route('machine-checklists.import'), ['file' => $csv])->assertRedirect();

    expect(session('machine_checklists_import_result'))->toMatchArray(['imported' => 1, 'duplicate' => 1, 'skipped' => 1]);
    expect(MachineChecklist::where('machine_type', 'MT-NEW')->exists())->toBeTrue();
});

test('pm schedules import reports imported, duplicate, and skipped counts', function () {
    $admin = importAdmin();
    $machine = Machine::create(['machine_number' => 'PM-MC1', 'machine_type' => 'TypeA', 'area' => 'WWD']);

    PMSchedule::create([
        'machine_id' => $machine->id,
        'machine_number' => 'PM-MC1',
        'machine_type' => 'TypeA',
        'area' => 'WWD',
        'plan_date' => '2026-08-01',
        'plan_month' => 'August',
        'plan_year' => 2026,
        'order_number' => 'WO-DUP',
        'status' => 'OPEN',
    ]);

    $csv = makeImportCsv('pm-schedules.csv', [
        ['machine_number', 'machine_type', 'plan_year', 'plan_month', 'plan_date', 'order_number', 'status'],
        ['PM-MC1', 'TypeA', '2026', 'August', '01-08-26', 'WO-DUP', 'OPEN'],
        ['PM-MC1', 'TypeA', '2026', 'August', '02-08-26', 'WO-NEW', 'OPEN'],
        ['UNKNOWN-MC', 'TypeA', '2026', 'August', '03-08-26', 'WO-SKIP', 'OPEN'],
    ]);

    $this->actingAs($admin)->post(route('pm-schedules.import'), ['file' => $csv])->assertRedirect();

    expect(session('pm_schedules_import_result'))->toMatchArray(['imported' => 1, 'duplicate' => 1, 'skipped' => 1]);
    expect(PMSchedule::where('order_number', 'WO-NEW')->exists())->toBeTrue();
});
