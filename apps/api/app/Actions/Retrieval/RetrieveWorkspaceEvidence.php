<?php

declare(strict_types=1);

namespace App\Actions\Retrieval;

use App\Enums\RetrievalOutcome;
use App\Exceptions\RetrievalException;
use App\Models\Document;
use App\Models\DocumentChunk;
use App\Services\Retrieval\EligibilityResolver;
use App\Services\Retrieval\RetrievalClient;
use App\Support\Documents\DocumentAuthorityTimeline;
use App\Support\Retrieval\AuthorisedKnowledgeScope;
use App\Support\Retrieval\RetrievalResult;
use Carbon\CarbonImmutable;

final readonly class RetrieveWorkspaceEvidence
{
    public function __construct(
        private RetrievalClient $client,
        private EligibilityResolver $eligibility,
        private DocumentAuthorityTimeline $timeline,
    ) {}

    public function handle(
        AuthorisedKnowledgeScope $authorised,
        string $question,
        int $candidateK,
    ): RetrievalResult {
        $evaluatedAt = CarbonImmutable::now();
        try {
            $plan = $this->client->plan($authorised->workspace, $question, $evaluatedAt);
            $eligible = $this->eligibility->handle($authorised, $plan, $evaluatedAt);
            if (! $eligible->canSearch()) {
                return new RetrievalResult($eligible->outcome, reason: $eligible->reason);
            }
            $corpus = $authorised->activeCorpusGeneration;
            if ($corpus === null) {
                return new RetrievalResult(RetrievalOutcome::NoEligibleEvidence);
            }
            $candidates = $this->client->search(
                $authorised->workspace,
                $corpus,
                $eligible,
                $plan->query,
                $candidateK,
            );
            if ($candidates === []) {
                return new RetrievalResult(RetrievalOutcome::NoRetrievalCandidates);
            }

            $freshEligible = $this->eligibility->handle($authorised, $plan, $evaluatedAt);
            $hydrated = $this->hydrateAndRecheck(
                $candidates,
                $freshEligible->canSearch()
                    ? $freshEligible->documentPublicIdsBySide
                    : [],
                $authorised,
            );

            return new RetrievalResult(
                $hydrated === []
                    ? RetrievalOutcome::NoRetrievalCandidates
                    : RetrievalOutcome::EvidenceFound,
                $hydrated,
            );
        } catch (RetrievalException) {
            return new RetrievalResult(RetrievalOutcome::RetrievalFailed);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $candidates
     * @param  array<string, list<string>>  $eligibleBySide
     * @return list<array<string, mixed>>
     */
    private function hydrateAndRecheck(
        array $candidates,
        array $eligibleBySide,
        AuthorisedKnowledgeScope $authorised,
    ): array {
        $chunkIds = collect($candidates)
            ->pluck('chunk_id')
            ->filter(fn (mixed $value): bool => is_string($value))
            ->unique()
            ->values();
        $chunks = DocumentChunk::query()
            ->where('workspace_id', $authorised->workspace->id)
            ->whereIn('public_id', $chunkIds)
            ->with(['document.family', 'document.createdBy'])
            ->get()
            ->keyBy('public_id');
        $generationId = $authorised->activeCorpusGeneration?->id;
        $result = [];
        foreach ($candidates as $candidate) {
            $side = $candidate['side'] ?? null;
            $chunkId = $candidate['chunk_id'] ?? null;
            $documentId = $candidate['document_id'] ?? null;
            if (! is_string($side) || ! is_string($chunkId) || ! is_string($documentId)) {
                continue;
            }
            $chunk = $chunks->get($chunkId);
            if (! $chunk instanceof DocumentChunk || $chunk->document->public_id !== $documentId) {
                continue;
            }
            if (! in_array($documentId, $eligibleBySide[$side] ?? [], true)) {
                continue;
            }
            if (
                $candidate['workspace_corpus_generation_id']
                    !== $authorised->activeCorpusGeneration?->public_id
                || $candidate['embedding_space_generation_id']
                    !== $authorised->activeCorpusGeneration?->embeddingSpaceGeneration->public_id
            ) {
                continue;
            }
            if ($generationId === null || ! $chunk->workspaceCorpusGenerations()->whereKey($generationId)->exists()) {
                continue;
            }
            $result[] = $this->candidate($candidate, $chunk);
        }

        return $result;
    }

    /** @param array<string, mixed> $candidate @return array<string, mixed> */
    private function candidate(array $candidate, DocumentChunk $chunk): array
    {
        $document = $chunk->document;
        $lineage = $this->timeline->attainedVersions($document->family);
        $position = $lineage->search(fn (Document $item): bool => $item->is($document));

        return [
            'chunk_id' => $chunk->public_id,
            'document_id' => $document->public_id,
            'document_family_id' => $document->family->public_id,
            'version_position' => is_int($position) ? $position + 1 : null,
            'source_filename' => $document->source_filename,
            'chunk_text' => $chunk->text,
            'provenance' => $chunk->provenance,
            'score' => $candidate['score'],
            'rank' => $candidate['rank'],
            'retrieval_method' => $candidate['retrieval_method'],
            'side' => $candidate['side'],
            'workspace_corpus_generation_id' => $candidate['workspace_corpus_generation_id'],
            'embedding_space_generation_id' => $candidate['embedding_space_generation_id'],
        ];
    }
}
