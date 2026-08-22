@extends('layouts.app')

@section('content')
    <div class="mb-6 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-slate-800">Greasing Schedule</h1>
            <p class="text-sm text-slate-500">Manage machine group greasing schedule</p>
        </div>

        @if (auth()->user()->isAdmin() || auth()->user()->isKoordinator())
        <div class="flex flex-col gap-3 sm:flex-row">
            <form action="{{ route('greasings.import') }}" method="POST" enctype="multipart/form-data"
                class="flex flex-col gap-3 rounded-2xl border border-slate-200 bg-slate-50 p-3 shadow-sm sm:flex-row sm:items-center">
                @csrf
                <div class="flex items-center gap-2">
                    <input type="file" id="greasingFileInput" name="file" accept=".csv" class="hidden" onchange="updateGreasingFileName(this)">
                    <label for="greasingFileInput" class="cursor-pointer rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-100">
                        Choose File
                    </label>
                    <span id="greasingFileName" class="max-w-45 truncate text-sm text-slate-500">No file chosen</span>
                </div>
                <button class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-emerald-700">
                    Import
                </button>
            </form>

            <a href="{{ route('greasings.create') }}" class="inline-flex items-center justify-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-700">
                Add Schedule
            </a>
        </div>
        @endif
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

    @if (session('error'))
        <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            {{ session('error') }}
        </div>
    @endif

    @if (session('greasing_import_result'))
        @php($importResult = session('greasing_import_result'))
        <div class="mb-4 flex flex-wrap gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
            <span>Import result:</span>
            <span class="font-semibold text-emerald-700">{{ $importResult['imported'] }} imported</span>
            <span class="font-semibold text-amber-700">{{ $importResult['duplicate'] }} duplicate</span>
            <span class="font-semibold text-rose-700">{{ $importResult['skipped'] }} skipped/invalid</span>
        </div>
    @endif

    <form method="GET" class="mb-4 flex flex-wrap gap-2 rounded-2xl border border-slate-200 bg-slate-50 p-4 shadow-sm">
        <input type="text" name="search" value="{{ request('search') }}" placeholder="Search cycle or pic..."
            class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 sm:w-64">

        <select name="group_id" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700">
            <option value="">All Group</option>
            @foreach ($groups as $group)
                <option value="{{ $group->id }}" {{ request('group_id') == $group->id ? 'selected' : '' }}>{{ $group->name }}</option>
            @endforeach
        </select>

        <select name="status" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700">
            <option value="">All Status</option>
            <option value="OPEN" {{ request('status') == 'OPEN' ? 'selected' : '' }}>OPEN</option>
            <option value="FINISH" {{ request('status') == 'FINISH' ? 'selected' : '' }}>FINISH</option>
            <option value="FINISH ON TIME" {{ request('status') == 'FINISH ON TIME' ? 'selected' : '' }}>FINISH ON TIME</option>
        </select>

        <button class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-700">
            Filter
        </button>

        <a href="{{ route('greasings.index') }}" class="rounded-lg bg-slate-500 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-600">
            Reset
        </a>
    </form>

    <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Order Number</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Group</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Cycle</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Plan Date</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Due Date</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">PIC</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Status</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Finding</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 bg-white">
                @forelse ($greasings as $greasing)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3 text-sm text-slate-700">{{ $greasing->order_number ?? '-' }}</td>
                        <td class="px-4 py-3 text-sm font-semibold text-slate-800">{{ $greasing->group->name ?? '-' }}</td>
                        <td class="px-4 py-3 text-sm text-slate-700">{{ $greasing->cycle }}</td>
                        <td class="px-4 py-3 text-sm text-slate-700">{{ $greasing->plan_date->format('d M Y') }}</td>
                        <td class="px-4 py-3 text-sm text-slate-700">{{ $greasing->due_date->format('d M Y') }}</td>
                        <td class="px-4 py-3 text-sm text-slate-700">{{ $greasing->pic ?? '-' }}</td>
                        <td class="px-4 py-3">
                            <span @class([
                                'rounded-full px-2.5 py-1 text-xs font-semibold',
                                'bg-amber-100 text-amber-700' => $greasing->status == 'OPEN',
                                'bg-rose-100 text-rose-700' => $greasing->status == 'FINISH',
                                'bg-emerald-100 text-emerald-700' => $greasing->status == 'FINISH ON TIME',
                            ])>
                                {{ $greasing->status }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-sm text-slate-700">
                            @if ($greasing->findings_count > 0)
                                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">[ {{ $greasing->findings_count }} {{ \Illuminate\Support\Str::plural('Finding', $greasing->findings_count) }} ]</span>
                            @else
                                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-400">[ No Finding ]</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex flex-wrap gap-2">
                                <a href="{{ route('greasings.execute', $greasing->id) }}" class="rounded-lg bg-emerald-600 px-3 py-1.5 text-sm font-medium text-white transition hover:bg-emerald-700">
                                    Execute
                                </a>
                                @if (auth()->user()->isAdmin() || auth()->user()->isKoordinator())
                                <a href="{{ route('greasings.edit', $greasing->id) }}" class="rounded-lg bg-amber-500 px-3 py-1.5 text-sm font-medium text-white transition hover:bg-amber-600">
                                    Edit
                                </a>
                                <form method="POST" action="{{ route('greasings.destroy', $greasing->id) }}" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button class="rounded-lg bg-rose-600 px-3 py-1.5 text-sm font-medium text-white transition hover:bg-rose-700"
                                        onclick="return confirm('Delete greasing schedule?')">
                                        Delete
                                    </button>
                                </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="px-4 py-8 text-center text-sm text-slate-500">No greasing schedule found</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $greasings->links() }}
    </div>

    <script>
        function updateGreasingFileName(input) {
            const fileName = input.files.length ? input.files[0].name : 'No file chosen';
            document.getElementById('greasingFileName').textContent = fileName;
        }
    </script>
@endsection
