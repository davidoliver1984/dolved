<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Ingestion\AuthoriseIngestionPublication;
use App\Actions\Ingestion\ClaimDocumentIngestion;
use App\Actions\Ingestion\CompleteIngestionAttempt;
use App\Actions\Ingestion\FailIngestionAttempt;
use App\Actions\Ingestion\RenewIngestionLease;
use App\Actions\Ingestion\ResumeIngestionAttempt;
use App\Actions\Ingestion\SealIngestionChunks;
use App\Actions\Ingestion\SubmitIngestionChunks;
use App\Enums\DocumentStatus;
use App\Enums\IngestionAttemptStatus;
use App\Enums\WorkspaceCorpusGenerationStatus;
use App\Exceptions\IngestionAttemptException;
use App\Models\Document;
use App\Models\EmbeddingProfile;
use App\Models\EmbeddingSpaceGeneration;
use App\Models\IngestionEventClaim;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Ingestion\DeterministicVectorPointIdentity;
use App\Services\Ingestion\IngestionCanonicaliser;
use App\Services\Ingestion\IngestionWorkerRequestAuthenticator;
use App\Support\Ingestion\DocumentIngestionRequestedPayload;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull;
use Illuminate\Foundation\Http\Middleware\TrimStrings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class EndToEndIngestionOrchestrationTest extends TestCase
{
    use RefreshDatabase;

    private Document $document;

    /** @var array<string, mixed> */
    private array $event;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow('2026-08-06 12:00:00');
        config(['ingestion.worker_auth.keys' => [
            'test-v2' => 'MDEyMzQ1Njc4OWFiY2RlZjAxMjM0NTY3ODlhYmNkZWY=',
        ]]);
        $profile = EmbeddingProfile::factory()->voyageV1()->create();
        EmbeddingSpaceGeneration::factory()->for($profile)->available()->create([
            'dimensions' => 1024,
            'collection_name' => 'rag-platform-vectors-v1',
        ]);
        $this->document = Document::factory()
            ->for(Workspace::factory(), 'workspace')
            ->for(User::factory(), 'createdBy')
            ->create(['status' => DocumentStatus::Queued]);
        $this->event = app(DocumentIngestionRequestedPayload::class)->build(
            $this->document->load('workspace'),
            (string) Str::uuid(),
            (string) Str::uuid(),
            CarbonImmutable::now(),
        );
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_successful_saga_is_idempotent_and_atomically_indexes_the_document(): void
    {
        [$attempt, $context] = $this->claim();
        $chunk = $this->chunk();
        $submit = app(SubmitIngestionChunks::class);

        $this->assertSame(1, $submit->handle($attempt->event_id, [...$context, 'chunks' => [$chunk]]));
        $this->assertSame(1, $submit->handle($attempt->event_id, [...$context, 'chunks' => [$chunk]]));
        $manifest = app(IngestionCanonicaliser::class)->chunkManifestDigest([[
            'chunk_id' => $chunk['chunk_id'],
            'ordinal' => 0,
            'content_digest' => $chunk['content_digest'],
        ]]);
        app(SealIngestionChunks::class)->handle($attempt->event_id, [
            ...$context,
            'expected_chunk_count' => 1,
            'chunk_manifest_digest' => $manifest,
            'configuration_fingerprint' => $chunk['configuration_fingerprint'],
        ]);
        $evidence = $this->evidence($attempt->fresh(), $chunk, $manifest);
        app(AuthoriseIngestionPublication::class)->handle($attempt->event_id, [...$context, ...$evidence]);
        $this->assertSame(
            WorkspaceCorpusGenerationStatus::Verifying,
            $attempt->workspaceCorpusGeneration->refresh()->status,
        );
        $completed = app(CompleteIngestionAttempt::class)->handle($attempt->event_id, [
            ...$context,
            ...$evidence,
            'publication_verified' => true,
        ]);
        $duplicate = app(CompleteIngestionAttempt::class)->handle($attempt->event_id, [
            ...$context,
            ...$evidence,
            'publication_verified' => true,
        ]);

        $this->assertSame(IngestionAttemptStatus::Completed, $completed->status);
        $this->assertSame($completed->id, $duplicate->id);
        $this->assertSame(DocumentStatus::Indexed, $this->document->refresh()->status);
        $this->assertSame(WorkspaceCorpusGenerationStatus::Active, $attempt->workspaceCorpusGeneration->refresh()->status);
        $this->assertSame(
            $attempt->workspace_corpus_generation_id,
            $this->document->workspace->refresh()->active_workspace_corpus_generation_id,
        );
        $this->assertDatabaseCount('document_chunks', 1);
        $this->assertDatabaseCount('workspace_corpus_generation_chunks', 1);
        $this->assertDatabaseCount('ingestion_audit_events', 2);
        $this->assertSame(
            [['code' => 'images_not_extracted', 'message' => 'Images were not extracted.']],
            $completed->publication_evidence['warnings'],
        );
    }

    public function test_chunk_conflicts_and_stale_leases_fail_closed(): void
    {
        [$attempt, $context] = $this->claim();
        $chunk = $this->chunk();
        app(SubmitIngestionChunks::class)->handle($attempt->event_id, [...$context, 'chunks' => [$chunk]]);

        try {
            app(SubmitIngestionChunks::class)->handle($attempt->event_id, [
                ...$context,
                'chunks' => [[...$chunk, 'text' => 'changed']],
            ]);
            $this->fail('A conflicting deterministic chunk was accepted.');
        } catch (IngestionAttemptException $exception) {
            $this->assertSame('chunk_digest_mismatch', $exception->errorCode);
        }

        $oldToken = $context['lease_token'];
        $attempt->forceFill(['lease_expires_at' => now()->subSecond()])->save();
        $reclaimed = app(ClaimDocumentIngestion::class)->handle($this->event, $this->eventDigest());
        $this->assertTrue($reclaimed->resetOpenAttempt);
        $this->assertDatabaseCount('document_chunks', 0);
        $this->expectException(IngestionAttemptException::class);
        app(RenewIngestionLease::class)->handle($attempt->event_id, [
            ...$context,
            'lease_token' => $oldToken,
        ]);
    }

    public function test_sealed_reclaim_preserves_and_pages_authoritative_chunks(): void
    {
        [$attempt, $context] = $this->claim();
        $chunk = $this->chunk();
        app(SubmitIngestionChunks::class)->handle($attempt->event_id, [...$context, 'chunks' => [$chunk]]);
        $manifest = app(IngestionCanonicaliser::class)->chunkManifestDigest([[
            'chunk_id' => $chunk['chunk_id'], 'ordinal' => 0, 'content_digest' => $chunk['content_digest'],
        ]]);
        app(SealIngestionChunks::class)->handle($attempt->event_id, [
            ...$context,
            'expected_chunk_count' => 1,
            'chunk_manifest_digest' => $manifest,
            'configuration_fingerprint' => $chunk['configuration_fingerprint'],
        ]);
        $attempt->refresh()->forceFill(['lease_expires_at' => now()->subSecond()])->save();
        $grant = app(ClaimDocumentIngestion::class)->handle($this->event, $this->eventDigest());
        $page = app(ResumeIngestionAttempt::class)->handle($attempt->event_id, [
            ...$context,
            'lease_token' => $grant->leaseToken,
            'cursor' => 0,
            'limit' => 1,
        ]);

        $this->assertTrue($grant->resumeSealedAttempt);
        $this->assertFalse($grant->resetOpenAttempt);
        $this->assertSame($chunk['text'], $page['chunks'][0]['text']);
        $this->assertNull($page['next_cursor']);
    }

    public function test_python_provenance_shapes_survive_the_http_boundary_and_persist_with_their_digest(): void
    {
        [$attempt, $context] = $this->claim();
        $chunks = $this->provenanceVectors();

        $response = $this->submitChunksOverHttp($attempt, $context, $chunks);

        $response->assertOk()->assertJsonPath('data.persisted_chunk_count', 3);
        foreach ($chunks as $chunk) {
            $persisted = $attempt->chunks()->where('public_id', $chunk['chunk_id'])->sole();

            $this->assertSame($chunk['text'], $persisted->text);
            $this->assertSame($chunk['content_digest'], $persisted->content_digest);
            $this->assertSame($chunk['configuration'], $persisted->configuration);
            $this->assertEquals($chunk['provenance'], $persisted->provenance);
        }

        $canonical = $attempt->chunks()->where('ordinal', 0)->sole();
        $this->assertStringStartsWith('  #', $canonical->text);
        $this->assertStringEndsWith("  \n ", $canonical->text);
        $this->assertSame('', $canonical->configuration['empty_label']);
    }

    public function test_unknown_or_malformed_source_location_fields_are_rejected(): void
    {
        [$attempt, $context] = $this->claim();
        $unknown = $this->provenanceVectors()[0];
        $unknown['provenance'][0]['source_locations'][0]['unsupported'] = 'value';
        $unknown['content_digest'] = app(IngestionCanonicaliser::class)->chunkContentDigest($unknown);

        $this->submitChunksOverHttp($attempt, $context, [$unknown])->assertUnprocessable();
        $this->assertDatabaseCount('document_chunks', 0);

        $malformed = $this->provenanceVectors()[1];
        $malformed['provenance'][0]['source_locations'][0]['x0'] = 2.0;
        $malformed['provenance'][0]['source_locations'][0]['x1'] = 1.0;
        $malformed['content_digest'] = app(IngestionCanonicaliser::class)->chunkContentDigest($malformed);

        $this->submitChunksOverHttp($attempt, $context, [$malformed])->assertUnprocessable();
        $this->assertDatabaseCount('document_chunks', 0);
    }

    public function test_intentionally_incorrect_cross_service_digest_still_fails_closed(): void
    {
        [$attempt, $context] = $this->claim();
        $chunk = $this->provenanceVectors()[2];
        $chunk['content_digest'] = str_repeat('f', 64);

        $this->submitChunksOverHttp($attempt, $context, [$chunk])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'chunk_digest_mismatch');
        $this->assertDatabaseCount('document_chunks', 0);
    }

    public function test_canonical_chunk_exemption_does_not_disable_ordinary_request_normalisation(): void
    {
        $request = Request::create(
            '/ordinary-request',
            'POST',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['name' => '  ordinary value  ', 'empty' => ''], JSON_THROW_ON_ERROR),
        );

        (new TrimStrings)->handle($request, fn (Request $request): Request => $request);
        (new ConvertEmptyStringsToNull)->handle($request, fn (Request $request): Request => $request);

        $this->assertSame('ordinary value', $request->input('name'));
        $this->assertNull($request->input('empty'));
    }

    public function test_permanent_failure_is_authoritative_and_idempotent(): void
    {
        [$attempt, $context] = $this->claim();
        $payload = [
            ...$context,
            'classification' => 'permanent',
            'failure_code' => 'extraction.invalid_encoding',
            'failure_message' => 'The source encoding is invalid.',
        ];
        app(FailIngestionAttempt::class)->handle($attempt->event_id, $payload);
        app(FailIngestionAttempt::class)->handle($attempt->event_id, $payload);

        $this->assertSame(DocumentStatus::Failed, $this->document->refresh()->status);
        $this->assertSame(IngestionAttemptStatus::Failed, $attempt->refresh()->status);
        $this->assertDatabaseCount('ingestion_audit_events', 1);
    }

    public function test_clean_migration_contains_attempt_evidence_audit_and_chunk_scope(): void
    {
        $this->assertTrue(Schema::hasColumns('ingestion_event_claims', [
            'lease_token_hash', 'lease_generation', 'lease_expires_at', 'status',
            'chunk_manifest_digest', 'publication_evidence', 'point_manifest_digest',
        ]));
        $this->assertTrue(Schema::hasColumns('document_chunks', [
            'ingestion_event_claim_id', 'content_digest',
        ]));
        $this->assertTrue(Schema::hasTable('ingestion_audit_events'));
    }

    public function test_signature_for_one_purpose_cannot_authorise_another_operation(): void
    {
        [$attempt, $context] = $this->claim();
        $path = "/api/internal/ingestion/events/{$attempt->event_id}/lease/renew";
        $body = json_encode($context, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $timestamp = (string) now()->timestamp;
        $purpose = 'ingestion.complete';
        $canonical = implode("\n", [
            $timestamp, 'POST', $path, hash('sha256', $body), $attempt->event_id, $purpose,
        ]);
        $secret = base64_decode('MDEyMzQ1Njc4OWFiY2RlZjAxMjM0NTY3ODlhYmNkZWY=', true);
        $this->assertIsString($secret);

        $this->call('POST', $path, server: $this->transformHeadersToServerVars([
            'Content-Type' => 'application/json',
            IngestionWorkerRequestAuthenticator::KEY_ID_HEADER => 'test-v2',
            IngestionWorkerRequestAuthenticator::TIMESTAMP_HEADER => $timestamp,
            IngestionWorkerRequestAuthenticator::EVENT_ID_HEADER => $attempt->event_id,
            IngestionWorkerRequestAuthenticator::PURPOSE_HEADER => $purpose,
            IngestionWorkerRequestAuthenticator::SIGNATURE_HEADER => 'v2='.hash_hmac('sha256', $canonical, $secret),
        ]), content: $body)->assertUnauthorized();
    }

    public function test_embedding_space_provisioning_is_explicit_and_idempotent(): void
    {
        EmbeddingSpaceGeneration::query()->delete();
        EmbeddingProfile::query()->delete();

        $this->artisan('ingestion:provision-embedding-space')->assertSuccessful();
        $this->artisan('ingestion:provision-embedding-space')->assertSuccessful();

        $this->assertDatabaseCount('embedding_profiles', 1);
        $this->assertDatabaseCount('embedding_space_generations', 1);
        $this->assertSame(
            'available',
            EmbeddingSpaceGeneration::query()->sole()->status->value,
        );
    }

    public function test_claim_fails_closed_when_no_embedding_space_is_available(): void
    {
        EmbeddingSpaceGeneration::query()->delete();

        try {
            app(ClaimDocumentIngestion::class)->handle($this->event, $this->eventDigest());
            $this->fail('A claim proceeded without a provisioned embedding space.');
        } catch (IngestionAttemptException $exception) {
            $this->assertSame('embedding_space_unavailable', $exception->errorCode);
            $this->assertSame(503, $exception->httpStatus);
        }

        $this->assertSame(DocumentStatus::Queued, $this->document->refresh()->status);
        $this->assertDatabaseCount('ingestion_event_claims', 0);
        $this->assertDatabaseCount('workspace_corpus_generations', 0);
    }

    /** @return array{IngestionEventClaim, array<string, mixed>} */
    private function claim(): array
    {
        $grant = app(ClaimDocumentIngestion::class)->handle($this->event, $this->eventDigest());
        $attempt = IngestionEventClaim::query()->sole();

        return [$attempt, [
            'contract_version' => 1,
            'event_id' => $attempt->event_id,
            'workspace_id' => $this->event['workspace_id'],
            'document_id' => $this->event['document_id'],
            'lease_token' => $grant->leaseToken,
        ]];
    }

    /** @return array<string, mixed> */
    private function chunk(): array
    {
        $chunk = [
            'chunk_id' => (string) Str::uuid(),
            'ordinal' => 0,
            'text' => 'Authoritative canonical text.',
            'token_count' => 5,
            'strategy_name' => 'baseline-structural',
            'strategy_version' => '1',
            'configuration' => ['target_tokens' => 400],
            'configuration_fingerprint' => hash('sha256', 'configuration'),
            'provenance' => [['source_element_ids' => [(string) Str::uuid()]]],
        ];
        $chunk['content_digest'] = app(IngestionCanonicaliser::class)->chunkContentDigest($chunk);

        return $chunk;
    }

    /** @return array<string, mixed> */
    private function evidence(IngestionEventClaim $attempt, array $chunk, string $manifest): array
    {
        $pointId = app(DeterministicVectorPointIdentity::class)->forChunk(
            $attempt->embeddingSpaceGeneration->public_id,
            $attempt->workspace->public_id,
            $attempt->workspaceCorpusGeneration->public_id,
            $chunk['chunk_id'],
        );

        return [
            'expected_chunk_count' => 1,
            'chunk_manifest_digest' => $manifest,
            'expected_point_count' => 1,
            'point_ids' => [$pointId],
            'point_manifest_digest' => app(IngestionCanonicaliser::class)->pointManifestDigest([$pointId]),
            'embedding_profile_fingerprint' => $attempt->embeddingSpaceGeneration->embeddingProfile->fingerprint,
            'embedding_space_generation_id' => $attempt->embeddingSpaceGeneration->public_id,
            'workspace_corpus_generation_id' => $attempt->workspaceCorpusGeneration->public_id,
            'warnings' => [[
                'code' => 'images_not_extracted',
                'message' => 'Images were not extracted.',
            ]],
        ];
    }

    private function eventDigest(): string
    {
        return hash('sha256', json_encode($this->event, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }

    /** @return array<int, array<string, mixed>> */
    private function provenanceVectors(): array
    {
        $fixture = json_decode(
            file_get_contents('/contracts/http/ingestion-worker/v1/provenance-vectors.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        return $fixture['chunks'];
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<int, array<string, mixed>>  $chunks
     */
    private function submitChunksOverHttp(
        IngestionEventClaim $attempt,
        array $context,
        array $chunks,
    ): TestResponse {
        $payload = [...$context, 'chunks' => $chunks];
        $path = "/api/internal/ingestion/events/{$attempt->event_id}/chunks";
        $body = json_encode(
            $payload,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION,
        );
        $timestamp = (string) now()->timestamp;
        $canonical = implode("\n", [
            $timestamp,
            'POST',
            $path,
            hash('sha256', $body),
            $attempt->event_id,
            'ingestion.chunks.submit',
        ]);
        $secret = base64_decode('MDEyMzQ1Njc4OWFiY2RlZjAxMjM0NTY3ODlhYmNkZWY=', true);
        $this->assertIsString($secret);

        return $this->call('POST', $path, server: $this->transformHeadersToServerVars([
            'Content-Type' => 'application/json',
            IngestionWorkerRequestAuthenticator::KEY_ID_HEADER => 'test-v2',
            IngestionWorkerRequestAuthenticator::TIMESTAMP_HEADER => $timestamp,
            IngestionWorkerRequestAuthenticator::EVENT_ID_HEADER => $attempt->event_id,
            IngestionWorkerRequestAuthenticator::PURPOSE_HEADER => 'ingestion.chunks.submit',
            IngestionWorkerRequestAuthenticator::SIGNATURE_HEADER => 'v2='.hash_hmac('sha256', $canonical, $secret),
        ]), content: $body);
    }
}
