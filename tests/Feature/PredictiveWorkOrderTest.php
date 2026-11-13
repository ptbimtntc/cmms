<?php

require_once __DIR__.'/../Support/predictive_helpers.php';

use App\Models\PredictiveWorkOrder;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;

test('predictive pages require authentication and a permitted role', function () {
    $this->get(route('predictive.index'))->assertRedirect(route('login'));

    $this->actingAs(User::factory()->create(['role' => User::ROLE_GUEST]))
        ->get(route('predictive.index'))->assertForbidden();
});

test('each role that may see the module can open both WO tabs and the dashboard', function (string $role) {
    $user = pvUser($role);

    foreach (['vibration', 'air-leaked', 'dashboard'] as $tab) {
        $this->actingAs($user)->get(route('predictive.index', ['tab' => $tab]))->assertOk();
    }
})->with(['ADMIN', 'KOORDINATOR', 'PIC']);

test('supervisor can view but cannot write', function () {
    $supervisor = User::factory()->create(['role' => User::ROLE_SUPERVISOR]);
    pvMachine();

    $this->actingAs($supervisor)->get(route('predictive.index'))->assertOk();
    $this->actingAs($supervisor)->post(route('predictive.store'), pvPayload())->assertForbidden();
    expect(PredictiveWorkOrder::count())->toBe(0);
});

test('koordinator adds a WO and area and machine type come from the Machine Master', function () {
    pvMachine('NDE-001', 'WWD', 'NDE');

    $this->actingAs(pvUser('KOORDINATOR'))
        ->post(route('predictive.store'), pvPayload(['area' => 'BUL', 'machine_type' => 'FORGED']))
        ->assertRedirect(route('predictive.index', ['tab' => 'vibration']))
        ->assertSessionHas('success');

    $wo = PredictiveWorkOrder::firstOrFail();
    expect($wo->area)->toBe('WWD')
        ->and($wo->machine_type)->toBe('NDE')
        ->and($wo->status)->toBe('OPEN')
        ->and($wo->pic_user_id)->toBeNull()
        ->and($wo->category)->toBe('VIBRATION');
});

test('air leaked WOs use the same structure under their own category', function () {
    pvMachine();

    $this->actingAs(pvUser('ADMIN'))
        ->post(route('predictive.store'), pvPayload(['category' => 'AIR_LEAKED']))
        ->assertRedirect(route('predictive.index', ['tab' => 'air-leaked']));

    expect(PredictiveWorkOrder::first()->category)->toBe('AIR_LEAKED');
});

test('order number must be unique globally, across categories', function () {
    pvMachine();
    $admin = pvUser('ADMIN');
    $this->actingAs($admin)->post(route('predictive.store'), pvPayload());

    $this->actingAs($admin)
        ->post(route('predictive.store'), pvPayload(['category' => 'AIR_LEAKED']))
        ->assertSessionHasErrors('order_number');

    expect(PredictiveWorkOrder::count())->toBe(1);
});

test('unknown machine number is rejected', function () {
    $this->actingAs(pvUser('ADMIN'))
        ->post(route('predictive.store'), pvPayload(['machine_number' => 'NOPE']))
        ->assertSessionHasErrors('machine_number');
});

test('required fields, date format and due date order are validated', function () {
    pvMachine();
    $admin = pvUser('ADMIN');

    $this->actingAs($admin)->post(route('predictive.store'), [])
        ->assertSessionHasErrors(['category', 'order_number', 'machine_number', 'wo_date', 'problem_description']);

    $this->actingAs($admin)->post(route('predictive.store'), pvPayload(['wo_date' => '01/11/2026']))
        ->assertSessionHasErrors('wo_date');

    $this->actingAs($admin)->post(route('predictive.store'), pvPayload(['due_date' => '2026-10-01']))
        ->assertSessionHasErrors('due_date');

    $this->actingAs($admin)->post(route('predictive.store'), pvPayload(['category' => 'OTHER']))
        ->assertSessionHasErrors('category');
});

test('a koordinator cannot create a WO for a machine in another area', function () {
    pvMachine('BUL-001', 'BUL');

    $this->actingAs(pvUser('KOORDINATOR WWD'))
        ->post(route('predictive.store'), pvPayload(['machine_number' => 'BUL-001']))
        ->assertSessionHasErrors('machine_number');

    expect(PredictiveWorkOrder::count())->toBe(0);
});

test('a PIC cannot create or import WOs', function () {
    pvMachine();
    $pic = pvUser('PIC');

    $this->actingAs($pic)->post(route('predictive.store'), pvPayload())->assertForbidden();
    $this->actingAs($pic)->post(route('predictive.import'), [
        'category' => 'VIBRATION',
        'file' => UploadedFile::fake()->createWithContent('wo.csv', "Order Number\n1"),
    ])->assertForbidden();
});

test('machine search is scoped to the koordinator area and needs a term', function () {
    pvMachine('NDE-001', 'WWD');
    pvMachine('NDE-002', 'BUL');
    $koordinator = pvUser('KOORDINATOR WWD');

    $this->actingAs($koordinator)->getJson(route('predictive.machines', ['q' => 'NDE']))
        ->assertOk()->assertJsonCount(1)->assertJsonPath('0.machine_number', 'NDE-001');

    $this->actingAs($koordinator)->getJson(route('predictive.machines'))->assertStatus(422);
    $this->actingAs(pvUser('PIC'))->getJson(route('predictive.machines', ['q' => 'NDE']))->assertForbidden();
});

test('list shows only WOs of the user area and the selected category', function () {
    pvWo(['order_number' => 'WWD-VIB', 'machine' => pvMachine('A1', 'WWD')]);
    pvWo(['order_number' => 'WWD-AIR', 'category' => 'AIR_LEAKED', 'machine' => pvMachine('A2', 'WWD')]);
    pvWo(['order_number' => 'BUL-VIB', 'machine' => pvMachine('B1', 'BUL')]);

    $this->actingAs(pvUser('PIC WWD'))->get(route('predictive.index', ['tab' => 'vibration']))
        ->assertSee('WWD-VIB')->assertDontSee('WWD-AIR')->assertDontSee('BUL-VIB');
});

test('list search and status filter work', function () {
    pvWo(['order_number' => 'ALPHA-1', 'machine' => pvMachine('A1', 'WWD')]);
    pvWo(['order_number' => 'BETA-2', 'status' => 'CLOSED', 'machine' => pvMachine('A2', 'WWD')]);
    $admin = pvUser('ADMIN');

    $this->actingAs($admin)->get(route('predictive.index', ['search' => 'ALPHA']))
        ->assertSee('ALPHA-1')->assertDontSee('BETA-2');
    $this->actingAs($admin)->get(route('predictive.index', ['status' => 'CLOSED']))
        ->assertSee('BETA-2')->assertDontSee('ALPHA-1');
});

test('WO detail is denied across areas', function () {
    $bul = pvWo(['machine' => pvMachine('B1', 'BUL')]);

    $this->actingAs(pvUser('PIC WWD'))->get(route('predictive.show', $bul))->assertForbidden();
    $this->actingAs(pvUser('ADMIN'))->get(route('predictive.show', $bul))->assertOk();
});

test('import csv creates WOs and reports success, failed and skipped with row causes', function () {
    pvMachine('NDE-001', 'WWD', 'NDE');
    pvWo(['order_number' => 'EXISTING', 'problem_description' => 'asli', 'machine' => pvMachine('X-1', 'WWD')]);

    $csv = implode("\n", [
        'Order Number,Machine Number,Date,Problem Description',
        'N-1,NDE-001,2026-11-01,Getaran tinggi',
        'N-2,NDE-001,05-11-2026,Format tanggal dd-mm-yyyy',
        'N-3,GHOST-9,2026-11-01,Mesin tidak ada',
        'EXISTING,NDE-001,2026-11-01,Harus tidak menimpa',
        'N-1,NDE-001,2026-11-01,Duplikat dalam file',
        'N-6,NDE-001,bukan-tanggal,Tanggal rusak',
        ',NDE-001,2026-11-01,Tanpa order',
        ',,,',
    ]);

    $response = $this->actingAs(pvUser('KOORDINATOR WWD'))->post(route('predictive.import'), [
        'category' => 'VIBRATION',
        'file' => UploadedFile::fake()->createWithContent('wo.csv', $csv),
    ]);

    $result = session('predictive_import_result');
    $response->assertRedirect(route('predictive.index', ['tab' => 'vibration']));
    expect($result['success'])->toBe(2)
        ->and($result['failed'])->toBe(3)
        ->and($result['skipped'])->toBe(2);

    $reasons = collect($result['details'])->pluck('reason', 'row');
    expect($reasons[4])->toContain('GHOST-9')
        ->and($reasons[5])->toContain('already exists')
        ->and($reasons[6])->toContain('already exists')
        ->and($reasons[7])->toContain('Date format');

    expect(PredictiveWorkOrder::where('order_number', 'EXISTING')->value('problem_description'))->toBe('asli');

    $n2 = PredictiveWorkOrder::where('order_number', 'N-2')->first();
    expect($n2->wo_date->toDateString())->toBe('2026-11-05')
        ->and($n2->due_date->toDateString())->toBe('2026-11-19')
        ->and($n2->area)->toBe('WWD')
        ->and($n2->machine_type)->toBe('NDE')
        ->and($n2->category)->toBe('VIBRATION');
});

test('import rejects machines outside the koordinator area as failed rows', function () {
    pvMachine('BUL-001', 'BUL');

    $this->actingAs(pvUser('KOORDINATOR WWD'))->post(route('predictive.import'), [
        'category' => 'AIR_LEAKED',
        'file' => UploadedFile::fake()->createWithContent('wo.csv', "Order Number,Machine Number,Date,Problem Description,Due Date\nZ-1,BUL-001,2026-11-01,x,2026-11-02"),
    ]);

    $result = session('predictive_import_result');
    expect($result['failed'])->toBe(1)->and($result['success'])->toBe(0)
        ->and($result['details'][0]['reason'])->toContain('di luar area');
});

test('import with missing headers touches nothing and explains why', function () {
    pvMachine();

    $this->actingAs(pvUser('ADMIN'))->post(route('predictive.import'), [
        'category' => 'VIBRATION',
        'file' => UploadedFile::fake()->createWithContent('wo.csv', "Order Number,Machine Number\nA,NDE-001"),
    ]);

    expect(session('predictive_import_result')['fatal'])->toContain('Required column')
        ->and(PredictiveWorkOrder::count())->toBe(0);
});

test('import rejects unsupported file types', function () {
    $this->actingAs(pvUser('ADMIN'))->post(route('predictive.import'), [
        'category' => 'VIBRATION',
        'file' => UploadedFile::fake()->create('wo.pdf', 10, 'application/pdf'),
    ])->assertSessionHasErrors('file');
});

test('import accepts a legacy xls spreadsheet', function () {
    pvMachine('NDE-001', 'WWD', 'NDE');

    $export = new class implements FromArray
    {
        public function array(): array
        {
            return [
                ['Order Number', 'Machine Number', 'Date', 'Problem Description'],
                ['XLS-1', 'NDE-001', '2026-11-01', 'Dari xls'],
            ];
        }
    };
    $file = UploadedFile::fake()->createWithContent('wo.xls', Excel::raw($export, ExcelWriter::XLS));

    $this->actingAs(pvUser('ADMIN'))->post(route('predictive.import'), ['category' => 'VIBRATION', 'file' => $file]);

    expect(session('predictive_import_result')['success'])->toBe(1)
        ->and(PredictiveWorkOrder::where('order_number', 'XLS-1')->exists())->toBeTrue();
});

test('import accepts an xlsx spreadsheet', function () {
    pvMachine('NDE-001', 'WWD', 'NDE');

    $export = new class implements FromArray
    {
        public function array(): array
        {
            return [
                ['Order Number', 'Machine Number', 'Date', 'Problem Description'],
                ['XLSX-1', 'NDE-001', '2026-11-01', 'Dari xlsx'],
            ];
        }
    };
    $file = UploadedFile::fake()->createWithContent('wo.xlsx', Excel::raw($export, ExcelWriter::XLSX));

    $this->actingAs(pvUser('ADMIN'))->post(route('predictive.import'), ['category' => 'VIBRATION', 'file' => $file]);

    expect(session('predictive_import_result')['success'])->toBe(1);
})->skip(fn () => ! class_exists(ZipArchive::class), 'ext-zip tidak terpasang di lingkungan ini, xlsx tidak bisa dibaca/ditulis.');

test('a failed Add WO submit sends the user back to the page with the modal set to reopen', function () {
    pvMachine();
    pvWo(['order_number' => 'WO-1001']);

    $this->actingAs(pvUser('ADMIN'))
        ->from(route('predictive.index'))
        ->followingRedirects()
        ->post(route('predictive.store'), pvPayload(['_form' => 'add']))
        ->assertSee('addOpen: true', false)
        ->assertSee('WO-1001');
});

test('a blank due date defaults to the WO date plus 14 days', function () {
    pvMachine();

    $this->actingAs(pvUser('ADMIN'))->post(route('predictive.store'), pvPayload(['wo_date' => '2026-11-20', 'due_date' => '']))
        ->assertSessionHasNoErrors();

    expect(PredictiveWorkOrder::first()->due_date->toDateString())->toBe('2026-12-04');
});

test('an explicit due date is kept as given', function () {
    pvMachine();

    $this->actingAs(pvUser('ADMIN'))->post(route('predictive.store'), pvPayload(['wo_date' => '2026-11-20', 'due_date' => '2026-11-25']));

    expect(PredictiveWorkOrder::first()->due_date->toDateString())->toBe('2026-11-25');
});

test('import always sets due date to date plus 14 days and ignores any Due Date column', function () {
    pvMachine();
    $admin = pvUser('ADMIN');

    $this->actingAs($admin)->post(route('predictive.import'), [
        'category' => 'VIBRATION',
        'file' => UploadedFile::fake()->createWithContent('a.csv', "Order Number,Machine Number,Date,Problem Description\nB-1,NDE-001,2026-11-20,Tanpa kolom due"),
    ]);
    $this->actingAs($admin)->post(route('predictive.import'), [
        'category' => 'VIBRATION',
        'file' => UploadedFile::fake()->createWithContent('b.csv', "Order Number,Machine Number,Date,Problem Description,Due Date\nB-2,NDE-001,2026-11-20,Kolom lama diabaikan,2026-11-22\nB-3,NDE-001,2026-11-20,Due rusak tidak masalah,rusak"),
    ]);

    expect(session('predictive_import_result')['failed'])->toBe(0)
        ->and(PredictiveWorkOrder::pluck('due_date', 'order_number')->map->toDateString()->all())
        ->toBe(['B-1' => '2026-12-04', 'B-2' => '2026-12-04', 'B-3' => '2026-12-04']);
});
