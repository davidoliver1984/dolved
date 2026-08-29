<?php

declare(strict_types=1);

namespace App\Actions\Ingestion;

use App\Enums\ExtractionUploadStatus;
use App\Exceptions\IngestionAttemptException;
use App\Models\DocumentExtractionUploadAuthorisation;
use App\Models\IngestionEventClaim;
use App\Services\Documents\ExtractionArtifactObjectStorage;
use App\Services\Ingestion\IngestionAttemptAuthorizer;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AuthoriseExtractionArtifactUpload
{
    public function __construct(
        private readonly IngestionAttemptAuthorizer $authorizer,
        private readonly ExtractionArtifactObjectStorage $storage,
    ) {}

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    public function handle(string $eventId, array $payload): array
    {
        $record = DB::transaction(function () use ($eventId, $payload): DocumentExtractionUploadAuthorisation {
            $attempt = IngestionEventClaim::query()->where('event_id', $eventId)->lockForUpdate()->firstOrFail();
            $this->authorizer->assert($attempt, $payload['event_id'], $payload['workspace_id'], $payload['document_id'], $payload['lease_token']);
            if ((int) $payload['lease_generation'] !== $attempt->lease_generation) {
                throw IngestionAttemptException::invalid('stale_lease_generation', 'The upload lease generation has been superseded.');
            }
            $existing = DocumentExtractionUploadAuthorisation::query()
                ->where('ingestion_event_claim_id', $attempt->id)
                ->where('lease_generation', $attempt->lease_generation)
                ->lockForUpdate()->first();
            if ($existing !== null) {
                if ($existing->status !== ExtractionUploadStatus::Authorised || $existing->expires_at->isPast()) {
                    throw IngestionAttemptException::invalid('artifact_upload_ineligible', 'The extraction upload authorisation is no longer active.');
                }

                return $existing;
            }
            $publicId = (string) Str::uuid();

            return DocumentExtractionUploadAuthorisation::query()->create([
                'public_id' => $publicId,
                'workspace_id' => $attempt->workspace_id,
                'document_id' => $attempt->document_id,
                'ingestion_event_claim_id' => $attempt->id,
                'purpose' => 'extraction_artifact_upload',
                'object_key' => "workspaces/{$attempt->workspace_public_id}/documents/{$attempt->document_public_id}/extraction-artifacts/{$publicId}.json",
                'lease_generation' => $attempt->lease_generation,
                'contract_version' => 'document-extraction-artifact-v1',
                'expires_at' => CarbonImmutable::now()->addSeconds(max(30, (int) config('ingestion.orchestration.extraction_artifact_upload_seconds'))),
            ]);
        });

        return [
            'outcome' => 'authorised',
            'authorisation_id' => $record->public_id,
            'object_key' => $record->object_key,
            'lease_generation' => $record->lease_generation,
            'contract_version' => $record->contract_version,
            'max_bytes' => max(1, (int) config('ingestion.orchestration.extraction_artifact_max_bytes')),
            'upload' => $this->storage->createUploadRequest($record->object_key, CarbonImmutable::instance($record->expires_at)),
        ];
    }
}
