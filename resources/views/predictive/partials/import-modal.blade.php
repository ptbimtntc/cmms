<div x-show="importOpen" x-cloak x-transition.opacity.duration.150ms
    @keydown.escape.window="importOpen = false"
    class="fixed inset-0 z-50 flex items-end justify-center bg-neutral-900/40 p-0 sm:items-center sm:p-4"
    role="dialog" aria-modal="true" aria-labelledby="predictive-import-title">
    <div @click.outside="importOpen = false" class="max-h-dvh w-full max-w-lg overflow-y-auto rounded-t-card border border-border bg-surface p-5 shadow-popover sm:rounded-card sm:p-6">
        <form method="POST" action="{{ route('predictive.import') }}" enctype="multipart/form-data" x-data="{ busy: false }" @submit="busy = true">
            @csrf
            <input type="hidden" name="category" value="{{ $category }}">
            <h3 id="predictive-import-title" class="text-lg font-semibold text-text">Import WO {{ $categoryLabel }}</h3>
            <p class="mt-1 text-sm text-text-muted">
                File .xlsx, .xls, atau .csv dengan kolom
                <span class="font-medium text-text">Order Number, Machine Number, Date, Problem Description</span>. Due Date dihitung otomatis: Date + 14 hari.
                Area dan Machine Type diambil dari Machine Master.
                Order Number yang sudah ada dilewati, WO existing tidak diubah.
            </p>
            <a href="{{ route('import-templates.download', 'predictive-work-order') }}" class="mt-2 inline-flex min-h-11 items-center text-sm font-semibold text-primary underline-offset-2 hover:underline">Unduh template (Excel)</a>

            @error('file')
                <div class="alert alert-danger mt-3" role="alert">{{ $message }}</div>
            @enderror

            <div class="mt-4">
                <label for="pi-file" class="form-label">File</label>
                <input id="pi-file" name="file" type="file" required accept=".xlsx,.xls,.csv"
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
