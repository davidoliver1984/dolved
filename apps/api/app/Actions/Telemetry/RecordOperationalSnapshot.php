<?php

declare(strict_types=1);

namespace App\Actions\Telemetry;

use App\Enums\DocumentDeletionStatus;
use App\Enums\ExtractionUploadCleanupState;
use App\Enums\IngestionAttemptStatus;
use App\Models\DocumentDeletionOperation;
use App\Models\DocumentExtractionUploadAuthorisation;
use App\Models\IngestionEventClaim;
use App\Models\OutboxEvent;
use App\Telemetry\OperationalTelemetry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class RecordOperationalSnapshot
{
    public function __construct(private readonly OperationalTelemetry $telemetry) {}

    public function handle(): void
    {
        $started = hrtime(true);
        try {
            DB::select('SELECT 1');
            $this->telemetry->dependency('database', true, $this->elapsed($started));
        } catch (Throwable) {
            $this->telemetry->dependency('database', false, $this->elapsed($started));
        }

        $started = hrtime(true);
        try {
            Storage::disk((string) config('filesystems.default'))->exists('__dolved_operational_probe__');
            $this->telemetry->dependency('object_storage', true, $this->elapsed($started));
        } catch (Throwable) {
            $this->telemetry->dependency('object_storage', false, $this->elapsed($started));
        }

        $started = hrtime(true);
        try {
            Queue::connection()->size((string) config('queue.connections.sqs.queue'));
            $this->telemetry->dependency('queue', true, $this->elapsed($started));
        } catch (Throwable) {
            $this->telemetry->dependency('queue', false, $this->elapsed($started));
        }

        $pending = OutboxEvent::query()->whereNull('published_at')->whereNull('failed_at');
        $oldest = (clone $pending)->min('created_at');
        $this->telemetry->queue(
            'durable_outbox',
            (clone $pending)->count(),
            $oldest === null ? 0.0 : max(0.0, now()->diffInMilliseconds($oldest, absolute: true) / 1_000),
        );
        $this->telemetry->stuck('ingestion', IngestionEventClaim::query()
            ->whereIn('status', [IngestionAttemptStatus::Open->value, IngestionAttemptStatus::Sealed->value, IngestionAttemptStatus::PublicationAuthorised->value])
            ->where('updated_at', '<', now()->subMinutes(5))->count());
        $this->telemetry->stuck('document_deletion', DocumentDeletionOperation::query()
            ->whereNotIn('status', [DocumentDeletionStatus::Completed->value])
            ->where('updated_at', '<', now()->subMinutes(5))->count());
        $this->telemetry->stuck('extraction_artifact_cleanup', DocumentExtractionUploadAuthorisation::query()
            ->where(function ($query): void {
                $query->where('cleanup_state', ExtractionUploadCleanupState::Failed->value)
                    ->orWhere(function ($claimed): void {
                        $claimed->where('cleanup_state', ExtractionUploadCleanupState::Claimed->value)
                            ->where('cleanup_claimed_at', '<', now()->subMinutes(5));
                    });
            })->count());
    }

    private function elapsed(int $started): float
    {
        return (hrtime(true) - $started) / 1_000_000_000;
    }
}
