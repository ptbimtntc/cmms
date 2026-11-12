{{-- Round user photo, or initials when there is none. Params: user, size (Tailwind h/w classes). --}}
@if ($user->avatar_path)
    <img src="{{ $user->photo_url }}" alt="" class="{{ $size }} shrink-0 rounded-full object-cover">
@else
    <span class="{{ $size }} inline-flex shrink-0 items-center justify-center rounded-full bg-primary-light text-xs font-semibold text-primary-hover" aria-hidden="true">{{ $user->initials() }}</span>
@endif
