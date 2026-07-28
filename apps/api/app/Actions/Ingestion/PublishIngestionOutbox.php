<?php

declare(strict_types=1);

namespace App\Actions\Ingestion;

use App\Contracts\Ingestion\IngestionEventPublisher;
use App\Exceptions\InvalidIngestionEvent;
use App\Models\OutboxEvent;
use App\Services\Ingestion\DocumentIngestionContractValidator;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class PublishIngestionOutbox
{
    public function __construct(
        private readonly DocumentIngestionContractValidator $validator,
        private readonly IngestionEventPublisher $publisher,
    ) {}

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

            try {
                $this->validator->validate($event->payload);
            } catch (InvalidIngestionEvent $exception) {
                $this->markTerminalFailure($event, $exception);
                $summary['failed']++;

                continue;
            }

            try {
                $messageId = $this->publisher->publish($event->payload);
                $this->markPublished($event, $messageId);
                $summary['published']++;
            } catch (Throwable $exception) {
                $this->scheduleRetry($event, $exception);
                $summary['retryable']++;
            }
        }

        return $summary;
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
