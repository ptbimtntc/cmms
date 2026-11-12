{{-- Status of an activity row (ACTIVE / FINISHED / CLOSED) or of a PIC (ACTIVE / INACTIVE / NOT STARTED). Param: status. --}}
@switch($status)
    @case('ACTIVE')
        <x-ui.badge tone="success">ACTIVE</x-ui.badge>
        @break
    @case('FINISHED')
        <x-ui.badge tone="primary">FINISHED</x-ui.badge>
        @break
    @case('INACTIVE')
        <x-ui.badge tone="warning">INACTIVE</x-ui.badge>
        @break
    @default
        <x-ui.badge>{{ $status }}</x-ui.badge>
@endswitch
