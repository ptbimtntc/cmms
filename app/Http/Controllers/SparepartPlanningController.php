<?php

namespace App\Http\Controllers;

use App\Exports\SparepartPlanningExport;
use App\Models\Machine;
use App\Models\MandatorySparepart;
use App\Models\Sparepart;
use App\Models\User;
use App\Services\SparepartPlanningService;
use App\Support\AreaAuthorizationScope;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Sparepart Planning: "Mandatory Sparepart" (configuration per machine type,
 * ADMIN/KOORDINATOR manage, SUPERVISOR read-only) and "Estimasi Kebutuhan"
 * (plan vs actual, scoped by the shared role/area rule). PIC has no access
 * (route role middleware + the explicit checks below).
 */
class SparepartPlanningController extends Controller
{
    public function index(Request $request, SparepartPlanningService $service)
    {
        $user = $this->viewer($request);
        $tab = $request->query('tab') === 'estimate' ? 'estimate' : 'mandatory';
        $machineTypes = $this->visibleMachineTypes($user);

        $data = [
            'tab' => $tab,
            'canManage' => $this->canManage($user),
            'machineTypes' => $machineTypes,
        ];

        if ($tab === 'mandatory') {
            $data['mandatories'] = MandatorySparepart::query()
                ->with('sparepart')
                ->whereIn('machine_type', $machineTypes)
                ->orderBy('machine_type')
                ->get()
                ->sortBy(fn ($m) => $m->machine_type.'|'.$m->sparepart->material_number, SORT_NATURAL)
                ->groupBy('machine_type');
            $data['spareparts'] = $this->canManage($user)
                ? Sparepart::orderBy('material_number')->get(['id', 'material_number', 'description', 'unit'])
                : collect();

            return view('sparepart-planning.index', $data);
        }

        $filters = $this->filters($request, $user, $machineTypes);

        return view('sparepart-planning.index', $data + $filters + [
            'areas' => $user->seesAllAreas() ? $user->selectableAreaNames() : collect(),
            'years' => range((int) now()->format('Y') + 1, (int) now()->format('Y') - 5),
            'spareparts' => Sparepart::orderBy('material_number')->get(['id', 'material_number', 'description', 'unit']),
        ] + $this->orderEstimate($request, $user, $filters, $service));
    }

    public function export(Request $request, SparepartPlanningService $service)
    {
        $user = $this->viewer($request);
        $filters = $this->filters($request, $user, $this->visibleMachineTypes($user));

        abort_unless($filters['machineType'], 422, 'Pick a machine type first.');

        $estimate = $this->orderEstimate($request, $user, $filters, $service)['estimate'];

        $filename = sprintf(
            'sparepart-order-estimation-%s-%s-%s.xlsx',
            Str::slug($filters['machineType']),
            $filters['year'],
            $filters['month'] ? str_pad((string) $filters['month'], 2, '0', STR_PAD_LEFT) : 'all',
        );

        $period = ($filters['month'] ? Carbon::create(null, $filters['month'], 1)->format('F') : 'All months').' '.$filters['year'];

        return Excel::download(new SparepartPlanningExport($filters['machineType'], $period, $estimate), $filename);
    }

    /**
     * Builds the stateless order estimate from the submitted form: applies
     * add/remove of manual items, drops corrections when the filter scope
     * changed (they belonged to another type/period), and computes rows.
     */
    private function orderEstimate(Request $request, User $user, array $filters, SparepartPlanningService $service): array
    {
        if (! $filters['machineType']) {
            return ['estimate' => null, 'usageReference' => null, 'messages' => []];
        }

        $scope = implode('|', [$filters['machineType'], $filters['year'], $filters['month'], $filters['area']]);
        $sameScope = $request->input('prev_scope') === null || $request->input('prev_scope') === $scope;

        $state = $sameScope ? [
            'machines_final' => $request->input('machines_final'),
            'qty' => (array) $request->input('qty', []),
            'orig' => (array) $request->input('orig', []),
            'manual' => (array) $request->input('manual', []),
            'reset' => $request->input('reset'),
        ] : [];
        $messages = $sameScope ? [] : ['The filters changed: earlier corrections and manual items were dropped.'];

        if ($request->filled('remove')) {
            unset($state['manual'][$request->input('remove')]);
        }

        if ($request->filled('add_sparepart_id')) {
            $id = (int) $request->input('add_sparepart_id');
            $qty = $request->input('add_qty', '1');
            $part = Sparepart::find($id);
            $isMandatory = MandatorySparepart::where('machine_type', $filters['machineType'])->where('sparepart_id', $id)->exists();

            if (! $part) {
                $messages[] = 'Sparepart not found.';
            } elseif ($isMandatory) {
                $messages[] = "{$part->material_number} is already a Mandatory part; change the quantity on the existing row.";
            } elseif (isset($state['manual'][$id])) {
                $messages[] = "{$part->material_number} is already in the list; change the quantity on that row.";
            } elseif (! (is_string($qty) && ctype_digit($qty) && strlen($qty) <= 9)) {
                $messages[] = 'The extra sparepart qty must be a whole number of 0 or more.';
            } else {
                $state['manual'][$id] = $qty;
            }
        }

        $estimate = $service->estimateOrder($user, $filters['year'], $filters['month'], $filters['machineType'], $filters['area'], $state);

        return [
            'estimate' => $estimate,
            'usageReference' => $service->usageReference($user, $filters['year'], $filters['month'], $filters['machineType'], $filters['area']),
            'messages' => array_merge($messages, $estimate['errors']),
            'scope' => $scope,
        ];
    }

    public function store(Request $request)
    {
        $user = $this->manager($request);

        $validated = $request->validate([
            'machine_type' => ['required', 'string', Rule::in($this->visibleMachineTypes($user))],
            'sparepart_id' => [
                'required',
                'integer',
                Rule::exists('spareparts', 'id'),
                Rule::unique('mandatory_spareparts', 'sparepart_id')
                    ->where('machine_type', $request->input('machine_type')),
            ],
            'qty' => ['required', 'integer', 'min:1', 'max:1000000'],
        ], [
            'sparepart_id.unique' => 'This sparepart is already mandatory for the selected machine type.',
        ]);

        MandatorySparepart::create($validated);

        return redirect()
            ->route('sparepart-planning.index', ['tab' => 'mandatory'])
            ->with('success', 'Mandatory sparepart added.');
    }

    public function edit(Request $request, MandatorySparepart $mandatorySparepart)
    {
        $user = $this->manager($request);
        $this->authorizeConfig($user, $mandatorySparepart);

        return view('sparepart-planning.edit', [
            'mandatory' => $mandatorySparepart->load('sparepart'),
            'machineTypes' => $this->visibleMachineTypes($user),
            'spareparts' => Sparepart::orderBy('material_number')->get(['id', 'material_number', 'description', 'unit']),
        ]);
    }

    public function update(Request $request, MandatorySparepart $mandatorySparepart)
    {
        $user = $this->manager($request);
        $this->authorizeConfig($user, $mandatorySparepart);

        $validated = $request->validate([
            'machine_type' => ['required', 'string', Rule::in($this->visibleMachineTypes($user))],
            'sparepart_id' => [
                'required',
                'integer',
                Rule::exists('spareparts', 'id'),
                Rule::unique('mandatory_spareparts', 'sparepart_id')
                    ->where('machine_type', $request->input('machine_type'))
                    ->ignore($mandatorySparepart->id),
            ],
            'qty' => ['required', 'integer', 'min:1', 'max:1000000'],
        ], [
            'sparepart_id.unique' => 'This sparepart is already mandatory for the selected machine type.',
        ]);

        $mandatorySparepart->update($validated);

        return redirect()
            ->route('sparepart-planning.index', ['tab' => 'mandatory'])
            ->with('success', 'Mandatory sparepart updated.');
    }

    public function destroy(Request $request, MandatorySparepart $mandatorySparepart)
    {
        $user = $this->manager($request);
        $this->authorizeConfig($user, $mandatorySparepart);

        $mandatorySparepart->delete();

        return redirect()
            ->route('sparepart-planning.index', ['tab' => 'mandatory'])
            ->with('success', 'Mandatory sparepart removed.');
    }

    private function viewer(Request $request): User
    {
        $user = $request->user();
        abort_unless($user->isAdmin() || $user->isKoordinator() || $user->isSupervisor(), 403);

        return $user;
    }

    private function manager(Request $request): User
    {
        $user = $request->user();
        abort_unless($this->canManage($user), 403);

        return $user;
    }

    private function canManage(User $user): bool
    {
        return $user->isAdmin() || $user->isKoordinator();
    }

    /**
     * A KOORDINATOR may only touch configuration of machine types that
     * exist in their own area.
     */
    private function authorizeConfig(User $user, MandatorySparepart $config): void
    {
        abort_unless(in_array($config->machine_type, $this->visibleMachineTypes($user), true), 403);
    }

    /**
     * Valid machine types = those actually used by machines in FreeDOMS,
     * narrowed to the viewer's area scope.
     *
     * @return list<string>
     */
    private function visibleMachineTypes(User $user): array
    {
        $query = Machine::query()
            ->whereNotNull('machine_type')
            ->where('machine_type', '!=', '');

        AreaAuthorizationScope::apply($query, $user, 'area');

        return $query->distinct()->orderBy('machine_type')->pluck('machine_type')->all();
    }

    private function filters(Request $request, User $user, array $machineTypes): array
    {
        $year = (int) $request->input('year', now()->format('Y'));
        $year = ($year >= 2000 && $year <= 2100) ? $year : (int) now()->format('Y');

        $month = $request->filled('month') ? (int) $request->input('month') : (int) now()->format('n');
        $month = ($month >= 1 && $month <= 12) ? $month : null;

        $machineType = $request->input('machine_type');
        $machineType = in_array($machineType, $machineTypes, true) ? $machineType : null;

        $area = $user->seesAllAreas() && $user->selectableAreaNames()->contains($request->input('area'))
            ? $request->input('area')
            : null;

        return compact('year', 'month', 'machineType', 'area');
    }
}
