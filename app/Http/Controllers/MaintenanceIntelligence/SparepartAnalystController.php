<?php

namespace App\Http\Controllers\MaintenanceIntelligence;

use App\Exports\SparepartForecastExport;
use App\Http\Controllers\Controller;
use App\Models\AiForecastResult;
use App\Models\AiForecastRun;
use App\Models\Area;
use App\Models\Machine;
use App\Services\SparepartForecast\ForecastRunner;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

/**
 * AI Sparepart Analyst dashboard (ADMIN only, via the route group). Reads
 * only the ai_forecast_* tables; running an analysis reads PM history and
 * writes only to those tables.
 */
class SparepartAnalystController extends Controller
{
    public function index(Request $request)
    {
        $runs = AiForecastRun::with('user:id,name')->latest('id')->limit(30)->get();

        $run = $request->filled('run')
            ? AiForecastRun::findOrFail($request->integer('run'))
            : $runs->firstWhere('status', AiForecastRun::COMPLETED);

        $data = [
            'runs' => $runs,
            'run' => $run,
            'areas' => Area::orderBy('name')->pluck('name'),
            'machineTypes' => Machine::query()->distinct()->orderBy('machine_type')->pluck('machine_type'),
            'filters' => $request->only(['area', 'machine_type', 'machine', 'sparepart']),
        ];

        if ($run && $run->status === AiForecastRun::COMPLETED) {
            $data += $this->dashboard($run, $request);
        }

        return view('maintenance-intelligence.sparepart-analyst', $data);
    }

    public function run(Request $request)
    {
        $data = $request->validate([
            'period_type' => ['required', 'in:WEEK,MONTH'],
            'period_date' => ['required', 'date'],
            'area' => ['nullable', 'string', 'max:100'],
            'machine_type' => ['nullable', 'string', 'max:255'],
        ]);

        $run = app(ForecastRunner::class)->run(
            $request->user(),
            $data['period_type'],
            Carbon::parse($data['period_date']),
            $data['area'] ?? null,
            $data['machine_type'] ?? null,
        );

        return redirect()
            ->route('maintenance-intelligence.sparepart-analyst', ['run' => $run->id])
            ->with($run->status === AiForecastRun::COMPLETED ? 'success' : 'error',
                $run->status === AiForecastRun::COMPLETED ? 'Analisis forecast selesai.' : 'Analysis failed: '.$run->error);
    }

    public function export(AiForecastRun $run)
    {
        abort_unless($run->status === AiForecastRun::COMPLETED, 404);

        return Excel::download(new SparepartForecastExport($run), "sparepart-forecast-run-{$run->id}.xlsx");
    }

    private function dashboard(AiForecastRun $run, Request $request): array
    {
        $results = fn (): Builder => $this->filtered(AiForecastResult::where('run_id', $run->id), $request);

        $machines = $run->machines()
            ->when($request->filled('area'), fn ($q) => $q->where('area', $request->area))
            ->when($request->filled('machine_type'), fn ($q) => $q->where('machine_type', $request->machine_type))
            ->when($request->filled('machine'), fn ($q) => $q->where('machine_number', 'like', '%'.$request->machine.'%'));

        return [
            'totals' => [
                'recommended' => (int) $results()->sum('recommended_qty'),
                'likely_items' => (clone $results())->where('level', 'LIKELY')->count(),
                'machines_with_forecast' => (clone $results())->distinct('machine_id')->count('machine_id'),
            ],
            'forecast' => $results()->orderBy('machine_number')->orderByDesc('expected_qty')->paginate(20, ['*'], 'fpage')->withQueryString(),
            'insufficient' => (clone $machines)->where('status', 'INSUFFICIENT_DATA')->orderBy('machine_number')->paginate(20, ['*'], 'ipage')->withQueryString(),
            'bySparepart' => $results()
                ->select('sparepart_id', 'material_number', 'description', 'unit')
                ->selectRaw('COUNT(DISTINCT machine_id) as machines, SUM(usage_pm_count) as usage_pm, SUM(total_qty) as hist_qty, SUM(expected_qty) as expected, SUM(recommended_qty) as recommended')
                ->selectRaw("SUM(trend = 'INCREASING') as increasing, SUM(trend = 'DECREASING') as decreasing")
                ->groupBy('sparepart_id', 'material_number', 'description', 'unit')
                ->orderByDesc('recommended')->orderByDesc('expected')->limit(100)->get(),
            'byMachineType' => $results()
                ->select('machine_type')
                ->selectRaw('COUNT(DISTINCT machine_id) as machines, COUNT(DISTINCT sparepart_id) as parts, SUM(expected_qty) as expected, SUM(recommended_qty) as recommended')
                ->groupBy('machine_type')->orderByDesc('recommended')->get(),
            'machineStatusByType' => $run->machines()
                ->select('machine_type')
                ->selectRaw("SUM(status = 'SUFFICIENT') as sufficient, SUM(status = 'INSUFFICIENT_DATA') as insufficient, SUM(upcoming_pm_count) as upcoming")
                ->groupBy('machine_type')->get()->keyBy('machine_type'),
            'byArea' => $results()
                ->select('area')
                ->selectRaw('COUNT(DISTINCT machine_id) as machines, SUM(expected_qty) as expected, SUM(recommended_qty) as recommended')
                ->groupBy('area')->orderBy('area')->get(),
            'evaluation' => $run->evaluation,
            'recommendations' => $run->recommendations()->orderByRaw("severity = 'WARNING' desc")->orderBy('id')->limit(200)->get(),
        ];
    }

    private function filtered(Builder $query, Request $request): Builder
    {
        return $query
            ->when($request->filled('area'), fn ($q) => $q->where('area', $request->area))
            ->when($request->filled('machine_type'), fn ($q) => $q->where('machine_type', $request->machine_type))
            ->when($request->filled('machine'), fn ($q) => $q->where('machine_number', 'like', '%'.$request->machine.'%'))
            ->when($request->filled('sparepart'), fn ($q) => $q->where(fn ($w) => $w
                ->where('material_number', 'like', '%'.$request->sparepart.'%')
                ->orWhere('description', 'like', '%'.$request->sparepart.'%')));
    }
}
