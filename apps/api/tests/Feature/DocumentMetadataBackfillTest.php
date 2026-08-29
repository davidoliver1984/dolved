<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Documents\BackfillDocumentMetadata;
use App\Enums\ChecksumUnavailableReason;
use App\Enums\ChecksumVerificationStatus;
use App\Enums\DocumentGovernanceActorType;
use App\Enums\DocumentGovernanceSystemActorCode;
use App\Exceptions\DocumentUploadException;
use App\Models\Document;
use App\Models\DocumentFamily;
use App\Models\DocumentGovernanceAuditEvent;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Documents\DocumentObjectStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentMetadataBackfillTest extends TestCase
{
    use RefreshDatabase;

    public function test_backfill_streams_checksums_reinterprets_titles_and_is_resumable(): void
    {
        Storage::fake('metadata-backfill');
        config()->set('documents.storage_disk', 'metadata-backfill');
        $workspace = Workspace::factory()->create();
        $owner = User::factory()->create();
        $family = DocumentFamily::factory()->for($workspace)->create([
            'name' => 'medication_policy-final.pdf',
            'owner_user_id' => $owner->id,
        ]);
        $bytes = str_repeat('source', 1024);
        $document = Document::factory()
            ->for($workspace)
            ->for($owner, 'createdBy')
            ->for($family, 'family')
            ->create([
                'source_filename' => 'medication_policy-final.pdf',
                'size_bytes' => strlen($bytes),
            ]);
        Storage::disk('metadata-backfill')->put($document->storage_key, $bytes);

        $first = app(BackfillDocumentMetadata::class)->handle(10);
        $second = app(BackfillDocumentMetadata::class)->handle(10);

        $this->assertSame(1, $first['titles']);
        $this->assertSame(1, $first['checksums_verified']);
        $this->assertSame(0, $first['remaining']);
        $this->assertSame(0, array_sum(array_values($second)));
        $this->assertSame('medication policy final', $family->refresh()->name);
        $this->assertSame(ChecksumVerificationStatus::Verified, $document->refresh()->checksum_verification_status);
        $this->assertSame(hash('sha256', $bytes), $document->source_checksum_sha256);
        $this->assertSame(1, DocumentGovernanceAuditEvent::query()
            ->where('document_id', $document->id)
            ->where('system_actor_code', DocumentGovernanceSystemActorCode::ChecksumBackfill->value)
            ->count());
        $this->assertTrue($family->governanceAuditEvents()
            ->where('system_actor_code', DocumentGovernanceSystemActorCode::AuditTargetScopeBackfill->value)
            ->exists());
    }

    public function test_confirmed_absence_and_size_mismatch_are_truthfully_unavailable(): void
    {
        Storage::fake('metadata-backfill');
        config()->set('documents.storage_disk', 'metadata-backfill');
        $missing = Document::factory()->create(['source_filename' => 'missing.pdf']);
        $mismatch = Document::factory()->create([
            'source_filename' => 'mismatch.pdf',
            'size_bytes' => 100,
        ]);
        Storage::disk('metadata-backfill')->put($mismatch->storage_key, 'short');

        $summary = app(BackfillDocumentMetadata::class)->handle(10);

        $this->assertSame(2, $summary['checksums_unavailable']);
        $this->assertSame(ChecksumUnavailableReason::SourceMissing, $missing->refresh()->checksum_unavailable_reason);
        $this->assertSame(ChecksumUnavailableReason::SourceUnrecoverable, $mismatch->refresh()->checksum_unavailable_reason);
        $this->assertNull($missing->source_checksum_sha256);
        $this->assertNull($mismatch->source_checksum_sha256);
    }

    public function test_transient_storage_failure_remains_pending_and_retryable(): void
    {
        $document = Document::factory()->create(['source_filename' => 'retry.pdf']);
        $storage = $this->mock(DocumentObjectStorage::class);
        $storage->shouldReceive('streamedIdentity')
            ->once()
            ->withArgs(fn (Document $candidate): bool => $candidate->is($document))
            ->andThrow(DocumentUploadException::storageUnavailable());

        $summary = app(BackfillDocumentMetadata::class)->handle(10);

        $this->assertSame(1, $summary['checksums_retryable']);
        $this->assertSame(1, $summary['remaining']);
        $this->assertSame(ChecksumVerificationStatus::Pending, $document->refresh()->checksum_verification_status);
        $this->assertFalse(DocumentGovernanceAuditEvent::query()
            ->where('document_id', $document->id)
            ->where('system_actor_code', DocumentGovernanceSystemActorCode::ChecksumBackfill->value)
            ->exists());
    }

    public function test_owner_backfill_uses_lineage_root_identity_and_records_system_provenance(): void
    {
        Storage::fake('metadata-backfill');
        config()->set('documents.storage_disk', 'metadata-backfill');
        $workspace = Workspace::factory()->create();
        $creator = User::factory()->create();
        $family = DocumentFamily::factory()->for($workspace)->create();
        $document = Document::factory()
            ->for($workspace)
            ->for($creator, 'createdBy')
            ->for($family, 'family')
            ->create(['source_filename' => 'owner.pdf', 'size_bytes' => 4]);
        Storage::disk('metadata-backfill')->put($document->storage_key, str_repeat('x', $document->size_bytes));
        $this->makeLegacyOwnerNullable($family);

        $summary = app(BackfillDocumentMetadata::class)->handle(10);

        $this->assertSame(1, $summary['owners']);
        $this->assertSame($creator->id, $family->refresh()->owner_user_id);
        $event = $family->governanceAuditEvents()
            ->where('system_actor_code', DocumentGovernanceSystemActorCode::OwnerBackfillLineageRoot->value)
            ->sole();
        $this->assertSame(DocumentGovernanceActorType::System, $event->actor_type);
        $this->assertNull($event->actor_user_id);
    }

    public function test_owner_backfill_falls_back_to_workspace_creator_when_a_family_has_no_root(): void
    {
        $workspace = Workspace::factory()->create();
        $family = DocumentFamily::factory()->for($workspace)->create();
        $this->makeLegacyOwnerNullable($family);

        $summary = app(BackfillDocumentMetadata::class)->handle(10);

        $this->assertSame(1, $summary['owners']);
        $this->assertSame($workspace->created_by_user_id, $family->refresh()->owner_user_id);
        $this->assertTrue($family->governanceAuditEvents()
            ->where('system_actor_code', DocumentGovernanceSystemActorCode::OwnerBackfillWorkspaceCreatorFallback->value)
            ->exists());
    }

    public function test_batch_size_bounds_each_lane_and_command_rejects_invalid_values(): void
    {
        Storage::fake('metadata-backfill');
        config()->set('documents.storage_disk', 'metadata-backfill');
        Document::factory()->count(2)->sequence(
            ['source_filename' => 'one.pdf'],
            ['source_filename' => 'two.pdf'],
        )->create();

        $summary = app(BackfillDocumentMetadata::class)->handle(1);

        $this->assertSame(1, $summary['checksums_unavailable']);
        $this->assertGreaterThanOrEqual(1, $summary['remaining']);
        $this->artisan('documents:backfill-metadata', ['--batch-size' => 0])
            ->assertFailed();
    }

    private function makeLegacyOwnerNullable(DocumentFamily $family): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE document_families DROP CONSTRAINT document_families_owner_required_check');
        }

        DB::table('document_families')->where('id', $family->id)->update(['owner_user_id' => null]);

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<'SQL'
                ALTER TABLE document_families
                ADD CONSTRAINT document_families_owner_required_check
                CHECK (owner_user_id IS NOT NULL) NOT VALID
                SQL);
        }
    }
}
