@extends('layouts.guest')

@section('title', 'Dashboard Preview · FreeDOMS')

@section('content')
    <x-landing.navbar />

    <main class="min-h-dvh bg-bg py-12 text-text sm:py-16">
        <div class="mx-auto max-w-7xl space-y-8 px-4 sm:px-6">
            <header>
                <h1 class="font-signage text-4xl font-semibold leading-tight text-text">Dashboard Preview</h1>
                <p class="mt-2 text-lg text-text-muted">Maintenance figures and activity as they stand right now</p>
                <x-ui.badge tone="primary" class="mt-3">Period: {{ $periodLabel }} (current month)</x-ui.badge>
            </header>

            @if ($overviewFailed)
                <x-ui.empty-state tone="error" title="Figures could not be loaded"
                    description="The overview query failed. Reload the page in a moment." />
            @else
                <dl class="card grid grid-cols-2 gap-px overflow-hidden bg-border lg:grid-cols-4">
                    @foreach ([
                        ['PM This Month', $pmThisMonth, 'PMs scheduled this month', 'text-text'],
                        ['Overdue PM', $overduePm, 'PMs past their date', $overduePm > 0 ? 'text-danger' : 'text-text'],
                        ['Problems', $problems, 'Machine problems', 'text-text'],
                        ['Sparepart Usage', $sparepartUsage, 'Spareparts used', 'text-text'],
                    ] as [$label, $value, $hint, $color])
                        <div class="flex flex-col-reverse justify-end gap-1 bg-surface p-5">
                            <dt class="text-sm text-text-muted">
                                <span class="font-semibold text-text">{{ $label }}</span>
                                <span class="block text-xs">{{ $hint }}</span>
                            </dt>
                            <dd class="font-signage text-4xl font-semibold tabular-nums {{ $color }}">{{ number_format($value) }}</dd>
                        </div>
                    @endforeach
                </dl>
            @endif

            <div class="grid grid-cols-1 gap-5 lg:grid-cols-3">
                <section class="card p-6 lg:col-span-2" aria-labelledby="dg-guide">
                    <h2 id="dg-guide" class="text-lg font-semibold text-text">How to use it</h2>
                    <ol class="mt-5 space-y-4">
                        @foreach ([
                            ['Scan a QR code', 'Use the QR scan on the landing page to pull up a machine history on the spot'],
                            ['Read the figures', 'Follow this month\'s PMs, overdue PMs, and machine problems on one dashboard'],
                            ['Log in for full access', 'Signing in opens every maintenance feature'],
                        ] as [$step, $text])
                            <li class="flex gap-4">
                                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-control bg-primary-light font-signage text-lg font-semibold text-primary-hover" aria-hidden="true">{{ $loop->iteration }}</span>
                                <div>
                                    <h3 class="font-semibold text-text">{{ $step }}</h3>
                                    <p class="mt-1 text-sm text-text-muted">{{ $text }}</p>
                                </div>
                            </li>
                        @endforeach
                    </ol>
                </section>

                <section class="card flex flex-col p-6" aria-labelledby="dg-start">
                    <h2 id="dg-start" class="text-lg font-semibold text-text">Get going</h2>
                    <p class="mt-2 text-sm text-text-muted">Open the full feature set and run preventive maintenance from one place</p>
                    <div class="mt-6 grid gap-3">
                        <x-ui.button size="lg" href="{{ route('qr.scan') }}">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7V4h3M20 7V4h-3M4 17v3h3M20 17v3h-3M8 8h2v2H8zM14 8h2v2h-2zM8 14h2v2H8zM14 14h2v2h-2z" />
                            </svg>
                            Scan a machine
                        </x-ui.button>
                        @guest
                            <x-ui.button size="lg" variant="outline" href="{{ route('login') }}">Login</x-ui.button>
                        @endguest
                    </div>
                </section>
            </div>
        </div>
    </main>

    <x-landing.footer />
@endsection
