@extends('layouts.app')

@section('content')
    <h1 class="text-2xl font-bold mb-6">
        Fill PM Record
    </h1>

    @if (session('warning'))
        <div class="mb-4 rounded-xl bg-red-100 border border-red-300 text-red-700 px-4 py-3">
            <strong>Warning!</strong><br>
            {{ session('warning') }}
        </div>
    @endif

    {{--
        FreeDOMS offline-first PM Save (Task 5). Hidden by default; shown by
        resources/js/pm/save.js only when a local draft or "saved offline"
        state actually exists for this PM. No layout/behavior change for
        anyone who never goes offline.
    --}}
    <div id="pm-draft-banner" class="mb-4 hidden rounded-xl border border-blue-300 bg-blue-50 px-4 py-3 text-sm text-blue-800"></div>

    <form id="pm-save-form" method="POST" action="{{ route('pm-schedules.update', $pmSchedule) }}"
        class="bg-white p-6 rounded shadow max-sm:p-4">

        <div id="pm-data" data-findings='@json($problemFindings)' data-spareparts='@json($spareparts)'
            data-big-problems='@json($bigProblems)' data-problem-index='@json(isset($pmProblems) ? $pmProblems->count() : 0)'
            data-sparepart-index='@json(isset($pmSpareparts) ? $pmSpareparts->count() : 0)'
            data-pm-schedule-id="{{ $pmSchedule->id }}" data-pm-status="{{ $pmSchedule->status }}"></div>

        @csrf
        @method('PUT')

        <div class="bg-linear-to-r from-blue-600 to-blue-700 text-white rounded-xl p-6 shadow mb-6 max-sm:p-4">

            <h2 class="text-xl font-bold mb-4">
                Machine Information
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">

                <div>
                    <p class="text-sm opacity-80">Machine Number</p>
                    <p class="font-semibold text-lg">
                        {{ $pmSchedule->machine_number }}
                    </p>
                </div>

                <div>
                    <p class="text-sm opacity-80">Machine Type</p>
                    <p class="font-semibold text-lg">
                        {{ $pmSchedule->machine_type }}
                    </p>
                </div>

                <div>
                    <p class="text-sm opacity-80">Last PM Date</p>
                    <p class="font-semibold text-lg">
                        {{ $lastPm ? $lastPm->format('d-m-Y') : '-' }}
                    </p>
                </div>

            </div>

        </div>

        <div class="bg-white rounded-xl shadow p-6 mb-6 max-sm:p-4">

            <h3 class="text-lg font-semibold mb-6">
                PM Information
            </h3>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                <div>
                    <label class="block mb-2 text-sm font-medium">
                        Order Number
                    </label>

                    <input value="{{ old('order_number', $pmSchedule->order_number) }}" readonly required
                        name="order_number" type="text" class="w-full bg-gray-100 border rounded-2xl p-3">
                </div>

                <div>
                    <label class="block mb-2 text-sm font-medium">
                        Completion Date
                    </label>

                    <input name="completion_date_display" type="date" readonly
                        value="{{ $pmSchedule->actual_date ? \Carbon\Carbon::parse($pmSchedule->actual_date)->format('Y-m-d') : '' }}"
                        class="w-full border rounded-2xl p-3 bg-gray-100">
                    <p class="text-xs text-slate-500 mt-1">Displayed only when PM is completed</p>
                </div>

                <div>
                    <label class="block mb-2 text-sm font-medium">
                        PIC PM
                    </label>

                    @php
                        $canEditPic = in_array(auth()->user()->role, ['ADMIN', 'KOORDINATOR WWD', 'KOORDINATOR BUL']);
                    @endphp

                    <select name="pic"
                        class="w-full rounded-2xl border border-gray-300 px-3 py-3
    {{ !$canEditPic ? 'bg-gray-100 cursor-not-allowed' : '' }}"
                        {{ !$canEditPic ? 'disabled' : '' }}>

                        <option value="">-- Select PIC --</option>

                        @foreach ($pics as $pic)
                            <option value="{{ $pic->name }}"
                                {{ old('pic', $pmSchedule->pic) == $pic->name ? 'selected' : '' }}>
                                {{ $pic->name }}
                            </option>
                        @endforeach

                    </select>

                    @if (!$canEditPic)
                        <input type="hidden" name="pic" value="{{ $pmSchedule->pic }}">
                    @endif
                </div>



                <div class="col-span-2">
                    <h4 class="mb-2 text-sm font-medium">PM Work Sessions</h4>

                    <div id="work-sessions" class="space-y-3">
                        @php
                            $sessions = $pmSchedule->workSessions ?? collect();
                        @endphp

                        @if ($sessions->isNotEmpty())
                            @foreach ($sessions as $i => $s)
                                <div class="session-row rounded-lg border p-3" data-index="{{ $i }}">
                                    <input type="hidden" name="sessions[{{ $i }}][id]"
                                        value="{{ $s->id }}">

                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                                        <div>
                                            <label class="text-xs">Date</label>
                                            <input type="date" name="sessions[{{ $i }}][actual_date]"
                                                value="{{ $s->actual_date }}" class="w-full border rounded-2xl p-2">
                                        </div>

                                        <div>
                                            <label class="text-xs">Start</label>
                                            <input type="time" name="sessions[{{ $i }}][start_time]"
                                                value="{{ $s->start_time ? \Carbon\Carbon::parse($s->start_time)->format('H:i') : '' }}"
                                                class="w-full border rounded-2xl p-2 session-start">
                                        </div>

                                        <div>
                                            <label class="text-xs">End</label>
                                            <input type="time" name="sessions[{{ $i }}][end_time]"
                                                value="{{ $s->end_time ? \Carbon\Carbon::parse($s->end_time)->format('H:i') : '' }}"
                                                class="w-full border rounded-2xl p-2 session-end">
                                        </div>
                                    </div>

                                    <div class="mt-2 flex items-center justify-between">
                                        <div class="text-sm text-slate-600">Duration: <span
                                                class="session-duration">{{ $s->duration ? floor($s->duration / 60) . ' Hours ' . $s->duration % 60 . ' Minutes' : '-' }}</span>
                                        </div>
                                        <div>
                                            @if ($i > 0)
                                                <button type="button"
                                                    class="remove-session inline-flex items-center rounded-lg bg-red-500 px-3 py-1 text-white">Remove
                                                    Day</button>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        @else
                            {{-- Default Day 1 preset from legacy fields (no DB write) --}}
                            <div class="session-row rounded-lg border p-3" data-index="0">
                                <input type="hidden" name="sessions[0][id]" value="">

                                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                                    <div>
                                        <label class="text-xs">Date</label>
                                        <input type="date" name="sessions[0][actual_date]"
                                            value="{{ old('sessions.0.actual_date', $pmSchedule->actual_date ?? date('Y-m-d')) }}"
                                            class="w-full border rounded-2xl p-2">
                                    </div>

                                    <div>
                                        <label class="text-xs">Start</label>
                                        <input type="time" name="sessions[0][start_time]"
                                            value="{{ old('sessions.0.start_time', $pmSchedule->start_time ? \Carbon\Carbon::parse($pmSchedule->start_time)->format('H:i') : '') }}"
                                            class="w-full border rounded-2xl p-2 session-start">
                                    </div>

                                    <div>
                                        <label class="text-xs">End</label>
                                        <input type="time" name="sessions[0][end_time]"
                                            value="{{ old('sessions.0.end_time', $pmSchedule->end_time ? \Carbon\Carbon::parse($pmSchedule->end_time)->format('H:i') : '') }}"
                                            class="w-full border rounded-2xl p-2 session-end">
                                    </div>
                                </div>

                                <div class="mt-2 flex items-center justify-between">
                                    <div class="text-sm text-slate-600">Duration: <span
                                            class="session-duration">{{ $pmSchedule->duration ? floor($pmSchedule->duration / 60) . ' Hours ' . $pmSchedule->duration % 60 . ' Minutes' : '-' }}</span>
                                    </div>
                                    <div></div>
                                </div>
                            </div>
                        @endif

                    </div>

                    <div class="mt-3 flex items-center gap-2">
                        <button type="button" id="add-session" class="bg-green-600 text-white px-3 py-1 rounded-xl">+ Add
                            Day</button>
                        <div class="text-sm text-slate-600">Total PM Duration: <span id="total-duration"
                                data-initial="{{ $pmSchedule->duration_formatted }}">{{ $pmSchedule->duration_formatted }}</span>
                        </div>
                    </div>
                </div>

            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-6">

                @if ($pmSchedule->requiresOilChange())
                    <div>
                        <label class="block mb-2 text-sm font-medium">
                            Oil Change
                        </label>

                        <select name="oil_change" class="w-full border rounded-2xl p-3">
                            <option value="">-- Select --</option>
                            <option value="YES" @if ($pmSchedule->oil_change == 'YES') selected @endif>YES</option>
                            <option value="NO" @if ($pmSchedule->oil_change == 'NO') selected @endif>NO</option>
                        </select>
                    </div>
                @endif

                <div>
                    <label class="block mb-2 text-sm font-medium">
                        Greasing
                    </label>

                    <select name="greasing" class="w-full border rounded-2xl p-3">
                        <option value="">-- Select --</option>
                        <option value="YES" @if ($pmSchedule->greasing == 'YES') selected @endif>YES</option>
                        <option value="NO" @if ($pmSchedule->greasing == 'NO') selected @endif>NO</option>
                    </select>
                </div>

                <div>
                    <label class="block mb-2 text-sm font-medium">
                        WO ZSBP
                    </label>

                    <select name="wo_zsbp" class="w-full border rounded-2xl p-3">
                        <option value="">-- Select --</option>
                        <option value="YES" @if ($pmSchedule->wo_zsbp == 'YES') selected @endif>YES</option>
                        <option value="NO" @if ($pmSchedule->wo_zsbp == 'NO') selected @endif>NO</option>
                    </select>
                </div>

            </div>
            <div class="gap-3 mt-5">
                <label class="block mb-2 text-sm font-medium">
                    Remarks
                </label>

                <textarea name="remarks" rows="3" class="w-full border rounded-2xl p-3"
                    placeholder="Contoh: Kapstan 1,2 oblak, kapstan 3 bocor, ganti ring kapstan 1 dan 4, ganti nylon, ganti spindel, ganti countersheave, gant belt 1200, 1800, 1500">{{ old('remarks', $pmSchedule->remarks) }}</textarea>
            </div>

        </div>

        <div class="bg-white rounded-xl shadow p-6 mb-6 max-sm:p-4">

            <h3 class="text-lg font-semibold mb-6">
                PM Result
            </h3>
            <div id="problem-wrapper">

                @foreach ($pmProblems as $i => $oldProblem)
                    <div
                        class="problem-row flex gap-2 mb-2 max-sm:flex-col max-sm:bg-gray-50 max-sm:border max-sm:border-gray-200 max-sm:rounded-lg max-sm:p-3 max-sm:mb-3">

                        <select name="problems[{{ $i }}][problem]"
                            class="problem-select border p-2 w-1/2 rounded-xl max-sm:w-full">

                            <option value="">-- Select Problem --</option>

                            @foreach ($bigProblems as $problem)
                                <option value="{{ $problem->id }}"
                                    data-category="{{ strtolower(trim($problem->category)) }}"
                                    {{ $oldProblem->machine_problem_id == $problem->id ? 'selected' : '' }}>
                                    {{ $problem->problem }}
                                </option>
                            @endforeach

                        </select>

                        <select name="problems[{{ $i }}][finding]"
                            class="finding-select border p-2 flex-1 rounded-xl max-sm:w-full"
                            data-old-finding="{{ $oldProblem->machine_problem_finding_id }}">

                            <option value="">-- Finding --</option>

                        </select>

                        <select name="problems[{{ $i }}][severity]"
                            class="border p-2 rounded-xl w-1/5 max-sm:w-full">

                            <option value="">-- Severity --</option>
                            <option value="Low" {{ $oldProblem->severity == 'Low' ? 'selected' : '' }}>Low</option>
                            <option value="Medium" {{ $oldProblem->severity == 'Medium' ? 'selected' : '' }}>Medium
                            </option>
                            <option value="High" {{ $oldProblem->severity == 'High' ? 'selected' : '' }}>High</option>

                        </select>

                        <button type="button" onclick="removeProblem(this)"
                            class="bg-red-500 text-white px-3 rounded-xl max-sm:w-full max-sm:py-2">
                            X
                        </button>

                    </div>
                @endforeach

            </div>

            <button type="button" onclick="addProblem()"
                class="mt-2 bg-green-600 text-white px-3 py-1 rounded-xl max-sm:w-full max-sm:py-2">
                + Add Problem
            </button>

        </div>


        {{-- PM Measurement --}}

        <div class="bg-white rounded-lg shadow p-6 mt-6 max-sm:p-4">

            <h2 class="text-lg font-semibold mb-4">
                Measurement
            </h2>

            {{-- Desktop: tabel --}}
            <div class="hidden md:block overflow-x-auto">

                <table class="min-w-full border">

                    <thead class="bg-gray-100">
                        <tr>
                            <th class="border px-3 py-2 text-left">Measurement Item</th>
                            <th class="border px-3 py-2 text-center">Standard</th>
                            <th class="border px-3 py-2 text-center">Actual</th>
                            <th class="border px-3 py-2 text-center">Unit</th>
                        </tr>
                    </thead>

                    <tbody>

                        @forelse ($measurements as $i => $item)
                            @php
                                $oldMeasurement = $pmMeasurements->where('machine_measurement_id', $item->id)->first();
                            @endphp

                            <tr>
                                <td class="border px-3 py-2">
                                    {{ $item->measurement_item }}
                                    <input type="hidden"
                                        name="measurements[{{ $i }}][machine_measurement_id]"
                                        value="{{ $item->id }}">
                                    <input type="hidden" name="measurements[{{ $i }}][measurement_item]"
                                        value="{{ $item->measurement_item }}">
                                </td>

                                <td class="border px-3 py-2 text-center">
                                    {{ $item->standard ?? '-' }}
                                    <input type="hidden" name="measurements[{{ $i }}][standard]"
                                        value="{{ $item->standard }}">
                                </td>

                                <td class="border px-3 py-2">
                                    <input type="text" name="measurements[{{ $i }}][measurement_value]"
                                        value="{{ $oldMeasurement->measurement_value ?? '' }}"
                                        class="w-full border rounded-xl px-2 py-1 text-center">
                                </td>

                                <td class="border px-3 py-2 text-center">
                                    {{ $item->unit }}
                                    <input type="hidden" name="measurements[{{ $i }}][unit]"
                                        value="{{ $item->unit }}">
                                </td>
                            </tr>

                        @empty
                            <tr>
                                <td colspan="4" class="border text-center py-5 text-gray-500">No Measurement Found</td>
                            </tr>
                        @endforelse

                    </tbody>

                </table>

            </div>

            {{-- Mobile: card per item --}}
            <div class="md:hidden space-y-3">

                @forelse ($measurements as $i => $item)
                    @php
                        $oldMeasurement = $pmMeasurements->where('machine_measurement_id', $item->id)->first();
                    @endphp

                    <div class="rounded-lg border border-gray-200 bg-gray-50 p-3">
                        <div class="mb-2 flex items-center justify-between">
                            <div class="text-sm font-medium text-gray-800">{{ $item->measurement_item }}</div>
                            <div class="text-xs text-gray-500">Std: {{ $item->standard ?? '-' }} {{ $item->unit }}
                            </div>
                        </div>

                        <input type="hidden" name="measurements[{{ $i }}][machine_measurement_id]"
                            value="{{ $item->id }}">
                        <input type="hidden" name="measurements[{{ $i }}][measurement_item]"
                            value="{{ $item->measurement_item }}">
                        <input type="hidden" name="measurements[{{ $i }}][standard]"
                            value="{{ $item->standard }}">
                        <input type="hidden" name="measurements[{{ $i }}][unit]"
                            value="{{ $item->unit }}">

                        <div class="flex items-center gap-2">
                            <input type="text" data-name="measurements[{{ $i }}][measurement_value]"
                                value="{{ $oldMeasurement->measurement_value ?? '' }}"
                                placeholder="Masukkan nilai actual"
                                class="w-full rounded-xl border bg-white px-3 py-2 text-center">
                            <span class="shrink-0 text-sm text-gray-500">{{ $item->unit }}</span>
                        </div>
                    </div>

                @empty
                    <div class="rounded-lg border py-5 text-center text-gray-500">
                        No Measurement Found
                    </div>
                @endforelse

            </div>

        </div>

        {{-- Sparepart Usage --}}

        <div class="mt-6 bg-white p-4 rounded shadow">

            <h2 class="text-lg font-bold mb-4">
                Sparepart Usage
            </h2>

            <div id="sparepart-wrapper">

                @foreach ($pmSpareparts as $i => $oldSpare)
                    <div
                        class="sparepart-row flex gap-2 mb-2 max-sm:flex-col max-sm:bg-gray-50 max-sm:border max-sm:border-gray-200 max-sm:rounded-lg max-sm:p-3 max-sm:mb-3">

                        <select name="spareparts[{{ $i }}][sparepart_id]"
                            class="sparepart-select border p-2 w-2/3 rounded-xl max-sm:w-full"
                            placeholder="Select Sparepart" data-selected="{{ $oldSpare->sparepart_id }}">
                            <option value="{{ $oldSpare->sparepart_id }}">
                                {{ $oldSpare->sparepart->material_number }}
                                -
                                {{ $oldSpare->sparepart->description }}
                            </option>
                        </select>

                        <input type="number" name="spareparts[{{ $i }}][qty]" value="{{ $oldSpare->qty }}"
                            class="border p-2 w-1/3 rounded-xl max-sm:w-full">

                        <button type="button" onclick="removeSparepart(this)"
                            class="bg-red-500 text-white px-3 rounded-xl max-sm:w-full max-sm:py-2">
                            X
                        </button>

                    </div>
                @endforeach

            </div>

            <button type="button" onclick="addSparepart()"
                class="mt-2 bg-green-600 text-white px-3 py-1 rounded-xl max-sm:w-full max-sm:py-2">
                + Add Sparepart
            </button>

        </div>

        <div class="flex justify-end gap-3 mt-8 max-sm:flex-col">

            {{-- <a href="{{ route('pm-schedules.checklist', $pmSchedule->id) }}"
                class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-xl text-center max-sm:w-full">
                Go To Checklist (Dev)
            </a> --}}

            <button type="submit" class="bg-gray-500 hover:bg-green-700 text-white px-6 py-3 rounded-xl max-sm:w-full">
                Lihat Checklist
            </button>

        </div>

    </form>


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
