<?php

declare(strict_types=1);

namespace App\Actions\Documents;

use App\Enums\ChecksumVerificationStatus;
use App\Enums\DocumentContentCloneStatus;
use App\Enums\DocumentStatus;
use App\Enums\IngestionAttemptOrigin;
use App\Enums\IngestionAttemptStatus;
use App\Exceptions\DocumentGovernanceException;
use App\Models\Document;
use App\Models\DocumentContentCloneOperation;
use App\Models\DocumentFamily;
use App\Models\IngestionEventClaim;
use App\Models\OrganisationalLocation;
use App\Models\User;
use App\Services\Documents\DocumentObjectStorage;
use App\Support\Documents\ContentCloneCompatibility;
use App\Support\Documents\DocumentGovernanceAuthorizer;
use App\Support\Documents\MaintainDocumentFamilyActivitySummary;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

final readonly class CreateApplicabilityOnlySuccessor
{
    public function __construct(
        private DocumentGovernanceAuthorizer $authorizer,
        private CreateDocumentVersion $createVersion,
        private ContentCloneCompatibility $compatibility,
        private MaterialiseDocumentContentClone $materialise,
        private RequestDocumentIngestion $requestIngestion,
        private ?MaintainDocumentFamilyActivitySummary $activity = null,
    ) {}

    /** @param list<OrganisationalLocation> $locations */
    public function handle(
        Document $predecessor,
        User $actor,
        CarbonInterface $effectiveFrom,
        array $locations,
        string $correlationId,
    ): Document {
        [$target, $operation, $leaseToken] = $this->prepare(
            $predecessor,
            $actor,
            $effectiveFrom,
            $locations,
            $correlationId,
        );

        return $this->finish($predecessor, $target, $operation, $leaseToken, $correlationId);
    }

    /**
     * @param  list<OrganisationalLocation>  $locations
     * @return array{Document, ?DocumentContentCloneOperation, ?string}
     */
    public function prepare(
        Document $predecessor,
        User $actor,
        CarbonInterface $effectiveFrom,
        array $locations,
        string $correlationId,
    ): array {
        $this->authorizer->ordinary($actor, $predecessor);

        return DB::transaction(function () use (
            $predecessor,
            $actor,
            $effectiveFrom,
            $locations,
            $correlationId,
        ): array {
            DocumentFamily::query()->whereKey($predecessor->document_family_id)->lockForUpdate()->firstOrFail();
            $versions = Document::query()
                ->with(['workspace', 'family', 'applicabilitySnapshot.locations'])
                ->where('document_family_id', $predecessor->document_family_id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $source = $versions->firstWhere('id', $predecessor->id);
            if (! $source instanceof Document) {
                throw new DocumentGovernanceException('The predecessor no longer belongs to the locked document family.');
            }
            $this->authorizer->ordinary($actor, $source);
            if ($source->status !== DocumentStatus::Indexed) {
                throw new DocumentGovernanceException('Only an indexed predecessor can supply an applicability-only successor.');
            }
            foreach ($locations as $location) {
                if ($location->workspace_id !== $source->workspace_id) {
                    throw new DocumentGovernanceException('Applicability locations must belong to the document workspace.');
                }
            }
            $extension = pathinfo($source->storage_key, PATHINFO_EXTENSION);
            $target = $this->createVersion->handle(
                $source,
                $actor,
                $source->source_filename,
                $source->media_type,
                $source->size_bytes,
                $effectiveFrom,
                $locations,
                $extension === '' ? null : $extension,
            );
            $sourceAttempt = $this->compatibility->sourceAttempt($source);
            if ($sourceAttempt === null) {
                return [$target, null, null];
            }

            $leaseToken = (string) Str::uuid();
            $eventId = (string) Str::uuid();
            $targetAttempt = IngestionEventClaim::query()->create([
                'event_id' => $eventId,
                'workspace_id' => $target->workspace_id,
                'document_id' => $target->id,
                'workspace_public_id' => $sourceAttempt->workspace_public_id,
                'document_public_id' => $target->public_id,
                'correlation_id' => $correlationId,
                'payload_sha256' => hash('sha256', implode("\n", [
                    'content_clone', $sourceAttempt->event_id, $target->public_id, $correlationId,
                ])),
                'attempt_origin' => IngestionAttemptOrigin::ContentClone,
                'materialisation_pipeline_fingerprint' => $sourceAttempt->materialisation_pipeline_fingerprint,
                'materialisation_pipeline_components' => $sourceAttempt->materialisation_pipeline_components,
                'claimed_at' => now(),
                'embedding_space_generation_id' => $sourceAttempt->embedding_space_generation_id,
                'sparse_space_generation_id' => $sourceAttempt->sparse_space_generation_id,
                'workspace_corpus_generation_id' => $sourceAttempt->workspace_corpus_generation_id,
                'status' => IngestionAttemptStatus::Open,
                'lease_token_hash' => hash('sha256', $leaseToken),
                'lease_generation' => 1,
                'lease_expires_at' => now()->addSeconds(max(30, (int) config('ingestion.orchestration.lease_seconds'))),
            ]);
            $this->compatibility->assertSamePipeline($sourceAttempt, $targetAttempt);
            $operation = DocumentContentCloneOperation::query()->create([
                'public_id' => (string) Str::uuid(),
                'workspace_id' => $target->workspace_id,
                'source_document_id' => $source->id,
                'target_document_id' => $target->id,
                'source_ingestion_event_claim_id' => $sourceAttempt->id,
                'target_ingestion_event_claim_id' => $targetAttempt->id,
                'status' => DocumentContentCloneStatus::Authorised,
                'materialisation_pipeline_fingerprint' => $sourceAttempt->materialisation_pipeline_fingerprint,
                'materialisation_pipeline_components' => $sourceAttempt->materialisation_pipeline_components,
                'source_checksum_sha256' => $source->source_checksum_sha256,
                'authorised_at' => now(),
            ]);
            ($this->activity ?? app(MaintainDocumentFamilyActivitySummary::class))
                ->record($target->family()->firstOrFail(), $operation->authorised_at);
            $target->forceFill(['status' => DocumentStatus::Processing])->save();

            return [$target->refresh(), $operation, $leaseToken];
        });
    }

    public function finish(
        Document $predecessor,
        Document $target,
        ?DocumentContentCloneOperation $operation,
        ?string $leaseToken,
        string $correlationId,
        ?string $fallbackEventId = null,
    ): Document {
        if ($operation === null) {
            return $this->copySourceAndRequestIngestion($predecessor, $target, $correlationId, $fallbackEventId);
        }
        if ($leaseToken === null) {
            throw new DocumentGovernanceException('The authorised clone lease is unavailable.');
        }

        try {
            return $this->materialise->handle($operation, $leaseToken);
        } catch (Throwable $error) {
            $fallback = $this->materialise->cleanupForFallback($operation);
            $fallback->forceFill(['status' => DocumentStatus::Uploaded])->save();
            $this->requestIngestion->handle($fallback, $correlationId, $fallbackEventId);
            report($error);

            return $fallback->refresh();
        }
    }

    private function copySourceAndRequestIngestion(
        Document $source,
        Document $target,
        string $correlationId,
        ?string $fallbackEventId = null,
    ): Document {
        $storage = app(DocumentObjectStorage::class);
        $identity = $storage->copy($source, $target);
        if ($source->source_checksum_sha256 === null || ! hash_equals($source->source_checksum_sha256, $identity['sha256'])) {
            throw new DocumentGovernanceException('The copied source checksum did not match its predecessor.');
        }
        $target->forceFill([
            'source_checksum_sha256' => $identity['sha256'],
            'checksum_verification_status' => ChecksumVerificationStatus::Verified,
            'checksum_unavailable_reason' => null,
            'size_bytes' => $identity['size_bytes'],
            'status' => DocumentStatus::Uploaded,
        ])->save();

        return $this->requestIngestion->handle($target, $correlationId, $fallbackEventId);
    }
}
