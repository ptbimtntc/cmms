@props([
    'name',
    'label',
    'options',
    'selected' => [],
    'autoSubmit' => false,
])

@php
    // Computed into a plain variable (not inlined into @json() below) so
    // the expression passed to @json() has no comma of its own — Blade's
    // @json() directive naively splits its argument on every literal ','
    // with no regard for nesting, so array_map('strval', $selected) (which
    // has an internal comma) would get its json_encode() flags silently
    // swapped out from under it.
    $selectedAsStrings = array_values(array_map('strval', $selected));
@endphp

{{--
    Multi-select filter rendered as a button that opens a checkbox list.
    Submits as `{name}[]=value1&{name}[]=value2` via plain GET, same as any
    other filter in these forms — no JS is needed server-side to read it,
    `$request->filled('{name}')` + `(array) $request->{name}` just works.

    `autoSubmit` matches the per-field auto-submit convention some filter
    forms already use (onchange="this.form.submit()"): when true, checking
    or unchecking a box submits the form immediately instead of waiting for
    a separate Filter button.
--}}
<div
    {{-- x-data is single-quoted deliberately: json_encode()'s string
         delimiters are always literal double quotes (JSON_HEX_QUOT only
         escapes quote CHARACTERS INSIDE string content, not the delimiters
         themselves), so @json() output can never be embedded safely inside
         a double-quoted attribute — it would close the attribute early the
         moment the array has any string in it. Single-quoting the
         attribute avoids that; JSON_HEX_APOS (part of @json()'s default
         flags) then escapes any single quote inside the values instead. --}}
    x-data='{
        open: false,
        selected: @json($selectedAsStrings),
    }'
    @click.outside="open = false"
    @keydown.escape="open = false"
    class="relative"
>
    <button
        type="button"
        @click="open = !open"
        class="flex w-full items-center justify-between gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-left text-sm text-slate-700 sm:w-auto sm:min-w-40"
    >
        {{-- Text is also rendered server-side (not just via x-text) so the
             correct label is visible immediately even if Alpine hasn't
             finished initializing yet — x-text keeps it live afterwards. --}}
        <span x-text="selected.length ? '{{ $label }} (' + selected.length + ')' : 'All {{ $label }}'">{{ count($selected) ? $label.' ('.count($selected).')' : 'All '.$label }}</span>
        <svg class="h-4 w-4 shrink-0 text-slate-400" :class="open ? 'rotate-180' : ''" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.293l3.71-4.06a.75.75 0 111.08 1.04l-4.25 4.65a.75.75 0 01-1.08 0l-4.25-4.65a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
        </svg>
    </button>

    <div
        x-show="open"
        x-transition
        style="display: none;"
        class="absolute z-20 mt-1 max-h-64 w-56 overflow-y-auto rounded-lg border border-slate-200 bg-white p-2 shadow-lg"
    >
        @foreach ($options as $value => $optionLabel)
            <label class="flex cursor-pointer items-center gap-2 rounded px-2 py-1.5 text-sm text-slate-700 hover:bg-slate-50">
                <input
                    type="checkbox"
                    name="{{ $name }}[]"
                    value="{{ $value }}"
                    x-model="selected"
                    @if ($autoSubmit) @change="$nextTick(() => $root.closest('form').submit())" @endif
                    class="rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                >
                <span>{{ $optionLabel }}</span>
            </label>
        @endforeach

        @if (count($options) === 0)
            <p class="px-2 py-1.5 text-sm text-slate-400">No options</p>
        @endif
    </div>
</div>
