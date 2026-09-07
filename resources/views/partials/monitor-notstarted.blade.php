{{--
    Bottom line listing PICs who have not started an activity yet. Mirrored
    in JS (render() in today-activity-monitor) for the auto-refresh poll.

    @param array $notStarted  PIC names
    @param int   $totalPics
--}}
@if (count($notStarted) === 0 && $totalPics > 0)
    All PIC are active
@elseif (count($notStarted) > 0)
    Not started: <span class="text-slate-300">{{ strtoupper(implode(', ', $notStarted)) }}</span>
@endif
