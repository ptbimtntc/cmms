{{--
    Dialog shell. Pages keep their own open/close code: they toggle the
    `hidden` and `flex` classes on the element with this id, as before.
    This component adds what those pages lacked: dialog semantics, a
    labelled title, Escape to close, and focus moved into the dialog on open.

    <x-ui.modal id="manual-edit-modal" title="Edit activity">...form...</x-ui.modal>
--}}
@props([
    'id',
    'title',
    'description' => null,
    'size' => 'md',
])

@php
    $widths = ['sm' => 'max-w-sm', 'md' => 'max-w-md', 'lg' => 'max-w-2xl'];
@endphp

<div id="{{ $id }}" data-ui-modal role="dialog" aria-modal="true" aria-labelledby="{{ $id }}-title"
    {{ $attributes->merge(['class' => 'fixed inset-0 z-50 hidden items-center justify-center bg-neutral-900/40 p-4']) }}>
    <div class="max-h-[90dvh] w-full {{ $widths[$size] ?? $widths['md'] }} overflow-y-auto rounded-card border border-border bg-surface p-5 shadow-popover sm:p-6">
        <h2 id="{{ $id }}-title" class="text-lg font-semibold text-text">{{ $title }}</h2>
        @if ($description)
            <p class="mt-1 text-sm text-text-muted">{{ $description }}</p>
        @endif
        {{ $slot }}
    </div>
</div>

@once
    <script>
        (function () {
            const isOpen = (m) => ! m.classList.contains('hidden');

            // Escape closes the top-most open dialog.
            document.addEventListener('keydown', function (e) {
                if (e.key !== 'Escape') return;
                const open = [...document.querySelectorAll('[data-ui-modal]')].filter(isOpen).pop();
                if (! open) return;
                open.classList.add('hidden');
                open.classList.remove('flex');
            });

            // When a page opens a dialog, move focus into it; when it closes, give focus back.
            let returnTo = null;
            const watch = new MutationObserver(function (records) {
                records.forEach(function (r) {
                    const m = r.target;
                    if (isOpen(m)) {
                        returnTo = document.activeElement;
                        const first = m.querySelector('input:not([type=hidden]):not([disabled]), select, textarea, button');
                        if (first) setTimeout(() => first.focus(), 0);
                    } else if (returnTo && document.contains(returnTo)) {
                        returnTo.focus();
                        returnTo = null;
                    }
                });
            });
            const start = () => document.querySelectorAll('[data-ui-modal]').forEach(function (m) {
                watch.observe(m, { attributes: true, attributeFilter: ['class'] });
            });
            document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', start) : start();
        })();
    </script>
@endonce
