@extends('layouts.app')

@section('content')
    <div class="mb-6 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-slate-800">Row Small PM</h1>
            <p class="text-sm text-slate-500">Kelompok mesin per row untuk Small PM. Urutan dipakai saat generate jadwal.</p>
        </div>

        @unless (auth()->user()->isSupervisor())
        <div class="flex flex-col gap-3 sm:flex-row">
            <form action="{{ route('small-pm-rows.import') }}" method="POST" enctype="multipart/form-data"
                class="flex flex-col gap-3 rounded-2xl border border-slate-200 bg-slate-50 p-3 shadow-sm sm:flex-row sm:items-center"
                x-data="{ busy: false }" @submit="busy = true">
                @csrf
                <div class="flex items-center gap-2">
                    <input type="file" id="rowFileInput" name="file" accept=".csv,.xlsx,.xls" class="sr-only" required
                        onchange="document.getElementById('rowFileName').textContent = this.files.length ? this.files[0].name : 'Belum ada file'">
                    <label for="rowFileInput" class="cursor-pointer rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-100">Pilih File</label>
                    <span id="rowFileName" class="max-w-45 truncate text-sm text-slate-500">Belum ada file</span>
                </div>
                <button x-bind:disabled="busy" class="min-h-11 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50">Import</button>
                <a href="{{ route('import-templates.download', 'small-pm-row') }}" class="text-sm font-medium text-blue-700 underline-offset-2 hover:underline">Template</a>
            </form>
            <a href="{{ route('small-pm-rows.create') }}" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-700">
                Tambah Row
            </a>
        </div>
        @endunless
    </div>

    @if (session('success'))
        <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700" role="status">{{ session('success') }}</div>
    @endif

    @if ($errors->has('file'))
        <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">{{ $errors->first('file') }}</div>
    @endif

    @if (session('small_pm_row_import_result'))
        @php($ir = session('small_pm_row_import_result'))
        <div class="mb-4 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700" role="status">
            <div class="flex flex-wrap gap-x-3 gap-y-1">
                <span>Hasil import:</span>
                <span class="font-semibold text-emerald-700">{{ $ir['rows_created'] }} row baru</span>
                <span class="font-semibold text-emerald-700">{{ $ir['assigned'] }} mesin masuk row</span>
                <span class="font-semibold text-amber-700">{{ $ir['moved'] }} mesin pindah row</span>
                <span class="text-slate-600">{{ $ir['unchanged'] }} sudah sesuai</span>
                <span class="font-semibold text-rose-700">{{ $ir['skipped'] }} dilewati</span>
            </div>
            @if ($ir['errors'])
                <ul class="mt-2 list-disc space-y-0.5 pl-5 text-xs text-rose-700">
                    @foreach ($ir['errors'] as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                    @if ($ir['skipped'] > count($ir['errors']))
                        <li>dan {{ $ir['skipped'] - count($ir['errors']) }} baris lain.</li>
                    @endif
                </ul>
            @endif
        </div>
    @endif

    @if (session('error'))
        <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">{{ session('error') }}</div>
    @endif

    <form method="GET" class="mb-4 flex flex-wrap gap-2 rounded-2xl border border-slate-200 bg-slate-50 p-4 shadow-sm">
        <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari nama row..." aria-label="Cari nama row"
            class="min-h-11 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 sm:w-64">
        <button class="min-h-11 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-700">Filter</button>
        <a href="{{ route('small-pm-rows.index') }}" class="inline-flex min-h-11 items-center rounded-lg bg-slate-500 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-600">Reset</a>
    </form>

    {{-- ============ DESKTOP: Table (md and up) ============ --}}
    <div class="hidden overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm md:block">
        <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Urutan</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Nama Row</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Area</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Jumlah Mesin</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 bg-white" data-infinite-list="small-pm-rows">
                @forelse ($rows as $row)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3 text-sm text-slate-700">{{ $row->sort_order }}</td>
                        <td class="px-4 py-3 text-sm font-semibold text-slate-800">{{ $row->name }}</td>
                        <td class="px-4 py-3 text-sm text-slate-700">{{ $row->area->name ?? '-' }}</td>
                        <td class="px-4 py-3 text-sm text-slate-700">{{ $row->machines_count }}</td>
                        <td class="px-4 py-3">
                            <div class="flex flex-wrap gap-2">
                                <a href="{{ route('small-pm-rows.show', $row) }}" class="rounded-lg bg-slate-600 px-3 py-1.5 text-sm font-medium text-white transition hover:bg-slate-700">View</a>
                                @unless (auth()->user()->isSupervisor())
                                <a href="{{ route('small-pm-rows.edit', ['small_pm_row' => $row->id, 'page' => request('page')]) }}" class="rounded-lg bg-amber-500 px-3 py-1.5 text-sm font-medium text-white transition hover:bg-amber-600">Edit</a>
                                <form method="POST" action="{{ route('small-pm-rows.destroy', $row) }}" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button class="rounded-lg bg-rose-600 px-3 py-1.5 text-sm font-medium text-white transition hover:bg-rose-700"
                                        onclick="return confirm('Hapus row?')">Delete</button>
                                </form>
                                @endunless
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-sm text-slate-500">Belum ada row</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </div>

    {{-- ============ MOBILE: Card List (below md) ============ --}}
    <div class="space-y-3 md:hidden" data-infinite-list="small-pm-rows">
        @forelse ($rows as $row)
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="min-w-0">
                    <div class="truncate text-sm font-semibold text-slate-800">{{ $row->name }}</div>
                    <div class="text-xs text-slate-500">Urutan {{ $row->sort_order }} · {{ $row->area->name ?? 'Tanpa area' }} · {{ $row->machines_count }} mesin</div>
                </div>
                <div class="mt-3 flex flex-wrap gap-2 border-t border-slate-100 pt-3">
                    <a href="{{ route('small-pm-rows.show', $row) }}" class="flex-1 rounded-lg bg-slate-600 px-3 py-2 text-center text-xs font-medium text-white transition hover:bg-slate-700">View</a>
                    @unless (auth()->user()->isSupervisor())
                    <a href="{{ route('small-pm-rows.edit', ['small_pm_row' => $row->id, 'page' => request('page')]) }}" class="flex-1 rounded-lg bg-amber-500 px-3 py-2 text-center text-xs font-medium text-white transition hover:bg-amber-600">Edit</a>
                    <form method="POST" action="{{ route('small-pm-rows.destroy', $row) }}" class="flex-1">
                        @csrf
                        @method('DELETE')
                        <button class="w-full rounded-lg bg-rose-600 px-3 py-2 text-xs font-medium text-white transition hover:bg-rose-700"
                            onclick="return confirm('Hapus row?')">Delete</button>
                    </form>
                    @endunless
                </div>
            </div>
        @empty
            <p class="rounded-2xl border border-slate-200 bg-white p-6 text-center text-sm text-slate-500">Belum ada row</p>
        @endforelse
    </div>

    <div class="mt-4">
        <x-infinite-scroll :paginator="$rows" key="small-pm-rows" />
    </div>
@endsection
