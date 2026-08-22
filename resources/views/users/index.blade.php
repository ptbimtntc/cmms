@extends('layouts.app')

@section('content')
<div class="mb-6 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
    <div>
        <h1 class="text-2xl font-semibold text-slate-800">User Management</h1>
        <p class="text-sm text-slate-500">Manage system user access</p>
    </div>

    <a href="{{ route('users.create') }}"
        class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-700">
        + Add User
    </a>
</div>

@if(session('success'))
<div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
    {{ session('success') }}
</div>
@endif

{{-- ============ DESKTOP: Table (md and up) ============ --}}
<div class="hidden overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm md:block">
    <div class="overflow-x-auto">
    <table class="min-w-full divide-y divide-slate-200">
        <thead class="bg-slate-50">
            <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Photo</th>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Name</th>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Email</th>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Role</th>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                    Status
                </th>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Action
                </th>

            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 bg-white">
            @foreach($users as $user)
            <tr class="hover:bg-slate-50">
                <td class="px-4 py-3">
                    @if ($user->avatar_path)
                        <img src="{{ $user->photo_url }}" alt="{{ $user->name }}" class="h-8 w-8 rounded-full object-cover">
                    @else
                        <div class="flex h-8 w-8 items-center justify-center rounded-full bg-slate-200 text-xs font-semibold text-slate-600">
                            {{ $user->initials() }}
                        </div>
                    @endif
                </td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ $user->name }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ $user->email }}</td>
                <td class="px-4 py-3">
                    <span class="rounded-full bg-blue-100 px-2.5 py-1 text-xs font-semibold text-blue-700">
                        {{ $user->role }}
                    </span>
                </td>
                <td class="px-4 py-3">
                    @if ($user->is_active)
                    <span class="inline-flex rounded-full bg-green-100 px-2.5 py-1 text-xs font-medium text-green-700">
                        Active
                    </span>
                    @else
                    <span class="inline-flex rounded-full bg-red-100 px-2.5 py-1 text-xs font-medium text-red-700">
                        Inactive
                    </span>
                    @endif
                </td>
                <td class="px-4 py-3">
                    <div class="flex items-center gap-2">
                        <a href="{{ route('users.edit', $user) }}"
                            class="rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-200">
                            Edit
                        </a>

                        @if (auth()->id() !== $user->id)
                            <form action="{{ route('users.destroy', $user) }}" method="POST"
                                onsubmit="return confirm('Delete this user? This action cannot be undone.')">
                                @csrf
                                @method('DELETE')
                                <button type="submit"
                                    class="rounded-lg bg-red-50 px-3 py-1.5 text-xs font-medium text-red-600 hover:bg-red-100">
                                    Delete
                                </button>
                            </form>
                        @endif
                    </div>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    </div>
</div>

{{-- ============ MOBILE: Card List (below md) ============ --}}
<div class="space-y-3 md:hidden">
    @forelse ($users as $user)
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex items-start justify-between gap-3">
                <div class="flex min-w-0 items-center gap-3">
                    @if ($user->avatar_path)
                        <img src="{{ $user->photo_url }}" alt="{{ $user->name }}" class="h-10 w-10 shrink-0 rounded-full object-cover">
                    @else
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-slate-200 text-xs font-semibold text-slate-600">
                            {{ $user->initials() }}
                        </div>
                    @endif
                    <div class="min-w-0">
                        <div class="truncate text-sm font-semibold text-slate-800">{{ $user->name }}</div>
                        <div class="truncate text-xs text-slate-500">{{ $user->email }}</div>
                    </div>
                </div>
                @if ($user->is_active)
                    <span class="shrink-0 rounded-full bg-green-100 px-2.5 py-1 text-xs font-medium text-green-700">Active</span>
                @else
                    <span class="shrink-0 rounded-full bg-red-100 px-2.5 py-1 text-xs font-medium text-red-700">Inactive</span>
                @endif
            </div>
            <div class="mt-3 flex items-center justify-between border-t border-slate-100 pt-3">
                <span class="rounded-full bg-blue-100 px-2.5 py-1 text-xs font-semibold text-blue-700">{{ $user->role }}</span>
                <div class="flex items-center gap-2">
                    <a href="{{ route('users.edit', $user) }}"
                        class="rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-200">
                        Edit
                    </a>

                    @if (auth()->id() !== $user->id)
                        <form action="{{ route('users.destroy', $user) }}" method="POST"
                            onsubmit="return confirm('Delete this user? This action cannot be undone.')">
                            @csrf
                            @method('DELETE')
                            <button type="submit"
                                class="rounded-lg bg-red-50 px-3 py-1.5 text-xs font-medium text-red-600 hover:bg-red-100">
                                Delete
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    @empty
        <p class="rounded-2xl border border-slate-200 bg-white p-6 text-center text-sm text-slate-500">No users found</p>
    @endforelse
</div>

<div class="mt-4">
    {{ $users->links() }}
</div>
@endsection