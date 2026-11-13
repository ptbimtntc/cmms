<?php

namespace App\Http\Controllers\MaintenanceIntelligence;

use App\Http\Controllers\Controller;
use App\Models\AiCharacterRun;
use App\Models\User;
use App\Models\VmeCharacterProfile;
use App\Services\Vme\Ai\AiCharacterRegistry;
use App\Services\Vme\VmeFeature;
use App\Services\Vme\VmeSnapshotService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * VME management inside the Maintenance Intelligence Center (ADMIN only —
 * enforced by the route group): feature switches, character avatars and
 * diagnostics. Map layout is fully automatic; there is nothing to position.
 */
class VmeAdminController extends Controller
{
    public function index(VmeSnapshotService $snapshots, AiCharacterRegistry $ai): View
    {
        return view('maintenance-intelligence.vme', [
            'killed' => VmeFeature::killed(),
            'features' => VmeFeature::stored(),
            'effective' => VmeFeature::all(),
            'pics' => User::query()->where('role', User::ROLE_PIC)->where('is_active', true)
                ->with('area:id,name')->orderBy('name')->get(['id', 'name', 'area_id']),
            'profiles' => VmeCharacterProfile::query()->pluck('avatar_key', 'user_id'),
            'avatars' => config('vme.avatars'),
            'diagnostics' => $snapshots->diagnostics(),
            'aiCharacters' => collect($ai->all())->map(fn (array $d, string $key) => $d + ['enabled' => VmeFeature::characterEnabled($key)])->all(),
        ]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        foreach (VmeFeature::KEYS as $key) {
            VmeFeature::set($key, $request->boolean($key), $request->user()->id);
        }

        // Each AI character has its own switch (default ON); only keys that exist can be set.
        if ($request->boolean('characters_submitted')) {
            foreach (array_keys(app(AiCharacterRegistry::class)->all()) as $character) {
                VmeFeature::setCharacter($character, $request->boolean('character_'.$character), $request->user()->id);
            }
        }

        return back()->with('success', 'VME settings saved.');
    }

    public function updateAvatar(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->role === User::ROLE_PIC, 404);

        $data = $request->validate([
            'avatar_key' => ['required', Rule::in(array_keys(config('vme.avatars')))],
        ]);

        VmeCharacterProfile::updateOrCreate(['user_id' => $user->id], $data);
        VmeSnapshotService::flush();

        return back()->with('success', 'Avatar '.$user->name.' disimpan.');
    }

    /** Everything the AI characters produced, newest first, filterable by character and date. */
    public function aiRuns(Request $request, AiCharacterRegistry $ai): View
    {
        $data = $request->validate([
            'character' => ['nullable', 'string', 'max:40'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $runs = AiCharacterRun::query()
            ->when($data['character'] ?? null, fn ($q, $c) => $q->where('character_key', $c))
            ->when($data['from'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($data['to'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '<=', $d))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('maintenance-intelligence.vme-ai-runs', [
            'runs' => $runs,
            'characters' => collect($ai->all())->map(fn (array $d) => $d['name'])->all(),
            'filters' => ['character' => $data['character'] ?? '', 'from' => $data['from'] ?? '', 'to' => $data['to'] ?? ''],
        ]);
    }
}
