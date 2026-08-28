@extends('layouts.app')
@section('content')
<div class="p-6 bg-gray-50">

    {{-- HEADER --}}
    <div class="flex justify-between items-center mb-6">

        <h1 class="text-2xl font-bold text-gray-800">
            Machine Big Problem Master
        </h1>

        <!-- ACTIONS -->
        <div class="flex flex-col md:flex-row md:items-center gap-3">

            <!-- IMPORT FORM -->
            <form action="{{ route('machine-problems.import') }}" method="POST" enctype="multipart/form-data"
                class="flex flex-col sm:flex-row sm:items-center gap-3 bg-white p-3 rounded shadow border">

                @csrf

                <!-- FILE WRAPPER -->
                <div class="flex items-center gap-2 w-full sm:w-auto">

                    <!-- Hidden Input -->
                    <input type="file" id="fileInput" name="file" accept=".csv" class="hidden"
                        onchange="updateFileName(this)">

                    <!-- Custom Button -->
                    <label for="fileInput"
                        class="cursor-pointer bg-gray-200 hover:bg-gray-300 text-sm px-3 py-2 rounded border">
                        Choose File
                    </label>

                    <!-- File Name Display -->
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
    @if (session('machine_problems_import_result'))
        @php($importResult = session('machine_problems_import_result'))
        <div class="mb-4 flex flex-wrap gap-3 rounded bg-gray-50 p-3 text-sm text-gray-700">
            <span>Import result:</span>
            <span class="font-semibold text-emerald-700">{{ $importResult['imported'] }} imported</span>
            <span class="font-semibold text-amber-700">{{ $importResult['duplicate'] }} duplicate</span>
            <span class="font-semibold text-rose-700">{{ $importResult['skipped'] }} skipped/invalid</span>
        </div>
    @endif

    {{-- FILTER BAR --}}
    <div class="bg-white p-4 rounded-lg shadow mb-4">

        <form method="GET" class="flex flex-col md:flex-row md:items-center gap-3">

            {{-- FILTER MACHINE TYPE --}}
            <select name="machine_type" class="border p-2 rounded w-full md:w-64" onchange="this.form.submit()">

                <option value="">All Machine Type</option>

                @foreach ($machines->unique('machine_type') as $machine)
                <option value="{{ $machine->machine_type }}"
                    {{ request('machine_type') == $machine->machine_type ? 'selected' : '' }}>
                    {{ $machine->machine_type }}
                </option>
                @endforeach

            </select>

            {{-- FILTER PROBLEM --}}
            <select name="problem" class="border p-2 rounded w-full md:w-64" onchange="this.form.submit()">

                <option value="">All Problems</option>

                @foreach ($machineProblems->pluck('problem')->unique() as $problem)
                <option value="{{ $problem }}" {{ request('problem') == $problem ? 'selected' : '' }}>
                    {{ $problem }}
                </option>
                @endforeach

            </select>

            {{-- SORT --}}
            <select name="sort" class="border p-2 rounded w-full md:w-64" onchange="this.form.submit()">

                <option value="">Default Sort</option>

                <option value="machine_type_asc" {{ request('sort') == 'machine_type_asc' ? 'selected' : '' }}>
                    Machine Type ↑
                </option>

                <option value="machine_type_desc" {{ request('sort') == 'machine_type_desc' ? 'selected' : '' }}>
                    Machine Type ↓
                </option>

                <option value="problem_asc" {{ request('sort') == 'problem_asc' ? 'selected' : '' }}>
                    Problem A-Z
                </option>

                <option value="problem_desc" {{ request('sort') == 'problem_desc' ? 'selected' : '' }}>
                    Problem Z-A
                </option>

            </select>

            {{-- RESET FILTER --}}
            @if (request()->hasAny(['machine_type', 'problem', 'sort']))
            <a href="{{ route('machine-problems.index') }}" class="text-sm text-red-500 hover:underline ml-2">
                Reset Filter
            </a>
            @endif

        </form>

    </div>

    {{-- ============ DESKTOP: Table (md and up) ============ --}}
    <div class="hidden bg-white rounded-lg shadow overflow-hidden md:block">

        <div class="overflow-x-auto">
        <table class="w-full text-sm">

            {{-- HEADER --}}
            <thead class="bg-gray-100 text-gray-700">
                <tr>
                    <th class="text-left p-3">Machine Type</th>
                    <th class="text-left p-3">Big Problem</th>
                    <th class="text-left p-3">Category</th>
                    <th class="text-center p-3 w-40">Action</th>
                </tr>
            </thead>

            {{-- BODY --}}
            <tbody>

                @forelse($machineProblems as $p)
                <tr class="border-b hover:bg-gray-50">

                    <td class="p-3 font-semibold">
                        {{ $p->machine_type }}
                    </td>

                    <td class="p-3">
                        {{ $p->problem }}
                    </td>
                    <td class="p-3">
                        {{ $p->category }}
                    </td>

                    <td class="p-3">

                        <div class="flex justify-center gap-2">

                            <a href="{{ route('machine-problems.edit', $p->id) }}"
                                class="bg-yellow-500 hover:bg-yellow-600 text-white px-3 py-1 rounded">
                                Edit
                            </a>

                            <form action="{{ route('machine-problems.destroy', $p->id) }}" method="POST"
                                onsubmit="return confirm('Delete this problem?')">

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
                    <td colspan="4" class="text-center p-6 text-gray-500">
                        No Big Problem data found
                    </td>
                </tr>
                @endforelse

            </tbody>

        </table>
        </div>

    </div>

    {{-- ============ MOBILE: Card List (below md) ============ --}}
    <div class="space-y-3 md:hidden">
        @forelse($machineProblems as $p)
            <div class="rounded-lg border bg-white p-4 shadow-sm">
                <div class="text-sm font-semibold text-gray-800">{{ $p->machine_type }}</div>
                <div class="mt-2 border-t pt-2 text-xs">
                    <div class="text-gray-400">Big Problem</div>
                    <div class="font-medium text-gray-700">{{ $p->problem }}</div>
                </div>
                <div class="mt-2 text-xs">
                    <div class="text-gray-400">Category</div>
                    <div class="font-medium text-gray-700">{{ $p->category }}</div>
                </div>
                <div class="mt-3 flex gap-2 border-t pt-3">
                    <a href="{{ route('machine-problems.edit', $p->id) }}"
                        class="flex-1 rounded bg-yellow-500 px-3 py-2 text-center text-xs font-medium text-white hover:bg-yellow-600">
                        Edit
                    </a>
                    <form action="{{ route('machine-problems.destroy', $p->id) }}" method="POST"
                        onsubmit="return confirm('Delete this problem?')" class="flex-1">
                        @csrf
                        @method('DELETE')
                        <button class="w-full rounded bg-red-600 px-3 py-2 text-xs font-medium text-white hover:bg-red-700">
                            Delete
                        </button>
                    </form>
                </div>
            </div>
        @empty
            <div class="rounded-lg border bg-white p-6 text-center text-sm text-gray-500">
                No Big Problem data found
            </div>
        @endforelse
    </div>

</div>
<script>
function validateFile(input) {
    const file = input.files[0];

    if (!file) return;

    const allowed = ['csv'];
    const ext = file.name.split('.').pop().toLowerCase();

    if (!allowed.includes(ext)) {
        alert("File harus .CSV saja!");
        input.value = ""; // reset file
    }
}
</script>

<script>
function updateFileName(input) {
    const fileName = input.files[0] ? input.files[0].name : 'No file chosen';
    document.getElementById('fileName').textContent = fileName;
}
</script>
@endsection