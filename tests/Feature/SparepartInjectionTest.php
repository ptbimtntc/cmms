<?php

use App\Models\Area;
use App\Models\Machine;
use App\Models\PMSchedule;
use App\Models\PMSparepart;
use App\Models\Sparepart;
use App\Models\SparepartInjectionImport;
use App\Models\SparepartInjectionRow;
use App\Models\User;
use App\Services\SparepartInjectionService;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

function siUser(string $role, string $area = 'WWD'): User
{
    $f = User::factory();

    return match ($role) {
        'admin' => $f->admin()->create(),
        'koordinator' => $f->koordinator()->forArea($area)->create(),
        'pic' => $f->pic()->forArea($area)->create(),
        'supervisor' => $f->supervisor()->create(),
    };
}

function siPm(string $order, string $area = 'WWD', array $overrides = []): PMSchedule
{
    $machine = Machine::create(['machine_number' => 'MC-'.uniqid(), 'area' => $area, 'machine_type' => 'NDE', 'status' => 'ACTIVE']);

    return PMSchedule::create(array_merge([
        'machine_id' => $machine->id, 'machine_number' => $machine->machine_number, 'machine_type' => 'NDE', 'area' => $area,
        'order_number' => $order, 'plan_date' => '2026-10-10', 'plan_month' => 'October', 'plan_year' => '2026',
        'due_date' => '2026-10-24', 'status' => 'OPEN',
    ], $overrides));
}

function siPart(string $sap, float $price = 0): Sparepart
{
    return Sparepart::create(['material_number' => $sap, 'description' => 'Part '.$sap, 'unit' => 'PCS', 'price' => $price]);
}

function siCsv(array $rows, string $header = 'Month,SAP Number,Qty,Order Number'): UploadedFile
{
    $lines = array_map(fn ($r) => implode(',', $r), $rows);

    return UploadedFile::fake()->createWithContent('gudang.csv', implode("\n", array_merge([$header], $lines))."\n");
}

function siImport(User $user, array $rows, string $header = 'Month,SAP Number,Qty,Order Number')
{
    return test()->actingAs($user)->post(route('sparepart-injection.store'), ['file' => siCsv($rows, $header)]);
}

function siLast(): SparepartInjectionImport
{
    return SparepartInjectionImport::latest('id')->firstOrFail();
}

function siRow(int $number, ?SparepartInjectionImport $import = null): SparepartInjectionRow
{
    return ($import ?? siLast())->rows()->where('row_number', $number)->firstOrFail();
}

// ------------------------------------------------------------ tabs & access

test('sparepart master tab keeps working and tabs follow the role', function () {
    siPart('SAP-1');

    foreach (['admin', 'koordinator', 'supervisor'] as $role) {
        $this->actingAs(siUser($role))->get(route('spareparts.index'))->assertOk()
            ->assertSee('Sparepart Master')->assertSee('Sparepart Injection')->assertSee('SAP-1');
    }

    $this->actingAs(siUser('pic'))->get(route('spareparts.index'))->assertOk()
        ->assertSee('SAP-1')->assertDontSee('Sparepart Injection');
});

test('admin and koordinator can open the injection tab, supervisor sees it read-only, PIC is denied everywhere', function () {
    $this->actingAs(siUser('admin'))->get(route('sparepart-injection.index'))->assertOk()->assertSee('Upload &amp; Import', false);
    $this->actingAs(siUser('koordinator'))->get(route('sparepart-injection.index'))->assertOk()->assertSee('Upload &amp; Import', false);
    $this->actingAs(siUser('supervisor'))->get(route('sparepart-injection.index'))->assertOk()
        ->assertDontSee('Upload &amp; Import', false)->assertSee('Read-only view');

    $pic = siUser('pic');
    siPm('ORD-1');
    $import = SparepartInjectionImport::create(['filename' => 'x.csv']);
    $this->actingAs($pic)->get(route('sparepart-injection.index'))->assertForbidden();
    $this->actingAs($pic)->get(route('sparepart-injection.show', $import))->assertForbidden();
    siImport($pic, [['October', 'SAP-1', 1, 'ORD-1']])->assertForbidden();
    expect(SparepartInjectionImport::count())->toBe(1);
});

test('supervisor cannot upload', function () {
    siPart('SAP-1');
    siPm('ORD-1');

    siImport(siUser('supervisor'), [['October', 'SAP-1', 1, 'ORD-1']])->assertForbidden();
    expect(SparepartInjectionImport::count())->toBe(0)->and(PMSparepart::count())->toBe(0);
});

// ----------------------------------------------------------------- matching

test('rows are matched by order number and SAP number and written to the existing usage table', function () {
    $pm = siPm('ORD-1');
    $other = siPm('ORD-2');
    $part = siPart('SAP-1');

    siImport(siUser('admin'), [['October', 'SAP-1', 2, 'ORD-1']])->assertRedirect(route('sparepart-injection.show', 1));

    $usage = PMSparepart::sole();
    expect($usage->pm_schedule_id)->toBe($pm->id)->and($usage->sparepart_id)->toBe($part->id)
        ->and($usage->qty)->toBe(2)->and($usage->source)->toBe('WAREHOUSE')
        ->and(PMSparepart::where('pm_schedule_id', $other->id)->count())->toBe(0)
        ->and(siLast()->only(['total_rows', 'processed_rows', 'usage_added', 'skipped_rows', 'failed_rows']))
        ->toBe(['total_rows' => 1, 'processed_rows' => 1, 'usage_added' => 1, 'skipped_rows' => 0, 'failed_rows' => 0]);
});

test('unmatched or invalid rows are reported with a reason and never inserted', function () {
    siPm('ORD-1');
    siPart('SAP-1');

    siImport(siUser('admin'), [
        ['October', 'SAP-1', 1, 'NOPE'],        // 2: order not found
        ['October', 'NOSAP', 1, 'ORD-1'],       // 3: sap not found
        ['not a month', 'SAP-1', 1, 'ORD-1'],               // 4: bad month
        ['October', 'SAP-1', 0, 'ORD-1'],       // 5: bad qty
        ['October', 'SAP-1', 1.5, 'ORD-1'],     // 6: bad qty
        ['October', 'SAP-1', 1, ''],            // 7: empty order
        ['10', 'SAP-1', 1, 'ORD-1'],            // 8: number instead of a month name
    ])->assertRedirect();

    $import = siLast();
    expect($import->failed_rows)->toBe(7)->and($import->processed_rows)->toBe(0)->and(PMSparepart::count())->toBe(0)
        ->and(siRow(2)->reason)->toContain('Order Number not found')
        ->and(siRow(3)->reason)->toContain('SAP Number does not match')
        ->and(siRow(4)->reason)->toContain('Month')
        ->and(siRow(5)->reason)->toContain('Qty')
        ->and(siRow(6)->reason)->toContain('Qty')
        ->and(siRow(7)->reason)->toContain('Order Number is empty')
        ->and(siRow(8)->reason)->toContain('Month');
});

test('an order number shared by several schedules is not guessed', function () {
    siPm('ORD-DUP');
    siPm('ORD-DUP');
    siPart('SAP-1');

    siImport(siUser('admin'), [['October', 'SAP-1', 1, 'ORD-DUP']]);

    expect(PMSparepart::count())->toBe(0)->and(siRow(2)->reason)->toContain('more than one');
});

test('wrong headers are rejected before anything is processed', function () {
    siPm('ORD-1');
    siPart('SAP-1');

    siImport(siUser('admin'), [['October', 'SAP-1', 1, 'ORD-1']], 'Time,SAP,Qty,Order')->assertSessionHasErrors('file');
    expect(SparepartInjectionImport::count())->toBe(0);
});

test('koordinator can only inject into PM schedules of their own area', function () {
    $own = siPm('ORD-OWN', 'WWD');
    $foreign = siPm('ORD-BUL', 'BUL');
    siPart('SAP-1');

    siImport(siUser('koordinator', 'WWD'), [
        ['October', 'SAP-1', 1, 'ORD-OWN'],
        ['October', 'SAP-1', 1, 'ORD-BUL'],
    ]);

    expect(PMSparepart::pluck('pm_schedule_id')->all())->toBe([$own->id])
        ->and(siRow(3)->reason)->toContain('Order Number not found')
        ->and(PMSparepart::where('pm_schedule_id', $foreign->id)->count())->toBe(0);
});

// ------------------------------------------------------------ qty & dupes

test('qty of the same order and sap number is summed into one usage row', function () {
    $pm = siPm('ORD-1');
    siPart('SAP-1');

    siImport(siUser('admin'), [
        ['October', 'SAP-1', 2, 'ORD-1'],
        ['October', 'SAP-1', 3, 'ORD-1'],
    ]);

    expect(PMSparepart::where('pm_schedule_id', $pm->id)->count())->toBe(1)
        ->and(PMSparepart::sole()->qty)->toBe(5)
        ->and(siLast()->processed_rows)->toBe(2)->and(siLast()->usage_added)->toBe(1);
});

test('all rows of the same order and sap number in one file are summed, whatever the month spelling', function () {
    siPm('ORD-1');
    siPart('SAP-1');

    siImport(siUser('admin'), [
        ['October', 'SAP-1', 2, 'ORD-1'],
        ['Okt', 'SAP-1', 4, ' ord-1 '],
        ['  OCT. ', 'SAP-1', 9, 'ORD-1'],
    ]);

    expect(PMSparepart::sole()->qty)->toBe(15)
        ->and(siLast()->processed_rows)->toBe(3)->and(siLast()->usage_added)->toBe(1)
        ->and(siRow(2)->month)->toBe(now()->year.'-10')
        ->and(siRow(2)->monthLabel())->toBe('October '.now()->year);
});

test('re-uploading the same file or an overlapping one never adds qty or usage again', function () {
    $pm = siPm('ORD-1');
    $pm2 = siPm('ORD-2');
    siPart('SAP-1');
    siPart('SAP-2');
    $admin = siUser('admin');
    $file = [['October', 'SAP-1', 2, 'ORD-1'], ['October', 'SAP-1', 3, 'ORD-1']];

    siImport($admin, $file);
    siImport($admin, $file);

    expect(PMSparepart::count())->toBe(1)->and(PMSparepart::sole()->qty)->toBe(5)
        ->and(siLast()->processed_rows)->toBe(0)->and(siLast()->skipped_rows)->toBe(2)
        ->and(siRow(2)->reason)->toContain('Sparepart Usage already exists');

    // Overlap: old rows skipped, the new order is applied.
    siImport($admin, array_merge($file, [['November', 'SAP-2', 1, 'ORD-2']]));
    expect(PMSparepart::count())->toBe(2)->and(PMSparepart::where('pm_schedule_id', $pm->id)->sole()->qty)->toBe(5)
        ->and(PMSparepart::where('pm_schedule_id', $pm2->id)->sole()->qty)->toBe(1);

    // A later file with more qty for an already injected order+sparepart is not added on top.
    siImport($admin, [['December', 'SAP-1', 50, 'ORD-1']]);
    expect(PMSparepart::where('pm_schedule_id', $pm->id)->sole()->qty)->toBe(5);
});

test('existing sparepart usage is never overwritten, reduced or re-added', function () {
    $pm = siPm('ORD-1');
    $part = siPart('SAP-1');
    $existing = PMSparepart::create(['pm_schedule_id' => $pm->id, 'sparepart_id' => $part->id, 'qty' => 7]);

    siImport(siUser('admin'), [['October', 'SAP-1', 2, 'ORD-1'], ['October', 'SAP-1', 3, 'ORD-1']]);

    expect(PMSparepart::count())->toBe(1)->and($existing->fresh()->qty)->toBe(7)->and($existing->fresh()->source)->toBeNull()
        ->and(siRow(2)->status)->toBe('SKIPPED')->and(siRow(2)->reason)->toContain('Sparepart Usage already exists')
        ->and(siLast()->usage_added)->toBe(0);
});

test('a failure rolls back everything so no partial data remains', function () {
    siPm('ORD-1');
    siPart('SAP-1');
    // Force a failure right after the usage row was written.
    PMSparepart::created(fn () => throw new RuntimeException('boom'));

    try {
        siImport(siUser('admin'), [['October', 'SAP-1', 2, 'ORD-1']])->assertSessionHasErrors('file');
    } finally {
        PMSparepart::flushEventListeners();
    }

    expect(PMSparepart::count())->toBe(0)->and(SparepartInjectionImport::count())->toBe(0)
        ->and(SparepartInjectionRow::count())->toBe(0);
});

test('typed spreadsheet cells (numeric sap/order/qty) are normalized', function () {
    // Cells as Maatwebsite returns them from an .xlsx (numbers arrive as int/float).
    $pm = siPm('4000123');
    siPart('1000456');

    $import = app(SparepartInjectionService::class)->import(siUser('admin'), 'gudang.xlsx', [
        ['Month', 'SAP Number', 'Qty', 'Order Number'],
        ['Oktober', 1000456.0, 4.0, 4000123.0],
        [null, null, null, null], // blank line is ignored
    ]);

    expect($import->total_rows)->toBe(1)->and(PMSparepart::sole()->qty)->toBe(4)
        ->and(PMSparepart::sole()->pm_schedule_id)->toBe($pm->id)
        ->and(siRow(2, $import)->month)->toBe(now()->year.'-10');
});

// ------------------------------------------------------- PM save & reports

test('injected usage survives Fill PM being opened and saved again', function () {
    $pic = siUser('pic');
    $pm = siPm('ORD-1', 'WWD', ['pic' => $pic->name]);
    $injected = siPart('SAP-1');
    $own = siPart('SAP-2');
    siImport(siUser('admin'), [['October', 'SAP-1', 5, 'ORD-1']]);
    $id = PMSparepart::sole()->id;

    $this->actingAs($pic)->get(route('pm-schedules.edit', $pm))->assertOk()->assertSee('Warehouse');

    $save = fn (array $spareparts) => $this->actingAs($pic)->put(route('pm-schedules.update', $pm), [
        'order_number' => 'ORD-1', 'pic' => $pic->name, 'actual_date' => '2026-10-11', 'start_time' => '08:00',
        'spareparts' => $spareparts,
    ])->assertSessionHasNoErrors();

    // Form re-submits the injected row (tampered qty) plus its own row.
    $save([['sparepart_id' => $injected->id, 'qty' => 99], ['sparepart_id' => $own->id, 'qty' => 1]]);
    expect(PMSparepart::where('pm_schedule_id', $pm->id)->count())->toBe(2)
        ->and(PMSparepart::find($id)->qty)->toBe(5)->and(PMSparepart::find($id)->source)->toBe('WAREHOUSE');

    // Form without the injected row (stale/offline payload) and again with it: still one injected row.
    $save([['sparepart_id' => $own->id, 'qty' => 2]]);
    $save([['sparepart_id' => $injected->id, 'qty' => 5]]);
    expect(PMSparepart::find($id))->not->toBeNull()
        ->and(PMSparepart::where('pm_schedule_id', $pm->id)->where('sparepart_id', $injected->id)->count())->toBe(1)
        ->and(PMSparepart::find($id)->qty)->toBe(5)
        ->and(PMSparepart::where('pm_schedule_id', $pm->id)->where('sparepart_id', $own->id)->count())->toBe(0);
});

test('cost reports count injected usage exactly once using the master price', function () {
    $pm = siPm('ORD-1', 'WWD', ['status' => 'FINISHED', 'actual_date' => '2026-10-05']);
    siPart('SAP-1', 1000);
    $admin = siUser('admin');
    $file = [['October', 'SAP-1', 3, 'ORD-1']];

    siImport($admin, $file);
    siImport($admin, $file);

    $response = $this->actingAs($admin)->get(route('reports.sparepart', ['year' => 2026]))->assertOk();
    expect($response->viewData('summary')['total_quantity'])->toBe(3)
        ->and($response->viewData('summary')['total_cost'])->toBe(3000.0);
    $this->actingAs($admin)->get(route('pm-schedules.pdf', $pm))->assertOk();
});

// ---------------------------------------------------------- history access

test('history is limited by area: koordinator sees own area, supervisor can view but not act', function () {
    siPm('ORD-1', 'WWD');
    siPart('SAP-1');
    siImport(siUser('koordinator', 'WWD'), [['October', 'SAP-1', 1, 'ORD-1']]);
    $wwdImport = siLast();
    siImport(siUser('admin'), [['October', 'SAP-1', 1, 'NOPE']]);
    $adminImport = siLast();

    $buller = siUser('koordinator', 'BUL');
    $this->actingAs($buller)->get(route('sparepart-injection.show', $wwdImport))->assertForbidden();
    $this->actingAs($buller)->get(route('sparepart-injection.show', $adminImport))->assertForbidden();
    $this->actingAs(siUser('koordinator', 'WWD'))->get(route('sparepart-injection.show', $wwdImport))->assertOk()->assertSee('ORD-1');

    $supervisor = siUser('supervisor');
    $this->actingAs($supervisor)->get(route('sparepart-injection.show', $adminImport))->assertOk()->assertSee('NOPE');
    $restricted = siUser('supervisor');
    $restricted->areas()->attach(Area::firstOrCreate(['name' => 'BUL'], ['slug' => 'bul', 'is_active' => true]));
    $this->actingAs($restricted)->get(route('sparepart-injection.show', $wwdImport))->assertForbidden();
    $this->actingAs($supervisor)->post(route('sparepart-injection.store'), [])->assertForbidden();
});

test('the injection template is listed on the import templates page and downloads with the right headers', function () {
    Excel::fake();
    $admin = siUser('admin');

    $this->actingAs($admin)->get(route('import-templates'))->assertOk()
        ->assertSee(route('import-templates.download', 'sparepart-injection'), false);
    $this->actingAs($admin)->get(route('import-templates.download', 'sparepart-injection'))->assertOk();

    Excel::assertDownloaded('sparepart_injection_import_template.xlsx', function ($export) {
        return $export->array()[0] === ['Month', 'SAP Number', 'Qty', 'Order Number'];
    });
});

test('month names are accepted in Indonesian and English, full or abbreviated', function () {
    siPm('ORD-1');
    siPart('SAP-1');
    $rows = collect(['Januari', 'Feb', 'March', 'Mei', 'agu', 'Sept', 'Desember', 'nopember'])
        ->map(fn ($m) => [$m, 'SAP-1', 1, 'ORD-1'])->all();

    siImport(siUser('admin'), $rows);

    expect(siLast()->rows()->pluck('month')->all())->toBe(collect([1, 2, 3, 5, 8, 9, 12, 11])->map(fn ($m) => sprintf('%d-%02d', now()->year, $m))->all())
        ->and(PMSparepart::sole()->qty)->toBe(8);
});
