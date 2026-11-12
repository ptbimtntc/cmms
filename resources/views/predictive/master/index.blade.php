@extends('layouts.app')

@section('title', 'Predictive Problem')

@section('content')
    @php
        $fullWidth = true;
        $inputClass = 'input lg:min-h-9';
        $iconBtn = 'btn btn-sm min-h-11 lg:min-h-9';
        $categoryLabel = \App\Models\PredictiveWorkOrder::CATEGORIES[$category];
    @endphp

    <div x-data="{ edit: null, importOpen: {{ $errors->has('file') ? 'true' : 'false' }} }" class="space-y-5">
        <x-ui.page-header title="Predictive Problem" description="Pilihan Problem dan Finding untuk form Action. Tiap kategori dan area punya daftar sendiri, dan Finding mengikuti Problem-nya.">
            <x-slot:actions>
                <x-ui.button variant="outline" x-on:click="importOpen = true">Import CSV</x-ui.button>
            </x-slot:actions>
        </x-ui.page-header>

        @foreach (['success' => 'success', 'error' => 'danger'] as $flash => $tone)
            @if (session($flash))
                <x-ui.alert :tone="$tone">{{ session($flash) }}</x-ui.alert>
            @endif
        @endforeach
        @if ($errors->any() && ! $errors->has('file'))
            <x-ui.alert tone="danger">{{ $errors->first() }}</x-ui.alert>
        @endif

        @if (session('predictive_master_import_result'))
            @include('predictive.partials.import-result', ['resultKey' => 'predictive_master_import_result', 'labelHeader' => 'Problem > Finding', 'maxDetail' => \App\Imports\PredictiveOptionImport::MAX_DETAIL_ROWS])
        @endif

        <form method="GET" action="{{ route('predictive.master.index') }}" class="card flex flex-col gap-3 p-4 sm:flex-row sm:flex-wrap sm:items-end">
            <label class="grid w-full gap-1.5 text-xs font-medium text-text-muted sm:w-52">Kategori
                <select name="category" class="select lg:min-h-9" onchange="this.form.submit()">
                    @foreach (\App\Models\PredictiveWorkOrder::CATEGORIES as $key => $label)
                        <option value="{{ $key }}" @selected($category === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="grid w-full gap-1.5 text-xs font-medium text-text-muted sm:w-52">Area
                <select name="area" class="select lg:min-h-9" onchange="this.form.submit()" @disabled($areas->count() < 2)>
                    @foreach ($areas as $a)
                        <option value="{{ $a }}" @selected($area === $a)>{{ $a }}</option>
                    @endforeach
                </select>
            </label>
            <noscript><button class="btn btn-primary min-h-11">Terapkan</button></noscript>
        </form>

        @if ($area === null)
            <x-ui.empty-state title="Akun Anda belum punya area, jadi belum ada master yang bisa dikelola." />
        @else
            <form method="POST" action="{{ route('predictive.master.problems.store') }}" class="card flex flex-col gap-3 p-4 sm:flex-row sm:items-end">
                @csrf
                <input type="hidden" name="category" value="{{ $category }}">
                <input type="hidden" name="area" value="{{ $area }}">
                <label class="grid flex-1 gap-1.5 text-xs font-medium text-text-muted">Tambah Problem untuk {{ $categoryLabel }}, area {{ $area }}
                    <input name="name" type="text" required maxlength="255" placeholder="Contoh: Bearing aus" class="{{ $inputClass }}">
                </label>
                <x-ui.button type="submit" class="lg:min-h-9">Tambah Problem</x-ui.button>
            </form>

            @if ($problems->isEmpty())
                <x-ui.empty-state title="Belum ada Problem untuk {{ $categoryLabel }} area {{ $area }}." description="PIC belum bisa menyimpan Action untuk WO ini sampai daftarnya diisi." />
            @else
                <div class="space-y-4">
                    @foreach ($problems as $problem)
                        @php $problemUsed = $usedProblems->has($problem->id) || $problem->findings->contains(fn ($f) => $usedFindings->has($f->id)); @endphp
                        <article class="card p-4 sm:p-5 {{ $problem->is_active ? '' : 'opacity-70' }}">
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                <div class="min-w-0 flex-1">
                                    <div x-show="edit !== 'p{{ $problem->id }}'" class="flex flex-wrap items-center gap-2">
                                        <h2 class="break-words font-semibold text-text">{{ $problem->name }}</h2>
                                        @unless ($problem->is_active)<x-ui.badge>Nonaktif</x-ui.badge>@endunless
                                    </div>
                                    <form x-show="edit === 'p{{ $problem->id }}'" x-cloak method="POST" action="{{ route('predictive.master.problems.update', $problem) }}" class="flex gap-2">
                                        @csrf @method('PUT')
                                        <input name="name" type="text" required maxlength="255" value="{{ $problem->name }}" aria-label="Nama Problem" class="{{ $inputClass }}">
                                        <button class="{{ $iconBtn }} btn-primary">Simpan</button>
                                        <button type="button" @click="edit = null" class="{{ $iconBtn }} btn-outline">Batal</button>
                                    </form>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <button type="button" @click="edit = 'p{{ $problem->id }}'" class="{{ $iconBtn }} btn-outline">Rename</button>
                                    <form method="POST" action="{{ route('predictive.master.problems.update', $problem) }}">
                                        @csrf @method('PUT')
                                        <input type="hidden" name="is_active" value="{{ $problem->is_active ? 0 : 1 }}">
                                        <button class="{{ $iconBtn }} btn-outline">{{ $problem->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button>
                                    </form>
                                    <form method="POST" action="{{ route('predictive.master.problems.destroy', $problem) }}" onsubmit="return confirm(@js('Hapus Problem '.$problem->name.' beserta semua Finding-nya?'))">
                                        @csrf @method('DELETE')
                                        <button @disabled($problemUsed) @if ($problemUsed) title="Sudah dipakai di Action, nonaktifkan saja" @endif class="{{ $iconBtn }} btn-ghost text-danger hover:bg-danger-light hover:text-danger-hover disabled:pointer-events-auto">Hapus</button>
                                    </form>
                                </div>
                            </div>

                            <ul class="mt-3 space-y-2 border-l-2 border-border pl-3">
                                @forelse ($problem->findings as $finding)
                                    @php $findingUsed = $usedFindings->has($finding->id); @endphp
                                    <li class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between {{ $finding->is_active ? '' : 'opacity-70' }}">
                                        <div class="min-w-0 flex-1">
                                            <div x-show="edit !== 'f{{ $finding->id }}'" class="flex flex-wrap items-center gap-2 text-sm text-text">
                                                <span class="break-words">{{ $finding->name }}</span>
                                                @unless ($finding->is_active)<x-ui.badge>Nonaktif</x-ui.badge>@endunless
                                            </div>
                                            <form x-show="edit === 'f{{ $finding->id }}'" x-cloak method="POST" action="{{ route('predictive.master.findings.update', $finding) }}" class="flex gap-2">
                                                @csrf @method('PUT')
                                                <input name="name" type="text" required maxlength="255" value="{{ $finding->name }}" aria-label="Nama Finding" class="{{ $inputClass }}">
                                                <button class="{{ $iconBtn }} btn-primary">Simpan</button>
                                                <button type="button" @click="edit = null" class="{{ $iconBtn }} btn-outline">Batal</button>
                                            </form>
                                        </div>
                                        <div class="flex flex-wrap gap-2">
                                            <button type="button" @click="edit = 'f{{ $finding->id }}'" class="{{ $iconBtn }} btn-outline">Rename</button>
                                            <form method="POST" action="{{ route('predictive.master.findings.update', $finding) }}">
                                                @csrf @method('PUT')
                                                <input type="hidden" name="is_active" value="{{ $finding->is_active ? 0 : 1 }}">
                                                <button class="{{ $iconBtn }} btn-outline">{{ $finding->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button>
                                            </form>
                                            <form method="POST" action="{{ route('predictive.master.findings.destroy', $finding) }}" onsubmit="return confirm(@js('Hapus Finding '.$finding->name.'?'))">
                                                @csrf @method('DELETE')
                                                <button @disabled($findingUsed) @if ($findingUsed) title="Sudah dipakai di Action, nonaktifkan saja" @endif class="{{ $iconBtn }} btn-ghost text-danger hover:bg-danger-light hover:text-danger-hover disabled:pointer-events-auto">Hapus</button>
                                            </form>
                                        </div>
                                    </li>
                                @empty
                                    <li class="text-sm text-warning-text">Belum ada Finding. Problem ini belum bisa dipakai PIC sebelum punya minimal satu Finding.</li>
                                @endforelse
                            </ul>

                            <form method="POST" action="{{ route('predictive.master.findings.store', $problem) }}" class="mt-3 flex flex-col gap-2 pl-3 sm:flex-row">
                                @csrf
                                <input name="name" type="text" required maxlength="255" placeholder="Tambah Finding untuk {{ $problem->name }}" aria-label="Finding baru untuk {{ $problem->name }}" class="{{ $inputClass }}">
                                <button class="btn btn-outline min-h-11 sm:whitespace-nowrap lg:min-h-9">Tambah Finding</button>
                            </form>
                        </article>
                    @endforeach
                </div>
            @endif
        @endif

        <div x-show="importOpen" x-cloak x-transition.opacity.duration.150ms @keydown.escape.window="importOpen = false"
            class="fixed inset-0 z-50 flex items-end justify-center bg-neutral-900/40 p-0 sm:items-center sm:p-4" role="dialog" aria-modal="true" aria-labelledby="master-import-title">
            <div @click.outside="importOpen = false" class="max-h-dvh w-full max-w-lg overflow-y-auto rounded-t-card border border-border bg-surface p-5 shadow-popover sm:rounded-card sm:p-6">
                <form method="POST" action="{{ route('predictive.master.import') }}" enctype="multipart/form-data" x-data="{ busy: false }" @submit="busy = true">
                    @csrf
                    <h3 id="master-import-title" class="text-lg font-semibold text-text">Import Problem dan Finding</h3>
                    <p class="mt-1 text-sm text-text-muted">
                        File .xlsx, .xls, atau .csv dengan kolom <span class="font-medium text-text">Category, Area, Problem, Finding</span>.
                        Category: VIBRATION atau AIR_LEAKED. Finding boleh kosong untuk hanya membuat Problem.
                        Yang sudah ada dilewati dan tidak diubah.
                        @unless (auth()->user()->isAdmin())Anda hanya bisa mengimpor untuk area {{ auth()->user()->area?->name }}.@endunless
                    </p>
                    <a href="{{ route('import-templates.download', 'predictive-problem-finding') }}" class="mt-2 inline-flex min-h-11 items-center text-sm font-semibold text-primary underline-offset-2 hover:underline">Unduh template (Excel)</a>
                    @error('file')
                        <div class="alert alert-danger mt-3" role="alert">{{ $message }}</div>
                    @enderror
                    <div class="mt-4">
                        <label for="mi-file" class="form-label">File</label>
                        <input id="mi-file" name="file" type="file" required accept=".xlsx,.xls,.csv"
                            class="block min-h-11 w-full rounded-control border border-border-strong bg-surface text-sm text-text file:mr-3 file:min-h-11 file:border-0 file:bg-surface-muted file:px-3 file:text-sm file:font-semibold file:text-text">
                    </div>
                    <div class="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                        <button type="button" @click="importOpen = false" class="btn btn-ghost min-h-11">Batal</button>
                        <button type="submit" x-bind:disabled="busy" class="btn btn-primary min-h-11">
                            <span x-show="!busy">Import</span><span x-show="busy" x-cloak>Memproses…</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
