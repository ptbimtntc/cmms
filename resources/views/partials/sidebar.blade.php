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
       flex h-screen flex-col
       border-r border-slate-800
       bg-slate-900 text-white
       transition-transform duration-300
       lg:translate-x-0">

        <div class="border-b border-slate-800 px-4 py-5">
            <div class="flex items-center justify-between gap-3">

                <div class="flex min-w-0 items-center gap-3">
                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl
                bg-gradient-to-br from-emerald-400 to-cyan-500
                text-slate-950 shadow-lg shadow-emerald-500/20">

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
                        <div class="text-base font-semibold tracking-wide text-white">FreeDOMS</div>
                        <div class="mt-0.5 text-xs text-slate-400">Preventive Maintenance System</div>
                    </div>
                </div>

                {{-- Tombol Close (X) --}}
                <button @click="$store.sidebar.open = false"
                    class="flex h-9 w-9 items-center justify-center rounded-lg text-slate-300 transition hover:bg-slate-800 hover:text-white lg:hidden"
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
        $greasingReportActive = request()->routeIs('reports.greasing');
        $profileActive = request()->routeIs('profile.*');

        @endphp
        @php
        $machineHistoryActive = request()->routeIs('machine-history.*');
        @endphp
        <nav class="flex-1 overflow-hidden px-3 py-4 text-sm" x-data="{
            openGroup: '{{ $dashboardActive || $pmScheduleActive || $oilAuditActive || $oilAuditActionActive || $greasingActive ? 'main' :
                        ($machineActive || $groupActive || $sparepartActive || $measurementActive || $checklistActive || $problemCategoryActive || $problemFindingsActive ? 'master' :
                        ($machineHistoryActive || $reportActive || $greasingReportActive ? 'report' : 'system')) }}'
        }">
            @php
            $groups = [
            [
            'key' => 'main',
            'title' => 'Main',
            'accent' => 'from-blue-500 to-cyan-500',
            'icon' => '
            <path d="M3 10.5 12 3l9 7.5" />
            <path d="M5 10.5V21h5v-6h4v6h5V10.5" />',
            'items' => [
            [
            'route' => route('dashboard'),
            'label' => 'Dashboard',
            'active' => $dashboardActive,
            'icon' => '
            <path d="M3 10.5 12 3l9 7.5" />
            <path d="M5 10.5V21h5v-6h4v6h5V10.5" />',
            ],
            [
            'route' => route('pm-schedules.index'),
            'label' => 'PM Schedule',
            'active' => $pmScheduleActive,
            'icon' => '
            <path d="M7 2v2" />
            <path d="M17 2v2" />
            <rect x="4" y="4" width="16" height="16" rx="2" />
            <path d="M4 8h16" />
            <path d="M8 12h.01" />
            <path d="M12 12h.01" />
            <path d="M16 12h.01" />',
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
            'icon' => '
            <path d="M12 3s-5 5.5-5 10a5 5 0 0 0 10 0c0-4.5-5-10-5-10Z" />
            <path d="M9.5 15.5c.7.8 1.5 1.2 2.5 1.2" />',
            'visible' =>
            $userRole === 'ADMIN' ||
            $userRole === 'PIC WWD' ||
            $userRole === 'KOORDINATOR WWD',
            ],
            [
            'route' => route('oil-audits.report'),
            'label' => 'Action Audit Oli',
            'active' => $oilAuditActionActive,
            'icon' => '
            <path d="M4 19V9" />
            <path d="M10 19V5" />
            <path d="M16 19v-7" />
            <path d="M22 19V3" />',
            'visible' =>
            $userRole === 'ADMIN' ||
            $userRole === 'PIC WWD' ||
            $userRole === 'KOORDINATOR WWD',
            ],
            [
            'route' => route('greasings.index'),
            'label' => 'Greasing',
            'active' => $greasingActive,
            'icon' => '
            <circle cx="12" cy="7" r="3" />
            <path d="M12 10v6" />
            <path d="M9 19h6" />
            <path d="M9 16h6" />',
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
            'accent' => 'from-green-500 to-cyan-500',
            'icon' => '
            <path d="M3 10.5 12 3l9 7.5" />
            <path d="M5 10.5V21h5v-6h4v6h5V10.5" />',
            'items' => [
            [
            'route' => route('machine-history.index'),
            'label' => 'Machine History',
            'active' => $machineHistoryActive,
            'icon' => '
            <path d="M3 10.5 12 3l9 7.5" />
            <path d="M5 10.5V21h5v-6h4v6h5V10.5" />',
            ],
            [
            'route' => route('reports.index'),
            'label' => 'Reports',
            'active' => $reportActive,
            'icon' => '
            <path d="M4 19V9" />
            <path d="M20 19V5" />
            <path d="M12 19V13" />',
            'visible' =>
            $userRole === 'ADMIN' ||
            $userRole === 'PIC WWD' ||
            $userRole === 'PIC BUL' ||
            $userRole === 'KOORDINATOR WWD'||
            $userRole === 'KOORDINATOR BUL',
            ],
            [
            'route' => route('reports.greasing'),
            'label' => 'Greasing Report',
            'active' => $greasingReportActive,
            'icon' => '
            <path d="M4 19V9" />
            <path d="M10 19V5" />
            <path d="M16 19v-11" />
            <path d="M22 19V13" />',
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
            'key' => 'master',
            'title' => 'Master Data',
            'accent' => 'from-violet-500 to-fuchsia-500',
            'icon' => '
            <path d="M4 7h16" />
            <path d="M7 7v10" />
            <path d="M17 7v10" />
            <path d="M4 17h16" />
            <rect x="6" y="4" width="12" height="4" rx="1" />',
            'items' => [
            [
            'route' => route('machines.index'),
            'label' => 'Machine',
            'active' => $machineActive,
            'icon' => '
            <path d="M4 7h16" />
            <path d="M7 7v10" />
            <path d="M17 7v10" />
            <path d="M4 17h16" />
            <rect x="6" y="4" width="12" height="4" rx="1" />',
            'visible' =>
            $userRole === 'ADMIN' ||
            $userRole === 'KOORDINATOR WWD'||
            $userRole === 'KOORDINATOR BUL',
            ],
            [
            'route' => route('groups.index'),
            'label' => 'Group',
            'active' => $groupActive,
            'icon' => '
            <circle cx="9" cy="8" r="3" />
            <circle cx="17" cy="9" r="2.5" />
            <path d="M4 19v-1a5 5 0 0 1 5-5h0a5 5 0 0 1 5 5v1" />
            <path d="M15 13.5a4 4 0 0 1 4 4v1.5" />',
            'visible' =>
            $userRole === 'ADMIN' ||
            $userRole === 'KOORDINATOR WWD'||
            $userRole === 'KOORDINATOR BUL',
            ],
            [
            'route' => route('spareparts.index'),
            'label' => 'Sparepart',
            'active' => $sparepartActive,
            'icon' => '
            <path d="M6 8h12" />
            <path d="M7 8v8a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2V8" />
            <path d="M9 5h6" />
            <path d="M10 5V3h4v2" />',
            ],
            [
            'route' => route('machine-measurements.index'),
            'label' => 'Measurement',
            'active' => $measurementActive,
            'icon' => '
            <path d="M4 12h16" />
            <path d="M7 12V7" />
            <path d="M12 12v-3" />
            <path d="M17 12v-6" />',
            'visible' =>
            $userRole === 'ADMIN' ||
            $userRole === 'KOORDINATOR WWD'||
            $userRole === 'KOORDINATOR BUL',
            ],
            [
            'route' => route('machine-checklists.index'),
            'label' => 'Checklist',
            'active' => $checklistActive,
            'icon' => '
            <path d="M9 11 11 13l4-4" />
            <rect x="4" y="4" width="16" height="16" rx="2" />',
            'visible' =>
            $userRole === 'ADMIN' ||
            $userRole === 'KOORDINATOR WWD'||
            $userRole === 'KOORDINATOR BUL',
            ],
            [
            'route' => route('machine-problems.index'),
            'label' => 'Problem Category',
            'active' => $problemCategoryActive,
            'icon' => '
            <path d="M12 3 2 19h20L12 3Z" />
            <path d="M12 8v5" />
            <path d="M12 16h.01" />',
            'visible' =>
            $userRole === 'ADMIN' ||
            $userRole === 'KOORDINATOR WWD'||
            $userRole === 'KOORDINATOR BUL',
            ],
            [
            'route' => route('machine-problem-findings.index'),
            'label' => 'Problem Finding',
            'active' => $problemFindingsActive,
            'icon' => '
            <circle cx="11" cy="11" r="6" />
            <path d="m20 20-4.2-4.2" />',
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
            'accent' => 'from-amber-500 to-orange-500',
            'icon' => '
            <path d="M12 3l7 4v5c0 5-3.5 7.5-7 9-3.5-1.5-7-4-7-9V7l7-4Z" />
            <path d="M9.5 12.5l1.8 1.8 3.2-3.4" />',
            'items' => [
            [
            'route' => route('import-templates'),
            'label' => 'Import Template',
            'active' => $importTemplateActive,
            'icon' => '
            <path d="M12 3v12" />
            <path d="m6 9 6 6 6-6" />
            <path d="M5 19h14" />',
            'visible' =>
            $userRole === 'ADMIN' ||
            $userRole === 'KOORDINATOR WWD'||
            $userRole === 'KOORDINATOR BUL',
            ],
            [
            'route' => route('users.index'),
            'label' => 'Users',
            'active' => $userActive,
            'icon' => '
            <path d="M16 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2" />
            <circle cx="9.5" cy="7" r="3" />
            <path d="M17 8h4" />
            <path d="M19 6v4" />',
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
            <div class="mb-3 rounded-2xl border border-slate-800/70 bg-slate-900/70">
                <button type="button"
                    @click="openGroup = (openGroup === '{{ $group['key'] }}') ? '' : '{{ $group['key'] }}'"
                    class="flex w-full items-center justify-between px-3 py-2.5 text-left">
                    <div class="flex items-center gap-2">
                        <span
                            class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-gradient-to-br {{ $group['accent'] }} text-white shadow-sm">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"
                                class="h-4 w-4">
                                {!! $group['icon'] !!}
                            </svg>
                        </span>
                        <span
                            class="text-[11px] font-semibold uppercase tracking-[0.22em] text-slate-400">{{ $group['title'] }}</span>
                    </div>
                    <span class="text-sm text-slate-400" x-show="openGroup !== '{{ $group['key'] }}'">+</span>
                    <span class="text-sm text-slate-400" x-show="openGroup === '{{ $group['key'] }}'">−</span>
                </button>

                <div x-show="openGroup === '{{ $group['key'] }}'" class="space-y-1 px-2 pb-2">
                    @foreach ($group['items'] as $item)
                    @if (!isset($item['visible']) || $item['visible'])
                    <a href="{{ $item['route'] }}"
                        class="flex items-center gap-3 rounded-xl px-3 py-2.5 transition-all {{ $item['active'] ? 'bg-blue-600 text-white shadow-lg shadow-blue-600/20' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                        <span
                            class="flex h-9 w-9 items-center justify-center rounded-lg bg-slate-800/80 text-sm text-slate-100">
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

        <div class="border-t border-slate-800 px-4 py-3 text-xs text-slate-500">
            v1.0 PM System
        </div>
    </aside>

    {{-- Overlay untuk mobile, klik di luar sidebar buat nutup --}}
    <div x-show="$store.sidebar.open" x-cloak @click="$store.sidebar.open = false"
        class="fixed inset-0 z-30 bg-black/40 lg:hidden" x-transition.opacity>
    </div>
    </aside>