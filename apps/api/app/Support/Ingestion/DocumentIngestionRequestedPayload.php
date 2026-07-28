<?php

declare(strict_types=1);

namespace App\Support\Ingestion;

use App\Models\Document;
use Carbon\CarbonImmutable;
use RuntimeException;

class DocumentIngestionRequestedPayload
{
    public const string EVENT_TYPE = 'document.ingestion.requested';

    public const int EVENT_VERSION = 1;

    /**
     * @return array{
     *     event_id: string,
     *     event_type: string,
     *     event_version: int,
     *     occurred_at: string,
     *     workspace_id: string,
     *     document_id: string,
     *     storage_bucket: string,
     *     storage_key: string,
     *     media_type: string,
     *     byte_size: int,
     *     correlation_id: string
     * }
     */
    public function build(
        Document $document,
        string $eventId,
        string $correlationId,
        CarbonImmutable $occurredAt,
    ): array {
        $bucket = config('filesystems.disks.s3.bucket');

        if (! is_string($bucket) || $bucket === '') {
            throw new RuntimeException(
                'The document storage bucket is not configured.',
            );
        }

        return [
            'event_id' => $eventId,
            'event_type' => self::EVENT_TYPE,
            'event_version' => self::EVENT_VERSION,
            'occurred_at' => $occurredAt->toIso8601ZuluString(),
            'workspace_id' => $document->workspace->public_id,
            'document_id' => $document->public_id,
            'storage_bucket' => $bucket,
            'storage_key' => $document->storage_key,
            'media_type' => $document->media_type,
            'byte_size' => $document->size_bytes,
            'correlation_id' => $correlationId,
        ];
    }
}
