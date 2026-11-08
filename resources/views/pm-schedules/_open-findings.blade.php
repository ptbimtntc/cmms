{{--
    Open findings on this machine from other modules (Oil Audit, Predictive,
    Greasing, Blower Panel), so the PIC can handle them during the PM.
    Read-only; each item links to its own module when the user may open it.
    Expects $openFindings from App\Services\MachineOpenFindings.
--}}
@php
    $findingsTotal = collect($openFindings)->sum('total');
    $findingsError = collect($openFindings)->contains('error', true);
@endphp

@if ($findingsTotal === 0 && ! $findingsError)
    <p class="mb-6 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
        No open findings for this machine.
    </p>
@else
    <details class="group mb-6 rounded-xl border border-amber-300 bg-amber-50" data-open-findings>
        <summary
            class="flex min-h-11 cursor-pointer list-none flex-wrap items-center gap-x-3 gap-y-2 rounded-xl px-4 py-3 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-500 [&::-webkit-details-marker]:hidden">
            <span class="font-semibold text-slate-900">
                Open findings on this machine
                <span class="ml-1 rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-800">{{ $findingsTotal }}</span>
            </span>

            <span class="flex flex-wrap gap-x-3 gap-y-1 text-sm">
                @foreach ($openFindings as $source)
                    <span class="{{ $source['total'] > 0 ? 'font-medium text-amber-800' : 'text-slate-500' }}">
                        {{ $source['label'] }} {{ $source['total'] }}@if ($source['error'])<span class="font-semibold text-red-700" title="Could not load">!</span>@endif
                    </span>
                @endforeach
            </span>

            <span class="ml-auto text-sm font-medium text-amber-800 underline underline-offset-2">
                <span class="group-open:hidden">Show</span>
                <span class="hidden group-open:inline">Hide</span>
            </span>
        </summary>

        <div class="space-y-4 border-t border-amber-200 px-4 py-4">
            @foreach ($openFindings as $source)
                @continue($source['total'] === 0 && ! $source['error'])

                <section>
                    <h3 class="mb-2 text-sm font-semibold text-slate-800">{{ $source['label'] }}</h3>

                    @if ($source['error'])
                        <p class="text-sm text-red-700">Could not load {{ $source['label'] }} findings.</p>
                    @else
                        <ul class="divide-y divide-amber-100 rounded-lg border border-amber-200 bg-white">
                            @foreach ($source['items'] as $item)
                                <li class="flex flex-col gap-1 px-3 py-2 sm:flex-row sm:items-center sm:gap-4">
                                    <div class="min-w-0 flex-1">
                                        <p class="break-words font-medium text-slate-900">{{ $item['title'] }}</p>
                                        @if ($item['detail'])
                                            <p class="break-words text-sm text-slate-600">{{ $item['detail'] }}</p>
                                        @endif
                                    </div>
                                    @if ($item['date'])
                                        <span class="shrink-0 text-sm text-slate-600">{{ $item['date'] }}</span>
                                    @endif
                                    @if ($item['url'])
                                        <a href="{{ $item['url'] }}"
                                            class="inline-flex min-h-11 shrink-0 items-center text-sm font-medium text-blue-700 underline underline-offset-2 hover:text-blue-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 rounded">
                                            Open in {{ $source['label'] }}
                                        </a>
                                    @endif
                                </li>
                            @endforeach
                        </ul>

                        @if ($source['more_url'])
                            <a href="{{ $source['more_url'] }}"
                                class="mt-2 inline-flex min-h-11 items-center text-sm font-medium text-blue-700 underline underline-offset-2 hover:text-blue-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 rounded">
                                See all {{ $source['total'] }} in {{ $source['label'] }}
                            </a>
                        @elseif ($source['total'] > count($source['items']))
                            <p class="mt-2 text-sm text-slate-600">Showing {{ count($source['items']) }} of {{ $source['total'] }}.</p>
                        @endif
                    @endif
                </section>
            @endforeach
        </div>
    </details>
@endif
