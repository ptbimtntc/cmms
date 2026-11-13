@php
    $isAdmin = auth()->check() && auth()->user()->role === \App\Models\User::ROLE_ADMIN;
    $links = [
        ['href' => '#features', 'label' => 'Features'],
        ['href' => '#how-it-works', 'label' => 'How It Works'],
        ['href' => '#tim-maintenance', 'label' => 'Tim Maintenance'],
        ['href' => route('dashboard-guest'), 'label' => 'Dashboard'],
        ['href' => route('monitor'), 'label' => 'Monitor'],
    ];
    $linkClass = 'inline-flex min-h-11 items-center text-[15px] font-medium text-blue-100 hover:text-white hover:underline underline-offset-8 decoration-2 decoration-blue-300';
@endphp

<header class="on-dark sticky top-0 z-50 border-b border-blue-800 bg-blue-950 text-white" x-data="{ menu: false }"
    @keydown.escape.window="menu = false">

    <nav class="mx-auto flex h-16 max-w-7xl items-center justify-between gap-2 px-4 sm:px-6 lg:px-8"
        aria-label="Navigasi utama">

        <a href="{{ route('home') }}" class="flex min-h-11 items-center gap-3">

            <x-brand-logo class="h-8 w-auto shrink-0 sm:h-10" aria-hidden="true" />

            {{-- On phones the logo carries the name; the text stays for screen readers. --}}
            <span class="sr-only leading-tight sm:not-sr-only">
                <span class="block font-signage text-xl font-bold text-white">FreeDOMS</span>
                <span class="hidden text-xs text-blue-200 sm:block">Preventive Maintenance</span>
            </span>

        </a>

        {{-- Desktop menu --}}
        <div class="hidden items-center gap-6 lg:flex">

            @foreach ($links as $link)
                <a href="{{ $link['href'] }}" class="{{ $linkClass }}">{{ $link['label'] }}</a>
            @endforeach

            {{-- PM Status is split per area, so the nav offers both area pages instead of picking a default. --}}
            <div class="relative" x-data="{ open: false }" @keydown.escape="open = false">
                <button type="button" @click="open = !open" @click.outside="open = false" :aria-expanded="open"
                    aria-haspopup="true" class="{{ $linkClass }} gap-1">

                    PM Status

                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                        stroke-linecap="square" class="h-4 w-4 transition" :class="open && 'rotate-180'"
                        aria-hidden="true">
                        <path d="m6 9 6 6 6-6" />
                    </svg>

                </button>

                <div x-show="open" x-cloak
                    class="absolute left-0 z-40 mt-2 w-36 rounded-md border-2 border-blue-950 bg-white py-1">

                    <a href="{{ route('pm-status.show', 'wwd') }}"
                        class="flex min-h-11 items-center px-4 text-sm font-medium text-slate-700 hover:bg-blue-50 hover:text-blue-700">
                        WWD
                    </a>

                    <a href="{{ route('pm-status.show', 'bul') }}"
                        class="flex min-h-11 items-center px-4 text-sm font-medium text-slate-700 hover:bg-blue-50 hover:text-blue-700">
                        BUL
                    </a>

                </div>
            </div>

            @if ($isAdmin)
                <a href="{{ route('maintenance-intelligence.index') }}" class="{{ $linkClass }}">
                    Maintenance Intelligence
                </a>
            @endif

        </div>

        {{-- Actions --}}
        <div class="flex items-center gap-2 sm:gap-3">

            @guest
                <a href="{{ route('login') }}"
                    class="hidden min-h-11 items-center rounded-md border-2 border-blue-300 px-4 text-[15px] font-semibold text-white hover:bg-blue-900 sm:inline-flex">
                    Login
                </a>
            @else
                <div class="hidden md:block">
                    <flux:dropdown position="bottom end" align="end">
                        <button type="button" class="inline-flex min-h-11 min-w-11 items-center justify-center"
                            aria-label="Menu akun">
                            <flux:avatar size="sm" :src="auth()->user()->photo_url" :name="auth()->user()->name"
                                :initials="auth()->user()->initials()" />
                        </button>

                        <flux:menu>
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <flux:avatar :src="auth()->user()->photo_url" :name="auth()->user()->name"
                                    :initials="auth()->user()->initials()" />
                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                                    <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                                </div>
                            </div>
                            <flux:menu.separator />
                            <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                                {{ __('Settings') }}
                            </flux:menu.item>
                            <form method="POST" action="{{ route('logout') }}" class="w-full">
                                @csrf
                                <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle"
                                    class="w-full cursor-pointer">
                                    {{ __('Log out') }}
                                </flux:menu.item>
                            </form>
                        </flux:menu>
                    </flux:dropdown>
                </div>
            @endguest

            {{-- Amber is reserved for the scan action: it is where a technician starts. --}}
            <a href="{{ route('qr.scan') }}"
                class="inline-flex min-h-11 items-center gap-2 rounded-md bg-white px-3 text-[15px] font-semibold text-blue-900 hover:bg-blue-50 active:bg-blue-100 sm:px-4">
                <x-landing.icon name="qr" class="h-5 w-5" />
                <span>Scan<span class="max-sm:sr-only"> Mesin</span></span>
            </a>

            <button type="button" @click="menu = !menu" :aria-expanded="menu" aria-controls="mobile-menu"
                class="inline-flex min-h-11 items-center gap-2 rounded-md border-2 border-blue-300 px-3 text-[15px] font-semibold text-white hover:bg-blue-900 lg:hidden">
                Menu
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="square"
                    class="h-5 w-5" aria-hidden="true">
                    <path x-show="!menu" d="M4 7h16M4 12h16M4 17h16" />
                    <path x-show="menu" x-cloak d="M6 6l12 12M18 6L6 18" />
                </svg>
            </button>

        </div>

    </nav>

    {{-- Mobile menu: every destination from the desktop bar, plus account actions. --}}
    <div id="mobile-menu" x-show="menu" x-cloak class="border-t border-blue-800 bg-blue-950 lg:hidden">
        <div class="mx-auto max-w-7xl px-4 py-2 sm:px-6">

            <ul class="divide-y divide-blue-800">

                @foreach ($links as $link)
                    <li>
                        <a href="{{ $link['href'] }}" @click="menu = false"
                            class="flex min-h-12 items-center font-medium text-blue-50 hover:text-white">
                            {{ $link['label'] }}
                        </a>
                    </li>
                @endforeach

                <li>
                    <a href="{{ route('pm-status.show', 'wwd') }}"
                        class="flex min-h-12 items-center font-medium text-blue-50 hover:text-white">
                        PM Status WWD
                    </a>
                </li>

                <li>
                    <a href="{{ route('pm-status.show', 'bul') }}"
                        class="flex min-h-12 items-center font-medium text-blue-50 hover:text-white">
                        PM Status BUL
                    </a>
                </li>

                @if ($isAdmin)
                    <li>
                        <a href="{{ route('maintenance-intelligence.index') }}"
                            class="flex min-h-12 items-center font-medium text-blue-50 hover:text-white">
                            Maintenance Intelligence
                        </a>
                    </li>
                @endif

                @guest
                    <li class="sm:hidden">
                        <a href="{{ route('login') }}"
                            class="flex min-h-12 items-center font-semibold text-blue-300">
                            Login
                        </a>
                    </li>
                @else
                    <li>
                        <a href="{{ route('profile.edit') }}"
                            class="flex min-h-12 items-center font-medium text-blue-50 hover:text-white">
                            {{ __('Settings') }}
                        </a>
                    </li>
                    <li>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit"
                                class="flex min-h-12 w-full items-center font-medium text-blue-50 hover:text-white">
                                {{ __('Log out') }}
                            </button>
                        </form>
                    </li>
                @endguest

            </ul>

        </div>
    </div>

</header>
