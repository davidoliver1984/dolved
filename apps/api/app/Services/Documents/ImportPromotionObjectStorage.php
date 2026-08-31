<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\Exceptions\ImportPromotionException;
use App\Models\ImportItem;
use App\Models\Workspace;
use App\Support\Documents\ImportStagingObjectKey;
use Aws\Exception\AwsException;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Filesystem\AwsS3V3Adapter;
use Throwable;

class ImportPromotionObjectStorage
{
    public function __construct(
        private readonly FilesystemFactory $filesystems,
        private readonly ImportStagingObjectKey $stagingKeys,
    ) {}

    public function reservedKey(Workspace $workspace, ImportItem $item): string
    {
        if (! is_string($item->source_checksum_sha256)) {
            throw ImportPromotionException::conflict('source_not_verified');
        }

        return sprintf(
            'workspaces/%s/imports/%s/objects/%s/source',
            $workspace->public_id,
            $item->public_id,
            $item->source_checksum_sha256,
        );
    }

    /** @return array{proof: 's3_version_id', version_id: string, sha256: string, size_bytes: int, media_type: string} */
    public function materialise(Workspace $workspace, ImportItem $item, string $reservedKey): array
    {
        $sourceKey = $this->stagingKeys->assertExact($workspace, $item);
        $disk = $this->versionedDisk();
        $client = $disk->getClient();
        $bucket = (string) config('filesystems.disks.s3.bucket');

        try {
            $existingVersionId = $this->currentVersionId($reservedKey);
            if ($existingVersionId !== null) {
                return $this->verifiedEvidence($item, $reservedKey, $existingVersionId);
            }
            $result = $client->copyObject([
                'Bucket' => $bucket,
                'Key' => $reservedKey,
                'CopySource' => rawurlencode($bucket).'/'.str_replace('%2F', '/', rawurlencode($sourceKey)),
                'MetadataDirective' => 'COPY',
            ]);
            $versionId = (string) ($result['VersionId'] ?? '');
            if ($versionId === '') {
                throw ImportPromotionException::conflict('immutable_storage_proof_unavailable');
            }
            try {
                return $this->verifiedEvidence($item, $reservedKey, $versionId);
            } catch (ImportPromotionException $exception) {
                $client->deleteObject(['Bucket' => $bucket, 'Key' => $reservedKey, 'VersionId' => $versionId]);
                throw $exception;
            }
        } catch (ImportPromotionException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);
            throw ImportPromotionException::conflict('promotion_storage_unavailable');
        }
    }

    private function currentVersionId(string $key): ?string
    {
        try {
            $result = $this->versionedDisk()->getClient()->headObject([
                'Bucket' => (string) config('filesystems.disks.s3.bucket'),
                'Key' => $key,
            ]);
            $versionId = (string) ($result['VersionId'] ?? '');
            if ($versionId === '') {
                throw ImportPromotionException::conflict('immutable_storage_proof_unavailable');
            }

            return $versionId;
        } catch (AwsException $exception) {
            if ($exception->getStatusCode() === 404 || $exception->getAwsErrorCode() === 'NoSuchKey') {
                return null;
            }
            throw $exception;
        }
    }

    /** @return array{proof: 's3_version_id', version_id: string, sha256: string, size_bytes: int, media_type: string} */
    private function verifiedEvidence(ImportItem $item, string $key, string $versionId): array
    {
        $identity = $this->versionIdentity($key, $versionId);
        if (! hash_equals((string) $item->source_checksum_sha256, $identity['sha256'])
            || $identity['size_bytes'] !== $item->size_bytes) {
            throw ImportPromotionException::conflict('copied_source_identity_mismatch');
        }

        return [
            'proof' => 's3_version_id',
            'version_id' => $versionId,
            'sha256' => $identity['sha256'],
            'size_bytes' => $identity['size_bytes'],
            'media_type' => (string) $item->media_type,
        ];
    }

    /** @return array{sha256: string, size_bytes: int} */
    public function versionIdentity(string $key, string $versionId): array
    {
        try {
            $disk = $this->versionedDisk();
            $result = $disk->getClient()->getObject([
                'Bucket' => (string) config('filesystems.disks.s3.bucket'),
                'Key' => $key,
                'VersionId' => $versionId,
            ]);
            $stream = $result['Body']->detach();
            if (! is_resource($stream)) {
                throw ImportPromotionException::conflict('immutable_storage_proof_unavailable');
            }
            $hash = hash_init('sha256');
            $size = 0;
            try {
                while (! feof($stream)) {
                    $chunk = fread($stream, 1024 * 1024);
                    if ($chunk === false) {
                        throw ImportPromotionException::conflict('promotion_storage_unavailable');
                    }
                    $size += strlen($chunk);
                    hash_update($hash, $chunk);
                }
            } finally {
                fclose($stream);
            }

            return ['sha256' => hash_final($hash), 'size_bytes' => $size];
        } catch (ImportPromotionException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);
            throw ImportPromotionException::conflict('promotion_storage_unavailable');
        }
    }

    public function deleteVersion(string $key, string $versionId): void
    {
        try {
            $this->versionedDisk()->getClient()->deleteObject([
                'Bucket' => (string) config('filesystems.disks.s3.bucket'),
                'Key' => $key,
                'VersionId' => $versionId,
            ]);
        } catch (Throwable $exception) {
            report($exception);
            throw ImportPromotionException::conflict('promotion_storage_unavailable');
        }
    }

    private function versionedDisk(): AwsS3V3Adapter
    {
        $disk = $this->filesystems->disk((string) config('imports.storage_disk'));
        if (! $disk instanceof AwsS3V3Adapter) {
            throw ImportPromotionException::conflict('immutable_storage_backend_unsupported');
        }

        return $disk;
    }
}
