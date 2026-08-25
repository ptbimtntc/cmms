<?php

use App\Http\Controllers\CostReportController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GreasingController;
use App\Http\Controllers\GreasingReportController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\ImportTemplateController;
use App\Http\Controllers\MachineChecklistController;
use App\Http\Controllers\MachineController;
use App\Http\Controllers\MachineHistoryController;
use App\Http\Controllers\MachineMeasurementController;
use App\Http\Controllers\MachineProblemController;
use App\Http\Controllers\MachineProblemFindingController;
use App\Http\Controllers\MachineReportController;
use App\Http\Controllers\OilAuditController;
use App\Http\Controllers\OilAuditReportController;
use App\Http\Controllers\PMReportController;
use App\Http\Controllers\PMScheduleController;
use App\Http\Controllers\ProblemReportController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\QrScannerController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SparepartController;
use App\Http\Controllers\SparepartReportController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::view('/dashboard-guest', 'dashboard-guest')->name('dashboard-guest');

Route::resource('machine-history', MachineHistoryController::class)->only(['index', 'show']);
Route::get('/machine-history/{machineNumber}/detail/{pmSchedule}', [MachineHistoryController::class, 'detail'])->name('machine-history.detail');
Route::get('/m/{machine}', [MachineHistoryController::class, 'show']);

Route::get('/scan', [QrScannerController::class, 'index'])->name('qr.scan');

Route::middleware('auth')->group(function () {

    Route::get('/profile', [ProfileController::class, 'edit'])
        ->name('profile.edit');

    Route::put('/profile', [ProfileController::class, 'update'])
        ->name('profile.update');

    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])
        ->name('profile.password.update');
});

Route::middleware([
    'auth',
    'role:ADMIN',
])->group(function () {
    Route::resource('users', UserController::class)
        ->only([
            'index',
            'create',
            'store',
            'edit',
            'update',
            'destroy',
        ]);
});

Route::middleware([
    'auth',
    'role:ADMIN,KOORDINATOR WWD,KOORDINATOR BUL',
])->group(function () {
    Route::resource('machines', MachineController::class);
    Route::put('/machines/{machine}', [MachineController::class, 'update'])->name('machines.update');
    Route::get('/machines/{machine}/edit', [MachineController::class, 'edit'])->name('machines.edit');
    Route::get('/machines/import', [MachineController::class, 'importForm'])->name('machines.import.form');
    Route::post('/machines/import', [MachineController::class, 'import'])->name('machines.import');

    Route::resource('groups', GroupController::class);

    Route::resource('greasings', GreasingController::class)->except(['index']);
    Route::post('/greasings/import', [GreasingController::class, 'import'])->name('greasings.import');
    Route::post('/greasings/{greasing}/assign-pic', [GreasingController::class, 'assignPic'])->name('greasings.assign-pic');

    Route::resource('spareparts', SparepartController::class);
    Route::post('/spareparts/import', [SparepartController::class, 'import'])->name('spareparts.import');

    Route::resource('machine-measurements', MachineMeasurementController::class);
    Route::post('/machine-measurements/import', [MachineMeasurementController::class, 'import'])->name('machine-measurements.import');

    Route::resource('machine-problems', MachineProblemController::class);
    Route::post('/machine-problems/import', [MachineProblemController::class, 'import'])->name('machine-problems.import');
    Route::get('/machine-problems/check-duplicate', [MachineProblemController::class, 'checkDuplicate'])->name('machine-problems.checkDuplicate');
    Route::get('/machine-problems/by-type/{type}', [MachineProblemController::class, 'getByType']);

    Route::resource('machine-problem-findings', MachineProblemFindingController::class);
    Route::post('/machine-problem-findings/import', [MachineProblemFindingController::class, 'import'])->name('machine-problem-findings.import');

    Route::resource('machine-checklists', MachineChecklistController::class);
    Route::post('/machine-checklists/import', [MachineChecklistController::class, 'import'])->name('machine-checklists.import');

    Route::resource('pm-schedules', PMScheduleController::class);
    Route::post('/pm-schedules/import', [PMScheduleController::class, 'import'])->name('pm-schedules.import');
    Route::post('/pm-schedules/{pmSchedule}/assign-pic', [PMScheduleController::class, 'assignPic'])->name('pm-schedules.assign-pic');

    Route::get('/import-templates', [ImportTemplateController::class, 'index'])->name('import-templates');
    Route::get('/import-templates/{type}', [ImportTemplateController::class, 'download'])->name('import-templates.download');
});

Route::middleware([
    'auth',
    'role:ADMIN,KOORDINATOR WWD,KOORDINATOR BUL,PIC WWD,PIC BUL',
])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('spareparts', [SparepartController::class, 'index'])->name('spareparts.index');

    Route::resource('pm-schedules', PMScheduleController::class)->except(['create', 'store', 'destroy', 'import']);
    Route::get('/pm-schedules/{pmSchedule}/checklist', [PMScheduleController::class, 'checklist'])->name('pm-schedules.checklist');
    Route::post('/pm-schedules/{pmSchedule}/checklist', [PMScheduleController::class, 'saveChecklist'])->name('pm-schedules.checklist.save');
    Route::get('/pm-schedules/{pmSchedule}/pdf', [PMScheduleController::class, 'exportPdf'])->name('pm-schedules.pdf');

    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/pm', [PMReportController::class, 'index'])->name('reports.pm');
    Route::get('/reports/greasing', [GreasingReportController::class, 'index'])->name('reports.greasing');
    Route::get('/reports/sparepart', [SparepartReportController::class, 'index'])->name('reports.sparepart');
    Route::get('/reports/machine', [MachineReportController::class, 'index'])->name('reports.machine');
    Route::get('/reports/problem', [ProblemReportController::class, 'index'])->name('reports.problem');
    Route::get('/reports/cost', [CostReportController::class, 'index'])->name('reports.cost');

    Route::get('/greasings', [GreasingController::class, 'index'])->name('greasings.index');
    Route::get('/greasings/{greasing}/execute', [GreasingController::class, 'execute'])->name('greasings.execute');
    Route::post('/greasings/{greasing}/execute', [GreasingController::class, 'storeExecution'])->name('greasings.execute.store');
    Route::patch('/greasings/{greasing}/findings/{finding}', [GreasingController::class, 'updateFinding'])->name('greasings.findings.update');
});

Route::middleware([
    'auth',
    'role:ADMIN,KOORDINATOR WWD,PIC WWD',
])->group(function () {
    Route::get('/oil-audits/scan', [OilAuditController::class, 'scan'])->name('oil-audits.scan');
    Route::get('/oil-audits/entry/{machineNumber}', [OilAuditController::class, 'entry'])->name('oil-audits.entry');
    Route::post('/oil-audits', [OilAuditController::class, 'store'])->name('oil-audits.store');
    Route::get('/oil-audit-report', [OilAuditController::class, 'action'])->name('oil-audits.report');
    Route::get('/oil-audit-report/{machineNumber}', [OilAuditController::class, 'history'])->name('oil-audits.history');
    Route::post('/oil-audits/{oilAudit}/follow-up', [OilAuditController::class, 'storeFollowUp'])->name('oil-audits.follow-up.store');

    Route::get('/reports/oil-audit', [OilAuditReportController::class, 'index'])->name('reports.oil-audit');
});

require __DIR__.'/settings.php';
// require __DIR__.'/auth.php'; // pastikan ada
