<?php

require_once __DIR__.'/../Support/predictive_helpers.php';

use App\Exports\PredictiveProblemFindingTemplateExport;
use App\Exports\PredictiveWorkOrderTemplateExport;
use App\Models\PredictiveProblemOption;
use App\Models\PredictiveWorkOrder;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Facades\Excel;

function pvTemplateCsv(array $rows): string
{
    return collect($rows)->map(fn ($row) => implode(',', $row))->implode("\n");
}

test('the import template page lists both Predictive templates', function () {
    $this->actingAs(pvUser('ADMIN'))->get(route('import-templates'))
        ->assertOk()
        ->assertSee('Predictive Work Order')
        ->assertSee(route('import-templates.download', 'predictive-work-order'), false)
        ->assertSee('Predictive Problem and Finding')
        ->assertSee(route('import-templates.download', 'predictive-problem-finding'), false);
});

test('both Predictive templates download for admin and koordinator, not for PIC', function () {
    Excel::fake();

    $this->actingAs(pvUser('ADMIN'))->get(route('import-templates.download', 'predictive-work-order'))->assertOk();
    Excel::assertDownloaded('predictive_work_order_import_template.xlsx', fn ($export) => $export instanceof PredictiveWorkOrderTemplateExport);

    $this->actingAs(pvUser('KOORDINATOR'))->get(route('import-templates.download', 'predictive-problem-finding'))->assertOk();
    Excel::assertDownloaded('predictive_problem_finding_import_template.xlsx', fn ($export) => $export instanceof PredictiveProblemFindingTemplateExport);

    $this->actingAs(pvUser('PIC'))->get(route('import-templates.download', 'predictive-work-order'))->assertForbidden();
});

test('the import modals point to the central template page', function () {
    pvMachine();

    $this->actingAs(pvUser('ADMIN'))->get(route('predictive.index'))->assertSee(route('import-templates.download', 'predictive-work-order'), false);
    $this->actingAs(pvUser('ADMIN'))->get(route('predictive.master.index'))->assertSee(route('import-templates.download', 'predictive-problem-finding'), false);
});

test('the work order template imports cleanly once its machines exist', function () {
    pvMachine('WWD-001');
    pvMachine('WWD-002');
    $rows = (new PredictiveWorkOrderTemplateExport)->array();

    $this->actingAs(pvUser('ADMIN'))->post(route('predictive.import'), [
        'category' => 'VIBRATION',
        'file' => UploadedFile::fake()->createWithContent('t.csv', pvTemplateCsv($rows)),
    ]);

    $result = session('predictive_import_result');
    expect($result['success'])->toBe(2)->and($result['failed'])->toBe(0)->and($result['skipped'])->toBe(0);
    // The template has no Due Date column: both rows get Date + 14 days.
    expect(PredictiveWorkOrder::pluck('due_date')->map->toDateString()->unique()->all())->toBe(['2026-11-24']);
    expect($rows[0])->not->toContain('Due Date');
});

test('the problem and finding template imports cleanly', function () {
    $rows = (new PredictiveProblemFindingTemplateExport)->array();
    roleAttributes('KOORDINATOR WWD');

    $this->actingAs(pvUser('ADMIN'))->post(route('predictive.master.import'), [
        'file' => UploadedFile::fake()->createWithContent('t.csv', pvTemplateCsv($rows)),
    ]);

    $result = session('predictive_master_import_result');
    expect($result['success'])->toBe(4)->and($result['failed'])->toBe(0)
        ->and(PredictiveProblemOption::forScope('VIBRATION', 'WWD')->where('name', 'Bearing aus')->first()->findings()->count())->toBe(2)
        ->and(PredictiveProblemOption::forScope('AIR_LEAKED', 'WWD')->count())->toBe(1);
});
