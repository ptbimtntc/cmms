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

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link href="https://cdn.jsdelivr.net/npm/tom-select/dist/css/tom-select.css" rel="stylesheet">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>

<body class="h-dvh overflow-hidden bg-slate-100 text-slate-800">
    @php
        $routeName = Route::currentRouteName() ?? 'dashboard';
        $defaultPageTitle = match (true) {
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
            str_contains($routeName, 'greasings') => 'Greasing Schedule',
            str_contains($routeName, 'spareparts') => 'Spareparts',
            str_contains($routeName, 'machine-measurements') => 'Measurements',
            str_contains($routeName, 'machine-checklists') => 'Checklists',
            str_contains($routeName, 'machine-problems') => 'Problem Categories',
            str_contains($routeName, 'machine-problem-findings') => 'Problem Findings',
            str_contains($routeName, 'users') => 'Users',
            str_contains($routeName, 'reports') => 'Reports',
            str_contains($routeName, 'import') => 'Import',
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
        <div class="flex min-h-0 flex-1 flex-col">

            @if (!($hideTopbar ?? false))
                @include('partials.topbar', ['pageTitle' => $pageTitle])
            @endif

            <main class="flex-1 overflow-y-auto {{ $hideSidebar ?? false ? '' : 'lg:ml-72' }}">
                <div class="mx-auto max-w-7xl p-4 sm:p-6 lg:p-8">
                    @if ($fullWidth ?? false)
                        @yield('content')
                    @else
                        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-6 lg:p-8">
                            @yield('content')
                        </div>
                    @endif
                </div>
            </main>

        </div>

    </div>
</body>

</html>
