@extends('layouts.app')

@section('title', 'Import Template')

@section('content')
    @php
        $fullWidth = true;
        // [title, what the file contains, template key]
        $templates = [
            ['Machine Master', 'Template import machine master', 'machine'],
            ['Sparepart Master', 'Template import sparepart', 'sparepart'],
            ['Sparepart Injection', 'Template import pengeluaran sparepart gudang (Month, SAP Number, Qty, Order Number)', 'sparepart-injection'],
            ['PM Schedule Master', 'Template untuk import PM schedule', 'pm-schedule'],
            ['Machine Problem Master', 'Template untuk import machine problem', 'machine-problem'],
            ['Problem Finding Master', 'Template untuk import problem finding', 'machine-problem-finding'],
            ['Measurement Master', 'Template untuk import measurement master', 'machine-measurement'],
            ['Checklist Master', 'Template untuk import machine checklist', 'machine-checklist'],
            ['Greasing Schedule', 'Template untuk import greasing schedule', 'greasing-schedule'],
            ['Row Small PM', 'Template untuk import row Small PM dan mesinnya (satu baris per mesin)', 'small-pm-row'],
            ['Small PM Schedule', 'Template untuk import jadwal Small PM (kolom row = nama row di master Row Small PM)', 'small-pm-schedule'],
            ['Predictive Work Order', 'Template import WO Action Predictive Vibration dan Air Leaked (Order Number, Machine Number, Date, Problem Description). Area dan Machine Type diambil dari Machine Master. Due Date dihitung otomatis: Date + 14 hari.', 'predictive-work-order'],
            ['Predictive Problem dan Finding', 'Template import pilihan Problem dan Finding untuk form Action Predictive (Category, Area, Problem, Finding). Category: VIBRATION atau AIR_LEAKED. Ulangi Problem di baris berikutnya untuk menambah Finding.', 'predictive-problem-finding'],
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
