<?php

declare(strict_types=1);

namespace App\Support\Ingestion;

use App\Enums\DocumentStatus;
use App\Enums\IngestionClaimOutcome;

final readonly class IngestionClaimResult
{
    public function __construct(
        public IngestionClaimOutcome $outcome,
        public ?DocumentStatus $documentStatus,
        public ?string $leaseToken = null,
        public ?string $leaseExpiresAt = null,
        public ?string $embeddingSpaceGenerationId = null,
        public ?string $workspaceCorpusGenerationId = null,
        public ?string $collectionName = null,
        public ?string $vectorName = null,
        public ?int $dimensions = null,
        public ?string $distance = null,
        public ?string $embeddingProfileFingerprint = null,
        public bool $resumeSealedAttempt = false,
        public bool $resetOpenAttempt = false,
    ) {}
}
