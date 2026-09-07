<header
    class="sticky top-0 z-30 border-b border-border bg-surface/95 px-4 py-4 shadow-sm backdrop-blur sm:px-6 lg:ml-72">
    <div class="flex flex-row items-center justify-between gap-3">
        <div class="flex min-w-0 items-center gap-4">
            {{-- Tombol Hamburger: cuma tampil di mobile --}}
            <button @click="$store.sidebar.open = true"
                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-text-muted transition hover:bg-surface-muted hover:text-text lg:hidden"
                title="Open Sidebar">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5">
                    <path d="M4 6h16" />
                    <path d="M4 12h16" />
                    <path d="M4 18h16" />
                </svg>
            </button>

            {{-- Separator: cuma tampil bareng hamburger (mobile) --}}
            <div class="h-6 w-px shrink-0 bg-border lg:hidden"></div>

            <div class="min-w-0">
                <p class="text-xs font-semibold uppercase tracking-[0.25em] text-text-muted">Maintenance Free</p>
                <h2 class="truncate text-xl font-semibold text-text">{{ $pageTitle ?? 'Dashboard' }}</h2>
            </div>
        </div>

        <div class="flex shrink-0 items-center justify-end gap-3" x-data="{ open: false }">
            <div class="relative">
                <button
                    type="button"
                    @click="open = !open"
                    class="block rounded-full focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2"
                    title="Account menu"
                >
                    @if (auth()->user()->avatar_path)
                        <img
                            src="{{ auth()->user()->photo_url }}"
                            alt="{{ auth()->user()->name }}"
                            class="h-9 w-9 rounded-full object-cover"
                        >
                    @else
                        <div class="flex h-9 w-9 items-center justify-center rounded-full bg-surface-muted text-xs font-semibold text-text-muted">
                            {{ auth()->user()->initials() }}
                        </div>
                    @endif
                </button>

                <div
                    x-show="open"
                    x-cloak
                    @click.outside="open = false"
                    x-transition
                    class="absolute right-0 z-40 mt-2 w-56 rounded-xl border border-border bg-surface py-2 shadow-lg"
                >
                    <div class="border-b border-border px-4 py-2">
                        <div class="text-sm font-medium text-text">{{ auth()->user()->name }}</div>
                        <div class="text-xs text-text-muted">{{ auth()->user()->role }}</div>
                    </div>

                    <a
                        href="{{ route('profile.edit') }}"
                        class="block px-4 py-2 text-sm text-text-muted hover:bg-surface-muted hover:text-text"
                    >
                        Edit Profile
                    </a>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button
                            type="submit"
                            class="block w-full px-4 py-2 text-left text-sm text-danger hover:bg-danger-light"
                        >
                            Logout
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</header>
