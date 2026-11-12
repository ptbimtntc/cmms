<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>FreeDOMS</title>

    <link rel="icon" href="{{ asset('FreeDOMS.ico') }}" sizes="any">
    <link rel="icon" href="{{ asset('FreeDOMS.svg') }}" type="image/svg+xml">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">

    {{-- FreeDOMS offline-first PWA/app-shell foundation (Phase 1, Task 3). --}}
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <meta name="theme-color" content="#0369a1">
    <meta name="app-user-id" content="{{ auth()->id() }}">
    <link rel="shortcut icon" href="{{ asset('FreeDOMS.ico') }}">

    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link href="https://cdn.jsdelivr.net/npm/tom-select/dist/css/tom-select.css" rel="stylesheet">
    <script>
        // Mobile drawer state for partials/sidebar + partials/topbar. `desktop` tracks the lg
        // breakpoint so the drawer is only made inert where it actually slides off-screen.
        document.addEventListener('alpine:init', () => {
            const lg = window.matchMedia('(min-width: 1024px)');
            Alpine.store('sidebar', {
                open: false,
                desktop: lg.matches,
            });
            lg.addEventListener('change', (event) => {
                Alpine.store('sidebar').desktop = event.matches;
                if (event.matches) Alpine.store('sidebar').open = false;
            });
        });
    </script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>

<body class="h-dvh overflow-hidden bg-background text-text">
    @php
        $routeName = Route::currentRouteName() ?? 'dashboard';
        $defaultPageTitle = match (true) {
            str_starts_with($routeName, 'small-pm-rows.') => 'Row Small PM',
            str_starts_with($routeName, 'small-pm.') => 'Small PM',
            str_contains($routeName, 'pm-schedules') => 'PM Schedules',
            str_contains($routeName, 'machines') => 'Machines',
            str_contains($routeName, 'groups') => 'Groups',
            str_contains($routeName, 'reports.greasing') => 'Greasing Report',
            str_contains($routeName, 'reports.pm') => 'PM Report',
            str_contains($routeName, 'reports.oil-audit') => 'Oil Audit Report',
            str_contains($routeName, 'reports.sparepart') => 'Sparepart Usage Report',
            str_contains($routeName, 'reports.machine') => 'Machine Report',
            str_contains($routeName, 'reports.problem') => 'Problem Analysis Report',
            str_contains($routeName, 'reports.cost') => 'Maintenance Cost Report',
            str_contains($routeName, 'predictive') => 'Action Predictive',
            str_contains($routeName, 'greasings') => 'Greasing Schedule',
            str_contains($routeName, 'spareparts') => 'Spareparts',
            str_contains($routeName, 'machine-measurements') => 'Measurements',
            str_contains($routeName, 'machine-checklists') => 'Checklists',
            str_contains($routeName, 'machine-problems') => 'Problem Categories',
            str_contains($routeName, 'machine-problem-findings') => 'Problem Findings',
            str_contains($routeName, 'users') => 'Users',
            str_contains($routeName, 'areas') => 'Area Management',
            str_contains($routeName, 'machine-maintenance-requirements') => 'Oil Change Requirements',
            str_contains($routeName, 'email-notifications') => 'Email Notifications',
            str_contains($routeName, 'backup') => 'Backup & Data Protection',
            str_contains($routeName, 'reports') => 'Reports',
            str_contains($routeName, 'import') => 'Import',
            str_contains($routeName, 'today-activity') => "Today's Activity",
            default => 'Dashboard',
        };

        $pageTitle = View::hasSection('title') ? View::getSection('title') : $defaultPageTitle;
    @endphp

    <div x-data class="flex h-dvh overflow-hidden">

        @if (!($hideSidebar ?? false))
            @include('partials.sidebar')
        @endif

        {{-- Margin statis lg:ml-72, karena sidebar SELALU full width di desktop.
        Di mobile margin 0 karena sidebar jadi overlay/drawer. --}}
        <div class="flex min-h-0 min-w-0 flex-1 flex-col">

            @if (!($hideTopbar ?? false))
                @include('partials.topbar', ['pageTitle' => $pageTitle])
            @endif

            <main class="flex-1 overflow-y-auto overscroll-y-contain {{ $hideSidebar ?? false ? '' : 'lg:ml-72' }}">
                {{-- $contentMaxWidth lets an individual page opt into a wider
                content area (e.g. a dense table) without affecting every
                other page, which keeps the max-w-7xl default. --}}
                <div class="mx-auto {{ $contentMaxWidth ?? 'max-w-7xl' }} p-4 sm:p-6 lg:p-8">
                    @if ($fullWidth ?? false)
                        @yield('content')
                    @else
                        {{-- Page-level frame for pages not yet moved to x-ui components. Flat
                             (border, no shadow): it is the ground plane, not a raised surface. --}}
                        <div class="rounded-card border border-border bg-surface p-4 sm:p-6 lg:p-8">
                            @yield('content')
                        </div>
                    @endif
                </div>
            </main>

        </div>

    </div>
</body>

</html>
