<?php

declare(strict_types=1);

namespace App\Services\Generation;

use App\Enums\GenerationSide;
use App\Enums\RequestedEvidenceType;
use App\Enums\RetrievalOutcome;
use App\Exceptions\GenerationContextBudgetExceeded;
use App\Exceptions\GenerationException;
use App\Models\DocumentChunk;
use App\Support\Generation\GenerationAssemblyInput;
use App\Support\Generation\GenerationEvidence;
use App\Support\Generation\GenerationRequest;
use App\Support\Retrieval\NeedsEvidenceSelection;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

final readonly class AssembleGenerationRequest
{
    public function __construct(
        private GenerationContextPacker $packer,
        private ResolvedEvidenceTemporalStatus $temporalStatus,
    ) {}

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
        $needs = collect($input->retrievalResult->evidenceNeeds)->keyBy('need_id');
        if ($needs->count() !== count($input->retrievalResult->evidenceNeeds) || $needs->count() > NeedsEvidenceSelection::MAX_NEEDS) {
            throw new GenerationException('Invalid bounded generation needs.');
        }
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
            $authority = $input->resolvedTemporalAuthority;
            $applicability = $input->resolvedApplicabilityLocation;
            if ($needs->isNotEmpty()) {
                $need = $needs->get($candidate['need_id'] ?? null);
                $familyId = $chunk->document->family->public_id;
                if ($need === null || ($candidate['document_family_id'] ?? null) !== $familyId) {
                    throw new GenerationException('Evidence escaped its generation need or family.');
                }
                $authority = [...$need['resolved_temporal_authority'], 'need_id' => $need['need_id'],
                    'document_family_id' => $familyId, 'document_version_id' => $candidate['document_id'],
                    'document_title' => mb_substr($chunk->document->source_filename, 0, 500),
                    'document_family_title' => mb_substr($chunk->document->family->name, 0, 500)];
                $applicability = $need['resolved_applicability_location'];
            }
            if (isset($authority['evaluated_at'])) {
                $authority = [...$authority, ...$this->temporalStatus->forDocument(
                    $chunk->document,
                    CarbonImmutable::parse($authority['evaluated_at']),
                )];
            }
            $evidence[] = new GenerationEvidence(
                sprintf('ev-%02d', $index + 1),
                $chunk->text,
                $chunk->id,
                $chunk->document_id,
                $chunk->ingestion_event_claim_id,
                $chunk->provenance,
                $authority,
                $applicability,
                $side,
            );
        }
        $requiredSides = collect($evidence)->pluck('side')->unique(fn (GenerationSide $side): string => $side->value)->values()->all();
        $policyVersion = (string) config('generation.context_policy_version');
        $maximum = (int) config('generation.max_context_characters');
        $generationNeeds = [];
        if ($needs->isEmpty()) {
            $packed = $this->packer->pack($evidence, $requiredSides, $maximum, $policyVersion);
        } else {
            $policyVersion = 'whole-needs-evidence-v1';
            $units = array_sum(array_map(fn ($item) => mb_strlen($item->text, 'UTF-8'), $evidence));
            if (count($evidence) > NeedsEvidenceSelection::FINAL_K || $units > $maximum) {
                throw new GenerationContextBudgetExceeded($policyVersion, $units, $maximum);
            }
            // Preserve the already-selected bounded units whole; never split a pair.
            $packed = $evidence;
            foreach ($needs as $need) {
                $items = array_values(array_filter($packed, fn ($item) => $item->temporalAuthority['need_id'] === $need['need_id']));
                $families = [];
                foreach ($items as $item) {
                    $families[$item->temporalAuthority['document_family_id']][$item->side->value][] = $item->documentId;
                }
                foreach ($families as $sides) {
                    if ($need['operation'] === 'compare') {
                        if (empty($sides['primary']) || empty($sides['comparison'])
                            || array_intersect($sides['primary'], $sides['comparison']) !== []) {
                            throw new GenerationException('Generation requires distinct paired versions within each need and family.');
                        }
                    } elseif (isset($sides['comparison'])) {
                        throw new GenerationException('Current supporting evidence cannot borrow a comparison lane.');
                    }
                }
                $generationNeeds[] = array_intersect_key($need, array_flip([
                    'need_id', 'aspect', 'central', 'operation', 'authority', 'fact_reference', 'applicability', 'claim_under_test',
                    'retrieval_outcome', 'retrieval_reason',
                ])) + ['evidence_ids' => array_map(fn ($item) => $item->evidenceId, $items)];
            }
            if (NeedsEvidenceSelection::searchLanes($needs->values()->all()) > NeedsEvidenceSelection::MAX_SEARCH_LANES) {
                throw new GenerationException('Generation needs exceed the accepted slice fanout.');
            }
        }
        $requestedEvidenceType = RequestedEvidenceType::tryFrom(
            (string) ($input->resolvedTemporalAuthority['requested_evidence_type'] ?? '')
        ) ?? RequestedEvidenceType::PolicyOrProceduralRequirements;

        return new GenerationRequest(
            (string) Str::uuid(),
            (string) $input->authorisedScope->workspace->public_id,
            $input->question,
            $packed,
            $policyVersion,
            $maximum,
            $requiredSides,
            $requestedEvidenceType,
            $generationNeeds,
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
