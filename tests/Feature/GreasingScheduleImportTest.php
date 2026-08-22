<?php

use App\Models\Greasing;
use App\Models\Group;
use App\Models\User;
use Illuminate\Http\UploadedFile;

function makeCsv(string $filename, array $rows): UploadedFile
{
    $path = storage_path('framework/testing/'.$filename);

    if (! is_dir(dirname($path))) {
        mkdir(dirname($path), 0777, true);
    }

    $handle = fopen($path, 'w');
    foreach ($rows as $row) {
        fputcsv($handle, $row);
    }
    fclose($handle);

    return new UploadedFile($path, $filename, 'text/csv', null, true);
}

/**
 * @return array{0: User, 1: Group, 2: User} [$admin, $group, $pic]
 */
function importFixtures(): array
{
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $group = Group::create(['name' => 'Line 1']);
    $pic = User::factory()->create(['role' => User::ROLE_PIC_WWD, 'name' => 'Budi']);

    return [$admin, $group, $pic];
}

test('valid csv rows are imported with due date auto-calculated and status OPEN', function () {
    [$admin, $group] = importFixtures();

    $csv = makeCsv('valid.csv', [
        ['order_number', 'plan_date', 'group', 'cycle', 'pic', 'remarks'],
        ['WO-1001', '2026-08-01', 'Line 1', '4W', 'Budi', 'first batch'],
    ]);

    $this->actingAs($admin)
        ->post(route('greasings.import'), ['file' => $csv])
        ->assertRedirect();

    $greasing = Greasing::where('order_number', 'WO-1001')->first();

    expect($greasing)->not->toBeNull()
        ->and($greasing->due_date->format('Y-m-d'))->toBe('2026-08-15')
        ->and($greasing->status)->toBe('OPEN')
        ->and($greasing->action_date)->toBeNull()
        ->and($greasing->pic)->toBe('Budi')
        ->and($greasing->cycle)->toBe('4W')
        ->and($greasing->remarks)->toBe('first batch')
        ->and($greasing->group_id)->toBe($group->id);
});

test('day week month due date and status columns are never read from the file', function () {
    [$admin] = importFixtures();

    $csv = makeCsv('extra-columns.csv', [
        ['order_number', 'plan_date', 'group', 'cycle', 'pic', 'remarks', 'day', 'week', 'month', 'due_date', 'status'],
        ['WO-2001', '2026-08-01', 'Line 1', '4W', 'Budi', '-', 'Monday', '32', 'August', '2099-01-01', 'FINISH'],
    ]);

    $this->actingAs($admin)->post(route('greasings.import'), ['file' => $csv]);

    $greasing = Greasing::where('order_number', 'WO-2001')->first();

    expect($greasing)->not->toBeNull()
        ->and($greasing->due_date->format('Y-m-d'))->toBe('2026-08-15') // computed, ignoring the bogus due_date column
        ->and($greasing->status)->toBe('OPEN'); // ignoring the bogus status column
});

test('row with an unknown group is skipped, not imported', function () {
    [$admin] = importFixtures();

    $csv = makeCsv('bad-group.csv', [
        ['order_number', 'plan_date', 'group', 'cycle', 'pic', 'remarks'],
        ['WO-3001', '2026-08-01', 'Nonexistent Group', '4W', 'Budi', ''],
    ]);

    $this->actingAs($admin)->post(route('greasings.import'), ['file' => $csv]);

    expect(Greasing::where('order_number', 'WO-3001')->exists())->toBeFalse();
    expect(session('greasing_import_result'))->toMatchArray(['imported' => 0, 'duplicate' => 0, 'skipped' => 1]);
});

test('row with an invalid date is skipped, not imported, and does not crash', function () {
    [$admin] = importFixtures();

    $csv = makeCsv('bad-date.csv', [
        ['order_number', 'plan_date', 'group', 'cycle', 'pic', 'remarks'],
        ['WO-4001', 'not-a-date', 'Line 1', '4W', 'Budi', ''],
    ]);

    $response = $this->actingAs($admin)->post(route('greasings.import'), ['file' => $csv]);

    $response->assertRedirect();
    expect(Greasing::where('order_number', 'WO-4001')->exists())->toBeFalse();
    expect(session('greasing_import_result')['skipped'])->toBe(1);
});

test('pic is optional and not validated against registered users during import — assignment happens later via koordinator', function () {
    [$admin] = importFixtures();

    $csv = makeCsv('free-text-pic.csv', [
        ['order_number', 'plan_date', 'group', 'cycle', 'pic', 'remarks'],
        ['WO-5001', '2026-08-01', 'Line 1', '4W', 'Nobody Registered Yet', ''],
    ]);

    $this->actingAs($admin)->post(route('greasings.import'), ['file' => $csv]);

    $greasing = Greasing::where('order_number', 'WO-5001')->first();
    expect($greasing)->not->toBeNull()
        ->and($greasing->pic)->toBe('Nobody Registered Yet');
});

test('blank pic column imports the row with a null pic', function () {
    [$admin] = importFixtures();

    $csv = makeCsv('blank-pic.csv', [
        ['order_number', 'plan_date', 'group', 'cycle', 'pic', 'remarks'],
        ['WO-5002', '2026-08-01', 'Line 1', '4W', '', ''],
    ]);

    $this->actingAs($admin)->post(route('greasings.import'), ['file' => $csv]);

    $greasing = Greasing::where('order_number', 'WO-5002')->first();
    expect($greasing)->not->toBeNull()
        ->and($greasing->pic)->toBeNull();
});

test('row with blank cycle is skipped', function () {
    [$admin] = importFixtures();

    $csv = makeCsv('blank-cycle.csv', [
        ['order_number', 'plan_date', 'group', 'cycle', 'pic', 'remarks'],
        ['WO-6001', '2026-08-01', 'Line 1', '', 'Budi', ''],
    ]);

    $this->actingAs($admin)->post(route('greasings.import'), ['file' => $csv]);

    expect(Greasing::where('order_number', 'WO-6001')->exists())->toBeFalse();
});

test('remarks column is optional', function () {
    [$admin] = importFixtures();

    $csv = makeCsv('no-remarks.csv', [
        ['order_number', 'plan_date', 'group', 'cycle', 'pic', 'remarks'],
        ['WO-7001', '2026-08-01', 'Line 1', '4W', 'Budi', ''],
    ]);

    $this->actingAs($admin)->post(route('greasings.import'), ['file' => $csv]);

    $greasing = Greasing::where('order_number', 'WO-7001')->first();
    expect($greasing)->not->toBeNull()
        ->and($greasing->remarks)->toBeNull();
});

test('duplicate rule is group plus plan date plus order number', function () {
    [$admin, $group] = importFixtures();

    Greasing::create([
        'group_id' => $group->id,
        'order_number' => 'WO-8001',
        'cycle' => '4W',
        'plan_date' => '2026-08-01',
        'due_date' => Greasing::calculateDueDate('2026-08-01'),
        'pic' => 'Budi',
        'status' => 'OPEN',
    ]);

    // Exact same group + plan_date + order_number => duplicate, must be skipped.
    $duplicateCsv = makeCsv('duplicate.csv', [
        ['order_number', 'plan_date', 'group', 'cycle', 'pic', 'remarks'],
        ['WO-8001', '2026-08-01', 'Line 1', '4W', 'Budi', ''],
    ]);
    $this->actingAs($admin)->post(route('greasings.import'), ['file' => $duplicateCsv]);
    expect(Greasing::where('order_number', 'WO-8001')->count())->toBe(1);
    expect(session('greasing_import_result'))->toMatchArray(['imported' => 0, 'duplicate' => 1, 'skipped' => 0]);

    // Same order_number + group but a DIFFERENT plan_date => not a duplicate, must import.
    $differentDateCsv = makeCsv('different-date.csv', [
        ['order_number', 'plan_date', 'group', 'cycle', 'pic', 'remarks'],
        ['WO-8001', '2026-09-01', 'Line 1', '52W', 'Budi', ''],
    ]);
    $this->actingAs($admin)->post(route('greasings.import'), ['file' => $differentDateCsv]);
    expect(Greasing::where('order_number', 'WO-8001')->count())->toBe(2);

    // Same order_number + plan_date but a DIFFERENT group => not a duplicate, must import.
    $otherGroup = Group::create(['name' => 'Line 2']);
    $differentGroupCsv = makeCsv('different-group.csv', [
        ['order_number', 'plan_date', 'group', 'cycle', 'pic', 'remarks'],
        ['WO-8001', '2026-08-01', 'Line 2', '4W', 'Budi', ''],
    ]);
    $this->actingAs($admin)->post(route('greasings.import'), ['file' => $differentGroupCsv]);
    expect(Greasing::where('order_number', 'WO-8001')->count())->toBe(3);
    expect(Greasing::where('order_number', 'WO-8001')->where('group_id', $otherGroup->id)->exists())->toBeTrue();
});

test('one invalid row does not prevent other valid rows in the same file from importing', function () {
    [$admin] = importFixtures();

    $csv = makeCsv('mixed.csv', [
        ['order_number', 'plan_date', 'group', 'cycle', 'pic', 'remarks'],
        ['WO-9001', '2026-08-01', 'Line 1', '4W', 'Budi', 'valid row 1'],
        ['WO-9002', 'garbage-date', 'Line 1', '4W', 'Budi', 'invalid date'],
        ['WO-9003', '2026-08-03', 'Unknown Group', '4W', 'Budi', 'invalid group'],
        ['WO-9004', '2026-08-04', 'Line 1', '4W', 'Budi', 'valid row 2'],
    ]);

    $this->actingAs($admin)->post(route('greasings.import'), ['file' => $csv]);

    expect(Greasing::where('order_number', 'WO-9001')->exists())->toBeTrue()
        ->and(Greasing::where('order_number', 'WO-9004')->exists())->toBeTrue()
        ->and(Greasing::where('order_number', 'WO-9002')->exists())->toBeFalse()
        ->and(Greasing::where('order_number', 'WO-9003')->exists())->toBeFalse();

    expect(session('greasing_import_result'))->toMatchArray(['imported' => 2, 'duplicate' => 0, 'skipped' => 2]);
});

test('non csv file is rejected', function () {
    [$admin] = importFixtures();
    $file = UploadedFile::fake()->create('schedule.pdf', 10, 'application/pdf');

    $response = $this->actingAs($admin)->post(route('greasings.import'), ['file' => $file]);

    $response->assertSessionHasErrors('file');
    expect(Greasing::count())->toBe(0);
});

test('oversized file is rejected', function () {
    [$admin] = importFixtures();
    $file = UploadedFile::fake()->create('huge.csv', 20481, 'text/csv');

    $response = $this->actingAs($admin)->post(route('greasings.import'), ['file' => $file]);

    $response->assertSessionHasErrors('file');
});

test('missing file is rejected', function () {
    [$admin] = importFixtures();

    $response = $this->actingAs($admin)->post(route('greasings.import'), []);

    $response->assertSessionHasErrors('file');
});

test('internal exception during import does not leak stack trace to the browser', function () {
    [$admin] = importFixtures();

    \Maatwebsite\Excel\Facades\Excel::shouldReceive('import')
        ->once()
        ->andThrow(new \RuntimeException('SQLSTATE[42S02]: secret schema detail leaked from a real exception'));

    $csv = makeCsv('boom.csv', [
        ['order_number', 'plan_date', 'group', 'cycle', 'pic', 'remarks'],
        ['WO-1', '2026-08-01', 'Line 1', '4W', 'Budi', ''],
    ]);

    $response = $this->actingAs($admin)->post(route('greasings.import'), ['file' => $csv]);

    // Caught gracefully: redirected back with a generic file error, not a 500/crash.
    $response->assertRedirect();
    $response->assertSessionHasErrors('file');

    $errorMessage = session('errors')->first('file');
    expect($errorMessage)->not->toContain('SQLSTATE')
        ->and($errorMessage)->not->toContain('RuntimeException')
        ->and($errorMessage)->toBe('Import failed. Please check the file format and data.');
});

test('guest cannot import schedules', function () {
    importFixtures();
    $guest = User::factory()->create(['role' => User::ROLE_GUEST]);

    $csv = makeCsv('guest-attempt.csv', [
        ['order_number', 'plan_date', 'group', 'cycle', 'pic', 'remarks'],
        ['WO-1', '2026-08-01', 'Line 1', '4W', 'Budi', ''],
    ]);

    $this->actingAs($guest)->post(route('greasings.import'), ['file' => $csv])->assertForbidden();
    expect(Greasing::count())->toBe(0);
});

test('pic role cannot import schedules', function () {
    [, , $pic] = importFixtures();

    $csv = makeCsv('pic-attempt.csv', [
        ['order_number', 'plan_date', 'group', 'cycle', 'pic', 'remarks'],
        ['WO-1', '2026-08-01', 'Line 1', '4W', 'Budi', ''],
    ]);

    $this->actingAs($pic)->post(route('greasings.import'), ['file' => $csv])->assertForbidden();
    expect(Greasing::count())->toBe(0);
});
