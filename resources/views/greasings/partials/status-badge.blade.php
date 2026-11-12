{{--
    Greasing status. Tones keep the module's existing meaning: FINISH (done after the due date)
    was always shown in red, FINISH ON TIME in green, OPEN as pending. Param: status.
--}}
@php
    $tone = match ($status) {
        'OPEN' => 'warning',
        'FINISH' => 'danger',
        'FINISH ON TIME' => 'success',
        default => 'neutral',
    };
@endphp
<x-ui.badge :tone="$tone" class="shrink-0">{{ $status }}</x-ui.badge>
