<?php

declare(strict_types=1);

namespace App\Actions\Retrieval;

use App\Enums\RetrievalOutcome;
use App\Exceptions\RetrievalException;
use App\Models\Document;
use App\Models\DocumentChunk;
use App\Services\Retrieval\EligibilityResolver;
use App\Services\Retrieval\ResolveEvidenceThresholdPolicy;
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
        private ResolveEvidenceThresholdPolicy $policies,
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
            $policy = $corpus->sparse_space_generation_id === null
                ? null
                : $this->policies->handle($corpus);
            $search = $this->client->search(
                $authorised->workspace,
                $corpus,
                $eligible,
                $plan->query,
                $candidateK,
                $policy,
            );
            $candidates = $search->candidates;
            if ($candidates === []) {
                return new RetrievalResult(RetrievalOutcome::NoRetrievalCandidates);
            }
            if ($policy !== null) {
                $this->policies->assertSearchLineage($policy, $search->lineage);
                if (
                    ($search->lineage['sparse_space_generation_id'] ?? null)
                    !== $corpus->sparseSpaceGeneration?->public_id
                ) {
                    throw new RetrievalException('Sparse generation lineage does not match the active corpus.');
                }
            }

            $freshEligible = $this->eligibility->handle($authorised, $plan, $evaluatedAt);
            $hydrated = $this->hydrateAndRecheck(
                $candidates,
                $freshEligible->canSearch()
                    ? $freshEligible->documentPublicIdsBySide
                    : [],
                $authorised,
            );

            if ($hydrated === []) {
                return new RetrievalResult(RetrievalOutcome::NoRetrievalCandidates);
            }
            if ($policy === null) {
                return new RetrievalResult(RetrievalOutcome::EvidenceFound, $hydrated);
            }

            $reranked = $this->client->rerank(
                $authorised->workspace,
                $plan->query,
                $hydrated,
                $policy,
            );
            $this->policies->assertRerankerLineage($policy, $reranked['profile']);
            $rerankedByChunk = collect($reranked['candidates'])->keyBy(
                fn (array $candidate): string => $candidate['side'].':'.$candidate['chunk_id']
            );
            $candidateByChunk = collect($candidates)->keyBy(
                fn (array $candidate): string => $candidate['side'].':'.$candidate['chunk_id']
            );
            $rerankCandidates = collect($reranked['candidates'])->map(
                fn (array $rerankedCandidate): ?array => $candidateByChunk->get(
                    $rerankedCandidate['side'].':'.$rerankedCandidate['chunk_id']
                )
            )->filter()->values()->all();
            $finalEligible = $this->eligibility->handle($authorised, $plan, $evaluatedAt);
            $final = $this->hydrateAndRecheck(
                $rerankCandidates,
                $finalEligible->canSearch() ? $finalEligible->documentPublicIdsBySide : [],
                $authorised,
            );
            $qualified = collect($final)->map(function (array $candidate) use ($rerankedByChunk, $policy): ?array {
                $rerankedCandidate = $rerankedByChunk->get(
                    $candidate['side'].':'.$candidate['chunk_id']
                );
                if (! is_array($rerankedCandidate) || (float) $rerankedCandidate['score'] < $policy->evidence_threshold) {
                    return null;
                }

                return [
                    ...$candidate,
                    'fused_score' => $candidate['score'],
                    'fused_rank' => $candidate['rank'],
                    'score' => (float) $rerankedCandidate['score'],
                    'rank' => $rerankedCandidate['rank'],
                    'evidence_threshold_policy_version' => $policy->version,
                    'evidence_threshold_policy_fingerprint' => $policy->fingerprint,
                    'reranker_provider' => $policy->reranker_provider,
                    'reranker_model' => $policy->reranker_model,
                    'reranker_adapter_version' => $policy->reranker_adapter_version,
                ];
            })->filter()->sortBy('rank')->values();
            if ($qualified->isEmpty()) {
                return new RetrievalResult(RetrievalOutcome::InsufficientEvidence);
            }

            $sides = array_keys($eligible->documentPublicIdsBySide);
            if (count($sides) === 2 && collect($sides)->contains(
                fn (string $side): bool => ! $qualified->contains(
                    fn (array $candidate): bool => $candidate['side'] === $side
                )
            )) {
                return new RetrievalResult(RetrievalOutcome::ComparisonScopeIncomplete);
            }
            $accepted = $qualified->groupBy('side')->flatMap(
                fn ($sideCandidates) => $sideCandidates->take($policy->final_evidence_k)
            )->sortBy('rank')->values()->all();

            return new RetrievalResult(RetrievalOutcome::EvidenceFound, $accepted);
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
            $expectedSparse = $authorised->activeCorpusGeneration?->sparseSpaceGeneration?->public_id;
            if (($candidate['sparse_space_generation_id'] ?? null) !== $expectedSparse) {
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
            'sparse_space_generation_id' => $candidate['sparse_space_generation_id'] ?? null,
            'dense_score' => $candidate['dense_score'] ?? null,
            'dense_rank' => $candidate['dense_rank'] ?? null,
            'sparse_score' => $candidate['sparse_score'] ?? null,
            'sparse_rank' => $candidate['sparse_rank'] ?? null,
        ];
    }
}
