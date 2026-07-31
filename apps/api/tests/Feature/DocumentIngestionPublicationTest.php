<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Documents\RequestDocumentIngestion;
use App\Actions\Ingestion\PublishIngestionOutbox;
use App\Contracts\Ingestion\IngestionEventPublisher;
use App\Enums\DocumentStatus;
use App\Enums\WorkspaceRole;
use App\Models\Document;
use App\Models\OutboxEvent;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use LogicException;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class DocumentIngestionPublicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_uploaded_document_transitions_and_records_publication_intent_atomically(): void
    {
        [$user, $workspace, $document] = $this->uploadedDocument();
        $correlationId = (string) Str::uuid();
        Http::fake();

        $this->actingAs($user)
            ->withHeader('X-Correlation-ID', $correlationId)
            ->postJson($this->url($workspace, $document))
            ->assertAccepted()
            ->assertHeader('X-Correlation-ID', $correlationId)
            ->assertJsonPath('data.status', DocumentStatus::Queued->value)
            ->assertJsonMissingPath('data.storage_key');

        $this->assertSame(
            DocumentStatus::Queued,
            $document->refresh()->status,
        );

        $event = OutboxEvent::query()->sole();

        $this->assertSame($correlationId, $event->correlation_id);
        $this->assertSame($workspace->public_id, $event->workspace_public_id);
        $this->assertSame($document->public_id, $event->document_public_id);
        $this->assertSame($event->event_id, $event->payload['event_id']);
        $this->assertSame(
            'document.ingestion.requested',
            $event->payload['event_type'],
        );
        $this->assertSame(1, $event->payload['event_version']);
        $this->assertSame($workspace->public_id, $event->payload['workspace_id']);
        $this->assertSame($document->public_id, $event->payload['document_id']);
        $this->assertSame($correlationId, $event->payload['correlation_id']);
        $this->assertNull($event->published_at);

        $this->actingAs($user)
            ->withHeader('X-Correlation-ID', (string) Str::uuid())
            ->postJson($this->url($workspace, $document))
            ->assertAccepted()
            ->assertJsonPath('data.status', DocumentStatus::Queued->value);

        $this->assertDatabaseCount('outbox_events', 1);
        Http::assertNothingSent();
    }

    public function test_missing_or_invalid_correlation_header_is_replaced_server_side(): void
    {
        [$user, $workspace, $document] = $this->uploadedDocument();

        $response = $this->actingAs($user)
            ->postJson($this->url($workspace, $document))
            ->assertAccepted();

        $generated = $response->headers->get('X-Correlation-ID');

        $this->assertIsString($generated);
        $this->assertTrue(Str::isUuid($generated));
        $this->assertSame(
            $generated,
            OutboxEvent::query()->sole()->correlation_id,
        );

        [$user, $workspace, $document] = $this->uploadedDocument();

        $response = $this->actingAs($user)
            ->withHeader('X-Correlation-ID', 'not-a-uuid')
            ->postJson($this->url($workspace, $document))
            ->assertAccepted();

        $replacement = $response->headers->get('X-Correlation-ID');

        $this->assertIsString($replacement);
        $this->assertTrue(Str::isUuid($replacement));
        $this->assertNotSame('not-a-uuid', $replacement);
    }

    public function test_all_workspace_roles_can_request_ingestion(): void
    {
        foreach (WorkspaceRole::cases() as $role) {
            [$user, $workspace] = $this->memberWorkspace($role);
            $document = Document::factory()
                ->for($workspace)
                ->for($user, 'createdBy')
                ->uploaded()
                ->create();

            $this->actingAs($user)
                ->postJson($this->url($workspace, $document))
                ->assertAccepted();
        }

        $this->assertDatabaseCount('outbox_events', 3);
    }

    #[DataProvider('idempotentStatusProvider')]
    public function test_queued_and_processing_requests_are_idempotent(
        DocumentStatus $status,
    ): void {
        [$user, $workspace] = $this->memberWorkspace();
        $document = Document::factory()
            ->for($workspace)
            ->for($user, 'createdBy')
            ->create(['status' => $status]);

        $this->actingAs($user)
            ->postJson($this->url($workspace, $document))
            ->assertAccepted()
            ->assertJsonPath('data.status', $status->value);

        $this->actingAs($user)
            ->postJson($this->url($workspace, $document))
            ->assertAccepted()
            ->assertJsonPath('data.status', $status->value);

        $this->assertDatabaseCount('outbox_events', 0);
    }

    #[DataProvider('conflictingStatusProvider')]
    public function test_ineligible_lifecycle_states_return_conflict(
        DocumentStatus $status,
    ): void {
        [$user, $workspace] = $this->memberWorkspace();
        $factory = Document::factory()
            ->for($workspace)
            ->for($user, 'createdBy');
        $document = match ($status) {
            DocumentStatus::Failed => $factory->failed()->create(),
            default => $factory->create(['status' => $status]),
        };

        $this->actingAs($user)
            ->postJson($this->url($workspace, $document))
            ->assertConflict();

        $this->assertSame($status, $document->refresh()->status);
        $this->assertDatabaseCount('outbox_events', 0);
    }

    public function test_endpoint_requires_authentication_verification_and_workspace_access(): void
    {
        [$user, $workspace, $document] = $this->uploadedDocument();
        $unverified = User::factory()->unverified()->create();
        WorkspaceMembership::factory()
            ->for($workspace)
            ->for($unverified)
            ->member()
            ->create();

        $this->postJson($this->url($workspace, $document))
            ->assertUnauthorized();

        $this->actingAs($unverified)
            ->postJson($this->url($workspace, $document))
            ->assertForbidden();

        $outsider = User::factory()->create();

        $this->actingAs($outsider)
            ->postJson($this->url($workspace, $document))
            ->assertNotFound();

        $this->assertDatabaseCount('outbox_events', 0);
        $this->assertSame(
            DocumentStatus::Uploaded,
            $document->refresh()->status,
        );
    }

    public function test_cross_workspace_document_lookup_fails_closed(): void
    {
        [$user, $workspace] = $this->memberWorkspace();
        [, , $otherDocument] = $this->uploadedDocument();

        $this->actingAs($user)
            ->postJson($this->url($workspace, $otherDocument))
            ->assertNotFound();

        $this->assertDatabaseCount('outbox_events', 0);
    }

    public function test_outbox_failure_rolls_back_the_lifecycle_transition(): void
    {
        [, , $document] = $this->uploadedDocument();
        OutboxEvent::creating(static function (): never {
            throw new RuntimeException('Synthetic outbox failure.');
        });

        try {
            app(RequestDocumentIngestion::class)->handle(
                $document,
                (string) Str::uuid(),
            );
            $this->fail('The synthetic outbox failure was not raised.');
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'Synthetic outbox failure.',
                $exception->getMessage(),
            );
        } finally {
            OutboxEvent::flushEventListeners();
        }

        $this->assertSame(
            DocumentStatus::Uploaded,
            $document->refresh()->status,
        );
        $this->assertDatabaseCount('outbox_events', 0);
    }

    public function test_publisher_validates_publishes_and_marks_the_same_event(): void
    {
        $event = OutboxEvent::factory()->create();
        $transport = Mockery::mock(IngestionEventPublisher::class);
        $transport->shouldReceive('publish')
            ->once()
            ->withArgs(fn (array $payload): bool => $payload['event_id'] === $event->event_id)
            ->andReturn('transport-message-id');
        $this->app->instance(IngestionEventPublisher::class, $transport);

        $summary = app(PublishIngestionOutbox::class)->handle();

        $this->assertSame([
            'claimed' => 1,
            'published' => 1,
            'retryable' => 0,
            'failed' => 0,
        ], $summary);

        $event->refresh();
        $this->assertNotNull($event->published_at);
        $this->assertSame(1, $event->attempt_count);
        $this->assertNull($event->claim_token);
        $this->assertNull($event->last_error);

        $this->assertSame(
            [
                'claimed' => 0,
                'published' => 0,
                'retryable' => 0,
                'failed' => 0,
            ],
            app(PublishIngestionOutbox::class)->handle(),
        );
        $this->assertSame($event->event_id, $event->refresh()->event_id);
    }

    public function test_transient_failure_remains_retryable_and_later_publishes(): void
    {
        CarbonImmutable::setTestNow('2026-07-28 12:00:00');
        config([
            'ingestion.publisher.retry_base_seconds' => 5,
            'ingestion.publisher.retry_max_seconds' => 300,
        ]);
        $event = OutboxEvent::factory()->create();
        $failedTransport = Mockery::mock(IngestionEventPublisher::class);
        $failedTransport->shouldReceive('publish')
            ->once()
            ->andThrow(new RuntimeException('token=secret-value unavailable'));
        $this->app->instance(
            IngestionEventPublisher::class,
            $failedTransport,
        );

        $summary = app(PublishIngestionOutbox::class)->handle();

        $this->assertSame(1, $summary['retryable']);
        $event->refresh();
        $this->assertNull($event->published_at);
        $this->assertNull($event->failed_at);
        $this->assertSame(1, $event->attempt_count);
        $this->assertSame(
            '2026-07-28 12:00:05',
            $event->next_attempt_at?->format('Y-m-d H:i:s'),
        );
        $this->assertStringNotContainsString(
            'secret-value',
            (string) $event->last_error,
        );

        CarbonImmutable::setTestNow('2026-07-28 12:00:05');
        $successfulTransport = Mockery::mock(IngestionEventPublisher::class);
        $successfulTransport->shouldReceive('publish')
            ->once()
            ->withArgs(fn (array $payload): bool => $payload['event_id'] === $event->event_id)
            ->andReturn('transport-message-id');
        $this->app->instance(
            IngestionEventPublisher::class,
            $successfulTransport,
        );

        $summary = app(PublishIngestionOutbox::class)->handle();

        $this->assertSame(1, $summary['published']);
        $this->assertNotNull($event->refresh()->published_at);
        $this->assertSame(2, $event->attempt_count);
        $this->assertSame(
            $event->event_id,
            $event->payload['event_id'],
        );
    }

    public function test_invalid_payload_is_set_aside_without_transport_publication(): void
    {
        $payload = OutboxEvent::factory()->make()->payload;
        $event = OutboxEvent::factory()->create([
            'payload' => [
                ...$payload,
                'event_version' => 2,
            ],
        ]);
        $transport = Mockery::mock(IngestionEventPublisher::class);
        $transport->shouldNotReceive('publish');
        $this->app->instance(IngestionEventPublisher::class, $transport);

        $summary = app(PublishIngestionOutbox::class)->handle();

        $this->assertSame(1, $summary['failed']);
        $event->refresh();
        $this->assertNotNull($event->failed_at);
        $this->assertNull($event->published_at);
        $this->assertNull($event->next_attempt_at);
        $this->assertSame(1, $event->attempt_count);
    }

    public function test_active_claim_is_not_stolen_but_expired_lease_is_reclaimed(): void
    {
        CarbonImmutable::setTestNow('2026-07-28 12:00:00');
        config(['ingestion.publisher.claim_lease_seconds' => 60]);
        $event = OutboxEvent::factory()->create([
            'claimed_at' => CarbonImmutable::now(),
            'claim_token' => (string) Str::uuid(),
        ]);
        $transport = Mockery::mock(IngestionEventPublisher::class);
        $transport->shouldReceive('publish')->once()->andReturn('message-id');
        $this->app->instance(IngestionEventPublisher::class, $transport);

        $summary = app(PublishIngestionOutbox::class)->handle();

        $this->assertSame(0, $summary['claimed']);

        $event->forceFill([
            'claimed_at' => CarbonImmutable::now()->subSeconds(61),
        ])->save();

        $summary = app(PublishIngestionOutbox::class)->handle();

        $this->assertSame(1, $summary['published']);
    }

    public function test_one_shot_command_reports_an_empty_batch_successfully(): void
    {
        $this->mock(IngestionEventPublisher::class)
            ->shouldNotReceive('publish');

        $exitCode = Artisan::call('ingestion:publish', ['--once' => true]);

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString(
            'Claimed 0; published 0; retryable 0; failed 0.',
            Artisan::output(),
        );
    }

    public function test_clean_migration_creates_outbox_columns_and_indexes(): void
    {
        $this->assertTrue(Schema::hasColumns('outbox_events', [
            'id',
            'event_id',
            'event_type',
            'event_version',
            'workspace_public_id',
            'document_public_id',
            'correlation_id',
            'traceparent',
            'tracestate',
            'payload',
            'occurred_at',
            'published_at',
            'failed_at',
            'claimed_at',
            'claim_token',
            'attempt_count',
            'next_attempt_at',
            'last_error',
            'created_at',
            'updated_at',
        ]));

        $indexes = collect(Schema::getIndexes('outbox_events'))
            ->pluck('name');

        $this->assertContains(
            'outbox_events_event_id_unique',
            $indexes,
        );
        $this->assertContains(
            'outbox_events_publication_lookup',
            $indexes,
        );
        $this->assertContains(
            'outbox_events_claim_lookup',
            $indexes,
        );
    }

    public function test_event_identifier_is_unique_at_database_level(): void
    {
        $event = OutboxEvent::factory()->create();

        $this->expectException(QueryException::class);

        OutboxEvent::factory()->create([
            'event_id' => $event->event_id,
        ]);
    }

    public function test_event_identity_and_payload_are_immutable(): void
    {
        $event = OutboxEvent::factory()->create();
        $event->event_id = (string) Str::uuid();

        $this->expectException(LogicException::class);

        $event->save();
    }

    public function test_persisted_trace_context_is_immutable(): void
    {
        $event = OutboxEvent::factory()->create([
            'traceparent' => '00-11111111111111111111111111111111-2222222222222222-01',
        ]);
        $event->traceparent = '00-33333333333333333333333333333333-4444444444444444-01';

        $this->expectException(LogicException::class);

        $event->save();
    }

    /**
     * @return array<string, array{DocumentStatus}>
     */
    public static function idempotentStatusProvider(): array
    {
        return [
            'queued' => [DocumentStatus::Queued],
            'processing' => [DocumentStatus::Processing],
        ];
    }

    /**
     * @return array<string, array{DocumentStatus}>
     */
    public static function conflictingStatusProvider(): array
    {
        return [
            'uploading' => [DocumentStatus::Uploading],
            'indexed' => [DocumentStatus::Indexed],
            'failed' => [DocumentStatus::Failed],
            'deleting' => [DocumentStatus::Deleting],
            'deleted' => [DocumentStatus::Deleted],
        ];
    }

    /**
     * @return array{User, Workspace, Document}
     */
    private function uploadedDocument(): array
    {
        [$user, $workspace] = $this->memberWorkspace();
        $document = Document::factory()
            ->for($workspace)
            ->for($user, 'createdBy')
            ->uploaded()
            ->create();

        return [$user, $workspace, $document];
    }

    /**
     * @return array{User, Workspace}
     */
    private function memberWorkspace(
        WorkspaceRole $role = WorkspaceRole::Member,
    ): array {
        $user = User::factory()->create();
        $workspace = Workspace::factory()
            ->for($user, 'creator')
            ->create();
        $membership = WorkspaceMembership::factory()
            ->for($workspace)
            ->for($user);

        (match ($role) {
            WorkspaceRole::Owner => $membership->owner(),
            WorkspaceRole::Admin => $membership->admin(),
            WorkspaceRole::Member => $membership->member(),
        })->create();

        return [$user, $workspace];
    }

    private function url(
        Workspace $workspace,
        Document $document,
    ): string {
        return sprintf(
            '/api/workspaces/%s/documents/%s/ingestion-requests',
            $workspace->public_id,
            $document->public_id,
        );
    }
}
