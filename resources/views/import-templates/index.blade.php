@extends('layouts.app')

@section('title', 'Import Template')

@section('content')
    @php
        $fullWidth = true;
        // [title, what the file contains, template key]
        $templates = [
            ['Machine Master', 'Import template for machine master data', 'machine'],
            ['Sparepart Master', 'Import template for sparepart master data', 'sparepart'],
            ['Sparepart Injection', 'Import template for warehouse sparepart issues (Month, SAP Number, Qty, Order Number)', 'sparepart-injection'],
            ['PM Schedule Master', 'Import template for PM schedules', 'pm-schedule'],
            ['Machine Problem Master', 'Import template for machine problems', 'machine-problem'],
            ['Problem Finding Master', 'Import template for problem findings', 'machine-problem-finding'],
            ['Measurement Master', 'Import template for measurement master data', 'machine-measurement'],
            ['Checklist Master', 'Import template for machine checklists', 'machine-checklist'],
            ['Greasing Schedule', 'Import template for greasing schedules', 'greasing-schedule'],
            ['Small PM Rows', 'Import template for Small PM rows and their machines (one line per machine)', 'small-pm-row'],
            ['Small PM Schedule', 'Import template for Small PM schedules (the row column is the row name in Small PM Rows)', 'small-pm-schedule'],
            ['Predictive Work Order', 'Import template for Vibration and Air Leaked Action Predictive WOs (Order Number, Machine Number, Date, Problem Description). Area and Machine Type come from Machine Master. The Due Date is worked out automatically: Date plus 14 days.', 'predictive-work-order'],
            ['Predictive Problem and Finding', 'Import template for the Problem and Finding choices in the Action Predictive form (Category, Area, Problem, Finding). Category: VIBRATION or AIR_LEAKED. Repeat the Problem on the next line to add another Finding.', 'predictive-problem-finding'],
        ];
    @endphp

    <div class="space-y-5">
        <x-ui.page-header title="Import Template" description="Download the Excel template for each import, fill it, then upload it on the matching page." />

        <ul class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($templates as [$title, $description, $key])
                <li class="card flex flex-col p-5">
                    <h2 class="font-semibold text-text">{{ $title }}</h2>
                    <p class="mt-1 flex-1 text-sm text-text-muted">{{ $description }}</p>
                    <x-ui.button variant="outline" size="sm" class="mt-4 self-start" href="{{ route('import-templates.download', $key) }}">Download Template</x-ui.button>
                </li>
            @endforeach
        </ul>
    </div>
@endsection
