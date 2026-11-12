<header class="sticky top-0 z-30 border-b border-border bg-surface px-3 py-2 sm:px-6 lg:ml-72">
    <div class="flex min-h-12 flex-row items-center justify-between gap-2 sm:gap-3">
        <div class="flex min-w-0 items-center gap-1 sm:gap-3">
            {{-- Below lg the sidebar is a drawer; a labelled button keeps it discoverable. --}}
            <button type="button" @click="$store.sidebar.open = true"
                :aria-expanded="$store.sidebar.open.toString()" aria-controls="app-sidebar"
                class="flex h-11 shrink-0 items-center gap-2 rounded-lg px-2.5 text-sm font-medium text-text-muted transition-colors hover:bg-surface-muted hover:text-text active:bg-border lg:hidden">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5" aria-hidden="true">
                    <path d="M4 6h16" />
                    <path d="M4 12h16" />
                    <path d="M4 18h16" />
                </svg>
                <span class="hidden min-[400px]:inline">Menu</span>
                <span class="sr-only min-[400px]:hidden">Menu</span>
            </button>

            <p class="min-w-0 truncate font-signage text-xl font-semibold leading-tight text-text sm:text-2xl">
                {{ $pageTitle ?? 'Dashboard' }}
            </p>
        </div>

        <div class="flex shrink-0 items-center justify-end gap-1 sm:gap-3">
            @include('partials.sync-status')

            <div class="relative" x-data="{ open: false }" @keydown.escape.window="open = false">
                <button
                    type="button"
                    @click="open = !open"
                    :aria-expanded="open.toString()"
                    aria-haspopup="true"
                    aria-label="Account menu"
                    class="flex h-11 w-11 items-center justify-center rounded-full transition hover:bg-surface-muted focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
                >
                    @if (auth()->user()->avatar_path)
                        <img
                            src="{{ auth()->user()->photo_url }}"
                            alt=""
                            width="36" height="36"
                            class="h-9 w-9 rounded-full object-cover"
                        >
                    @else
                        <span class="flex h-9 w-9 items-center justify-center rounded-full bg-primary-light text-xs font-semibold text-primary-hover" aria-hidden="true">
                            {{ auth()->user()->initials() }}
                        </span>
                    @endif
                </button>

                <div
                    x-show="open"
                    x-cloak
                    @click.outside="open = false"
                    x-transition.opacity
                    class="absolute right-0 z-40 mt-2 w-60 rounded-card border border-border bg-surface py-2 shadow-popover"
                >
                    <div class="border-b border-border px-4 pb-2 pt-1">
                        <div class="truncate text-sm font-semibold text-text">{{ auth()->user()->name }}</div>
                        <div class="text-xs text-text-muted">{{ auth()->user()->role }}</div>
                    </div>

                    <a
                        href="{{ route('profile.edit') }}"
                        class="flex min-h-11 items-center px-4 text-sm text-text hover:bg-surface-muted active:bg-border"
                    >
                        Edit Profile
                    </a>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button
                            type="submit"
                            class="flex min-h-11 w-full items-center px-4 text-left text-sm font-medium text-danger hover:bg-danger-light active:bg-danger-light"
                        >
                            Logout
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</header>
