{{--
    Assign PIC dropdown (ADMIN / KOORDINATOR). Saved on change by partials/assign-pic-script;
    the status line under it reports whether the save went through.
    Keep `assign-pic` as the first class: tests look for class="assign-pic.
    Params: id (unique per rendering; table and phone card each render one), recordId,
    label (what is being assigned, for screen readers), pics (users), current (PIC name or null).
--}}
<div class="grid gap-1">
    <label for="{{ $id }}" class="sr-only">Assign PIC for {{ $label }}</label>
    <select id="{{ $id }}" class="assign-pic select w-full min-w-44 lg:min-h-9" data-id="{{ $recordId }}">
        <option value="">Assign PIC</option>
        @foreach ($pics as $pic)
            <option value="{{ $pic->name }}" {{ $current == $pic->name ? 'selected' : '' }}>
                {{ \Illuminate\Support\Str::title(strtolower($pic->name)) }}
            </option>
        @endforeach
    </select>
    <span class="assign-pic-status min-h-4 text-xs text-text-muted" data-for="{{ $recordId }}" aria-live="polite"></span>
</div>
