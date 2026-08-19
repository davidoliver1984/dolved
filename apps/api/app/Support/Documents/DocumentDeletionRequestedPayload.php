<?php

declare(strict_types=1);

namespace App\Support\Documents;

use App\Models\DocumentDeletionOperation;
use Carbon\CarbonImmutable;

final class DocumentDeletionRequestedPayload
{
    public const string EVENT_TYPE = 'document.deletion.requested';

    public const int EVENT_VERSION = 1;

    public function build(DocumentDeletionOperation $operation, CarbonImmutable $occurredAt): array
    {
        $operation->loadMissing(['document.workspace']);

        return [
            'event_id' => $operation->public_id,
            'event_type' => self::EVENT_TYPE,
            'event_version' => self::EVENT_VERSION,
            'occurred_at' => $occurredAt->toIso8601ZuluString(),
            'workspace_id' => $operation->document->workspace->public_id,
            'document_id' => $operation->document->public_id,
            'correlation_id' => $operation->correlation_id,
            'vector_scopes' => $operation->vector_scopes ?? [],
        ];
    }
}
