@extends('layouts.app')

@php
    $fullWidth = true;
    $canEditPic = auth()->user()->isAdmin() || auth()->user()->isKoordinator();
    $sessions = $pmSchedule->workSessions ?? collect();

    // Row and button classes shared with the rows resources/js/pm/edit.js adds at runtime;
    // keep the two in step so added rows look like the saved ones.
    $removeBtn = 'btn btn-ghost min-h-11 text-danger hover:bg-danger-light hover:text-danger-hover lg:min-h-9';
    $entryRow = 'grid gap-2 rounded-control border border-border bg-surface-sunken p-3 sm:items-center sm:border-0 sm:bg-transparent sm:p-0';

    // Problem rows: what the user just sent when validation bounced it back, otherwise what is saved.
    $problemRows = session()->hasOldInput('problems')
        ? collect(old('problems'))->map(fn ($row) => [
            'problem' => $row['problem'] ?? null,
            'finding' => $row['finding'] ?? null,
            'severity' => $row['severity'] ?? null,
        ])
        : collect($pmProblems)->map(fn ($p) => [
            'problem' => $p->machine_problem_id,
            'finding' => $p->machine_problem_finding_id,
            'severity' => $p->severity,
        ]);
    // Next index for rows added in the browser; must not reuse an index already on the page.
    $nextProblemIndex = $problemRows->isEmpty() ? 0 : max(array_map('intval', $problemRows->keys()->all())) + 1;
@endphp

@section('content')
    <div class="space-y-5">
        <x-ui.page-header title="Fill PM Record" :description="$pmSchedule->machine_number . ' · ' . $pmSchedule->machine_type">
            <x-slot:actions>
                <x-ui.button variant="outline" href="{{ route('pm-schedules.index', ['page' => request('page')]) }}">Back to PM Schedule</x-ui.button>
            </x-slot:actions>
        </x-ui.page-header>

        @if (session('warning'))
            <x-ui.alert tone="danger" title="Warning!">{{ session('warning') }}</x-ui.alert>
        @endif

        @if ($errors->any())
            <x-ui.alert tone="danger" title="Not saved yet">
                {{ $errors->count() === 1 ? 'One field needs attention' : $errors->count() . ' fields need attention' }}. They are marked in red below.
            </x-ui.alert>
        @endif

        {{-- Offline-first PM Save: hidden by default; resources/js/pm/save.js shows it only when a
             local draft or "saved offline" state exists for this PM. --}}
        <div id="pm-draft-banner" class="alert alert-info hidden" role="status"></div>

        <form id="pm-save-form" method="POST" action="{{ route('pm-schedules.update', $pmSchedule) }}" class="space-y-5">

            <div id="pm-data" data-findings='@json($problemFindings)' data-spareparts='@json($spareparts)'
                data-big-problems='@json($bigProblems)' data-problem-index='@json($nextProblemIndex)'
                data-sparepart-index='@json(isset($pmSpareparts) ? $pmSpareparts->count() : 0)'
                data-pm-schedule-id="{{ $pmSchedule->id }}" data-pm-status="{{ $pmSchedule->status }}"></div>

            @csrf
            @method('PUT')
            <input type="hidden" name="return_page" value="{{ request('page') }}">

            {{-- Machine: the context a technician checks first before touching anything. --}}
            <section class="card p-5" aria-labelledby="machine-heading">
                <h2 id="machine-heading" class="sr-only">Machine Information</h2>
                <dl class="grid grid-cols-2 gap-4 md:grid-cols-4">
                    <div>
                        <dt class="text-xs text-text-muted">Machine Number</dt>
                        <dd class="font-signage text-2xl font-semibold text-text">{{ $pmSchedule->machine_number }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-text-muted">Machine Type</dt>
                        <dd class="mt-1 font-medium text-text">{{ $pmSchedule->machine_type }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-text-muted">Last PM Date</dt>
                        <dd class="mt-1 font-medium text-text">{{ $lastPm ? $lastPm->format('d-m-Y') : '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-text-muted">Status</dt>
                        <dd class="mt-1">@include('pm-schedules.partials.status-badge', ['status' => $pmSchedule->status])</dd>
                    </div>
                </dl>
            </section>

            @include('pm-schedules._open-findings')

            {{-- 1. PM information --}}
            <x-ui.card title="PM Information">
                <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                    <x-ui.field label="Order Number" for="order_number" class="min-w-0">
                        <input id="order_number" value="{{ old('order_number', $pmSchedule->order_number) }}" readonly required
                            name="order_number" type="text" class="input">
                    </x-ui.field>

                    <x-ui.field label="Completion Date" for="completion_date_display" hint="Filled in automatically when the PM is completed." class="min-w-0">
                        <input id="completion_date_display" name="completion_date_display" type="date" readonly
                            value="{{ $pmSchedule->actual_date ? \Carbon\Carbon::parse($pmSchedule->actual_date)->format('Y-m-d') : '' }}"
                            class="input">
                    </x-ui.field>

                    <x-ui.field label="PIC PM" for="pm-pic" class="min-w-0">
                        <select id="pm-pic" name="pic" class="select" {{ ! $canEditPic ? 'disabled' : '' }}>
                            <option value="">Select PIC</option>
                            @foreach ($pics as $pic)
                                <option value="{{ $pic->name }}" {{ old('pic', $pmSchedule->pic) == $pic->name ? 'selected' : '' }}>{{ $pic->name }}</option>
                            @endforeach
                        </select>
                        @if (! $canEditPic)
                            <input type="hidden" name="pic" value="{{ $pmSchedule->pic }}">
                        @endif
                    </x-ui.field>
                </div>

                <div class="mt-6 border-t border-border pt-5">
                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                        <h3 class="text-sm font-semibold text-text">PM Work Sessions</h3>
                        <p class="text-sm text-text-muted">Total PM Duration:
                            <span id="total-duration" class="font-semibold text-text" data-initial="{{ $pmSchedule->duration_formatted }}">{{ $pmSchedule->duration_formatted }}</span>
                        </p>
                    </div>
                    <p class="mt-1 text-xs text-text-muted">One row per working day. Add a day when the PM runs past one shift.</p>

                    <div id="work-sessions" class="mt-3 space-y-3">
                        @if ($sessions->isNotEmpty())
                            @foreach ($sessions as $i => $s)
                                @include('pm-schedules.partials.session-row', [
                                    'index' => $i,
                                    'id' => $s->id,
                                    'date' => old("sessions.$i.actual_date", $s->actual_date),
                                    'start' => old("sessions.$i.start_time", $s->start_time ? \Carbon\Carbon::parse($s->start_time)->format('H:i') : ''),
                                    'end' => old("sessions.$i.end_time", $s->end_time ? \Carbon\Carbon::parse($s->end_time)->format('H:i') : ''),
                                    'duration' => $s->duration ? floor($s->duration / 60) . ' Hours ' . $s->duration % 60 . ' Minutes' : '-',
                                    'removable' => $i > 0,
                                ])
                            @endforeach
                        @else
                            {{-- Default Day 1 preset from legacy fields (no DB write) --}}
                            @include('pm-schedules.partials.session-row', [
                                'index' => 0,
                                'id' => '',
                                'date' => old('sessions.0.actual_date', $pmSchedule->actual_date ?? date('Y-m-d')),
                                'start' => old('sessions.0.start_time', $pmSchedule->start_time ? \Carbon\Carbon::parse($pmSchedule->start_time)->format('H:i') : ''),
                                'end' => old('sessions.0.end_time', $pmSchedule->end_time ? \Carbon\Carbon::parse($pmSchedule->end_time)->format('H:i') : ''),
                                'duration' => $pmSchedule->duration ? floor($pmSchedule->duration / 60) . ' Hours ' . $pmSchedule->duration % 60 . ' Minutes' : '-',
                                'removable' => false,
                            ])
                        @endif
                    </div>

                    <x-ui.button variant="outline" size="sm" id="add-session" class="mt-3">Add day</x-ui.button>
                </div>

                <div class="mt-6 grid grid-cols-1 gap-4 border-t border-border pt-5 sm:grid-cols-3">
                    @if ($pmSchedule->requiresOilChange())
                        <x-ui.field label="Oil Change" for="oil_change">
                            <select id="oil_change" name="oil_change" class="select">
                                <option value="">Select</option>
                                <option value="YES" @if ($pmSchedule->oil_change == 'YES') selected @endif>YES</option>
                                <option value="NO" @if ($pmSchedule->oil_change == 'NO') selected @endif>NO</option>
                            </select>
                        </x-ui.field>
                    @endif

                    <x-ui.field label="Greasing" for="greasing">
                        <select id="greasing" name="greasing" class="select">
                            <option value="">Select</option>
                            <option value="YES" @if ($pmSchedule->greasing == 'YES') selected @endif>YES</option>
                            <option value="NO" @if ($pmSchedule->greasing == 'NO') selected @endif>NO</option>
                        </select>
                    </x-ui.field>

                    <x-ui.field label="WO ZSBP" for="wo_zsbp">
                        <select id="wo_zsbp" name="wo_zsbp" class="select">
                            <option value="">Select</option>
                            <option value="YES" @if ($pmSchedule->wo_zsbp == 'YES') selected @endif>YES</option>
                            <option value="NO" @if ($pmSchedule->wo_zsbp == 'NO') selected @endif>NO</option>
                        </select>
                    </x-ui.field>
                </div>

                <x-ui.field label="Remarks" for="remarks" class="mt-5">
                    <textarea id="remarks" name="remarks" rows="3" class="textarea"
                        placeholder="Example: Kapstan 1 and 2 loose, kapstan 3 leaking, replaced rings on kapstan 1 and 4, replaced nylon, spindle, and countersheave, gant belt 1200, 1800, 1500">{{ old('remarks', $pmSchedule->remarks) }}</textarea>
                </x-ui.field>
            </x-ui.card>

            {{-- 2. PM result --}}
            <x-ui.card title="PM Result" description="Record every problem found, or tick the box when there was none.">
                <label class="flex min-h-11 items-center gap-3 text-sm font-medium text-text">
                    <input type="checkbox" name="no_problem" value="1" class="checkbox h-5 w-5"
                        data-none-toggle="problem-entry" @checked(session()->hasOldInput() ? old('no_problem') : $pmSchedule->no_problem)>
                    No problem found during this PM
                </label>

                @error('no_problem')
                    <p class="form-error mt-1">{{ $message }}</p>
                @enderror

                <div id="problem-entry" class="mt-3">
                    <div class="mb-1.5 hidden gap-2 px-0.5 text-xs font-medium text-text-muted sm:grid sm:grid-cols-[minmax(0,2fr)_minmax(0,2fr)_9rem_6rem]" aria-hidden="true">
                        <span>Problem</span><span>Finding</span><span>Severity</span><span></span>
                    </div>
                    <div id="problem-wrapper" class="space-y-2">
                        @foreach ($problemRows as $i => $row)
                            @php
                                $findingError = $errors->first("problems.$i.finding");
                                $severityError = $errors->first("problems.$i.severity");
                            @endphp
                            {{-- finding/severity become required once a problem is picked (resources/js/pm/edit.js); the server checks the same rule. --}}
                            <div class="problem-row {{ $entryRow }} sm:grid-cols-[minmax(0,2fr)_minmax(0,2fr)_9rem_6rem]">
                                <select name="problems[{{ $i }}][problem]" class="problem-select select" aria-label="Problem">
                                    <option value="">-- Select Problem --</option>
                                    @foreach ($bigProblems as $problem)
                                        <option value="{{ $problem->id }}"
                                            data-category="{{ strtolower(trim($problem->category)) }}"
                                            {{ $row['problem'] == $problem->id ? 'selected' : '' }}>
                                            {{ $problem->problem }}
                                        </option>
                                    @endforeach
                                </select>

                                <select name="problems[{{ $i }}][finding]" aria-label="Finding" data-old-finding="{{ $row['finding'] }}"
                                    @class(['finding-select select', 'is-invalid' => $findingError])
                                    @if ($findingError) aria-invalid="true" aria-describedby="problem-{{ $i }}-error" @endif
                                    @if (filled($row['problem'])) required @endif>
                                    <option value="">-- Finding --</option>
                                </select>

                                <select name="problems[{{ $i }}][severity]" aria-label="Severity"
                                    @class(['severity-select select', 'is-invalid' => $severityError])
                                    @if ($severityError) aria-invalid="true" aria-describedby="problem-{{ $i }}-error" @endif
                                    @if (filled($row['problem'])) required @endif>
                                    <option value="">-- Severity --</option>
                                    <option value="Low" {{ $row['severity'] == 'Low' ? 'selected' : '' }}>Low</option>
                                    <option value="Medium" {{ $row['severity'] == 'Medium' ? 'selected' : '' }}>Medium</option>
                                    <option value="High" {{ $row['severity'] == 'High' ? 'selected' : '' }}>High</option>
                                </select>

                                <button type="button" onclick="removeProblem(this)" class="{{ $removeBtn }}" aria-label="Remove this problem">Remove</button>

                                @if ($findingError || $severityError)
                                    <p id="problem-{{ $i }}-error" class="form-error sm:col-span-4">
                                        {{ collect([$findingError, $severityError])->filter()->implode(' ') }}
                                    </p>
                                @endif
                            </div>
                        @endforeach
                    </div>

                    <x-ui.button variant="outline" size="sm" onclick="addProblem()" class="mt-3">Add problem</x-ui.button>
                </div>
            </x-ui.card>

            {{-- 3. Measurement --}}
            <x-ui.card title="Measurement" description="Enter the actual reading next to each standard." :padded="false">
                @if ($measurements->isEmpty())
                    <div class="p-4 sm:p-5">
                        <x-ui.empty-state title="No Measurement Found"
                            :description="'No measurement items are set for ' . $pmSchedule->machine_type . '. They are managed in Master Data, Measurement.'" />
                    </div>
                @else
                    {{-- Desktop: table --}}
                    <div class="relative hidden overflow-x-auto md:block">
                        <table class="data-table min-w-full">
                            <thead>
                                <tr>
                                    <th scope="col">Measurement Item</th>
                                    <th scope="col" class="text-center">Standard</th>
                                    <th scope="col" class="w-56 text-center">Actual</th>
                                    <th scope="col" class="text-center">Unit</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($measurements as $i => $item)
                                    @php($oldMeasurement = $pmMeasurements->where('machine_measurement_id', $item->id)->first())
                                    <tr>
                                        <td class="font-medium">
                                            {{ $item->measurement_item }}
                                            <input type="hidden" name="measurements[{{ $i }}][machine_measurement_id]" value="{{ $item->id }}">
                                            <input type="hidden" name="measurements[{{ $i }}][measurement_item]" value="{{ $item->measurement_item }}">
                                        </td>
                                        <td class="text-center text-text-muted">
                                            {{ $item->standard ?? '-' }}
                                            <input type="hidden" name="measurements[{{ $i }}][standard]" value="{{ $item->standard }}">
                                        </td>
                                        <td>
                                            <input type="text" name="measurements[{{ $i }}][measurement_value]"
                                                value="{{ $oldMeasurement->measurement_value ?? '' }}"
                                                aria-label="Actual {{ $item->measurement_item }}"
                                                class="input text-center lg:min-h-9">
                                        </td>
                                        <td class="text-center text-text-muted">
                                            {{ $item->unit }}
                                            <input type="hidden" name="measurements[{{ $i }}][unit]" value="{{ $item->unit }}">
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    {{-- Phone: one card per item. The value input only gets its name below 768px (script below). --}}
                    <div class="space-y-3 p-4 md:hidden">
                        @foreach ($measurements as $i => $item)
                            @php($oldMeasurement = $pmMeasurements->where('machine_measurement_id', $item->id)->first())
                            <div class="rounded-control border border-border bg-surface-sunken p-3">
                                <div class="mb-2 flex items-start justify-between gap-3">
                                    <label for="measure-m-{{ $i }}" class="text-sm font-medium text-text">{{ $item->measurement_item }}</label>
                                    <span class="shrink-0 text-xs text-text-muted">Std: {{ $item->standard ?? '-' }} {{ $item->unit }}</span>
                                </div>

                                <input type="hidden" name="measurements[{{ $i }}][machine_measurement_id]" value="{{ $item->id }}">
                                <input type="hidden" name="measurements[{{ $i }}][measurement_item]" value="{{ $item->measurement_item }}">
                                <input type="hidden" name="measurements[{{ $i }}][standard]" value="{{ $item->standard }}">
                                <input type="hidden" name="measurements[{{ $i }}][unit]" value="{{ $item->unit }}">

                                <div class="flex items-center gap-2">
                                    <input id="measure-m-{{ $i }}" type="text" data-name="measurements[{{ $i }}][measurement_value]"
                                        value="{{ $oldMeasurement->measurement_value ?? '' }}"
                                        placeholder="Masukkan nilai actual" class="input text-center">
                                    <span class="shrink-0 text-sm text-text-muted">{{ $item->unit }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-ui.card>

            {{-- 4. Sparepart usage --}}
            <x-ui.card title="Sparepart Usage" description="Every part used on this machine during the PM.">
                {{-- Warehouse-injected usage is real usage, so "none used" is not offered then. --}}
                @unless ($pmSpareparts->contains(fn ($spare) => $spare->isFromWarehouse()))
                    <label class="flex min-h-11 items-center gap-3 text-sm font-medium text-text">
                        <input type="checkbox" name="no_sparepart" value="1" class="checkbox h-5 w-5"
                            data-none-toggle="sparepart-entry" @checked(session()->hasOldInput() ? old('no_sparepart') : $pmSchedule->no_sparepart)>
                        No sparepart used during this PM
                    </label>
                @endunless

                @error('no_sparepart')
                    <p class="form-error mt-1">{{ $message }}</p>
                @enderror

                <div id="sparepart-entry" class="mt-3">
                    @if (! empty($mandatoryPrefilled))
                        <x-ui.alert tone="info" class="mb-3">
                            Mandatory spareparts for {{ $pmSchedule->machine_type }} are pre-filled below. They are only
                            recorded as usage when you save this PM. Adjust the quantity or remove a row if it was not used.
                        </x-ui.alert>
                    @endif

                    <div class="mb-1.5 hidden gap-2 px-0.5 text-xs font-medium text-text-muted sm:grid sm:grid-cols-[minmax(0,1fr)_7rem_6rem]" aria-hidden="true">
                        <span>Sparepart</span><span>Qty</span><span></span>
                    </div>
                    <div id="sparepart-wrapper" class="space-y-2">
                        @foreach ($pmSpareparts as $i => $oldSpare)
                            <div class="sparepart-row {{ $entryRow }} sm:grid-cols-[minmax(0,1fr)_7rem_6rem]">
                                <select name="spareparts[{{ $i }}][sparepart_id]"
                                    class="sparepart-select select" aria-label="Sparepart"
                                    placeholder="Select Sparepart" data-selected="{{ $oldSpare->sparepart_id }}"
                                    @disabled($oldSpare->isFromWarehouse())>
                                    <option value="{{ $oldSpare->sparepart_id }}">
                                        {{ $oldSpare->sparepart->material_number }}
                                        -
                                        {{ $oldSpare->sparepart->description }}
                                    </option>
                                </select>

                                @if ($oldSpare->isFromWarehouse())
                                    {{-- Injected from a warehouse issue: shown for reference, locked server-side. --}}
                                    <input type="number" name="spareparts[{{ $i }}][qty]" value="{{ $oldSpare->qty }}" readonly
                                        class="input" aria-label="Qty">

                                    <span class="inline-flex min-h-11 items-center justify-center rounded-control bg-success-light px-3 text-xs font-semibold text-success-hover lg:min-h-9"
                                        title="Recorded from warehouse issue; cannot be edited or removed here">
                                        Warehouse
                                    </span>
                                @else
                                    <input type="number" name="spareparts[{{ $i }}][qty]" value="{{ $oldSpare->qty }}"
                                        class="input" aria-label="Qty">

                                    <button type="button" onclick="removeSparepart(this)" class="{{ $removeBtn }}" aria-label="Remove this sparepart">Remove</button>
                                @endif
                            </div>
                        @endforeach
                    </div>

                    <x-ui.button variant="outline" size="sm" onclick="addSparepart()" class="mt-3">Add sparepart</x-ui.button>
                </div>
            </x-ui.card>

            {{-- Sticky on every screen so the next step is always one tap away. --}}
            <div class="sticky bottom-0 z-10 -mx-4 border-t border-border bg-surface/95 px-4 py-3 sm:-mx-6 sm:px-6 lg:-mx-8 lg:px-8">
                <div class="flex flex-col-reverse gap-2 sm:flex-row sm:items-center sm:justify-between">
                    {{-- Hidden on phones to keep the sticky bar short. --}}
                    <p class="hidden text-xs text-text-muted sm:block">Saving takes you to the checklist for this PM.</p>
                    <x-ui.button type="submit" size="lg" class="w-full sm:w-auto">Open checklist</x-ui.button>
                </div>
            </div>
        </form>
    </div>

    <script>
        function syncMeasurementInput() {
            document.querySelectorAll('[data-name]').forEach(input => {
                if (window.innerWidth < 768) {
                    input.name = input.dataset.name;
                } else {
                    input.removeAttribute('name');
                }
            });
        }

        syncMeasurementInput();
        window.addEventListener('resize', syncMeasurementInput);
    </script>
@endsection
