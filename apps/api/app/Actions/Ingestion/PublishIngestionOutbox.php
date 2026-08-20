<?php

declare(strict_types=1);

namespace App\Actions\Ingestion;

use App\Contracts\Ingestion\IngestionEventPublisher;
use App\Exceptions\InvalidIngestionEvent;
use App\Models\OutboxEvent;
use App\Services\Documents\DocumentDeletionContractValidator;
use App\Services\Ingestion\DocumentIngestionContractValidator;
use App\Telemetry\TelemetryAttributeAllowlist;
use App\Telemetry\TelemetryLifecycle;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use OpenTelemetry\API\Metrics\CounterInterface;
use OpenTelemetry\API\Metrics\HistogramInterface;
use OpenTelemetry\API\Metrics\MeterProviderInterface;
use OpenTelemetry\API\Trace\Propagation\TraceContextPropagator;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use OpenTelemetry\Context\ContextInterface;
use Throwable;

class PublishIngestionOutbox
{
    private readonly TracerInterface $tracer;

    private readonly CounterInterface $publicationCount;

    private readonly HistogramInterface $publicationDuration;

    public function __construct(
        private readonly DocumentIngestionContractValidator $ingestionValidator,
        private readonly DocumentDeletionContractValidator $deletionValidator,
        private readonly IngestionEventPublisher $publisher,
        TracerProviderInterface $tracerProvider,
        MeterProviderInterface $meterProvider,
        private readonly TelemetryAttributeAllowlist $allowlist,
        private readonly TelemetryLifecycle $telemetryLifecycle,
    ) {
        $this->tracer = $tracerProvider->getTracer(
            'dolved.laravel.ingestion-publisher',
        );
        $meter = $meterProvider->getMeter(
            'dolved.laravel.ingestion-publisher',
        );
        $this->publicationCount = $meter->createCounter(
            'rag.ingestion.outbox.publication.count',
            '{event}',
            'Count of ingestion outbox publication outcomes.',
        );
        $this->publicationDuration = $meter->createHistogram(
            'rag.ingestion.outbox.publication.duration',
            's',
            'Duration of ingestion outbox publication attempts.',
        );
    }

    /**
     * @return array{claimed: int, published: int, retryable: int, failed: int}
     */
    public function handle(): array
    {
        $summary = [
            'claimed' => 0,
            'published' => 0,
            'retryable' => 0,
            'failed' => 0,
        ];
        try {
            $batchSize = max(
                1,
                (int) config('ingestion.publisher.batch_size'),
            );

            for ($index = 0; $index < $batchSize; $index++) {
                $event = $this->claimNextEvent();

                if ($event === null) {
                    break;
                }

                $summary['claimed']++;
                $this->publishClaimedEvent($event, $summary);
            }

            return $summary;
        } finally {
            $this->telemetryLifecycle->flush();
        }
    }

    /**
     * @param  array{claimed: int, published: int, retryable: int, failed: int}  $summary
     */
    private function publishClaimedEvent(
        OutboxEvent $event,
        array &$summary,
    ): void {
        $startedAt = hrtime(true);
        $attributes = [
            'messaging.system' => 'aws_sqs',
            'messaging.destination.name' => (string) config(
                'queue.connections.sqs.queue',
                'default',
            ),
            'messaging.operation.type' => 'publish',
            'rag.event.id' => $event->event_id,
            'rag.correlation.id' => $event->correlation_id,
            'rag.workspace.id' => $event->workspace_public_id,
            'rag.document.id' => $event->document_public_id,
        ];
        $span = $this->tracer
            ->spanBuilder("messaging.publish {$event->event_type}")
            ->setParent($this->parentContext($event))
            ->setSpanKind(SpanKind::KIND_PRODUCER)
            ->setAttributes($this->allowlist->trace($attributes))
            ->startSpan();
        $scope = $span->activate();
        $outcome = 'failed';

        try {
            try {
                match ($event->event_type) {
                    'document.ingestion.requested' => $this->ingestionValidator->validate($event->payload),
                    'document.deletion.requested' => $this->deletionValidator->validate($event->payload),
                    default => throw new InvalidIngestionEvent('The outbox event type is unsupported.'),
                };
            } catch (InvalidIngestionEvent $exception) {
                $this->markTerminalFailure($event, $exception);
                $summary['failed']++;

                return;
            }

            try {
                $messageId = $this->publisher->publish($event->payload);
                $this->markPublished($event, $messageId);
                $summary['published']++;
                $outcome = 'published';
            } catch (Throwable $exception) {
                $this->scheduleRetry($event, $exception);
                $summary['retryable']++;
                $outcome = 'retryable';
            }
        } finally {
            $outcomeAttributes = [
                ...$attributes,
                'rag.outbox.outcome' => $outcome,
            ];
            $span->setAttributes(
                $this->allowlist->trace($outcomeAttributes),
            );
            $metricAttributes = $this->allowlist->metric(
                $outcomeAttributes,
            );
            $this->publicationCount->add(1, $metricAttributes);
            $this->publicationDuration->record(
                (hrtime(true) - $startedAt) / 1_000_000_000,
                $metricAttributes,
            );
            $scope->detach();
            $span->end();
        }
    }

    private function parentContext(OutboxEvent $event): ContextInterface
    {
        $carrier = array_filter([
            'traceparent' => $event->traceparent,
            'tracestate' => $event->tracestate,
        ], static fn (mixed $value): bool => is_string($value)
            && $value !== '');

        return TraceContextPropagator::getInstance()->extract($carrier);
    }

    private function claimNextEvent(): ?OutboxEvent
    {
        $now = CarbonImmutable::now();
        $leaseExpiredAt = $now->subSeconds(max(
            1,
            (int) config('ingestion.publisher.claim_lease_seconds'),
        ));

        return DB::transaction(function () use (
            $now,
            $leaseExpiredAt,
        ): ?OutboxEvent {
            $query = OutboxEvent::query()
                ->whereNull('published_at')
                ->whereNull('failed_at')
                ->where(function (Builder $query) use ($now): void {
                    $query->whereNull('next_attempt_at')
                        ->orWhere('next_attempt_at', '<=', $now);
                })
                ->where(function (Builder $query) use ($leaseExpiredAt): void {
                    $query->whereNull('claimed_at')
                        ->orWhere('claimed_at', '<=', $leaseExpiredAt);
                })
                ->orderBy('id')
                ->limit(1);

            if (DB::getDriverName() === 'pgsql') {
                $query->lock('FOR UPDATE SKIP LOCKED');
            } else {
                $query->lockForUpdate();
            }

            $event = $query->first();

            if ($event === null) {
                return null;
            }

            $event->forceFill([
                'claimed_at' => $now,
                'claim_token' => (string) Str::uuid(),
            ])->save();

            return $event;
        });
    }

    private function markPublished(
        OutboxEvent $event,
        string $messageId,
    ): void {
        $updated = $this->claimedEventQuery($event)->update([
            'published_at' => CarbonImmutable::now(),
            'claimed_at' => null,
            'claim_token' => null,
            'attempt_count' => $event->attempt_count + 1,
            'next_attempt_at' => null,
            'last_error' => null,
            'updated_at' => CarbonImmutable::now(),
        ]);

        Log::info('Ingestion event published.', [
            'event_id' => $event->event_id,
            'correlation_id' => $event->correlation_id,
            'workspace_id' => $event->workspace_public_id,
            'document_id' => $event->document_public_id,
            'transport_message_id' => $messageId,
            'outbox_marked_published' => $updated === 1,
        ]);
    }

    private function markTerminalFailure(
        OutboxEvent $event,
        InvalidIngestionEvent $exception,
    ): void {
        $this->claimedEventQuery($event)->update([
            'failed_at' => CarbonImmutable::now(),
            'claimed_at' => null,
            'claim_token' => null,
            'attempt_count' => $event->attempt_count + 1,
            'next_attempt_at' => null,
            'last_error' => $this->safeError($exception),
            'updated_at' => CarbonImmutable::now(),
        ]);

        Log::error('Ingestion event failed contract validation.', [
            'event_id' => $event->event_id,
            'correlation_id' => $event->correlation_id,
            'workspace_id' => $event->workspace_public_id,
            'document_id' => $event->document_public_id,
        ]);
    }

    private function scheduleRetry(
        OutboxEvent $event,
        Throwable $exception,
    ): void {
        $attempt = $event->attempt_count + 1;
        $delay = $this->retryDelay($attempt);

        $this->claimedEventQuery($event)->update([
            'claimed_at' => null,
            'claim_token' => null,
            'attempt_count' => $attempt,
            'next_attempt_at' => CarbonImmutable::now()->addSeconds($delay),
            'last_error' => $this->safeError($exception),
            'updated_at' => CarbonImmutable::now(),
        ]);

        Log::warning('Ingestion event publication will be retried.', [
            'event_id' => $event->event_id,
            'correlation_id' => $event->correlation_id,
            'workspace_id' => $event->workspace_public_id,
            'document_id' => $event->document_public_id,
            'attempt_count' => $attempt,
            'retry_delay_seconds' => $delay,
        ]);
    }

    /**
     * @return Builder<OutboxEvent>
     */
    private function claimedEventQuery(OutboxEvent $event): Builder
    {
        return OutboxEvent::query()
            ->whereKey($event->getKey())
            ->where('claim_token', $event->claim_token);
    }

    private function retryDelay(int $attempt): int
    {
        $base = max(
            1,
            (int) config('ingestion.publisher.retry_base_seconds'),
        );
        $maximum = max(
            $base,
            (int) config('ingestion.publisher.retry_max_seconds'),
        );
        $multiplier = 2 ** min(max($attempt - 1, 0), 10);

        return min($maximum, $base * $multiplier);
    }

    private function safeError(Throwable $exception): string
    {
        $message = preg_replace(
            '/(password|secret|token|credential|access[_ -]?key)\s*[=:]\s*\S+/i',
            '$1=[redacted]',
            $exception->getMessage(),
        ) ?? 'Publication failed.';

        return Str::limit(
            class_basename($exception).': '.$message,
            1_000,
            '',
        );
    }
}
