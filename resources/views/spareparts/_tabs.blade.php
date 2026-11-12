{{-- Sparepart Master / Injection switch. PIC only has the master list, so no tab bar is shown to them. --}}
@php($tabUser = auth()->user())
@if ($tabUser->isAdmin() || $tabUser->isKoordinator() || $tabUser->isSupervisor())
    <nav class="mb-5 flex overflow-x-auto border-b border-border" aria-label="Sparepart">
        @foreach (['spareparts.index' => ['spareparts.*', 'Sparepart Master'], 'sparepart-injection.index' => ['sparepart-injection.*', 'Sparepart Injection']] as $routeName => [$pattern, $text])
            @php($active = request()->routeIs($pattern))
            <a href="{{ route($routeName) }}" @if ($active) aria-current="page" @endif
                @class([
                    '-mb-px inline-flex min-h-11 flex-1 items-center justify-center whitespace-nowrap border-b-2 px-3 text-sm font-semibold transition-colors sm:flex-none',
                    'border-primary text-primary-hover' => $active,
                    'border-transparent text-text-muted hover:border-border-strong hover:text-text' => ! $active,
                ])>{{ $text }}</a>
        @endforeach
    </nav>
@endif
