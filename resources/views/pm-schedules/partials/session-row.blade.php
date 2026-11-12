{{--
    One working day of a PM. resources/js/pm/edit.js addSession() builds the same markup
    for new days; keep the two in step (classes .session-row/.session-start/.session-end/
    .session-duration/.remove-session are what the script reads).
    Date and start are required, matching PMScheduleSaveService::rules().
    Params: index, id, date, start, end, duration (text), removable.
--}}
@php
    $fieldErrors = [
        'actual_date' => $errors->first("sessions.$index.actual_date"),
        'start_time' => $errors->first("sessions.$index.start_time"),
        'end_time' => $errors->first("sessions.$index.end_time"),
    ];
    $hasError = collect($fieldErrors)->filter()->isNotEmpty();
    $invalid = fn ($field) => $fieldErrors[$field] ? 'aria-invalid=true aria-describedby=session-' . $index . '-error' : '';
@endphp

<div class="session-row rounded-control border border-border bg-surface-sunken p-3" data-index="{{ $index }}">
    <input type="hidden" name="sessions[{{ $index }}][id]" value="{{ $id }}">

    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
        <div class="grid gap-1.5">
            <label for="session-{{ $index }}-date" class="text-xs font-medium text-text-muted">Date</label>
            <input id="session-{{ $index }}-date" type="date" name="sessions[{{ $index }}][actual_date]" value="{{ $date }}" required
                @class(['input', 'is-invalid' => $fieldErrors['actual_date']]) {{ $invalid('actual_date') }}>
        </div>
        <div class="grid gap-1.5">
            <label for="session-{{ $index }}-start" class="text-xs font-medium text-text-muted">Start</label>
            <input id="session-{{ $index }}-start" type="time" name="sessions[{{ $index }}][start_time]" value="{{ $start }}" required
                @class(['input session-start', 'is-invalid' => $fieldErrors['start_time']]) {{ $invalid('start_time') }}>
        </div>
        <div class="grid gap-1.5">
            <label for="session-{{ $index }}-end" class="text-xs font-medium text-text-muted">End</label>
            <input id="session-{{ $index }}-end" type="time" name="sessions[{{ $index }}][end_time]" value="{{ $end }}"
                @class(['input session-end', 'is-invalid' => $fieldErrors['end_time']]) {{ $invalid('end_time') }}>
        </div>
    </div>

    @if ($hasError)
        <p id="session-{{ $index }}-error" class="form-error mt-2">{{ collect($fieldErrors)->filter()->implode(' ') }}</p>
    @endif

    <div class="mt-2 flex min-h-11 items-center justify-between gap-3 lg:min-h-9">
        <p class="text-sm text-text-muted">Duration: <span class="session-duration font-medium text-text">{{ $duration }}</span></p>
        @if ($removable)
            <button type="button" class="remove-session btn btn-ghost min-h-11 text-danger hover:bg-danger-light hover:text-danger-hover lg:min-h-9">Remove Day</button>
        @endif
    </div>
</div>
