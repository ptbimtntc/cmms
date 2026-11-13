<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-slate-50 font-plain text-slate-800 antialiased dark:bg-slate-950 dark:text-slate-100">
        <div class="grid min-h-screen lg:grid-cols-[1.1fr_0.9fr]">

            {{-- Same WWD line as the landing hero, so signing in feels like stepping onto that floor. --}}
            <aside class="on-dark relative h-56 bg-blue-950 text-white sm:h-72 lg:h-auto lg:min-h-screen">

                <x-landing.factory-scene uid="auth" class="absolute inset-0">
                    <div class="scene-scrim pointer-events-none absolute inset-0 hidden lg:block"></div>
                    <div class="scene-fade pointer-events-none absolute inset-x-0 bottom-0 h-2/3"></div>
                </x-landing.factory-scene>

                <div class="pointer-events-none relative z-10 flex h-full flex-col justify-between p-5 sm:p-8 lg:p-12">

                    <a href="{{ route('home') }}"
                        class="pointer-events-auto flex min-h-11 w-fit items-center gap-3 rounded-md focus-visible:outline-3 focus-visible:outline-offset-4 focus-visible:outline-white">
                        <x-brand-logo class="h-10 w-auto shrink-0" aria-hidden="true" />
                        <span class="leading-tight">
                            <span class="block font-signage text-xl font-bold">FreeDOMS</span>
                            <span class="block text-xs text-blue-200">Preventive Maintenance System</span>
                        </span>
                    </a>

                    <div class="hidden max-w-md pb-14 lg:block">
                        <p class="font-signage text-4xl font-bold leading-tight">
                            Sign in to fill PM checklists and record inspection results.
                        </p>
                        <p class="mt-4 text-lg text-blue-100">
                            Machine scan, dashboard, and monitor stay open without signing in.
                        </p>
                    </div>

                </div>
            </aside>

            <main data-auth class="flex flex-col justify-center px-4 py-10 sm:px-10 lg:px-16">
                <div class="mx-auto w-full max-w-sm lg:mx-0">

                    {{ $slot }}

                    <nav aria-label="Access without signing in" class="mt-10 border-t-2 border-slate-200 pt-6 dark:border-slate-800">
                        <p class="text-sm font-semibold text-slate-700 dark:text-slate-300">Without signing in</p>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <a href="{{ route('qr.scan') }}"
                                class="inline-flex min-h-11 items-center gap-2 rounded-md bg-primary px-4 font-semibold text-white hover:bg-primary-hover active:bg-primary-active focus-visible:outline-3 focus-visible:outline-offset-2 focus-visible:outline-blue-600">
                                <x-landing.icon name="qr" class="h-5 w-5" />
                                Scan a machine
                            </a>
                            <a href="{{ route('dashboard-guest') }}"
                                class="inline-flex min-h-11 items-center rounded-md border-2 border-blue-950 px-4 font-semibold text-blue-950 hover:bg-blue-950 hover:text-white dark:border-blue-300 dark:text-white dark:hover:bg-blue-900 focus-visible:outline-3 focus-visible:outline-offset-2 focus-visible:outline-blue-600">
                                Dashboard
                            </a>
                            <a href="{{ route('monitor') }}"
                                class="inline-flex min-h-11 items-center rounded-md border-2 border-blue-950 px-4 font-semibold text-blue-950 hover:bg-blue-950 hover:text-white dark:border-blue-300 dark:text-white dark:hover:bg-blue-900 focus-visible:outline-3 focus-visible:outline-offset-2 focus-visible:outline-blue-600">
                                Monitor
                            </a>
                        </div>
                    </nav>

                </div>
            </main>
        </div>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
