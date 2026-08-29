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

    /** @return null|array{size_bytes: int, content_type: string} */
    public function metadata(Document $document): ?array
    {
        try {
            $disk = $this->filesystems->disk((string) config('documents.storage_disk'));

            if (! $disk->exists($document->storage_key)) {
                return null;
            }

            return [
                'size_bytes' => $disk->size($document->storage_key),
                'content_type' => $document->media_type,
            ];
        } catch (Throwable $exception) {
            report($exception);

            throw DocumentUploadException::storageUnavailable();
        }
    }

    /** @return resource */
    public function readStream(Document $document)
    {
        try {
            $stream = $this->filesystems
                ->disk((string) config('documents.storage_disk'))
                ->readStream($document->storage_key);

            if (! is_resource($stream)) {
                throw DocumentUploadException::storageUnavailable();
            }

            return $stream;
        } catch (DocumentUploadException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            throw DocumentUploadException::storageUnavailable();
        }
    }

    /** @return resource */
    public function readRange(Document $document, int $start)
    {
        $stream = $this->readStream($document);

        if ($start === 0) {
            return $stream;
        }

        if (@fseek($stream, $start) === 0) {
            return $stream;
        }

        $remaining = $start;
        while ($remaining > 0) {
            $discarded = fread($stream, min(64 * 1024, $remaining));
            if ($discarded === false || $discarded === '') {
                fclose($stream);
                throw DocumentUploadException::storageUnavailable();
            }
            $remaining -= strlen($discarded);
        }

        return $stream;
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
