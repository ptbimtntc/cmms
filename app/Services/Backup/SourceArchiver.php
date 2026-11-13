<?php

namespace App\Services\Backup;

use FilesystemIterator;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use Throwable;
use ZipStream\ZipStream;

/**
 * Packs the code that is actually deployed in $root (the running application
 * directory) into one ZIP file. It reads files from disk and only adds them to
 * the archive, so the originals are never modified or moved.
 *
 * Writes the ZIP with the ZipStream library (already vendored as a transitive
 * dependency of phpoffice/phpspreadsheet) instead of PHP's ZipArchive/ext-zip:
 * ZipStream only needs ext-zlib and ext-mbstring, both of which the rest of the
 * application already depends on, so source backups don't need an extension
 * that shared hosting may not expose — the same reasoning that keeps the
 * database dump off `mysqldump`.
 *
 * Included on purpose: .env, vendor/, public/build, auth.json, .git and other
 * hidden files. Excluded and reported: runtime data under storage/ (uploads,
 * logs, caches, sessions, earlier backups), bootstrap/cache compiled files,
 * node_modules and symbolic links (so nothing outside the app is pulled in).
 */
class SourceArchiver
{
    /** Directories never descended into. */
    private const PRUNED_DIRECTORIES = ['node_modules'];

    public const INFO_FILE = 'BACKUP-INFO.json';

    /**
     * @return array{files: int, excluded: array<string, int>}
     */
    public function archive(string $root, string $destination): array
    {
        foreach (['zlib', 'mbstring'] as $extension) {
            if (! extension_loaded($extension)) {
                throw new BackupStepException("Ekstensi {$extension} tidak tersedia di server.");
            }
        }

        $handle = fopen($destination, 'wb');

        if ($handle === false) {
            throw new BackupStepException('Could not create a temporary ZIP archive.');
        }

        // Writes straight to the temp file on disk, not into memory — safe for a
        // vendor/ + .git tree of any size. sendHttpHeaders is off because this
        // runs outside of an HTTP response.
        $zip = new ZipStream(outputStream: $handle, sendHttpHeaders: false);

        $excluded = [];
        $files = 0;

        try {
            foreach ($this->iterate($root, $excluded) as $file) {
                /** @var SplFileInfo $file */
                if ($file->isLink()) {
                    $this->count($excluded, 'symbolic-link');

                    continue;
                }

                if (! $file->isFile()) {
                    continue;
                }

                $relative = $this->relative($root, $file->getPathname());
                $category = $this->excludedCategory($relative);

                if ($category !== null) {
                    $this->count($excluded, $category);

                    continue;
                }

                $zip->addFileFromPath(fileName: $relative, path: $file->getPathname());
                $files++;
            }

            $zip->addFile(
                fileName: self::INFO_FILE,
                data: json_encode($this->info($root, $files, $excluded), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            );

            $zip->finish();
        } catch (Throwable $e) {
            fclose($handle);

            throw $e instanceof RuntimeException ? $e : new BackupStepException('Could not write the ZIP archive.');
        }

        fclose($handle);

        return ['files' => $files, 'excluded' => $excluded];
    }

    /**
     * Walks the tree while pruning excluded directories early.
     *
     * @param  array<string, int>  $excluded
     */
    private function iterate(string $root, array &$excluded): RecursiveIteratorIterator
    {
        $directories = new RecursiveCallbackFilterIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            function (SplFileInfo $current) use ($root, &$excluded) {
                if (! $current->isDir() || $current->isLink()) {
                    return true;
                }

                $name = $current->getFilename();
                $relative = $this->relative($root, $current->getPathname());

                if ($relative === $name && in_array($name, self::PRUNED_DIRECTORIES, true)) {
                    $this->count($excluded, 'node_modules');

                    return false;
                }

                return true;
            },
        );

        return new RecursiveIteratorIterator($directories);
    }

    /** Returns the reason a file is left out, or null when it is packed. */
    private function excludedCategory(string $relative): ?string
    {
        $name = basename($relative);

        if (str_starts_with($relative, 'storage/')) {
            // Keep the folder placeholders so Laravel can run after a restore.
            return $name === '.gitignore' ? null : 'storage-runtime';
        }

        if (str_starts_with($relative, 'bootstrap/cache/')) {
            return $name === '.gitignore' ? null : 'bootstrap-cache';
        }

        return null;
    }

    /**
     * @param  array<string, int>  $excluded
     * @return array<string, mixed>
     */
    private function info(string $root, int $files, array $excluded): array
    {
        return [
            'application' => config('app.name'),
            'generated_at' => now()->timezone(config('app.timezone'))->toIso8601String(),
            'laravel_version' => app()->version(),
            'php_version' => PHP_VERSION,
            'git' => $this->gitState($root),
            'files_included' => $files,
            'files_excluded_by_category' => $excluded,
            'notes' => [
                'storage/ runtime data (uploads, logs, cache, sessions, backups) is backed up separately, if at all.',
                'bootstrap/cache compiled files are regenerated with php artisan optimize.',
                'node_modules is a build-time dependency and is not needed to run the application.',
            ],
        ];
    }

    /** @return array{commit: string|null, branch: string|null} */
    private function gitState(string $root): array
    {
        try {
            $commit = trim(Process::path($root)->timeout(10)->run(['git', 'rev-parse', 'HEAD'])->output());
            $branch = trim(Process::path($root)->timeout(10)->run(['git', 'rev-parse', '--abbrev-ref', 'HEAD'])->output());

            return [
                'commit' => preg_match('/^[0-9a-f]{40}$/', $commit) ? $commit : null,
                'branch' => $branch !== '' ? $branch : null,
            ];
        } catch (Throwable) {
            return ['commit' => null, 'branch' => null];
        }
    }

    private function relative(string $root, string $path): string
    {
        return Str::after(str_replace('\\', '/', $path), rtrim(str_replace('\\', '/', $root), '/').'/');
    }

    /** @param  array<string, int>  $excluded */
    private function count(array &$excluded, string $category): void
    {
        $excluded[$category] = ($excluded[$category] ?? 0) + 1;
    }
}
