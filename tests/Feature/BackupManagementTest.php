<?php

use App\Models\BackupRun;
use App\Models\User;
use App\Services\Backup\BackupManager;
use App\Services\Backup\BackupStepException;
use App\Services\Backup\DatabaseDumper;
use App\Services\Backup\SourceArchiver;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function () {
    // Real archives go to the private disk; a fake disk keeps test files out of storage/app/private.
    Storage::fake('local');
});

function backupRunOn(string $type, array $attributes = []): BackupRun
{
    $name = $attributes['file_name'] ?? ($type === BackupRun::TYPE_DATABASE ? 'freedoms-db-test.sql.gz' : 'freedoms-source-test.zip');

    Storage::disk('local')->put(BackupRun::DIRECTORY.'/'.$name, 'archive');

    return BackupRun::create(array_merge([
        'type' => $type,
        'status' => BackupRun::STATUS_SUCCEEDED,
        'file_name' => $name,
        'file_size' => 7,
        'started_at' => now(),
        'finished_at' => now(),
    ], $attributes));
}

function backupDumpContents(BackupRun $run): string
{
    return gzdecode(file_get_contents($run->absolutePath()));
}

describe('access control', function () {
    test('guests are redirected to login', function () {
        $this->get('/backup')->assertRedirect('/login');
        $this->post('/backup/database')->assertRedirect('/login');
    });

    test('only ADMIN can open the page and run backups', function (User $user) {
        $run = backupRunOn(BackupRun::TYPE_DATABASE);

        $this->actingAs($user)->get('/backup')->assertForbidden();
        $this->actingAs($user)->post('/backup/database')->assertForbidden();
        $this->actingAs($user)->post('/backup/source')->assertForbidden();
        $this->actingAs($user)->get(route('backup.download', $run))->assertForbidden();

        expect(BackupRun::count())->toBe(1);
        expect($run->fresh()->download_started_at)->toBeNull();
    })->with([
        'koordinator' => fn () => User::factory()->koordinator()->create(),
        'pic' => fn () => User::factory()->pic()->create(),
        'supervisor' => fn () => User::factory()->supervisor()->create(),
        'guest' => fn () => User::factory()->guest()->create(),
    ]);
});

describe('page', function () {
    test('admin sees both sections and never the secrets or server paths', function () {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get('/backup')->assertOk();

        $response->assertSee('Backup Database')
            ->assertSee('Backup Source Code Production')
            ->assertSee('Backup &amp; Data Protection', false)
            ->assertSee('Belum pernah');

        if (config('app.key')) {
            $response->assertDontSee(config('app.key'));
        }
        $response->assertDontSee(base_path());
        $response->assertDontSee(storage_path());
    });

    test('download button is hidden when the archive was removed from disk', function () {
        $admin = User::factory()->admin()->create();
        $run = backupRunOn(BackupRun::TYPE_DATABASE, ['file_removed_at' => now()]);
        Storage::disk('local')->delete($run->relativePath());

        $this->actingAs($admin)->get('/backup')
            ->assertOk()
            ->assertDontSee(route('backup.download', $run))
            ->assertSee('Tidak ada file backup yang tersedia');
    });
});

describe('database backup', function () {
    test('creates a complete gzip dump with structure and data', function () {
        $admin = User::factory()->admin()->create();
        User::factory()->create(['email' => 'marker-backup@example.test']);

        $this->actingAs($admin)->post('/backup/database')
            ->assertRedirect(route('backup.index'))
            ->assertSessionHas('success');

        $run = BackupRun::sole();

        expect($run->type)->toBe(BackupRun::TYPE_DATABASE)
            ->and($run->status)->toBe(BackupRun::STATUS_SUCCEEDED)
            ->and($run->file_name)->toMatch('/^freedoms-db-\d{8}-\d{4}\.sql\.gz$/')
            ->and($run->file_size)->toBeGreaterThan(0)
            ->and($run->created_by_user_id)->toBe($admin->id)
            ->and($run->finished_at)->not->toBeNull();

        $sql = backupDumpContents($run);

        // SQLite stores DDL exactly as Laravel wrote it (lower-case), so match case-insensitively.
        expect($sql)
            ->toMatch('/create table "users"/i')
            ->toMatch('/create table "email_notification_settings"/i')
            ->toContain('marker-backup@example.test')
            ->toMatch('/create unique index/i');

        // Every base table in the schema must be present, not only a hand-picked list.
        $tables = collect(DB::select("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'"))->pluck('name');

        foreach ($tables as $table) {
            expect($sql)->toMatch('/create table "'.preg_quote($table, '/').'"/i');
        }
    });

    test('the dump restores into an empty database with the same rows', function () {
        $admin = User::factory()->admin()->create();
        User::factory()->create(['email' => 'roundtrip@example.test', 'name' => 'Roundtrip Name']);

        $this->actingAs($admin)->post('/backup/database');
        $sql = backupDumpContents(BackupRun::sole());

        config(['database.connections.restore' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        $restored = DB::connection('restore');
        $restored->getPdo()->exec($sql);

        expect($restored->table('users')->where('email', 'roundtrip@example.test')->value('name'))
            ->toBe('Roundtrip Name');
    });

    test('a failed dump is recorded as failed and does not move the last success timestamp', function () {
        $admin = User::factory()->admin()->create();
        $previous = backupRunOn(BackupRun::TYPE_DATABASE, ['finished_at' => now()->subDays(2)]);

        $this->mock(DatabaseDumper::class)
            ->shouldReceive('dumpTo')->once()
            ->andThrow(new BackupStepException('Gagal membuat dump'));

        $this->actingAs($admin)->post('/backup/database')
            ->assertRedirect(route('backup.index'))
            ->assertSessionHas('error');

        $failed = BackupRun::where('status', BackupRun::STATUS_FAILED)->sole();

        expect($failed->file_name)->toBeNull()
            ->and($failed->error_message)->toBe('Gagal membuat dump')
            ->and(app(BackupManager::class)->lastSucceeded(BackupRun::TYPE_DATABASE)->is($previous))->toBeTrue()
            ->and($previous->fresh()->finished_at->equalTo($previous->finished_at))->toBeTrue();
    });

    test('unexpected errors show a generic message and no internal detail', function () {
        $admin = User::factory()->admin()->create();

        $this->mock(DatabaseDumper::class)
            ->shouldReceive('dumpTo')->once()
            ->andThrow(new PDOException('SQLSTATE[HY000] secret-host.internal refused'));

        $response = $this->actingAs($admin)->post('/backup/database')->assertRedirect(route('backup.index'));

        expect(BackupRun::sole()->error_message)->toBe('Gagal membuat backup database. Periksa log server.');
        $response->assertSessionHas('error', fn ($message) => ! str_contains($message, 'secret-host'));
    });

    test('a second backup of the same type is refused while the first is running', function () {
        $admin = User::factory()->admin()->create();
        $held = Cache::lock('backup:'.BackupRun::TYPE_DATABASE, 60);
        expect($held->get())->toBeTrue();

        $this->actingAs($admin)->post('/backup/database')
            ->assertRedirect(route('backup.index'))
            ->assertSessionHas('error');

        expect(BackupRun::count())->toBe(0);

        $held->release();
    });
});

describe('source code backup', function () {
    test('stores the archive and records it when the archiver succeeds', function () {
        $admin = User::factory()->admin()->create();

        $this->mock(SourceArchiver::class)
            ->shouldReceive('archive')->once()
            ->andReturnUsing(function (string $root, string $destination) {
                file_put_contents($destination, 'PK-test-archive');

                return ['files' => 1, 'excluded' => []];
            });

        $this->actingAs($admin)->post('/backup/source')->assertSessionHas('success');

        $run = BackupRun::sole();

        expect($run->type)->toBe(BackupRun::TYPE_SOURCE)
            ->and($run->file_name)->toMatch('/^freedoms-source-\d{8}-\d{4}\.zip$/')
            ->and($run->file_size)->toBe(strlen('PK-test-archive'));
        expect(Storage::disk('local')->exists(BackupRun::DIRECTORY.'/'.$run->file_name))->toBeTrue();
        expect(Storage::disk('local')->files(BackupRun::DIRECTORY.'/tmp'))->toBeEmpty();
    });

    test('a failed archive leaves no partial file and no success record', function () {
        $admin = User::factory()->admin()->create();

        $this->mock(SourceArchiver::class)
            ->shouldReceive('archive')->once()
            ->andReturnUsing(function (string $root, string $destination) {
                file_put_contents($destination, 'partial');

                throw new RuntimeException('Gagal menulis arsip ZIP.');
            });

        $this->actingAs($admin)->post('/backup/source');

        $run = BackupRun::sole();

        expect($run->status)->toBe(BackupRun::STATUS_FAILED)
            ->and(app(BackupManager::class)->lastSucceeded(BackupRun::TYPE_SOURCE))->toBeNull();
        expect(Storage::disk('local')->files(BackupRun::DIRECTORY.'/tmp'))->toBeEmpty();
        expect(Storage::disk('local')->files(BackupRun::DIRECTORY))->toBeEmpty();
    });
});

describe('source archiver', function () {
    test('includes hidden files, .env and vendor, and excludes runtime data and links', function () {
        $root = sys_get_temp_dir().'/freedoms-src-'.uniqid();
        $outside = sys_get_temp_dir().'/freedoms-outside-'.uniqid();
        $destination = $outside.'/out.zip';

        foreach ([
            '.env' => 'APP_KEY=base64:test',
            'app/Http/Foo.php' => '<?php',
            '.hidden/note.txt' => 'hidden',
            'vendor/autoload.php' => '<?php',
            'public/build/app.js' => 'js',
            'storage/app/public/avatar.png' => 'png',
            'storage/app/.gitignore' => '*',
            'storage/logs/laravel.log' => 'log',
            'bootstrap/cache/config.php' => '<?php // cached',
            'bootstrap/cache/.gitignore' => '*',
            'node_modules/pkg/index.js' => 'dep',
        ] as $relative => $content) {
            @mkdir(dirname($root.'/'.$relative), 0777, true);
            file_put_contents($root.'/'.$relative, $content);
        }

        @mkdir($outside, 0777, true);
        file_put_contents($outside.'/secret.txt', 'outside');
        symlink($outside.'/secret.txt', $root.'/public/leak.txt');

        $result = app(SourceArchiver::class)->archive($root, $destination);

        // Read back with PharData (ext-phar) rather than ZipArchive (ext-zip) — this
        // asserts against a real archive written by the ZipStream-based implementation,
        // without requiring ext-zip to be installed anywhere, mirroring production.
        $phar = new PharData($destination);
        $prefix = 'phar://'.$destination.'/';
        $names = collect(iterator_to_array(new RecursiveIteratorIterator($phar)))
            ->map(fn ($file) => Str::after($file->getPathname(), $prefix));

        expect($names)
            ->toContain('.env', '.hidden/note.txt', 'vendor/autoload.php', 'public/build/app.js', 'app/Http/Foo.php')
            ->toContain('storage/app/.gitignore', 'bootstrap/cache/.gitignore', SourceArchiver::INFO_FILE)
            ->not->toContain('storage/app/public/avatar.png', 'storage/logs/laravel.log', 'bootstrap/cache/config.php')
            ->not->toContain('node_modules/pkg/index.js', 'public/leak.txt');

        expect($result['excluded'])->toHaveKeys(['storage-runtime', 'bootstrap-cache', 'node_modules', 'symbolic-link']);

        // The originals must be untouched.
        expect(file_get_contents($root.'/.env'))->toBe('APP_KEY=base64:test');
        expect(file_exists($root.'/storage/app/public/avatar.png'))->toBeTrue();

        File::deleteDirectory($root);
        File::deleteDirectory($outside);
    });
});

describe('download', function () {
    test('admin download streams the file as an attachment and records the start time', function () {
        $admin = User::factory()->admin()->create();
        $run = backupRunOn(BackupRun::TYPE_DATABASE);

        $response = $this->actingAs($admin)->get(route('backup.download', $run));

        $response->assertOk()
            ->assertDownload($run->file_name);

        expect($response->headers->get('Cache-Control'))->toContain('no-store')->toContain('private')->not->toContain('public');

        $fresh = $run->fresh();
        expect($fresh->download_started_at)->not->toBeNull()
            ->and($fresh->downloaded_by_user_id)->toBe($admin->id);
        expect(app(BackupManager::class)->lastDownloadStarted(BackupRun::TYPE_DATABASE)->is($run))->toBeTrue();
    });

    test('a removed archive is refused and its download time is not touched', function () {
        $admin = User::factory()->admin()->create();
        $run = backupRunOn(BackupRun::TYPE_SOURCE, ['file_removed_at' => now()]);
        Storage::disk('local')->delete($run->relativePath());

        $this->actingAs($admin)->get(route('backup.download', $run))
            ->assertRedirect(route('backup.index'))
            ->assertSessionHas('error');

        expect($run->fresh()->download_started_at)->toBeNull();
    });

    test('archives are never reachable through a public URL', function () {
        $run = backupRunOn(BackupRun::TYPE_DATABASE);

        // Laravel's storage route for the local disk only answers signed URLs, so the archive body must never leak.
        foreach (['/'.BackupRun::DIRECTORY.'/'.$run->file_name, '/storage/'.BackupRun::DIRECTORY.'/'.$run->file_name] as $url) {
            $this->get($url)->assertDontSee('archive');
            expect($this->get($url)->getStatusCode())->not->toBe(200);
        }
    });
});

describe('retention', function () {
    test('only the newest archives stay on disk and older runs are marked removed', function () {
        $admin = User::factory()->admin()->create();
        $old = collect(range(1, BackupManager::KEEP_FILES_PER_TYPE + 1))->map(fn ($i) => backupRunOn(BackupRun::TYPE_DATABASE, [
            'file_name' => "freedoms-db-old-{$i}.sql.gz",
            'finished_at' => now()->subDays(10 - $i),
        ]));

        $this->actingAs($admin)->post('/backup/database')->assertSessionHas('success');

        $oldest = $old->first()->fresh();

        expect($oldest->file_removed_at)->not->toBeNull()
            ->and(Storage::disk('local')->exists($oldest->relativePath()))->toBeFalse();
        expect(BackupRun::succeeded()->whereNull('file_removed_at')->count())->toBe(BackupManager::KEEP_FILES_PER_TYPE);

        $this->actingAs($admin)->get(route('backup.download', $oldest))->assertRedirect(route('backup.index'));
    });
});

describe('reminder', function () {
    test('reminds the admin when no database backup exists yet', function () {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/backup')->assertOk()->assertSee('Pengingat backup');
    });

    test('no reminder while every backup is recent', function () {
        $admin = User::factory()->admin()->create();
        backupRunOn(BackupRun::TYPE_DATABASE, ['finished_at' => now()->subDay()]);
        backupRunOn(BackupRun::TYPE_SOURCE, ['finished_at' => now()->subDay()]);

        $this->actingAs($admin)->get('/backup')->assertOk()->assertDontSee('Pengingat backup');
        expect(app(BackupManager::class)->reminderNeeded())->toBeFalse();
    });

    test('the reminder never shows outside the backup page', function () {
        $admin = User::factory()->admin()->create();

        // No backup has ever been made, so the reminder would fire everywhere if it were still included elsewhere.
        $this->actingAs($admin)->get('/dashboard')->assertOk()->assertDontSee('Pengingat backup');
    });

    test('reminder becomes due once the last successful backup is older than the window', function () {
        $admin = User::factory()->admin()->create();
        backupRunOn(BackupRun::TYPE_DATABASE, ['finished_at' => now()->subDays(8)]);
        backupRunOn(BackupRun::TYPE_SOURCE, ['finished_at' => now()]);

        $this->actingAs($admin)->get('/backup')
            ->assertOk()
            ->assertSee('Pengingat backup')
            ->assertSee('sudah lebih dari 7 hari');

        expect(app(BackupManager::class)->reminders()[BackupRun::TYPE_DATABASE]['state'])->toBe(BackupManager::REMINDER_DUE);
    });

    test('a failed run does not clear the reminder', function () {
        $admin = User::factory()->admin()->create();
        BackupRun::create([
            'type' => BackupRun::TYPE_DATABASE,
            'status' => BackupRun::STATUS_FAILED,
            'started_at' => now(),
            'finished_at' => now(),
        ]);

        expect(app(BackupManager::class)->reminders()[BackupRun::TYPE_DATABASE]['state'])
            ->toBe(BackupManager::REMINDER_NEVER);
    });
});
