    {{-- Daftarkan Alpine store sekali saja. Idealnya taruh di layout utama (app.blade.php)
     sebelum script Alpine di-load, tapi kalau belum ada, taruh di sini juga aman. --}}
    <script>
document.addEventListener('alpine:init', () => {
    Alpine.store('sidebar', {
        open: false,
    });
});
    </script>

    <aside x-cloak :class="$store.sidebar.open ? 'translate-x-0' : '-translate-x-full'" class="fixed inset-y-0 left-0
       z-40 w-72
       flex h-dvh flex-col
       border-r border-sidebar-border
       bg-sidebar text-sidebar-foreground
       transition-transform duration-300
       lg:translate-x-0">

        <div class="border-b border-sidebar-border px-4 py-5">
            <div class="flex items-center justify-between gap-3">

                <div class="flex min-w-0 items-center gap-3">
                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl
                bg-primary text-white shadow-sm">

                        <svg viewBox="0 0 192 192" xmlns="http://www.w3.org/2000/svg" fill="none">
                            <g id="SVGRepo_bgCarrier" stroke-width="0"></g>
                            <g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g>
                            <g id="SVGRepo_iconCarrier">
                                <g style="stroke-width:.903553">
                                    <g style="stroke-width:1.22576">
                                        <path d="M6 104V56h34.856M6 80h21.855" class="a"
                                            style="fill:none;stroke:#000000;stroke-width:14.7089;stroke-linecap:round;stroke-linejoin:round;stroke-dasharray:none"
                                            transform="matrix(.79792 0 0 .83414 17.08 29.264)"></path>
                                    </g>
                                    <g style="stroke-width:2.24031;stroke-dasharray:none">
                                        <path
                                            d="M14.665 15.027V7.109h2.574a2.672 2.672 0 1 1 0 5.345h-2.574m5.245 2.573-2.483-2.582"
                                            class="a"
                                            style="fill:none;stroke:#000000;stroke-width:2.24031;stroke-linecap:round;stroke-linejoin:round;stroke-dasharray:none"
                                            transform="matrix(5.56244 0 0 5.15795 -16.54 38.912)"></path>
                                    </g>
                                    <g style="stroke-width:1.03392">
                                        <path d="M6 6h28v0M6 24h28v0M6 42h28v0"
                                            style="fill:none;stroke:#000000;stroke-width:12.4073;stroke-linecap:round;stroke-linejoin:round;stroke-miterlimit:1;stroke-dasharray:none;paint-order:stroke fill markers"
                                            transform="matrix(.86732 0 0 1.07855 141.13 70.11)"></path>
                                    </g>
                                    <g style="stroke-width:1.03392">
                                        <path d="M6 6h28v0M6 24h28v0M6 42h28v0"
                                            style="fill:none;stroke:#000000;stroke-width:12.4073;stroke-linecap:round;stroke-linejoin:round;stroke-miterlimit:1;stroke-dasharray:none;paint-order:stroke fill markers"
                                            transform="matrix(.86732 0 0 1.07855 103.848 70.12)"></path>
                                    </g>
                                </g>
                            </g>
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <div class="text-base font-semibold tracking-wide text-sidebar-foreground">FreeDOMS</div>
                        <div class="mt-0.5 text-xs text-sidebar-muted">Preventive Maintenance System</div>
                    </div>
                </div>

                {{-- Tombol Close (X) --}}
                <button @click="$store.sidebar.open = false"
                    class="flex h-9 w-9 items-center justify-center rounded-lg text-sidebar-muted transition hover:bg-sidebar-hover hover:text-sidebar-foreground lg:hidden"
                    title="Close Sidebar">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5">
                        <path d="M18 6 6 18" />
                        <path d="m6 6 12 12" />
                    </svg>
                </button>
            </div>
        </div>

        @php
        $userRole = strtoupper(auth()->user()->role);

        $machineActive = request()->routeIs('machines.*');
        $groupActive = request()->routeIs('groups.*');
        $sparepartActive = request()->routeIs('spareparts.*');
        $reportActive = request()->routeIs('reports.*');
        $userActive = request()->routeIs('users.*');
        $dashboardActive = request()->routeIs('dashboard');
        $pmScheduleActive = request()->routeIs('pm-schedules.*');
        $importTemplateActive = request()->routeIs('import-templates');
        $measurementActive = request()->routeIs('machine-measurements.*');
        $checklistActive = request()->routeIs('machine-checklists.*');
        $problemCategoryActive = request()->routeIs('machine-problems.*');
        $problemFindingsActive = request()->routeIs('machine-problem-findings.*');
        $oilAuditActive = request()->routeIs('oil-audits.scan');
        $oilAuditActionActive = request()->routeIs('oil-audits.report');
        $greasingActive = request()->routeIs('greasings.*');
        $profileActive = request()->routeIs('profile.*');

        @endphp
        @php
        $machineHistoryActive = request()->routeIs('machine-history.*');
        @endphp
        <nav class="flex-1 overflow-y-auto px-3 py-4 text-sm" x-data="{
            openGroup: '{{ $dashboardActive || $pmScheduleActive || $oilAuditActive || $oilAuditActionActive || $greasingActive ? 'main' :
                        ($machineActive || $groupActive || $sparepartActive || $measurementActive || $checklistActive || $problemCategoryActive || $problemFindingsActive ? 'master' :
                        ($machineHistoryActive || $reportActive ? 'report' : 'system')) }}'
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
            $userRole === 'ADMIN' ||
            $userRole === 'PIC WWD' ||
            $userRole === 'PIC BUL' ||
            $userRole === 'KOORDINATOR WWD'||
            $userRole === 'KOORDINATOR BUL',
            ],
            [
            'route' => route('oil-audits.scan'),
            'label' => 'Audit Oli',
            'active' =>
            $oilAuditActive && !request()->routeIs('oil-audits.report', 'oil-audits.history'),
            // droplet
            'icon' => '
            <path d="M12 22a7 7 0 0 0 7-7c0-2-1-3.9-3-5.5s-3.5-4-4-6.5c-.5 2.5-2 4.9-4 6.5C4 11.1 3 13 3 15a7 7 0 0 0 7 7z" />',
            'visible' =>
            $userRole === 'ADMIN' ||
            $userRole === 'PIC WWD' ||
            $userRole === 'KOORDINATOR WWD',
            ],
            [
            'route' => route('oil-audits.report'),
            'label' => 'Action Audit Oli',
            'active' => $oilAuditActionActive,
            // clipboard-check
            'icon' => '
            <rect width="8" height="4" x="8" y="2" rx="1" ry="1" />
            <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2" />
            <path d="m9 14 2 2 4-4" />',
            'visible' =>
            $userRole === 'ADMIN' ||
            $userRole === 'PIC WWD' ||
            $userRole === 'KOORDINATOR WWD',
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
            $userRole === 'ADMIN' ||
            $userRole === 'KOORDINATOR WWD'||
            $userRole === 'KOORDINATOR BUL'||
            $userRole === 'PIC WWD'||
            $userRole === 'PIC BUL',
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
            'route' => route('reports.index'),
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
            $userRole === 'ADMIN' ||
            $userRole === 'PIC WWD' ||
            $userRole === 'PIC BUL' ||
            $userRole === 'KOORDINATOR WWD'||
            $userRole === 'KOORDINATOR BUL',
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
            $userRole === 'ADMIN' ||
            $userRole === 'KOORDINATOR WWD'||
            $userRole === 'KOORDINATOR BUL',
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
            $userRole === 'ADMIN' ||
            $userRole === 'KOORDINATOR WWD'||
            $userRole === 'KOORDINATOR BUL',
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
            $userRole === 'ADMIN' ||
            $userRole === 'KOORDINATOR WWD'||
            $userRole === 'KOORDINATOR BUL',
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
            $userRole === 'ADMIN' ||
            $userRole === 'KOORDINATOR WWD'||
            $userRole === 'KOORDINATOR BUL',
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
            $userRole === 'ADMIN' ||
            $userRole === 'KOORDINATOR WWD'||
            $userRole === 'KOORDINATOR BUL',
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
            $userRole === 'ADMIN' ||
            $userRole === 'KOORDINATOR WWD'||
            $userRole === 'KOORDINATOR BUL',
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
            $userRole === 'ADMIN' ||
            $userRole === 'KOORDINATOR WWD'||
            $userRole === 'KOORDINATOR BUL',
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
            $userRole === 'ADMIN',
            ],
            ],
            'visible' =>
            $userRole === 'ADMIN' ||
            $userRole === 'KOORDINATOR WWD'||
            $userRole === 'KOORDINATOR BUL',
            ],
            ];
            @endphp

            @foreach ($groups as $group)
            @if (!isset($group['visible']) || $group['visible'])
            <div class="mb-3 rounded-2xl border border-sidebar-border bg-sidebar-elevated">
                <button type="button"
                    @click="openGroup = (openGroup === '{{ $group['key'] }}') ? '' : '{{ $group['key'] }}'"
                    class="flex w-full items-center justify-between px-3 py-2.5 text-left">
                    <div class="flex items-center gap-2">
                        <span
                            class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-sidebar-hover text-sidebar-foreground">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"
                                class="h-4 w-4">
                                {!! $group['icon'] !!}
                            </svg>
                        </span>
                        <span
                            class="text-[11px] font-semibold uppercase tracking-[0.22em] text-sidebar-muted">{{ $group['title'] }}</span>
                    </div>
                    <span class="text-sm text-sidebar-muted" x-show="openGroup !== '{{ $group['key'] }}'">+</span>
                    <span class="text-sm text-sidebar-muted" x-show="openGroup === '{{ $group['key'] }}'">−</span>
                </button>

                <div x-show="openGroup === '{{ $group['key'] }}'" class="space-y-1 px-2 pb-2">
                    @foreach ($group['items'] as $item)
                    @if (!isset($item['visible']) || $item['visible'])
                    <a href="{{ $item['route'] }}"
                        class="flex items-center gap-3 rounded-xl px-3 py-2.5 transition-all {{ $item['active'] ? 'bg-sidebar-active text-white shadow-sm' : 'text-sidebar-muted hover:bg-sidebar-hover hover:text-sidebar-foreground' }}">
                        <span
                            class="flex h-9 w-9 items-center justify-center rounded-lg bg-sidebar-hover text-sm text-sidebar-foreground">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"
                                class="h-4 w-4">
                                {!! $item['icon'] !!}
                            </svg>
                        </span>
                        <span class="truncate">{{ $item['label'] }}</span>
                    </a>
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

    {{-- Overlay untuk mobile, klik di luar sidebar buat nutup --}}
    <div x-show="$store.sidebar.open" x-cloak @click="$store.sidebar.open = false"
        class="fixed inset-0 z-30 bg-black/40 lg:hidden" x-transition.opacity>
    </div>
    </aside>
