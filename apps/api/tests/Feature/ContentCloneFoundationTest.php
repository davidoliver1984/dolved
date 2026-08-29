<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\IngestionAttemptOrigin;
use App\Enums\IngestionAttemptStatus;
use App\Exceptions\IngestionAttemptException;
use App\Models\Document;
use App\Models\IngestionEventClaim;
use App\Models\Workspace;
use App\Queries\Workspaces\GetWorkspaceUsage;
use App\Services\Ingestion\IngestionAttemptAuthorizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use LogicException;
use Tests\TestCase;

final class ContentCloneFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_clone_schema_and_origin_index_are_present(): void
    {
        $this->assertTrue(Schema::hasColumns('ingestion_event_claims', [
            'attempt_origin',
            'materialisation_pipeline_fingerprint',
            'materialisation_pipeline_components',
        ]));
        $this->assertTrue(Schema::hasTable('document_content_clone_operations'));
        $this->assertTrue(Schema::hasTable('document_content_clone_manifests'));
        $this->assertContains(
            'ingestion_attempts_origin_status',
            collect(Schema::getIndexes('ingestion_event_claims'))->pluck('name'),
        );
    }

    public function test_pipeline_identity_is_write_once_for_legacy_attempts(): void
    {
        $claim = IngestionEventClaim::factory()->create([
            'materialisation_pipeline_fingerprint' => null,
            'materialisation_pipeline_components' => null,
        ]);
        $claim->forceFill([
            'materialisation_pipeline_fingerprint' => str_repeat('a', 64),
            'materialisation_pipeline_components' => ['contract' => 'v1'],
        ])->save();

        $this->assertSame(str_repeat('a', 64), $claim->refresh()->materialisation_pipeline_fingerprint);
        $claim->materialisation_pipeline_fingerprint = str_repeat('b', 64);

        $this->expectException(LogicException::class);
        $claim->save();
    }

    public function test_ordinary_callback_authorizer_rejects_clone_origin(): void
    {
        $leaseToken = 'clone-lease';
        $claim = IngestionEventClaim::factory()->create([
            'attempt_origin' => IngestionAttemptOrigin::ContentClone,
            'status' => IngestionAttemptStatus::Open,
            'lease_token_hash' => hash('sha256', $leaseToken),
            'lease_expires_at' => now()->addMinute(),
        ]);

        $this->expectException(IngestionAttemptException::class);
        app(IngestionAttemptAuthorizer::class)->assert(
            $claim,
            $claim->event_id,
            $claim->workspace_public_id,
            $claim->document_public_id,
            $leaseToken,
        );
    }

    public function test_workspace_usage_never_merges_clone_failures_into_ingestion(): void
    {
        $workspace = Workspace::factory()->create();
        $ingestionDocument = Document::factory()->for($workspace)->create();
        $cloneDocument = Document::factory()->for($workspace)->create();
        foreach ([
            [$ingestionDocument, IngestionAttemptOrigin::Ingestion],
            [$cloneDocument, IngestionAttemptOrigin::ContentClone],
        ] as [$document, $origin]) {
            IngestionEventClaim::factory()->for($document)->create([
                'workspace_id' => $workspace->id,
                'attempt_origin' => $origin,
                'status' => IngestionAttemptStatus::Failed,
                'claimed_at' => now()->subMinute(),
                'failed_at' => now()->subMinute(),
            ]);
        }

        $usage = app(GetWorkspaceUsage::class)->handle($workspace, '7d');

        $this->assertSame(1, $usage['historical']['ingestion_failures']);
        $this->assertSame(1, $usage['historical']['content_clone_failures']);
        $this->assertCount(2, $usage['historical']['materialisation_attempts']);
    }
}
