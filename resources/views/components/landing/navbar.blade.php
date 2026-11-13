@php
    $isAdmin = auth()->check() && auth()->user()->role === \App\Models\User::ROLE_ADMIN;

    $links = [
        ['href' => route('monitor'), 'label' => 'Monitor'],
        ['href' => route('dashboard-guest'), 'label' => 'Dashboard'],
        ['href' => route('machine-history.index'), 'label' => 'Machine History'],
    ];

    $pmStatusAreas = null;

    try {
        $pmStatusAreas = \App\Models\Area::active()->orderBy('name')->get(['name', 'slug']);
    } catch (\Throwable $e) {
        report($e);
    }

    $linkClass = 'inline-flex min-h-11 items-center text-[15px] font-medium text-text hover:text-primary';
@endphp

<header class="sticky top-0 z-50 border-b border-border bg-surface" x-data="{ menu: false }"
    @keydown.escape.window="menu = false">

    <nav class="mx-auto flex h-16 max-w-7xl items-center justify-between gap-2 px-4 sm:px-6 lg:px-8"
        aria-label="Main navigation">

        <a href="{{ route('home') }}" class="flex min-h-11 items-center gap-3">

            <x-brand-logo :framed="false" class="h-8 w-auto shrink-0 sm:h-9" aria-hidden="true" />

            <span class="sr-only leading-tight sm:not-sr-only">
                <span class="block font-signage text-lg font-bold text-text">FreeDOMS</span>
            </span>

        </a>

        {{-- Desktop menu --}}
        <div class="hidden items-center gap-6 lg:flex">

            {{-- PM Status is split per area, so the nav offers each active area instead of picking a default. --}}
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
                    class="card absolute left-0 z-40 mt-2 w-40 py-1">

                    @if ($pmStatusAreas)
                        @foreach ($pmStatusAreas as $area)
                            <a href="{{ route('pm-status.show', $area->slug) }}"
                                class="flex min-h-11 items-center px-4 text-sm font-medium text-text hover:bg-surface-muted">
                                {{ $area->name }}
                            </a>
                        @endforeach
                    @endif

                </div>
            </div>

            @foreach ($links as $link)
                <a href="{{ $link['href'] }}" class="{{ $linkClass }}">{{ $link['label'] }}</a>
            @endforeach

            @if ($isAdmin)
                <a href="{{ route('maintenance-intelligence.index') }}" class="{{ $linkClass }}">
                    Maintenance Intelligence
                </a>
            @endif

        </div>

        {{-- Actions --}}
        <div class="flex items-center gap-2 sm:gap-3">

            @guest
                <x-ui.button href="{{ route('login') }}" variant="outline" class="hidden sm:inline-flex">
                    Sign in
                </x-ui.button>
            @else
                <div class="hidden md:block">
                    <flux:dropdown position="bottom end" align="end">
                        <button type="button" class="inline-flex min-h-11 min-w-11 items-center justify-center"
                            aria-label="Account menu">
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

            <button type="button" @click="menu = !menu" :aria-expanded="menu" aria-controls="mobile-menu"
                class="btn btn-outline min-h-11 lg:hidden">
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
    <div id="mobile-menu" x-show="menu" x-cloak class="border-t border-border bg-surface lg:hidden">
        <div class="mx-auto max-w-7xl px-4 py-2 sm:px-6">

            <ul class="divide-y divide-border">

                @if ($pmStatusAreas)
                    @foreach ($pmStatusAreas as $area)
                        <li>
                            <a href="{{ route('pm-status.show', $area->slug) }}" @click="menu = false"
                                class="flex min-h-12 items-center font-medium text-text hover:text-primary">
                                PM Status, {{ $area->name }}
                            </a>
                        </li>
                    @endforeach
                @endif

                @foreach ($links as $link)
                    <li>
                        <a href="{{ $link['href'] }}" @click="menu = false"
                            class="flex min-h-12 items-center font-medium text-text hover:text-primary">
                            {{ $link['label'] }}
                        </a>
                    </li>
                @endforeach

                @if ($isAdmin)
                    <li>
                        <a href="{{ route('maintenance-intelligence.index') }}"
                            class="flex min-h-12 items-center font-medium text-text hover:text-primary">
                            Maintenance Intelligence
                        </a>
                    </li>
                @endif

                @guest
                    <li class="sm:hidden">
                        <a href="{{ route('login') }}"
                            class="flex min-h-12 items-center font-semibold text-primary">
                            Sign in
                        </a>
                    </li>
                @else
                    <li>
                        <a href="{{ route('profile.edit') }}"
                            class="flex min-h-12 items-center font-medium text-text hover:text-primary">
                            {{ __('Settings') }}
                        </a>
                    </li>
                    <li>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit"
                                class="flex min-h-12 w-full items-center font-medium text-text hover:text-primary">
                                {{ __('Log out') }}
                            </button>
                        </form>
                    </li>
                @endguest

            </ul>

        </div>
    </div>

</header>
