{{--
    Action / Closing form, shared by the list and the detail page. The page
    root must own `action` (null or {id, order, machine, url, next}).
    Same shape as the Oil Audit follow-up: one or more Problems, each with one
    or more Findings, plus optional Remarks. Problem and Finding are picked
    from the master (never typed): $actionOptions is the Problem > Finding tree
    for the WO's category and area, $actionContext names that category and area.
--}}
@php
    $actionOptions = $actionOptions ?? [];
    $actionContext = $actionContext ?? ['category' => '', 'area' => ''];
    $oldForm = old('_action_wo')
        ? [
            'wo' => (int) old('_action_wo'),
            'remarks' => old('remarks', ''),
            'problems' => collect((array) old('problems', []))->map(fn ($p) => [
                'problem_id' => (string) ($p['problem_id'] ?? ''),
                'findings' => collect((array) ($p['findings'] ?? []))->map(fn ($f) => (string) ($f['finding_id'] ?? ''))->values()->all(),
            ])->values()->all(),
        ]
        : null;
@endphp

<div x-show="action" x-cloak x-transition.opacity.duration.150ms
    @keydown.escape.window="action = null"
    class="fixed inset-0 z-50 flex items-end justify-center bg-neutral-900/40 p-0 sm:items-center sm:p-4"
    role="dialog" aria-modal="true" aria-labelledby="predictive-action-title">
    <div @click.outside="action = null" class="max-h-dvh w-full max-w-xl overflow-y-auto rounded-t-card border border-border bg-surface p-5 shadow-popover sm:rounded-card sm:p-6">
        <template x-if="action">
            <form method="POST" :action="action.url" x-data="actionForm(@js($oldForm), action.id, @js($actionOptions))" @submit="busy = true">
                @csrf
                <input type="hidden" name="_action_wo" :value="action.id">
                <h3 id="predictive-action-title" class="text-lg font-semibold text-text">
                    Action #<span x-text="action.next"></span> · <span x-text="action.order"></span>
                </h3>
                <p class="mt-1 text-sm text-text-muted">
                    Machine <span class="font-medium text-text" x-text="action.machine"></span>.
                    Once saved, the WO becomes CLOSED and your claim is released.
                </p>

                @if ($errors->any() && old('_action_wo'))
                    <div class="alert alert-danger mt-3" role="alert">
                        <ul class="list-disc space-y-0.5 pl-4">
                            @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                        </ul>
                    </div>
                @endif

                <template x-if="tree.length === 0">
                    <p class="alert alert-warning mt-4" role="alert">
                        The Problem master for {{ $actionContext['category'] }} in area {{ $actionContext['area'] }} is empty.
                        Ask an Admin or Koordinator to fill it in under Predictive Problem, then try again.
                    </p>
                </template>
                <p x-show="tree.length > 0" class="form-hint mt-4">
                    One Action can hold several Problems, and each Problem can hold several Findings.
                    No Problem may repeat, and no Finding may repeat within the same Problem.
                </p>

                <div class="mt-3 space-y-3">
                    <template x-for="(p, pi) in problems" :key="p.key">
                        <div class="rounded-control border border-border bg-surface-sunken p-3">
                            <div class="flex items-end gap-2">
                                <div class="min-w-0 flex-1">
                                    <label :for="'pa-problem-' + p.key" class="form-label">Problem #<span x-text="pi + 1"></span></label>
                                    <select :id="'pa-problem-' + p.key" :name="`problems[${pi}][problem_id]`" x-model="p.problemId" @change="problemChanged(p)" required
                                        class="select">
                                        <option value="">Select a problem</option>
                                        <template x-for="o in tree" :key="o.id">
                                            <option :value="String(o.id)" x-text="o.name" :disabled="taken(p, o.id)"></option>
                                        </template>
                                    </select>
                                </div>
                                <button type="button" x-show="problems.length > 1" @click="removeProblem(pi)" aria-label="Remove Problem"
                                    class="btn btn-ghost min-h-11 shrink-0 text-danger hover:bg-danger-light hover:text-danger-hover lg:min-h-9">Remove</button>
                            </div>

                            <div class="mt-3 space-y-2 border-l-2 border-border pl-3">
                                <template x-for="(f, fi) in p.findings" :key="f.key">
                                    <div class="flex items-end gap-2">
                                        <div class="min-w-0 flex-1">
                                            <label :for="'pa-finding-' + f.key" class="mb-1 block text-xs font-medium text-text-muted">Finding #<span x-text="fi + 1"></span></label>
                                            <select :id="'pa-finding-' + f.key" :name="`problems[${pi}][findings][${fi}][finding_id]`" x-model="f.value" required :disabled="!p.problemId"
                                                class="select">
                                                <option value="" x-text="p.problemId ? 'Select a finding' : 'Select a problem first'"></option>
                                                <template x-for="o in findingsOf(p)" :key="o.id">
                                                    <option :value="String(o.id)" x-text="o.name" :disabled="p.findings.some(x => x !== f && x.value === String(o.id))"></option>
                                                </template>
                                            </select>
                                        </div>
                                        <button type="button" x-show="p.findings.length > 1" @click="removeFinding(pi, fi)" aria-label="Remove Finding"
                                            class="btn btn-ghost min-h-11 shrink-0 text-danger hover:bg-danger-light hover:text-danger-hover lg:min-h-9">Remove</button>
                                    </div>
                                </template>
                                <button type="button" x-show="p.findings.length < 20" @click="addFinding(pi)"
                                    class="btn btn-ghost btn-sm min-h-11 lg:min-h-9">Add finding</button>
                            </div>
                        </div>
                    </template>
                    <button type="button" x-show="problems.length < 20" @click="addProblem()"
                        class="btn btn-outline btn-sm min-h-11 lg:min-h-9">Add problem</button>
                </div>

                <div class="mt-4">
                    <label for="pa-remarks" class="form-label">Remarks <span class="font-normal text-text-muted">(optional)</span></label>
                    <textarea id="pa-remarks" name="remarks" rows="3" maxlength="2000" x-model="remarks"
                        class="textarea resize-y"></textarea>
                </div>

                <div class="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <button type="button" @click="action = null" class="btn btn-ghost min-h-11">Cancel</button>
                    <button type="submit" class="btn btn-success min-h-11" x-bind:disabled="busy || tree.length === 0">Save and close WO</button>
                </div>
            </form>
        </template>
    </div>
</div>

<script>
    // Dependent Problem > Finding dropdowns. `tree` is [{id, name, findings:[{id, name}]}]
    // for the WO's category and area. At least one Problem and one Finding per
    // Problem always remain, and names are re-indexed by x-for on every change
    // so the server always receives a dense problems[i][findings][j] tree.
    window.actionForm = function (old, woId, tree) {
        let n = 0;
        const finding = (value = '') => ({ key: ++n, value });
        const problem = (problemId = '', findings = ['']) => ({ key: ++n, problemId, findings: findings.map(finding) });
        const restore = old && old.wo === woId && old.problems.length;
        return {
            tree,
            busy: false,
            remarks: restore ? old.remarks : '',
            problems: restore ? old.problems.map(p => problem(p.problem_id, p.findings.length ? p.findings : [''])) : [problem()],
            findingsOf(p) { const o = this.tree.find(x => String(x.id) === String(p.problemId)); return o ? o.findings : []; },
            taken(p, id) { return this.problems.some(x => x !== p && String(x.problemId) === String(id)); },
            problemChanged(p) { p.findings = [finding()]; },
            addProblem() { this.problems.push(problem()); },
            removeProblem(i) { if (this.problems.length > 1) this.problems.splice(i, 1); },
            addFinding(i) { this.problems[i].findings.push(finding()); },
            removeFinding(i, j) { if (this.problems[i].findings.length > 1) this.problems[i].findings.splice(j, 1); },
        };
    };
</script>
