{{--
    Nested Problem -> Finding fields for the Oil Audit follow-up form.
    Shared by the create form and the (initially hidden) edit form.

    Expects:
      $audit          — the OilAudit this follow-up belongs to
      $problemOptions  — OilAudit::PROBLEM_OPTIONS (Problem dropdown)
      $findingOptions  — OilAudit::FINDING_OPTIONS (Finding dropdown)
      $mode            — 'create' | 'edit'
--}}
@php
    $fu = $audit->followUp;
    $fuIsOld = old('_followup_audit') !== null && (int) old('_followup_audit') === $audit->id;
    $fuProblems = $fuIsOld
        ? old('problems', [])
        : ($fu
            ? $fu->problems->map(fn ($p) => [
                'problem' => $p->problem,
                'findings' => $p->findings->isNotEmpty()
                    ? $p->findings->map(fn ($f) => ['finding' => $f->finding])->values()->all()
                    : [['finding' => '']],
            ])->values()->all()
            : []);
    if (empty($fuProblems)) {
        $fuProblems = [['problem' => '', 'findings' => [['finding' => '']]]];
    }
    $fuActionTaken = $fuIsOld ? old('action_taken', '') : ($fu?->action_taken ?? '');
@endphp

<input type="hidden" name="_followup_audit" value="{{ $audit->id }}">

<div class="mb-3 flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
    <div>
        <h3 class="font-bold text-slate-900">
            {{ $mode === 'edit' ? 'Ubah tindak lanjut' : 'Catat tindak lanjut' }}
        </h3>
        <p class="text-xs text-slate-600">
            Setiap problem hanya boleh dipilih sekali, punya minimal satu finding, dan finding dalam satu problem tidak boleh sama.
            @if ($mode === 'create')
                PIC akan tercatat otomatis sebagai {{ auth()->user()->name }}.
            @endif
        </p>
    </div>
</div>

{{-- Server-side validation errors, scoped to the form that was actually submitted --}}
@if ($fuIsOld && $errors->any())
    <div class="mb-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700">
        <ul class="list-disc space-y-0.5 pl-4">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="js-problem-list space-y-3">
    @foreach ($fuProblems as $pi => $problem)
        @php($rowFindingOptions = \App\Models\OilAudit::findingOptionsFor($problem['problem'] ?? ''))
        <div class="js-problem-row rounded-lg border border-slate-200 bg-white p-3">
            <div class="flex items-start gap-2">
                <div class="min-w-0 flex-1">
                    <label class="js-problem-label mb-1.5 block text-xs font-semibold text-slate-700">Problem #{{ $pi + 1 }}</label>
                    <select name="problems[{{ $pi }}][problem]" required
                        class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-700 outline-none focus:border-orange-500 focus:ring-2 focus:ring-orange-100">
                        <option value="">Pilih problem</option>
                        @foreach ($problemOptions as $opt)
                            <option value="{{ $opt }}" @selected(($problem['problem'] ?? '') === $opt)>{{ $opt }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="button" class="js-remove-problem mt-6 rounded-lg border border-red-200 px-3 py-2.5 text-xs font-semibold text-red-600 hover:bg-red-50">Hapus</button>
            </div>

            <div class="js-finding-list mt-2 space-y-2 border-l border-slate-200 pl-3">
                @foreach (($problem['findings'] ?? [['finding' => '']]) as $fi => $finding)
                    <div class="js-finding-row flex items-start gap-2">
                        <div class="min-w-0 flex-1">
                            <label class="js-finding-label mb-1 block text-[11px] font-semibold text-slate-500">Finding #{{ $fi + 1 }}</label>
                            <select name="problems[{{ $pi }}][findings][{{ $fi }}][finding]" required
                                class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 outline-none focus:border-orange-500 focus:ring-2 focus:ring-orange-100">
                                <option value="">Pilih finding</option>
                                @foreach ($rowFindingOptions as $fopt)
                                    <option value="{{ $fopt }}" @selected(($finding['finding'] ?? '') === $fopt)>{{ $fopt }}</option>
                                @endforeach
                            </select>
                        </div>
                        <button type="button" class="js-remove-finding mt-5 rounded-lg border border-red-200 px-2.5 py-2 text-[11px] font-semibold text-red-600 hover:bg-red-50">Hapus</button>
                    </div>
                @endforeach
            </div>

            <button type="button" class="js-add-finding mt-2 ml-3 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:border-slate-400 hover:bg-slate-50">+ Add Finding</button>
        </div>
    @endforeach
</div>

{{-- Client-side duplicate-finding warning, shown inside this audit card only --}}
<div class="js-dup-warning mt-3 rounded-lg border border-rose-300 bg-rose-100 px-3 py-2 text-xs font-semibold text-rose-800" hidden></div>

<button type="button" class="js-add-problem mt-3 rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 transition hover:border-slate-400 hover:bg-slate-50">+ Add Problem</button>

<div class="mt-3">
    <label class="mb-1.5 block text-xs font-semibold text-slate-700">Tindakan yang dilakukan</label>
    <textarea name="action_taken" required rows="3" maxlength="2000"
        placeholder="Contoh: Ganti seal dan perbaiki bearing."
        class="w-full resize-y rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-700 outline-none focus:border-orange-500 focus:ring-2 focus:ring-orange-100">{{ $fuActionTaken }}</textarea>
</div>

<div class="mt-3 flex flex-wrap justify-end gap-2">
    @if ($mode === 'edit')
        <button type="button" class="js-followup-edit-toggle rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50" data-target="{{ $audit->id }}">Batal</button>
    @endif
    <button type="submit" class="js-followup-submit w-full rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-700 disabled:cursor-not-allowed disabled:opacity-50 sm:w-auto">
        {{ $mode === 'edit' ? 'Simpan perubahan' : 'Simpan & tandai selesai' }}
    </button>
</div>
