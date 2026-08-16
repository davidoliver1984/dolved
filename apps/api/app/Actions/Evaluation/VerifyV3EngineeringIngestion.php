<?php

declare(strict_types=1);

namespace App\Actions\Evaluation;

use App\Enums\DocumentStatus;
use App\Enums\IngestionAttemptStatus;
use App\Enums\WorkspaceCorpusGenerationStatus;
use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\IngestionEventClaim;
use App\Models\OutboxEvent;
use App\Models\Workspace;
use App\Services\Retrieval\RetrievalClient;
use App\Support\Evaluation\V3EngineeringBenchmark;
use App\Support\Evaluation\V3EngineeringBenchmarkState;
use RuntimeException;

final readonly class VerifyV3EngineeringIngestion
{
    public function __construct(
        private V3EngineeringBenchmarkState $states,
        private RetrievalClient $retrieval,
    ) {}

    /** @return array<string, mixed> */
    public function handle(int $timeoutSeconds, int $pollMilliseconds): array
    {
        if ($timeoutSeconds < 1 || $timeoutSeconds > 21600 || $pollMilliseconds < 100 || $pollMilliseconds > 5000) {
            throw new RuntimeException('The V3 ingestion wait bounds are invalid.');
        }
        $state = $this->states->read();
        $this->assertState($state, ['QUEUED', 'DENSE_VERIFIED']);
        $deadline = hrtime(true) + ($timeoutSeconds * 1_000_000_000);
        do {
            $diagnostics = $this->diagnostics($state);
            if ($diagnostics['failed_documents'] !== [] || $diagnostics['failed_outbox_events'] !== []) {
                throw new RuntimeException('V3 ingestion failed: '.json_encode($diagnostics, JSON_THROW_ON_ERROR));
            }
            if ($diagnostics['indexed_documents'] === V3EngineeringBenchmark::EXPECTED_VERSIONS) {
                return $this->verifyDense($state, $diagnostics);
            }
            if (hrtime(true) >= $deadline) {
                throw new RuntimeException('V3 ingestion timed out: '.json_encode($diagnostics, JSON_THROW_ON_ERROR));
            }
            usleep($pollMilliseconds * 1000);
        } while (true);
    }

    /** @param array<string, mixed> $state @return array<string, mixed> */
    private function diagnostics(array $state): array
    {
        $documentIds = collect($state['document_versions'])->pluck('public_id')->all();
        $eventIds = collect($state['ingestion_events'])->pluck('event_id')->all();
        $documents = Document::query()->whereIn('public_id', $documentIds)->get();
        $events = OutboxEvent::query()->whereIn('event_id', $eventIds)->get();
        $attempts = IngestionEventClaim::query()->whereIn('event_id', $eventIds)->get();

        return [
            'expected_documents' => V3EngineeringBenchmark::EXPECTED_VERSIONS,
            'observed_documents' => $documents->count(),
            'indexed_documents' => $documents->where('status', DocumentStatus::Indexed)->count(),
            'document_states' => $documents->groupBy(fn (Document $document): string => $document->status->value)->map->count()->sortKeys()->all(),
            'failed_documents' => $documents->where('status', DocumentStatus::Failed)->map(fn (Document $document): array => [
                'public_id' => $document->public_id,
                'category' => $document->failure_category,
            ])->values()->all(),
            'published_outbox_events' => $events->whereNotNull('published_at')->count(),
            'failed_outbox_events' => $events->whereNotNull('failed_at')->pluck('event_id')->values()->all(),
            'attempt_states' => $attempts->groupBy(fn (IngestionEventClaim $attempt): string => $attempt->status->value)->map->count()->sortKeys()->all(),
            'open_attempts' => $attempts->where('status', '!=', IngestionAttemptStatus::Completed)->count(),
        ];
    }

    /** @param array<string, mixed> $state @param array<string, mixed> $diagnostics @return array<string, mixed> */
    private function verifyDense(array $state, array $diagnostics): array
    {
        if (
            $diagnostics['observed_documents'] !== V3EngineeringBenchmark::EXPECTED_VERSIONS
            || $diagnostics['published_outbox_events'] !== V3EngineeringBenchmark::EXPECTED_VERSIONS
            || ($diagnostics['attempt_states'][IngestionAttemptStatus::Completed->value] ?? 0) !== V3EngineeringBenchmark::EXPECTED_VERSIONS
            || $diagnostics['open_attempts'] !== 0
        ) {
            throw new RuntimeException('V3 terminal documents lack complete ingestion evidence.');
        }
        $workspace = Workspace::query()->where('public_id', $state['workspace']['public_id'])->sole();
        $generation = $workspace->activeCorpusGeneration()->with([
            'embeddingSpaceGeneration.embeddingProfile',
            'documentChunks',
        ])->firstOrFail();
        if ($generation->status !== WorkspaceCorpusGenerationStatus::Active || $generation->sparse_space_generation_id !== null) {
            throw new RuntimeException('V3 ingestion did not produce one active dense generation.');
        }
        $chunkCount = DocumentChunk::query()->where('workspace_id', $workspace->id)->count();
        if ($chunkCount < 1 || $generation->documentChunks->count() !== $chunkCount) {
            throw new RuntimeException('V3 canonical chunks do not reconcile with the dense generation.');
        }
        $verification = $this->retrieval->verifyCorpus($workspace, $generation);
        if (! $verification['complete'] || count($verification['point_ids']) !== $chunkCount) {
            throw new RuntimeException('V3 dense Qdrant completeness verification failed.');
        }
        $state['status'] = 'DENSE_VERIFIED';
        $state['canonical_chunk_count'] = $chunkCount;
        $state['dense_verified_at'] = now()->toIso8601String();
        $state['generations']['embedding_space'] = [
            'public_id' => $generation->embeddingSpaceGeneration->public_id,
            'profile_fingerprint' => $generation->embeddingSpaceGeneration->embeddingProfile->fingerprint,
            'collection_name' => $generation->embeddingSpaceGeneration->collection_name,
            'vector_name' => $generation->embeddingSpaceGeneration->vector_name,
            'dimensions' => $generation->embeddingSpaceGeneration->dimensions,
            'distance' => $generation->embeddingSpaceGeneration->distance,
        ];
        $state['generations']['dense_corpus'] = [
            'public_id' => $generation->public_id,
            'point_count' => count($verification['point_ids']),
            'point_ids' => $verification['point_ids'],
        ];

        return $this->states->write($state);
    }

    /** @param array<string, mixed> $state @param list<string> $allowed */
    private function assertState(array $state, array $allowed): void
    {
        if (
            ! in_array($state['status'] ?? null, $allowed, true)
            || ($state['benchmark']['population_digest'] ?? null) !== V3EngineeringBenchmark::POPULATION_DIGEST
            || ($state['provisioning_definition_digest'] ?? null) !== V3EngineeringBenchmark::PROVISIONING_DEFINITION_DIGEST
            || ($state['workspace']['slug'] ?? null) !== V3EngineeringBenchmark::WORKSPACE_SLUG
        ) {
            throw new RuntimeException('The V3 provisioning state is not eligible for verification.');
        }
    }
}
