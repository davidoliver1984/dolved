<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\OutboxEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OutboxEvent>
 */
class OutboxEventFactory extends Factory
{
    public function definition(): array
    {
        $eventId = fake()->uuid();
        $workspaceId = fake()->uuid();
        $documentId = fake()->uuid();
        $correlationId = fake()->uuid();
        $occurredAt = now()->toImmutable();

        return [
            'event_id' => $eventId,
            'event_type' => 'document.ingestion.requested',
            'event_version' => 1,
            'workspace_public_id' => $workspaceId,
            'document_public_id' => $documentId,
            'correlation_id' => $correlationId,
            'payload' => [
                'event_id' => $eventId,
                'event_type' => 'document.ingestion.requested',
                'event_version' => 1,
                'occurred_at' => $occurredAt->toIso8601ZuluString(),
                'workspace_id' => $workspaceId,
                'document_id' => $documentId,
                'storage_bucket' => 'rag-platform-document-uploads-local',
                'storage_key' => "workspaces/{$workspaceId}/documents/{$documentId}/source.pdf",
                'media_type' => 'application/pdf',
                'byte_size' => 42_000,
                'correlation_id' => $correlationId,
            ],
            'occurred_at' => $occurredAt,
            'published_at' => null,
            'failed_at' => null,
            'claimed_at' => null,
            'claim_token' => null,
            'attempt_count' => 0,
            'next_attempt_at' => null,
            'last_error' => null,
        ];
    }
}
