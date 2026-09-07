{{--
    The list of PICs with no active activity yet, or "ALL PIC ARE ACTIVE".
    Mirrored in JS (render() in today-activity-monitor) for the auto-refresh
    poll. The section heading ("Not Started") lives in the status panel.

    @param array $notStarted  PIC names
    @param int   $totalPics
--}}
@if (count($notStarted) === 0 && $totalPics > 0)
    ALL PIC ARE ACTIVE
@elseif (count($notStarted) > 0)
    {{ strtoupper(implode(' · ', $notStarted)) }}
@endif
