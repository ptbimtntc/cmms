{{--
    Edit WO. The page root owns `edit` (null or the WO payload built in
    wo-buttons). Machine Number can only change while the WO is unclaimed and
    has no history; Area and Machine Type then follow the new machine.
--}}
<div x-show="edit" x-cloak x-transition.opacity.duration.150ms
    @keydown.escape.window="edit = null"
    class="fixed inset-0 z-50 flex items-end justify-center bg-neutral-900/40 p-0 sm:items-center sm:p-4"
    role="dialog" aria-modal="true" aria-labelledby="predictive-edit-title">
    <div @click.outside="edit = null" class="max-h-dvh w-full max-w-lg overflow-y-auto rounded-t-card border border-border bg-surface p-5 shadow-popover sm:rounded-card sm:p-6">
        <template x-if="edit">
            <form method="POST" :action="edit.url" x-data="machinePicker(edit.machine_number, edit.wo_date, edit.due_date)" @submit="submitted()">
                @csrf
                @method('PUT')
                <input type="hidden" name="_edit_wo" :value="edit.id">
                <h3 id="predictive-edit-title" class="text-lg font-semibold text-text">Edit WO <span x-text="edit.order"></span></h3>

                @if ($errors->any() && old('_edit_wo'))
                    <div class="alert alert-danger mt-3" role="alert">
                        <ul class="list-disc space-y-0.5 pl-4">
                            @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                        </ul>
                    </div>
                @endif

                <div class="mt-4 space-y-4">
                    <div>
                        <label for="pe-order" class="form-label">Order Number</label>
                        <input id="pe-order" name="order_number" type="text" required maxlength="100" :value="edit.order_input"
                            class="input">
                    </div>

                    <div class="relative">
                        <label for="pe-machine" class="form-label">Machine Number</label>
                        <template x-if="edit.locked">
                            <div>
                                <input id="pe-machine" type="text" :value="edit.machine_number" disabled class="input">
                                <input type="hidden" name="machine_number" :value="edit.machine_number">
                                <p class="form-hint mt-1">Terkunci karena WO sudah di-claim atau punya riwayat Action.</p>
                            </div>
                        </template>
                        <template x-if="!edit.locked">
                            <div>
                                <input id="pe-machine" name="machine_number" type="text" required maxlength="255" autocomplete="off"
                                    x-model="q" @input="schedule()" @keydown.escape="results = []"
                                    class="input">
                                <ul x-show="results.length" x-cloak class="absolute z-10 mt-1 max-h-48 w-full overflow-y-auto rounded-control border border-border bg-surface py-1 text-sm shadow-popover">
                                    <template x-for="m in results" :key="m.machine_number">
                                        <li>
                                            <button type="button" @click="pick(m)" class="flex min-h-11 w-full items-center justify-between gap-3 px-3 text-left hover:bg-primary-light focus-visible:bg-primary-light focus-visible:outline-none">
                                                <span class="font-medium text-text" x-text="m.machine_number"></span>
                                                <span class="truncate text-xs text-text-muted" x-text="(m.machine_type || '-') + ' · ' + m.area"></span>
                                            </button>
                                        </li>
                                    </template>
                                </ul>
                                <p x-show="loading" x-cloak class="form-hint mt-1">Mencari mesin…</p>
                                <p x-show="failed" x-cloak class="form-error mt-1">Pencarian mesin gagal. Coba lagi.</p>
                                <p class="form-hint mt-1">Area dan Machine Type mengikuti Machine Master.</p>
                            </div>
                        </template>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="pe-date" class="form-label">Date</label>
                            <input id="pe-date" name="wo_date" type="date" required x-model="woDate" @change="syncDue()"
                                class="input">
                        </div>
                        <div>
                            <label for="pe-due" class="form-label">Due Date</label>
                            <input id="pe-due" name="due_date" type="date" required x-model="due" @input="dueTouched = true"
                                class="input">
                        </div>
                    </div>

                    <div>
                        <label for="pe-problem" class="form-label">Problem Description</label>
                        <textarea id="pe-problem" name="problem_description" rows="3" required maxlength="2000" x-init="$el.value = edit.problem"
                            class="textarea resize-y"></textarea>
                    </div>
                </div>

                <div class="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <button type="button" @click="edit = null" class="btn btn-ghost min-h-11">Batal</button>
                    <button type="submit" x-bind:disabled="busy" class="btn btn-primary min-h-11">Simpan perubahan</button>
                </div>
            </form>
        </template>
    </div>
</div>
