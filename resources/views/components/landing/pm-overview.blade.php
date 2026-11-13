{{--
    The evidence section: real counts for the running month, replacing the
    old "Dashboard Preview / Data contoh" mock card. Same source and cache
    (PublicPmOverview) as /dashboard-guest, so the two pages never disagree.
--}}
@php
    $monthly = \App\Services\PublicPmOverview::monthly();
    $cumulative = \App\Services\PublicPmOverview::cumulative();
@endphp

<section id="pm-overview" class="bg-background py-14 lg:py-20">

    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">

        @if ($monthly)

            <div class="flex flex-wrap items-baseline justify-between gap-3">
                <h2 class="font-signage text-2xl font-bold text-text sm:text-3xl">
                    Preventive maintenance, {{ $monthly['period_label'] }}
                </h2>
                <x-ui.badge tone="neutral">Updated every 10 minutes</x-ui.badge>
            </div>

            <dl class="card mt-6 grid grid-cols-2 gap-px overflow-hidden bg-border lg:grid-cols-4">

                <div class="bg-surface p-5">
                    <dt class="text-sm text-text-muted">Planned this month</dt>
                    <dd class="tabular mt-1 font-signage text-4xl font-bold text-text">{{ number_format($monthly['planned']) }}</dd>
                </div>

                <div class="bg-surface p-5">
                    <dt class="text-sm text-text-muted">Overdue</dt>
                    <dd class="tabular mt-1 font-signage text-4xl font-bold {{ $monthly['overdue'] > 0 ? 'text-danger' : 'text-text' }}">
                        {{ number_format($monthly['overdue']) }}
                    </dd>
                </div>

                <div class="bg-surface p-5">
                    <dt class="text-sm text-text-muted">Problems recorded</dt>
                    <dd class="tabular mt-1 font-signage text-4xl font-bold text-text">{{ number_format($monthly['problems']) }}</dd>
                </div>

                <div class="bg-surface p-5">
                    <dt class="text-sm text-text-muted">Spareparts used</dt>
                    <dd class="tabular mt-1 font-signage text-4xl font-bold text-text">{{ number_format($monthly['sparepart_usage']) }}</dd>
                </div>

            </dl>

            <div class="mt-6 grid gap-8 lg:grid-cols-[1.3fr_0.7fr] lg:items-center lg:gap-16">

                @if ($monthly['planned'] > 0)
                    <x-ui.progress :value="$monthly['closing_percent']" :max="100"
                        label="Closed on time or late this month" />
                @else
                    <p class="text-sm text-text-muted">No PM is planned for this month yet.</p>
                @endif

                @if ($cumulative)
                    <dl class="flex flex-wrap gap-x-8 gap-y-3 text-sm lg:justify-end">
                        <div>
                            <dt class="text-text-muted">Machines</dt>
                            <dd class="tabular font-signage text-xl font-semibold text-text">{{ number_format($cumulative['machines']) }}</dd>
                        </div>
                        <div>
                            <dt class="text-text-muted">PM records</dt>
                            <dd class="tabular font-signage text-xl font-semibold text-text">{{ number_format($cumulative['pm']) }}</dd>
                        </div>
                        <div>
                            <dt class="text-text-muted">Spareparts</dt>
                            <dd class="tabular font-signage text-xl font-semibold text-text">{{ number_format($cumulative['spareparts']) }}</dd>
                        </div>
                    </dl>
                @endif

            </div>

            <p class="mt-6 text-sm text-text-muted">
                These figures are read directly from the database and cached for 10 minutes.
            </p>

        @else

            <h2 class="font-signage text-2xl font-bold text-text sm:text-3xl">Preventive maintenance this month</h2>

            <x-ui.empty-state tone="error" title="Figures could not be loaded"
                description="The overview query failed. Reload the page in a moment, or open the full dashboard once you are signed in." class="mt-6">
                <x-ui.button href="{{ route('dashboard-guest') }}" variant="outline">Open the dashboard</x-ui.button>
            </x-ui.empty-state>

        @endif

    </div>

</section>
