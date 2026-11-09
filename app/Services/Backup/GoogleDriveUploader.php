<?php

namespace App\Services\Backup;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Copies finished backup archives to one Google Drive folder through the
 * Drive REST API v3, using only Laravel's HTTP client (no Google SDK, which is
 * heavy for shared hosting).
 *
 * Authentication is OAuth with a refresh token from a personal Google account.
 * Files are sent with a resumable upload in fixed-size chunks read straight
 * from disk, so a source archive of several hundred MB never sits in memory.
 *
 * Error messages never contain tokens, URLs, or response bodies; at most the
 * HTTP status and Google's short error code.
 */
class GoogleDriveUploader
{
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    private const UPLOAD_URL = 'https://www.googleapis.com/upload/drive/v3/files';

    private const FILES_URL = 'https://www.googleapis.com/drive/v3/files';

    private const FOLDER_MIME = 'application/vnd.google-apps.folder';

    /** Kept only for the life of this process; never cached in the database (which is itself backed up). */
    private ?string $accessToken = null;

    private int $accessTokenExpiresAt = 0;

    public function isConfigured(): bool
    {
        return $this->clientConfigured() && filled(config('backup.google_drive.folder_id'));
    }

    /** Client ID, secret and refresh token are set (the folder may still be missing). */
    public function clientConfigured(): bool
    {
        $config = config('backup.google_drive');

        return filled($config['client_id'] ?? null)
            && filled($config['client_secret'] ?? null)
            && filled($config['refresh_token'] ?? null);
    }

    /**
     * Uploads one file into the configured folder and returns its Drive file ID.
     *
     * @throws BackupStepException
     */
    public function upload(string $absolutePath, string $name, string $mimeType = 'application/octet-stream'): string
    {
        if (! $this->isConfigured()) {
            throw new BackupStepException('Google Drive belum dikonfigurasi.');
        }

        $size = @filesize($absolutePath);
        $handle = @fopen($absolutePath, 'rb');

        if ($size === false || $handle === false) {
            throw new BackupStepException('File backup tidak dapat dibaca untuk diunggah ke Google Drive.');
        }

        try {
            $session = $this->startResumableSession($name, $mimeType, $size);

            return $this->sendChunks($session, $handle, $size);
        } finally {
            fclose($handle);
        }
    }

    /**
     * Deletes a file this app uploaded. A file that is already gone counts as deleted.
     *
     * @throws BackupStepException
     */
    public function delete(string $fileId): void
    {
        $response = $this->request(fn () => Http::withToken($this->token())
            ->timeout(30)
            ->delete(self::FILES_URL.'/'.rawurlencode($fileId)));

        if ($response->successful() || $response->status() === 404) {
            return;
        }

        throw new BackupStepException($this->failure('Gagal menghapus file lama di Google Drive', $response));
    }

    /**
     * Creates a folder in the root of the account's Drive and returns its ID.
     * With the drive.file scope the app can only see folders it created itself.
     *
     * @throws BackupStepException
     */
    public function createFolder(string $name): string
    {
        if (! $this->clientConfigured()) {
            throw new BackupStepException('Client ID, client secret, atau refresh token Google Drive belum diisi.');
        }

        $response = $this->request(fn () => Http::withToken($this->token())
            ->timeout(30)
            ->post(self::FILES_URL.'?fields=id', ['name' => $name, 'mimeType' => self::FOLDER_MIME]));

        if (! $response->successful() || ! is_string($response->json('id'))) {
            throw new BackupStepException($this->failure('Gagal membuat folder di Google Drive', $response));
        }

        return $response->json('id');
    }

    private function startResumableSession(string $name, string $mimeType, int $size): string
    {
        $response = $this->request(fn () => Http::withToken($this->token())
            ->withHeaders([
                'X-Upload-Content-Type' => $mimeType,
                'X-Upload-Content-Length' => (string) $size,
            ])
            ->timeout(30)
            ->post(self::UPLOAD_URL.'?uploadType=resumable&fields=id', [
                'name' => $name,
                'parents' => [config('backup.google_drive.folder_id')],
            ]));

        $location = $response->header('Location');

        if (! $response->successful() || $location === '') {
            throw new BackupStepException($this->failure('Gagal memulai unggahan ke Google Drive', $response));
        }

        return $location;
    }

    /** @param  resource  $handle */
    private function sendChunks(string $session, $handle, int $size): string
    {
        $chunkBytes = max(256 * 1024, (int) config('backup.google_drive.chunk_bytes'));
        $offset = 0;

        do {
            $chunk = $size === 0 ? '' : (string) fread($handle, $chunkBytes);
            $length = strlen($chunk);

            if ($size > 0 && $length === 0) {
                throw new BackupStepException('File backup berubah saat diunggah ke Google Drive.');
            }

            $range = $size === 0
                ? 'bytes */0'
                : sprintf('bytes %d-%d/%d', $offset, $offset + $length - 1, $size);

            $response = $this->request(fn () => Http::withToken($this->token())
                ->withHeaders(['Content-Range' => $range])
                ->withBody($chunk, 'application/octet-stream')
                ->withoutRedirecting()
                ->timeout(300)
                ->retry(3, 2000, fn (?Throwable $e) => $e !== null && $this->isTransient($e), throw: false)
                ->put($session));

            $offset += $length;

            if ($response->successful()) {
                $id = $response->json('id');

                if (! is_string($id) || $id === '') {
                    throw new BackupStepException('Google Drive tidak mengembalikan ID file.');
                }

                return $id;
            }

            // 308 = Google received this chunk and expects the next one.
            if ($response->status() !== 308 || $offset >= $size) {
                throw new BackupStepException($this->failure('Unggahan ke Google Drive terputus', $response));
            }
        } while (true);
    }

    private function token(): string
    {
        if ($this->accessToken !== null && time() < $this->accessTokenExpiresAt) {
            return $this->accessToken;
        }

        $config = config('backup.google_drive');

        $response = $this->request(fn () => Http::asForm()->timeout(30)->post(self::TOKEN_URL, [
            'client_id' => $config['client_id'],
            'client_secret' => $config['client_secret'],
            'refresh_token' => $config['refresh_token'],
            'grant_type' => 'refresh_token',
        ]));

        $token = $response->json('access_token');

        if (! $response->successful() || ! is_string($token) || $token === '') {
            $code = $this->errorCode($response);

            throw new BackupStepException($code === 'invalid_grant'
                ? 'Refresh token Google Drive ditolak (invalid_grant). Buat refresh token baru.'
                : $this->failure('Login ke Google Drive gagal', $response));
        }

        $this->accessToken = $token;
        // Renew a few minutes early so a long upload never starts with a nearly expired token.
        $this->accessTokenExpiresAt = time() + max(60, (int) $response->json('expires_in', 3600) - 300);

        return $token;
    }

    /** @param  callable(): Response  $send */
    private function request(callable $send): Response
    {
        try {
            return $send();
        } catch (ConnectionException) {
            throw new BackupStepException('Tidak dapat terhubung ke Google Drive. Periksa koneksi internet server.');
        }
    }

    private function isTransient(Throwable $e): bool
    {
        return $e instanceof ConnectionException
            || ($e instanceof RequestException && $e->response->serverError());
    }

    private function failure(string $prefix, Response $response): string
    {
        $code = $this->errorCode($response);

        return $prefix.' (HTTP '.$response->status().($code !== null ? ', '.$code : '').').';
    }

    /** Google's short error code only (e.g. invalid_grant, insufficientPermissions), never the full body. */
    private function errorCode(Response $response): ?string
    {
        $code = $response->json('error.errors.0.reason') ?? $response->json('error.status') ?? $response->json('error');

        return is_string($code) && preg_match('/^[A-Za-z_]{1,64}$/', $code) ? $code : null;
    }
}
