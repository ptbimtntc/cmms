<header
    class="sticky top-0 z-30 border-b border-slate-200 bg-white/95 px-4 py-4 shadow-sm backdrop-blur sm:px-6 lg:ml-72">
    <div class="flex flex-row items-center justify-between gap-3">
        <div class="flex min-w-0 items-center gap-4">
            {{-- Tombol Hamburger: cuma tampil di mobile --}}
            <button @click="$store.sidebar.open = true"
                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-slate-600 transition hover:bg-slate-100 hover:text-slate-900 lg:hidden"
                title="Open Sidebar">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5">
                    <path d="M4 6h16" />
                    <path d="M4 12h16" />
                    <path d="M4 18h16" />
                </svg>
            </button>

            {{-- Separator: cuma tampil bareng hamburger (mobile) --}}
            <div class="h-6 w-px shrink-0 bg-slate-200 lg:hidden"></div>

            <div class="min-w-0">
                <p class="text-xs font-semibold uppercase tracking-[0.25em] text-slate-500">Maintenance Free</p>
                <h2 class="truncate text-xl font-semibold text-slate-800">{{ $pageTitle ?? 'Dashboard' }}</h2>
            </div>
        </div>

        <div class="flex shrink-0 items-center justify-end gap-3" x-data="{ open: false }">
            <div class="relative">
                <button
                    type="button"
                    @click="open = !open"
                    class="block rounded-full focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                    title="Account menu"
                >
                    @if (auth()->user()->avatar_path)
                        <img
                            src="{{ auth()->user()->photo_url }}"
                            alt="{{ auth()->user()->name }}"
                            class="h-9 w-9 rounded-full object-cover"
                        >
                    @else
                        <div class="flex h-9 w-9 items-center justify-center rounded-full bg-slate-200 text-xs font-semibold text-slate-600">
                            {{ auth()->user()->initials() }}
                        </div>
                    @endif
                </button>

                <div
                    x-show="open"
                    x-cloak
                    @click.outside="open = false"
                    x-transition
                    class="absolute right-0 z-40 mt-2 w-56 rounded-xl border border-slate-200 bg-white py-2 shadow-lg"
                >
                    <div class="border-b border-slate-100 px-4 py-2">
                        <div class="text-sm font-medium text-slate-800">{{ auth()->user()->name }}</div>
                        <div class="text-xs text-slate-500">{{ auth()->user()->role }}</div>
                    </div>

                    <a
                        href="{{ route('profile.edit') }}"
                        class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-100"
                    >
                        Edit Profile
                    </a>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button
                            type="submit"
                            class="block w-full px-4 py-2 text-left text-sm text-red-600 hover:bg-red-50"
                        >
                            Logout
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</header>