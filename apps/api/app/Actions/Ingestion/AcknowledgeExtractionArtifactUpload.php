<?php

declare(strict_types=1);

namespace App\Actions\Ingestion;

use App\Enums\ExtractionUploadCleanupState;
use App\Enums\ExtractionUploadStatus;
use App\Exceptions\IngestionAttemptException;
use App\Models\DocumentExtractionArtifact;
use App\Models\DocumentExtractionUploadAuthorisation;
use App\Models\IngestionEventClaim;
use App\Services\Documents\ExtractionArtifactObjectStorage;
use App\Services\Ingestion\IngestionAttemptAuthorizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AcknowledgeExtractionArtifactUpload
{
    public function __construct(
        private readonly IngestionAttemptAuthorizer $authorizer,
        private readonly ExtractionArtifactObjectStorage $storage,
    ) {}

    /** @param array<string, mixed> $payload */
    public function handle(string $eventId, array $payload): DocumentExtractionArtifact
    {
        return DB::transaction(function () use ($eventId, $payload): DocumentExtractionArtifact {
            $attempt = IngestionEventClaim::query()->where('event_id', $eventId)->lockForUpdate()->firstOrFail();
            $this->authorizer->assert($attempt, $payload['event_id'], $payload['workspace_id'], $payload['document_id'], $payload['lease_token']);
            $record = DocumentExtractionUploadAuthorisation::query()
                ->where('public_id', $payload['authorisation_id'])->lockForUpdate()->firstOrFail();
            if ($record->ingestion_event_claim_id !== $attempt->id || $record->lease_generation !== $attempt->lease_generation || (int) $payload['lease_generation'] !== $attempt->lease_generation) {
                throw IngestionAttemptException::invalid('stale_lease_generation', 'The artifact acknowledgement lease generation has been superseded.');
            }
            if ($record->status === ExtractionUploadStatus::Verified) {
                $artifact = $record->artifact()->firstOrFail();
                if ($artifact->artifact_sha256 !== $payload['artifact_sha256']) {
                    throw IngestionAttemptException::invalid('artifact_acknowledgement_conflict', 'The artifact was already verified with a different identity.');
                }

                return $artifact;
            }
            if ($record->status !== ExtractionUploadStatus::Authorised || $record->expires_at->isPast()) {
                throw IngestionAttemptException::invalid('artifact_upload_ineligible', 'The extraction upload authorisation is no longer active.');
            }
            $observed = $this->storage->inspect($record->object_key);
            if ($observed === null) {
                throw IngestionAttemptException::invalid('artifact_upload_incomplete', 'The authorised extraction artifact is not present.', 422);
            }
            if ($observed['sha256'] !== $payload['artifact_sha256'] || $observed['size_bytes'] !== (int) $payload['size_bytes']) {
                throw IngestionAttemptException::invalid('artifact_identity_mismatch', 'The stored extraction artifact does not match the acknowledgement.', 422);
            }
            if ($observed['contract_version'] !== $record->contract_version || $payload['artifact_contract_version'] !== $record->contract_version) {
                throw IngestionAttemptException::invalid('artifact_contract_mismatch', 'The stored extraction artifact uses an unexpected contract version.', 422);
            }
            $artifact = DocumentExtractionArtifact::query()->create([
                'public_id' => (string) Str::uuid(),
                'workspace_id' => $record->workspace_id,
                'document_id' => $record->document_id,
                'upload_authorisation_id' => $record->id,
                'object_key' => $record->object_key,
                'contract_version' => $record->contract_version,
                'artifact_sha256' => $observed['sha256'],
                'size_bytes' => $observed['size_bytes'],
                'projection_manifest_digest' => $payload['projection_manifest_digest'],
                'warning_manifest_digest' => $payload['warning_manifest_digest'],
                'element_count' => $payload['element_count'],
                'warning_count' => $payload['warning_count'],
                'storage_version_id' => $payload['storage_version_id'] ?? null,
                'storage_etag' => $payload['storage_etag'] ?? null,
                'verified_at' => now(),
            ]);
            $record->forceFill([
                'status' => ExtractionUploadStatus::Verified,
                'artifact_sha256' => $artifact->artifact_sha256,
                'size_bytes' => $artifact->size_bytes,
                'projection_manifest_digest' => $artifact->projection_manifest_digest,
                'warning_manifest_digest' => $artifact->warning_manifest_digest,
                'element_count' => $artifact->element_count,
                'warning_count' => $artifact->warning_count,
                'storage_version_id' => $artifact->storage_version_id,
                'storage_etag' => $artifact->storage_etag,
                'verified_at' => $artifact->verified_at,
                'cleanup_state' => ExtractionUploadCleanupState::NotNeeded,
            ])->save();

            return $artifact;
        });
    }
}
