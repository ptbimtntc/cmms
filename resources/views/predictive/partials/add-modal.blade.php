{{-- Add WO. Area and Machine Type are shown read-only; the server re-reads them from the Machine Master. --}}
<div x-show="addOpen" x-cloak x-transition.opacity.duration.150ms
    @keydown.escape.window="addOpen = false"
    class="fixed inset-0 z-50 flex items-end justify-center bg-neutral-900/40 p-0 sm:items-center sm:p-4"
    role="dialog" aria-modal="true" aria-labelledby="predictive-add-title">
    <div @click.outside="addOpen = false" class="max-h-dvh w-full max-w-lg overflow-y-auto rounded-t-card border border-border bg-surface p-5 shadow-popover sm:rounded-card sm:p-6">
        <form method="POST" action="{{ route('predictive.store') }}" x-data="machinePicker(@js(old('machine_number', '')), @js(old('wo_date', now()->format('Y-m-d'))), @js(old('due_date')))" @submit="submitted()">
            @csrf
            <input type="hidden" name="_form" value="add">
            <input type="hidden" name="category" value="{{ $category }}">
            <h3 id="predictive-add-title" class="text-lg font-semibold text-text">Add {{ $categoryLabel }} WO</h3>

            @if ($errors->any() && old('_form') === 'add')
                <div class="alert alert-danger mt-3" role="alert">
                    <ul class="list-disc space-y-0.5 pl-4">
                        @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                    </ul>
                </div>
            @endif

            <div class="mt-4 space-y-4">
                <div>
                    <label for="pw-order" class="form-label">Order Number</label>
                    <input id="pw-order" name="order_number" type="text" required maxlength="100" value="{{ old('order_number') }}"
                        class="input">
                </div>

                <div class="relative">
                    <label for="pw-machine" class="form-label">Machine Number</label>
                    <input id="pw-machine" name="machine_number" type="text" required maxlength="255" autocomplete="off"
                        x-model="q" @input="schedule()" @keydown.escape="results = []"
                        placeholder="Type to search machines"
                        class="input">
                    <ul x-show="results.length" x-cloak class="absolute z-10 mt-1 max-h-48 w-full overflow-y-auto rounded-control border border-border bg-surface py-1 text-sm shadow-popover">
                        <template x-for="m in results" :key="m.machine_number">
                            <li>
                                <button type="button" @click="pick(m)" class="flex min-h-11 w-full items-center justify-between gap-3 px-3 text-left hover:bg-primary-light focus-visible:bg-primary-light focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-primary">
                                    <span class="font-medium text-text" x-text="m.machine_number"></span>
                                    <span class="truncate text-xs text-text-muted" x-text="(m.machine_type || '-') + ' · ' + m.area"></span>
                                </button>
                            </li>
                        </template>
                    </ul>
                    <p x-show="loading" x-cloak class="form-hint mt-1">Searching machines…</p>
                    <p x-show="failed" x-cloak class="form-error mt-1">Machine search failed. Try again.</p>
                    <p x-show="searched && !loading && !failed && !results.length && !picked" x-cloak class="mt-1 text-xs text-warning-text">Machine not found in Machine Master.</p>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <span class="form-label">Area</span>
                        <div class="flex min-h-11 items-center rounded-control border border-border bg-surface-sunken px-3 text-sm text-text" x-text="picked ? picked.area : 'Otomatis'" :class="picked ? '' : 'text-text-muted'"></div>
                    </div>
                    <div>
                        <span class="form-label">Machine Type</span>
                        <div class="flex min-h-11 items-center rounded-control border border-border bg-surface-sunken px-3 text-sm text-text" x-text="picked ? (picked.machine_type || '-') : 'Otomatis'" :class="picked ? '' : 'text-text-muted'"></div>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label for="pw-date" class="form-label">Date</label>
                        <input id="pw-date" name="wo_date" type="date" required x-model="woDate" @change="syncDue()"
                            class="input">
                    </div>
                    <div>
                        <label for="pw-due" class="form-label">Due Date</label>
                        <input id="pw-due" name="due_date" type="date" required x-model="due" @input="dueTouched = true"
                            class="input">
                        <p class="form-hint mt-1">Defaults to the WO date plus 14 days, and can be changed.</p>
                    </div>
                </div>

                <div>
                    <label for="pw-problem" class="form-label">Problem Description</label>
                    <textarea id="pw-problem" name="problem_description" rows="3" required maxlength="2000"
                        class="textarea resize-y">{{ old('problem_description') }}</textarea>
                </div>
            </div>

            <div class="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <button type="button" @click="addOpen = false" class="btn btn-ghost min-h-11">Cancel</button>
                <button type="submit" x-bind:disabled="busy" class="btn btn-primary min-h-11">Save WO</button>
            </div>
        </form>
    </div>
</div>
