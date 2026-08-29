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

<script>
    (function () {
        const problemTpl = document.getElementById('tpl-followup-problem-row');
        const findingTpl = document.getElementById('tpl-followup-finding-row');

        const FINDING_OPTIONS = @json($findingOptions);
        const GENERIC_FINDING_PROBLEMS = @json(\App\Models\OilAudit::GENERIC_FINDING_PROBLEMS);
        const GENERIC_FINDING = @json(\App\Models\OilAudit::GENERIC_FINDING);

        function findingOptionsFor(problemValue) {
            return GENERIC_FINDING_PROBLEMS.includes(problemValue) ? [GENERIC_FINDING] : FINDING_OPTIONS;
        }

        // Rebuild every Finding <select> in a problem row so its options match
        // the currently selected Problem. Keeps the current value if it is
        // still valid, otherwise clears it.
        function syncFindingOptions(problemRow) {
            const problemValue = (problemRow.querySelector('select')?.value || '').trim();
            const options = findingOptionsFor(problemValue);

            problemRow.querySelectorAll('.js-finding-row select').forEach((select) => {
                const current = select.value;
                const keep = options.includes(current) ? current : '';

                select.innerHTML =
                    '<option value="">Pilih finding</option>' +
                    options.map((opt) => {
                        const escaped = opt.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;');
                        return '<option value="' + escaped + '"' + (opt === keep ? ' selected' : '') + '>' + escaped + '</option>';
                    }).join('');

                select.value = keep;
            });
        }

        function newFindingRow() {
            return findingTpl.content.firstElementChild.cloneNode(true);
        }

        function newProblemRow() {
            const row = problemTpl.content.firstElementChild.cloneNode(true);
            row.querySelector('.js-finding-list').appendChild(newFindingRow());
            return row;
        }

        function reindex(form) {
            const problemRows = form.querySelectorAll('.js-problem-row');

            problemRows.forEach((problemRow, pi) => {
                problemRow.querySelector('.js-problem-label').textContent = 'Problem #' + (pi + 1);

                const select = problemRow.querySelector('select');
                select.setAttribute('name', 'problems[' + pi + '][problem]');

                const findingRows = problemRow.querySelectorAll('.js-finding-row');
                findingRows.forEach((findingRow, fi) => {
                    findingRow.querySelector('.js-finding-label').textContent = 'Finding #' + (fi + 1);
                    findingRow.querySelector('select').setAttribute(
                        'name', 'problems[' + pi + '][findings][' + fi + '][finding]'
                    );
                    findingRow.querySelector('.js-remove-finding').hidden = findingRows.length <= 1;
                });

                syncFindingOptions(problemRow);
                problemRow.querySelector('.js-remove-problem').hidden = problemRows.length <= 1;
            });

            checkDuplicates(form);
        }

        // Two rules, both surfaced inside this audit card (never at the top
        // of the page) and both blocking submit while unresolved:
        //   - the same Problem must not be picked on two problem rows;
        //   - a Finding must not repeat inside one problem (it may still be
        //     used by a different problem).
        function checkDuplicates(form) {
            const messages = [];

            const problemSeen = new Set();
            const problemDupes = new Set();
            form.querySelectorAll('.js-problem-row').forEach((problemRow) => {
                // The problem <select> is the first select in the row;
                // finding selects live inside .js-finding-list after it.
                const value = (problemRow.querySelector('select')?.value || '').trim();
                if (!value) return;
                if (problemSeen.has(value)) problemDupes.add(value);
                problemSeen.add(value);
            });
            problemDupes.forEach((value) => {
                messages.push('Problem "' + value + '" dipilih lebih dari sekali.');
            });

            form.querySelectorAll('.js-problem-row').forEach((problemRow, pi) => {
                const seen = new Set();
                const dupes = new Set();

                problemRow.querySelectorAll('.js-finding-row select').forEach((select) => {
                    const value = select.value.trim();
                    if (!value) return;
                    if (seen.has(value)) {
                        dupes.add(value);
                    }
                    seen.add(value);
                });

                dupes.forEach((value) => {
                    messages.push('Problem #' + (pi + 1) + ': finding "' + value + '" terisi lebih dari sekali.');
                });
            });

            const warning = form.querySelector('.js-dup-warning');
            const submit = form.querySelector('.js-followup-submit');

            if (messages.length) {
                if (warning) {
                    warning.textContent = messages.join(' ');
                    warning.hidden = false;
                }
                if (submit) submit.disabled = true;
            } else {
                if (warning) {
                    warning.textContent = '';
                    warning.hidden = true;
                }
                if (submit) submit.disabled = false;
            }
        }

        document.querySelectorAll('.js-followup-form').forEach((form) => {
            reindex(form);

            form.addEventListener('click', (event) => {
                const target = event.target;

                if (target.closest('.js-add-problem')) {
                    form.querySelector('.js-problem-list').appendChild(newProblemRow());
                    reindex(form);
                } else if (target.closest('.js-add-finding')) {
                    const list = target.closest('.js-problem-row').querySelector('.js-finding-list');
                    list.appendChild(newFindingRow());
                    reindex(form);
                } else if (target.closest('.js-remove-problem')) {
                    if (form.querySelectorAll('.js-problem-row').length <= 1) return;
                    target.closest('.js-problem-row').remove();
                    reindex(form);
                } else if (target.closest('.js-remove-finding')) {
                    const problemRow = target.closest('.js-problem-row');
                    if (problemRow.querySelectorAll('.js-finding-row').length <= 1) return;
                    target.closest('.js-finding-row').remove();
                    reindex(form);
                }
            });

            form.addEventListener('change', (event) => {
                const problemRow = event.target.closest?.('.js-problem-row');
                // Problem <select> is the first select in the row.
                if (problemRow && event.target === problemRow.querySelector('select')) {
                    syncFindingOptions(problemRow);
                }
                checkDuplicates(form);
            });

            form.addEventListener('submit', (event) => {
                checkDuplicates(form);
                if (form.querySelector('.js-followup-submit')?.disabled) {
                    event.preventDefault();
                }
            });
        });

        // Toggle the read-only view <-> edit form for an existing follow-up.
        document.querySelectorAll('.js-followup-edit-toggle').forEach((button) => {
            button.addEventListener('click', () => {
                const id = button.dataset.target;
                const view = document.querySelector('[data-followup-view="' + id + '"]');
                const editForm = document.querySelector('[data-followup-form="' + id + '"]');
                if (view) view.hidden = !view.hidden;
                if (editForm) {
                    editForm.hidden = !editForm.hidden;
                    if (!editForm.hidden) reindex(editForm);
                }
            });
        });
    })();
</script>
