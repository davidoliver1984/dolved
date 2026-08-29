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
        public ?int $leaseGeneration = null,
        public ?string $embeddingSpaceGenerationId = null,
        public ?string $workspaceCorpusGenerationId = null,
        public ?string $collectionName = null,
        public ?string $vectorName = null,
        public ?int $dimensions = null,
        public ?string $distance = null,
        public ?string $embeddingProfileFingerprint = null,
        public ?string $sparseSpaceGenerationId = null,
        public ?string $sparseVectorName = null,
        public ?string $sparseProfileFingerprint = null,
        /** @var array<string, mixed>|null */
        public ?array $sparseProfile = null,
        public bool $resumeSealedAttempt = false,
        public bool $resetOpenAttempt = false,
    ) {}
}
