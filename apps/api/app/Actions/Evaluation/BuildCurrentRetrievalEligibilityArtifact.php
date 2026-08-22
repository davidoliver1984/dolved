<?php

declare(strict_types=1);

namespace App\Actions\Evaluation;

use App\Actions\Workspaces\CreateWorkspace;
use App\Enums\DocumentGovernanceStatus;
use App\Enums\DocumentStatus;
use App\Enums\EmbeddingSpaceGenerationStatus;
use App\Enums\WorkspaceCorpusGenerationStatus;
use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\DocumentFamily;
use App\Models\EmbeddingProfile;
use App\Models\EmbeddingSpaceGeneration;
use App\Models\OrganisationalLocation;
use App\Models\OrganisationalLocationAlias;
use App\Models\SparseEmbeddingProfile;
use App\Models\SparseSpaceGeneration;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceCorpusGeneration;
use App\Models\WorkspaceCorpusGenerationChunk;
use App\Queries\Retrieval\BuildAuthorisedKnowledgeScope;
use App\Services\Retrieval\EligibilityResolver;
use App\Support\Documents\CreateApplicabilitySnapshot;
use App\Support\Evaluation\BenchmarkCanonicalJson;
use App\Support\Retrieval\RetrievalPlan;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;
use Ramsey\Uuid\Uuid;
use RuntimeException;

final readonly class BuildCurrentRetrievalEligibilityArtifact
{
    public function __construct(
        private CreateWorkspace $createWorkspace,
        private CreateApplicabilitySnapshot $applicability,
        private BuildAuthorisedKnowledgeScope $authorisedScopes,
        private EligibilityResolver $eligibility,
        private BenchmarkCanonicalJson $canonical,
    ) {}

    /** @return array<string, mixed> */
    public function handle(
        string $runId,
        string $repositoryCommit,
        string $documentCatalogPath,
        string $organisationPath,
        string $planCatalogPath,
        string $schemaPath,
        int $expectedVariantCount = 126,
    ): array {
        $catalog = $this->json($documentCatalogPath);
        $organisation = $this->json($organisationPath);
        $plans = $this->json($planCatalogPath);
        $this->assertInputs($catalog, $organisation, $plans, $repositoryCommit, $expectedVariantCount);
        $evaluatedAt = CarbonImmutable::parse((string) $organisation['evaluation_clock']);

        $artifact = DB::transaction(function () use ($runId, $repositoryCommit, $catalog, $organisation, $plans, $evaluatedAt, $schemaPath): array {
            $documentCount = collect($catalog['families'])->sum(
                fn (array $family): int => count($family['versions']),
            );
            [$user, $workspace, $generation] = $this->workspace($runId, $documentCount);
            $locations = $this->locations($workspace, $organisation);
            $documents = $this->documents($workspace, $user, $generation, $locations, $catalog);
            $foreignDocument = $this->foreignDocument($runId);
            $scope = $this->authorisedScopes->handle($user, $workspace->public_id);
            $lineage = $this->plannerLineage($plans);
            $usage = [
                'stage' => 'planner', 'provider' => 'deterministic',
                'model' => 'engineering-expectations-v2', 'execution' => 'local',
                'request_count' => 1, 'retry_count' => 0, 'input_tokens' => null,
                'cached_input_tokens' => null, 'output_tokens' => null,
                'latency_ms' => 0, 'cost_basis' => 'zero_cost_local',
                'cost_usd' => 0, 'pricing_snapshot' => null,
            ];
            $entries = [];
            $scopedIds = [];
            foreach ($plans['expectations'] as $expectation) {
                $question = (string) $expectation['question'];
                $contract = $expectation['contract'];
                $plan = RetrievalPlan::fromArray([
                    'retrieval_queries' => [$question],
                    'temporal_mode' => Str::lower((string) $contract['temporal_mode']),
                    'explicit_date' => $contract['explicit_date'],
                    'temporal_reference' => $contract['temporal_reference'],
                    'location_references' => $contract['location_references'],
                    'clarification_reason' => $contract['clarification_reason'],
                ], $question, $lineage, $usage);
                $resolved = $this->eligibility->handle($scope, $plan, $evaluatedAt);
                foreach ($resolved->documentPublicIdsBySide as $ids) {
                    array_push($scopedIds, ...$ids);
                }
                $entries[] = [
                    'case_id' => $expectation['case_id'],
                    'variant_id' => $expectation['variant_id'],
                    'question_digest' => hash('sha256', $question),
                    'outcome' => $resolved->outcome->value,
                    'reason' => $resolved->reason,
                    'clarification_source' => $resolved->clarificationSource?->value,
                    'resolved_location_public_id' => $resolved->resolvedLocationPublicId,
                    'document_public_ids_by_side' => $resolved->documentPublicIdsBySide === []
                        ? (object) []
                        : $resolved->documentPublicIdsBySide,
                ];
            }
            if (in_array($foreignDocument->public_id, $scopedIds, true)) {
                throw new RuntimeException('A cross-workspace document entered an eligible retrieval scope.');
            }
            $foreignScope = $this->authorisedScopes->handle(
                $foreignDocument->workspace->creator,
                $foreignDocument->workspace->public_id,
            );
            $noCorpusProbe = $this->eligibility->handle(
                $foreignScope,
                RetrievalPlan::fromArray([
                    'retrieval_queries' => ['Current policy?'],
                    'temporal_mode' => 'current',
                    'explicit_date' => null,
                    'temporal_reference' => null,
                    'location_references' => [],
                    'clarification_reason' => null,
                ], 'Current policy?', $lineage, $usage),
                $evaluatedAt,
            );
            if ($noCorpusProbe->outcome->value !== 'no_eligible_evidence' || $noCorpusProbe->documentPublicIdsBySide !== []) {
                throw new RuntimeException('The no-active-corpus resolver probe did not fail narrow.');
            }
            try {
                $this->authorisedScopes->handle($user, $foreignDocument->workspace->public_id);
                throw new RuntimeException('A non-member workspace was not concealed.');
            } catch (ModelNotFoundException) {
                // The normal authorised-scope query must preserve 404-style concealment.
            }

            $documentBindings = collect($documents)->map(fn (Document $document, string $versionId): array => [
                'evaluation_document_version_id' => $versionId,
                'public_document_id' => $document->public_id,
                'qdrant_document_id' => $this->uuid("qdrant-document:{$versionId}"),
            ])->values()->all();
            $eligibilityCatalogue = [
                'version' => 'dolved-care-engineering-v2',
                'document_catalog_digest' => $this->canonical->digest($catalog),
                'organisation_digest' => $this->canonical->digest($organisation),
            ];
            $eligibilityCatalogue['digest'] = $this->canonical->digest($eligibilityCatalogue);
            $resolverSource = file_get_contents(app_path('Services/Retrieval/EligibilityResolver.php'));
            if ($resolverSource === false) {
                throw new RuntimeException('The eligibility resolver source is unreadable.');
            }
            $artifact = [
                'schema_version' => 'v1',
                'contract_id' => 'deterministic-eligibility-v1',
                'run_id' => $runId,
                'repository_commit' => $repositoryCommit,
                'evaluated_at' => $evaluatedAt->toIso8601ZuluString(),
                'workspace_public_id' => $workspace->public_id,
                'plan_catalogue' => [
                    'version' => (string) $plans['schema_version'],
                    'digest' => $this->canonical->digest($plans),
                ],
                'eligibility_catalogue' => $eligibilityCatalogue,
                'resolver' => [
                    'implementation' => EligibilityResolver::class,
                    'boundary' => 'evaluation:resolve-current-eligibility',
                    'source_digest' => hash('sha256', $resolverSource),
                    'configuration_digest' => $this->canonical->digest([
                        'evaluated_at' => $evaluatedAt->toIso8601ZuluString(),
                        'workspace_public_id' => $workspace->public_id,
                        'active_corpus_generation_public_id' => $generation->public_id,
                    ]),
                ],
                'documents' => $documentBindings,
                'entries' => $entries,
                'probes' => [
                    'no_active_corpus_generation' => [
                        'resolver_executed' => true,
                        'outcome' => $noCorpusProbe->outcome->value,
                        'eligible_document_count' => 0,
                    ],
                ],
                'isolation' => [
                    'foreign_workspace_probe_executed' => true,
                    'cross_workspace_document_count_in_scopes' => 0,
                ],
            ];
            $artifact['comparability_digest'] = $this->canonical->digest([
                'schema_version' => $artifact['schema_version'],
                'contract_id' => $artifact['contract_id'],
                'evaluated_at' => $artifact['evaluated_at'],
                'plan_catalogue' => $artifact['plan_catalogue'],
                'eligibility_catalogue' => $artifact['eligibility_catalogue'],
                'resolver' => $artifact['resolver'],
                'documents' => $artifact['documents'],
                'entries' => $artifact['entries'],
                'probes' => $artifact['probes'],
                'isolation' => $artifact['isolation'],
            ]);
            $artifact['artifact_digest'] = $this->canonical->digest($artifact);
            $this->validate($artifact, $schemaPath);

            return $artifact;
        });

        return $artifact;
    }

    /** @return array{User, Workspace, WorkspaceCorpusGeneration} */
    private function workspace(string $runId, int $documentCount): array
    {
        $user = User::query()->create([
            'name' => 'Dolved deterministic retrieval evaluator',
            'email' => "retrieval-current+{$runId}@dolved.invalid",
            'email_verified_at' => now(),
            'password' => Str::password(64),
        ]);
        Str::createUuidsUsingSequence([
            Uuid::fromString($this->uuid('workspace')),
            Uuid::fromString($this->uuid('workspace-membership')),
        ]);
        try {
            $workspace = $this->createWorkspace->handle($user, 'Dolved deterministic current retrieval');
        } finally {
            Str::createUuidsNormally();
        }
        $profile = new EmbeddingProfile([
            'fingerprint' => hash('sha256', 'deterministic-embedding-v1'),
            'provider' => 'deterministic', 'model' => 'sha256-unit-vector-v1',
            'dimensions' => 1024, 'output_dtype' => 'float',
            'document_input_type' => 'document', 'query_input_type' => 'query',
            'normalisation' => 'unit_length', 'truncation' => false,
            'model_revision' => null, 'adapter_version' => '1',
        ]);
        $profile->public_id = $this->uuid('embedding-profile');
        $profile->save();
        $space = new EmbeddingSpaceGeneration([
            'embedding_profile_id' => $profile->id,
            'collection_name' => 'dolved-evaluation-current-v1', 'vector_name' => 'dense',
            'dimensions' => 1024, 'distance' => 'cosine',
            'status' => EmbeddingSpaceGenerationStatus::Available, 'available_at' => now(),
        ]);
        $space->public_id = $this->uuid('embedding-space');
        $space->save();
        $sparseProfile = new SparseEmbeddingProfile([
            'fingerprint' => hash('sha256', 'deterministic-sparse-v1'),
            'provider' => 'deterministic', 'model' => 'sha256-sparse-v1',
            'tokenizer' => 'deterministic', 'tokenizer_revision' => null,
            'output_representation' => 'sparse-index-weight', 'max_input_tokens' => 512,
            'document_input_type' => 'document', 'query_input_type' => 'query',
            'model_revision' => null, 'adapter_version' => '1',
        ]);
        $sparseProfile->public_id = $this->uuid('sparse-profile');
        $sparseProfile->save();
        $sparse = new SparseSpaceGeneration([
            'sparse_embedding_profile_id' => $sparseProfile->id,
            'embedding_space_generation_id' => $space->id, 'vector_name' => 'sparse',
            'status' => EmbeddingSpaceGenerationStatus::Available, 'available_at' => now(),
        ]);
        $sparse->public_id = $this->uuid('sparse-space');
        $sparse->save();
        $generation = new WorkspaceCorpusGeneration([
            'workspace_id' => $workspace->id,
            'embedding_space_generation_id' => $space->id,
            'sparse_space_generation_id' => $sparse->id,
            'expected_point_count' => $documentCount,
            'point_manifest_digest' => hash('sha256', 'dolved-care-engineering-v2'),
            'verified_at' => now(), 'status' => WorkspaceCorpusGenerationStatus::Active,
            'activated_at' => now(),
        ]);
        $generation->public_id = $this->uuid('corpus-generation');
        $generation->save();
        $workspace->active_workspace_corpus_generation_id = $generation->id;
        $workspace->save();

        return [$user, $workspace->fresh(), $generation];
    }

    /** @param array<string, mixed> $organisation @return array<string, OrganisationalLocation> */
    private function locations(Workspace $workspace, array $organisation): array
    {
        $locations = [];
        $remaining = collect($organisation['locations']);
        while ($remaining->isNotEmpty()) {
            $before = $remaining->count();
            $remaining = $remaining->reject(function (array $item) use ($workspace, &$locations): bool {
                $parentId = $item['parent_location_id'];
                if ($parentId !== null && ! isset($locations[$parentId])) {
                    return false;
                }
                $location = new OrganisationalLocation([
                    'name' => $item['name'], 'kind' => Str::lower($item['kind']),
                    'parent_id' => $parentId === null ? null : $locations[$parentId]->id,
                ]);
                $location->public_id = $this->uuid('location:'.$item['location_id']);
                $location->workspace()->associate($workspace);
                $location->save();
                $locations[$item['location_id']] = $location;

                return true;
            });
            if ($before === $remaining->count()) {
                throw new RuntimeException('The evaluation location hierarchy is cyclic.');
            }
        }
        foreach ($organisation['aliases'] as $item) {
            foreach ($item['location_ids'] as $locationId) {
                $alias = new OrganisationalLocationAlias(['alias' => $item['alias']]);
                $alias->workspace()->associate($workspace);
                $alias->organisationalLocation()->associate($locations[$locationId]);
                $alias->save();
            }
        }

        return $locations;
    }

    /** @param array<string, OrganisationalLocation> $locations @param array<string, mixed> $catalog @return array<string, Document> */
    private function documents(Workspace $workspace, User $user, WorkspaceCorpusGeneration $generation, array $locations, array $catalog): array
    {
        $documents = [];
        foreach ($catalog['families'] as $familyItem) {
            $family = new DocumentFamily(['name' => $familyItem['title']]);
            $family->public_id = $this->uuid('family:'.$familyItem['family_id']);
            $family->workspace()->associate($workspace);
            $family->save();
            foreach ($familyItem['versions'] as $version) {
                $document = new Document;
                $document->public_id = $this->uuid('document:'.$version['version_id']);
                $document->workspace()->associate($workspace);
                $document->family()->associate($family);
                $document->predecessor_document_id = $version['supersedes_version_id'] === null
                    ? null : $documents[$version['supersedes_version_id']]->id;
                $document->createdBy()->associate($user);
                $document->status = DocumentStatus::Indexed;
                $document->governance_status = DocumentGovernanceStatus::from(Str::lower($version['governance_state']));
                $document->effective_from = CarbonImmutable::parse($version['effective_from']);
                $document->approved_at = $version['approved_at'] === null ? null : CarbonImmutable::parse($version['approved_at']);
                $document->withdrawn_at = $version['withdrawn_at'] === null ? null : CarbonImmutable::parse($version['withdrawn_at']);
                $document->source_filename = basename($version['source_path']);
                $document->media_type = 'text/markdown';
                $document->size_bytes = 1;
                $document->storage_key = 'evaluation-current/'.$version['source_path'];
                $document->save();
                $this->applicability->create($document, array_map(
                    fn (string $id): OrganisationalLocation => $locations[$id],
                    $version['applicability']['location_ids'],
                ));
                $configuration = ['strategy' => 'source-document-whole', 'version' => '1'];
                $chunk = new DocumentChunk([
                    'workspace_id' => $workspace->id, 'document_id' => $document->id,
                    'ordinal' => 0, 'text' => $version['version_id'], 'token_count' => 1,
                    'strategy_name' => 'source-document-whole', 'strategy_version' => '1',
                    'configuration' => $configuration,
                    'configuration_fingerprint' => $this->canonical->digest($configuration),
                    'provenance' => [[
                        'normalised_element_id' => $this->uuid('element:'.$version['version_id']),
                        'source_element_ids' => [$this->uuid('source:'.$version['version_id'])],
                        'source_locations' => [['type' => 'text', 'start_character' => 0, 'end_character' => 1, 'start_line' => 1, 'end_line' => 1]],
                        'element_start_character' => 0, 'element_end_character' => 1,
                        'chunk_start_character' => 0, 'chunk_end_character' => 1, 'role' => 'primary',
                    ]],
                    'content_digest' => hash('sha256', $version['version_id']),
                ]);
                $chunk->public_id = $this->uuid('chunk:'.$version['version_id']);
                $chunk->save();
                WorkspaceCorpusGenerationChunk::query()->create([
                    'workspace_id' => $workspace->id,
                    'workspace_corpus_generation_id' => $generation->id,
                    'document_chunk_id' => $chunk->id,
                ]);
                $documents[$version['version_id']] = $document;
            }
        }

        return $documents;
    }

    private function foreignDocument(string $runId): Document
    {
        Str::createUuidsUsingSequence([
            Uuid::fromString($this->uuid('foreign-workspace')),
            Uuid::fromString($this->uuid('foreign-membership')),
        ]);
        try {
            $foreign = $this->createWorkspace->handle(User::query()->create([
                'name' => 'Foreign evaluator', 'email' => "foreign+{$runId}@dolved.invalid",
                'email_verified_at' => now(), 'password' => Str::password(64),
            ]), 'Foreign deterministic workspace');
        } finally {
            Str::createUuidsNormally();
        }
        $family = new DocumentFamily(['name' => 'Foreign policy']);
        $family->public_id = $this->uuid('foreign-family');
        $family->workspace()->associate($foreign);
        $family->save();
        $document = new Document;
        $document->public_id = $this->uuid('foreign-document');
        $document->workspace()->associate($foreign);
        $document->family()->associate($family);
        $document->createdBy()->associate($foreign->creator);
        $document->status = DocumentStatus::Indexed;
        $document->governance_status = DocumentGovernanceStatus::Approved;
        $document->effective_from = now()->subYear();
        $document->approved_at = now()->subYear();
        $document->source_filename = 'foreign.md';
        $document->media_type = 'text/markdown';
        $document->size_bytes = 1;
        $document->storage_key = 'evaluation-current/foreign.md';
        $document->save();
        $document->setRelation('workspace', $foreign);

        return $document;
    }

    /** @param array<string, mixed> $plans @return array<string, string> */
    private function plannerLineage(array $plans): array
    {
        $parts = [
            'provider' => 'deterministic', 'model' => 'engineering-expectations-v2',
            'contract_schema_version' => 'plan-response-v2',
            'prompt_version' => 'catalogue-'.$this->canonical->digest($plans),
            'adapter_version' => 'laravel-catalogue-v1',
        ];
        $fingerprintParts = $parts;
        ksort($fingerprintParts);
        $parts['fingerprint'] = hash('sha256', json_encode($fingerprintParts, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

        return $parts;
    }

    /** @param array<string, mixed> $catalog @param array<string, mixed> $organisation @param array<string, mixed> $plans */
    private function assertInputs(array $catalog, array $organisation, array $plans, string $commit, int $expectedVariantCount): void
    {
        if (preg_match('/^[a-f0-9]{40}$/', $commit) !== 1
            || ($catalog['benchmark_id'] ?? null) !== 'dolved-care-engineering'
            || ($catalog['catalog_version'] ?? null) !== '1'
            || ($organisation['benchmark_id'] ?? null) !== 'dolved-care-engineering'
            || ($plans['schema_version'] ?? null) !== 'v2'
            || ($plans['scope'] ?? null) !== 'engineering_tuning'
            || $expectedVariantCount < 1
            || count($plans['expectations'] ?? []) !== $expectedVariantCount) {
            throw new RuntimeException('Current retrieval evaluation input identity is invalid.');
        }
    }

    /** @return array<string, mixed> */
    private function json(string $path): array
    {
        $value = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($value)) {
            throw new RuntimeException('Evaluation input must be a JSON object.');
        }

        return $value;
    }

    /** @param array<string, mixed> $artifact */
    private function validate(array $artifact, string $schemaPath): void
    {
        $schema = json_decode((string) file_get_contents($schemaPath));
        $result = (new Validator)->validate(json_decode($this->canonical->encode($artifact)), $schema);
        if (! $result->isValid()) {
            $errors = (new ErrorFormatter)->formatFlat($result->error());
            throw new RuntimeException('The deterministic eligibility artifact failed its shared contract: '.json_encode($errors, JSON_THROW_ON_ERROR));
        }
    }

    private function uuid(string $identity): string
    {
        return Uuid::uuid5(Uuid::NAMESPACE_URL, 'dolved:evaluation-current:'.$identity)->toString();
    }
}
