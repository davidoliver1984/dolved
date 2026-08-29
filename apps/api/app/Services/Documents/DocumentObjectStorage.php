<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\Exceptions\DocumentUploadException;
use App\Models\Document;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Throwable;

class DocumentObjectStorage
{
    public function __construct(
        private readonly FilesystemFactory $filesystems,
    ) {}

    /**
     * @return array{
     *     url: string,
     *     method: 'PUT',
     *     headers: array<string, string>,
     *     expires_at: string
     * }
     */
    public function createUploadRequest(Document $document): array
    {
        $expiresAt = CarbonImmutable::now()->addSeconds(
            (int) config('documents.presigned_url_lifetime_seconds'),
        );

        try {
            $signedRequest = $this->filesystems
                ->disk((string) config('documents.upload_disk'))
                ->temporaryUploadUrl(
                    $document->storage_key,
                    $expiresAt,
                    ['ContentType' => $document->media_type],
                );
        } catch (Throwable $exception) {
            report($exception);

            throw DocumentUploadException::storageUnavailable();
        }

        return [
            'url' => $signedRequest['url'],
            'method' => 'PUT',
            'headers' => $this->browserHeaders($signedRequest['headers']),
            'expires_at' => $expiresAt->toIso8601String(),
        ];
    }

    /** @return null|array{size_bytes: int, sha256: string} */
    public function streamedIdentity(Document $document): ?array
    {
        try {
            $disk = $this->filesystems->disk(
                (string) config('documents.storage_disk'),
            );

            if (! $disk->exists($document->storage_key)) {
                return null;
            }

            $stream = $disk->readStream($document->storage_key);

            if (! is_resource($stream)) {
                throw DocumentUploadException::storageUnavailable();
            }

            $hash = hash_init('sha256');
            $sizeBytes = 0;

            try {
                while (! feof($stream)) {
                    $chunk = fread($stream, 1024 * 1024);

                    if ($chunk === false) {
                        throw DocumentUploadException::storageUnavailable();
                    }

                    $sizeBytes += strlen($chunk);
                    hash_update($hash, $chunk);
                }
            } finally {
                fclose($stream);
            }

            return [
                'size_bytes' => $sizeBytes,
                'sha256' => hash_final($hash),
            ];
        } catch (DocumentUploadException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            throw DocumentUploadException::storageUnavailable();
        }
    }

    public function delete(Document $document): void
    {
        try {
            $disk = $this->filesystems->disk(
                (string) config('documents.storage_disk'),
            );

            if ($disk->exists($document->storage_key) && ! $disk->delete($document->storage_key)) {
                throw DocumentUploadException::storageUnavailable();
            }
        } catch (DocumentUploadException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            throw DocumentUploadException::storageUnavailable();
        }
    }

    /**
     * @param  array<string, mixed>  $headers
     * @return array<string, string>
     */
    private function browserHeaders(array $headers): array
    {
        $browserHeaders = [];

        foreach ($headers as $name => $value) {
            $normalisedName = strtolower($name);

            if (
                $normalisedName === 'host'
                || $normalisedName === 'content-length'
            ) {
                continue;
            }

            $browserHeaders[$name] = is_array($value)
                ? implode(', ', $value)
                : (string) $value;
        }

        return $browserHeaders;
    }
}
