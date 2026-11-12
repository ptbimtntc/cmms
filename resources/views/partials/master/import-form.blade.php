{{--
    File import for a master list. Posts `file` to $action; the server validates the type again.
    Params: action, accept (default .csv,.xlsx,.xls), hint (optional), id (default master-import-file),
    template (optional download url), fileLabel (field label; not `label`, which pages use for a class string).
--}}
@php($fileId = $id ?? 'master-import-file')
<form action="{{ $action }}" method="POST" enctype="multipart/form-data" class="card flex flex-col gap-3 p-4 sm:flex-row sm:items-end"
    x-data="{ busy: false }" @submit="busy = true">
    @csrf
    <div class="grid min-w-0 flex-1 gap-1.5">
        <label for="{{ $fileId }}" class="text-xs font-medium text-text-muted">{{ $fileLabel ?? 'Import file' }} ({{ $accept ?? '.csv,.xlsx,.xls' }})</label>
        <input id="{{ $fileId }}" type="file" name="file" accept="{{ $accept ?? '.csv,.xlsx,.xls' }}" required @isset($hint) aria-describedby="{{ $fileId }}-hint" @endisset
            class="block min-h-11 w-full rounded-control border border-border-strong bg-surface text-sm text-text file:mr-3 file:min-h-11 file:border-0 file:bg-surface-muted file:px-3 file:text-sm file:font-semibold file:text-text lg:min-h-9 lg:file:min-h-9">
    </div>
    <x-ui.button type="submit" variant="outline" class="lg:min-h-9" x-bind:disabled="busy">Import</x-ui.button>
    @isset($template)
        <x-ui.button variant="ghost" href="{{ $template }}" class="lg:min-h-9">Template</x-ui.button>
    @endisset
    @isset($hint)
        <p id="{{ $fileId }}-hint" class="form-hint sm:hidden">{{ $hint }}</p>
    @endisset
</form>
