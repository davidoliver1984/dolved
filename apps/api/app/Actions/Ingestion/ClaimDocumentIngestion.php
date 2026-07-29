<?php

declare(strict_types=1);

namespace App\Actions\Ingestion;

use App\Enums\DocumentStatus;
use App\Enums\IngestionClaimOutcome;
use App\Exceptions\IngestionClaimException;
use App\Models\Document;
use App\Models\IngestionEventClaim;
use App\Support\Ingestion\IngestionClaimResult;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ClaimDocumentIngestion
{
    /**
     * @param  array<string, mixed>  $event
     */
    public function handle(
        array $event,
        string $payloadSha256,
    ): IngestionClaimResult {
        return DB::transaction(function () use (
            $event,
            $payloadSha256,
        ): IngestionClaimResult {
            $existing = IngestionEventClaim::query()
                ->where('event_id', $event['event_id'])
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                return $this->existingResult(
                    $existing,
                    $event,
                    $payloadSha256,
                );
            }

            $document = Document::query()
                ->where('public_id', $event['document_id'])
                ->whereHas(
                    'workspace',
                    fn (Builder $query): Builder => $query->where(
                        'public_id',
                        $event['workspace_id'],
                    ),
                )
                ->lockForUpdate()
                ->first();

            if ($document === null) {
                throw IngestionClaimException::unknownDocument();
            }

            $existing = IngestionEventClaim::query()
                ->where('event_id', $event['event_id'])
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                return $this->existingResult(
                    $existing,
                    $event,
                    $payloadSha256,
                    $document,
                );
            }

            if ($document->status === DocumentStatus::Queued) {
                IngestionEventClaim::query()->create([
                    'event_id' => $event['event_id'],
                    'workspace_public_id' => $event['workspace_id'],
                    'document_public_id' => $event['document_id'],
                    'correlation_id' => $event['correlation_id'],
                    'payload_sha256' => $payloadSha256,
                    'claimed_at' => now(),
                ]);

                $document->status = DocumentStatus::Processing;
                $document->save();

                return new IngestionClaimResult(
                    IngestionClaimOutcome::Claimed,
                    DocumentStatus::Processing,
                );
            }

            if (in_array($document->status, [
                DocumentStatus::Processing,
                DocumentStatus::Indexed,
                DocumentStatus::Failed,
                DocumentStatus::Deleting,
                DocumentStatus::Deleted,
            ], true)) {
                return new IngestionClaimResult(
                    IngestionClaimOutcome::StaleEvent,
                    $document->status,
                );
            }

            return new IngestionClaimResult(
                IngestionClaimOutcome::IneligibleState,
                $document->status,
            );
        });
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function existingResult(
        IngestionEventClaim $claim,
        array $event,
        string $payloadSha256,
        ?Document $document = null,
    ): IngestionClaimResult {
        if (
            $claim->workspace_public_id !== $event['workspace_id']
            || $claim->document_public_id !== $event['document_id']
            || $claim->correlation_id !== $event['correlation_id']
            || $claim->payload_sha256 !== $payloadSha256
        ) {
            throw IngestionClaimException::eventIdentityReused();
        }

        $document ??= Document::query()
            ->where('public_id', $claim->document_public_id)
            ->first();

        return new IngestionClaimResult(
            IngestionClaimOutcome::AlreadyClaimed,
            $document?->status,
        );
    }
}
