@extends('layouts.app')

@section('content')
    <div class="flex justify-between items-center mb-6">

        <!-- TITLE -->
        <h1 class="text-2xl font-bold text-gray-800">
            Machine Problem Findings
        </h1>

        <!-- ACTIONS -->
        <div class="flex flex-col md:flex-row md:items-center gap-3">

            <!-- IMPORT FORM -->
            <form action="{{ route('machine-problem-findings.import') }}" method="POST" enctype="multipart/form-data"
                class="flex flex-col sm:flex-row sm:items-center gap-3 bg-white p-3 rounded shadow border">

                @csrf

                <!-- FILE WRAPPER -->
                <div class="flex items-center gap-2 w-full sm:w-auto">

                    <!-- Hidden Input -->
                    <input type="file" id="fileInput" name="file" accept=".csv,.xlsx,.xls" class="hidden"
                        onchange="updateFileName(this)">

                    <!-- Custom Button -->
                    <label for="fileInput"
                        class="cursor-pointer bg-gray-200 hover:bg-gray-300 text-sm px-3 py-2 rounded border">

                        Choose File

                    </label>

                    <!-- File Name -->
                    <span id="fileName" class="text-sm text-gray-600 truncate max-w-[200px]">

                        No file chosen

                    </span>

                </div>

                <!-- IMPORT BUTTON -->
                <button class="bg-green-600 hover:bg-green-700 text-white text-sm px-4 py-2 rounded w-full sm:w-auto">

                    Import

                </button>

            </form>

        </div>

    </div>

    {{-- ALERT ERROR --}}
    @if ($errors->has('file'))
        <div class="bg-red-100 text-red-700 p-3 rounded mb-3">
            {{ $errors->first('file') }}
        </div>
    @endif

    {{-- ALERT --}}
    @if (session('success'))
        <div class="bg-green-100 text-green-700 p-3 rounded mb-4">
            {{ session('success') }}
        </div>
    @endif

    {{-- IMPORT RESULT --}}
    @if (session('machine_problem_findings_import_result'))
        @php($importResult = session('machine_problem_findings_import_result'))
        <div class="mb-4 flex flex-wrap gap-3 rounded bg-gray-50 p-3 text-sm text-gray-700">
            <span>Import result:</span>
            <span class="font-semibold text-emerald-700">{{ $importResult['imported'] }} imported</span>
            <span class="font-semibold text-amber-700">{{ $importResult['duplicate'] }} duplicate</span>
            <span class="font-semibold text-rose-700">{{ $importResult['skipped'] }} skipped/invalid</span>
        </div>
    @endif

    {{-- FILTER --}}
    <form method="GET" class="mb-4 bg-white p-3 rounded shadow flex flex-wrap gap-2">

        {{-- SORT --}}
        <select name="sort" class="border rounded px-3 py-2">

            <option value="">Default Sort</option>

            <option value="category_asc" {{ request('sort') == 'category_asc' ? 'selected' : '' }}>
                Category ↑
            </option>

            <option value="category_desc" {{ request('sort') == 'category_desc' ? 'selected' : '' }}>
                Category ↓
            </option>

            <option value="finding_asc" {{ request('sort') == 'finding_asc' ? 'selected' : '' }}>
                Finding ↑
            </option>

            <option value="finding_desc" {{ request('sort') == 'finding_desc' ? 'selected' : '' }}>
                Finding ↓
            </option>

        </select>

        {{-- SEARCH --}}
        <input type="text" name="search" value="{{ request('search') }}" placeholder="Search..."
            class="border rounded px-3 py-2 w-64">

        {{-- CATEGORY --}}
        <select name="category" class="border rounded px-3 py-2">

            <option value="">All Category</option>

            @foreach ($categories as $category)
                <option value="{{ $category }}" {{ request('category') == $category ? 'selected' : '' }}>

                    {{ $category }}

                </option>
            @endforeach

        </select>

        <button class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">

            Filter

        </button>

        <a href="{{ route('machine-problem-findings.index') }}"
            class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded">

            Reset

        </a>

    </form>

    {{-- ============ DESKTOP: Table (md and up) ============ --}}
    <div class="hidden bg-white shadow rounded overflow-hidden md:block">

        <div class="overflow-x-auto">
        <table class="w-full">

            <thead class="bg-gray-100">

                <tr>

                    <th class="px-4 py-3 text-left">Category</th>

                    <th class="px-4 py-3 text-left">Finding</th>

                    <th class="px-4 py-3 text-center">Action</th>

                </tr>

            </thead>

            <tbody>

                @forelse($findings as $finding)
                    <tr class="border-t">

                        <td class="px-4 py-3">

                            {{ $finding->category }}

                        </td>

                        <td class="px-4 py-3">

                            {{ $finding->finding }}

                        </td>

                        <td class="px-4 py-3">

                            <div class="flex justify-center gap-2">

                                <a href="{{ route('machine-problem-findings.edit', $finding->id) }}"
                                    class="bg-yellow-500 hover:bg-yellow-600 text-white px-3 py-1 rounded">

                                    Edit

                                </a>

                                <form action="{{ route('machine-problem-findings.destroy', $finding->id) }}" method="POST"
                                    onsubmit="return confirm('Delete this finding?')">

                                    @csrf
                                    @method('DELETE')

                                    <button class="bg-red-600 hover:bg-red-700 text-white px-3 py-1 rounded">

                                        Delete

                                    </button>

                                </form>

                            </div>

                        </td>

                    </tr>

                @empty

                    <tr>

                        <td colspan="3" class="text-center py-6 text-gray-500">

                            No data found.

                        </td>

                    </tr>
                @endforelse

            </tbody>

        </table>
        </div>

    </div>

    {{-- ============ MOBILE: Card List (below md) ============ --}}
    <div class="space-y-3 md:hidden">
        @forelse($findings as $finding)
            <div class="rounded border bg-white p-4 shadow-sm">
                <div class="text-xs text-gray-400">Category</div>
                <div class="text-sm font-semibold text-gray-800">{{ $finding->category }}</div>
                <div class="mt-2 border-t pt-2 text-xs text-gray-400">Finding</div>
                <div class="text-sm text-gray-700">{{ $finding->finding }}</div>
                <div class="mt-3 flex gap-2 border-t pt-3">
                    <a href="{{ route('machine-problem-findings.edit', $finding->id) }}"
                        class="flex-1 rounded bg-yellow-500 px-3 py-2 text-center text-xs font-medium text-white hover:bg-yellow-600">
                        Edit
                    </a>
                    <form action="{{ route('machine-problem-findings.destroy', $finding->id) }}" method="POST"
                        onsubmit="return confirm('Delete this finding?')" class="flex-1">
                        @csrf
                        @method('DELETE')
                        <button class="w-full rounded bg-red-600 px-3 py-2 text-xs font-medium text-white hover:bg-red-700">
                            Delete
                        </button>
                    </form>
                </div>
            </div>
        @empty
            <div class="rounded border bg-white p-6 text-center text-sm text-gray-500">
                No data found.
            </div>
        @endforelse
    </div>

    <div class="mt-4">

        {{ $findings->links() }}

    </div>

    </div>

    <script>
        function updateFileName(input) {
            const fileName = input.files.length ?
                input.files[0].name :
                'No file chosen';

            document.getElementById('fileName').innerText = fileName;
        }
    </script>
@endsection
