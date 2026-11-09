<?php

use App\Models\BackupRun;
use App\Models\User;
use App\Services\Backup\BackupManager;
use App\Services\Backup\DatabaseDumper;
use App\Services\Backup\SourceArchiver;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

const DRIVE_SESSION = 'https://www.googleapis.com/upload/drive/v3/files?upload_id=session-1';

beforeEach(function () {
    Storage::fake('local');

    // Archivers are mocked so the tests exercise scheduling and upload only.
    $this->mock(DatabaseDumper::class)->shouldReceive('dumpTo')
        ->andReturnUsing(fn (string $path) => file_put_contents($path, 'db-dump'));
    $this->mock(SourceArchiver::class)->shouldReceive('archive')
        ->andReturnUsing(function (string $root, string $path) {
            file_put_contents($path, 'PK-source');

            return ['files' => 1, 'excluded' => []];
        });
});

function configureDrive(): void
{
    config([
        'backup.google_drive.client_id' => 'client-id',
        'backup.google_drive.client_secret' => 'client-secret',
        'backup.google_drive.refresh_token' => 'refresh-secret',
        'backup.google_drive.folder_id' => 'folder-123',
    ]);
}

function fakeDrive(array $overrides = []): void
{
    Http::fake(array_merge([
        'oauth2.googleapis.com/token' => Http::response(['access_token' => 'access-secret', 'expires_in' => 3599]),
        'www.googleapis.com/upload/drive/v3/files?upload_id=*' => Http::response(['id' => 'drive-file-1']),
        'www.googleapis.com/upload/drive/v3/files*' => Http::response('', 200, ['Location' => DRIVE_SESSION]),
        'www.googleapis.com/drive/v3/files/*' => Http::response('', 204),
    ], $overrides));
}

describe('google drive copy', function () {
    test('a successful backup is uploaded to the configured folder', function () {
        configureDrive();
        fakeDrive();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/backup/database')->assertSessionHas('success');

        $run = BackupRun::sole();

        expect($run->status)->toBe(BackupRun::STATUS_SUCCEEDED)
            ->and($run->trigger)->toBe(BackupRun::TRIGGER_MANUAL)
            ->and($run->drive_file_id)->toBe('drive-file-1')
            ->and($run->drive_uploaded_at)->not->toBeNull()
            ->and($run->drive_error)->toBeNull();

        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'uploadType=resumable')
            && $r['parents'] === ['folder-123']
            && $r['name'] === $run->file_name
            && $r->hasHeader('Authorization', 'Bearer access-secret'));
        Http::assertSent(fn (Request $r) => $r->url() === DRIVE_SESSION
            && $r->method() === 'PUT'
            && $r->body() === 'db-dump'
            && $r->hasHeader('Content-Range', 'bytes 0-6/7'));
    });

    test('large files are sent in chunks', function () {
        configureDrive();
        config(['backup.google_drive.chunk_bytes' => 256 * 1024]);
        $this->mock(DatabaseDumper::class)->shouldReceive('dumpTo')
            ->andReturnUsing(fn (string $path) => file_put_contents($path, str_repeat('a', 256 * 1024 + 10)));
        fakeDrive([
            'www.googleapis.com/upload/drive/v3/files?upload_id=*' => Http::sequence()
                ->push('', 308, ['Range' => 'bytes=0-262143'])
                ->push(['id' => 'drive-big']),
        ]);

        app(BackupManager::class)->create(BackupRun::TYPE_DATABASE, null);

        expect(BackupRun::sole()->drive_error)->toBeNull();
        expect(BackupRun::sole()->drive_file_id)->toBe('drive-big');
        Http::assertSent(fn (Request $r) => $r->hasHeader('Content-Range', 'bytes 0-262143/262154'));
        Http::assertSent(fn (Request $r) => $r->hasHeader('Content-Range', 'bytes 262144-262153/262154'));
    });

    test('a failed upload keeps the backup successful and records a safe message', function () {
        configureDrive();
        fakeDrive(['oauth2.googleapis.com/token' => Http::response(['error' => 'invalid_grant'], 400)]);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/backup/database')->assertSessionHas('success');

        $run = BackupRun::sole();

        expect($run->status)->toBe(BackupRun::STATUS_SUCCEEDED)
            ->and($run->isDownloadable())->toBeTrue()
            ->and($run->drive_file_id)->toBeNull()
            ->and($run->drive_error)->toContain('invalid_grant')
            ->and($run->drive_error)->not->toContain('refresh-secret')
            ->and($run->drive_error)->not->toContain('client-secret');
    });

    test('nothing is sent when drive is not configured', function () {
        Http::fake();

        app(BackupManager::class)->create(BackupRun::TYPE_DATABASE, null);

        expect(BackupRun::sole()->drive_file_id)->toBeNull()
            ->and(BackupRun::sole()->drive_error)->toBeNull();
        Http::assertNothingSent();
    });

    test('only the newest copies stay on drive', function () {
        configureDrive();
        config(['backup.google_drive.keep.database' => 2]);
        fakeDrive();

        foreach (['old-a', 'old-b'] as $id) {
            BackupRun::create([
                'type' => BackupRun::TYPE_DATABASE,
                'status' => BackupRun::STATUS_SUCCEEDED,
                'file_name' => "{$id}.sql.gz",
                'started_at' => now(),
                'finished_at' => now(),
                'drive_file_id' => $id,
                'drive_uploaded_at' => now(),
            ]);
        }
        // A source copy must not count toward the database limit.
        BackupRun::create([
            'type' => BackupRun::TYPE_SOURCE,
            'status' => BackupRun::STATUS_SUCCEEDED,
            'file_name' => 'src.zip',
            'started_at' => now(),
            'finished_at' => now(),
            'drive_file_id' => 'src-1',
        ]);

        app(BackupManager::class)->create(BackupRun::TYPE_DATABASE, null);

        expect(BackupRun::where('drive_file_id', 'old-a')->sole()->drive_removed_at)->not->toBeNull()
            ->and(BackupRun::where('drive_file_id', 'old-b')->sole()->drive_removed_at)->toBeNull()
            ->and(BackupRun::where('drive_file_id', 'src-1')->sole()->drive_removed_at)->toBeNull();
        Http::assertSent(fn (Request $r) => $r->method() === 'DELETE' && str_ends_with($r->url(), '/files/old-a'));
        Http::assertNotSent(fn (Request $r) => $r->method() === 'DELETE' && str_ends_with($r->url(), '/files/old-b'));
    });
});

describe('scheduled backups', function () {
    test('the first run creates both types as scheduled runs with no user', function () {
        $this->artisan('backup:scheduled')->assertSuccessful();

        $runs = BackupRun::all();

        expect($runs)->toHaveCount(2)
            ->and($runs->pluck('type')->sort()->values()->all())->toBe([BackupRun::TYPE_DATABASE, BackupRun::TYPE_SOURCE])
            ->and($runs->every(fn (BackupRun $r) => $r->isScheduled() && $r->created_by_user_id === null))->toBeTrue();
    });

    test('database runs weekly and source code every two weeks', function () {
        $this->artisan('backup:scheduled')->assertSuccessful();

        $this->travel(3)->days();
        $this->artisan('backup:scheduled')->assertSuccessful();
        expect(BackupRun::count())->toBe(2);

        $this->travel(4)->days();
        $this->artisan('backup:scheduled')->assertSuccessful();
        expect(BackupRun::ofType(BackupRun::TYPE_DATABASE)->count())->toBe(2)
            ->and(BackupRun::ofType(BackupRun::TYPE_SOURCE)->count())->toBe(1);

        $this->travel(7)->days();
        $this->artisan('backup:scheduled')->assertSuccessful();
        expect(BackupRun::ofType(BackupRun::TYPE_DATABASE)->count())->toBe(3)
            ->and(BackupRun::ofType(BackupRun::TYPE_SOURCE)->count())->toBe(2);
    });

    test('a manual backup does not postpone the scheduled one', function () {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->post('/backup/database');

        expect(app(BackupManager::class)->isDue(BackupRun::TYPE_DATABASE))->toBeTrue();
    });

    test('force and type options run only the requested type', function () {
        $this->artisan('backup:scheduled', ['--type' => ['source']])->assertSuccessful();
        $this->artisan('backup:scheduled', ['--type' => ['source'], '--force' => true])->assertSuccessful();

        expect(BackupRun::ofType(BackupRun::TYPE_SOURCE)->count())->toBe(2)
            ->and(BackupRun::ofType(BackupRun::TYPE_DATABASE)->count())->toBe(0);
    });

    test('a failed drive upload makes the command exit non-zero', function () {
        configureDrive();
        fakeDrive(['www.googleapis.com/upload/drive/v3/files*' => Http::response(['error' => ['errors' => [['reason' => 'storageQuotaExceeded']]]], 403)]);

        $this->artisan('backup:scheduled', ['--type' => ['database']])
            ->expectsOutputToContain('storageQuotaExceeded')
            ->assertFailed();
    });

    test('the daily check is registered with the scheduler', function () {
        $this->artisan('schedule:list')->expectsOutputToContain('backup:scheduled')->assertSuccessful();
    });
});

describe('backup page', function () {
    test('shows the schedule and that drive is not set up yet', function () {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/backup')->assertOk()
            ->assertSee('Database tiap 7 hari, source code tiap 14 hari')
            ->assertSee('Belum diatur')
            ->assertSee('cron <code>schedule:run</code>', false);
    });

    test('shows scheduled runs, drive copies and drive errors without secrets', function () {
        configureDrive();
        $admin = User::factory()->admin()->create();
        BackupRun::create([
            'type' => BackupRun::TYPE_DATABASE,
            'status' => BackupRun::STATUS_SUCCEEDED,
            'trigger' => BackupRun::TRIGGER_SCHEDULED,
            'file_name' => 'freedoms-db-a.sql.gz',
            'started_at' => now()->subDay(),
            'finished_at' => now()->subDay(),
            'drive_file_id' => 'drive-a',
            'drive_uploaded_at' => now()->subDay(),
        ]);
        BackupRun::create([
            'type' => BackupRun::TYPE_DATABASE,
            'status' => BackupRun::STATUS_SUCCEEDED,
            'file_name' => 'freedoms-db-b.sql.gz',
            'started_at' => now(),
            'finished_at' => now(),
            'drive_error' => 'Unggahan ke Google Drive terputus (HTTP 403, storageQuotaExceeded).',
        ]);

        $this->actingAs($admin)->get('/backup')->assertOk()
            ->assertSee('Aktif')
            ->assertSee('Otomatis')
            ->assertSee('Tersimpan')
            ->assertSee('Salinan Google Drive gagal')
            ->assertSee('storageQuotaExceeded')
            ->assertDontSee('refresh-secret')
            ->assertDontSee('client-secret')
            ->assertDontSee('drive-a');
    });

    test('source code reminder follows its two-week schedule', function () {
        BackupRun::create([
            'type' => BackupRun::TYPE_SOURCE,
            'status' => BackupRun::STATUS_SUCCEEDED,
            'file_name' => 'src.zip',
            'started_at' => now()->subDays(10),
            'finished_at' => now()->subDays(10),
        ]);

        expect(app(BackupManager::class)->reminders()[BackupRun::TYPE_SOURCE]['state'])->toBe(BackupManager::REMINDER_CURRENT);

        $this->travel(5)->days();

        expect(app(BackupManager::class)->reminders()[BackupRun::TYPE_SOURCE]['state'])->toBe(BackupManager::REMINDER_DUE);
    });
});
