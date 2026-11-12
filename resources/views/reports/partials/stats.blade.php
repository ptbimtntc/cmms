{{--
    Summary figures for a report: one card, cells split by hairlines.
    $items: list of ['label' => string, 'value' => string|int, 'hint' => ?string, 'tone' => ?'success'|'warning'|'danger'|'primary']
    $cols:  grid column classes, e.g. 'grid-cols-2 sm:grid-cols-4'.
    Tone colours the number only when it carries meaning (missed, failed); the label always says what it is.
--}}
@php
    $toneText = ['success' => 'text-success-hover', 'warning' => 'text-warning-text', 'danger' => 'text-danger', 'primary' => 'text-primary-hover'];
@endphp
<dl class="card grid gap-px overflow-hidden bg-border {{ $cols ?? 'grid-cols-2 sm:grid-cols-4' }}">
    @foreach ($items as $item)
        <div class="flex min-w-0 flex-col-reverse justify-end gap-1 bg-surface p-4">
            <dt class="text-xs text-text-muted">
                {{ $item['label'] }}
                @if (! empty($item['hint']))
                    <span class="mt-0.5 block text-text-muted/80">{{ $item['hint'] }}</span>
                @endif
            </dt>
            <dd class="break-words font-signage text-2xl font-semibold tabular-nums leading-tight {{ $toneText[$item['tone'] ?? ''] ?? 'text-text' }}">{{ $item['value'] }}</dd>
        </div>
    @endforeach
</dl>
