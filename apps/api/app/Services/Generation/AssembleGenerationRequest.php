<?php

declare(strict_types=1);

namespace App\Services\Generation;

use App\Enums\GenerationSide;
use App\Enums\RetrievalOutcome;
use App\Exceptions\GenerationException;
use App\Models\DocumentChunk;
use App\Support\Generation\GenerationAssemblyInput;
use App\Support\Generation\GenerationEvidence;
use App\Support\Generation\GenerationRequest;
use Illuminate\Support\Str;

final readonly class AssembleGenerationRequest
{
    public function __construct(private GenerationContextPacker $packer) {}

    public function handle(GenerationAssemblyInput $input): GenerationRequest
    {
        if ($input->retrievalResult->outcome !== RetrievalOutcome::EvidenceFound || $input->retrievalResult->candidates === []) {
            throw new GenerationException('Generation requires a final authorised EVIDENCE_FOUND retrieval result.');
        }

        $candidateChunkIds = collect($input->retrievalResult->candidates)
            ->pluck('chunk_id')
            ->filter(fn (mixed $value): bool => is_string($value))
            ->unique()
            ->values();
        $chunks = DocumentChunk::query()
            ->where('workspace_id', $input->authorisedScope->workspace->id)
            ->whereIn('public_id', $candidateChunkIds)
            ->get()->keyBy('public_id');
        $evidence = [];
        foreach ($input->retrievalResult->candidates as $index => $candidate) {
            $chunk = $chunks->get($candidate['chunk_id'] ?? null);
            if (! $chunk instanceof DocumentChunk
                || $chunk->document_id !== $this->documentInternalId($candidate, $input)
                || $chunk->ingestion_event_claim_id === null
                || ! is_string($candidate['chunk_text'] ?? null)
                || ! hash_equals($chunk->text, $candidate['chunk_text'])) {
                throw new GenerationException('Final evidence identity does not match the authorised workspace snapshot.');
            }
            $side = GenerationSide::tryFrom((string) ($candidate['side'] ?? ''));
            if ($side === null) {
                throw new GenerationException('Final evidence has an invalid generation side.');
            }
            $evidence[] = new GenerationEvidence(
                sprintf('ev-%02d', $index + 1),
                $chunk->text,
                $chunk->id,
                $chunk->document_id,
                $chunk->ingestion_event_claim_id,
                $chunk->provenance,
                $input->resolvedTemporalAuthority,
                $input->resolvedApplicabilityLocation,
                $side,
            );
        }
        $requiredSides = collect($evidence)->pluck('side')->unique(fn (GenerationSide $side): string => $side->value)->values()->all();
        $policyVersion = (string) config('generation.context_policy_version');
        $maximum = (int) config('generation.max_context_characters');
        $packed = $this->packer->pack($evidence, $requiredSides, $maximum, $policyVersion);

        return new GenerationRequest(
            (string) Str::uuid(),
            (string) $input->authorisedScope->workspace->public_id,
            $input->question,
            $packed,
            $policyVersion,
            $maximum,
            $requiredSides,
        );
    }

    private function documentInternalId(array $candidate, GenerationAssemblyInput $input): int
    {
        $publicId = $candidate['document_id'] ?? null;
        if (! is_string($publicId)) {
            return 0;
        }

        return (int) $input->authorisedScope->workspace->documents()->where('public_id', $publicId)->value('id');
    }
}
