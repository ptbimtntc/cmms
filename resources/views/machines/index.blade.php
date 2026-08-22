@extends('layouts.app')

@section('content')
    <div class="mb-6 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-slate-800">Machine Master</h1>
            <p class="text-sm text-slate-500">Manage machine master data</p>
        </div>

        <form action="{{ route('machines.import') }}" method="POST" enctype="multipart/form-data"
            class="flex flex-col gap-3 rounded-2xl border border-slate-200 bg-slate-50 p-3 shadow-sm sm:flex-row sm:items-center">
            @csrf
            <div class="flex items-center gap-2">
                <input type="file" id="fileInput" name="file" accept=".csv" class="hidden" onchange="updateFileName(this)">
                <label for="fileInput" class="cursor-pointer rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-100">
                    Choose File
                </label>
                <span id="fileName" class="max-w-[180px] truncate text-sm text-slate-500">No file chosen</span>
            </div>
            <button class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-emerald-700">
                Import
            </button>
        </form>
    </div>

    @if ($errors->has('file'))
        <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            {{ $errors->first('file') }}
        </div>
    @endif

    @if (session('success'))
        <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
            {{ session('success') }}
        </div>
    @endif

    <form method="GET" class="mb-4 flex flex-wrap gap-2 rounded-2xl border border-slate-200 bg-slate-50 p-4 shadow-sm">
        <select name="sort" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700">
            <option value="">Default Sort</option>
            <option value="machine_number_asc" {{ request('sort') == 'machine_number_asc' ? 'selected' : '' }}>Machine No ↑</option>
            <option value="machine_number_desc" {{ request('sort') == 'machine_number_desc' ? 'selected' : '' }}>Machine No ↓</option>
            <option value="area_asc" {{ request('sort') == 'area_asc' ? 'selected' : '' }}>Area A-Z</option>
            <option value="area_desc" {{ request('sort') == 'area_desc' ? 'selected' : '' }}>Area Z-A</option>
        </select>

        <input type="text" name="search" value="{{ request('search') }}" placeholder="Search machine..."
            class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 sm:w-64">

        <select name="machine_type" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700">
            <option value="">All Type</option>
            @foreach ($machineTypes as $type)
                <option value="{{ $type->machine_type }}" {{ request('machine_type') == $type->machine_type ? 'selected' : '' }}>
                    {{ $type->machine_type }}
                </option>
            @endforeach
        </select>

        <select name="area" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700">
            <option value="">All Area</option>
            @foreach ($areas as $a)
                <option value="{{ $a->area }}" {{ request('area') == $a->area ? 'selected' : '' }}>
                    {{ $a->area }}
                </option>
            @endforeach
        </select>

        <select name="status" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700">
            <option value="">All Status</option>
            <option value="ACTIVE" {{ request('status') == 'ACTIVE' ? 'selected' : '' }}>ACTIVE</option>
            <option value="INACTIVE" {{ request('status') == 'INACTIVE' ? 'selected' : '' }}>INACTIVE</option>
        </select>

        <button class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-700">
            Filter
        </button>

        <a href="{{ route('machines.index') }}" class="rounded-lg bg-slate-500 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-600">
            Reset
        </a>
    </form>

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Area</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Machine Type</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Machine Number</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Group</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Status</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 bg-white">
                @forelse($machines as $m)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3 text-sm text-slate-700">{{ $m->area }}</td>
                        <td class="px-4 py-3 text-sm text-slate-700">{{ $m->machine_type }}</td>
                        <td class="px-4 py-3 text-sm font-semibold text-slate-800">{{ $m->machine_number }}</td>
                        <td class="px-4 py-3 text-sm text-slate-700">{{ $m->group->name ?? '-' }}</td>
                        <td class="px-4 py-3">
                            <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $m->status == 'ACTIVE' ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700' }}">
                                {{ $m->status }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex flex-wrap gap-2">
                                <a href="{{ route('machines.edit', $m->id) }}" class="rounded-lg bg-amber-500 px-3 py-1.5 text-sm font-medium text-white transition hover:bg-amber-600">
                                    Edit
                                </a>
                                <form method="POST" action="{{ route('machines.destroy', $m->id) }}" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button class="rounded-lg bg-rose-600 px-3 py-1.5 text-sm font-medium text-white transition hover:bg-rose-700"
                                        onclick="return confirm('Delete machine?')">
                                        Delete
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-sm text-slate-500">No machines found</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $machines->links() }}
    </div>

    <script>
        function validateFile(input) {
            const file = input.files[0];
            if (!file) return;
            const allowed = ['csv'];
            const ext = file.name.split('.').pop().toLowerCase();
            if (!allowed.includes(ext)) {
                alert("File harus .CSV saja!");
                input.value = "";
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
