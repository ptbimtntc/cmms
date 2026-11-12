@extends('layouts.app')

@section('title', 'Predictive Problem')

@section('content')
    @php
        $inputClass = 'w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700 outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-100';
        $iconBtn = 'inline-flex min-h-11 items-center justify-center rounded-lg px-3 text-xs font-semibold focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-400 sm:min-h-0 sm:py-1.5';
        $categoryLabel = \App\Models\PredictiveWorkOrder::CATEGORIES[$category];
    @endphp

    <div x-data="{ edit: null, importOpen: {{ $errors->has('file') ? 'true' : 'false' }} }">
        <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">Predictive Problem</h1>
                <p class="mt-1 text-sm text-slate-500">Pilihan Problem dan Finding untuk form Action. Tiap kategori dan area punya daftar sendiri, dan Finding mengikuti Problem-nya.</p>
            </div>
            <button type="button" @click="importOpen = true" class="inline-flex min-h-11 shrink-0 items-center justify-center whitespace-nowrap rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Import CSV</button>
        </div>

        @foreach ([
            'success' => 'border-emerald-200 bg-emerald-50 text-emerald-800',
            'error' => 'border-rose-200 bg-rose-50 text-rose-800',
        ] as $flash => $classes)
            @if (session($flash))
                <div class="mb-4 rounded-xl border px-4 py-3 text-sm font-medium {{ $classes }}" role="{{ $flash === 'error' ? 'alert' : 'status' }}">{{ session($flash) }}</div>
            @endif
        @endforeach
        @if ($errors->any() && ! $errors->has('file'))
            <div class="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-800" role="alert">{{ $errors->first() }}</div>
        @endif

        @if (session('predictive_master_import_result'))
            @include('predictive.partials.import-result', ['resultKey' => 'predictive_master_import_result', 'labelHeader' => 'Problem > Finding', 'maxDetail' => \App\Imports\PredictiveOptionImport::MAX_DETAIL_ROWS, 'class' => 'mb-5'])
        @endif

        <form method="GET" action="{{ route('predictive.master.index') }}" class="mb-5 flex flex-wrap items-end gap-2 rounded-2xl border border-slate-200 bg-slate-50 p-3">
            <label class="block w-full text-xs font-medium text-slate-600 sm:w-52">Kategori
                <select name="category" class="{{ $inputClass }} mt-1" onchange="this.form.submit()">
                    @foreach (\App\Models\PredictiveWorkOrder::CATEGORIES as $key => $label)
                        <option value="{{ $key }}" @selected($category === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="block w-full text-xs font-medium text-slate-600 sm:w-52">Area
                <select name="area" class="{{ $inputClass }} mt-1" onchange="this.form.submit()" @disabled($areas->count() < 2)>
                    @foreach ($areas as $a)
                        <option value="{{ $a }}" @selected($area === $a)>{{ $a }}</option>
                    @endforeach
                </select>
            </label>
            <noscript><button class="min-h-11 rounded-lg bg-slate-800 px-5 text-sm font-semibold text-white">Terapkan</button></noscript>
        </form>

        @if ($area === null)
            <div class="rounded-2xl border border-dashed border-slate-300 bg-white px-4 py-12 text-center text-sm text-slate-500">Akun Anda belum punya area, jadi belum ada master yang bisa dikelola.</div>
        @else
            <form method="POST" action="{{ route('predictive.master.problems.store') }}" class="mb-5 flex flex-col gap-2 rounded-2xl border border-slate-200 bg-white p-3 shadow-sm sm:flex-row sm:items-end">
                @csrf
                <input type="hidden" name="category" value="{{ $category }}">
                <input type="hidden" name="area" value="{{ $area }}">
                <label class="block flex-1 text-xs font-medium text-slate-600">Tambah Problem untuk {{ $categoryLabel }}, area {{ $area }}
                    <input name="name" type="text" required maxlength="255" placeholder="Contoh: Bearing aus" class="{{ $inputClass }} mt-1">
                </label>
                <button class="min-h-11 rounded-lg bg-sky-600 px-5 text-sm font-semibold text-white hover:bg-sky-700">Tambah Problem</button>
            </form>

            @if ($problems->isEmpty())
                <div class="rounded-2xl border border-dashed border-slate-300 bg-white px-4 py-12 text-center text-sm text-slate-500">
                    Belum ada Problem untuk {{ $categoryLabel }} area {{ $area }}. PIC belum bisa menyimpan Action untuk WO ini sampai daftarnya diisi.
                </div>
            @else
                <div class="space-y-4">
                    @foreach ($problems as $problem)
                        @php $problemUsed = $usedProblems->has($problem->id) || $problem->findings->contains(fn ($f) => $usedFindings->has($f->id)); @endphp
                        <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm {{ $problem->is_active ? '' : 'opacity-70' }}">
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                <div class="min-w-0 flex-1">
                                    <div x-show="edit !== 'p{{ $problem->id }}'" class="flex flex-wrap items-center gap-2">
                                        <h2 class="break-words font-semibold text-slate-900">{{ $problem->name }}</h2>
                                        @unless ($problem->is_active)<span class="rounded-full bg-slate-200 px-2 py-0.5 text-xs font-semibold text-slate-700">Nonaktif</span>@endunless
                                    </div>
                                    <form x-show="edit === 'p{{ $problem->id }}'" x-cloak method="POST" action="{{ route('predictive.master.problems.update', $problem) }}" class="flex gap-2">
                                        @csrf @method('PUT')
                                        <input name="name" type="text" required maxlength="255" value="{{ $problem->name }}" aria-label="Nama Problem" class="{{ $inputClass }}">
                                        <button class="{{ $iconBtn }} bg-sky-600 text-white hover:bg-sky-700">Simpan</button>
                                        <button type="button" @click="edit = null" class="{{ $iconBtn }} border border-slate-300 text-slate-700 hover:bg-slate-50">Batal</button>
                                    </form>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <button type="button" @click="edit = 'p{{ $problem->id }}'" class="{{ $iconBtn }} border border-slate-300 text-slate-700 hover:bg-slate-50">Rename</button>
                                    <form method="POST" action="{{ route('predictive.master.problems.update', $problem) }}">
                                        @csrf @method('PUT')
                                        <input type="hidden" name="is_active" value="{{ $problem->is_active ? 0 : 1 }}">
                                        <button class="{{ $iconBtn }} border border-slate-300 text-slate-700 hover:bg-slate-50">{{ $problem->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button>
                                    </form>
                                    <form method="POST" action="{{ route('predictive.master.problems.destroy', $problem) }}" onsubmit="return confirm(@js('Hapus Problem '.$problem->name.' beserta semua Finding-nya?'))">
                                        @csrf @method('DELETE')
                                        <button @disabled($problemUsed) @if ($problemUsed) title="Sudah dipakai di Action, nonaktifkan saja" @endif class="{{ $iconBtn }} border border-rose-200 text-rose-700 hover:bg-rose-50 disabled:cursor-not-allowed disabled:opacity-40">Hapus</button>
                                    </form>
                                </div>
                            </div>

                            <ul class="mt-3 space-y-2 border-l-2 border-slate-200 pl-3">
                                @forelse ($problem->findings as $finding)
                                    @php $findingUsed = $usedFindings->has($finding->id); @endphp
                                    <li class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between {{ $finding->is_active ? '' : 'opacity-70' }}">
                                        <div class="min-w-0 flex-1">
                                            <div x-show="edit !== 'f{{ $finding->id }}'" class="flex flex-wrap items-center gap-2 text-sm text-slate-800">
                                                <span class="break-words">{{ $finding->name }}</span>
                                                @unless ($finding->is_active)<span class="rounded-full bg-slate-200 px-2 py-0.5 text-xs font-semibold text-slate-700">Nonaktif</span>@endunless
                                            </div>
                                            <form x-show="edit === 'f{{ $finding->id }}'" x-cloak method="POST" action="{{ route('predictive.master.findings.update', $finding) }}" class="flex gap-2">
                                                @csrf @method('PUT')
                                                <input name="name" type="text" required maxlength="255" value="{{ $finding->name }}" aria-label="Nama Finding" class="{{ $inputClass }}">
                                                <button class="{{ $iconBtn }} bg-sky-600 text-white hover:bg-sky-700">Simpan</button>
                                                <button type="button" @click="edit = null" class="{{ $iconBtn }} border border-slate-300 text-slate-700 hover:bg-slate-50">Batal</button>
                                            </form>
                                        </div>
                                        <div class="flex flex-wrap gap-2">
                                            <button type="button" @click="edit = 'f{{ $finding->id }}'" class="{{ $iconBtn }} border border-slate-300 text-slate-700 hover:bg-slate-50">Rename</button>
                                            <form method="POST" action="{{ route('predictive.master.findings.update', $finding) }}">
                                                @csrf @method('PUT')
                                                <input type="hidden" name="is_active" value="{{ $finding->is_active ? 0 : 1 }}">
                                                <button class="{{ $iconBtn }} border border-slate-300 text-slate-700 hover:bg-slate-50">{{ $finding->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button>
                                            </form>
                                            <form method="POST" action="{{ route('predictive.master.findings.destroy', $finding) }}" onsubmit="return confirm(@js('Hapus Finding '.$finding->name.'?'))">
                                                @csrf @method('DELETE')
                                                <button @disabled($findingUsed) @if ($findingUsed) title="Sudah dipakai di Action, nonaktifkan saja" @endif class="{{ $iconBtn }} border border-rose-200 text-rose-700 hover:bg-rose-50 disabled:cursor-not-allowed disabled:opacity-40">Hapus</button>
                                            </form>
                                        </div>
                                    </li>
                                @empty
                                    <li class="text-sm text-amber-700">Belum ada Finding. Problem ini belum bisa dipakai PIC sebelum punya minimal satu Finding.</li>
                                @endforelse
                            </ul>

                            <form method="POST" action="{{ route('predictive.master.findings.store', $problem) }}" class="mt-3 flex flex-col gap-2 pl-3 sm:flex-row">
                                @csrf
                                <input name="name" type="text" required maxlength="255" placeholder="Tambah Finding untuk {{ $problem->name }}" aria-label="Finding baru untuk {{ $problem->name }}" class="{{ $inputClass }}">
                                <button class="min-h-11 rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50 sm:whitespace-nowrap">+ Add Finding</button>
                            </form>
                        </article>
                    @endforeach
                </div>
            @endif
        @endif

        <div x-show="importOpen" x-cloak x-transition.opacity.duration.150ms @keydown.escape.window="importOpen = false"
            class="fixed inset-0 z-50 flex items-end justify-center bg-black/40 p-0 sm:items-center sm:p-4" role="dialog" aria-modal="true" aria-labelledby="master-import-title">
            <div @click.outside="importOpen = false" class="max-h-dvh w-full max-w-lg overflow-y-auto rounded-t-2xl bg-white p-5 shadow-xl sm:rounded-2xl sm:p-6">
                <form method="POST" action="{{ route('predictive.master.import') }}" enctype="multipart/form-data" x-data="{ busy: false }" @submit="busy = true">
                    @csrf
                    <h3 id="master-import-title" class="text-lg font-semibold text-slate-900">Import Problem dan Finding</h3>
                    <p class="mt-1 text-sm text-slate-500">
                        File .xlsx, .xls, atau .csv dengan kolom <span class="font-medium text-slate-700">Category, Area, Problem, Finding</span>.
                        Category: VIBRATION atau AIR_LEAKED. Finding boleh kosong untuk hanya membuat Problem.
                        Yang sudah ada dilewati dan tidak diubah.
                        @unless (auth()->user()->isAdmin())Anda hanya bisa mengimpor untuk area {{ auth()->user()->area?->name }}.@endunless
                    </p>
                    <a href="{{ route('import-templates.download', 'predictive-problem-finding') }}" class="mt-2 inline-block text-sm font-semibold text-sky-700 hover:underline">Unduh template (Excel)</a>
                    @error('file')
                        <div class="mt-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700" role="alert">{{ $message }}</div>
                    @enderror
                    <div class="mt-4">
                        <label for="mi-file" class="mb-1.5 block text-sm font-medium text-slate-700">File</label>
                        <input id="mi-file" name="file" type="file" required accept=".xlsx,.xls,.csv"
                            class="block w-full rounded-lg border border-slate-300 text-sm file:mr-3 file:border-0 file:bg-slate-100 file:px-3 file:py-2.5 file:text-sm file:font-semibold file:text-slate-700">
                    </div>
                    <div class="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                        <button type="button" @click="importOpen = false" class="min-h-11 rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Batal</button>
                        <button type="submit" x-bind:disabled="busy" class="min-h-11 rounded-lg bg-sky-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-sky-700 disabled:opacity-50">
                            <span x-show="!busy">Import</span><span x-show="busy" x-cloak>Memproses…</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
