{{-- Summary of the last import (imported / duplicate / skipped), plus per-row errors when the importer sends them. --}}
<x-ui.alert :tone="! empty($result['errors']) || ($result['skipped'] ?? 0) > 0 ? 'warning' : 'success'" title="Import result">
    <span class="font-semibold">{{ $result['imported'] }}</span> imported,
    <span class="font-semibold">{{ $result['duplicate'] }}</span> duplicate,
    <span class="font-semibold">{{ $result['skipped'] }}</span> skipped/invalid.
    @if (! empty($result['errors']))
        <ul class="mt-2 list-disc space-y-0.5 pl-5">
            @foreach ($result['errors'] as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    @endif
</x-ui.alert>
