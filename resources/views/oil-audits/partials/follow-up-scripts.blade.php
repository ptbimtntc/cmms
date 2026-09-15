{{--
    One delegated handler for every Oil Audit follow-up form on the page
    (create + edit). Enforces: >= 1 problem per form, >= 1 finding per
    problem, remove buttons hidden when at the minimum. Names are fully
    re-indexed after every add/remove so the server always receives a
    dense problems[i][findings][j][finding] tree.
--}}
<template id="tpl-followup-problem-row">
    <div class="js-problem-row rounded-lg border border-slate-200 bg-white p-3">
        <div class="flex items-start gap-2">
            <div class="min-w-0 flex-1">
                <label class="js-problem-label mb-1.5 block text-xs font-semibold text-slate-700">Problem</label>
                <select data-problem-select required
                    class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-700 outline-none focus:border-orange-500 focus:ring-2 focus:ring-orange-100">
                    <option value="">Pilih problem</option>
                    @foreach ($problemOptions as $opt)
                        <option value="{{ $opt }}">{{ $opt }}</option>
                    @endforeach
                </select>
            </div>
            <button type="button" class="js-remove-problem mt-6 rounded-lg border border-red-200 px-3 py-2.5 text-xs font-semibold text-red-600 hover:bg-red-50">Hapus</button>
        </div>
        <div class="js-finding-list mt-2 space-y-2 border-l border-slate-200 pl-3"></div>
        <button type="button" class="js-add-finding mt-2 ml-3 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:border-slate-400 hover:bg-slate-50">+ Add Finding</button>
    </div>
</template>

<template id="tpl-followup-finding-row">
    <div class="js-finding-row flex items-start gap-2">
        <div class="min-w-0 flex-1">
            <label class="js-finding-label mb-1 block text-[11px] font-semibold text-slate-500">Finding</label>
            <select data-finding-select required
                class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 outline-none focus:border-orange-500 focus:ring-2 focus:ring-orange-100">
                <option value="">Pilih finding</option>
                @foreach ($findingOptions as $fopt)
                    <option value="{{ $fopt }}">{{ $fopt }}</option>
                @endforeach
            </select>
        </div>
        <button type="button" class="js-remove-finding mt-5 rounded-lg border border-red-200 px-2.5 py-2 text-[11px] font-semibold text-red-600 hover:bg-red-50">Hapus</button>
    </div>
</template>

{{--
    All logic that used to live inline here (dynamic add/remove rows,
    duplicate-checking, finding-options syncing, edit/view toggle) now
    lives in resources/js/oil-audits/follow-up.js (Task 8), which ALSO adds
    offline draft autosave/restore and the online/offline submit decision
    on top of it — unchanged behaviour, same functions, just moved out of
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
