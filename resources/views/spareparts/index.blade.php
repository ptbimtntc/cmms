@extends('layouts.app')

@section('content')
    <div class="mb-6 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-slate-800">Sparepart Master</h1>
            <p class="text-sm text-slate-500">Manage sparepart master data</p>
        </div>

        @if (in_array(auth()->user()->role, ['ADMIN', 'KOORDINATOR WWD', 'KOORDINATOR BUL']))
        <form action="{{ route('spareparts.import') }}" method="POST" enctype="multipart/form-data"
            class="flex flex-col gap-3 rounded-2xl border border-slate-200 bg-slate-50 p-3 shadow-sm sm:flex-row sm:items-center">
            @csrf
            <div class="flex items-center gap-2">
                <input type="file" id="sparepartFileInput" name="file" accept=".csv" class="hidden" onchange="updateSparepartFileName(this)">
                <label for="sparepartFileInput" class="cursor-pointer rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-100">
                    Choose File
                </label>
                <span id="sparepartFileName" class="max-w-48 truncate text-sm text-slate-500">No file chosen</span>
            </div>
            <button class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-emerald-700">
                Import
            </button>
        </form>
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

    @if (session('spareparts_import_result'))
        @php($importResult = session('spareparts_import_result'))
        <div class="mb-4 flex flex-wrap gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
            <span>Import result:</span>
            <span class="font-semibold text-emerald-700">{{ $importResult['imported'] }} imported</span>
            <span class="font-semibold text-amber-700">{{ $importResult['duplicate'] }} duplicate</span>
            <span class="font-semibold text-rose-700">{{ $importResult['skipped'] }} skipped/invalid</span>
        </div>
    @endif

    <form method="GET" class="mb-4 flex flex-wrap gap-2 rounded-2xl border border-slate-200 bg-slate-50 p-4 shadow-sm">
        <select name="sort" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700">
            <option value="">Default Sort</option>
            <option value="material_asc" {{ request('sort') == 'material_asc' ? 'selected' : '' }}>Material No ↑</option>
            <option value="material_desc" {{ request('sort') == 'material_desc' ? 'selected' : '' }}>Material No ↓</option>
            <option value="stock_asc" {{ request('sort') == 'stock_asc' ? 'selected' : '' }}>Stock Low → High</option>
            <option value="stock_desc" {{ request('sort') == 'stock_desc' ? 'selected' : '' }}>Stock High → Low</option>
        </select>

        <input type="text" name="search" value="{{ request('search') }}" placeholder="Search material or description..."
            class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 sm:w-64">

        <select name="machine_type" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700">
            <option value="">All Machine Type</option>
            @foreach ($machineTypes as $type)
                <option value="{{ $type }}" {{ request('machine_type') == $type ? 'selected' : '' }}>{{ $type }}</option>
            @endforeach
        </select>

        <select name="status" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700">
            <option value="">All Status</option>
            <option value="ACTIVE" {{ request('status') == 'ACTIVE' ? 'selected' : '' }}>ACTIVE</option>
            <option value="INACTIVE" {{ request('status') == 'INACTIVE' ? 'selected' : '' }}>INACTIVE</option>
        </select>

        <select name="segment" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700">
            <option value="">All Segment</option>
            @foreach ($segments as $segment)
                <option value="{{ $segment }}" {{ request('segment') == $segment ? 'selected' : '' }}>{{ $segment }}</option>
            @endforeach
        </select>

        <button class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-700">Filter</button>
        <a href="{{ route('spareparts.index') }}" class="rounded-lg bg-slate-500 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-600">Reset</a>
    </form>

    {{-- ============ DESKTOP: Table (md and up) ============ --}}
    <div class="hidden overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm md:block">
        <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Material No</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Description</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Machine Type</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Stock</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Unit</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">ROP</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Location</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Price</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Status</th>
                    @if (in_array(auth()->user()->role, ['ADMIN', 'KOORDINATOR WWD', 'KOORDINATOR BUL']))
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Action</th>
                    @endif
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 bg-white">
                @forelse($spareparts as $sparepart)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3 text-sm text-slate-700">{{ $sparepart->material_number }}</td>
                        <td class="px-4 py-3 text-sm text-slate-700">{{ $sparepart->description }}</td>
                        <td class="px-4 py-3 text-sm text-slate-700">{{ $sparepart->machine_type }}</td>
                        <td class="px-4 py-3 text-sm text-slate-700">
                            @if ($sparepart->stock <= $sparepart->rop)
                                <span class="font-semibold text-red-600">{{ $sparepart->stock }} (LOW)</span>
                            @else
                                {{ $sparepart->stock }}
                            @endif
                        </td>
                        <td class="px-4 py-3 text-sm text-slate-700">{{ $sparepart->unit }}</td>
                        <td class="px-4 py-3 text-sm text-slate-700">{{ $sparepart->rop }}</td>
                        <td class="px-4 py-3 text-sm text-slate-700">{{ $sparepart->location }}</td>
                        <td class="px-4 py-3 text-sm text-slate-700">$ {{ number_format($sparepart->price, 0, ',', '.') }}</td>
                        <td class="px-4 py-3">
                            @if ($sparepart->status == 'ACTIVE')
                                <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700">ACTIVE</span>
                            @else
                                <span class="rounded-full bg-rose-100 px-2.5 py-1 text-xs font-semibold text-rose-700">INACTIVE</span>
                            @endif
                        </td>
                        @if (in_array(auth()->user()->role, ['ADMIN', 'KOORDINATOR WWD', 'KOORDINATOR BUL']))
                        <td class="px-4 py-3">
                            <div class="flex flex-wrap gap-2">
                                <a href="{{ route('spareparts.edit', $sparepart->id) }}" class="rounded-lg bg-amber-500 px-3 py-1.5 text-sm font-medium text-white transition hover:bg-amber-600">Edit</a>
                                <form action="{{ route('spareparts.destroy', $sparepart->id) }}" method="POST">
                                    @csrf
                                    @method('DELETE')
                                    <button onclick="return confirm('Delete sparepart?')" class="rounded-lg bg-rose-600 px-3 py-1.5 text-sm font-medium text-white transition hover:bg-rose-700">Delete</button>
                                </form>
                            </div>
                        </td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="px-4 py-8 text-center text-sm text-slate-500">No Data Found</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </div>

    {{-- ============ MOBILE: Card List (below md) ============ --}}
    <div class="space-y-3 md:hidden">
        @forelse ($spareparts as $sparepart)
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="mb-3 flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <div class="truncate text-sm font-semibold text-slate-800">{{ $sparepart->material_number }}</div>
                        <div class="truncate text-xs text-slate-500">{{ $sparepart->description }}</div>
                    </div>
                    @if ($sparepart->status == 'ACTIVE')
                        <span class="shrink-0 rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700">ACTIVE</span>
                    @else
                        <span class="shrink-0 rounded-full bg-rose-100 px-2.5 py-1 text-xs font-semibold text-rose-700">INACTIVE</span>
                    @endif
                </div>
                <div class="grid grid-cols-2 gap-y-2 border-t border-slate-100 pt-3 text-xs">
                    <div>
                        <div class="text-slate-400">Machine Type</div>
                        <div class="font-medium text-slate-700">{{ $sparepart->machine_type ?: '-' }}</div>
                    </div>
                    <div>
                        <div class="text-slate-400">Stock</div>
                        <div class="font-medium text-slate-700">
                            @if ($sparepart->stock <= $sparepart->rop)
                                <span class="font-semibold text-red-600">{{ $sparepart->stock }} (LOW)</span>
                            @else
                                {{ $sparepart->stock }}
                            @endif
                        </div>
                    </div>
                    <div>
                        <div class="text-slate-400">Unit</div>
                        <div class="font-medium text-slate-700">{{ $sparepart->unit ?: '-' }}</div>
                    </div>
                    <div>
                        <div class="text-slate-400">ROP</div>
                        <div class="font-medium text-slate-700">{{ $sparepart->rop }}</div>
                    </div>
                    <div>
                        <div class="text-slate-400">Location</div>
                        <div class="font-medium text-slate-700">{{ $sparepart->location ?: '-' }}</div>
                    </div>
                    <div>
                        <div class="text-slate-400">Price</div>
                        <div class="font-medium text-slate-700">$ {{ number_format($sparepart->price, 0, ',', '.') }}</div>
                    </div>
                </div>
                @if (in_array(auth()->user()->role, ['ADMIN', 'KOORDINATOR WWD', 'KOORDINATOR BUL']))
                    <div class="mt-3 flex flex-wrap gap-2 border-t border-slate-100 pt-3">
                        <a href="{{ route('spareparts.edit', $sparepart->id) }}" class="flex-1 rounded-lg bg-amber-500 px-3 py-2 text-center text-xs font-medium text-white transition hover:bg-amber-600">Edit</a>
                        <form action="{{ route('spareparts.destroy', $sparepart->id) }}" method="POST" class="flex-1">
                            @csrf
                            @method('DELETE')
                            <button onclick="return confirm('Delete sparepart?')" class="w-full rounded-lg bg-rose-600 px-3 py-2 text-xs font-medium text-white transition hover:bg-rose-700">Delete</button>
                        </form>
                    </div>
                @endif
            </div>
        @empty
            <p class="rounded-2xl border border-slate-200 bg-white p-6 text-center text-sm text-slate-500">No Data Found</p>
        @endforelse
    </div>

    <div class="mt-4">
        {{ $spareparts->appends(request()->query())->links() }}
    </div>

    <script>
        function updateSparepartFileName(input) {
            const fileName = input.files.length ? input.files[0].name : 'No file chosen';
            document.getElementById('sparepartFileName').textContent = fileName;
        }
    </script>
@endsection
