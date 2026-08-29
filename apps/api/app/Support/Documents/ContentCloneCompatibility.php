<?php

declare(strict_types=1);

namespace App\Support\Documents;

use App\Enums\ChecksumVerificationStatus;
use App\Enums\IngestionAttemptOrigin;
use App\Enums\IngestionAttemptStatus;
use App\Enums\WorkspaceCorpusGenerationStatus;
use App\Models\Document;
use App\Models\IngestionEventClaim;
use App\Support\Ingestion\MaterialisationPipelineIdentity;

final readonly class ContentCloneCompatibility
{
    public function __construct(private MaterialisationPipelineIdentity $pipelineIdentity) {}

    public function sourceAttempt(Document $source): ?IngestionEventClaim
    {
        if (
            $source->checksum_verification_status !== ChecksumVerificationStatus::Verified
            || $source->source_checksum_sha256 === null
        ) {
            return null;
        }

        $attempt = $source->ingestionAttempts()
            ->with([
                'embeddingSpaceGeneration.embeddingProfile',
                'workspaceCorpusGeneration.workspace',
                'workspaceCorpusGeneration.sparseSpaceGeneration.sparseEmbeddingProfile',
            ])
            ->where('status', IngestionAttemptStatus::Completed->value)
            ->whereNotNull('publication_evidence')
            ->orderByDesc('id')
            ->first();
        if (
            $attempt === null
            || $attempt->materialisation_pipeline_fingerprint === null
            || $attempt->materialisation_pipeline_components === null
            || $attempt->workspace_id !== $source->workspace_id
            || $attempt->workspaceCorpusGeneration->status !== WorkspaceCorpusGenerationStatus::Active
            || $attempt->workspaceCorpusGeneration->workspace->active_workspace_corpus_generation_id
                !== $attempt->workspace_corpus_generation_id
        ) {
            return null;
        }
        $current = $this->pipelineIdentity->for(
            $attempt->embeddingSpaceGeneration,
            $attempt->workspaceCorpusGeneration,
        );
        if (! hash_equals($attempt->materialisation_pipeline_fingerprint, $current['fingerprint'])) {
            return null;
        }

        return $attempt;
    }

    public function assertSamePipeline(
        IngestionEventClaim $source,
        IngestionEventClaim $target,
    ): void {
        if (
            $source->workspace_id !== $target->workspace_id
            || $target->attempt_origin !== IngestionAttemptOrigin::ContentClone
            || $source->materialisation_pipeline_fingerprint === null
            || $target->materialisation_pipeline_fingerprint === null
            || ! hash_equals(
                $source->materialisation_pipeline_fingerprint,
                $target->materialisation_pipeline_fingerprint,
            )
        ) {
            throw new \LogicException('Content-clone pipeline compatibility failed closed.');
        }
    }
}
