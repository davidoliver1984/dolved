<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Imports\AssessImportItemMatches;
use App\Enums\ChecksumVerificationStatus;
use App\Enums\DocumentGovernanceStatus;
use App\Enums\DocumentStatus;
use App\Enums\ImportBatchStatus;
use App\Enums\ImportMatchStatus;
use App\Enums\ImportPreflightStatus;
use App\Exceptions\ImportPreflightException;
use App\Models\Document;
use App\Models\DocumentFamily;
use App\Models\ImportBatch;
use App\Models\ImportItem;
use App\Models\User;
use App\Models\Workspace;
use App\Support\Imports\WorkspaceChecksumLock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;
use Tests\TestCase;

final class ImportMatchingTest extends TestCase
{
    use RefreshDatabase;

    public function test_verified_checksum_matches_are_workspace_scoped_and_classified_by_liveness(): void
    {
        [$workspace, , $item] = $this->verifiedItem('Medication Procedure.pdf');
        $checksum = $item->source_checksum_sha256;
        $live = $this->document($workspace, $this->family($workspace), $checksum, DocumentStatus::Indexed, '00000000-0000-4000-8000-000000000001');
        $withdrawn = $this->document($workspace, $this->family($workspace), $checksum, DocumentStatus::Failed, '00000000-0000-4000-8000-000000000002', DocumentGovernanceStatus::Withdrawn);
        $uploaded = $this->document($workspace, $this->family($workspace), $checksum, DocumentStatus::Uploaded, '00000000-0000-4000-8000-000000000003');
        $queued = $this->document($workspace, $this->family($workspace), $checksum, DocumentStatus::Queued, '00000000-0000-4000-8000-000000000004');
        $processing = $this->document($workspace, $this->family($workspace), $checksum, DocumentStatus::Processing, '00000000-0000-4000-8000-000000000005');
        $deleting = $this->document($workspace, $this->family($workspace), $checksum, DocumentStatus::Deleting, '00000000-0000-4000-8000-000000000006');
        $deleted = $this->document($workspace, $this->family($workspace), $checksum, DocumentStatus::Deleted, '00000000-0000-4000-8000-000000000007');

        $otherWorkspace = Workspace::factory()->create();
        $this->document(
            $otherWorkspace,
            DocumentFamily::factory()->for($otherWorkspace)->create(),
            $checksum,
            DocumentStatus::Indexed,
            '00000000-0000-4000-8000-000000000008',
        );

        $result = app(AssessImportItemMatches::class)->handle($item);

        $this->assertSame('family-title-levenshtein-v1', $result['profile_version']);
        $this->assertSame(
            [$live->public_id, $withdrawn->public_id, $uploaded->public_id, $queued->public_id, $processing->public_id],
            array_column($result['exact_live_duplicates'], 'document_id'),
        );
        $this->assertSame([$deleting->public_id, $deleted->public_id], array_column($result['deleted_duplicates'], 'document_id'));
        $this->assertSame($live->public_id, $result['applicability_only_redirect_document_id']);
        $this->assertSame([], $result['family_candidates']);
        $this->assertSame(ImportMatchStatus::Pending, $item->refresh()->match_status);
    }

    public function test_family_candidates_use_only_normalised_filename_and_title_with_deterministic_bounded_order(): void
    {
        [$workspace, , $item] = $this->verifiedItem('  MÉDICATION---PROCEDURE.v2.PDF  ');
        $identicalIds = [
            '00000000-0000-4000-8000-000000000014',
            '00000000-0000-4000-8000-000000000012',
            '00000000-0000-4000-8000-000000000013',
            '00000000-0000-4000-8000-000000000011',
            '00000000-0000-4000-8000-000000000010',
            '00000000-0000-4000-8000-000000000015',
        ];
        foreach ($identicalIds as $publicId) {
            DocumentFamily::factory()->for($workspace)->create([
                'public_id' => $publicId,
                'name' => 'Médication procedure v2',
            ]);
        }
        DocumentFamily::factory()->for($workspace)->create([
            'public_id' => '00000000-0000-4000-8000-000000000020',
            'name' => 'Medication procedure',
        ]);
        $tombstoned = DocumentFamily::factory()->for($workspace)->create([
            'public_id' => '00000000-0000-4000-8000-000000000001',
            'name' => 'Médication procedure v2',
        ]);
        DB::table('document_families')->where('id', $tombstoned->id)->update(['tombstoned_at' => now()]);
        $otherWorkspace = Workspace::factory()->create();
        DocumentFamily::factory()->for($otherWorkspace)->create([
            'public_id' => '00000000-0000-4000-8000-000000000002',
            'name' => 'Médication procedure v2',
        ]);
        DocumentFamily::factory()->for($workspace)->create([
            'public_id' => '00000000-0000-4000-8000-000000000003',
            'name' => "Medication\u{0007} procedure v2",
        ]);

        $result = app(AssessImportItemMatches::class)->handle($item);

        $this->assertSame([], $result['exact_live_duplicates']);
        $this->assertSame([], $result['deleted_duplicates']);
        $this->assertNull($result['applicability_only_redirect_document_id']);
        $this->assertCount(5, $result['family_candidates']);
        sort($identicalIds);
        $this->assertSame(array_slice($identicalIds, 0, 5), array_column($result['family_candidates'], 'family_id'));
        $this->assertSame([10000, 10000, 10000, 10000, 10000], array_column($result['family_candidates'], 'score_basis_points'));
    }

    public function test_matching_fails_closed_for_unverified_or_unsafe_source_identity(): void
    {
        [, , $pending] = $this->pendingItem('Medication.pdf');
        try {
            app(AssessImportItemMatches::class)->handle($pending);
            $this->fail('A non-verified import item was matched.');
        } catch (ImportPreflightException $exception) {
            $this->assertSame('matching_not_eligible', $exception->reason);
        }

        [, , $unsafe] = $this->verifiedItem("Medication\u{0007}.pdf");
        try {
            app(AssessImportItemMatches::class)->handle($unsafe);
            $this->fail('A control-bearing source filename was matched.');
        } catch (ImportPreflightException $exception) {
            $this->assertSame('unsupported_source_filename', $exception->reason);
        }
    }

    public function test_workspace_checksum_lock_requires_a_transaction_and_persists_one_identity_per_workspace(): void
    {
        $workspace = Workspace::factory()->create();
        $otherWorkspace = Workspace::factory()->create();
        $checksum = str_repeat('a', 64);
        $locks = app(WorkspaceChecksumLock::class);

        DB::transaction(function () use ($locks, $workspace, $checksum): void {
            $first = $locks->acquire($workspace->id, $checksum);
            $second = $locks->acquire($workspace->id, $checksum);
            $this->assertTrue($first->is($second));

            try {
                $locks->acquire($workspace->id, 'not-a-checksum');
                $this->fail('An invalid checksum lock identity was accepted.');
            } catch (LogicException) {
                $this->assertTrue(true);
            }
        });
        DB::transaction(fn () => $locks->acquire($otherWorkspace->id, $checksum));

        $this->assertDatabaseCount('workspace_checksum_reservations', 2);
        $this->assertDatabaseHas('workspace_checksum_reservations', [
            'workspace_id' => $workspace->id,
            'source_checksum_sha256' => $checksum,
        ]);

        $rollbackChecksum = str_repeat('b', 64);
        try {
            DB::transaction(function () use ($locks, $workspace, $rollbackChecksum): void {
                $locks->acquire($workspace->id, $rollbackChecksum);
                throw new LogicException('synthetic rollback');
            });
        } catch (LogicException) {
            $this->assertDatabaseMissing('workspace_checksum_reservations', [
                'workspace_id' => $workspace->id,
                'source_checksum_sha256' => $rollbackChecksum,
            ]);
        }
        DB::transaction(fn () => $locks->acquire($workspace->id, $rollbackChecksum));
        $this->assertDatabaseHas('workspace_checksum_reservations', [
            'workspace_id' => $workspace->id,
            'source_checksum_sha256' => $rollbackChecksum,
        ]);
    }

    /** @return array{Workspace, ImportBatch, ImportItem} */
    private function verifiedItem(string $sourceFilename): array
    {
        return $this->item($sourceFilename, ImportPreflightStatus::Verified);
    }

    /** @return array{Workspace, ImportBatch, ImportItem} */
    private function pendingItem(string $sourceFilename): array
    {
        return $this->item($sourceFilename, ImportPreflightStatus::Pending);
    }

    /** @return array{Workspace, ImportBatch, ImportItem} */
    private function item(string $sourceFilename, ImportPreflightStatus $status): array
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
        $verified = $status === ImportPreflightStatus::Verified;
        $item = ImportItem::query()->create([
            'public_id' => $publicId,
            'import_batch_id' => $batch->id,
            'workspace_id' => $workspace->id,
            'staged_object_key' => "imports/workspaces/{$workspace->public_id}/items/{$publicId}/source",
            'source_filename' => $sourceFilename,
            'source_checksum_sha256' => $verified ? str_repeat('a', 64) : null,
            'media_type' => $verified ? 'application/pdf' : null,
            'size_bytes' => $verified ? 100 : null,
            'preflight_status' => $status,
            'match_status' => ImportMatchStatus::Pending,
        ]);

        return [$workspace, $batch, $item];
    }

    private function document(
        Workspace $workspace,
        DocumentFamily $family,
        string $checksum,
        DocumentStatus $status,
        string $publicId,
        DocumentGovernanceStatus $governance = DocumentGovernanceStatus::Draft,
    ): Document {
        return Document::factory()->for($workspace)->for($family, 'family')->create([
            'public_id' => $publicId,
            'status' => $status,
            'governance_status' => $governance,
            'approved_at' => $governance === DocumentGovernanceStatus::Withdrawn ? now()->subMinute() : null,
            'withdrawn_at' => $governance === DocumentGovernanceStatus::Withdrawn ? now() : null,
            'source_checksum_sha256' => $checksum,
            'checksum_verification_status' => ChecksumVerificationStatus::Verified,
            'failure_category' => $status === DocumentStatus::Failed ? 'synthetic_failure' : null,
            'failure_message' => $status === DocumentStatus::Failed ? 'Synthetic failure.' : null,
        ]);
    }

    private function family(Workspace $workspace): DocumentFamily
    {
        return DocumentFamily::factory()->for($workspace)->create(['name' => 'Unrelated title']);
    }
}
