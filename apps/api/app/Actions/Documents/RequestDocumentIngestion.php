<?php

declare(strict_types=1);

namespace App\Actions\Documents;

use App\Enums\DocumentStatus;
use App\Exceptions\DocumentIngestionException;
use App\Models\Document;
use App\Models\OutboxEvent;
use App\Services\Ingestion\DocumentIngestionContractValidator;
use App\Support\Ingestion\DocumentIngestionRequestedPayload;
use App\Telemetry\TraceContextHeaders;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RequestDocumentIngestion
{
    public function __construct(
        private readonly DocumentIngestionRequestedPayload $payloads,
        private readonly DocumentIngestionContractValidator $validator,
        private readonly TraceContextHeaders $traceContext,
    ) {}

    public function handle(
        Document $document,
        string $correlationId,
    ): Document {
        return DB::transaction(function () use (
            $document,
            $correlationId,
        ): Document {
            $lockedDocument = Document::query()
                ->with('workspace')
                ->whereKey($document->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (in_array($lockedDocument->status, [
                DocumentStatus::Queued,
                DocumentStatus::Processing,
            ], true)) {
                return $lockedDocument;
            }

            if ($lockedDocument->status !== DocumentStatus::Uploaded) {
                throw DocumentIngestionException::invalidState(
                    $lockedDocument->status,
                );
            }

            $eventId = (string) Str::uuid();
            $occurredAt = CarbonImmutable::now();
            $payload = $this->payloads->build(
                $lockedDocument,
                $eventId,
                $correlationId,
                $occurredAt,
            );

            $this->validator->validate($payload);

            $traceContext = $this->traceContext->current();

            $lockedDocument->status = DocumentStatus::Queued;
            $lockedDocument->save();

            OutboxEvent::query()->create([
                'event_id' => $eventId,
                'event_type' => DocumentIngestionRequestedPayload::EVENT_TYPE,
                'event_version' => DocumentIngestionRequestedPayload::EVENT_VERSION,
                'workspace_public_id' => $lockedDocument->workspace->public_id,
                'document_public_id' => $lockedDocument->public_id,
                'correlation_id' => $correlationId,
                'traceparent' => $traceContext['traceparent'] ?? null,
                'tracestate' => $traceContext['tracestate'] ?? null,
                'payload' => $payload,
                'occurred_at' => $occurredAt,
            ]);

            return $lockedDocument->refresh();
        });
    }
}
