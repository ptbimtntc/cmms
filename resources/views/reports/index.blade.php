@extends('layouts.app')

@section('content')

    <div class="mb-6">
        <h1 class="text-2xl font-bold text-slate-800">Report Center</h1>
        <p class="text-sm text-slate-500">Detail, analysis, filtering, and drill-down for every CMMS module. For a
            live overview, see the <a href="{{ route('dashboard') }}" class="font-medium text-blue-600 hover:underline">Dashboard</a>.
        </p>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @php
            // Oil Audit is a WWD-only module (same role set as its own
            // route middleware) — hide the card entirely for roles that
            // could never open it, rather than showing a dead link.
            $oilAuditEligible = in_array(auth()->user()->role, ['ADMIN', 'KOORDINATOR WWD', 'PIC WWD'], true);

            $reports = [
                ['title' => 'PM Report', 'description' => 'Filter, search, and drill down into every PM Schedule record.', 'route' => 'reports.pm', 'available' => true, 'visible' => true],
                ['title' => 'Greasing Report', 'description' => 'Closing/completion KPIs and history for greasing execution.', 'route' => 'reports.greasing', 'available' => true, 'visible' => true],
                ['title' => 'Oil Audit Report', 'description' => 'Oil condition audits, follow-ups, and history.', 'route' => 'reports.oil-audit', 'available' => true, 'visible' => $oilAuditEligible],
                ['title' => 'Sparepart Usage Report', 'description' => 'Sparepart consumption and cost across PM records.', 'route' => 'reports.sparepart', 'available' => true, 'visible' => true],
                ['title' => 'Machine Report', 'description' => 'Machine inventory, status, and maintenance history.', 'route' => 'reports.machine', 'available' => true, 'visible' => true],
                ['title' => 'Problem Analysis Report', 'description' => 'Recurring problems and findings by category.', 'route' => 'reports.problem', 'available' => true, 'visible' => true],
                ['title' => 'Maintenance Cost Report', 'description' => 'Monthly cost trend and breakdown from sparepart usage.', 'route' => 'reports.cost', 'available' => true, 'visible' => true],
            ];
        @endphp

        @foreach ($reports as $report)
            @continue(! $report['visible'])
            @if ($report['available'])
                <a href="{{ route($report['route']) }}"
                    class="group rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-blue-300 hover:shadow-md">
                    <div class="flex items-start justify-between gap-2">
                        <h2 class="text-base font-semibold text-slate-800 group-hover:text-blue-700">{{ $report['title'] }}</h2>
                        <span class="shrink-0 rounded-full bg-blue-50 px-2 py-0.5 text-xs font-medium text-blue-600">Open →</span>
                    </div>
                    <p class="mt-1.5 text-sm text-slate-500">{{ $report['description'] }}</p>
                </a>
            @else
                <div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 p-5 opacity-70">
                    <div class="flex items-start justify-between gap-2">
                        <h2 class="text-base font-semibold text-slate-600">{{ $report['title'] }}</h2>
                        <span class="shrink-0 rounded-full bg-slate-200 px-2 py-0.5 text-xs font-medium text-slate-500">Coming Soon</span>
                    </div>
                    <p class="mt-1.5 text-sm text-slate-400">{{ $report['description'] }}</p>
                </div>
            @endif
        @endforeach
    </div>

@endsection
