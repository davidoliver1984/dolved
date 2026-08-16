<?php

declare(strict_types=1);

namespace App\Actions\Evaluation;

use App\Actions\Documents\ApproveDocumentVersion;
use App\Actions\Documents\CompleteDocumentUpload;
use App\Actions\Documents\CreateDocument;
use App\Actions\Documents\CreateDocumentVersion;
use App\Actions\Documents\RequestDocumentIngestion;
use App\Actions\Documents\WithdrawDocumentVersion;
use App\Actions\Workspaces\CreateWorkspace;
use App\Models\Document;
use App\Models\DocumentFamily;
use App\Models\OrganisationalLocation;
use App\Models\OrganisationalLocationAlias;
use App\Models\OutboxEvent;
use App\Models\User;
use App\Models\Workspace;
use App\Support\Evaluation\V3EngineeringBenchmark;
use App\Support\Evaluation\V3EngineeringBenchmarkSource;
use App\Support\Evaluation\V3EngineeringBenchmarkState;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;
use RuntimeException;
use Throwable;

final readonly class ProvisionV3EngineeringBenchmark
{
    public function __construct(
        private V3EngineeringBenchmarkSource $source,
        private V3EngineeringBenchmarkState $states,
        private CreateWorkspace $createWorkspace,
        private CreateDocument $createDocument,
        private CreateDocumentVersion $createVersion,
        private ApproveDocumentVersion $approveVersion,
        private WithdrawDocumentVersion $withdrawVersion,
        private CompleteDocumentUpload $completeUpload,
        private RequestDocumentIngestion $requestIngestion,
        private FilesystemFactory $filesystems,
    ) {}

    /** @return array<string, mixed> */
    public function handle(string $repositoryCommit): array
    {
        $this->assertLocalEnvironment();
        if (preg_match('/^[0-9a-f]{40}$/', $repositoryCommit) !== 1) {
            throw new RuntimeException('V3 provisioning requires the exact repository commit.');
        }
        $benchmark = $this->source->load();
        $definition = $benchmark['provisioning'];
        if ($this->states->exists()) {
            $existing = $this->states->read();
            if (
                ($existing['repository_commit'] ?? null) === $repositoryCommit
                && ($existing['provisioning_definition_digest'] ?? null) === V3EngineeringBenchmark::PROVISIONING_DEFINITION_DIGEST
                && in_array($existing['status'] ?? null, ['QUEUED', 'DENSE_VERIFIED', 'MATERIALISED'], true)
            ) {
                return $existing;
            }
            throw new RuntimeException('A V3 provisioning record already exists; provisioning fails closed.');
        }
        if (
            Workspace::query()->where('slug', V3EngineeringBenchmark::WORKSPACE_SLUG)->exists()
            || User::query()->where('email', V3EngineeringBenchmark::OWNER_EMAIL)->exists()
        ) {
            throw new RuntimeException('The reserved V3 identity exists without its trusted state record.');
        }

        $owner = User::query()->create([
            'name' => V3EngineeringBenchmark::OWNER_NAME,
            'email' => V3EngineeringBenchmark::OWNER_EMAIL,
            'email_verified_at' => now(),
            'password' => Str::password(64),
        ]);
        $this->uuidSequence([$definition['workspace']['public_id']]);
        try {
            $workspace = $this->createWorkspace->handle($owner, V3EngineeringBenchmark::WORKSPACE_NAME);
        } finally {
            Str::createUuidsNormally();
        }
        $workspace->forceFill(['slug' => V3EngineeringBenchmark::WORKSPACE_SLUG])->save();
        $state = [
            'schema_version' => 'v1',
            'status' => 'PROVISIONING',
            'repository_commit' => $repositoryCommit,
            'benchmark' => [
                'id' => V3EngineeringBenchmark::ID,
                'version' => V3EngineeringBenchmark::VERSION,
                'population_id' => V3EngineeringBenchmark::POPULATION_ID,
                'population_digest' => V3EngineeringBenchmark::POPULATION_DIGEST,
                'authoring_digest' => V3EngineeringBenchmark::BENCHMARK_AUTHORING_DIGEST,
                'evaluation_clock' => $benchmark['corpus']['evaluation_clock'],
            ],
            'provisioning_definition_digest' => V3EngineeringBenchmark::PROVISIONING_DEFINITION_DIGEST,
            'workspace' => [
                'public_id' => $workspace->public_id,
                'slug' => $workspace->slug,
                'name' => $workspace->name,
            ],
            'owner' => ['id' => $owner->id, 'email' => $owner->email],
            'locations' => [],
            'document_families' => [],
            'document_versions' => [],
            'ingestion_events' => [],
            'generations' => [],
            'created_at' => now()->toIso8601String(),
        ];
        $state = $this->states->write($state);

        try {
            $locations = $this->locations($workspace, $definition, $state);
            $documents = $this->documents($workspace, $owner, $locations, $benchmark['catalog'], $definition, $state);
            $this->govern($owner, $documents, $benchmark['catalog']);
            foreach ($documents as $versionId => $document) {
                $correlationId = (string) Str::uuid();
                $this->requestIngestion->handle($document->fresh(), $correlationId);
                $event = OutboxEvent::query()->where('document_public_id', $document->public_id)->latest('id')->firstOrFail();
                $state['ingestion_events'][$versionId] = [
                    'event_id' => $event->event_id,
                    'correlation_id' => $correlationId,
                ];
            }
            $state['status'] = 'QUEUED';
            $state['queued_at'] = now()->toIso8601String();

            return $this->states->write($state);
        } catch (Throwable $exception) {
            $state['status'] = 'FAILED';
            $state['failure'] = ['type' => $exception::class, 'message' => $exception->getMessage()];
            $this->states->write($state);

            throw $exception;
        } finally {
            Date::setTestNow();
            Str::createUuidsNormally();
        }
    }

    /** @param array<string, mixed> $definition @param array<string, mixed> $state @return array<string, OrganisationalLocation> */
    private function locations(Workspace $workspace, array $definition, array &$state): array
    {
        $locations = [];
        $remaining = collect($definition['locations']);
        while ($remaining->isNotEmpty()) {
            $before = $remaining->count();
            $remaining = $remaining->reject(function (array $item) use ($workspace, &$locations, &$state): bool {
                $parentId = $item['parent_location_id'];
                if ($parentId !== null && ! isset($locations[$parentId])) {
                    return false;
                }
                $location = new OrganisationalLocation([
                    'name' => $item['name'],
                    'kind' => Str::lower($item['kind']),
                    'parent_id' => $parentId === null ? null : $locations[$parentId]->id,
                ]);
                $location->public_id = $item['public_id'];
                $location->workspace()->associate($workspace);
                $location->save();
                $locations[$item['location_id']] = $location;
                $state['locations'][$item['location_id']] = $location->public_id;
                foreach ($item['aliases'] as $alias) {
                    $model = new OrganisationalLocationAlias(['alias' => $alias]);
                    $model->workspace()->associate($workspace);
                    $model->organisationalLocation()->associate($location);
                    $model->save();
                }
                $this->states->write($state);

                return true;
            });
            if ($remaining->count() === $before) {
                throw new RuntimeException('The V3 location hierarchy is cyclic or incomplete.');
            }
        }

        return $locations;
    }

    /** @param array<string, OrganisationalLocation> $locations @param array<string, mixed> $catalog @param array<string, mixed> $definition @param array<string, mixed> $state @return array<string, Document> */
    private function documents(Workspace $workspace, User $owner, array $locations, array $catalog, array $definition, array &$state): array
    {
        $definedFamilies = collect($definition['document_families'])->keyBy('document_family_id');
        $definedVersions = collect($definition['documents'])->keyBy('document_version_id');
        $familyDefaults = [];
        DocumentFamily::created(function (DocumentFamily $family) use (&$familyDefaults): void {
            $locations = $familyDefaults[$family->public_id] ?? [];
            if ($locations !== []) {
                $family->defaultApplicabilityLocations()->attach(collect($locations)->mapWithKeys(
                    fn (OrganisationalLocation $location): array => [$location->id => ['workspace_id' => $family->workspace_id]],
                ));
            }
        });
        $documents = [];
        foreach ($catalog['families'] as $family) {
            $definedFamily = $definedFamilies->get($family['family_id']);
            if (! is_array($definedFamily)) {
                throw new RuntimeException('The V3 definition is missing a document family.');
            }
            foreach ($family['versions'] as $index => $version) {
                $defined = $definedVersions->get($version['version_id']);
                if (! is_array($defined) || $defined['document_family_id'] !== $family['family_id']) {
                    throw new RuntimeException('The V3 definition is missing a document version.');
                }
                $contents = $this->source->document($version['source_path'], $version['source_sha256']);
                $applicability = array_map(fn (string $id): OrganisationalLocation => $locations[$id], $version['applicability']['location_ids']);
                Date::setTestNow(CarbonImmutable::parse($version['created_at']));
                if ($index === 0) {
                    $familyDefaults[$definedFamily['public_id']] = $applicability;
                    $this->uuidSequence([$definedFamily['public_id'], $defined['document_public_id']]);
                    try {
                        $document = $this->createDocument->handle(
                            $workspace,
                            $owner,
                            basename($version['source_path']),
                            'text/markdown',
                            strlen($contents),
                            'md',
                        );
                    } finally {
                        Str::createUuidsNormally();
                    }
                    $document->family->update(['name' => $family['title']]);
                    $document->forceFill(['effective_from' => CarbonImmutable::parse($version['effective_from'])])->save();
                } else {
                    $predecessor = $documents[$version['supersedes_version_id']];
                    $this->uuidSequence([$defined['document_public_id']]);
                    try {
                        $document = $this->createVersion->handle(
                            $predecessor,
                            $owner,
                            basename($version['source_path']),
                            'text/markdown',
                            strlen($contents),
                            CarbonImmutable::parse($version['effective_from']),
                            $applicability,
                            'md',
                        );
                    } finally {
                        Str::createUuidsNormally();
                    }
                }
                $stored = $this->filesystems->disk((string) config('documents.storage_disk'))->put($document->storage_key, $contents);
                if (! $stored) {
                    throw new RuntimeException('A V3 source object could not be persisted.');
                }
                $this->completeUpload->handle($document);
                $documents[$version['version_id']] = $document;
                $state['document_families'][$family['family_id']] = $document->family->public_id;
                $state['document_versions'][$version['version_id']] = [
                    'public_id' => $document->public_id,
                    'family_id' => $family['family_id'],
                    'source_path' => $version['source_path'],
                    'source_digest' => hash('sha256', $contents),
                    'storage_key' => $document->storage_key,
                ];
                $this->states->write($state);
            }
        }
        if (count($state['document_families']) !== V3EngineeringBenchmark::EXPECTED_FAMILIES || count($documents) !== V3EngineeringBenchmark::EXPECTED_VERSIONS) {
            throw new RuntimeException('The materialised V3 document inventory is incomplete.');
        }

        return $documents;
    }

    /** @param array<string, Document> $documents @param array<string, mixed> $catalog */
    private function govern(User $owner, array $documents, array $catalog): void
    {
        $events = [];
        foreach ($catalog['families'] as $family) {
            foreach ($family['versions'] as $version) {
                if ($version['approved_at'] !== null) {
                    $events[] = ['at' => $version['approved_at'], 'action' => 'approve', 'id' => $version['version_id']];
                }
                if ($version['withdrawn_at'] !== null) {
                    $events[] = ['at' => $version['withdrawn_at'], 'action' => 'withdraw', 'id' => $version['version_id']];
                }
            }
        }
        usort($events, fn (array $left, array $right): int => [$left['at'], $left['action']] <=> [$right['at'], $right['action']]);
        foreach ($events as $event) {
            Date::setTestNow(CarbonImmutable::parse($event['at']));
            if ($event['action'] === 'approve') {
                $this->approveVersion->handle($documents[$event['id']]->fresh(), $owner);
            } else {
                $this->withdrawVersion->handle($documents[$event['id']]->fresh(), $owner);
            }
        }
        Date::setTestNow();
    }

    /** @param list<string> $identities */
    private function uuidSequence(array $identities): void
    {
        Str::createUuidsUsingSequence(array_map(fn (string $identity) => Uuid::fromString($identity), $identities));
    }

    private function assertLocalEnvironment(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('V3 engineering provisioning is restricted to local/testing environments.');
        }
    }
}
