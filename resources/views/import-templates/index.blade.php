@extends('layouts.app')

@section('content')
    <div class="max-w-6xl mx-auto px-6 py-6">


        <h1 class="text-2xl font-bold mb-6">
            Import Template
        </h1>


        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">


            <div class="bg-white shadow rounded-lg p-6">

                <h2 class="font-semibold text-lg">
                    Machine Master
                </h2>

                <p class="text-sm text-gray-500 mt-2">
                    Template import machine master
                </p>


                <a href="{{ route('import-templates.download', 'machine') }}"
                    class="inline-block mt-4 bg-green-600 text-white px-4 py-2 rounded">

                    Download Template

                </a>

            </div>



            <div class="bg-white shadow rounded-lg p-6">

                <h2 class="font-semibold text-lg">
                    Sparepart Master
                </h2>

                <p class="text-sm text-gray-500 mt-2">
                    Template import sparepart
                </p>


                <a href="{{ route('import-templates.download', 'sparepart') }}"
                    class="inline-block mt-4 bg-green-600 text-white px-4 py-2 rounded">

                    Download Template

                </a>

            </div>

            <div class="bg-white shadow rounded-lg p-6">

                <h2 class="font-semibold text-lg">
                    Sparepart Injection
                </h2>

                <p class="text-sm text-gray-500 mt-2">
                    Template import pengeluaran sparepart gudang (Month, SAP Number, Qty, Order Number)
                </p>


                <a href="{{ route('import-templates.download', 'sparepart-injection') }}"
                    class="inline-block mt-4 bg-green-600 text-white px-4 py-2 rounded">

                    Download Template

                </a>

            </div>

            <div class="bg-white shadow rounded-lg p-6">

                <h2 class="font-semibold text-lg">
                    PM Schedule Master
                </h2>

                <p class="text-sm text-gray-500 mt-2">
                    Template untuk import PM schedule
                </p>

                <a href="{{ route('import-templates.download', 'pm-schedule') }}"
                    class="inline-block mt-4 bg-green-600 text-white px-4 py-2 rounded">

                    Download Template

                </a>

            </div>

            <div class="bg-white shadow rounded-lg p-6">

                <h2 class="font-semibold text-lg">
                    Machine Problem Master
                </h2>

                <p class="text-sm text-gray-500 mt-2">
                    Template untuk import machine problem
                </p>


                <a href="{{ route('import-templates.download', 'machine-problem') }}"
                    class="inline-block mt-4 bg-green-600 text-white px-4 py-2 rounded">

                    Download Template

                </a>

            </div>

            <div class="bg-white shadow rounded-lg p-6">

                <h2 class="font-semibold text-lg">
                    Problem Finding Master
                </h2>

                <p class="text-sm text-gray-500 mt-2">
                    Template untuk import problem finding
                </p>


                <a href="{{ route('import-templates.download', 'machine-problem-finding') }}"
                    class="inline-block mt-4 bg-green-600 text-white px-4 py-2 rounded">

                    Download Template

                </a>

            </div>

            <div class="bg-white shadow rounded-lg p-6">

                <h2 class="font-semibold text-lg">
                    Measurement Master
                </h2>

                <p class="text-sm text-gray-500 mt-2">
                    Template untuk import measurement master
                </p>


                <a href="{{ route('import-templates.download', 'machine-measurement') }}"
                    class="inline-block mt-4 bg-green-600 text-white px-4 py-2 rounded">

                    Download Template

                </a>

            </div>

            <div class="bg-white shadow rounded-lg p-6">

                <h2 class="font-semibold text-lg">
                    Checklist Master
                </h2>

                <p class="text-sm text-gray-500 mt-2">
                    Template untuk import machine checklist
                </p>


                <a href="{{ route('import-templates.download', 'machine-checklist') }}"
                    class="inline-block mt-4 bg-green-600 text-white px-4 py-2 rounded">

                    Download Template

                </a>

            </div>

            <div class="bg-white shadow rounded-lg p-6">

                <h2 class="font-semibold text-lg">
                    Greasing Schedule
                </h2>

                <p class="text-sm text-gray-500 mt-2">
                    Template untuk import greasing schedule
                </p>


                <a href="{{ route('import-templates.download', 'greasing-schedule') }}"
                    class="inline-block mt-4 bg-green-600 text-white px-4 py-2 rounded">

                    Download Template

                </a>

            </div>

            <div class="bg-white shadow rounded-lg p-6">

                <h2 class="font-semibold text-lg">
                    Row Small PM
                </h2>

                <p class="text-sm text-gray-500 mt-2">
                    Template untuk import row Small PM dan mesinnya (satu baris per mesin)
                </p>

                <a href="{{ route('import-templates.download', 'small-pm-row') }}"
                    class="inline-block mt-4 bg-green-600 text-white px-4 py-2 rounded">

                    Download Template

                </a>

            </div>

            <div class="bg-white shadow rounded-lg p-6">

                <h2 class="font-semibold text-lg">
                    Small PM Schedule
                </h2>

                <p class="text-sm text-gray-500 mt-2">
                    Template untuk import jadwal Small PM (kolom row = nama row di master Row Small PM)
                </p>

                <a href="{{ route('import-templates.download', 'small-pm-schedule') }}"
                    class="inline-block mt-4 bg-green-600 text-white px-4 py-2 rounded">

                    Download Template

                </a>

            </div>



            <div class="bg-white shadow rounded-lg p-6">

                <h2 class="font-semibold text-lg">
                    Predictive Work Order
                </h2>

                <p class="text-sm text-gray-500 mt-2">
                    Template import WO Action Predictive Vibration dan Air Leaked (Order Number, Machine Number, Date, Problem Description). Area dan Machine Type diambil dari Machine Master. Due Date dihitung otomatis: Date + 14 hari.
                </p>


                <a href="{{ route('import-templates.download', 'predictive-work-order') }}"
                    class="inline-block mt-4 bg-green-600 text-white px-4 py-2 rounded">

                    Download Template

                </a>

            </div>

            <div class="bg-white shadow rounded-lg p-6">

                <h2 class="font-semibold text-lg">
                    Predictive Problem dan Finding
                </h2>

                <p class="text-sm text-gray-500 mt-2">
                    Template import pilihan Problem dan Finding untuk form Action Predictive (Category, Area, Problem, Finding). Category: VIBRATION atau AIR_LEAKED. Ulangi Problem di baris berikutnya untuk menambah Finding.
                </p>


                <a href="{{ route('import-templates.download', 'predictive-problem-finding') }}"
                    class="inline-block mt-4 bg-green-600 text-white px-4 py-2 rounded">

                    Download Template

                </a>

            </div>
        </div>

    </div>
@endsection
