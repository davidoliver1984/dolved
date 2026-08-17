<?php

declare(strict_types=1);

namespace App\Support\Generation;

use App\Enums\GenerationSide;

final readonly class GenerationEvidence
{
    /** @param list<array<string, mixed>> $sourceProvenance @param array<string, mixed> $temporalAuthority @param array<string, mixed> $applicabilityContext */
    public function __construct(
        public string $evidenceId,
        public string $text,
        public int $documentChunkId,
        public int $documentId,
        public int $ingestionEventClaimId,
        public array $sourceProvenance,
        public array $temporalAuthority,
        public array $applicabilityContext,
        public GenerationSide $side,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'evidence_id' => $this->evidenceId,
            'text' => $this->text,
            'document_chunk_id' => $this->documentChunkId,
            'document_id' => $this->documentId,
            'ingestion_event_claim_id' => $this->ingestionEventClaimId,
            'source_provenance' => $this->sourceProvenance,
            'temporal_authority' => $this->temporalAuthority,
            'applicability_context' => $this->applicabilityContext,
            'side' => $this->side->value,
        ];
    }
}
