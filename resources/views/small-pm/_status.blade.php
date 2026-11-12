{{-- Same statuses and colours as Greasing: FINISH (after due date) red, FINISH ON TIME green, OPEN pending. --}}
@include('greasings.partials.status-badge', ['status' => $status])
