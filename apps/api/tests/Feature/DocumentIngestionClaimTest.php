<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Ingestion\ClaimDocumentIngestion;
use App\Enums\DocumentStatus;
use App\Enums\IngestionAttemptOrigin;
use App\Models\Document;
use App\Models\EmbeddingProfile;
use App\Models\EmbeddingSpaceGeneration;
use App\Models\IngestionEventClaim;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Ingestion\IngestionWorkerRequestAuthenticator;
use App\Support\Ingestion\DocumentIngestionRequestedPayload;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class DocumentIngestionClaimTest extends TestCase
{
    use RefreshDatabase;

    private const PRIMARY_KEY_ID = 'test-primary';

    private const PRIMARY_SECRET = 'MDEyMzQ1Njc4OWFiY2RlZjAxMjM0NTY3ODlhYmNkZWY=';

    private const SECONDARY_KEY_ID = 'test-secondary';

    private const SECONDARY_SECRET = 'YWJjZGVmMDEyMzQ1Njc4OWFiY2RlZjAxMjM0NTY3ODg5';

    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow('2026-07-29 12:00:00');
        config([
            'ingestion.worker_auth.keys' => [
                self::PRIMARY_KEY_ID => self::PRIMARY_SECRET,
                self::SECONDARY_KEY_ID => self::SECONDARY_SECRET,
            ],
            'ingestion.worker_auth.max_clock_skew_seconds' => 300,
        ]);
        $profile = EmbeddingProfile::factory()->voyageV1()->create();
        EmbeddingSpaceGeneration::factory()->for($profile)->available()->create([
            'dimensions' => 1024,
            'collection_name' => 'rag-platform-vectors-v1',
        ]);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_valid_worker_request_atomically_claims_a_queued_document(): void
    {
        $document = $this->document(DocumentStatus::Queued);
        $event = $this->eventFor($document);

        $this->signedRequest($event)
            ->assertOk()
            ->assertJsonPath('data.outcome', 'claimed')
            ->assertJsonPath('data.document_status', 'processing');

        $this->assertSame(
            DocumentStatus::Processing,
            $document->refresh()->status,
        );

        $claim = IngestionEventClaim::query()->sole();

        $this->assertSame($event['event_id'], $claim->event_id);
        $this->assertSame($event['workspace_id'], $claim->workspace_public_id);
        $this->assertSame($event['document_id'], $claim->document_public_id);
        $this->assertSame($event['correlation_id'], $claim->correlation_id);
        $this->assertSame(IngestionAttemptOrigin::Ingestion, $claim->attempt_origin);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', (string) $claim->materialisation_pipeline_fingerprint);
        $this->assertIsArray($claim->materialisation_pipeline_components);
        $this->assertNotNull($claim->claimed_at);
    }

    public function test_duplicate_delivery_cannot_take_over_a_live_lease(): void
    {
        $document = $this->document(DocumentStatus::Queued);
        $event = $this->eventFor($document);

        $this->signedRequest($event)
            ->assertOk()
            ->assertJsonPath('data.outcome', 'claimed');

        $this->signedRequest($event)
            ->assertStatus(423)
            ->assertJsonPath('data.outcome', 'owned_by_another_worker')
            ->assertJsonPath('data.document_status', 'processing');

        $this->assertDatabaseCount('ingestion_event_claims', 1);
        $this->assertSame(
            DocumentStatus::Processing,
            $document->refresh()->status,
        );
    }

    public function test_primary_and_secondary_keys_can_overlap_during_rotation(): void
    {
        $primaryDocument = $this->document(DocumentStatus::Queued);
        $secondaryDocument = $this->document(DocumentStatus::Queued);

        $this->signedRequest(
            $this->eventFor($primaryDocument),
            keyId: self::PRIMARY_KEY_ID,
            encodedSecret: self::PRIMARY_SECRET,
        )->assertOk();

        $this->signedRequest(
            $this->eventFor($secondaryDocument),
            keyId: self::SECONDARY_KEY_ID,
            encodedSecret: self::SECONDARY_SECRET,
        )->assertOk();

        $this->assertDatabaseCount('ingestion_event_claims', 2);
    }

    public function test_version_one_signatures_are_rejected_after_the_bounded_cutover(): void
    {
        CarbonImmutable::setTestNow(
            CarbonImmutable::createFromTimestampUTC(1_785_326_400),
        );
        config([
            'ingestion.worker_auth.keys' => [
                'local-v1' => self::PRIMARY_SECRET,
            ],
        ]);
        $eventId = '5a1e9c3e-3b3a-4e2a-9c7d-1f6b6f0a2b41';
        $path = $this->path($eventId);

        $this->call(
            'POST',
            $path,
            server: $this->serverHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                IngestionWorkerRequestAuthenticator::KEY_ID_HEADER => 'local-v1',
                IngestionWorkerRequestAuthenticator::TIMESTAMP_HEADER => '1785326400',
                IngestionWorkerRequestAuthenticator::EVENT_ID_HEADER => $eventId,
                IngestionWorkerRequestAuthenticator::SIGNATURE_HEADER => (
                    'v1=4b54632a0c852c07c654ef3f4f658fba'.
                    '1759fefe0fa8d5cc3c531b1f83b43da9'
                ),
            ]),
            content: '{}',
        )
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'worker_authentication_failed');
    }

    public function test_missing_invalid_and_unknown_credentials_fail_generically(): void
    {
        $event = $this->eventFor($this->document(DocumentStatus::Queued));
        $path = $this->path($event['event_id']);

        $this->postJson($path, $event)
            ->assertUnauthorized()
            ->assertJsonPath(
                'error.code',
                'worker_authentication_failed',
            );

        $this->signedRequest(
            $event,
            signatureOverride: 'v2='.str_repeat('0', 64),
        )->assertUnauthorized();

        $this->signedRequest(
            $event,
            keyId: 'unknown-key',
        )->assertUnauthorized();

        $this->assertSame(
            DocumentStatus::Queued,
            Document::query()->sole()->status,
        );
        $this->assertDatabaseCount('ingestion_event_claims', 0);
    }

    public function test_past_and_future_timestamps_outside_the_window_are_rejected(): void
    {
        $event = $this->eventFor($this->document(DocumentStatus::Queued));
        $now = CarbonImmutable::now()->timestamp;

        $this->signedRequest($event, timestamp: $now - 301)
            ->assertUnauthorized();
        $this->signedRequest($event, timestamp: $now + 301)
            ->assertUnauthorized();

        $this->assertDatabaseCount('ingestion_event_claims', 0);
    }

    public function test_method_path_body_and_event_identifier_are_bound_to_the_signature(): void
    {
        $event = $this->eventFor($this->document(DocumentStatus::Queued));
        $body = $this->body($event);
        $path = $this->path($event['event_id']);

        $this->signedRequest($event, signedMethod: 'PUT')
            ->assertUnauthorized();
        $this->signedRequest(
            $event,
            signedPath: '/api/internal/ingestion/events/'.
                $event['event_id'].
                '/different',
        )->assertUnauthorized();
        $this->signedRequest(
            $event,
            signedBody: $body.' ',
        )->assertUnauthorized();
        $this->signedRequest(
            $event,
            headerEventId: (string) Str::uuid(),
        )->assertUnauthorized();

        $this->call(
            'POST',
            $path.'?unexpected=1',
            server: $this->serverHeaders(
                $this->headers($event, $body, $path),
            ),
            content: $body,
        )
            ->assertUnauthorized();

        $this->assertDatabaseCount('ingestion_event_claims', 0);
    }

    public function test_body_event_identifier_must_match_the_signed_path_identifier(): void
    {
        $document = $this->document(DocumentStatus::Queued);
        $event = $this->eventFor($document);
        $pathEventId = (string) Str::uuid();
        $path = $this->path($pathEventId);
        $body = $this->body($event);

        $this->call(
            'POST',
            $path,
            server: $this->serverHeaders($this->headers(
                $event,
                $body,
                $path,
                headerEventId: $pathEventId,
            )),
            content: $body,
        )
            ->assertConflict()
            ->assertJsonPath('error.code', 'event_identity_mismatch');

        $this->assertSame(
            DocumentStatus::Queued,
            $document->refresh()->status,
        );
    }

    public function test_contract_invalid_event_is_poison_and_does_not_claim(): void
    {
        $document = $this->document(DocumentStatus::Queued);
        $event = $this->eventFor($document);
        $event['event_version'] = 2;

        $this->signedRequest($event)
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'invalid_event_contract');

        $this->assertSame(
            DocumentStatus::Queued,
            $document->refresh()->status,
        );
        $this->assertDatabaseCount('ingestion_event_claims', 0);
    }

    public function test_signed_malformed_json_is_poison_and_does_not_claim(): void
    {
        $event = $this->eventFor($this->document(DocumentStatus::Queued));
        $malformedBody = '{';

        $this->signedRequest(
            $event,
            signedBody: $malformedBody,
            sentBody: $malformedBody,
        )
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'invalid_event_contract');

        $this->assertDatabaseCount('ingestion_event_claims', 0);
    }

    public function test_reusing_an_event_identifier_for_changed_payload_is_rejected(): void
    {
        $document = $this->document(DocumentStatus::Queued);
        $event = $this->eventFor($document);

        $this->signedRequest($event)->assertOk();

        $event['correlation_id'] = (string) Str::uuid();

        $this->signedRequest($event)
            ->assertConflict()
            ->assertJsonPath('error.code', 'event_identity_reused');

        $this->assertDatabaseCount('ingestion_event_claims', 1);
    }

    #[DataProvider('staleStatusProvider')]
    public function test_later_lifecycle_states_are_safe_stale_events(
        DocumentStatus $status,
    ): void {
        $document = $this->document($status);

        $this->signedRequest($this->eventFor($document))
            ->assertConflict()
            ->assertJsonPath('data.outcome', 'stale_event')
            ->assertJsonPath('data.document_status', $status->value);

        $this->assertSame($status, $document->refresh()->status);
        $this->assertDatabaseCount('ingestion_event_claims', 0);
    }

    #[DataProvider('ineligibleStatusProvider')]
    public function test_earlier_lifecycle_states_are_poison_ineligible_events(
        DocumentStatus $status,
    ): void {
        $document = $this->document($status);

        $this->signedRequest($this->eventFor($document))
            ->assertConflict()
            ->assertJsonPath('data.outcome', 'ineligible_state')
            ->assertJsonPath('data.document_status', $status->value);

        $this->assertSame($status, $document->refresh()->status);
        $this->assertDatabaseCount('ingestion_event_claims', 0);
    }

    public function test_workspace_identity_is_enforced_in_the_document_lookup(): void
    {
        $document = $this->document(DocumentStatus::Queued);
        $event = $this->eventFor($document);
        $event['workspace_id'] = Workspace::factory()->create()->public_id;

        $this->signedRequest($event)
            ->assertNotFound()
            ->assertJsonPath('error.code', 'unknown_document');

        $this->assertSame(
            DocumentStatus::Queued,
            $document->refresh()->status,
        );
        $this->assertDatabaseCount('ingestion_event_claims', 0);
    }

    public function test_claim_insert_failure_rolls_back_the_lifecycle_transition(): void
    {
        $document = $this->document(DocumentStatus::Queued);
        $event = $this->eventFor($document);
        IngestionEventClaim::creating(static function (): never {
            throw new RuntimeException('Synthetic claim failure.');
        });

        try {
            app(ClaimDocumentIngestion::class)->handle(
                $event,
                hash('sha256', $this->body($event)),
            );
            $this->fail('The synthetic claim failure was not raised.');
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'Synthetic claim failure.',
                $exception->getMessage(),
            );
        } finally {
            IngestionEventClaim::flushEventListeners();
        }

        $this->assertSame(
            DocumentStatus::Queued,
            $document->refresh()->status,
        );
        $this->assertDatabaseCount('ingestion_event_claims', 0);
    }

    public function test_lifecycle_write_failure_rolls_back_the_claim_insert(): void
    {
        $document = $this->document(DocumentStatus::Queued);
        $event = $this->eventFor($document);
        $failUpdate = true;
        Document::updating(static function () use (&$failUpdate): void {
            if ($failUpdate) {
                throw new RuntimeException('Synthetic lifecycle failure.');
            }
        });

        try {
            app(ClaimDocumentIngestion::class)->handle(
                $event,
                hash('sha256', $this->body($event)),
            );
            $this->fail('The synthetic lifecycle failure was not raised.');
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'Synthetic lifecycle failure.',
                $exception->getMessage(),
            );
        } finally {
            $failUpdate = false;
        }

        $this->assertSame(
            DocumentStatus::Queued,
            $document->refresh()->status,
        );
        $this->assertDatabaseCount('ingestion_event_claims', 0);
    }

    public function test_claim_identifier_is_unique_and_claim_is_immutable(): void
    {
        $claim = IngestionEventClaim::factory()->create();

        try {
            IngestionEventClaim::factory()->create([
                'event_id' => $claim->event_id,
            ]);
            $this->fail('The duplicate event ID was accepted.');
        } catch (QueryException) {
            $this->assertDatabaseCount('ingestion_event_claims', 1);
        }

        $claim->correlation_id = (string) Str::uuid();

        $this->expectException(LogicException::class);

        $claim->save();
    }

    public function test_clean_migration_creates_claim_columns_and_indexes(): void
    {
        $this->assertTrue(Schema::hasColumns('ingestion_event_claims', [
            'id',
            'event_id',
            'workspace_public_id',
            'document_public_id',
            'correlation_id',
            'payload_sha256',
            'attempt_origin',
            'materialisation_pipeline_fingerprint',
            'materialisation_pipeline_components',
            'claimed_at',
            'created_at',
            'updated_at',
        ]));

        $indexes = collect(Schema::getIndexes('ingestion_event_claims'))
            ->pluck('name');

        $this->assertContains(
            'ingestion_event_claims_event_id_unique',
            $indexes,
        );
        $this->assertContains(
            'ingestion_claims_tenant_document',
            $indexes,
        );
    }

    /**
     * @return array<string, array{DocumentStatus}>
     */
    public static function staleStatusProvider(): array
    {
        return [
            'processing' => [DocumentStatus::Processing],
            'indexed' => [DocumentStatus::Indexed],
            'failed' => [DocumentStatus::Failed],
            'deleting' => [DocumentStatus::Deleting],
            'deleted' => [DocumentStatus::Deleted],
        ];
    }

    /**
     * @return array<string, array{DocumentStatus}>
     */
    public static function ineligibleStatusProvider(): array
    {
        return [
            'uploading' => [DocumentStatus::Uploading],
            'uploaded' => [DocumentStatus::Uploaded],
        ];
    }

    private function document(DocumentStatus $status): Document
    {
        $factory = Document::factory()
            ->for(Workspace::factory(), 'workspace')
            ->for(User::factory(), 'createdBy');

        return match ($status) {
            DocumentStatus::Failed => $factory->failed()->create(),
            default => $factory->create(['status' => $status]),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function eventFor(Document $document): array
    {
        return app(DocumentIngestionRequestedPayload::class)->build(
            $document->load('workspace'),
            (string) Str::uuid(),
            (string) Str::uuid(),
            CarbonImmutable::now(),
        );
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function signedRequest(
        array $event,
        string $keyId = self::PRIMARY_KEY_ID,
        string $encodedSecret = self::PRIMARY_SECRET,
        ?int $timestamp = null,
        ?string $signedMethod = null,
        ?string $signedPath = null,
        ?string $signedBody = null,
        ?string $sentBody = null,
        ?string $headerEventId = null,
        ?string $signatureOverride = null,
    ): TestResponse {
        $body = $this->body($event);
        $path = $this->path($event['event_id']);
        $headers = $this->headers(
            $event,
            $signedBody ?? $body,
            $signedPath ?? $path,
            $keyId,
            $encodedSecret,
            $timestamp,
            $signedMethod,
            $headerEventId,
            $signatureOverride,
        );

        return $this->call(
            'POST',
            $path,
            server: $this->serverHeaders($headers),
            content: $sentBody ?? $body,
        );
    }

    /**
     * @param  array<string, mixed>  $event
     * @return array<string, string>
     */
    private function headers(
        array $event,
        string $signedBody,
        string $signedPath,
        string $keyId = self::PRIMARY_KEY_ID,
        string $encodedSecret = self::PRIMARY_SECRET,
        ?int $timestamp = null,
        ?string $signedMethod = null,
        ?string $headerEventId = null,
        ?string $signatureOverride = null,
    ): array {
        $timestampText = (string) (
            $timestamp ?? CarbonImmutable::now()->timestamp
        );
        $eventId = $headerEventId ?? $event['event_id'];
        $canonical = implode("\n", [
            $timestampText,
            $signedMethod ?? 'POST',
            $signedPath,
            hash('sha256', $signedBody),
            $eventId,
            'ingestion.claim',
        ]);
        $secret = base64_decode($encodedSecret, true);
        $this->assertIsString($secret);
        $signature = $signatureOverride ?? 'v2='.hash_hmac(
            'sha256',
            $canonical,
            $secret,
        );

        return [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            IngestionWorkerRequestAuthenticator::KEY_ID_HEADER => $keyId,
            IngestionWorkerRequestAuthenticator::TIMESTAMP_HEADER => $timestampText,
            IngestionWorkerRequestAuthenticator::EVENT_ID_HEADER => $eventId,
            IngestionWorkerRequestAuthenticator::SIGNATURE_HEADER => $signature,
            IngestionWorkerRequestAuthenticator::PURPOSE_HEADER => 'ingestion.claim',
        ];
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function body(array $event): string
    {
        return json_encode(
            $event,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
        );
    }

    private function path(string $eventId): string
    {
        return sprintf(
            '/api/internal/ingestion/events/%s/claim',
            $eventId,
        );
    }

    /**
     * @param  array<string, string>  $headers
     * @return array<string, string>
     */
    private function serverHeaders(array $headers): array
    {
        return $this->transformHeadersToServerVars($headers);
    }
}
