<?php

use App\Http\Controllers\AreaController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\CostReportController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DashboardGuestController;
use App\Http\Controllers\EmailNotificationSettingController;
use App\Http\Controllers\GreasingController;
use App\Http\Controllers\GreasingReportController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\ImportTemplateController;
use App\Http\Controllers\MachineChecklistController;
use App\Http\Controllers\MachineController;
use App\Http\Controllers\MachineHistoryController;
use App\Http\Controllers\MachineMaintenanceRequirementController;
use App\Http\Controllers\MachineMeasurementController;
use App\Http\Controllers\MachineProblemController;
use App\Http\Controllers\MachineProblemFindingController;
use App\Http\Controllers\MachineQrController;
use App\Http\Controllers\MachineReportController;
use App\Http\Controllers\OilAuditController;
use App\Http\Controllers\OilAuditReportController;
use App\Http\Controllers\PMReportController;
use App\Http\Controllers\PMScheduleController;
use App\Http\Controllers\PMStatusBoardController;
use App\Http\Controllers\PredictiveController;
use App\Http\Controllers\PredictiveOptionController;
use App\Http\Controllers\ProblemReportController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\QrScannerController;
use App\Http\Controllers\SmallPmController;
use App\Http\Controllers\SmallPmRowController;
use App\Http\Controllers\SparepartController;
use App\Http\Controllers\SparepartInjectionController;
use App\Http\Controllers\SparepartPlanningController;
use App\Http\Controllers\SparepartReportController;
use App\Http\Controllers\SyncOperationController;
use App\Http\Controllers\TodayActivityController;
use App\Http\Controllers\TodayActivityMonitorController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WeeklyPlanController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

// FreeDOMS offline-first — Service Worker navigation fallback (Phase 1,
// Task 3). Public, self-contained, no user/server data — see
// resources/views/offline.blade.php and public/sw.js. Not linked from
// anywhere in the UI; only ever reached by the Service Worker when a page
// navigation fails while offline.
Route::view('/offline', 'offline')->name('offline');

Route::get('/dashboard-guest', [DashboardGuestController::class, 'index'])->name('dashboard-guest');

Route::resource('machine-history', MachineHistoryController::class)->only(['index', 'show']);
Route::get('/machine-history/{machineNumber}/detail/{pmSchedule}', [MachineHistoryController::class, 'detail'])->name('machine-history.detail');
Route::get('/m/{machine}', [MachineHistoryController::class, 'show']);

// Public PM Status Board — read-only, no login required (same guest pattern
// as machine-history above). For Production team to check PM OPEN/CLOSED
// status and GAP DAY before requesting a schedule shift. Reuses the
// existing PM Schedule data/status; adds no new PM workflow.
// Area is a route segment (not a filter) so each area gets its own public
// URL and data never mixes across areas. The valid slug list is master
// data (see Area Management), not a compile-time whereIn — a whereIn here
// would go stale under `route:cache` the moment a new area is added, so the
// controller resolves the slug against Area itself and 404s on an unknown
// or inactive one.
Route::get('/pm-status/{area}', [PMStatusBoardController::class, 'index'])
    ->name('pm-status.show');
Route::get('/pm-status/{area}/bulan', [PMStatusBoardController::class, 'month'])
    ->name('pm-status.month');

Route::get('/scan', [QrScannerController::class, 'index'])->name('qr.scan');

// Public operational monitoring board (wall/TV display) — no login, all
// roles + guests. Deliberately kept out of the authenticated sidebar.
// /monitor/data backs the 60s in-place auto-refresh (no full page reload).
Route::get('/monitor', [TodayActivityMonitorController::class, 'index'])->name('monitor');
Route::get('/monitor/data', [TodayActivityMonitorController::class, 'data'])->name('monitor.data');

Route::middleware('auth')->group(function () {

    Route::get('/profile', [ProfileController::class, 'edit'])
        ->name('profile.edit');

    Route::put('/profile', [ProfileController::class, 'update'])
        ->name('profile.update');

    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])
        ->name('profile.password.update');

    // FreeDOMS offline-first sync endpoint (Phase 1, Task 2). A single
    // route carries every transaction type (PM_START, PM_SAVE,
    // PM_CHECKLIST_SAVE, OIL_AUDIT_CREATE, OIL_AUDIT_FOLLOW_UP_SAVE), each
    // with a different role scope — so it sits under plain 'auth' and
    // SyncOperationController enforces the per-type role scope itself,
    // rather than one role:... middleware group covering every case.
    // Purely additive: existing pm-schedules.* / oil-audits.* routes below
    // are untouched and remain the only way to use those features online.
    Route::post('/api/sync', [SyncOperationController::class, 'handle'])->name('api.sync');
    // Read-only latest checklist state, used by the Sync Status conflict resolver.
    Route::get('/api/sync/pm-schedules/{pmSchedule}/checklist-state', [SyncOperationController::class, 'pmChecklistState'])->name('api.sync.pm-checklist-state');

    // Group detail (which machines belong to a group) is read-only and
    // open to every authenticated role, unlike the rest of groups.* which
    // is restricted to ADMIN/KOORDINATOR/SUPERVISOR below. Constrained to
    // digits so it doesn't swallow /groups/create (registered later, below)
    // by capturing "create" as the {group} id and 404ing on binding.
    Route::get('/groups/{group}', [GroupController::class, 'show'])->name('groups.show')->where('group', '[0-9]+');
});

// Virtual Maintenance Environment viewer. NOT public (unlike /monitor):
// authenticated ADMIN / KOORDINATOR / SUPERVISOR only, area-scoped in
// VmeAccess, and 404 unless the VME display switch is ON. Read-only.
Route::middleware(['auth', 'role:ADMIN,KOORDINATOR,SUPERVISOR'])->group(function () {
    Route::get('/vme', [\App\Http\Controllers\VmeController::class, 'index'])->name('vme.index');
    Route::get('/vme/snapshot', [\App\Http\Controllers\VmeController::class, 'snapshot'])->name('vme.snapshot');
    Route::get('/vme/config', [\App\Http\Controllers\VmeController::class, 'config'])->name('vme.config');
    Route::get('/vme/pics/{user}', [\App\Http\Controllers\VmeController::class, 'pic'])->name('vme.pic')->whereNumber('user');
    Route::get('/vme/ai/{key}', [\App\Http\Controllers\VmeController::class, 'ai'])->name('vme.ai')->where('key', '[a-z0-9_]+');
    Route::get('/vme/machines/{machine}', [\App\Http\Controllers\VmeController::class, 'machine'])->name('vme.machine')->whereNumber('machine');
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

    Route::get('/settings/email-notifications', [EmailNotificationSettingController::class, 'edit'])->name('email-notifications.edit');
    Route::put('/settings/email-notifications', [EmailNotificationSettingController::class, 'update'])->name('email-notifications.update');
    Route::post('/settings/email-notifications/test', [EmailNotificationSettingController::class, 'test'])->name('email-notifications.test');

    // Backup & Data Protection (ADMIN only). Files are kept on the private disk
    // and only streamed by the download route; they are never under public/.
    Route::get('/backup', [BackupController::class, 'index'])->name('backup.index');
    Route::post('/backup/database', [BackupController::class, 'storeDatabase'])->name('backup.database.store');
    Route::post('/backup/source', [BackupController::class, 'storeSource'])->name('backup.source.store');
    Route::get('/backup/{backupRun}/download', [BackupController::class, 'download'])->name('backup.download');

    // Maintenance Intelligence Center (ADMIN only). Static overview of the AI
    // agent + Virtual Maintenance Environment workstreams. Deliberately not in
    // the sidebar; reachable only from the landing page navbar for ADMIN.
    Route::view('/maintenance-intelligence', 'maintenance-intelligence.index')->name('maintenance-intelligence.index');
    Route::get('/maintenance-intelligence/sparepart-analyst', [\App\Http\Controllers\MaintenanceIntelligence\SparepartAnalystController::class, 'index'])->name('maintenance-intelligence.sparepart-analyst');
    Route::post('/maintenance-intelligence/sparepart-analyst/run', [\App\Http\Controllers\MaintenanceIntelligence\SparepartAnalystController::class, 'run'])->name('maintenance-intelligence.sparepart-analyst.run');
    Route::get('/maintenance-intelligence/sparepart-analyst/{run}/export', [\App\Http\Controllers\MaintenanceIntelligence\SparepartAnalystController::class, 'export'])->name('maintenance-intelligence.sparepart-analyst.export')->whereNumber('run');

    // Virtual Maintenance Environment management (ADMIN only, inside MIC).
    Route::get('/maintenance-intelligence/vme', [\App\Http\Controllers\MaintenanceIntelligence\VmeAdminController::class, 'index'])->name('maintenance-intelligence.vme');
    Route::get('/maintenance-intelligence/vme/ai-runs', [\App\Http\Controllers\MaintenanceIntelligence\VmeAdminController::class, 'aiRuns'])->name('maintenance-intelligence.vme.ai-runs');
    Route::put('/maintenance-intelligence/vme/settings', [\App\Http\Controllers\MaintenanceIntelligence\VmeAdminController::class, 'updateSettings'])->name('maintenance-intelligence.vme.settings');
    Route::put('/maintenance-intelligence/vme/users/{user}/avatar', [\App\Http\Controllers\MaintenanceIntelligence\VmeAdminController::class, 'updateAvatar'])->name('maintenance-intelligence.vme.avatar')->whereNumber('user');

    Route::resource('areas', AreaController::class)
        ->only([
            'index',
            'create',
            'store',
            'edit',
            'update',
        ]);

    Route::resource('machine-maintenance-requirements', MachineMaintenanceRequirementController::class)
        ->except(['show']);
});

// Weekly PM Plan: Monday-Friday plan per area, composed into the production
// email. Area access is checked inside WeeklyPlanController (User::hasArea).
Route::middleware(['auth', 'role:ADMIN,KOORDINATOR,SUPERVISOR'])->prefix('weekly-plan')->name('weekly-plan.')->group(function () {
    Route::get('/', [WeeklyPlanController::class, 'index'])->name('index');
    Route::get('/{area}/{week}', [WeeklyPlanController::class, 'show'])->name('show');
    Route::put('/plans/{plan}/items', [WeeklyPlanController::class, 'saveItems'])->name('items.save')->whereNumber('plan');
    Route::put('/plans/{plan}/day-limits', [WeeklyPlanController::class, 'saveDayLimits'])->name('day-limits.save')->whereNumber('plan');
    Route::post('/plans/{plan}/autofill', [WeeklyPlanController::class, 'autofill'])->name('autofill')->whereNumber('plan');
    Route::post('/plans/{plan}/day-defaults', [WeeklyPlanController::class, 'restoreDayDefaults'])->name('day-defaults.restore')->whereNumber('plan');
    Route::get('/plans/{plan}/pic-sync', [WeeklyPlanController::class, 'picSyncPreview'])->name('pic-sync.preview')->whereNumber('plan');
    Route::post('/plans/{plan}/pic-sync', [WeeklyPlanController::class, 'picSyncApply'])->name('pic-sync.apply')->whereNumber('plan');
    Route::post('/plans/{plan}/mark-sent', [WeeklyPlanController::class, 'markSent'])->name('mark-sent')->whereNumber('plan');
});

// Small PM: schedules per row of machines, one tab per kind of work. Same
// split as Greasing: everyone (PIC included) lists / starts / executes,
// ADMIN / KOORDINATOR plan. Area checks live in SmallPmController.
Route::middleware(['auth', 'role:ADMIN,KOORDINATOR,SUPERVISOR,PIC'])->prefix('small-pm')->name('small-pm.')->group(function () {
    Route::get('/', [SmallPmController::class, 'index'])->name('index');
    Route::post('/{schedule}/start', [SmallPmController::class, 'start'])->name('start')->whereNumber('schedule');
    Route::get('/{schedule}/execute', [SmallPmController::class, 'execute'])->name('execute')->whereNumber('schedule');
    Route::post('/{schedule}/execute', [SmallPmController::class, 'storeExecution'])->name('execute.store')->whereNumber('schedule');
    Route::patch('/{schedule}/findings/{finding}', [SmallPmController::class, 'updateFinding'])->name('findings.update')->whereNumber(['schedule', 'finding']);
});

Route::middleware(['auth', 'role:ADMIN,KOORDINATOR'])->prefix('small-pm')->name('small-pm.')->group(function () {
    Route::get('/create', [SmallPmController::class, 'create'])->name('create');
    Route::post('/', [SmallPmController::class, 'store'])->name('store');
    Route::post('/import', [SmallPmController::class, 'import'])->name('import');
    Route::post('/generate', [SmallPmController::class, 'generate'])->name('generate');
    Route::get('/{schedule}/edit', [SmallPmController::class, 'edit'])->name('edit')->whereNumber('schedule');
    Route::put('/{schedule}', [SmallPmController::class, 'update'])->name('update')->whereNumber('schedule');
    Route::delete('/{schedule}', [SmallPmController::class, 'destroy'])->name('destroy')->whereNumber('schedule');
    Route::post('/{schedule}/assign-pic', [SmallPmController::class, 'assignPic'])->name('assign-pic')->whereNumber('schedule');
});

// Machine QR Management (Master): QR Code Management + Last PM Override.
// ADMIN / KOORDINATOR only — SUPERVISOR is deliberately NOT in this group.
// Area scoping is enforced inside MachineQrController on every endpoint.
Route::middleware(['auth', 'role:ADMIN,KOORDINATOR'])->prefix('machine-qr')->name('machine-qr.')->group(function () {
    Route::get('/', [MachineQrController::class, 'index'])->name('index');
    Route::get('/ids', [MachineQrController::class, 'ids'])->name('ids');
    Route::get('/machines', [MachineQrController::class, 'machines'])->name('machines');
    Route::get('/count', [MachineQrController::class, 'count'])->name('count');
    Route::post('/export', [MachineQrController::class, 'export'])->name('export');
    Route::get('/{machine}/preview', [MachineQrController::class, 'preview'])->name('preview')->whereNumber('machine');
    Route::get('/{machine}/download', [MachineQrController::class, 'download'])->name('download')->whereNumber('machine');
    Route::post('/overrides', [MachineQrController::class, 'storeOverride'])->name('overrides.store');
    Route::delete('/overrides/{machine}', [MachineQrController::class, 'resetOverride'])->name('overrides.reset')->whereNumber('machine');
    Route::get('/overrides/{machine}/history', [MachineQrController::class, 'history'])->name('overrides.history')->whereNumber('machine');
});

Route::middleware([
    'auth',
    'role:ADMIN,KOORDINATOR,SUPERVISOR',
])->group(function () {
    Route::resource('machines', MachineController::class);
    Route::put('/machines/{machine}', [MachineController::class, 'update'])->name('machines.update');
    Route::get('/machines/{machine}/edit', [MachineController::class, 'edit'])->name('machines.edit');
    Route::get('/machines/import', [MachineController::class, 'importForm'])->name('machines.import.form');
    Route::post('/machines/import', [MachineController::class, 'import'])->name('machines.import');

    Route::resource('groups', GroupController::class)->except(['show']);
    Route::post('/small-pm-rows/import', [SmallPmRowController::class, 'import'])->name('small-pm-rows.import');
    Route::resource('small-pm-rows', SmallPmRowController::class);

    Route::resource('greasings', GreasingController::class)->except(['index']);
    Route::post('/greasings/import', [GreasingController::class, 'import'])->name('greasings.import');
    Route::post('/greasings/{greasing}/assign-pic', [GreasingController::class, 'assignPic'])->name('greasings.assign-pic');

    // Sparepart Planning (Mandatory Sparepart + Estimasi Kebutuhan). The
    // group is ADMIN/KOORDINATOR/SUPERVISOR (PIC excluded); SUPERVISOR is
    // read-only via RoleMiddleware and the controller re-checks every write.
    Route::get('/sparepart-planning', [SparepartPlanningController::class, 'index'])->name('sparepart-planning.index');
    Route::get('/sparepart-planning/export', [SparepartPlanningController::class, 'export'])->name('sparepart-planning.export');
    Route::post('/sparepart-planning/mandatory', [SparepartPlanningController::class, 'store'])->name('sparepart-planning.store');
    Route::get('/sparepart-planning/mandatory/{mandatorySparepart}/edit', [SparepartPlanningController::class, 'edit'])->name('sparepart-planning.edit');
    Route::put('/sparepart-planning/mandatory/{mandatorySparepart}', [SparepartPlanningController::class, 'update'])->name('sparepart-planning.update');
    Route::delete('/sparepart-planning/mandatory/{mandatorySparepart}', [SparepartPlanningController::class, 'destroy'])->name('sparepart-planning.destroy');

    // Sparepart Injection (tab of Master > Sparepart). PIC excluded by this
    // group; SUPERVISOR limited to the GET index/show pages by RoleMiddleware.
    Route::get('/sparepart-injection', [SparepartInjectionController::class, 'index'])->name('sparepart-injection.index');
    Route::post('/sparepart-injection', [SparepartInjectionController::class, 'store'])->name('sparepart-injection.store');
    Route::get('/sparepart-injection/{import}', [SparepartInjectionController::class, 'show'])->name('sparepart-injection.show')->whereNumber('import');

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
    Route::post('/pm-schedules/{pmSchedule}/revert-to-open', [PMScheduleController::class, 'revertToOpen'])->name('pm-schedules.revert-to-open');

    // Manual Activity — start / edit / finish from the Activity Control
    // Panel. ADMIN / KOORDINATOR only; the controller re-checks the role and
    // area scope. PM / Greasing / Oil Audit are not mutated here.
    Route::post('/today-activity/manual', [TodayActivityController::class, 'storeManual'])->name('today-activity.manual.store');
    Route::patch('/today-activity/manual/{manualActivity}', [TodayActivityController::class, 'updateManual'])->name('today-activity.manual.update');
    // Finish = remove ANY activity from the monitor (monitoring only, never
    // completes module work).
    Route::post('/today-activity/finish', [TodayActivityController::class, 'finishActivity'])->name('today-activity.finish');
    // PIC availability — mark a NOT-STARTED PIC inactive for today / clear it.
    Route::post('/today-activity/inactive', [TodayActivityController::class, 'setInactive'])->name('today-activity.inactive.set');
    Route::delete('/today-activity/inactive/{picAvailability}', [TodayActivityController::class, 'clearInactive'])->name('today-activity.inactive.clear');

    Route::get('/import-templates', [ImportTemplateController::class, 'index'])->name('import-templates');
    Route::get('/import-templates/{type}', [ImportTemplateController::class, 'download'])->name('import-templates.download');
});

Route::middleware([
    'auth',
    'role:ADMIN,KOORDINATOR,PIC,SUPERVISOR',
])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/today-activity', [TodayActivityController::class, 'index'])->name('today-activity.index');

    Route::get('spareparts', [SparepartController::class, 'index'])->name('spareparts.index');

    Route::resource('pm-schedules', PMScheduleController::class)->except(['create', 'store', 'destroy', 'import']);
    Route::get('/pm-schedules/{pmSchedule}/checklist', [PMScheduleController::class, 'checklist'])->name('pm-schedules.checklist');
    Route::post('/pm-schedules/{pmSchedule}/checklist', [PMScheduleController::class, 'saveChecklist'])->name('pm-schedules.checklist.save');
    Route::post('/pm-schedules/{pmSchedule}/start', [PMScheduleController::class, 'start'])->name('pm-schedules.start');
    Route::get('/pm-schedules/{pmSchedule}/pdf', [PMScheduleController::class, 'exportPdf'])->name('pm-schedules.pdf');

    Route::get('/reports/pm', [PMReportController::class, 'index'])->name('reports.pm');
    Route::get('/reports/greasing', [GreasingReportController::class, 'index'])->name('reports.greasing');
    Route::get('/reports/sparepart', [SparepartReportController::class, 'index'])->name('reports.sparepart');
    Route::get('/reports/machine', [MachineReportController::class, 'index'])->name('reports.machine');
    Route::get('/reports/problem', [ProblemReportController::class, 'index'])->name('reports.problem');
    Route::get('/reports/cost', [CostReportController::class, 'index'])->name('reports.cost');

    Route::get('/greasings', [GreasingController::class, 'index'])->name('greasings.index');
    Route::post('/greasings/{greasing}/start', [GreasingController::class, 'start'])->name('greasings.start');
    Route::get('/greasings/{greasing}/execute', [GreasingController::class, 'execute'])->name('greasings.execute');
    Route::post('/greasings/{greasing}/execute', [GreasingController::class, 'storeExecution'])->name('greasings.execute.store');
    Route::post('/greasings/{greasing}/findings', [GreasingController::class, 'storeFinding'])->name('greasings.findings.store');
    Route::patch('/greasings/{greasing}/findings/{finding}', [GreasingController::class, 'updateFinding'])->name('greasings.findings.update');
    Route::delete('/greasings/{greasing}/findings/{finding}', [GreasingController::class, 'destroyFinding'])->name('greasings.findings.destroy');
});

// Oil Audit is a permanent WWD-only business rule (see OilAudit::AREA), not
// something that scales to every area — the extra 'area:WWD' middleware
// enforces it declaratively alongside the base-role check, since
// OilAuditController itself has no per-user area check of its own.
Route::middleware([
    'auth',
    'role:ADMIN,KOORDINATOR,PIC,SUPERVISOR',
    'area:WWD',
])->group(function () {
    Route::get('/oil-audits/scan', [OilAuditController::class, 'scan'])->name('oil-audits.scan');
    Route::post('/oil-audits/start', [OilAuditController::class, 'startDaily'])->name('oil-audits.start-daily');
    Route::get('/oil-audits/entry/{machineNumber}', [OilAuditController::class, 'entry'])->name('oil-audits.entry');
    Route::post('/oil-audits', [OilAuditController::class, 'store'])->name('oil-audits.store');
    Route::get('/oil-audit-report', [OilAuditController::class, 'action'])->name('oil-audits.report');
    Route::get('/oil-audit-report/more', [OilAuditController::class, 'actionMore'])->name('oil-audits.report.more');
    Route::post('/oil-audit-report/start', [OilAuditController::class, 'startDailyAction'])->name('oil-audits.report.start-daily');
    Route::get('/oil-audit-report/{machineNumber}', [OilAuditController::class, 'history'])->name('oil-audits.history');
    Route::delete('/oil-audits/{oilAudit}', [OilAuditController::class, 'destroy'])->name('oil-audits.destroy');
    Route::put('/oil-audits/{oilAudit}/condition', [OilAuditController::class, 'updateCondition'])->name('oil-audits.condition.update');
    Route::post('/oil-audits/{oilAudit}/follow-up', [OilAuditController::class, 'storeFollowUp'])->name('oil-audits.follow-up.store');
    Route::put('/oil-audits/{oilAudit}/follow-up', [OilAuditController::class, 'updateFollowUp'])->name('oil-audits.follow-up.update');
    Route::delete('/oil-audits/{oilAudit}/follow-up', [OilAuditController::class, 'destroyFollowUp'])->name('oil-audits.follow-up.destroy');

    Route::get('/reports/oil-audit', [OilAuditReportController::class, 'index'])->name('reports.oil-audit');
});

// Action Predictive (Vibration / Air Leaked WOs + dashboard). Every role that
// can see the module is listed here; SUPERVISOR is view-only through
// RoleMiddleware (only predictive.index / predictive.show are GET pages with
// the .index/.show suffix). Area scope and per-action rights (claim = PIC,
// action = the claiming PIC, reopen = ADMIN or KOORDINATOR, create/edit/delete/import = ADMIN or
// KOORDINATOR of the area) are enforced again inside PredictiveController and
// PredictiveWorkOrder, so a forged URL or POST cannot cross areas.
Route::middleware(['auth', 'role:ADMIN,KOORDINATOR,PIC,SUPERVISOR'])->prefix('predictive')->name('predictive.')->group(function () {
    Route::get('/', [PredictiveController::class, 'index'])->name('index');
    Route::get('/machines', [PredictiveController::class, 'machines'])->name('machines');
    Route::post('/start', [PredictiveController::class, 'start'])->name('start');
    Route::post('/work-orders', [PredictiveController::class, 'store'])->name('store');
    Route::post('/import', [PredictiveController::class, 'import'])->name('import');
    Route::get('/work-orders/{workOrder}', [PredictiveController::class, 'show'])->name('show')->whereNumber('workOrder');
    Route::put('/work-orders/{workOrder}', [PredictiveController::class, 'update'])->name('update')->whereNumber('workOrder');
    Route::delete('/work-orders/{workOrder}', [PredictiveController::class, 'destroy'])->name('destroy')->whereNumber('workOrder');
    Route::post('/work-orders/{workOrder}/claim', [PredictiveController::class, 'claim'])->name('claim')->whereNumber('workOrder');
    Route::post('/work-orders/{workOrder}/action', [PredictiveController::class, 'storeAction'])->name('action')->whereNumber('workOrder');
    Route::post('/work-orders/{workOrder}/reopen', [PredictiveController::class, 'reopen'])->name('reopen')->whereNumber('workOrder');
});

// Predictive Problem / Finding master (one list per category and area).
// ADMIN manages every area, KOORDINATOR only their own (re-checked per write
// in PredictiveOptionController). SUPERVISOR, PIC and GUEST are not in this group.
Route::middleware(['auth', 'role:ADMIN,KOORDINATOR'])->prefix('predictive/master')->name('predictive.master.')->group(function () {
    Route::get('/', [PredictiveOptionController::class, 'index'])->name('index');
    Route::post('/import', [PredictiveOptionController::class, 'import'])->name('import');
    Route::post('/problems', [PredictiveOptionController::class, 'storeProblem'])->name('problems.store');
    Route::put('/problems/{problem}', [PredictiveOptionController::class, 'updateProblem'])->name('problems.update')->whereNumber('problem');
    Route::delete('/problems/{problem}', [PredictiveOptionController::class, 'destroyProblem'])->name('problems.destroy')->whereNumber('problem');
    Route::post('/problems/{problem}/findings', [PredictiveOptionController::class, 'storeFinding'])->name('findings.store')->whereNumber('problem');
    Route::put('/findings/{finding}', [PredictiveOptionController::class, 'updateFinding'])->name('findings.update')->whereNumber('finding');
    Route::delete('/findings/{finding}', [PredictiveOptionController::class, 'destroyFinding'])->name('findings.destroy')->whereNumber('finding');
});

require __DIR__.'/settings.php';
// require __DIR__.'/auth.php'; // pastikan ada
