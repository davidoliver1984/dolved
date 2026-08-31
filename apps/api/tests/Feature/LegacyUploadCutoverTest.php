<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Documents\CompleteLegacyDocumentUpload;
use App\Actions\Documents\InitializeDocumentUpload;
use App\Actions\Documents\RequestDocumentIngestion;
use App\Actions\Documents\RequestLegacyDocumentIngestion;
use App\Actions\Imports\CloseLegacyUploadInitializationGate;
use App\Actions\Imports\ExpireLegacyDrainUpload;
use App\Actions\Imports\InventoryLegacyUploads;
use App\Actions\Imports\ReconcileLegacyUploadDrain;
use App\Enums\DocumentStatus;
use App\Exceptions\LegacyUploadCutoverException;
use App\Models\Document;
use App\Models\LegacyUploadInitializationGate;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Documents\DocumentObjectStorage;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class LegacyUploadCutoverTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_transition_window_initialization_is_marked_and_gate_closure_refuses_new_uploads(): void
    {
        [$user, $workspace] = $this->workspace();
        $storage = $this->mock(DocumentObjectStorage::class);
        $storage->shouldReceive('createUploadRequest')->once()->andReturn([
            'url' => 'https://storage.example.test/upload', 'method' => 'PUT',
            'headers' => ['Content-Type' => 'application/pdf'], 'expires_at' => now()->addMinute()->toIso8601String(),
        ]);
        $result = app(InitializeDocumentUpload::class)->handle(
            $workspace, $user, 'policy.pdf', 'application/pdf', 100, 'pdf',
        );
        $gate = LegacyUploadInitializationGate::query()->findOrFail(1);
        $this->assertTrue($result['document']->legacy_upload_initiated_before_cutover);
        $this->assertSame($gate->cutover_operation_id, $result['document']->legacy_upload_cutover_operation_id);
        $this->assertDatabaseHas('legacy_upload_cutover_audits', [
            'document_id' => $result['document']->id, 'actor_type' => 'human',
            'actor_user_id' => $user->id, 'reason' => 'transition_window_creation',
        ]);
        $this->assertTrue(app(CloseLegacyUploadInitializationGate::class)->handle(10));

        $this->expectException(LegacyUploadCutoverException::class);
        app(InitializeDocumentUpload::class)->handle(
            $workspace, $user, 'blocked.pdf', 'application/pdf', 100, 'pdf',
        );
    }

    public function test_inventory_is_bounded_idempotent_and_gate_close_has_reconstructable_audit(): void
    {
        [$user, $workspace] = $this->workspace();
        $first = Document::factory()->for($workspace)->for($user, 'createdBy')->uploading()->create();
        $second = Document::factory()->for($workspace)->for($user, 'createdBy')->uploaded()->create();
        $queued = Document::factory()->for($workspace)->for($user, 'createdBy')->queued()->create();

        $this->assertSame(1, app(InventoryLegacyUploads::class)->handle(1));
        $this->assertSame(1, app(InventoryLegacyUploads::class)->handle(1));
        $this->assertSame(0, app(InventoryLegacyUploads::class)->handle(1));
        $this->assertTrue(app(CloseLegacyUploadInitializationGate::class)->handle(1));
        $this->assertSame(0, app(InventoryLegacyUploads::class)->handle(1));
        $gate = LegacyUploadInitializationGate::query()->findOrFail(1);
        $this->assertTrue($gate->closed);
        $this->assertSame(2, $gate->total_marked_count);
        $this->assertDatabaseCount('legacy_upload_cutover_audits', 2);
        $this->assertDatabaseHas('legacy_upload_cutover_events', [
            'cutover_operation_id' => $gate->cutover_operation_id,
            'event_type' => 'gate_closed', 'total_marked_count' => 2,
        ]);
        $this->assertTrue($first->refresh()->legacy_upload_initiated_before_cutover);
        $this->assertTrue($second->refresh()->legacy_upload_initiated_before_cutover);
        $this->assertNull($queued->refresh()->legacy_upload_initiated_before_cutover);
    }

    public function test_final_remainder_ceiling_fails_closed_then_restarts_without_duplicate_audit(): void
    {
        [$user, $workspace] = $this->workspace();
        Document::factory()->count(2)->for($workspace)->for($user, 'createdBy')->uploading()->create();
        $this->assertFalse(app(CloseLegacyUploadInitializationGate::class)->handle(1));
        $this->assertFalse(LegacyUploadInitializationGate::query()->findOrFail(1)->closed);
        $this->assertDatabaseCount('legacy_upload_cutover_audits', 0);
        $this->assertSame(2, app(InventoryLegacyUploads::class)->handle(10));
        $this->assertTrue(app(CloseLegacyUploadInitializationGate::class)->handle(1));
        $this->assertTrue(app(CloseLegacyUploadInitializationGate::class)->handle(1));
        $this->assertDatabaseCount('legacy_upload_cutover_audits', 2);
        $this->assertDatabaseCount('legacy_upload_cutover_events', 1);
    }

    public function test_marked_completion_and_ingestion_continue_after_cutover_but_unmarked_rows_fail_closed(): void
    {
        [$user, $workspace] = $this->workspace();
        $uploading = Document::factory()->for($workspace)->for($user, 'createdBy')->uploading()->create(['size_bytes' => 100]);
        $uploaded = Document::factory()->for($workspace)->for($user, 'createdBy')->uploaded()->create();
        app(InventoryLegacyUploads::class)->handle(10);
        app(CloseLegacyUploadInitializationGate::class)->handle(10);
        $uploading->refresh();
        $uploaded->refresh();
        $storage = $this->mock(DocumentObjectStorage::class);
        $storage->shouldReceive('streamedIdentity')->once()->andReturn(['size_bytes' => 100, 'sha256' => str_repeat('a', 64)]);
        $completed = app(CompleteLegacyDocumentUpload::class)->handle($uploading);
        $this->assertSame(DocumentStatus::Uploaded, $completed->status);
        $this->assertSame(DocumentStatus::Uploaded, app(CompleteLegacyDocumentUpload::class)->handle($completed)->status);
        $this->assertSame(
            DocumentStatus::Queued,
            app(RequestLegacyDocumentIngestion::class)->handle($uploaded, (string) Str::uuid())->status,
        );

        $unmarked = Document::factory()->for($workspace)->for($user, 'createdBy')->uploading()->create();
        $this->expectException(LegacyUploadCutoverException::class);
        app(CompleteLegacyDocumentUpload::class)->handle($unmarked);
    }

    public function test_expiry_is_narrow_and_drain_closes_only_after_all_browser_upload_states_leave(): void
    {
        CarbonImmutable::setTestNow('2026-08-31 09:00:00');
        config()->set('imports.legacy_cutover.drain_window_hours', 2);
        [$user, $workspace] = $this->workspace();
        $document = Document::factory()->for($workspace)->for($user, 'createdBy')->uploaded()->create();
        app(InventoryLegacyUploads::class)->handle(10);
        app(CloseLegacyUploadInitializationGate::class)->handle(10);
        CarbonImmutable::setTestNow('2026-08-31 12:00:00');
        $result = app(ReconcileLegacyUploadDrain::class)->handle(10);
        $this->assertSame(['expired' => 1, 'remaining' => 0, 'drain_closed' => true], $result);
        $this->assertDatabaseHas('documents', [
            'id' => $document->id, 'status' => DocumentStatus::Failed->value,
            'failure_category' => 'legacy_upload_drain_expired',
        ]);
        $this->assertDatabaseHas('legacy_upload_cutover_events', ['event_type' => 'drain_closed']);

        $this->expectException(LegacyUploadCutoverException::class);
        app(RequestLegacyDocumentIngestion::class)->handle($document->refresh(), (string) Str::uuid());
    }

    public function test_expiry_rejects_unmarked_noneligible_and_in_window_documents(): void
    {
        CarbonImmutable::setTestNow('2026-08-31 09:00:00');
        config()->set('imports.legacy_cutover.drain_window_hours', 4);
        [$user, $workspace] = $this->workspace();
        $document = Document::factory()->for($workspace)->for($user, 'createdBy')->uploading()->create();
        app(InventoryLegacyUploads::class)->handle(10);
        app(CloseLegacyUploadInitializationGate::class)->handle(10);
        CarbonImmutable::setTestNow('2026-08-31 10:00:00');

        $this->expectException(LegacyUploadCutoverException::class);
        app(ExpireLegacyDrainUpload::class)->handle($document);
    }

    public function test_internal_ingestion_action_remains_available_for_nonlegacy_promoted_documents(): void
    {
        [$user, $workspace] = $this->workspace();
        app(CloseLegacyUploadInitializationGate::class)->handle(10);
        app(ReconcileLegacyUploadDrain::class)->handle(10);
        $document = Document::factory()->for($workspace)->for($user, 'createdBy')->uploaded()->create();
        $this->assertNull($document->legacy_upload_initiated_before_cutover);
        $queued = app(RequestDocumentIngestion::class)->handle($document, (string) Str::uuid());
        $this->assertSame(DocumentStatus::Queued, $queued->status);
    }

    public function test_marker_identity_is_model_immutable_after_assignment(): void
    {
        [$user, $workspace] = $this->workspace();
        $document = Document::factory()->for($workspace)->for($user, 'createdBy')->uploading()->create();
        app(InventoryLegacyUploads::class)->handle(10);
        $document->refresh()->legacy_upload_cutover_operation_id = (string) Str::uuid();
        $this->expectException(\LogicException::class);
        $document->save();
    }

    /** @return array{User, Workspace} */
    private function workspace(): array
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->withOwner($user)->create();

        return [$user, $workspace];
    }
}
