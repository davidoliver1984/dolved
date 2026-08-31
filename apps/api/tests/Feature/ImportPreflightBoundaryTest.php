<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Imports\ReconcileExpiredImportPreflights;
use App\Actions\Imports\RecordImportPreflightCallback;
use App\Actions\Imports\StartImportPreflight;
use App\Enums\ImportBatchStatus;
use App\Enums\ImportMatchStatus;
use App\Enums\ImportPreflightAttemptStatus;
use App\Enums\ImportPreflightStatus;
use App\Exceptions\ImportPreflightException;
use App\Models\ImportBatch;
use App\Models\ImportItem;
use App\Models\ImportPreflightAttempt;
use App\Models\OutboxEvent;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Documents\ImportStagingStorage;
use App\Services\Imports\ImportPreflightContractValidator;
use App\Services\Ingestion\IngestionWorkerRequestAuthenticator;
use App\Support\Documents\ImportStagingObjectKey;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

final class ImportPreflightBoundaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispatch_is_atomic_exact_object_scoped_and_contains_both_lease_authorities(): void
    {
        [$workspace, , $item] = $this->domain();
        $attempt = $this->starter()->handle($item, 'd93554a7-dff6-4a0a-9f6e-c9df0ed1106b');
        $event = OutboxEvent::query()->where('event_id', $attempt->event_id)->firstOrFail();

        $this->assertSame(ImportPreflightAttemptStatus::Open, $attempt->status);
        $this->assertSame(1, $attempt->lease_generation);
        $this->assertSame($item->public_id, $event->import_item_public_id);
        $this->assertNull($event->document_public_id);
        $this->assertSame('import.preflight.requested', $event->payload['event_type']);
        $this->assertSame($item->staged_object_key, $event->payload['staged_object']['key']);
        $this->assertSame($workspace->public_id, $event->payload['workspace_id']);
        $this->assertSame(1, $event->payload['lease_generation']);
        $this->assertTrue(Str::isUuid($event->payload['lease_token']));
        $this->assertSame(hash('sha256', $event->payload['lease_token']), $attempt->lease_token_hash);
        $this->assertStringNotContainsString('policy.pdf', json_encode($event->payload, JSON_THROW_ON_ERROR));
    }

    public function test_readable_callback_is_incorporated_once_and_conflicting_replay_fails_closed(): void
    {
        [, , $item] = $this->domain();
        $attempt = $this->starter()->handle($item);
        $payload = $this->completePayload($attempt);
        $record = app(RecordImportPreflightCallback::class);

        $this->assertSame('recorded', $record->complete($attempt->event_id, $payload));
        $this->assertSame('duplicate', $record->complete($attempt->event_id, array_reverse($payload, true)));
        $this->assertSame(ImportPreflightStatus::Verified, $item->fresh()->preflight_status);
        $this->assertSame(str_repeat('a', 64), $item->fresh()->source_checksum_sha256);

        $payload = $this->basePayload($attempt) + [
            'result' => 'mime_mismatch',
            'diagnostic_code' => 'declared_type_mismatch',
        ];
        $this->expectException(ImportPreflightException::class);
        $record->complete($attempt->event_id, $payload);
    }

    public function test_stale_generation_and_mismatched_token_mutate_nothing(): void
    {
        [, , $item] = $this->domain();
        $attempt = $this->starter()->handle($item);
        $payload = $this->completePayload($attempt);
        $payload['lease_generation'] = 2;

        try {
            app(RecordImportPreflightCallback::class)->complete($attempt->event_id, $payload);
            $this->fail('A stale generation was accepted.');
        } catch (ImportPreflightException $exception) {
            $this->assertSame('stale_or_mismatched_lease', $exception->reason);
        }
        $this->assertSame(ImportPreflightStatus::Pending, $item->fresh()->preflight_status);
        $this->assertSame(ImportPreflightAttemptStatus::Open, $attempt->fresh()->status);
    }

    public function test_expired_generation_is_terminalised_before_successor_dispatch(): void
    {
        [, , $item] = $this->domain();
        $starter = $this->starter();
        $attempt = $starter->handle($item);
        ImportPreflightAttempt::query()->whereKey($attempt->id)->update(['lease_expires_at' => now()->subSecond()]);

        $count = (new ReconcileExpiredImportPreflights($starter))->handle();

        $this->assertSame(1, $count);
        $this->assertSame(ImportPreflightAttemptStatus::Expired, $attempt->fresh()->status);
        $successor = ImportPreflightAttempt::query()->where('import_item_id', $item->id)->where('lease_generation', 2)->firstOrFail();
        $this->assertSame(ImportPreflightAttemptStatus::Open, $successor->status);
        $this->assertNotSame($attempt->event_id, $successor->event_id);
        $this->assertSame(2, OutboxEvent::query()->where('import_item_public_id', $item->public_id)->count());
    }

    public function test_expired_generation_remains_recoverable_when_successor_dispatch_is_temporarily_unavailable(): void
    {
        [, , $item] = $this->domain();
        $attempt = $this->starter()->handle($item);
        ImportPreflightAttempt::query()->whereKey($attempt->id)->update(['lease_expires_at' => now()->subSecond()]);

        $unavailable = Mockery::mock(FilesystemAdapter::class);
        $unavailable->shouldReceive('size')->andThrow(new \RuntimeException('storage unavailable'));
        $filesystems = Mockery::mock(FilesystemFactory::class);
        $filesystems->shouldReceive('disk')->with((string) config('imports.storage_disk'))->andReturn($unavailable);
        $starter = new StartImportPreflight(
            new ImportStagingStorage($filesystems, new ImportStagingObjectKey),
            app(ImportPreflightContractValidator::class),
        );

        $this->assertSame(0, (new ReconcileExpiredImportPreflights($starter))->handle());
        $this->assertSame(ImportPreflightAttemptStatus::Expired, $attempt->fresh()->status);
        $this->assertDatabaseMissing('import_preflight_attempts', [
            'import_item_id' => $item->id,
            'lease_generation' => 2,
        ]);

        $this->assertSame(1, (new ReconcileExpiredImportPreflights($this->starter()))->handle());
        $this->assertDatabaseHas('import_preflight_attempts', [
            'import_item_id' => $item->id,
            'lease_generation' => 2,
            'status' => ImportPreflightAttemptStatus::Open->value,
        ]);
    }

    public function test_detected_rejection_maps_to_rejected_but_operational_failure_remains_pending(): void
    {
        [, , $rejected] = $this->domain();
        $rejectedAttempt = $this->starter()->handle($rejected);
        $rejection = $this->basePayload($rejectedAttempt) + [
            'result' => 'encrypted',
            'diagnostic_code' => 'office_encrypted',
        ];
        app(RecordImportPreflightCallback::class)->complete($rejectedAttempt->event_id, $rejection);
        $this->assertSame(ImportPreflightStatus::Rejected, $rejected->fresh()->preflight_status);

        [, , $pending] = $this->domain();
        $failedAttempt = $this->starter()->handle($pending);
        $failure = $this->basePayload($failedAttempt) + ['diagnostic_code' => 'source_unavailable'];
        app(RecordImportPreflightCallback::class)->fail($failedAttempt->event_id, $failure);
        $this->assertSame(ImportPreflightStatus::Pending, $pending->fresh()->preflight_status);
        $this->assertSame(ImportPreflightAttemptStatus::Failed, $failedAttempt->fresh()->status);
    }

    public function test_inbound_callback_requires_the_exact_purpose_scoped_hmac(): void
    {
        [, , $item] = $this->domain();
        $attempt = $this->starter()->handle($item);
        $payload = $this->completePayload($attempt);
        $body = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $path = "/api/internal/import-preflights/{$attempt->event_id}/complete";
        $timestamp = (string) now()->timestamp;
        $secret = str_repeat('s', 32);
        config()->set('ingestion.worker_auth.keys', ['preflight-v1' => base64_encode($secret)]);
        $canonical = implode("\n", [
            $timestamp,
            'POST',
            $path,
            hash('sha256', $body),
            $attempt->event_id,
            'import.preflight.complete',
        ]);
        $headers = $this->transformHeadersToServerVars([
            'Content-Type' => 'application/json',
            IngestionWorkerRequestAuthenticator::KEY_ID_HEADER => 'preflight-v1',
            IngestionWorkerRequestAuthenticator::TIMESTAMP_HEADER => $timestamp,
            IngestionWorkerRequestAuthenticator::EVENT_ID_HEADER => $attempt->event_id,
            IngestionWorkerRequestAuthenticator::PURPOSE_HEADER => 'import.preflight.complete',
            IngestionWorkerRequestAuthenticator::SIGNATURE_HEADER => 'v2='.hash_hmac('sha256', $canonical, $secret),
        ]);

        $this->call('POST', $path, [], [], [], $headers, $body)
            ->assertOk()
            ->assertJsonPath('data.outcome', 'recorded');

        $failedAttempt = $this->starter()->handle($this->domain()[2]);
        $this->postJson("/api/internal/import-preflights/{$failedAttempt->event_id}/fail", [])
            ->assertUnauthorized();
    }

    public function test_zero_byte_source_is_rejected_by_laravel_without_dispatch(): void
    {
        [, , $item] = $this->domain();
        try {
            $this->starter(0)->handle($item);
            $this->fail('A zero-byte source was dispatched to Python.');
        } catch (ImportPreflightException $exception) {
            $this->assertSame('empty_source', $exception->reason);
        }
        $this->assertSame(ImportPreflightStatus::Rejected, $item->fresh()->preflight_status);
        $this->assertSame('empty_source', $item->fresh()->preflight_rejection_reason->value);
        $this->assertDatabaseCount('import_preflight_attempts', 0);
        $this->assertDatabaseCount('outbox_events', 0);
    }

    /** @return array{Workspace, ImportBatch, ImportItem} */
    private function domain(): array
    {
        $workspace = Workspace::factory()->create();
        $actor = User::factory()->create();
        $batch = ImportBatch::query()->create([
            'public_id' => (string) Str::uuid(),
            'workspace_id' => $workspace->id,
            'initiated_by_user_id' => $actor->id,
            'status' => ImportBatchStatus::Open,
            'retention_expires_at' => now()->addDays(7),
        ]);
        $publicId = (string) Str::uuid();
        $item = ImportItem::query()->create([
            'public_id' => $publicId,
            'import_batch_id' => $batch->id,
            'workspace_id' => $workspace->id,
            'staged_object_key' => "imports/workspaces/{$workspace->public_id}/items/{$publicId}/source",
            'declared_media_type' => 'application/pdf',
            'preflight_status' => ImportPreflightStatus::Pending,
            'match_status' => ImportMatchStatus::Pending,
        ]);

        return [$workspace, $batch, $item];
    }

    private function starter(int $size = 100): StartImportPreflight
    {
        $disk = Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('size')->andReturn($size);
        $disk->shouldReceive('temporaryUrl')->andReturn('https://storage.example/preflight?signature=bounded');
        $filesystems = Mockery::mock(FilesystemFactory::class);
        $filesystems->shouldReceive('disk')->with((string) config('imports.storage_disk'))->andReturn($disk);

        return new StartImportPreflight(
            new ImportStagingStorage($filesystems, new ImportStagingObjectKey),
            app(ImportPreflightContractValidator::class),
        );
    }

    /** @return array<string, mixed> */
    private function completePayload(ImportPreflightAttempt $attempt): array
    {
        return $this->basePayload($attempt) + [
            'result' => 'readable',
            'diagnostic_code' => 'readable',
            'source_checksum_sha256' => str_repeat('a', 64),
            'media_type' => 'application/pdf',
            'size_bytes' => 100,
        ];
    }

    /** @return array<string, mixed> */
    private function basePayload(ImportPreflightAttempt $attempt): array
    {
        $event = OutboxEvent::query()->where('event_id', $attempt->event_id)->firstOrFail();

        return [
            'contract_version' => 'import-preflight-v1',
            'event_id' => $attempt->event_id,
            'workspace_id' => $event->payload['workspace_id'],
            'import_item_id' => $event->payload['import_item_id'],
            'staged_object_key' => $attempt->staged_object_key,
            'lease_token' => $event->payload['lease_token'],
            'lease_generation' => $attempt->lease_generation,
        ];
    }
}
