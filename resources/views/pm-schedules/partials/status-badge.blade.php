{{--
    PM status as a badge. Tones follow the dashboard's PM Status chart:
    open = attention, in progress = info, finished = success, missed = danger.
    Param: status (raw PM status), short (bool, "ON TIME" instead of "FINISHED ON TIME").
--}}
@php
    $map = [
        'OPEN' => ['warning', 'OPEN'],
        'IN_PROGRESS' => ['info', 'IN PROGRESS'],
        'FINISHED' => ['success', 'FINISHED'],
        'FINISHED_ON_TIME' => ['success', ($short ?? false) ? 'ON TIME' : 'FINISHED ON TIME'],
        'MISSED' => ['danger', 'MISSED'],
    ];
    [$tone, $text] = $map[$status] ?? ['neutral', $status];
@endphp
<x-ui.badge :tone="$tone" class="shrink-0">{{ $text }}</x-ui.badge>
