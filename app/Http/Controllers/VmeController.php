<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Models\Machine;
use App\Models\User;
use App\Services\Vme\Ai\AiCharacterService;
use App\Services\Vme\Ai\AiContext;
use App\Services\Vme\VmeAccess;
use App\Services\Vme\VmeDetailService;
use App\Services\Vme\VmeFeature;
use App\Services\Vme\VmeLayout;
use App\Services\Vme\VmeSnapshotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Virtual Maintenance Environment — read-only viewer endpoints. Behind
 * auth + role middleware (not public like /monitor); every handler also
 * checks the feature switch/kill switch, so VME OFF = 404 here and nothing
 * else in FreeDOMS is affected.
 */
class VmeController extends Controller
{
    public function index(Request $request): View
    {
        $this->ensureEnabled($request);

        $filter = $this->areaFilter($request, $request->user());
        $allowed = VmeAccess::allowedAreaIds($request->user());

        return view('vme.index', [
            // The Area filter at the top of the map: only Areas the viewer may see.
            'areaOptions' => Area::active()->orderBy('name')->get(['id', 'name', 'slug'])
                ->filter(fn (Area $a) => $allowed === null || in_array($a->id, $allowed, true))->values(),
            'selectedArea' => $filter ? Area::find($filter)?->slug : null,
            'snapshotUrl' => route('vme.snapshot'),
            'configUrl' => route('vme.config'),
            'picUrl' => route('vme.pic', '__ID__'),
            'machineUrl' => route('vme.machine', '__ID__'),
            'aiUrl' => route('vme.ai', '__ID__'),
        ]);
    }

    public function snapshot(Request $request, VmeSnapshotService $service): JsonResponse|Response
    {
        $user = $this->ensureEnabled($request);

        $payload = $service->forViewer(VmeAccess::allowedAreaIds($user), $user->isAdmin(), $this->areaFilter($request, $user));

        return $this->respond($request, $payload, $payload['version']);
    }

    public function config(Request $request): JsonResponse|Response
    {
        $user = $this->ensureEnabled($request);

        $payload = [
            'features' => VmeFeature::all(),
            'poll_seconds' => (int) config('vme.poll_seconds'),
            'stale_after_seconds' => (int) config('vme.stale_after_seconds'),
            'patrol_seconds' => (int) config('vme.patrol_seconds'),
            'ai' => [
                'wander_min_seconds' => (int) config('vme.ai.wander_min_seconds'),
                'wander_max_seconds' => (int) config('vme.ai.wander_max_seconds'),
                'wander_zone_types' => config('vme.ai.wander_zone_types'),
                'visit_spots' => config('vme.ai.visit_spots'),
            ],
            'layout' => VmeLayout::make($this->areaFilter($request, $user))->toArray(),
            'avatars' => collect(config('vme.avatars') + config('vme.ai_avatars', []))->map(fn ($a) => collect($a)->except('label')->all())->all(),
        ];

        return $this->respond($request, $payload, sha1(json_encode($payload)));
    }

    /** Detail of one PIC for the side panel. Only PICs of the viewer's own scope. */
    public function pic(Request $request, User $user, VmeDetailService $details): JsonResponse
    {
        $viewer = $this->ensureEnabled($request);

        abort_unless($user->role === User::ROLE_PIC && $user->isActive(), 404);
        $this->authorizeArea($viewer, $user->area_id);

        return $this->noStore(response()->json($details->pic($user->loadMissing('area:id,name'), $viewer)));
    }

    /** Detail of one machine for the side panel. Only machines of the viewer's own scope. */
    public function machine(Request $request, Machine $machine, VmeDetailService $details): JsonResponse
    {
        $viewer = $this->ensureEnabled($request);

        $this->authorizeArea($viewer, $details->areaIdForMachine($machine));

        return $this->noStore(response()->json($details->machine($machine)));
    }

    /**
     * Panel of an AI character. Floor-wide summaries are for viewers who see every area; a viewer
     * limited to some areas gets the character and its status only.
     */
    public function ai(Request $request, string $key, AiCharacterService $ai, VmeSnapshotService $snapshots): JsonResponse
    {
        $viewer = $this->ensureEnabled($request);

        $detail = $ai->detail($key, function () use ($snapshots) {
            $snapshot = $snapshots->cached();

            return new AiContext(
                pics: array_map(fn (array $p) => ['user_id' => $p['user_id'], 'status' => $p['status']], $snapshot['pics']),
                activitySync: (bool) $snapshot['sync']['enabled'],
                unmapped: (int) ($snapshot['sync']['unmapped_count'] ?? 0),
            );
        });

        abort_if($detail === null, 404);

        if (VmeAccess::allowedAreaIds($viewer) !== null) {
            $detail = array_merge($detail, ['summary' => null, 'details' => [], 'restricted' => true]);
        }

        $detail['link'] = $viewer->isAdmin()
            ? ['label' => 'Open the AI result log', 'url' => route('maintenance-intelligence.vme.ai-runs', ['character' => $key])]
            : null;

        return $this->noStore(response()->json($detail));
    }

    /** null area (a PIC without area, a machine whose area is not on the map) is for unrestricted viewers only. */
    private function authorizeArea(User $viewer, ?int $areaId): void
    {
        $allowed = VmeAccess::allowedAreaIds($viewer);

        abort_unless($allowed === null || ($areaId !== null && in_array($areaId, $allowed, true)), 403, 'Unauthorized');
    }

    /**
     * The Area chosen in the map's filter (?area=<slug>), or null for all. An unknown Area is 404;
     * one outside the viewer's scope is 403, like every other VME read.
     */
    private function areaFilter(Request $request, User $viewer): ?int
    {
        $slug = trim((string) $request->query('area', ''));

        if ($slug === '') {
            return null;
        }

        $area = Area::active()->where('slug', $slug)->first();
        abort_if($area === null, 404);
        $this->authorizeArea($viewer, $area->id);

        return $area->id;
    }

    private function noStore(JsonResponse $response): JsonResponse
    {
        return $response->header('Cache-Control', 'private, no-store');
    }

    private function ensureEnabled(Request $request)
    {
        $user = $request->user();

        abort_unless($user && VmeAccess::canView($user), 403, 'Unauthorized');
        abort_unless(VmeFeature::enabled(VmeFeature::DISPLAY), 404);

        return $user;
    }

    /** JSON with an ETag; 304 when the client already holds this version. */
    private function respond(Request $request, array $payload, string $version): JsonResponse|Response
    {
        $etag = '"'.$version.'"';
        $headers = ['ETag' => $etag, 'Cache-Control' => 'private, no-cache'];

        if ($request->headers->get('If-None-Match') === $etag) {
            return response('', 304, $headers);
        }

        return response()->json($payload, 200, $headers);
    }
}
