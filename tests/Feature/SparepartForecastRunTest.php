<?php

use App\Models\AiForecastRun;
use App\Models\Machine;
use App\Models\MandatorySparepart;
use App\Models\PMSchedule;
use App\Models\PMSparepart;
use App\Models\Sparepart;
use App\Models\User;
use App\Services\SparepartForecast\ForecastRunner;
use Illuminate\Support\Carbon;

function fxMachine(string $type = 'NDE', string $area = 'WWD'): Machine
{
    return Machine::create(['machine_number' => 'FX-'.uniqid(), 'area' => $area, 'machine_type' => $type, 'status' => 'ACTIVE']);
}

function fxPart(string $material): Sparepart
{
    return Sparepart::create(['material_number' => $material, 'description' => 'Part '.$material, 'unit' => 'PCS']);
}

function fxPm(Machine $m, string $date, string $status = 'FINISHED'): PMSchedule
{
    return PMSchedule::create([
        'machine_id' => $m->id, 'machine_number' => $m->machine_number, 'machine_type' => $m->machine_type, 'area' => $m->area,
        'order_number' => 'ORD-'.uniqid(), 'plan_date' => $date, 'plan_month' => date('F', strtotime($date)), 'plan_year' => date('Y', strtotime($date)),
        'actual_date' => in_array($status, PMSchedule::DONE_STATUSES) ? $date : null, 'status' => $status,
    ]);
}

function fxUse(PMSchedule $pm, Sparepart $part, int $qty, ?string $source = null): void
{
    PMSparepart::create(['pm_schedule_id' => $pm->id, 'sparepart_id' => $part->id, 'qty' => $qty, 'source' => $source]);
}

function fxRun(array $args = []): AiForecastRun
{
    return app(ForecastRunner::class)->run(User::factory()->admin()->create(), 'MONTH', Carbon::parse('2026-11-10'), $args['area'] ?? null, $args['type'] ?? null);
}

it('forecasts from PM usage only, ignoring mandatory and injected data', function () {
    $m = fxMachine();
    $used = fxPart('SAP-USED');
    $mandatoryOnly = fxPart('SAP-MANDATORY');
    $injected = fxPart('SAP-INJECTED');
    MandatorySparepart::create(['machine_type' => 'NDE', 'sparepart_id' => $mandatoryOnly->id, 'qty' => 9]);

    foreach (['2026-07-01', '2026-08-01', '2026-09-01', '2026-10-01'] as $i => $date) {
        $pm = fxPm($m, $date);
        if ($i < 3) {
            fxUse($pm, $used, 2);
        }
    }
    // A PM carrying warehouse-injected usage is excluded as a whole.
    $mixed = fxPm($m, '2026-10-15');
    fxUse($mixed, $injected, 7, PMSparepart::SOURCE_WAREHOUSE);
    // Two PMs scheduled in the forecast month.
    fxPm($m, '2026-11-05', 'OPEN');
    fxPm($m, '2026-11-20', 'OPEN');

    $run = fxRun();
    $parts = $run->results()->pluck('material_number')->all();
    $row = $run->results()->where('material_number', 'SAP-USED')->first();

    expect($run->status)->toBe('COMPLETED')
        ->and($parts)->toBe(['SAP-USED'])
        ->and($row->usage_pm_count)->toBe(3)->and($row->pm_considered)->toBe(4)
        ->and($row->probability)->toBe(0.75)
        ->and($row->upcoming_pm_count)->toBe(2)
        ->and($row->expected_qty)->toBe(3.0)->and($row->recommended_qty)->toBe(3)
        ->and($run->summary['quality']['finished_pm_excluded_warehouse'])->toBe(1)
        ->and($run->machines->first()->excluded_pm_count)->toBe(1)
        ->and($run->data_fingerprint)->toHaveLength(64)
        ->and($run->evaluation->status)->toBe('NOT_AVAILABLE');
});

it('marks machines without enough history and invents nothing for them', function () {
    $m = fxMachine();
    MandatorySparepart::create(['machine_type' => 'NDE', 'sparepart_id' => fxPart('SAP-M')->id, 'qty' => 5]);
    fxUse(fxPm($m, '2026-10-01'), fxPart('SAP-X'), 1);
    fxPm($m, '2026-11-05', 'OPEN');

    $run = fxRun();

    expect($run->results()->count())->toBe(0)
        ->and($run->machines->first()->status)->toBe('INSUFFICIENT_DATA')
        ->and($run->machines->first()->note)->toContain('Insufficient Historical Data')
        ->and($run->summary['quality']['machines_insufficient'])->toBe(1);
});

it('keeps previous runs and does not touch operational tables', function () {
    $m = fxMachine();
    $p = fxPart('SAP-A');
    foreach (['2026-08-01', '2026-09-01', '2026-10-01'] as $d) {
        fxUse(fxPm($m, $d), $p, 1);
    }
    $before = [PMSparepart::count(), PMSchedule::count(), PMSparepart::sum('qty'), PMSchedule::pluck('status')->all()];

    $first = fxRun();
    $second = fxRun();

    expect(AiForecastRun::count())->toBe(2)
        ->and($first->results()->count())->toBe(1)->and($second->results()->count())->toBe(1)
        ->and([PMSparepart::count(), PMSchedule::count(), PMSparepart::sum('qty'), PMSchedule::pluck('status')->all()])->toBe($before)
        ->and($second->data_fingerprint)->toBe($first->data_fingerprint);
});

it('applies the area and machine type scope', function () {
    foreach ([['NDE', 'WWD'], ['NDB', 'BUL']] as [$type, $area]) {
        $m = fxMachine($type, $area);
        $p = fxPart('SAP-'.$type);
        foreach (['2026-08-01', '2026-09-01', '2026-10-01'] as $d) {
            fxUse(fxPm($m, $d), $p, 1);
        }
    }

    expect(fxRun(['area' => 'BUL'])->results()->pluck('machine_type')->unique()->all())->toBe(['NDB'])
        ->and(fxRun(['type' => 'NDE'])->results()->pluck('area')->unique()->all())->toBe(['WWD']);
});

it('restricts the dashboard, run and export to ADMIN', function () {
    $m = fxMachine();
    $p = fxPart('SAP-A');
    foreach (['2026-08-01', '2026-09-01', '2026-10-01'] as $d) {
        fxUse(fxPm($m, $d), $p, 1);
    }
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post('/maintenance-intelligence/sparepart-analyst/run', ['period_type' => 'MONTH', 'period_date' => '2026-11-10'])
        ->assertRedirect();
    $run = AiForecastRun::first();

    $this->actingAs($admin)->get('/maintenance-intelligence/sparepart-analyst')->assertOk()
        ->assertSee('AI Sparepart Analyst')->assertSee('Accuracy Not Available: Insufficient Testing Data');
    $this->actingAs($admin)->get("/maintenance-intelligence/sparepart-analyst/{$run->id}/export")->assertOk();

    foreach (['pic', 'koordinator', 'supervisor'] as $role) {
        $u = User::factory()->{$role}()->create();
        $this->actingAs($u)->get('/maintenance-intelligence/sparepart-analyst')->assertForbidden();
        $this->actingAs($u)->post('/maintenance-intelligence/sparepart-analyst/run', ['period_type' => 'MONTH', 'period_date' => '2026-11-10'])->assertForbidden();
        $this->actingAs($u)->get("/maintenance-intelligence/sparepart-analyst/{$run->id}/export")->assertForbidden();
    }

    auth()->logout();
    $this->get('/maintenance-intelligence/sparepart-analyst')->assertRedirect(route('login'));
});
