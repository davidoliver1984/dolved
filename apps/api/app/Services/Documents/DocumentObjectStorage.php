<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\Exceptions\DocumentUploadException;
use App\Models\Document;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Filesystem\AwsS3V3Adapter;
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
            if (is_string($document->storage_version_id) && $document->storage_version_id !== '') {
                return $this->versionedIdentity($document);
            }
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
            if (is_string($document->storage_version_id) && $document->storage_version_id !== '') {
                $identity = $this->versionedHead($document);

                return ['size_bytes' => $identity['size_bytes'], 'content_type' => $identity['content_type']];
            }
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
            if (is_string($document->storage_version_id) && $document->storage_version_id !== '') {
                return $this->versionedReadStream($document);
            }
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
            if (is_string($document->storage_version_id) && $document->storage_version_id !== '') {
                $this->s3Disk()->getClient()->deleteObject([
                    'Bucket' => (string) config('filesystems.disks.s3.bucket'),
                    'Key' => $document->storage_key,
                    'VersionId' => $document->storage_version_id,
                ]);

                return;
            }
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

    /** @return array{size_bytes: int, sha256: string} */
    public function copy(Document $source, Document $target): array
    {
        try {
            $disk = $this->filesystems->disk((string) config('documents.storage_disk'));
            if (! $disk->exists($source->storage_key)) {
                throw DocumentUploadException::storageUnavailable();
            }
            if ($disk->exists($target->storage_key) || ! $disk->copy($source->storage_key, $target->storage_key)) {
                throw DocumentUploadException::storageUnavailable();
            }
        } catch (DocumentUploadException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);
            throw DocumentUploadException::storageUnavailable();
        }

        $identity = $this->streamedIdentity($target);
        if ($identity === null) {
            throw DocumentUploadException::storageUnavailable();
        }

        return $identity;
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

    /** @return array{size_bytes: int, sha256: string} */
    private function versionedIdentity(Document $document): array
    {
        $stream = $this->versionedReadStream($document);
        $hash = hash_init('sha256');
        $size = 0;
        try {
            while (! feof($stream)) {
                $chunk = fread($stream, 1024 * 1024);
                if ($chunk === false) {
                    throw DocumentUploadException::storageUnavailable();
                }
                $size += strlen($chunk);
                hash_update($hash, $chunk);
            }
        } finally {
            fclose($stream);
        }

        return ['size_bytes' => $size, 'sha256' => hash_final($hash)];
    }

    /** @return array{size_bytes: int, content_type: string} */
    private function versionedHead(Document $document): array
    {
        $result = $this->s3Disk()->getClient()->headObject([
            'Bucket' => (string) config('filesystems.disks.s3.bucket'),
            'Key' => $document->storage_key,
            'VersionId' => $document->storage_version_id,
        ]);

        return [
            'size_bytes' => (int) $result['ContentLength'],
            'content_type' => (string) ($result['ContentType'] ?? $document->media_type),
        ];
    }

    /** @return resource */
    private function versionedReadStream(Document $document)
    {
        $result = $this->s3Disk()->getClient()->getObject([
            'Bucket' => (string) config('filesystems.disks.s3.bucket'),
            'Key' => $document->storage_key,
            'VersionId' => $document->storage_version_id,
        ]);
        $stream = $result['Body']->detach();
        if (! is_resource($stream)) {
            throw DocumentUploadException::storageUnavailable();
        }

        return $stream;
    }

    private function s3Disk(): AwsS3V3Adapter
    {
        $disk = $this->filesystems->disk((string) config('documents.storage_disk'));
        if (! $disk instanceof AwsS3V3Adapter) {
            throw DocumentUploadException::storageUnavailable();
        }

        return $disk;
    }
}
