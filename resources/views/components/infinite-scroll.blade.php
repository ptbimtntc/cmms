@props(['paginator', 'key'])
{{--
    Shared infinite-scroll footer (replaces `$paginator->links()`).

    Usage: put `data-infinite-list="{{ $key }}"` on every element whose children
    are the rows/cards of this paginator (a <tbody>, a card-list <div>; a page
    may have several, e.g. a desktop table + a mobile card list), then place
    <x-infinite-scroll :paginator="$p" key="..." /> after them. When the
    sentinel nears the viewport, resources/js/infinite-scroll.js fetches the
    next page URL (current URL + page=N, so every search / filter / sort /
    area scope is reapplied by the same controller) and appends the rows.
    Numbered links stay available as a <noscript> fallback.
--}}
@php
    // url()->current(), not request()->fullUrlWithQuery(): behind the
    // Codespaces proxy the raw request reports "localhost:8000", which the
    // browser cannot reach; url() follows the forced root URL set in
    // AppServiceProvider (and is the plain request URL everywhere else).
    $next = $paginator->hasMorePages()
        ? url()->current().'?'.\Illuminate\Support\Arr::query(array_merge(
            request()->query(),
            [$paginator->getPageName() => $paginator->currentPage() + 1]
        ))
        : null;
@endphp
<div data-infinite-sentinel="{{ $key }}" @if ($next) data-next-url="{{ $next }}" @endif {{ $attributes->class('w-full') }}>
    <div data-infinite-trigger aria-hidden="true" class="h-px"></div>
    <p data-infinite-loading class="hidden py-4 text-center text-sm text-slate-500">Memuat data…</p>
    <p data-infinite-end class="hidden py-4 text-center text-sm text-slate-400">Everything is loaded.</p>
    <div data-infinite-error class="hidden py-4 text-center text-sm text-rose-600">
        Could not load the next batch.
        <button type="button" data-infinite-retry class="font-semibold underline">Try again</button>
    </div>
    @if ($next)
        <div data-infinite-more class="hidden py-3 text-center">
            <button type="button" data-infinite-retry class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100">Load more</button>
        </div>
    @endif
    <noscript>{{ $paginator->links() }}</noscript>
</div>
