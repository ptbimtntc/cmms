{{--
    One delegated handler for every Oil Audit follow-up form on the page
    (create + edit). Enforces: >= 1 problem per form, >= 1 finding per
    problem, remove buttons hidden when at the minimum. Names are fully
    re-indexed after every add/remove so the server always receives a
    dense problems[i][findings][j][finding] tree.
--}}
{{-- Mirrors the rows in follow-up-fields.blade.php: the script renumbers the .js-*-label spans. --}}
<template id="tpl-followup-problem-row">
    <div class="js-problem-row rounded-control border border-border bg-surface p-3">
        <div class="flex items-end gap-2">
            <label class="grid min-w-0 flex-1 gap-1.5">
                <span class="js-problem-label text-xs font-semibold text-text">Problem</span>
                <select data-problem-select required class="select">
                    <option value="">Select a problem</option>
                    @foreach ($problemOptions as $opt)
                        <option value="{{ $opt }}">{{ $opt }}</option>
                    @endforeach
                </select>
            </label>
            <button type="button" class="js-remove-problem btn btn-ghost min-h-11 shrink-0 text-danger hover:bg-danger-light hover:text-danger-hover lg:min-h-9">Hapus</button>
        </div>
        <div class="js-finding-list mt-3 space-y-2 border-l-2 border-border pl-3"></div>
        <button type="button" class="js-add-finding btn btn-ghost btn-sm mt-2 min-h-11 lg:min-h-9">Add finding</button>
    </div>
</template>

<template id="tpl-followup-finding-row">
    <div class="js-finding-row flex items-end gap-2">
        <label class="grid min-w-0 flex-1 gap-1">
            <span class="js-finding-label text-xs font-medium text-text-muted">Finding</span>
            <select data-finding-select required class="select">
                <option value="">Select a finding</option>
                @foreach ($findingOptions as $fopt)
                    <option value="{{ $fopt }}">{{ $fopt }}</option>
                @endforeach
            </select>
        </label>
        <button type="button" class="js-remove-finding btn btn-ghost min-h-11 shrink-0 text-danger hover:bg-danger-light hover:text-danger-hover lg:min-h-9">Hapus</button>
    </div>
</template>

{{--
    All logic that used to live inline here (dynamic add/remove rows,
    duplicate-checking, finding-options syncing, edit/view toggle) now
    lives in resources/js/oil-audits/follow-up.js (Task 8), which ALSO adds
    offline draft autosave/restore and the online/offline submit decision
    on top of it, unchanged behaviour, same functions, just moved out of
    an inline <script> so the offline additions have somewhere to import
    from. This element is the only bridge: it exposes the option lists
    (which need PHP/Blade to render) to that external, Blade-free JS file
    as data attributes, since @json() cannot be used outside a .blade.php
    file.
--}}
<div id="followup-config"
    data-finding-options="{{ json_encode($findingOptions) }}"
    data-generic-finding-problems="{{ json_encode(\App\Models\OilAudit::GENERIC_FINDING_PROBLEMS) }}"
    data-generic-finding="{{ json_encode(\App\Models\OilAudit::GENERIC_FINDING) }}"
    hidden></div>
