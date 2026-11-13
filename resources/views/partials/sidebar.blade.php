    {{-- The `sidebar` Alpine store is registered in layouts/app.blade.php. Below lg the
         drawer is inert while closed, so Tab never lands on links that are off-screen. --}}
    <aside id="app-sidebar" x-cloak
        :class="$store.sidebar.open ? 'translate-x-0' : '-translate-x-full'"
        :inert="!$store.sidebar.open && !$store.sidebar.desktop"
        @keydown.escape.window="$store.sidebar.open = false"
        aria-label="Menu utama"
        class="fixed inset-y-0 left-0 z-40 flex h-dvh w-72 flex-col
       border-r border-sidebar-border bg-sidebar text-sidebar-foreground
       motion-safe:transition-transform motion-safe:duration-300
       lg:translate-x-0">

        <div class="px-4 pb-4 pt-5">
            <div class="flex items-center justify-between gap-2">

                <a href="{{ route('home') }}" title="Go to landing page"
                    class="flex min-h-11 min-w-0 items-center gap-3 rounded-lg transition hover:opacity-80">
                    <x-brand-logo :framed="false" class="h-7 w-auto shrink-0" aria-hidden="true" />
                    <span class="min-w-0">
                        <span class="block font-signage text-lg font-semibold leading-none text-sidebar-foreground">FreeDOMS</span>
                        {{-- Hidden in the mobile drawer, where it would wrap to three lines beside the close button. --}}
                        <span class="mt-1 hidden text-xs text-sidebar-muted lg:block">Preventive Maintenance System</span>
                    </span>
                </a>

                <button type="button" @click="$store.sidebar.open = false"
                    class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg text-sidebar-muted transition hover:bg-sidebar-hover hover:text-sidebar-foreground active:bg-sidebar-border lg:hidden"
                    aria-label="Close menu">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5" aria-hidden="true">
                        <path d="M18 6 6 18" />
                        <path d="m6 6 12 12" />
                    </svg>
                </button>
            </div>
        </div>

        @php
        $authUser = auth()->user();
        $isAdmin = $authUser->isAdmin();
        $isKoordinator = $authUser->isKoordinator();
        $isPic = $authUser->isPic();
        $isSupervisor = $authUser->isSupervisor();
        // Oil Audit is a permanent WWD-only business rule (see
        // OilAudit::AREA), not general area authorization, hasArea()
        // already returns true unconditionally for ADMIN, so this one flag
        // covers every role that may see WWD-only menu items.
        $hasWwdArea = $authUser->hasArea('WWD');

        // Machine History Detail opened via Report PM's View button keeps
        // the PM Report environment active in the sidebar (and Machine
        // History's own active state suppressed) instead of switching to
        // Machine History, navigation context carried via ?from=report-pm
        // from reports/pm/index.blade.php. Any other route, or this same
        // route without that query param, is unaffected.
        $cameFromReportPm = request()->routeIs('machine-history.detail') && request()->query('from') === 'report-pm';

        // Same idea for Report Machine's View button (?from=report-machine):
        // the Machine History pages it opens keep Report Machine active.
        $cameFromReportMachine = request()->routeIs('machine-history.show', 'machine-history.detail')
            && request()->query('from') === 'report-machine';

        $machineActive = request()->routeIs('machines.*');
        $groupActive = request()->routeIs('groups.*');
        $smallPmRowActive = request()->routeIs('small-pm-rows.*');
        $machineQrActive = request()->routeIs('machine-qr.*');
        $sparepartActive = request()->routeIs('spareparts.*', 'sparepart-injection.*');
        $sparepartPlanningActive = request()->routeIs('sparepart-planning.*');
        $reportActive = request()->routeIs('reports.*') || $cameFromReportPm || $cameFromReportMachine;
        $userActive = request()->routeIs('users.*');
        $areaActive = request()->routeIs('areas.*');
        $maintenanceRequirementActive = request()->routeIs('machine-maintenance-requirements.*');
        $dashboardActive = request()->routeIs('dashboard');
        $todayActivityActive = request()->routeIs('today-activity.*');
        $pmScheduleActive = request()->routeIs('pm-schedules.*');
        $weeklyPlanActive = request()->routeIs('weekly-plan.*');
        $emailNotificationActive = request()->routeIs('email-notifications.*');
        $backupActive = request()->routeIs('backup.*');
        $importTemplateActive = request()->routeIs('import-templates');
        $measurementActive = request()->routeIs('machine-measurements.*');
        $checklistActive = request()->routeIs('machine-checklists.*');
        $problemCategoryActive = request()->routeIs('machine-problems.*');
        $problemFindingsActive = request()->routeIs('machine-problem-findings.*');
        $oilAuditActive = request()->routeIs('oil-audits.scan');
        $oilAuditActionActive = request()->routeIs('oil-audits.report');
        $predictiveMasterActive = request()->routeIs('predictive.master.*');
        $predictiveActive = request()->routeIs('predictive.*') && ! $predictiveMasterActive;
        $greasingActive = request()->routeIs('greasings.*');
        $smallPmActive = request()->routeIs('small-pm.*');
        $profileActive = request()->routeIs('profile.*');

        // Individual report routes, each links straight to its own report
        // page from the sidebar now (no more intermediate Report Center hub).
        $reportsPmActive = request()->routeIs('reports.pm') || $cameFromReportPm;
        $reportsGreasingActive = request()->routeIs('reports.greasing');
        $reportsOilAuditActive = request()->routeIs('reports.oil-audit');
        $reportsSparepartActive = request()->routeIs('reports.sparepart');
        $reportsMachineActive = request()->routeIs('reports.machine') || $cameFromReportMachine;
        $reportsProblemActive = request()->routeIs('reports.problem');
        $reportsCostActive = request()->routeIs('reports.cost');

        // Oil Audit is a WWD-only module (same rule as its own route
        // middleware), hide its report link for roles that could never
        // open it, same rule the old Report Center hub used.
        $oilAuditReportEligible = $hasWwdArea;

        @endphp
        @php
        $machineHistoryActive = request()->routeIs('machine-history.*') && ! $cameFromReportPm && ! $cameFromReportMachine;
        @endphp
        <nav class="flex-1 overflow-y-auto overscroll-contain px-3 pb-4 text-sm" x-data="{
            openGroup: '{{ $dashboardActive || $todayActivityActive || $pmScheduleActive || $weeklyPlanActive || $oilAuditActive || $oilAuditActionActive || $predictiveActive || $greasingActive || $smallPmActive ? 'main' :
                        ($machineActive || $machineQrActive || $groupActive || $smallPmRowActive || $sparepartActive || $sparepartPlanningActive || $measurementActive || $checklistActive || $problemCategoryActive || $problemFindingsActive || $predictiveMasterActive ? 'master' :
                        ($machineHistoryActive || $reportActive ? 'report' : 'system')) }}',
            openSubmenu: '{{ $reportActive ? 'reports' : '' }}'
        }">
            @php
            $groups = [
            [
            'key' => 'main',
            'title' => 'Main',
            // layout-dashboard
            'icon' => '
            <rect width="7" height="9" x="3" y="3" rx="1" />
            <rect width="7" height="5" x="14" y="3" rx="1" />
            <rect width="7" height="9" x="14" y="12" rx="1" />
            <rect width="7" height="5" x="3" y="16" rx="1" />',
            'items' => [
            [
            'route' => route('dashboard'),
            'label' => 'Dashboard',
            'active' => $dashboardActive,
            // gauge
            'icon' => '
            <path d="m12 14 4-4" />
            <path d="M3.34 19a10 10 0 1 1 17.32 0" />',
            ],
            [
            'route' => route('today-activity.index'),
            'label' => "Today's Activity",
            'active' => $todayActivityActive,
            // activity (pulse line)
            'icon' => '
            <path d="M22 12h-2.48a2 2 0 0 0-1.93 1.46l-2.35 8.36a.25.25 0 0 1-.48 0L9.24 2.18a.25.25 0 0 0-.48 0l-2.35 8.36A2 2 0 0 1 4.49 12H2" />',
            'visible' =>
            $isAdmin || $isKoordinator || $isSupervisor,
            ],
            [
            'route' => route('pm-schedules.index'),
            'label' => 'PM Schedule',
            'active' => $pmScheduleActive,
            // calendar-check
            'icon' => '
            <path d="M8 2v4" />
            <path d="M16 2v4" />
            <rect width="18" height="18" x="3" y="4" rx="2" />
            <path d="M3 10h18" />
            <path d="m9 16 2 2 4-4" />',
            'visible' =>
            $isAdmin || $isKoordinator || $isPic || $isSupervisor,
            ],
            [
            'route' => route('weekly-plan.index'),
            'label' => 'Weekly PM Plan',
            'active' => $weeklyPlanActive,
            // calendar-range: a Monday-Friday span
            'icon' => '
            <rect width="18" height="18" x="3" y="4" rx="2" />
            <path d="M16 2v4" />
            <path d="M8 2v4" />
            <path d="M3 10h18" />
            <path d="M7 14h10" />
            <path d="M7 18h6" />',
            'visible' =>
            $isAdmin || $isKoordinator || $isSupervisor,
            ],
            [
            'route' => route('oil-audits.scan'),
            'label' => 'Oil Audit',
            'active' =>
            $oilAuditActive && !request()->routeIs('oil-audits.report', 'oil-audits.history'),
            // droplet
            'icon' => '
            <path d="M12 22a7 7 0 0 0 7-7c0-2-1-3.9-3-5.5s-3.5-4-4-6.5c-.5 2.5-2 4.9-4 6.5C4 11.1 3 13 3 15a7 7 0 0 0 7 7z" />',
            'visible' =>
            $hasWwdArea,
            ],
            [
            'route' => route('oil-audits.report'),
            'label' => 'Oil Audit Action',
            'active' => $oilAuditActionActive,
            // clipboard-check
            'icon' => '
            <rect width="8" height="4" x="8" y="2" rx="1" ry="1" />
            <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2" />
            <path d="m9 14 2 2 4-4" />',
            'visible' =>
            $hasWwdArea,
            ],
            [
            'route' => route('predictive.index'),
            'label' => 'Action Predictive',
            'active' => $predictiveActive,
            // activity (vibration waveform)
            'icon' => '
            <path d="M2 12h3l2-7 4 14 3-10 2 3h6" />',
            'visible' =>
            $isAdmin || $isKoordinator || $isPic || $isSupervisor,
            ],
            [
            'route' => route('greasings.index'),
            'label' => 'Greasing',
            'active' => $greasingActive,
            // syringe (grease application)
            'icon' => '
            <path d="m18 2 4 4" />
            <path d="m17 7 3-3" />
            <path d="M19 9 8.7 19.3c-1 1-2.5 1-3.4 0l-.6-.6c-1-1-1-2.5 0-3.4L15 5" />
            <path d="m9 11 4 4" />
            <path d="m5 19-3 3" />
            <path d="m14 4 6 6" />',
            'visible' =>
            $isAdmin || $isKoordinator || $isPic || $isSupervisor,
            ],
            [
            'route' => route('small-pm.index'),
            'label' => 'Small PM',
            'active' => $smallPmActive,
            // brush-cleaning: both Small PM jobs are cleaning work (motor grille
            // cleaning, blower panel filter replacement). The old fan glyph read
            // as a shuriken at sidebar size.
            'icon' => '
            <path d="m16 22-1-4" />
            <path d="M19 13.99a1 1 0 0 0 1-1V12a2 2 0 0 0-2-2h-3a1 1 0 0 1-1-1V4a2 2 0 0 0-4 0v5a1 1 0 0 1-1 1H6a2 2 0 0 0-2 2v.99a1 1 0 0 0 1 1" />
            <path d="M5 14h14l1.973 6.767A1 1 0 0 1 20 22H4a1 1 0 0 1-.973-1.233z" />
            <path d="m8 22 1-4" />',
            'visible' =>
            $hasWwdArea && ($isAdmin || $isKoordinator || $isPic || $isSupervisor),
            ],
            ],
            ],
            [
            'key' => 'report',
            'title' => 'Report',
            // chart-column
            'icon' => '
            <path d="M3 3v16a2 2 0 0 0 2 2h16" />
            <path d="M18 17V9" />
            <path d="M13 17V5" />
            <path d="M8 17v-3" />',
            'items' => [
            [
            'route' => route('machine-history.index'),
            'label' => 'Machine History',
            'active' => $machineHistoryActive,
            // history (clock + rewind)
            'icon' => '
            <path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8" />
            <path d="M3 3v5h5" />
            <path d="M12 7v5l4 2" />',
            ],
            [
            'type' => 'submenu',
            'key' => 'reports',
            'label' => 'Reports',
            'active' => $reportActive,
            // file-chart-column
            'icon' => '
            <path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z" />
            <path d="M14 2v4a2 2 0 0 0 2 2h4" />
            <path d="M8 18v-1" />
            <path d="M12 18v-6" />
            <path d="M16 18v-3" />',
            'visible' =>
            $isAdmin || $isKoordinator || $isPic || $isSupervisor,
            'children' => [
            [
            'route' => route('reports.pm'),
            'label' => 'PM Report',
            'active' => $reportsPmActive,
            ],
            [
            'route' => route('reports.greasing'),
            'label' => 'Greasing Report',
            'active' => $reportsGreasingActive,
            ],
            [
            'route' => route('reports.oil-audit'),
            'label' => 'Oil Audit Report',
            'active' => $reportsOilAuditActive,
            'visible' => $oilAuditReportEligible,
            ],
            [
            'route' => route('reports.sparepart'),
            'label' => 'Sparepart Usage Report',
            'active' => $reportsSparepartActive,
            ],
            [
            'route' => route('reports.machine'),
            'label' => 'Machine Report',
            'active' => $reportsMachineActive,
            ],
            [
            'route' => route('reports.problem'),
            'label' => 'Problem Analysis Report',
            'active' => $reportsProblemActive,
            ],
            [
            'route' => route('reports.cost'),
            'label' => 'Maintenance Cost Report',
            'active' => $reportsCostActive,
            ],
            ],
            ],
            ],
            ],
            [
            'key' => 'master',
            'title' => 'Master Data',
            // database
            'icon' => '
            <ellipse cx="12" cy="5" rx="9" ry="3" />
            <path d="M3 5v14a9 3 0 0 0 18 0V5" />
            <path d="M3 12a9 3 0 0 0 18 0" />',
            'items' => [
            [
            'route' => route('machines.index'),
            'label' => 'Machine',
            'active' => $machineActive,
            // cpu (equipment unit)
            'icon' => '
            <path d="M12 20v2" />
            <path d="M12 2v2" />
            <path d="M17 20v2" />
            <path d="M17 2v2" />
            <path d="M2 12h2" />
            <path d="M2 17h2" />
            <path d="M2 7h2" />
            <path d="M20 12h2" />
            <path d="M20 17h2" />
            <path d="M20 7h2" />
            <path d="M7 20v2" />
            <path d="M7 2v2" />
            <rect x="4" y="4" width="16" height="16" rx="2" />
            <rect x="9" y="9" width="6" height="6" />',
            'visible' =>
            $isAdmin || $isKoordinator || $isSupervisor,
            ],
            [
            'route' => route('machine-qr.index'),
            'label' => 'Machine QR',
            'active' => $machineQrActive,
            // qr-code
            'icon' => '
            <rect width="5" height="5" x="3" y="3" rx="1" />
            <rect width="5" height="5" x="16" y="3" rx="1" />
            <rect width="5" height="5" x="3" y="16" rx="1" />
            <path d="M21 16h-3a2 2 0 0 0-2 2v3" />
            <path d="M21 21v.01" />
            <path d="M12 7v3a2 2 0 0 1-2 2H7" />
            <path d="M3 12h.01" />
            <path d="M12 3h.01" />
            <path d="M12 16v.01" />
            <path d="M16 12h1" />
            <path d="M21 12v.01" />
            <path d="M12 21v-1" />',
            'visible' =>
            $isAdmin || $isKoordinator,
            ],
            [
            'route' => route('groups.index'),
            'label' => 'Group',
            'active' => $groupActive,
            // layers (machine groupings)
            'icon' => '
            <path d="M12.83 2.18a2 2 0 0 0-1.66 0L2.6 6.08a1 1 0 0 0 0 1.83l8.58 3.91a2 2 0 0 0 1.66 0l8.58-3.9a1 1 0 0 0 0-1.83Z" />
            <path d="M2 12a1 1 0 0 0 .58.91l8.6 3.91a2 2 0 0 0 1.65 0l8.58-3.9A1 1 0 0 0 22 12" />
            <path d="M2 17a1 1 0 0 0 .58.91l8.6 3.91a2 2 0 0 0 1.65 0l8.58-3.9A1 1 0 0 0 22 17" />',
            'visible' =>
            $isAdmin || $isKoordinator || $isSupervisor,
            ],
            [
            'route' => route('small-pm-rows.index'),
            'label' => 'Small PM Rows',
            'active' => $smallPmRowActive,
            // rows-3: machines standing in rows
            'icon' => '
            <rect width="18" height="18" x="3" y="3" rx="2" />
            <path d="M21 9H3" />
            <path d="M21 15H3" />',
            'visible' =>
            $hasWwdArea && ($isAdmin || $isKoordinator || $isSupervisor),
            ],
            [
            'route' => route('spareparts.index'),
            'label' => 'Sparepart',
            'active' => $sparepartActive,
            // package (parts inventory)
            'icon' => '
            <path d="m7.5 4.27 9 5.15" />
            <path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z" />
            <path d="M3.3 7 12 12l8.7-5" />
            <path d="M12 22V12" />',
            ],
            [
            'route' => route('sparepart-planning.index'),
            'label' => 'Sparepart Planning',
            'active' => $sparepartPlanningActive,
            // clipboard-list (planning)
            'icon' => '
            <rect width="8" height="4" x="8" y="2" rx="1" ry="1" />
            <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2" />
            <path d="M12 11h4" />
            <path d="M12 16h4" />
            <path d="M8 11h.01" />
            <path d="M8 16h.01" />',
            'visible' =>
            $isAdmin || $isKoordinator || $isSupervisor,
            ],
            [
            'route' => route('machine-measurements.index'),
            'label' => 'Measurement',
            'active' => $measurementActive,
            // ruler
            'icon' => '
            <path d="M21.3 8.7 8.7 21.3c-1 1-2.5 1-3.4 0l-2.6-2.6c-1-1-1-2.5 0-3.4L15.3 2.7c1-1 2.5-1 3.4 0l2.6 2.6c1 1 1 2.5 0 3.4Z" />
            <path d="m7.5 10.5 2 2" />
            <path d="m10.5 7.5 2 2" />
            <path d="m13.5 4.5 2 2" />
            <path d="m4.5 13.5 2 2" />',
            'visible' =>
            $isAdmin || $isKoordinator || $isSupervisor,
            ],
            [
            'route' => route('machine-checklists.index'),
            'label' => 'Checklist',
            'active' => $checklistActive,
            // list-checks
            'icon' => '
            <path d="m3 17 2 2 4-4" />
            <path d="m3 7 2 2 4-4" />
            <path d="M13 6h8" />
            <path d="M13 12h8" />
            <path d="M13 18h8" />',
            'visible' =>
            $isAdmin || $isKoordinator || $isSupervisor,
            ],
            [
            'route' => route('machine-problems.index'),
            'label' => 'Problem Category',
            'active' => $problemCategoryActive,
            // triangle-alert
            'icon' => '
            <path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3" />
            <path d="M12 9v4" />
            <path d="M12 17h.01" />',
            'visible' =>
            $isAdmin || $isKoordinator || $isSupervisor,
            ],
            [
            'route' => route('predictive.master.index'),
            'label' => 'Predictive Problem',
            'active' => $predictiveMasterActive,
            // list-checks (standard choices)
            'icon' => '
            <path d="m3 17 2 2 4-4" />
            <path d="m3 7 2 2 4-4" />
            <path d="M13 6h8" />
            <path d="M13 12h8" />
            <path d="M13 18h8" />',
            'visible' =>
            $isAdmin || $isKoordinator,
            ],
            [
            'route' => route('machine-problem-findings.index'),
            'label' => 'Problem Finding',
            'active' => $problemFindingsActive,
            // scan-search
            'icon' => '
            <path d="M3 7V5a2 2 0 0 1 2-2h2" />
            <path d="M17 3h2a2 2 0 0 1 2 2v2" />
            <path d="M21 17v2a2 2 0 0 1-2 2h-2" />
            <path d="M7 21H5a2 2 0 0 1-2-2v-2" />
            <circle cx="12" cy="12" r="3" />
            <path d="m16 16-1.9-1.9" />',
            'visible' =>
            $isAdmin || $isKoordinator,
            ],
            ],
            ],
            [
            'key' => 'system',
            'title' => 'System',
            // shield-check
            'icon' => '
            <path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z" />
            <path d="m9 12 2 2 4-4" />',
            'items' => [
            [
            'route' => route('import-templates'),
            'label' => 'Import Template',
            'active' => $importTemplateActive,
            // file-down
            'icon' => '
            <path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z" />
            <path d="M14 2v4a2 2 0 0 0 2 2h4" />
            <path d="M12 18v-6" />
            <path d="m9 15 3 3 3-3" />',
            'visible' =>
            $isAdmin || $isKoordinator,
            ],
            [
            'route' => route('users.index'),
            'label' => 'Users',
            'active' => $userActive,
            // users (people / accounts)
            'icon' => '
            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
            <circle cx="9" cy="7" r="4" />
            <path d="M22 21v-2a4 4 0 0 0-3-3.87" />
            <path d="M16 3.13a4 4 0 0 1 0 7.75" />',
            'visible' =>
            $isAdmin,
            ],
            [
            'route' => route('areas.index'),
            'label' => 'Area Management',
            'active' => $areaActive,
            // map-pin (area/location master data)
            'icon' => '
            <path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0" />
            <circle cx="12" cy="10" r="3" />',
            'visible' =>
            $isAdmin,
            ],
            [
            'route' => route('machine-maintenance-requirements.index'),
            'label' => 'Oil Change Requirements',
            'active' => $maintenanceRequirementActive,
            // droplet (mirrors the Oil Change field this master data controls)
            'icon' => '
            <path d="M12 22a7 7 0 0 0 7-7c0-2-1-3.9-3-5.5s-3.5-4-4-6.5c-.5 2.5-2 4.9-4 6.5C4 11.1 3 13 3 15a7 7 0 0 0 7 7z" />',
            'visible' =>
            $isAdmin,
            ],
            [
            'route' => route('email-notifications.edit'),
            'label' => 'Email Notifications',
            'active' => $emailNotificationActive,
            // mail
            'icon' => '
            <rect width="20" height="16" x="2" y="4" rx="2" />
            <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7" />',
            'visible' =>
            $isAdmin,
            ],
            [
            'route' => route('backup.index'),
            'label' => 'Backup & Data Protection',
            'active' => $backupActive,
            // database
            'icon' => '
            <ellipse cx="12" cy="5" rx="9" ry="3" />
            <path d="M3 5V19A9 3 0 0 0 21 19V5" />
            <path d="M3 12A9 3 0 0 0 21 12" />',
            'visible' =>
            $isAdmin,
            ],
            ],
            'visible' =>
            $isAdmin || $isKoordinator,
            ],
            ];
            @endphp

            @php
            // Shared row classes. Active rows carry bold text and aria-current as well as the
            // tint, so the state never rests on colour alone. Tests look for bg-sidebar-active
            // in the class attribute that directly follows href, so keep that order.
            $rowBase = 'flex min-h-11 items-center gap-3 rounded-lg px-3 transition-colors';
            $rowActive = 'bg-sidebar-active font-semibold text-sidebar-active-foreground';
            $rowIdle = 'text-sidebar-muted hover:bg-sidebar-hover hover:text-sidebar-foreground active:bg-sidebar-border';
            @endphp

            @foreach ($groups as $group)
            @if (!isset($group['visible']) || $group['visible'])
            <div class="mt-2 border-t border-sidebar-border pt-2 first:mt-0 first:border-t-0 first:pt-0">
                <button type="button"
                    @click="openGroup = (openGroup === '{{ $group['key'] }}') ? '' : '{{ $group['key'] }}'"
                    :aria-expanded="(openGroup === '{{ $group['key'] }}').toString()"
                    aria-controls="nav-group-{{ $group['key'] }}"
                    class="flex min-h-11 w-full items-center justify-between rounded-lg px-3 text-left text-xs font-semibold text-sidebar-muted transition-colors hover:bg-sidebar-hover hover:text-sidebar-foreground">
                    <span>{{ $group['title'] }}</span>
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"
                        class="h-4 w-4 shrink-0 motion-safe:transition-transform"
                        :class="{ 'rotate-180': openGroup === '{{ $group['key'] }}' }">
                        <path d="m6 9 6 6 6-6" />
                    </svg>
                </button>

                <div id="nav-group-{{ $group['key'] }}" x-show="openGroup === '{{ $group['key'] }}'" class="space-y-0.5 pb-1">
                    @foreach ($group['items'] as $item)
                    @if (!isset($item['visible']) || $item['visible'])
                    @if (($item['type'] ?? 'link') === 'submenu')
                    {{-- Sub-accordion: toggles independently of the parent group, revealing its own direct links below. --}}
                    <button type="button"
                        @click="openSubmenu = (openSubmenu === '{{ $item['key'] }}') ? '' : '{{ $item['key'] }}'"
                        :aria-expanded="(openSubmenu === '{{ $item['key'] }}').toString()"
                        aria-controls="nav-sub-{{ $item['key'] }}"
                        class="{{ $rowBase }} w-full justify-between text-left {{ $item['active'] ? 'font-semibold text-sidebar-active-foreground hover:bg-sidebar-hover' : $rowIdle }}">
                        <span class="flex min-w-0 items-center gap-3">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"
                                class="h-5 w-5 shrink-0" aria-hidden="true">
                                {!! $item['icon'] !!}
                            </svg>
                            <span class="truncate">{{ $item['label'] }}</span>
                        </span>
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"
                            class="h-4 w-4 shrink-0 motion-safe:transition-transform"
                            :class="{ 'rotate-90': openSubmenu === '{{ $item['key'] }}' }">
                            <path d="m9 18 6-6-6-6" />
                        </svg>
                    </button>

                    <div id="nav-sub-{{ $item['key'] }}" x-show="openSubmenu === '{{ $item['key'] }}'"
                        class="ml-5 space-y-0.5 border-l border-sidebar-border py-1 pl-3">
                        @foreach ($item['children'] as $child)
                        @if (!isset($child['visible']) || $child['visible'])
                        <a href="{{ $child['route'] }}"
                            class="{{ $rowBase }} {{ $child['active'] ? $rowActive : $rowIdle }}"
                            @if ($child['active']) aria-current="page" @endif>
                            <span class="truncate">{{ $child['label'] }}</span>
                        </a>
                        @endif
                        @endforeach
                    </div>
                    @else
                    <a href="{{ $item['route'] }}"
                        class="{{ $rowBase }} {{ $item['active'] ? $rowActive : $rowIdle }}"
                        @if ($item['active']) aria-current="page" @endif>
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"
                            class="h-5 w-5 shrink-0" aria-hidden="true">
                            {!! $item['icon'] !!}
                        </svg>
                        <span class="truncate">{{ $item['label'] }}</span>
                    </a>
                    @endif
                    @endif
                    @endforeach
                </div>
            </div>
            @endif
            @endforeach
        </nav>

        <div class="border-t border-sidebar-border px-4 py-3 text-xs text-sidebar-muted">
            v1.0 PM System
        </div>
    </aside>

    {{-- Mobile backdrop: tapping outside the drawer closes it. --}}
    <div x-show="$store.sidebar.open" x-cloak @click="$store.sidebar.open = false"
        class="fixed inset-0 z-30 bg-neutral-900/40 lg:hidden" x-transition.opacity aria-hidden="true">
    </div>
