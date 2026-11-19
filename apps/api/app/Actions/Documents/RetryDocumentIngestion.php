<?php

declare(strict_types=1);

namespace App\Actions\Documents;

use App\Enums\DocumentStatus;
use App\Exceptions\DocumentAdministrationException;
use App\Jobs\SyncDifyDocument;
use App\Models\Document;
use App\Models\DocumentIngestionRetry;
use App\Models\IngestionAuditEvent;
use App\Models\OutboxEvent;
use App\Models\User;
use App\Services\Ingestion\DocumentIngestionContractValidator;
use App\Support\Ingestion\DocumentIngestionRequestedPayload;
use App\Telemetry\OperationTracer;
use App\Telemetry\TraceContextHeaders;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RetryDocumentIngestion
{
    public function __construct(
        private readonly DocumentIngestionRequestedPayload $payloads,
        private readonly DocumentIngestionContractValidator $validator,
        private readonly TraceContextHeaders $traceContext,
        private readonly OperationTracer $tracer,
    ) {}

    public function handle(
        Document $document,
        User $actor,
        string $idempotencyKey,
        string $correlationId,
    ): DocumentIngestionRetry {
        if (config('documents.processing_driver') === 'dify') {
            return $this->handleDify($document, $actor, $idempotencyKey, $correlationId);
        }

        return $this->tracer->run('document.ingestion.retry', [
            'rag.correlation.id' => $correlationId,
            'rag.document.id' => $document->public_id,
            'rag.operation.stage' => 'ingestion_retry',
        ], fn (): DocumentIngestionRetry => DB::transaction(function () use ($document, $actor, $idempotencyKey, $correlationId): DocumentIngestionRetry {
            $locked = Document::query()
                ->with('workspace')
                ->whereKey($document->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $existing = DocumentIngestionRetry::query()
                ->where('document_id', $locked->id)
                ->where('idempotency_key', $idempotencyKey)
                ->first();
            if ($existing !== null) {
                return $existing->load('document');
            }
            if ($locked->status !== DocumentStatus::Failed) {
                throw DocumentAdministrationException::conflict(
                    'document_retry_ineligible',
                    'The document is no longer eligible for retry.',
                );
            }

            $eventId = (string) Str::uuid();
            $occurredAt = CarbonImmutable::now();
            $payload = $this->payloads->build($locked, $eventId, $correlationId, $occurredAt);
            $this->validator->validate($payload);
            $traceContext = $this->traceContext->current();

            $retry = DocumentIngestionRetry::query()->create([
                'workspace_id' => $locked->workspace_id,
                'document_id' => $locked->id,
                'requested_by_user_id' => $actor->id,
                'idempotency_key' => $idempotencyKey,
                'event_id' => $eventId,
                'correlation_id' => $correlationId,
            ]);
            $locked->forceFill(['status' => DocumentStatus::Queued])->save();
            OutboxEvent::query()->create([
                'event_id' => $eventId,
                'event_type' => DocumentIngestionRequestedPayload::EVENT_TYPE,
                'event_version' => DocumentIngestionRequestedPayload::EVENT_VERSION,
                'workspace_public_id' => $locked->workspace->public_id,
                'document_public_id' => $locked->public_id,
                'correlation_id' => $correlationId,
                'traceparent' => $traceContext['traceparent'] ?? null,
                'tracestate' => $traceContext['tracestate'] ?? null,
                'payload' => $payload,
                'occurred_at' => $occurredAt,
            ]);
            IngestionAuditEvent::query()->create([
                'event_id' => $eventId,
                'workspace_id' => $locked->workspace_id,
                'document_id' => $locked->id,
                'action' => 'retry_requested',
                'outcome' => 'queued',
                'context' => [
                    'actor_user_id' => $actor->id,
                    'correlation_id' => $correlationId,
                ],
                'occurred_at' => $occurredAt,
            ]);

            return $retry->load('document');
        }));
    }

    private function handleDify(
        Document $document,
        User $actor,
        string $idempotencyKey,
        string $correlationId,
    ): DocumentIngestionRetry {
        [$retry, $created] = $this->tracer->run('document.dify.retry', [
            'rag.correlation.id' => $correlationId,
            'rag.document.id' => $document->public_id,
            'rag.operation.stage' => 'dify_retry',
        ], fn (): array => DB::transaction(function () use ($document, $actor, $idempotencyKey, $correlationId): array {
            $locked = Document::query()->whereKey($document->getKey())->lockForUpdate()->firstOrFail();
            $existing = DocumentIngestionRetry::query()
                ->where('document_id', $locked->id)
                ->where('idempotency_key', $idempotencyKey)
                ->first();
            if ($existing !== null) {
                return [$existing->load('document'), false];
            }
            if ($locked->status !== DocumentStatus::Failed) {
                throw DocumentAdministrationException::conflict(
                    'document_retry_ineligible',
                    'The document is no longer eligible for retry.',
                );
            }
            $retryableBindings = $locked->difyBindings()->where('sync_status', 'failed');
            if (! $retryableBindings->exists()) {
                throw DocumentAdministrationException::conflict(
                    'document_retry_unavailable',
                    'No disabled searchable copy is available to retry.',
                );
            }

            $eventId = (string) Str::uuid();
            $retry = DocumentIngestionRetry::query()->create([
                'workspace_id' => $locked->workspace_id,
                'document_id' => $locked->id,
                'requested_by_user_id' => $actor->id,
                'idempotency_key' => $idempotencyKey,
                'event_id' => $eventId,
                'correlation_id' => $correlationId,
            ]);
            $retryableBindings->update(['sync_status' => 'retry_pending', 'failure_code' => null]);
            $locked->forceFill([
                'status' => DocumentStatus::Processing,
                'failure_category' => null,
                'failure_message' => null,
            ])->save();
            IngestionAuditEvent::query()->create([
                'event_id' => $eventId,
                'workspace_id' => $locked->workspace_id,
                'document_id' => $locked->id,
                'action' => 'dify_retry_requested',
                'outcome' => 'queued',
                'context' => [
                    'actor_user_id' => $actor->id,
                    'correlation_id' => $correlationId,
                ],
                'occurred_at' => CarbonImmutable::now(),
            ]);

            return [$retry->load('document'), true];
        }));

        if ($created) {
            SyncDifyDocument::dispatch($document->id);
        }

        return $retry;
    }
}
