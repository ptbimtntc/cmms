<?php

use App\Exceptions\DuplicateOilAuditException;
use App\Models\Machine;
use App\Models\OilAudit;
use App\Models\SyncOperation;
use App\Models\User;
use App\Services\OilAuditCreateService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Task 10 — one Oil Audit per machine per business (Asia/Jakarta) date,
 * for every PIC and device, guaranteed by the database.
 */
function dupMachine(string $number): Machine
{
    return Machine::create([
        'machine_number' => $number,
        'area' => 'WWD',
        'machine_type' => oilAuditMachineType(),
        'status' => 'ACTIVE',
    ]);
}

function dupPic(string $name = 'PIC'): User
{
    return User::factory()->create([...roleAttributes('PIC WWD'), 'name' => $name]);
}

function dupSync(User $user, Machine $machine, ?string $uuid = null, string $condition = 'OKE')
{
    return test()->actingAs($user)->postJson(route('api.sync'), [
        'operation_uuid' => $uuid ?? (string) Str::uuid(),
        'transaction_type' => 'OIL_AUDIT_CREATE',
        'payload' => ['machine_id' => $machine->id, 'condition' => $condition],
        'expected_state' => [],
    ]);
}

beforeEach(function () {
    // Controlled clock: 2026-10-08 10:00 Asia/Jakarta.
    Carbon::setTestNow(Carbon::parse('2026-10-08 10:00:00', 'Asia/Jakarta'));
});

afterEach(function () {
    Carbon::setTestNow();
});

test('first audit of a machine on a date succeeds and stores the Jakarta business date', function () {
    $machine = dupMachine('3001');

    $audit = app(OilAuditCreateService::class)->create($machine, dupPic(), 'OKE');

    expect($audit->audit_date)->toBe('2026-10-08')
        ->and($audit->audited_at->format('Y-m-d H:i:s'))->toBe('2026-10-08 10:00:00');
});

test('business date follows Asia/Jakarta, not UTC (23:30 Jakarta stays on the same day)', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-08 23:30:00', 'Asia/Jakarta')); // 16:30 UTC

    $audit = app(OilAuditCreateService::class)->create(dupMachine('3001'), dupPic(), 'OKE');

    expect($audit->audit_date)->toBe('2026-10-08');
});

test('second audit of the same machine on the same date is rejected, even by a different PIC', function () {
    $machine = dupMachine('3001');
    app(OilAuditCreateService::class)->create($machine, dupPic('Budi'), 'OKE');

    expect(fn () => app(OilAuditCreateService::class)->create($machine, dupPic('Sari'), 'KRITIS'))
        ->toThrow(DuplicateOilAuditException::class, 'Mesin 3001 sudah memiliki audit oli untuk hari ini');

    expect(OilAudit::where('machine_id', $machine->id)->count())->toBe(1);
});

test('another machine on the same date, and the same machine on the next date, are allowed', function () {
    $service = app(OilAuditCreateService::class);
    $m1 = dupMachine('3001');
    $m2 = dupMachine('3002');
    $pic = dupPic();

    $service->create($m1, $pic, 'OKE');
    $service->create($m2, $pic, 'PANTAU');

    Carbon::setTestNow(Carbon::parse('2026-10-09 08:00:00', 'Asia/Jakarta'));
    $service->create($m1, $pic, 'OKE');

    expect(OilAudit::count())->toBe(3);
});

test('the database itself rejects a second audit for the same machine and date', function () {
    $machine = dupMachine('3001');
    $attrs = fn () => [
        'machine_id' => $machine->id, 'machine_number' => '3001', 'machine_type' => 'NDE SW', 'area' => 'WWD',
        'condition' => 'OKE', 'audited_by_name' => 'x', 'audited_at' => now(), 'audit_date' => '2026-10-08',
    ];

    OilAudit::create($attrs());

    expect(fn () => OilAudit::create($attrs()))->toThrow(UniqueConstraintViolationException::class);
});

test('a concurrent request that slips in after the pre-check is still rejected as a business duplicate, not a 500', function () {
    $machine = dupMachine('3001');
    $raced = false;

    // Simulates request B committing between request A's exists() check and
    // A's INSERT — exactly the window a pre-check alone cannot close.
    OilAudit::creating(function (OilAudit $audit) use (&$raced, $machine) {
        if ($raced) {
            return;
        }
        $raced = true;
        DB::table('oil_audits')->insert([
            'machine_id' => $machine->id, 'machine_number' => '3001', 'machine_type' => 'NDE SW', 'area' => 'WWD',
            'condition' => 'OKE', 'audited_by_name' => 'B', 'audited_at' => now(), 'audit_date' => $audit->audit_date,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    });

    $response = dupSync(dupPic(), $machine);

    $response->assertStatus(409)->assertJson(['success' => false, 'status' => 'duplicate_audit']);
    // The losing request left no audit behind (the simulated winner's row
    // shares its transaction in this test, so only the loser is asserted).
    expect(OilAudit::whereNotNull('audited_by_user_id')->count())->toBe(0);
});

test('online store: duplicate audit is refused with a clear message, no second row', function () {
    $machine = dupMachine('3001');
    $other = dupPic('Budi');
    app(OilAuditCreateService::class)->create($machine, $other, 'OKE');

    $response = $this->actingAs(dupPic('Sari'))->post(route('oil-audits.store'), ['machine_id' => $machine->id, 'condition' => 'KRITIS']);

    $response->assertRedirect(route('oil-audits.scan'))
        ->assertSessionHas('error', fn ($m) => str_contains($m, 'Mesin 3001 sudah memiliki audit oli untuk hari ini'));
    expect(OilAudit::count())->toBe(1);
});

test('sync: duplicate from a second device returns duplicate_audit 409 and stores nothing extra', function () {
    $machine = dupMachine('3001');
    dupSync(dupPic('Device1'), $machine)->assertOk()->assertJson(['status' => 'processed']);

    $uuid2 = (string) Str::uuid();
    $response = dupSync(dupPic('Device2'), $machine, $uuid2, 'KRITIS');

    $response->assertStatus(409)->assertJson(['status' => 'duplicate_audit', 'operation_uuid' => $uuid2]);
    expect($response->json('message'))->toContain('sudah memiliki audit oli untuk hari ini');
    expect(OilAudit::count())->toBe(1)
        ->and(SyncOperation::where('operation_uuid', $uuid2)->exists())->toBeFalse();
});

test('sync: retrying an already processed uuid stays already_processed — never turned into a duplicate rejection', function () {
    $machine = dupMachine('3001');
    $pic = dupPic();
    $uuid = (string) Str::uuid();

    dupSync($pic, $machine, $uuid)->assertOk()->assertJson(['status' => 'processed']);
    dupSync($pic, $machine, $uuid)->assertOk()->assertJson(['status' => 'already_processed']);

    expect(OilAudit::count())->toBe(1);
});

test('sync: a rejected duplicate re-sent with the same uuid is rejected the same way, never creating an audit', function () {
    $machine = dupMachine('3001');
    dupSync(dupPic('A'), $machine)->assertOk();
    $uuid = (string) Str::uuid();

    dupSync(dupPic('B'), $machine, $uuid)->assertStatus(409)->assertJson(['status' => 'duplicate_audit']);
    dupSync(dupPic('B'), $machine, $uuid)->assertStatus(409)->assertJson(['status' => 'duplicate_audit']);

    expect(OilAudit::count())->toBe(1);
});

test('sync: a duplicate of one machine does not affect an independent audit of another machine', function () {
    $m1 = dupMachine('3001');
    $m2 = dupMachine('3002');
    dupSync(dupPic('A'), $m1)->assertOk();
    $pic = dupPic('B');

    dupSync($pic, $m1)->assertStatus(409);
    dupSync($pic, $m2)->assertOk()->assertJson(['status' => 'processed']);

    expect(OilAudit::count())->toBe(2);
});

test('migration keeps historical audits (including legacy same-day duplicates) and backfills audit_date safely', function () {
    $machine = dupMachine('3001');
    $path = 'database/migrations/2026_10_09_000100_add_audit_date_to_oil_audits_table.php';

    Artisan::call('migrate:rollback', ['--path' => $path]);
    expect(Schema::hasColumn('oil_audits', 'audit_date'))->toBeFalse();

    $row = fn (string $at, string $cond) => [
        'machine_id' => $machine->id, 'machine_number' => '3001', 'machine_type' => 'NDE SW', 'area' => 'WWD',
        'condition' => $cond, 'audited_by_name' => 'legacy', 'audited_at' => $at, 'created_at' => $at, 'updated_at' => $at,
    ];
    DB::table('oil_audits')->insert([
        $row('2026-09-01 08:00:00', 'OKE'),
        $row('2026-09-01 15:00:00', 'KRITIS'), // legacy same-day duplicate
        $row('2026-09-02 23:50:00', 'PANTAU'),
    ]);

    Artisan::call('migrate', ['--path' => $path]);

    $rows = DB::table('oil_audits')->orderBy('id')->get(['condition', 'audit_date']);

    expect($rows)->toHaveCount(3); // nothing deleted
    expect($rows->pluck('audit_date')->map(fn ($d) => $d ? substr($d, 0, 10) : null)->all())
        ->toBe(['2026-09-01', null, '2026-09-02']);
    expect(OilAudit::count())->toBe(3);
});
