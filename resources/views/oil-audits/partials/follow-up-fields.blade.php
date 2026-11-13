{{--
    Nested Problem -> Finding fields for the Oil Audit follow-up form.
    Shared by the create form and the (initially hidden) edit form. The <template>s in
    follow-up-scripts.blade.php build the same rows for resources/js/oil-audits/follow-up.js;
    keep the two in step (js-* classes, the label <span>s the script renumbers).

    Expects:
      $audit          the OilAudit this follow-up belongs to
      $problemOptions  OilAudit::PROBLEM_OPTIONS (Problem dropdown)
      $findingOptions  OilAudit::FINDING_OPTIONS (Finding dropdown)
      $mode            'create' | 'edit'
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
    // PANTAU is monitoring, so its action is a choice: refill the oil and/or run a full check.
    $isPantau = $audit->condition === 'PANTAU';
    $fuRefill = $fuIsOld ? (bool) old('refill_oil') : (bool) ($fu?->refill_oil ?? false);
    $fuFullCheck = $fuIsOld ? (bool) old('full_check') : ($fu ? (bool) $fu->full_check : false);
    $fuActionTaken = $fuIsOld ? old('action_taken', '') : ($fu?->action_taken ?? '');
    $removeBtn = 'btn btn-ghost min-h-11 shrink-0 text-danger hover:bg-danger-light hover:text-danger-hover lg:min-h-9';
@endphp

<input type="hidden" name="_followup_audit" value="{{ $audit->id }}">

<div class="js-followup-banner alert alert-info mb-3 hidden" role="status"></div>

<div class="mb-4">
    <h3 class="font-semibold text-text">
        @if ($isPantau)
            {{ $mode === 'edit' ? 'Edit action' : 'Record action' }}
        @else
            {{ $mode === 'edit' ? 'Edit follow-up' : 'Record follow-up' }}
        @endif
    </h3>
    <p class="mt-0.5 text-sm text-text-muted">
        @if ($isPantau)
            PANTAU does not need an action. If one was taken, tick what was done.
        @else
            Each problem can be picked once, needs at least one finding, and no finding may repeat within the same problem.
        @endif
        @if ($mode === 'create')
            The PIC is recorded automatically as {{ auth()->user()->name }}.
        @endif
    </p>
</div>

{{-- Server-side validation errors, scoped to the form that was actually submitted --}}
@if ($fuIsOld && $errors->any())
    <div class="alert alert-danger mb-3" role="alert">
        <ul class="list-disc space-y-0.5 pl-4">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

@if ($isPantau)
    <div class="mb-4 grid gap-2 sm:grid-cols-2">
        <label class="flex min-h-11 cursor-pointer items-center gap-3 rounded-control border border-border bg-surface px-3 py-2">
            <input type="checkbox" name="refill_oil" value="1" class="checkbox h-5 w-5" @checked($fuRefill)>
            <span class="text-sm font-semibold text-text">Refill oil</span>
        </label>
        <label class="flex min-h-11 cursor-pointer items-center gap-3 rounded-control border border-border bg-surface px-3 py-2">
            <input type="checkbox" name="full_check" value="1" class="checkbox h-5 w-5" @checked($fuFullCheck)>
            <span class="text-sm font-semibold text-text">Full check</span>
        </label>
    </div>
@endif

<fieldset class="js-fullcheck-fields m-0 min-w-0 border-0 p-0" @if ($isPantau && ! $fuFullCheck) disabled hidden @endif>
<div class="js-problem-list space-y-3">
    @foreach ($fuProblems as $pi => $problem)
        @php($rowFindingOptions = \App\Models\OilAudit::findingOptionsFor($problem['problem'] ?? ''))
        <div class="js-problem-row rounded-control border border-border bg-surface p-3">
            <div class="flex items-end gap-2">
                <label class="grid min-w-0 flex-1 gap-1.5">
                    <span class="js-problem-label text-xs font-semibold text-text">Problem #{{ $pi + 1 }}</span>
                    <select name="problems[{{ $pi }}][problem]" required class="select">
                        <option value="">Select a problem</option>
                        @foreach ($problemOptions as $opt)
                            <option value="{{ $opt }}" @selected(($problem['problem'] ?? '') === $opt)>{{ $opt }}</option>
                        @endforeach
                    </select>
                </label>
                <button type="button" class="js-remove-problem {{ $removeBtn }}">Remove</button>
            </div>

            <div class="js-finding-list mt-3 space-y-2 border-l-2 border-border pl-3">
                @foreach (($problem['findings'] ?? [['finding' => '']]) as $fi => $finding)
                    <div class="js-finding-row flex items-end gap-2">
                        <label class="grid min-w-0 flex-1 gap-1">
                            <span class="js-finding-label text-xs font-medium text-text-muted">Finding #{{ $fi + 1 }}</span>
                            <select name="problems[{{ $pi }}][findings][{{ $fi }}][finding]" required class="select">
                                <option value="">Select a finding</option>
                                @foreach ($rowFindingOptions as $fopt)
                                    <option value="{{ $fopt }}" @selected(($finding['finding'] ?? '') === $fopt)>{{ $fopt }}</option>
                                @endforeach
                            </select>
                        </label>
                        <button type="button" class="js-remove-finding {{ $removeBtn }}">Remove</button>
                    </div>
                @endforeach
            </div>

            <button type="button" class="js-add-finding btn btn-ghost btn-sm mt-2 min-h-11 lg:min-h-9">Add finding</button>
        </div>
    @endforeach
</div>

{{-- Client-side duplicate-finding warning, shown inside this audit card only --}}
<div class="js-dup-warning alert alert-danger mt-3 font-semibold" role="alert" hidden></div>

<button type="button" class="js-add-problem btn btn-outline btn-sm mt-3 min-h-11 lg:min-h-9">Add problem</button>

<label class="mt-4 grid gap-1.5">
    <span class="text-sm font-semibold text-text">Action taken <span class="text-danger" aria-hidden="true">*</span></span>
    <textarea name="action_taken" required rows="3" maxlength="2000"
        placeholder="Example: replaced the seal and repaired the bearing." class="textarea">{{ $fuActionTaken }}</textarea>
</label>
</fieldset>

<div class="mt-4 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
    @if ($mode === 'edit')
        <button type="button" class="js-followup-edit-toggle btn btn-ghost min-h-11" data-target="{{ $audit->id }}">Cancel</button>
    @endif
    @if ($mode === 'create' && ($canDeleteFollowUp ?? false))
        {{-- Belongs to the standalone form #delete-audit-{id} (HTML forms cannot nest),
             rendered right after this follow-up form. --}}
        <button type="submit" form="delete-audit-{{ $audit->id }}"
            onclick="return confirm('Delete this oil audit result ({{ $audit->audited_at->format('d-m-Y H:i') }})? The audit cannot be restored.')"
            class="btn btn-ghost min-h-11 text-danger hover:bg-danger-light hover:text-danger-hover">
            Delete
        </button>
    @endif
    <button type="submit" class="js-followup-submit btn btn-primary min-h-11">
        {{ $mode === 'edit' ? 'Save changes' : ($isPantau ? 'Save action' : 'Save and mark done') }}
    </button>
</div>
