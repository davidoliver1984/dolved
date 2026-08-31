<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\Exceptions\DocumentUploadException;
use App\Models\ImportItem;
use App\Models\Workspace;
use App\Support\Documents\ImportStagingObjectKey;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Throwable;

class ImportStagingStorage
{
    public function __construct(
        private readonly FilesystemFactory $filesystems,
        private readonly ImportStagingObjectKey $keys,
    ) {}

    /** @return array{url: string, method: 'PUT', headers: array<string, string>, expires_at: string} */
    public function createUploadRequest(Workspace $workspace, ImportItem $item, string $mediaType): array
    {
        $key = $this->keys->assertExact($workspace, $item);
        $expiresAt = CarbonImmutable::now()->addSeconds(
            (int) config('imports.presigned_url_lifetime_seconds'),
        );

        try {
            $request = $this->filesystems
                ->disk((string) config('imports.staging_disk'))
                ->temporaryUploadUrl($key, $expiresAt, ['ContentType' => $mediaType]);
        } catch (Throwable $exception) {
            report($exception);
            throw DocumentUploadException::storageUnavailable();
        }

        return [
            'url' => $request['url'],
            'method' => 'PUT',
            'headers' => array_filter(
                $request['headers'],
                static fn (string $name): bool => ! in_array(strtolower($name), ['host', 'content-length'], true),
                ARRAY_FILTER_USE_KEY,
            ),
            'expires_at' => $expiresAt->toIso8601String(),
        ];
    }

    public function deleteExact(Workspace $workspace, ImportItem $item): void
    {
        $key = $this->keys->assertExact($workspace, $item);

        try {
            $disk = $this->filesystems->disk((string) config('imports.storage_disk'));
            if ($disk->exists($key) && ! $disk->delete($key)) {
                throw DocumentUploadException::storageUnavailable();
            }
        } catch (DocumentUploadException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);
            throw DocumentUploadException::storageUnavailable();
        }
    }

    /** @return array{key: string, read_url: string, expires_at: string} */
    public function createPreflightReadRequest(Workspace $workspace, ImportItem $item): array
    {
        $key = $this->keys->assertExact($workspace, $item);
        $expiresAt = CarbonImmutable::now()->addSeconds(
            (int) config('imports.preflight.lease_seconds'),
        );

        try {
            $url = $this->filesystems
                ->disk((string) config('imports.storage_disk'))
                ->temporaryUrl($key, $expiresAt);
        } catch (Throwable $exception) {
            report($exception);
            throw DocumentUploadException::storageUnavailable();
        }

        return [
            'key' => $key,
            'read_url' => $url,
            'expires_at' => $expiresAt->toIso8601String(),
        ];
    }

    public function exactSize(Workspace $workspace, ImportItem $item): int
    {
        $key = $this->keys->assertExact($workspace, $item);
        try {
            return $this->filesystems
                ->disk((string) config('imports.storage_disk'))
                ->size($key);
        } catch (Throwable $exception) {
            report($exception);
            throw DocumentUploadException::storageUnavailable();
        }
    }
}
