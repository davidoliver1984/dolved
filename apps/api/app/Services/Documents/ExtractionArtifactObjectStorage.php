<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\Exceptions\IngestionAttemptException;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use JsonException;
use Throwable;

class ExtractionArtifactObjectStorage
{
    public function __construct(private readonly FilesystemFactory $filesystems) {}

    /** @return array{url: string, method: 'PUT', headers: array<string, string>, expires_at: string} */
    public function createUploadRequest(string $objectKey, CarbonImmutable $expiresAt): array
    {
        try {
            $signed = $this->filesystems
                ->disk((string) config('ingestion.orchestration.extraction_artifact_upload_disk'))
                ->temporaryUploadUrl($objectKey, $expiresAt, [
                    'ContentType' => 'application/json',
                    'IfNoneMatch' => '*',
                ]);
        } catch (Throwable $exception) {
            report($exception);
            throw IngestionAttemptException::invalid('artifact_storage_unavailable', 'Extraction artifact storage is temporarily unavailable.', 503);
        }

        $headers = $this->workerHeaders($signed['headers']);
        $headers['Content-Type'] = 'application/json';
        $headers['If-None-Match'] = '*';

        return [
            'url' => $signed['url'],
            'method' => 'PUT',
            'headers' => $headers,
            'expires_at' => $expiresAt->toIso8601String(),
        ];
    }

    /** @return null|array{size_bytes: int, sha256: string, contract_version: string} */
    public function inspect(string $objectKey): ?array
    {
        try {
            $disk = $this->filesystems->disk((string) config('ingestion.orchestration.extraction_artifact_disk'));
            if (! $disk->exists($objectKey)) {
                return null;
            }
            $stream = $disk->readStream($objectKey);
            if (! is_resource($stream)) {
                throw new \RuntimeException('Artifact stream unavailable.');
            }
            $hash = hash_init('sha256');
            $size = 0;
            $content = '';
            $maximum = max(1, (int) config('ingestion.orchestration.extraction_artifact_max_bytes'));
            try {
                while (! feof($stream)) {
                    $chunk = fread($stream, 1024 * 1024);
                    if ($chunk === false) {
                        throw new \RuntimeException('Artifact stream read failed.');
                    }
                    $size += strlen($chunk);
                    if ($size > $maximum) {
                        throw IngestionAttemptException::invalid('artifact_too_large', 'The extraction artifact exceeds the configured size limit.', 422);
                    }
                    hash_update($hash, $chunk);
                    $content .= $chunk;
                }
            } finally {
                fclose($stream);
            }
            try {
                $value = json_decode($content, true, flags: JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                throw IngestionAttemptException::invalid('artifact_invalid_json', 'The extraction artifact is not valid JSON.', 422);
            }
            if (! is_array($value) || ! is_string($value['contract_version'] ?? null)) {
                throw IngestionAttemptException::invalid('artifact_contract_missing', 'The extraction artifact contract version is missing.', 422);
            }

            return ['size_bytes' => $size, 'sha256' => hash_final($hash), 'contract_version' => $value['contract_version']];
        } catch (IngestionAttemptException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);
            throw IngestionAttemptException::invalid('artifact_storage_unavailable', 'Extraction artifact storage is temporarily unavailable.', 503);
        }
    }

    public function deleteExact(string $objectKey): void
    {
        try {
            $disk = $this->filesystems->disk((string) config('ingestion.orchestration.extraction_artifact_disk'));
            if ($disk->exists($objectKey) && ! $disk->delete($objectKey)) {
                throw new \RuntimeException('Artifact delete failed.');
            }
        } catch (Throwable $exception) {
            report($exception);
            throw IngestionAttemptException::invalid('artifact_storage_unavailable', 'Extraction artifact storage is temporarily unavailable.', 503);
        }
    }

    /** @return resource */
    public function readStreamExact(string $objectKey)
    {
        try {
            $stream = $this->filesystems
                ->disk((string) config('ingestion.orchestration.extraction_artifact_disk'))
                ->readStream($objectKey);
            if (! is_resource($stream)) {
                throw new \RuntimeException('Artifact stream unavailable.');
            }

            return $stream;
        } catch (Throwable $exception) {
            report($exception);
            throw IngestionAttemptException::invalid('artifact_storage_unavailable', 'Extraction artifact storage is temporarily unavailable.', 503);
        }
    }

    /** @param array<string, mixed> $headers @return array<string, string> */
    private function workerHeaders(array $headers): array
    {
        $result = [];
        foreach ($headers as $name => $value) {
            if (in_array(strtolower((string) $name), ['host', 'content-length'], true)) {
                continue;
            }
            $result[(string) $name] = is_array($value) ? implode(', ', $value) : (string) $value;
        }

        return $result;
    }
}
