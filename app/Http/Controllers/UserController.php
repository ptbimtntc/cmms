<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index()
    {
        $users = User::with(['area', 'areas'])->latest()->paginate(20);

        return view('users.index', compact('users'));
    }

    public function create()
    {
        $roles = User::ROLES;
        $areas = Area::active()->orderBy('name')->get();

        return view('users.create', compact('roles', 'areas'));
    }

    public function store(Request $request)
    {
        $validated = $this->validated($request);

        $user = User::create([
            'name' => Str::title(strtolower(trim($validated['name']))),
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
            'area_id' => $validated['area_id'],
            'is_active' => true,
        ]);

        $user->areas()->sync($validated['area_ids']);

        return redirect()
            ->route('users.index')
            ->with('success', 'User created successfully.');
    }

    public function edit(User $user)
    {
        $roles = User::ROLES;
        $areas = Area::active()->orderBy('name')->get();

        return view('users.edit', compact('user', 'roles', 'areas'));
    }

    public function update(Request $request, User $user)
    {
        $isCurrentUser = auth()->id() === $user->id;

        // Admin tidak boleh mengubah role dirinya sendiri
        if ($isCurrentUser && $request->input('role') !== $user->role) {
            return back()
                ->withErrors([
                    'role' => 'You cannot change your own role.',
                ])
                ->withInput();
        }

        if ($isCurrentUser && ! $request->boolean('is_active')) {
            return back()
                ->withErrors([
                    'is_active' => 'You cannot deactivate your own account.',
                ])
                ->withInput();
        }

        $validated = $this->validated($request, $user);

        $data = [
            'name' => Str::title(strtolower(trim($validated['name']))),
            'email' => $validated['email'],
            'role' => $validated['role'],
            'area_id' => $validated['area_id'],
            'is_active' => $validated['is_active'],
        ];

        if (! empty($validated['password'])) {
            $data['password'] = Hash::make($validated['password']);
        }

        if ($request->hasFile('avatar')) {
            if ($user->avatar_path) {
                Storage::disk('public')->delete($user->avatar_path);
            }

            $data['avatar_path'] = $request->file('avatar')->store('avatars', 'public');
        } elseif ($request->boolean('remove_avatar') && $user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
            $data['avatar_path'] = null;
        }

        $user->update($data);
        $user->areas()->sync($validated['area_ids']);

        $message = ! empty($validated['password'])
            ? 'User updated successfully. Password has been changed.'
            : 'User updated successfully.';

        return redirect()
            ->route('users.index')
            ->with('success', $message);
    }

    public function destroy(User $user)
    {
        if (auth()->id() === $user->id) {
            return back()->withErrors([
                'delete' => 'You cannot delete your own account.',
            ]);
        }

        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
        }

        $user->delete();

        return redirect()
            ->route('users.index')
            ->with('success', 'User deleted successfully.');
    }

    /**
     * Shared store/update validation. Role and Area are validated
     * independently (Area sourced from the live master list, never
     * hardcoded), then Area is required exactly when the role needs one
     * (KOORDINATOR/PIC) and forced null otherwise (ADMIN accesses every
     * area; GUEST has no area, matching existing behaviour).
     *
     * @return array{name: string, email: string, password: ?string, role: string, area_id: ?int, is_active?: bool}
     */
    private function validated(Request $request, ?User $user = null): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user?->id),
            ],
            'password' => [$user ? 'nullable' : 'required', 'string', 'min:8'],
            'role' => ['required', Rule::in(User::ROLES)],
            'area_id' => [
                Rule::requiredIf(in_array($request->input('role'), [User::ROLE_KOORDINATOR, User::ROLE_PIC], true)),
                'nullable',
                Rule::exists('areas', 'id')->where('is_active', true),
            ],
        ];

        if ($user) {
            $rules['is_active'] = ['required', 'boolean'];
            $rules['avatar'] = ['nullable', 'image', 'max:2048'];
            $rules['remove_avatar'] = ['nullable', 'boolean'];
        }

        // SUPERVISOR may be given zero (= all areas), one or many areas.
        $rules['area_ids'] = ['nullable', 'array'];
        $rules['area_ids.*'] = ['integer', Rule::exists('areas', 'id')->where('is_active', true)];

        $validated = $request->validate($rules);

        // ADMIN, SUPERVISOR and GUEST never use the single area, regardless of
        // what the form submitted (Area field is hidden for those roles).
        $validated['area_id'] = in_array($validated['role'], [User::ROLE_KOORDINATOR, User::ROLE_PIC], true)
            ? $validated['area_id']
            : null;

        $validated['area_ids'] = $validated['role'] === User::ROLE_SUPERVISOR
            ? array_values(array_unique($validated['area_ids'] ?? []))
            : [];

        return $validated;
    }
}
